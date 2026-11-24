<?php
/** Exact-staging synthetic buyer acceptance: stop before profile/order/payment writes. */
function sswr_normal_checkout_boundary($data,$errors){
    if(rtrim(home_url('/'),'/')!==SSWR_TARGET||!current_user_can('manage_options')||!is_object($errors)||!method_exists($errors,'add'))return;
    $cart=function_exists('WC')?WC()->cart:null;$studio=false;
    foreach(is_object($cart)?(array)$cart->get_cart():[] as $line)if(sanro_studio_pricing_is_line($line)&&($line['product_id']??null)===24)$studio=true;
    if(!$studio)return;
    // Always block FIRST, including when evidence validation/storage fails. Never create a second order.
    $prior=method_exists($errors,'get_error_codes')?$errors->get_error_codes():[];
    $errors->add('sanro_staging_one_order_boundary','SANRO staging acceptance: validasi selesai; dihentikan sebelum order baru atau pembayaran. Order sintetis tetap #56.');
    try{
        sswr_identity();$accept=sswr_acceptance_record();if(($accept['order_id']??null)!==56)throw new RuntimeException('existing_order56_required');
        $order=sswr_acceptance_order_summary(wc_get_order(56));$v=$GLOBALS['sanro_studio_pricing_checkout']??null;
        $codes=[];foreach($prior as $code)if(is_string($code)&&preg_match('/^[a-z0-9_-]{1,80}$/iD',$code))$codes[]=$code;
        $valid=is_array($v)&&!empty($v['contract']['quote']['valid'])&&($v['contract']['product_id']??null)===24&&($v['contract']['quote']['total_price']??null)===240000;
        $r=['schema'=>1,'source'=>SSWR_SOURCE,'state'=>$valid?'STUDIO_VALIDATION_PASS_STOPPED_BEFORE_NEW_ORDER':'STUDIO_VALIDATION_FAILED','normal_woocommerce_validation_hook'=>true,'native_authorization'=>$valid,'quote_total'=>$valid?240000:null,'currency'=>'IDR','woo_validation_codes'=>$codes,'existing_order'=>['id'=>56,'total'=>$order['total'],'paid'=>false],'order_creation_invoked'=>false,'payment_invoked'=>false,'customer_profile_updated'=>false,'boundary_added_before_order_creation'=>true];
        sswr_write(sswr_private_root().'/normal-checkout.json',wp_json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    }catch(Throwable $e){/* Fail closed: the Woo error was already added. No raw exception/credential logging. */}
}
add_action('woocommerce_after_checkout_validation','sswr_normal_checkout_boundary',PHP_INT_MAX,2);
function sswr_normal_checkout_evidence(){
    $path=sswr_private_root().'/normal-checkout.json';
    if(is_file($path)&&!is_link($path)&&filesize($path)<4096)echo '<h2>Normal buyer checkout boundary acceptance</h2><pre>'.esc_html(file_get_contents($path)).'</pre>';
}
