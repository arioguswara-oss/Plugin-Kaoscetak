<?php
/** Owner-authorized ONE unpaid synthetic order. Uses native Studio/Woo checkout hooks; never invokes payment. */
const SSWR_DESIGN='4233e0e2-7c89-408f-b36b-fb76ce5adbc2';
function sswr_acceptance_record($value=null) {
    $path=sswr_private_root().'/acceptance.json';
    if($value!==null){sswr_write($path,wp_json_encode($value,JSON_UNESCAPED_SLASHES));return $value;}
    if(!file_exists($path))return null;
    if(is_link($path)||!is_file($path)||(fileperms($path)&0077)||filesize($path)>65536)throw new RuntimeException('unsafe_acceptance_record');
    $r=json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR);
    if(($r['design_id']??null)!==SSWR_DESIGN)throw new RuntimeException('acceptance_design_mismatch');
    return $r;
}
function sswr_acceptance_cart() {
    if(!function_exists('wc_load_cart')||!function_exists('WC'))throw new RuntimeException('woocommerce_unavailable');
    if(!WC()->cart)wc_load_cart();$cart=WC()->cart;$lines=$cart->get_cart();
    if(count($lines)!==3)throw new RuntimeException('acceptance_requires_three_size_lines');
    $sizes=[];
    foreach($lines as $line) {
        if(!sanro_studio_pricing_is_line($line)||($line['sanro_studio_design_id']??null)!==SSWR_DESIGN
            ||($line['product_id']??null)!==24||sanro_studio_pricing_quantity($line['quantity']??null)!==1)throw new RuntimeException('acceptance_cart_mismatch');
        $sizes[]=$line['sanro_studio_size'];
    }sort($sizes);if($sizes!==['L','M','S'])throw new RuntimeException('acceptance_sizes_mismatch');
    $cart->calculate_totals();if((string)$cart->get_total('edit')!=='240000'&&(string)$cart->get_total('edit')!=='240000.00')throw new RuntimeException('acceptance_total_mismatch');
    return $cart;
}
function sswr_acceptance_order_summary($order) {
    $context=sanro_studio_saved_order_context($order,true);
    if(empty($context['valid'])||($context['design_id']??null)!==SSWR_DESIGN||$order->is_paid()||$order->get_currency()!=='IDR'||(float)$order->get_total()!==240000.0)throw new RuntimeException('acceptance_order_integrity_failed');
    $items=[];foreach($order->get_items() as $id=>$item)$items[]=['item_id'=>$id,'size'=>$item->get_meta('_sanro_studio_size',true),'quantity'=>$item->get_quantity(),'unit_price'=>$item->get_meta('_sanro_studio_unit_price',true),'total'=>$item->get_total(),'design_id'=>$item->get_meta('_sanro_studio_design_id',true),'front_preview_bound'=>$item->get_meta('_sanro_studio_preview_front_ref',true)!=='','back_preview_bound'=>$item->get_meta('_sanro_studio_preview_back_ref',true)!==''];
    return ['order_id'=>$order->get_id(),'status'=>$order->get_status(),'paid'=>false,'total'=>$order->get_total(),'currency'=>'IDR','customer_id'=>$order->get_customer_id(),'design_id'=>SSWR_DESIGN,'canonical_order_integrity'=>'PASS','items'=>$items];
}
function sswr_create_synthetic_order() {
    $gate=sswr_preflight();if(!$gate['installed'])throw new RuntimeException('installed_candidate_required');
    if(!current_user_can('manage_woocommerce'))throw new RuntimeException('manage_woocommerce_required');
    $root=sswr_private_root();$path=$root.'/acceptance.lock';if(is_link($path))throw new RuntimeException('unsafe_acceptance_lock');
    $lock=fopen($path,'c+');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('acceptance_busy');chmod($path,0600);
    try {
        $record=sswr_acceptance_record();
        if($record){if(!empty($record['order_id']))return sswr_acceptance_order_summary(wc_get_order($record['order_id']));throw new RuntimeException('previous_order_attempt_requires_readback_no_second_order');}
        sswr_acceptance_cart();wc_clear_notices();sanro_studio_pricing_checkout_authorize();
        $verified=$GLOBALS['sanro_studio_pricing_checkout']??null;
        if(wc_notice_count('error')||($verified['id']??null)!==SSWR_DESIGN||!empty($verified['existing_order_id']))throw new RuntimeException('native_checkout_authorization_failed');
        $record=['schema'=>1,'design_id'=>SSWR_DESIGN,'source'=>SSWR_SOURCE,'state'=>'creating','order_id'=>null,'payment_invoked'=>false,'customer_profile_updated'=>false];
        sswr_acceptance_record($record); // Durable claim before Woo writes. Never auto-retry an ambiguous attempt.
        $mark=static function($order)use($record){$order->update_meta_data('_sanro_studio_acceptance_source',SSWR_SOURCE);$order->update_meta_data('_sanro_studio_acceptance_run',SSWR_DESIGN);$order->set_created_via('sanro-staging-acceptance');};
        $capture=static function($order)use($record){$record['state']='created_pending_verification';$record['order_id']=$order->get_id();sswr_acceptance_record($record);};
        $guest=static function(){return 0;};
        add_action('woocommerce_checkout_create_order',$mark,1,1);add_action('woocommerce_checkout_order_created',$capture,1,1);add_filter('woocommerce_checkout_customer_id',$guest,999);
        $data=['billing_first_name'=>'SANRO','billing_last_name'=>'STAGING ACCEPTANCE','billing_email'=>'sanro-studio-acceptance@example.invalid','billing_country'=>'ID','billing_state'=>'JT','billing_city'=>'Kudus','billing_postcode'=>'59316','billing_address_1'=>'SYNTHETIC STAGING - DO NOT SHIP','shipping_country'=>'ID','shipping_state'=>'JT','shipping_city'=>'Kudus','shipping_postcode'=>'59316','shipping_address_1'=>'SYNTHETIC STAGING - DO NOT SHIP','order_comments'=>'Owner-authorized synthetic acceptance only. No real payment or shipment.','payment_method'=>''];
        try{$id=WC()->checkout()->create_order($data);}finally{remove_action('woocommerce_checkout_create_order',$mark,1);remove_action('woocommerce_checkout_order_created',$capture,1);remove_filter('woocommerce_checkout_customer_id',$guest,999);}
        if(is_wp_error($id)||!is_numeric($id))throw new RuntimeException('native_order_creation_failed_no_automatic_retry');
        $order=wc_get_order((int)$id);$summary=sswr_acceptance_order_summary($order);$record=sswr_acceptance_record();$record['state']='verified_unpaid';$record['order_id']=$order->get_id();$record['summary']=$summary;sswr_acceptance_record($record);
        return $summary;
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
function sswr_generate_synthetic_dtf() {
    $leased=sswr_config_lease_live();sswr_preflight($leased);$record=sswr_acceptance_record();
    if(!is_array($record)||empty($record['order_id']))throw new RuntimeException('synthetic_order_required');
    $order=wc_get_order($record['order_id']);sswr_acceptance_order_summary($order);
    if(!sanro_studio_auto_drive_order_eligible($order))throw new RuntimeException('explicit_production_ready_required');
    $flags=['DTF_ADMIN_GENERATE','DTF_ORDER_BATCH','DTF_IMAGE_RASTER','DTF_LAYER_RASTER','DTF_PRIVATE_ARTIFACTS'];
    foreach($flags as $flag)if(defined('SANRO_STUDIO_ENABLE_'.$flag)&&!($leased&&constant('SANRO_STUDIO_ENABLE_'.$flag)===true))throw new RuntimeException('explicit_runtime_flag_requires_server_configuration_'.$flag);
    if(defined('SANRO_STUDIO_DTF_ARTIFACT_DIR'))throw new RuntimeException('existing_artifact_configuration_requires_review');
    $dir=sswr_private_root().'/dtf-acceptance';if(!is_dir($dir)&&!mkdir($dir,0700))throw new RuntimeException('artifact_root_create_failed');
    if(is_link($dir)||realpath($dir)!==$dir||(fileperms($dir)&0077))throw new RuntimeException('unsafe_artifact_root');
    define('SANRO_STUDIO_DTF_ARTIFACT_DIR',$dir);
    // Request-local opt-in only AFTER capability, nonce, installed hash and exact synthetic order checks.
    // No configuration file/option is changed; the next HTTP request has all writers OFF again.
    foreach($flags as $flag)if(!defined('SANRO_STUDIO_ENABLE_'.$flag))define('SANRO_STUDIO_ENABLE_'.$flag,true);
    $results=[];
    foreach($order->get_items() as $id=>$item){
        $generated=sanro_studio_dtf_admin_generate_candidates($order,$id);
        if(empty($generated['valid']))throw new RuntimeException('dtf_'.$generated['error']);
        $status=sanro_studio_dtf_order_item_private_status($order,$id);
        $package=sanro_studio_prepare_production_package($order,$id);
        if(empty($package['valid']))throw new RuntimeException('package_'.$package['error']);
        $artifacts=[];foreach($package['assets'] as $asset){$bytes=$asset['bytes'];$name='item-'.$id.'-'.$asset['kind'].'-'.$asset['side'].'.'.$asset['ext'];sswr_write($dir.'/'.$name,$bytes);$artifacts[]=['file'=>$name,'bytes'=>strlen($bytes),'sha256'=>hash('sha256',$bytes),'mime'=>$asset['mime']];}
        sswr_write($dir.'/item-'.$id.'-manifest.json',wp_json_encode($package['manifest'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        $results[]=['generation'=>$generated,'private_readback'=>$status,'package_manifest'=>$package['manifest'],'local_assets'=>$artifacts];
    }
    $record['dtf']=['state'=>'PASS_PRIVATE_CANDIDATES_AND_LOCAL_PDF','quality_approved'=>false,'print_ready'=>false,'results'=>$results,'flags_persisted'=>false,'drive_invoked'=>false];sswr_acceptance_record($record);return $record['dtf'];
}
function sswr_synthetic_local_package(){
    sswr_preflight();$r=sswr_acceptance_record();if(($r['order_id']??null)!==56)throw new RuntimeException('exact_order_required');$order=wc_get_order(56);sswr_acceptance_order_summary($order);
    if(!sanro_studio_auto_drive_order_eligible($order))throw new RuntimeException('production_approval_required');
    $dir=sswr_private_root().'/production-package';if(!is_dir($dir)&&!mkdir($dir,0700))throw new RuntimeException('package_directory_failed');if(is_link($dir)||($mode=fileperms($dir))===false||($mode&0077))throw new RuntimeException('unsafe_package_directory');
    $items=[];foreach($order->get_items() as $id=>$item){$p=sanro_studio_prepare_production_package($order,$id);if(empty($p['valid']))throw new RuntimeException('package_'.$p['error']);$assets=[];foreach($p['assets'] as $a){$name='item-'.$id.'-'.$a['kind'].'-'.$a['side'].'.'.$a['ext'];sswr_write($dir.'/'.$name,$a['bytes']);$assets[]=['file'=>$name,'bytes'=>strlen($a['bytes']),'sha256'=>hash('sha256',$a['bytes']),'mime'=>$a['mime']];}sswr_write($dir.'/item-'.$id.'-manifest.json',wp_json_encode($p['manifest'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));$items[]=['item_id'=>$id,'manifest'=>$p['manifest'],'assets'=>$assets];}
    $r['local_package']=['state'=>'PASS_MANIFEST_PRIVATE_PREVIEWS_UNICODE_PDF','items'=>$items,'dtf_generated'=>false,'drive_invoked'=>false,'flags_changed'=>false];sswr_acceptance_record($r);return $r['local_package'];
}
function sswr_acceptance_page() {
    echo '<h2>One synthetic unpaid acceptance order</h2><p>No gateway, payment, customer profile update or shipment. Same native Studio checkout authorization/order hooks. Maximum one order; ambiguous attempts fail closed.</p>';
    $status=sswr_private_root().'/acceptance-status.json';if(is_file($status)&&!is_link($status)&&filesize($status)<4096)echo '<pre>'.esc_html(file_get_contents($status)).'</pre>';
    $lease=sswr_config_lease();if($lease)echo '<pre>'.esc_html(wp_json_encode(['dtf_config_lease'=>$lease['state'],'expires'=>$lease['expires'],'default_and_get_writers'=>'OFF'])).'</pre>';
    $record=sswr_acceptance_record();
    if($record){
        echo '<pre>'.esc_html(wp_json_encode($record,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)).'</pre>';
        if(!empty($record['order_id'])){echo '<a href="'.esc_url(wc_get_order($record['order_id'])->get_edit_order_url()).'">Open synthetic WooCommerce order</a>';
            if(!empty($record['local_package']))echo '<p><a href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=sswr_download_package'),'sswr-download-'.SSWR_SOURCE)).'">Download private order56 acceptance package</a></p>';
            echo '<form method="post">';wp_nonce_field('sswr-'.SSWR_SOURCE);echo '<input type="hidden" name="source" value="'.esc_attr(SSWR_SOURCE).'"><input type="hidden" name="acceptance" value="dtf">';submit_button('Generate private DTF and PDF for this order','primary','sswr_generate');echo '</form>';
            foreach(['arm_dtf'=>'Arm bounded DTF configuration for this order','restore_dtf'=>'Restore original DTF configuration','package_local'=>'Build private PDF and manifest without DTF'] as $action=>$label){echo '<form method="post">';wp_nonce_field('sswr-'.SSWR_SOURCE);echo '<input type="hidden" name="source" value="'.esc_attr(SSWR_SOURCE).'"><input type="hidden" name="acceptance" value="'.esc_attr($action).'">';submit_button($label,'primary','sswr_'.$action);echo '</form>';}
        }
        return;
    }
    sswr_acceptance_cart();echo '<form method="post" class="checkout">';wp_nonce_field('sswr-'.SSWR_SOURCE);echo '<input type="hidden" name="source" value="'.esc_attr(SSWR_SOURCE).'"><input type="hidden" name="acceptance" value="create_unpaid">';sanro_studio_pricing_checkout_field();submit_button('Create the single synthetic unpaid order','primary','sswr_create');echo '</form>';
    wp_enqueue_script('sanro-acceptance-auth',plugins_url('sanro-studio/assets/checkout-auth.js'),['jquery'],'0.1.0',true);
}
add_action('admin_post_sswr_download_package',static function(){
    if(!current_user_can('manage_options')||!current_user_can('manage_woocommerce'))wp_die('Forbidden',403);
    check_admin_referer('sswr-download-'.SSWR_SOURCE);sswr_preflight();$r=sswr_acceptance_record();if(($r['order_id']??null)!==56||empty($r['local_package']))wp_die('Package unavailable',409);sswr_acceptance_order_summary(wc_get_order(56));
    $root=sswr_private_root();$path=$root.'/order-56-acceptance.zip';if(is_link($path))wp_die('Unsafe package',409);$zip=new ZipArchive();if($zip->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)wp_die('Package unavailable',500);
    $count=0;$size=0;
    foreach(['production-package','dtf-acceptance'] as $kind){$dir=$root.'/'.$kind;if(!is_dir($dir))continue;
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS)) as $file){
            if($file->isLink()||!$file->isFile()||++$count>100||($size+=$file->getSize())>33554432){$zip->close();wp_die('Package boundary refused',409);}
            $relative=substr($file->getPathname(),strlen($dir)+1);if(!preg_match('~^[A-Za-z0-9_./-]+$~D',$relative)||strpos($relative,'..')!==false){$zip->close();wp_die('Package boundary refused',409);}
            $zip->addFile($file->getPathname(),$kind.'/'.$relative);
        }
    }$zip->close();chmod($path,0600);nocache_headers();header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="sanro-studio-order-56-acceptance.zip"');header('Content-Length: '.filesize($path));readfile($path);exit;
});
