<?php
/** Real native modules and filesystem; only WP/Woo/SMTP persistence boundaries are simulated. No remote writes. */
$root=sys_get_temp_dir().'/sanro-native-'.bin2hex(random_bytes(6));foreach([$root,$root.'/web',$root.'/master',$root.'/preview'] as $dir)mkdir($dir,0700);
define('SANRO_STUDIO_ENABLE_STORAGE_GC',true);define('ABSPATH',$root.'/web/');define('ARRAY_A','ARRAY_A');define('DAY_IN_SECONDS',86400);define('HOUR_IN_SECONDS',3600);
define('SANRO_STUDIO_ENABLE_PRIVATE_MASTERS',true);define('SANRO_STUDIO_ENABLE_MASTER_UPLOAD',true);define('SANRO_STUDIO_ENABLE_PRIVATE_PREVIEWS',true);
define('SANRO_STUDIO_PRIVATE_MASTER_DIR',$root.'/master');define('SANRO_STUDIO_PRIVATE_PREVIEW_DIR',$root.'/preview');define('SANRO_STUDIO_PRIVATE_QUOTA_BYTES',2097152);
$_SERVER['DOCUMENT_ROOT']=$root.'/web';$_SERVER['REMOTE_ADDR']='192.0.2.44';$GLOBALS['options']=[];$GLOBALS['transients']=[];$GLOBALS['matrix']=[];$GLOBALS['orders']=[];
register_shutdown_function(function()use($root){$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $file){if($file->isDir()&&!$file->isLink())rmdir($file->getPathname());else unlink($file->getPathname());}rmdir($root);});
function absint($v){return abs((int)$v);}function maybe_serialize($v){return serialize($v);}function maybe_unserialize($v){return unserialize($v,['allowed_classes'=>false]);}
function wp_salt($v){return $GLOBALS['auth_salt']??'native-test-secret-salt-20261124';}function wp_cache_delete(...$a){}function sanitize_text_field($v){return trim(strip_tags($v));}function is_email($v){return filter_var($v,FILTER_VALIDATE_EMAIL)!==false;}
function wp_generate_uuid4(){$hex=bin2hex(random_bytes(16));return substr($hex,0,8).'-'.substr($hex,8,4).'-4'.substr($hex,13,3).'-a'.substr($hex,17,3).'-'.substr($hex,20);}
function get_option($k,$default=false){return $GLOBALS['options'][$k]??$default;}function add_option($k,$v,...$a){if(isset($GLOBALS['options'][$k]))return false;$GLOBALS['options'][$k]=$v;return true;}function delete_option($k){$ex=isset($GLOBALS['options'][$k]);unset($GLOBALS['options'][$k]);return $ex;}
function get_transient($k){return $GLOBALS['transients'][$k]??false;}function set_transient($k,$v,$ttl){$GLOBALS['transients'][$k]=$v;return true;}function delete_transient($k){$ex=isset($GLOBALS['transients'][$k]);unset($GLOBALS['transients'][$k]);return $ex;}
function wp_mail($email,$subject,$body){$GLOBALS['mail']=compact('email','subject','body');return true;}
function get_post_meta($id,$key,$single=true){return match($key){'_sanro_studio_mockup_matrix'=>$GLOBALS['matrix'],'_sanro_studio_safe_areas'=>['front'=>['width'=>32,'height'=>48],'back'=>['width'=>32,'height'=>48]],default=>''};}
function sanro_studio_product_pricing_config($id){return ['valid'=>true,'profile'=>'cotton_combed_24s','sizes'=>['S','L']];}
function wc_get_product($id){return $id===7?new class{function get_status(){return 'publish';}function is_purchasable(){return true;}function is_type($v){return $v==='simple';}}:false;}
function wp_attachment_is_image($id){return in_array($id,[11,12,13,14],true);}function get_post_status($id){return 'inherit';}function wp_get_attachment_image_url($id,$size){return 'https://example.test/mockup-'.$id.'.jpg';}function esc_url_raw($v){return $v;}
function wc_get_orders($args){return $GLOBALS['order_query_fail']??false?false:$GLOBALS['orders'];}function wc_get_order_statuses(){return ['wc-pending'=>'pending','wc-processing'=>'processing'];}
class WP_Error {function __construct(public $code,public $message,public $data){}}
class NativeResponse {public $headers=[];function __construct(public $data){}function header($k,$v){$this->headers[$k]=$v;}}
function rest_ensure_response($data){return new NativeResponse($data);}
class NativeRequest {function __construct(public $params=[],public $headers=[]){}function get_param($key){return $this->params[$key]??null;}function get_header($key){return $this->headers[$key]??'';}}
class NativeDB {
 public $options='wp_options',$last_error='';public $conflict=false,$scan_fail=false;
 function prepare($sql,...$args){return [$sql,$args];}function esc_like($v){return addcslashes($v,'_%\\');}
 function query($input){[$sql,$args]=$input;
  if(str_starts_with($sql,'INSERT IGNORE')){[$key,$value]=$args;if(isset($GLOBALS['options'][$key]))return 0;$GLOBALS['options'][$key]=unserialize($value);return 1;}
  if(str_starts_with($sql,'DELETE')){[$key,$before]=$args;if(serialize(get_option($key))!==$before)return 0;return delete_option($key)?1:0;}
  [$after,$key,$before]=$args;if($this->conflict&&str_starts_with($key,'sanro_studio_draft_'))return 0;
  if(serialize(get_option($key))!==$before)return 0;$GLOBALS['options'][$key]=unserialize($after);return 1;
 }
 function get_results($input,$mode){if($this->scan_fail)return false;[$sql,$args]=$input;[$pattern,$after,$limit]=$args;$prefix=stripslashes(substr($pattern,0,-1));$rows=[];$keys=array_keys($GLOBALS['options']);sort($keys);
  foreach($keys as $key)if(str_starts_with($key,$prefix)&&strcmp($key,$after)>0){$rows[]=['option_name'=>$key,'option_value'=>serialize($GLOBALS['options'][$key])];if(count($rows)>=$limit)break;}return $rows;
 }
}
$GLOBALS['wpdb']=new NativeDB();
foreach(['print-area','product-area','product-mockups','mockup-mapping','editor-config','draft','draft-rest','storage-management','storage-policy','master-storage','repeat-discount','design-collection','background-removal','order-preview'] as $file)require __DIR__.'/../includes/'.$file.'.php';
$checks=0;function gap_check($ok,$label){global $checks;$checks++;if(!$ok)throw new RuntimeException('FAIL '.$label);}
function png_sample($alpha=false){$image=imagecreatetruecolor(64,64);imagealphablending($image,false);imagesavealpha($image,true);$white=imagecolorallocate($image,255,255,255);imagefill($image,0,0,$white);$red=imagecolorallocate($image,190,20,20);imagefilledrectangle($image,20,20,44,44,$red);if($alpha)imagesetpixel($image,1,1,imagecolorallocatealpha($image,0,0,0,127));ob_start();imagepng($image);$bytes=ob_get_clean();imagedestroy($image);return $bytes;}
$bytes=png_sample();$transparent=png_sample(true);$created=sanro_studio_create_draft(7,['front'=>['category'=>'A5','objects'=>[['type'=>'image','x'=>1,'y'=>1,'width'=>10,'height'=>10]]],'shirt_color'=>'#ffffff','quantities'=>['S'=>1,'L'=>2],'long_sleeve'=>false]);
gap_check($created['valid'],'real geometry draft created');$id=$created['design_id'];$token=$created['owner_token'];$key='sanro_studio_draft_'.$id;$hash=hash('sha256',serialize(get_option($key)['design']));
$upload=sanro_studio_store_owned_draft_master($id,$token,'front',$bytes);gap_check($upload['valid'],'metered original master upload');$original=$upload['file_id'];
gap_check(sanro_studio_bind_owned_draft_master($id,$token,'front',$original,$hash)['valid'],'owner-bound master');
gap_check(!sanro_studio_collection_save($id,$token,'Native')['valid'],'unverified owner cannot save collection');
gap_check(sanro_studio_repeat_request_verification($id,$token,'owner@example.test')['valid'],'native email verification requested');preg_match('/\b[a-f0-9]{64}\b/',$GLOBALS['mail']['body'],$code);
gap_check(sanro_studio_repeat_complete_verification($id,$token,$code[0])['valid'],'email proof binds real draft token');
$remove=sanro_studio_background_apply($id,$token,'front',$hash);gap_check($remove['valid']&&$remove['file_id']!==$original,'native background creates new immutable master');
$after=get_option($key);gap_check($after['original_master_refs']['front']===$original&&file_get_contents(SANRO_STUDIO_PRIVATE_MASTER_DIR.'/'.$original)===$bytes,'original bytes retained unchanged');
$processed=sanro_studio_read_owned_draft_master($id,$token,'front',$remove['file_id']);$image=imagecreatefromstring($processed['bytes']);
gap_check((imagecolorat($image,0,0)>>24&127)===127&&(imagecolorat($image,32,32)>>24&127)===0&&imagesx($image)===64,'alpha, foreground and resolution preserved');imagedestroy($image);
gap_check(sanro_studio_remove_background_bytes($transparent)['error']==='image_already_transparent','actual alpha disables reprocessing');
gap_check(!sanro_studio_remove_background_bytes($bytes,97)['valid'],'invalid tolerance refused');
$flat=imagecreatetruecolor(32,32);imagefill($flat,0,0,imagecolorallocate($flat,255,255,255));ob_start();imagepng($flat);$flat_bytes=ob_get_clean();imagedestroy($flat);gap_check(!sanro_studio_remove_background_bytes($flat_bytes)['valid'],'entirely removed foreground cannot replace source');
gap_check(!sanro_studio_background_apply($id,str_repeat('b',64),'front',$hash)['valid'],'foreign token cannot process master');
gap_check(!sanro_studio_background_apply($id,$token,'front',str_repeat('0',64))['valid'],'stale geometry cannot replace master');
gap_check(sanro_studio_background_apply($id,$token,'front',$hash,true)['file_id']===$original,'original can be restored');
$GLOBALS['wpdb']->conflict=true;$conflicted=sanro_studio_background_apply($id,$token,'front',$hash);$GLOBALS['wpdb']->conflict=false;
gap_check(!$conflicted['valid']&&get_option($key)['master_refs']['front']===$original,'CAS conflict leaves current master intact');
foreach(['front','back'] as $side)gap_check(sanro_studio_store_owned_draft_preview($id,$token,$side,$bytes,$hash)['valid'],'revision-bound preview '.$side);
$saved=sanro_studio_collection_save($id,$token,'Native permanent');gap_check($saved['valid'],'verified owner saves permanent snapshot');$session=$saved['collection_token'];
$other=sanro_studio_collection_session_create('other@example.test')['collection_token'];gap_check(!sanro_studio_collection_authorize($id,$other),'cross-user collection isolation');
gap_check(!sanro_studio_collection_mutate($id,$other,'delete')['valid'],'foreign delete denied');
$identity=sanro_studio_collection_owner_key('owner@example.test');$GLOBALS['auth_salt']='rotated-login-secret-salt';gap_check(sanro_studio_collection_owner_key('owner@example.test')===$identity&&sanro_studio_collection_authorize($id,$session),'WP login salt rotation cannot orphan permanent collection identity');unset($GLOBALS['auth_salt']);
$shared=sanro_studio_collection_mutate($id,$session,'share');gap_check($shared['valid']&&sanro_studio_collection_authorize($id,null,$shared['share_token']),'share enables preview authorization');
gap_check(!str_contains(serialize(get_option('sanro_studio_library_'.$id)),$shared['share_token']),'only hash of share secret persisted');
$resaved=sanro_studio_collection_save($id,$token,'Native snapshot revision');gap_check($resaved['valid']&&!sanro_studio_collection_authorize($id,null,$shared['share_token']),'new permanent snapshot is private and revokes prior share');$shared=sanro_studio_collection_mutate($id,$session,'share');
$source=get_option($key);$source['expires_at']=time()-1;$GLOBALS['options'][$key]=$source;
gap_check(!sanro_studio_read_draft($id,$token)['valid'],'temporary source draft expires');
$auth=sanro_studio_collection_authorize($id,$session);$read=sanro_studio_collection_asset($auth['entry'],$original,'front',true);
gap_check($read['valid']&&$read['bytes']===$bytes,'permanent snapshot survives source TTL');
$fork=sanro_studio_collection_fork($id,null,$shared['share_token']);gap_check($fork['valid']&&$fork['design_id']!==$id&&$fork['owner_token']!==$token,'shared edit/buy gets new ID and secret');
$copy=get_option('sanro_studio_draft_'.$fork['design_id']);gap_check(str_starts_with($copy['master_refs']['front'],$fork['design_id'])&&!isset($copy['verified_owner'],$copy['order_id'],$copy['wc_session_hash'],$copy['checkout_quote']),'fork copies namespace, no identity/payment/session inheritance');
gap_check(!isset($copy['library_origin_id']),'shared copy cannot overwrite source collection');
gap_check(!sanro_studio_read_draft($fork['design_id'],$token)['valid'],'source owner token cannot open fork');
gap_check(sanro_studio_collection_mutate($id,$session,'revoke')['valid']&&!sanro_studio_collection_fork($id,null,$shared['share_token'])['valid'],'revoke prevents future share forks');
gap_check(sanro_studio_read_draft($fork['design_id'],$fork['owner_token'])['valid'],'already-created fork independent after revoke');
$reuse=sanro_studio_collection_fork($id,$session);gap_check($reuse['valid']&&sanro_studio_read_draft($reuse['design_id'],$reuse['owner_token'])['owner_email_verified'],'owner reuse safely rebinds verified email');
$edited=sanro_studio_collection_save($reuse['design_id'],$reuse['owner_token'],'Updated permanent');gap_check($edited['valid']&&$edited['id']===$id&&get_option('sanro_studio_library_'.$id)['source_design_id']===$reuse['design_id'],'owner reopen/save updates same permanent entry with independent editable draft');
gap_check(sanro_studio_collection_access_request('owner@example.test')['valid'],'cross-browser email access');preg_match('/\b[a-f0-9]{32}\b/',$GLOBALS['mail']['body'],$access);
gap_check(!sanro_studio_collection_access_verify('other@example.test',$access[0])['valid'],'access code scoped to email');
$new_session=sanro_studio_collection_access_verify('owner@example.test',$access[0]);gap_check($new_session['valid']&&sanro_studio_collection_authorize($id,$new_session['collection_token']),'new browser recovers own permanent collection');
gap_check(!sanro_studio_collection_access_verify('owner@example.test',$access[0])['valid'],'access code single-use');
$list=sanro_studio_collection_rest(new NativeRequest([],['X-Sanro-Collection-Token'=>$new_session['collection_token']]),'list');
gap_check($list instanceof NativeResponse&&$list->data['entries'][0]['id']===$id&&$list->headers['Cache-Control']==='private, no-store, max-age=0','owner list uses correct IDs and private cache policy');
gap_check(!str_contains(serialize($list->data),'token_hash')&&!str_contains(serialize($list->data),'master_refs'),'collection metadata never exposes master bindings or owner secret');
$foreign_list=sanro_studio_collection_rest(new NativeRequest([],['X-Sanro-Collection-Token'=>$other]),'list');gap_check($foreign_list->data['entries']===[],'REST list cannot enumerate another owner collection');
$logout=sanro_studio_collection_rest(new NativeRequest([],['X-Sanro-Collection-Token'=>$new_session['collection_token']]),'logout');gap_check($logout->data['valid']&&!sanro_studio_collection_session($new_session['collection_token']),'logout revokes server bearer session');

