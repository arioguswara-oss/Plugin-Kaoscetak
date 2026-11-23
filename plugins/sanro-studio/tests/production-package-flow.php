<?php
/** Real locked-order/private-preview/PDF/Drive upload pipeline, synthetic WP/WC + HTTP. */
require __DIR__.'/feature-checkout-flow.php';
require __DIR__.'/../includes/order-master-source.php';
require __DIR__.'/../includes/drive-auto-provision.php';
require __DIR__.'/../includes/production-package.php';
require __DIR__.'/../includes/drive-master-export.php';
require __DIR__.'/../includes/production-status.php';
class PackageItem extends FlowItem {public $name='Cotton Combed 24s';function get_name(){return $this->name;}function get_order_id(){return 88;}}
class PackageOrder extends FlowOrder {public $paid=false,$status='pending',$ready='';function is_paid(){return $this->paid;}function get_status(){return $this->status;}function get_meta($key,$single=true){return $key==='_sanro_studio_production_ready'?$this->ready:'';}function get_order_number(){return 'TEST-88';}}
function wc_get_order($id){return $GLOBALS['package_order'];}
function current_user_can($cap){return !empty($GLOBALS['admin'])&&$cap==='manage_woocommerce';}
function esc_html($s){return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}function esc_attr($s){return esc_html($s);}function esc_url($s){return esc_html($s);}
function add_query_arg($args,$url){return $url.'?'.http_build_query($args);}function admin_url($path){return 'https://example.test/wp-admin/'.$path;}function wp_nonce_url($url,$action){return $url.'&_wpnonce='.rawurlencode($action);}
function home_url($path='/'){return 'https://example.test'.$path;}function wp_json_encode($v){return json_encode($v);}
function add_option($key,$v,...$args){if(isset($GLOBALS['options'][$key]))return false;$GLOBALS['options'][$key]=$v;return true;}function delete_option($key){unset($GLOBALS['options'][$key]);return true;}
function sanro_studio_verify_drive_item_folder($order,$item,$folder){return ['valid'=>$folder==='private-folder-88-'.$item];}
function is_wp_error($v){return $v instanceof WP_Error;}function wp_remote_retrieve_response_code($v){return $v['code'];}function wp_remote_retrieve_body($v){return $v['body'];}
function wp_remote_get($url,$args){
 $GLOBALS['network_calls']++;
 if(str_contains($url,'/files?')){
  parse_str(parse_url($url,PHP_URL_QUERY),$query);preg_match_all("/key='([^']+)' and value='([^']+)'/",$query['q'],$matches,PREG_SET_ORDER);
  $files=[];foreach($GLOBALS['remote'] as $file){$valid=true;foreach($matches as $m)if(($file['appProperties'][$m[1]]??null)!==$m[2])$valid=false;if($valid)$files[]=['id'=>$file['id']];}
  return ['code'=>200,'body'=>json_encode(['files'=>$files])];
 }
 $id=basename(parse_url($url,PHP_URL_PATH));$file=$GLOBALS['remote'][$id]??null;
 if($file&& !empty($GLOBALS['remote_public']))$file['permissions']=[['type'=>'anyone']];
 return ['code'=>$file?200:404,'body'=>json_encode($file??[])];
}
function wp_remote_post($url,$args){
 $GLOBALS['network_calls']++;$GLOBALS['uploads']++;
 if(!str_starts_with($url,'https://www.googleapis.com/upload/drive/v3/files?'))throw new RuntimeException('unexpected URL');
 $boundary=substr($args['headers']['Content-Type'],strlen('multipart/related; boundary='));
 $parts=explode('--'.$boundary,$args['body']);$meta=json_decode(explode("\r\n\r\n",$parts[1],2)[1],true);
 $bytes=explode("\r\n\r\n",$parts[2],2)[1];$bytes=substr($bytes,0,-2);
 $id='synthetic-file-'.count($GLOBALS['remote']);$GLOBALS['remote'][$id]=$meta+['id'=>$id,'trashed'=>false,'size'=>(string)strlen($bytes),'md5Checksum'=>md5($bytes),'permissions'=>[['type'=>'user']]];
 return ['code'=>201,'body'=>json_encode(['id'=>$id])];
}
$checks=0;$production=new PackageOrder();foreach($order->items as $old){$item=new PackageItem($old->id,$old->qty,$old->unit);$item->meta=$old->meta;$production->items[$item->id]=$item;}$GLOBALS['package_order']=$production;
$GLOBALS['network_calls']=0;$GLOBALS['uploads']=0;$GLOBALS['remote']=[];
flow_check(sanro_studio_export_production_package($production,71,'private-folder-88-71')['error']==='drive_package_disabled'&&$GLOBALS['network_calls']===0,'default OFF exports nothing');
flow_check(sanro_studio_prepare_production_package($production,71)['error']==='production_not_ready','unpaid not-ready order refused');
$production->ready='yes';flow_check(sanro_studio_prepare_production_package($production,71)['valid'],'explicit ready order allowed');$production->ready='';$production->paid=true;$production->status='processing';
$package=sanro_studio_prepare_production_package($production,71);flow_check(!empty($package['valid'])&&count($package['assets'])===4&&$package['print_ready']===false,'four in-memory assets, never print-ready');
$pdf=$package['assets'][2]['bytes'];flow_check(str_starts_with($pdf,'%PDF-1.4')&&str_ends_with($pdf,"%%EOF\n")&&str_contains($pdf,'/Count 2'),'two-page PDF guide with JPEG preview');
preg_match('/startxref\n([0-9]+)/',$pdf,$xref);flow_check(substr($pdf,(int)$xref[1],4)==='xref','PDF cross-reference offset valid');
preg_match_all('/([0-9]{10}) 00000 n /',$pdf,$offsets);foreach($offsets[1] as $index=>$offset)flow_check(str_starts_with(substr($pdf,(int)$offset),($index+1).' 0 obj'),'PDF object offsets valid');
flow_check($package['manifest']['quantities']===['S'=>3,'L'=>1]&&$package['manifest']['subtotal']===186000,'production follows locked checkout, no live repricing');
$production->items[71]->total=1;flow_check(sanro_studio_prepare_production_package($production,71)['error']==='production_order_amount_mismatch','production denies modified persisted total');$production->items[71]->total=186000;
$production->items[71]->name='Kaos é';flow_check(!empty(sanro_studio_prepare_production_package($production,71)['valid']),'Unicode renders with bundled embedded font');$production->items[71]->name='Cotton Combed 24s';
$GLOBALS['admin']=true;ob_start();sanro_studio_admin_item_preview_status(71,$production->items[71],null);$html=ob_get_clean();flow_check(substr_count($html,'<img ')===2&&str_contains($html,'_wpnonce=')&&str_contains($html,'#ffffff')&&str_contains($html,'A5'),'admin renders both nonce-protected previews and snapshot');
flow_check(!str_contains($html,$private)&&!str_contains($html,$token),'admin HTML never leaks private path or owner secret');
$GLOBALS['admin']=false;ob_start();sanro_studio_admin_item_preview_status(71,$production->items[71],null);flow_check(ob_get_clean()==='','non-admin sees no preview HTML');
flow_check(!sanro_studio_admin_order_preview_bytes($production,'front',71)['valid'],'non-admin private admin reader denied');
$GLOBALS['admin']=true;$readiness=sanro_studio_production_status($production,71);flow_check($readiness['order_ready']&&$readiness['design_ready']&&$readiness['previews_ready']&&!$readiness['generator_enabled']&&!$readiness['drive_enabled']&&!$readiness['print_approved'],'admin separates paid/design/preview readiness from disabled writers and print approval');
if(extension_loaded('gd')){
    $im=imagecreatetruecolor(20,16);imagefill($im,0,0,imagecolorallocate($im,20,80,120));
    foreach(['png','webp'] as $format){ob_start();if($format==='png')imagepng($im);else imagewebp($im);$bytes=ob_get_clean();$mime='image/'.$format;$guide=sanro_studio_production_guide_pdf($package['manifest'],['front'=>['mime'=>$mime,'bytes'=>$bytes],'back'=>['mime'=>$mime,'bytes'=>$bytes]]);flow_check(!empty($guide['valid']),'guide supports '.$format.' preview');}
    imagedestroy($im);
}
define('SANRO_STUDIO_ENABLE_DRIVE_AUTO_PROVISION',true);define('SANRO_STUDIO_ENABLE_VERIFIED_DRIVE_ATTACHMENT',true);define('SANRO_STUDIO_DRIVE_PARENT_FOLDER_ID','synthetic-private-parent');define('SANRO_STUDIO_ENABLE_DRIVE_PACKAGE_EXPORT',true);
foreach([71,72] as $itemid){$result=sanro_studio_export_production_package($production,$itemid,'private-folder-88-'.$itemid);flow_check(!empty($result['valid'])&&$result['uploaded']===4,'upload all four assets with exact private remote verification');}
flow_check($GLOBALS['uploads']===8,'two sizes have isolated item folders and assets');
foreach([71,72] as $itemid){$result=sanro_studio_export_production_package($production,$itemid,'private-folder-88-'.$itemid);flow_check(!empty($result['valid'])&&$result['reused']===4,'retry reuses verified remote assets');}flow_check($GLOBALS['uploads']===8,'retry performs no upload');
$GLOBALS['remote_public']=true;flow_check(!sanro_studio_export_production_package($production,71,'private-folder-88-71')['valid'],'public Drive permissions reject reuse');unset($GLOBALS['remote_public']);
$production->status='refunded';$calls=$GLOBALS['network_calls'];flow_check(!sanro_studio_export_production_package($production,71,'private-folder-88-71')['valid']&&$GLOBALS['network_calls']===$calls,'refunded prevents all network');
echo "PASS: $checks production guide/admin/private-upload checks (HTTP/WP/WC simulated; real JPEG/PDF bytes)\n";
