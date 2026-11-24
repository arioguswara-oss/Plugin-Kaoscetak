<?php
defined('ABSPATH') || exit;
require_once __DIR__.'/worker-lease.php';

/** Site-wide byte ceiling, including immutable revisions; never a per-upload counter. */
function sanro_studio_storage_roots() {
    $roots=[];
    foreach(['SANRO_STUDIO_PRIVATE_MASTER_DIR','SANRO_STUDIO_PRIVATE_PREVIEW_DIR','SANRO_STUDIO_DTF_ARTIFACT_DIR'] as $constant){
        if(!defined($constant)||constant($constant)==='')continue;
        $raw=constant($constant);if(!is_string($raw)||strpos($raw,"\0")!==false)return false;$dir=realpath($raw);
        $wp=realpath(ABSPATH);$web=realpath($_SERVER['DOCUMENT_ROOT']??'');
        if(!$dir||!$wp||!$web||is_link($raw)||!is_dir($dir)||!is_readable($dir)||!is_writable($dir)||(@fileperms($dir)&0077)!==0)return false;
        foreach([$wp,$web] as $public)if($dir===$public||strpos($dir,$public.DIRECTORY_SEPARATOR)===0)return false;
        $roots[$dir]=$dir;
    }
    usort($roots,static function($a,$b){return strlen($a)<=>strlen($b);});$unique=[];
    foreach($roots as $dir){foreach($unique as $parent)if(strpos($dir,$parent.DIRECTORY_SEPARATOR)===0)continue 2;$unique[]=$dir;}
    return $unique;
}
function sanro_studio_storage_inventory() {
    $roots=sanro_studio_storage_roots();if($roots===false)return ['valid'=>false,'error'=>'storage_configuration'];
    $files=[];$bytes=0;$deadline=microtime(true)+5;
    try{foreach($roots as $root){
        $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
        foreach($iterator as $file){
            if(microtime(true)>$deadline||count($files)>=50000||$file->isLink()||!$file->isReadable())return ['valid'=>false,'error'=>'storage_inventory_incomplete'];
            if(!$file->isFile())continue;$path=$file->getPathname();$length=$file->getSize();$bytes+=$length;
            $files[$path]=['bytes'=>$length,'mtime'=>$file->getMTime()];
        }
    }}catch(Throwable $error){return ['valid'=>false,'error'=>'storage_inventory_incomplete'];}
    $limit=defined('SANRO_STUDIO_PRIVATE_QUOTA_BYTES')?SANRO_STUDIO_PRIVATE_QUOTA_BYTES:536870912;
    if(!is_int($limit)||$limit<1048576)return ['valid'=>false,'error'=>'storage_configuration'];
    return ['valid'=>true,'bytes'=>$bytes,'limit'=>$limit,'files'=>$files,'roots'=>$roots];
}
function sanro_studio_storage_inside() {return !empty($GLOBALS['sanro_studio_storage_lease']);}
/** Serialize capacity checks, writes and pin/GC mutations. No scheduled deletion or writer activation. */
function sanro_studio_storage_transaction($path,$length,$operation) {
    if(sanro_studio_storage_inside()){
        $lease=&$GLOBALS['sanro_studio_storage_lease'];
        if(!sanro_studio_lease_refresh($lease))return ['valid'=>false,'error'=>'storage_busy'];
    }else{
        $lease=sanro_studio_lease_acquire('sanro_studio_storage_lock',900);
        if(!$lease)return ['valid'=>false,'error'=>'storage_busy'];
        $GLOBALS['sanro_studio_storage_lease']=$lease;
        try{return sanro_studio_storage_transaction($path,$length,$operation);}
        finally{$held=$GLOBALS['sanro_studio_storage_lease'];unset($GLOBALS['sanro_studio_storage_lease']);sanro_studio_lease_release($held);}
    }
    if($path!==null){
        $inventory=sanro_studio_storage_inventory();if(empty($inventory['valid']))return $inventory;
        $covered=false;foreach($inventory['roots'] as $root)if(strpos($path,$root.DIRECTORY_SEPARATOR)===0)$covered=true;
        if(!$covered||!is_int($length)||$length<0||is_link($path))return ['valid'=>false,'error'=>'storage_configuration'];
        $additional=is_file($path)?0:$length;$free=@disk_free_space(dirname($path));
        if($additional>0&&($inventory['bytes']+$additional>$inventory['limit']||$free===false||$free-$additional<16777216))return ['valid'=>false,'error'=>'storage_capacity'];
    }
    $previous=$GLOBALS['sanro_studio_storage_path']??null;
    $GLOBALS['sanro_studio_storage_path']=$path;
    try{return $operation();}finally{$GLOBALS['sanro_studio_storage_path']=$previous;}
}
function sanro_studio_storage_writing($path){return ($GLOBALS['sanro_studio_storage_path']??null)===$path && sanro_studio_storage_inside();}

