<?php
defined('ABSPATH') || exit;
/** No paths, credentials, order data or feature enablement. */
function sanro_studio_build_identity() {
    $path=dirname(__DIR__).'/SANRO-STUDIO-BUILD.json';
    $build=is_file($path)&&is_readable($path)&&filesize($path)<4096?json_decode(file_get_contents($path),true):null;
    return is_array($build)&&is_string($build['source_commit']??null)&&is_string($build['plugin_digest']??null)&&preg_match('/^[a-f0-9]{40}$/D',$build['source_commit']??'')&&preg_match('/^[a-f0-9]{64}$/D',$build['plugin_digest']??'')
        ? ['source_commit'=>$build['source_commit'],'plugin_digest'=>$build['plugin_digest'],'integrity'=>'declared_marker_only']:['source_commit'=>'unpackaged','plugin_digest'=>null,'integrity'=>'unpackaged'];
}
/** Read-only safe booleans; never expose configured filesystem paths. */
function sanro_studio_storage_health($directory) {
    $configured=is_string($directory)&&$directory!=='';$actual=$configured&&strpos($directory,"\0")===false?realpath($directory):false;
    $root=defined('ABSPATH')?realpath(ABSPATH):false;$web=is_string($_SERVER['DOCUMENT_ROOT']??null)?realpath($_SERVER['DOCUMENT_ROOT']):false;
    $readable=$actual&&is_dir($actual)&&is_readable($actual);$writable=$actual&&is_dir($actual)&&is_writable($actual);
    $mode=$actual?@fileperms($actual):false;$private=is_int($mode)&&($mode&0077)===0;
    $outside=$actual&&$root&&$web&&$actual!==$root&&$actual!==$web&&strpos($actual,$root.DIRECTORY_SEPARATOR)!==0&&strpos($actual,$web.DIRECTORY_SEPARATOR)!==0;
    return ['configured'=>$configured,'safe'=>(bool)($outside&&$private&&$readable&&$writable&&!is_link($directory)),
        'readable'=>(bool)$readable,'writable'=>(bool)$writable,'private_permissions'=>$private];
}
function sanro_studio_runtime_readiness($product_id=0) {
    $flags=[];
    foreach(['BUYER_EDITOR','DRAFT_API','PRIVATE_PREVIEWS','PREVIEW_UPLOAD','PRIVATE_MASTERS','MASTER_UPLOAD','WC_PRICING','STAGING_CHECKOUT_HOOK','DTF_IMAGE_RASTER','DTF_LAYER_RASTER','DTF_PRIVATE_ARTIFACTS','DRIVE_MASTER_EXPORT','DTF_ADMIN_GENERATE','DTF_ORDER_BATCH','DRIVE_AUTO_PROVISION','VERIFIED_DRIVE_ATTACHMENT','DRIVE_PACKAGE_EXPORT','DRIVE_DTF_CANDIDATES'] as $key){$name='SANRO_STUDIO_ENABLE_'.$key;$flags[$key]=defined($name)&&constant($name)===true;}
    $extensions=[];foreach(['gd','ctype','json','fileinfo'] as $ext)$extensions[$ext]=extension_loaded($ext);
    return ['build'=>sanro_studio_build_identity(),'php'=>['version'=>PHP_VERSION,'sapi'=>PHP_SAPI,'minimum_met'=>version_compare(PHP_VERSION,'7.4','>='),'memory_limit'=>ini_get('memory_limit'),'extensions'=>$extensions,'int64'=>PHP_INT_SIZE>=8,'gd_freetype'=>function_exists('imagettftext')],
        'woocommerce_loaded'=>class_exists('WooCommerce',false),'flags'=>$flags,
        'private_storage'=>['masters'=>sanro_studio_storage_health(defined('SANRO_STUDIO_PRIVATE_MASTER_DIR')?SANRO_STUDIO_PRIVATE_MASTER_DIR:null),'previews'=>sanro_studio_storage_health(defined('SANRO_STUDIO_PRIVATE_PREVIEW_DIR')?SANRO_STUDIO_PRIVATE_PREVIEW_DIR:null)],
        'private_master_configured'=>defined('SANRO_STUDIO_PRIVATE_MASTER_DIR')&&is_string(SANRO_STUDIO_PRIVATE_MASTER_DIR)&&SANRO_STUDIO_PRIVATE_MASTER_DIR!=='',
        'product'=>$product_id>0?sanro_studio_public_editor_config($product_id):null,'verified_end_to_end'=>false,'print_approved'=>false];
}
function sanro_studio_readiness_permission($request) {
    return current_user_can('manage_woocommerce')&&wp_verify_nonce($request->get_header('X-WP-Nonce'),'wp_rest');
}
function sanro_studio_register_readiness_route() {
    register_rest_route('sanro-studio/v1','/readiness',['methods'=>'GET','permission_callback'=>'sanro_studio_readiness_permission',
        'callback'=>static function($request){$raw=$request->get_param('product_id');$id=is_scalar($raw)&&preg_match('/^[0-9]+$/D',(string)$raw)?(int)$raw:0;
            $response=rest_ensure_response(sanro_studio_runtime_readiness($id));$response->header('Cache-Control','private, no-store, max-age=0');return $response;}]);
}
function sanro_studio_rest_source_header($response,$server,$request) {
    if(strpos($request->get_route(),'/sanro-studio/v1/')===0&&is_object($response)&&method_exists($response,'header')){
        $build=sanro_studio_build_identity();$response->header('X-Sanro-Source',$build['source_commit']);
    }return $response;
}
