<?php
defined('ABSPATH') || exit;

function sanro_studio_collection_id($id){return is_string($id)&&preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',$id);}
function sanro_studio_collection_cas($key,$before,$after){
    $db=sanro_studio_lease_database();if(!$db)return false;
    if($before===false)return add_option($key,$after,'',false);
    if($before===$after)return true;
    $n=$db->query($db->prepare("UPDATE `{$db->options}` SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s",maybe_serialize($after),$key,maybe_serialize($before)));
    sanro_studio_lease_cache($key);return $n===1;
}
/** Durable identity key is separate from login salts; rotating WP auth salts must not orphan permanent collections. */
function sanro_studio_collection_owner_key($email){
    $email=sanro_studio_repeat_email($email);if($email==='')return null;
    $name='sanro_studio_collection_identity_key';$secret=get_option($name,false);
    if($secret===false){add_option($name,bin2hex(random_bytes(32)),'',false);sanro_studio_lease_cache($name);$secret=get_option($name,false);}
    return is_string($secret)&&preg_match('/^[a-f0-9]{64}$/D',$secret)?hash_hmac('sha256',$email,$secret):null;
}
/** Short-lived bearer sessions are independent from drafts and only created from verified email proof. */
function sanro_studio_collection_session_create($email){
    $owner=sanro_studio_collection_owner_key($email);if(!$owner)return ['valid'=>false,'error'=>'collection_identity'];
    $token=bin2hex(random_bytes(32));$record=['owner'=>$owner,'email'=>$email,'expires'=>time()+3600];
    if(!set_transient('sanro_collection_session_'.hash('sha256',$token),$record,3600))return ['valid'=>false,'error'=>'collection_session'];
    return ['valid'=>true,'collection_token'=>$token,'expires_at'=>$record['expires']];
}
function sanro_studio_collection_session($token){
    if(!is_string($token)||!preg_match('/^[a-f0-9]{64}$/D',$token))return false;
    $value=get_transient('sanro_collection_session_'.hash('sha256',$token));
    return is_array($value)&&is_string($value['owner']??null)&&preg_match('/^[a-f0-9]{64}$/D',$value['owner'])&&($value['expires']??0)>time()&&($value['owner']??null)===sanro_studio_collection_owner_key($value['email']??'')?$value:false;
}
function sanro_studio_collection_access_request($email){
    $email=sanro_studio_repeat_email($email);$hash=sanro_studio_collection_owner_key($email);
    if(!$hash||!sanro_studio_repeat_mail_budget($email))return ['valid'=>false,'error'=>'verification_rate_limit'];
    $code=bin2hex(random_bytes(16));$key='sanro_collection_access_'.$hash;
    if(!set_transient($key,['hash'=>hash('sha256',$code),'expires'=>time()+1800],1800))return ['valid'=>false,'error'=>'verification_unavailable'];
    if(!function_exists('wp_mail')||!wp_mail($email,'SANRO Studio — Desain Saya','Kode akses koleksi (30 menit): '.$code)){
        delete_transient($key);return ['valid'=>false,'error'=>'verification_delivery'];
    }
    return ['valid'=>true]; // Same response for empty and populated collections.
}
function sanro_studio_collection_access_verify($email,$code){
    $email=sanro_studio_repeat_email($email);$hash=sanro_studio_collection_owner_key($email);
    if(!$hash||!is_string($code)||!preg_match('/^[a-f0-9]{32}$/D',$code))return ['valid'=>false,'error'=>'verification_invalid'];
    $lease=sanro_studio_lease_acquire('sanro_collection_verify_'.$hash,30);if(!$lease)return ['valid'=>false,'error'=>'verification_busy'];
    try{$key='sanro_collection_access_'.$hash;$proof=get_transient($key);
        if(!is_array($proof)||($proof['expires']??0)<=time()||!hash_equals((string)($proof['hash']??''),hash('sha256',$code)))return ['valid'=>false,'error'=>'verification_invalid'];
        if(!delete_transient($key))return ['valid'=>false,'error'=>'verification_conflict'];
        return sanro_studio_collection_session_create($email);
    }finally{sanro_studio_lease_release($lease);}
}
function sanro_studio_collection_authorize($id,$session_token=null,$share_token=null){
    if(!sanro_studio_collection_id($id))return false;$entry=get_option('sanro_studio_library_'.$id,false);
    if(!is_array($entry)||($entry['status']??null)!=='active')return false;
    $session=sanro_studio_collection_session($session_token);
    if($session&&hash_equals((string)($entry['owner']??''),$session['owner']))return ['entry'=>$entry,'session'=>$session,'owner'=>true];
    if(is_string($share_token)&&preg_match('/^[a-f0-9]{64}$/D',$share_token)&&is_string($entry['share_hash']??null)&&hash_equals($entry['share_hash'],hash('sha256',$share_token)))return ['entry'=>$entry,'owner'=>false];
    return false;
}
/** Read an authenticated snapshot reference after source draft TTL; no client paths. */
function sanro_studio_collection_asset($entry,$ref,$side,$master=false){
    $id=$entry['source_design_id']??null;
    if(!sanro_studio_collection_id($id)||!in_array($side,['front','back'],true)||!is_string($ref)||!preg_match('/^'.preg_quote($id.'-'.$side.'-','/').'([a-f0-9]{64})\.(png|jpg|webp)$/D',$ref,$m))return ['valid'=>false,'error'=>'asset_reference'];
    $constant=$master?'SANRO_STUDIO_PRIVATE_MASTER_DIR':'SANRO_STUDIO_PRIVATE_PREVIEW_DIR';
    $roots=sanro_studio_storage_roots();if($roots===false||!defined($constant))return ['valid'=>false,'error'=>'asset_storage'];
    $dir=realpath(constant($constant));$path=$dir.DIRECTORY_SEPARATOR.$ref;$limit=$master?10485760:51200;
    if(!$dir||is_link($path)||!is_file($path)||!is_readable($path)||filesize($path)>$limit)return ['valid'=>false,'error'=>'asset_unavailable'];
    $bytes=@file_get_contents($path);$checked=$master?sanro_studio_validate_master_bytes($bytes):sanro_studio_validate_preview_bytes($bytes);
    $ext=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'][$checked['mime']??'']??null;
    if(empty($checked['valid'])||!hash_equals($m[1],$checked['sha256'])||$m[2]!==$ext)return ['valid'=>false,'error'=>'asset_integrity'];
    return ['valid'=>true,'bytes'=>$bytes,'mime'=>$checked['mime']];
}
function sanro_studio_collection_save($id,$token,$title){
    if(!sanro_studio_collection_id($id)||!is_string($title)||strlen($title)>240)return ['valid'=>false,'error'=>'collection_input'];
    return sanro_studio_storage_transaction(null,0,static function()use($id,$token,$title){
        $draft=sanro_studio_read_draft($id,$token);$record=get_option('sanro_studio_draft_'.$id,false);
        if(empty($draft['valid'])||!is_array($record)||empty(sanro_studio_public_editor_config($record['product_id'],true)['valid'])||empty(sanro_studio_validate_product_design($record['product_id'],$record['design'])['valid'])||!sanro_studio_draft_image_binding_allowed($record['design'],$record['master_refs']??[],$id))return ['valid'=>false,'error'=>'collection_draft'];
        $email=sanro_studio_repeat_verified_email($record);$owner=sanro_studio_collection_owner_key($email);
        if(!$owner)return ['valid'=>false,'error'=>'collection_email_required'];
        $library_id=$id;$origin=$record['library_origin_id']??null;
        if(sanro_studio_collection_id($origin)){$original=get_option('sanro_studio_library_'.$origin,false);if(is_array($original)&&($original['status']??null)==='active'&&($original['owner']??null)===$owner)$library_id=$origin;}
        $key='sanro_studio_library_'.$library_id;$before=get_option($key,false);
        if($before!==false&&(!is_array($before)||($before['owner']??null)!==$owner))return ['valid'=>false,'error'=>'collection_owner'];
        $entry=array_intersect_key($record,array_flip(['product_id','design','master_refs','original_master_refs','preview_refs','preview_refs_by_size','preview_variant_required','preview_design_hash']));
        $entry+=['version'=>1,'status'=>'active','owner'=>$owner,'source_design_id'=>$id,
            'title'=>sanitize_text_field($title),'updated_at'=>time()];
        $entry['public_design_id']=function_exists('sanro_studio_public_design_id')?sanro_studio_public_design_id($id):null;
        // Saving a new snapshot revokes old share links rather than silently republishing changes.
        if(!sanro_studio_collection_cas($key,$before,$entry))return ['valid'=>false,'error'=>'collection_conflict'];
        $session=sanro_studio_collection_session_create($email);return $session+['id'=>$library_id];
    });
}
function sanro_studio_collection_mutate($id,$token,$action){
    return sanro_studio_storage_transaction(null,0,static function()use($id,$token,$action){
        $auth=sanro_studio_collection_authorize($id,$token);if(!$auth||!$auth['owner'])return ['valid'=>false,'error'=>'collection_not_found'];
        $before=$auth['entry'];$after=$before;$result=['valid'=>true];
        if($action==='share'){$secret=bin2hex(random_bytes(32));$after['share_hash']=hash('sha256',$secret);$result['share_token']=$secret;}
        elseif($action==='revoke')unset($after['share_hash']);
        elseif($action==='delete'){$after=['version'=>1,'status'=>'deleted','owner'=>$before['owner'],'updated_at'=>time()];}
        else return ['valid'=>false,'error'=>'collection_action'];
        return sanro_studio_collection_cas('sanro_studio_library_'.$id,$before,$after)?$result:['valid'=>false,'error'=>'collection_conflict'];
    });
}
/** Reuse and shared fork copy immutable assets into a fresh draft namespace; no order/session/payment inheritance. */
function sanro_studio_collection_fork($id,$token=null,$share=null){
    return sanro_studio_storage_transaction(null,0,static function()use($id,$token,$share){
        $auth=sanro_studio_collection_authorize($id,$token,$share);if(!$auth)return ['valid'=>false,'error'=>'collection_not_found'];$entry=$auth['entry'];
        if(empty(sanro_studio_public_editor_config($entry['product_id'])['valid'])||empty(sanro_studio_validate_product_design($entry['product_id'],$entry['design'])['valid']))return ['valid'=>false,'error'=>'collection_product_changed'];
        if(!sanro_studio_draft_creation_budget())return ['valid'=>false,'error'=>'draft_rate_limit'];
        $created=sanro_studio_create_draft($entry['product_id'],$entry['design']);if(empty($created['valid']))return $created;
        $new_id=$created['design_id'];$key='sanro_studio_draft_'.$new_id;$before=get_option($key,false);$after=$before;
        foreach(['master_refs','original_master_refs','preview_refs','preview_refs_by_size'] as $group){
            if(!isset($entry[$group]))continue;$copy=function($values)use(&$copy,$entry,$group,$new_id){$output=[];
                foreach($values as $side=>$ref){if(is_array($ref)){$nested=$copy($ref);if(empty($nested['valid']))return $nested;$output[$side]=$nested['refs'];continue;}
                    $master=in_array($group,['master_refs','original_master_refs'],true);$read=sanro_studio_collection_asset($entry,$ref,$side,$master);if(empty($read['valid']))return $read;
                    $write=$master?sanro_studio_store_private_master($new_id,$side,$read['bytes']):sanro_studio_store_private_preview($new_id,$side,$read['bytes']);if(empty($write['valid']))return $write;$output[$side]=$write['file_id'];
                }return ['valid'=>true,'refs'=>$output];};
            $copied=$copy($entry[$group]);if(empty($copied['valid'])){delete_option($key);return $copied;}$after[$group]=$copied['refs'];
        }
        if(isset($entry['preview_variant_required']))$after['preview_variant_required']=$entry['preview_variant_required'];
        if(isset($entry['preview_design_hash']))$after['preview_design_hash']=$entry['preview_design_hash'];
        if($auth['owner']){$after['verified_owner']=['version'=>1,'email'=>$auth['session']['email'],'verified_at'=>time(),'token_binding'=>$after['token_hash']];$after['library_origin_id']=$id;}
        if(!sanro_studio_collection_cas($key,$before,$after))return ['valid'=>false,'error'=>'collection_conflict'];
        return $created+['product_id'=>$entry['product_id']];
    });
}
function sanro_studio_collection_public_entry($id,$entry){$public=$entry['public_design_id']??null;if(!is_string($public)&&function_exists('sanro_studio_public_design_id'))$public=sanro_studio_public_design_id($entry['source_design_id']??'');return ['id'=>$id,'public_design_id'=>is_string($public)?$public:null,'title'=>$entry['title'],'product_id'=>$entry['product_id'],'updated_at'=>$entry['updated_at'],'shared'=>isset($entry['share_hash'])];}
function sanro_studio_collection_routes(){
    if(!sanro_studio_draft_rest_enabled())return;
    $routes=[
        ['/drafts/(?P<id>[a-f0-9-]{36})/collection','POST','save'],
        ['/collection/access/(?P<operation>request|verify)','POST','access'],
        ['/collection','GET','list'],
        ['/collection/logout','POST','logout'],
        ['/collection/(?P<id>[a-f0-9-]{36})/(?P<operation>share|revoke|delete|fork)','POST','mutate'],
        ['/collection/(?P<id>[a-f0-9-]{36})','GET','read'],
        ['/collection/(?P<id>[a-f0-9-]{36})/preview/(?P<side>front|back)','GET','preview'],
    ];
    foreach($routes as [$route,$method,$action])register_rest_route('sanro-studio/v1',$route,['methods'=>$method,'permission_callback'=>'sanro_studio_rest_draft_nonce','callback'=>static function($request)use($action){return sanro_studio_collection_rest($request,$action);}]);
}
function sanro_studio_collection_rest($r,$action){
    $id=$r->get_param('id');$token=$r->get_header('X-Sanro-Collection-Token');$share=$r->get_header('X-Sanro-Share-Token');$op=$r->get_param('operation');$result=['valid'=>false,'error'=>'collection_not_found'];
    if($action==='save')$result=sanro_studio_collection_save($id,$r->get_header('X-Sanro-Owner-Token'),$r->get_param('title')??'Desain saya');
    elseif($action==='access')$result=$op==='request'?sanro_studio_collection_access_request($r->get_param('email')):sanro_studio_collection_access_verify($r->get_param('email'),$r->get_param('code'));
    elseif($action==='mutate')$result=$op==='fork'?sanro_studio_collection_fork($id,$token,$share):sanro_studio_collection_mutate($id,$token,$op);
    elseif($action==='logout'){if(sanro_studio_collection_session($token))$result=['valid'=>delete_transient('sanro_collection_session_'.hash('sha256',$token))];}
    elseif($action==='list'){
        $session=sanro_studio_collection_session($token);$cursor=$r->get_param('cursor')??'';
        if($session&&is_string($cursor)&&($cursor===''||preg_match('/^sanro_studio_library_[a-f0-9-]{36}$/D',$cursor))){
            $rows=sanro_studio_storage_option_rows('sanro_studio_library_',$cursor);$entries=[];
            if($rows!==false){foreach($rows as $row){$cursor=$row['option_name'];$entry=maybe_unserialize($row['option_value']);if(is_array($entry)&&($entry['status']??null)==='active'&&($entry['owner']??null)===$session['owner'])$entries[]=sanro_studio_collection_public_entry(substr($cursor,21),$entry);}
                $result=['valid'=>true,'entries'=>$entries,'cursor'=>count($rows)===200?$cursor:null];}
        }
    }else{
        $auth=sanro_studio_collection_authorize($id,$token,$share);
        if($auth){$entry=$auth['entry'];$result=['valid'=>true,'entry'=>sanro_studio_collection_public_entry($id,$entry)];
            if($action==='preview'){$side=$r->get_param('side');$ref=$entry['preview_refs'][$side]??null;
                $result=is_string($entry['preview_design_hash']??null)&&hash_equals(hash('sha256',maybe_serialize($entry['design'])),$entry['preview_design_hash'])?sanro_studio_collection_asset($entry,$ref,$side):['valid'=>false,'error'=>'preview_not_current'];
                if(!empty($result['valid'])){$result['image_base64']=base64_encode($result['bytes']);unset($result['bytes']);}
            }
        }
    }
    if(empty($result['valid']))return new WP_Error('sanro_'.($result['error']??'collection_unavailable'),'Koleksi belum dapat diakses: '.($result['error']??'unavailable'),['status'=>($result['error']??'')==='storage_capacity'?507:409]);
    $response=rest_ensure_response($result);$response->header('Cache-Control','private, no-store, max-age=0');$response->header('X-Content-Type-Options','nosniff');return $response;
}