/** Cursor scan is bounded and must finish before a single unlink. No direct Woo order SQL. */
function sanro_studio_storage_option_rows($prefix,$after='',$limit=200) {
    $db=sanro_studio_lease_database();if(!$db||!method_exists($db,'get_results')||!method_exists($db,'esc_like'))return false;
    $rows=$db->get_results($db->prepare("SELECT option_name, option_value FROM `{$db->options}` WHERE option_name LIKE %s AND option_name > %s ORDER BY option_name ASC LIMIT %d",$db->esc_like($prefix).'%',$after,$limit),ARRAY_A);
    return is_array($rows)&&empty($db->last_error)?$rows:false;
}
function sanro_studio_storage_references() {
    $refs=[];$orders=[];$deadline=microtime(true)+8;$count=0;
    foreach(['sanro_studio_draft_','sanro_studio_library_'] as $prefix){$cursor='';
        do{$rows=sanro_studio_storage_option_rows($prefix,$cursor);if($rows===false)return false;
            foreach($rows as $row){
                if(++$count>50000||microtime(true)>$deadline)return false;$cursor=$row['option_name'];
                $record=maybe_unserialize($row['option_value']);if(!is_array($record))return false;
                if($prefix==='sanro_studio_library_'&&($record['status']??null)==='deleted')continue;
                $id=substr($cursor,strlen($prefix));
                if(isset($record['order_id'])||isset($record['order_item_ids'])||isset($record['checkout_quote']))$orders[strtolower($id)]=true;
                $walk=function($value)use(&$walk,&$refs){if(is_array($value)){foreach($value as $v)$walk($v);}elseif(is_string($value)&&preg_match('/^[a-f0-9-]{36}-(front|back)-[a-f0-9]{64}\.(png|jpg|webp)$/D',$value))$refs[$value]=true;};
                $walk($record);
            }
        }while(count($rows)===200);
    }
    // A surviving Woo order protects its entire design namespace even if draft metadata is absent.
    if(!function_exists('wc_get_orders'))return false;
    for($page=1;$page<=500;$page++){
        if(microtime(true)>$deadline)return false;
        try{$batch=wc_get_orders(['limit'=>100,'page'=>$page,'status'=>array_merge(array_keys(wc_get_order_statuses()),['trash']),'return'=>'objects','orderby'=>'ID','order'=>'ASC']);}catch(Throwable $e){return false;}
        if(!is_array($batch))return false;
        foreach($batch as $order){if(!is_object($order)||!method_exists($order,'get_meta')||!method_exists($order,'get_items'))return false;
            foreach(['_sanro_studio_design_id','_sanro_studio_checkout_design_id'] as $field){$id=$order->get_meta($field,true);if(is_string($id)&&$id!=='')$orders[strtolower($id)]=true;}
            foreach($order->get_items('line_item') as $item){if(!is_object($item)||!method_exists($item,'get_meta'))return false;$id=$item->get_meta('_sanro_studio_design_id',true);if(is_string($id)&&$id!=='')$orders[strtolower($id)]=true;}
        }
        if(count($batch)<100)return ['refs'=>$refs,'orders'=>$orders];
    }return false;
}
/** Explicit admin operation only; order artifacts and unknown files never qualify for GC. */
function sanro_studio_storage_gc() {
    if(!defined('SANRO_STUDIO_ENABLE_STORAGE_GC')||SANRO_STUDIO_ENABLE_STORAGE_GC!==true)return ['valid'=>false,'error'=>'storage_gc_disabled'];
    return sanro_studio_storage_transaction(null,0,static function(){
        $inventory=sanro_studio_storage_inventory();$pins=sanro_studio_storage_references();
        if(empty($inventory['valid'])||$pins===false)return ['valid'=>false,'error'=>'gc_reference_scan_incomplete'];
        $managed=[];foreach(['SANRO_STUDIO_PRIVATE_MASTER_DIR','SANRO_STUDIO_PRIVATE_PREVIEW_DIR'] as $key)if(defined($key)&&($dir=realpath(constant($key))))$managed[$dir]=true;
        $removed=0;$bytes=0;
        foreach($inventory['files'] as $path=>$info){
            $name=basename($path);
            if(!isset($managed[dirname($path)])||!preg_match('/^([a-f0-9-]{36})-(front|back)-[a-f0-9]{64}\.(png|jpg|webp)$/D',$name,$m)
                ||isset($pins['refs'][$name])||isset($pins['orders'][$m[1]])||$info['mtime']>time()-86400)continue;
            if(!sanro_studio_lease_refresh($GLOBALS['sanro_studio_storage_lease']))return ['valid'=>false,'error'=>'storage_busy','removed'=>$removed];
            clearstatcache(true,$path);
            if(!is_link($path)&&is_file($path)&&filemtime($path)===$info['mtime']&&filesize($path)===$info['bytes']&&@unlink($path)){$removed++;$bytes+=$info['bytes'];}
        }
        return ['valid'=>true,'removed'=>$removed,'bytes'=>$bytes];
    });
}
function sanro_studio_storage_admin_menu(){add_submenu_page('sanro-studio','Storage Privat SANRO Studio','Storage Privat','manage_woocommerce','sanro-studio-storage','sanro_studio_storage_admin');}
function sanro_studio_storage_admin(){
    if(!current_user_can('manage_woocommerce'))return;
    echo '<div class="wrap"><h1>SANRO private storage</h1>';
    if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
        check_admin_referer('sanro_storage_gc');$result=sanro_studio_storage_gc();
        echo '<p>'.esc_html(!empty($result['valid'])?'Orphan dihapus: '.$result['removed']:($result['error']??'GC ditolak')).'</p>';
    }
    $status=sanro_studio_storage_inventory();
    echo '<p>'.esc_html(!empty($status['valid'])?$status['bytes'].' / '.$status['limit'].' bytes':($status['error']??'Storage unavailable')).'</p>';
    echo '<p>GC hanya file orphan master/preview berumur minimal 24 jam. File pesanan dan artefak produksi dilindungi; tidak ada GC otomatis.</p><form method="post">';
    wp_nonce_field('sanro_storage_gc');
    if(defined('SANRO_STUDIO_ENABLE_STORAGE_GC')&&SANRO_STUDIO_ENABLE_STORAGE_GC===true)submit_button('Bersihkan orphan yang aman');else echo '<p>GC default OFF. Tidak ada file yang dihapus tanpa opt-in konfigurasi dan tindakan admin.</p>';
    echo '</form></div>';
}
