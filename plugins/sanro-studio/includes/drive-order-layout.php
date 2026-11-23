<?php
defined('ABSPATH') || exit;

function sanro_studio_drive_token() {
    $token=apply_filters('sanro_studio_drive_access_token','');
    return is_string($token)&&strlen($token)>=12&&strlen($token)<=4096&&preg_match('/^[A-Za-z0-9._~+\/-]+$/D',$token)?$token:false;
}
/** Verify every ancestor and its user-only permissions; metadata alone is never authority. */
function sanro_studio_drive_folder_matches($id,$parent,$props,$token) {
    foreach([$id,$parent] as $v)if(!is_string($v)||!preg_match('/^[A-Za-z0-9_-]{10,128}$/D',$v))return false;
    if($id===$parent)return false;
    $r=wp_remote_get('https://www.googleapis.com/drive/v3/files/'.rawurlencode($id).'?fields='.rawurlencode('id,mimeType,parents,trashed,permissions(type),appProperties'),['timeout'=>15,'redirection'=>0,'limit_response_size'=>8192,'headers'=>['Authorization'=>'Bearer '.$token,'Accept'=>'application/json']]);
    if(is_wp_error($r)||wp_remote_retrieve_response_code($r)!==200)return false;$body=wp_remote_retrieve_body($r);$v=is_string($body)&&strlen($body)<=8192?json_decode($body,true):null;
    if(!is_array($v)||($v['id']??null)!==$id||($v['parents']??null)!==[$parent]||($v['mimeType']??null)!=='application/vnd.google-apps.folder'||($v['trashed']??true)!==false||empty($v['permissions'])||!is_array($v['permissions']))return false;
    foreach($v['permissions'] as $p)if(!is_array($p)||($p['type']??null)!=='user')return false;
    foreach($props as $k=>$value)if(($v['appProperties'][$k]??null)!==$value)return false;
    return true;
}
function sanro_studio_drive_order_properties($order) {
    return ['sanroSite'=>hash('sha256',rtrim(home_url('/'),'/')),'sanroOrderId'=>(string)$order->get_id()];
}
function sanro_studio_drive_verified_layout($order,$token) {
    if(!is_object($order)||!method_exists($order,'get_meta')||!defined('SANRO_STUDIO_DRIVE_PARENT_FOLDER_ID'))return false;
    $root=$order->get_meta('_sanro_studio_drive_order_folder_id',true);$production=$order->get_meta('_sanro_studio_drive_production_folder_id',true);$props=sanro_studio_drive_order_properties($order);
    return sanro_studio_drive_folder_matches($root,SANRO_STUDIO_DRIVE_PARENT_FOLDER_ID,$props+['sanroFolderKind'=>'order'],$token)
        &&sanro_studio_drive_folder_matches($production,$root,$props+['sanroFolderKind'=>'production'],$token)
        ?['order_folder_id'=>$root,'production_folder_id'=>$production]:false;
}
/** Recover a remote create before a failed local CRUD save; ambiguity blocks writes. */
function sanro_studio_drive_find_or_create_folder($parent,$props,$name,$token) {
    $q="'".$parent."' in parents and trashed = false and mimeType = 'application/vnd.google-apps.folder'";
    foreach($props as $key=>$value)$q.=" and appProperties has { key='".$key."' and value='".$value."' }";
    $headers=['Authorization'=>'Bearer '.$token,'Accept'=>'application/json'];
    $r=wp_remote_get('https://www.googleapis.com/drive/v3/files?fields='.rawurlencode('nextPageToken,files(id)').'&pageSize=2&q='.rawurlencode($q),['timeout'=>15,'redirection'=>0,'limit_response_size'=>8192,'headers'=>$headers]);
    if(is_wp_error($r)||wp_remote_retrieve_response_code($r)!==200)return ['valid'=>false,'error'=>'drive_search_failed'];$body=wp_remote_retrieve_body($r);$v=is_string($body)&&strlen($body)<=8192?json_decode($body,true):null;
    if(!is_array($v)||!is_array($v['files']??null)||count($v['files'])>1||!empty($v['nextPageToken']))return ['valid'=>false,'error'=>'drive_search_ambiguous'];$created=false;
    if($v['files'])$id=$v['files'][0]['id']??null;
    else{$r=wp_remote_post('https://www.googleapis.com/drive/v3/files?fields=id',['timeout'=>15,'redirection'=>0,'limit_response_size'=>8192,'headers'=>$headers+['Content-Type'=>'application/json'],'body'=>wp_json_encode(['name'=>$name,'mimeType'=>'application/vnd.google-apps.folder','parents'=>[$parent],'appProperties'=>$props])]);
        if(is_wp_error($r)||!in_array(wp_remote_retrieve_response_code($r),[200,201],true))return ['valid'=>false,'error'=>'drive_create_failed'];$v=json_decode(wp_remote_retrieve_body($r),true);$id=$v['id']??null;$created=true;}
    if(!sanro_studio_drive_folder_matches($id,$parent,$props,$token))return ['valid'=>false,'error'=>'drive_folder_unverified'];
    return ['valid'=>true,'folder_id'=>$id,'created'=>$created];
}
/** merchant / SANRO-order-number / Produksi / ITEM-id. No migration/deletion of legacy folders. */
function sanro_studio_drive_ensure_order_layout($order) {
    if(!sanro_studio_auto_drive_enabled()||!sanro_studio_auto_drive_order_eligible($order)||!method_exists($order,'update_meta_data')||!method_exists($order,'save'))return ['valid'=>false,'error'=>'drive_layout_unavailable'];
    $token=sanro_studio_drive_token();if(!$token)return ['valid'=>false,'error'=>'drive_not_connected'];
    $lock='sanro_drive_order_lock_'.md5(home_url('/').':'.$order->get_id());if(!add_option($lock,(string)time(),'',false))return ['valid'=>false,'error'=>'drive_layout_locked'];
    try{$props=sanro_studio_drive_order_properties($order);$parent=SANRO_STUDIO_DRIVE_PARENT_FOLDER_ID;
        $number=method_exists($order,'get_order_number')?(string)$order->get_order_number():(string)$order->get_id();$number=preg_replace('/[^a-zA-Z0-9._-]/','-',substr($number,0,80));
        foreach(['order'=>['key'=>'_sanro_studio_drive_order_folder_id','name'=>'SANRO-'.$number],'production'=>['key'=>'_sanro_studio_drive_production_folder_id','name'=>'Produksi']] as $kind=>$entry){
            $existing=$order->get_meta($entry['key'],true);$expected=$props+['sanroFolderKind'=>$kind];
            if($existing!==''){if(!sanro_studio_drive_folder_matches($existing,$parent,$expected,$token))return ['valid'=>false,'error'=>'drive_layout_conflict'];$id=$existing;}
            else{$found=sanro_studio_drive_find_or_create_folder($parent,$expected,$entry['name'],$token);if(empty($found['valid']))return $found;$id=$found['folder_id'];if(!sanro_studio_auto_drive_order_eligible($order))return ['valid'=>false,'error'=>'production_not_ready'];$order->update_meta_data($entry['key'],$id);if($order->save()===false)return ['valid'=>false,'error'=>'drive_layout_save_failed'];}
            $parent=$id;
        }
        $verified=sanro_studio_drive_verified_layout($order,$token);return $verified?['valid'=>true]+$verified:['valid'=>false,'error'=>'drive_layout_unverified'];
    }finally{delete_option($lock);}
}
function sanro_studio_verified_order_drive_url($order) {
    if(!current_user_can('manage_woocommerce')||!defined('SANRO_STUDIO_ENABLE_VERIFIED_DRIVE_ATTACHMENT')||SANRO_STUDIO_ENABLE_VERIFIED_DRIVE_ATTACHMENT!==true||!sanro_studio_auto_drive_order_eligible($order))return null;
    $token=sanro_studio_drive_token();$layout=$token?sanro_studio_drive_verified_layout($order,$token):false;
    return $layout?'https://drive.google.com/drive/folders/'.rawurlencode($layout['order_folder_id']):null;
}