// Aggregate capacity includes orphan revisions and simultaneous requests serialize on the same site lease.
$inventory=sanro_studio_storage_inventory();gap_check($inventory['valid']&&$inventory['bytes']>strlen($bytes),'aggregate counts every immutable copy/revision');
file_put_contents(SANRO_STUDIO_PRIVATE_MASTER_DIR.'/capacity.bin',str_repeat('x',2097152-$inventory['bytes']-100));
$capacity=sanro_studio_store_private_master(wp_generate_uuid4(),'front',$transparent);gap_check(!$capacity['valid']&&$capacity['error']==='storage_capacity','remaining aggregate capacity checked before write');file_put_contents(SANRO_STUDIO_PRIVATE_MASTER_DIR.'/capacity.bin',str_repeat('x',2097152));gap_check(sanro_studio_store_private_master($id,'front',$bytes)['valid'],'idempotent existing content works even when configured capacity is exceeded');unlink(SANRO_STUDIO_PRIVATE_MASTER_DIR.'/capacity.bin');
$lease=sanro_studio_lease_acquire('sanro_studio_storage_lock');gap_check(sanro_studio_store_private_master(wp_generate_uuid4(),'front',$bytes)['error']==='storage_busy','competing writer cannot bypass atomic budget');sanro_studio_lease_release($lease);
// Complete pin scan: library, live draft, Woo order namespace, unknown/young files survive; only old orphan unlinks.
$orphan=sanro_studio_store_private_master(wp_generate_uuid4(),'front',$bytes)['file_id'];$ordered_id=wp_generate_uuid4();$ordered=sanro_studio_store_private_master($ordered_id,'front',$bytes)['file_id'];$young=sanro_studio_store_private_master(wp_generate_uuid4(),'front',$bytes)['file_id'];
foreach(glob(SANRO_STUDIO_PRIVATE_MASTER_DIR.'/*') as $file)touch($file,time()-172800);touch(SANRO_STUDIO_PRIVATE_MASTER_DIR.'/'.$young,time());file_put_contents(SANRO_STUDIO_PRIVATE_MASTER_DIR.'/unknown.txt','never purge');
$GLOBALS['orders']=[new class($ordered_id){function __construct(public $id){}function get_meta($key,$single){return $this->id;}function get_items($type){return [];}}];
$GLOBALS['wpdb']->scan_fail=true;$gc=sanro_studio_storage_gc();gap_check(!$gc['valid']&&is_file(SANRO_STUDIO_PRIVATE_MASTER_DIR.'/'.$orphan),'incomplete reference scan causes zero deletions');$GLOBALS['wpdb']->scan_fail=false;
$gc=sanro_studio_storage_gc();gap_check($gc['valid']&&!is_file(SANRO_STUDIO_PRIVATE_MASTER_DIR.'/'.$orphan),'old unreferenced master garbage collected');
gap_check(is_file(SANRO_STUDIO_PRIVATE_MASTER_DIR.'/'.$ordered)&&is_file(SANRO_STUDIO_PRIVATE_MASTER_DIR.'/'.$original)&&is_file(SANRO_STUDIO_PRIVATE_MASTER_DIR.'/'.$young)&&is_file(SANRO_STUDIO_PRIVATE_MASTER_DIR.'/unknown.txt'),'order namespace, snapshot, young and unknown files protected');
gap_check(sanro_studio_collection_mutate($id,$session,'delete')['valid']&&!sanro_studio_collection_authorize($id,$session),'delete revokes access without deleting order or file bytes');
gap_check(sanro_studio_read_draft($fork['design_id'],$fork['owner_token'])['valid'],'collection deletion cannot delete fork');
gap_check(!sanro_studio_collection_asset(['source_design_id'=>$id],'../'.$original,'front',true)['valid'],'path traversal rejected');
// Backend matrix and server palette are product-owned; invalid mapping never becomes public config.
$rows=[['color'=>'#abcdef','label'=>'Custom','size'=>'S','front'=>11,'back'=>12],['color'=>'#abcdef','label'=>'Custom','size'=>'L','front'=>13,'back'=>14]];
gap_check(sanro_studio_mockup_matrix_validate($rows,['S','L'])===$rows,'configured sizes and both sides accepted');
gap_check(sanro_studio_mockup_matrix_validate([$rows[0],$rows[0]],['S','L'])===false,'ambiguous duplicate variant rejected');
$bad=$rows;$bad[0]['front']='https://evil.test/master.png';gap_check(sanro_studio_mockup_matrix_validate($bad,['S','L'])===false,'buyer/external URL cannot become mockup mapping');
$GLOBALS['matrix']=$rows;$config=sanro_studio_public_editor_config(7);gap_check($config['valid']&&$config['mockup_matrix']['#abcdef']['L']['back']==='https://example.test/mockup-14.jpg'&&array_keys($config['palette'])===['#abcdef'],'backend mapping reaches public config without private paths');
$design=$copy['design'];gap_check(!sanro_studio_validate_product_design(7,$design)['valid'],'server rejects palette not configured for mapped product');$design['shirt_color']='#abcdef';gap_check(sanro_studio_validate_product_design(7,$design)['valid'],'configured custom palette accepted by server');
$fork_key='sanro_studio_draft_'.$fork['design_id'];$GLOBALS['options'][$fork_key]['design']=$design;$variant_hash=hash('sha256',serialize($design));
gap_check(sanro_studio_mockup_variant_store($fork['design_id'],$fork['owner_token'],'front','L',$transparent,$variant_hash)['valid'],'size-specific preview saved with owner/revision');
$variant=get_option($fork_key);gap_check(sanro_studio_record_preview_refs($variant,'L')['front']!==($variant['preview_refs']['front']??null),'variant can reference independent rendered image');
gap_check(sanro_studio_record_preview_refs($variant,'S')===[],'missing mapped size cannot silently substitute another preview');
gap_check(!sanro_studio_mockup_variant_store($fork['design_id'],$token,'back','L',$bytes,$variant_hash)['valid'],'foreign token cannot bind variant');
gap_check(!defined('SANRO_STUDIO_ENABLE_DTF_GENERATOR')&&!defined('SANRO_STUDIO_ENABLE_AUTO_DRIVE'),'native features never activate production writers');
echo "PASS: $checks native collection/storage/background/mockup functional and security checks (WP/Woo/SMTP simulated; actual PHP/GD/filesystem)\n";
