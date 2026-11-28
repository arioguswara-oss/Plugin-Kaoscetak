<?php
defined('ABSPATH') || exit;

/** Operational guard, not an invented tax/coupon/fee business decision. */
function sanro_studio_checkout_policy() {
    return ['version'=>1,'currency'=>'IDR','tax'=>['decision'=>'OPEN','guard'=>'reject_nonzero'],
        'fees'=>['decision'=>'OPEN','guard'=>'reject_fee_lines'],'coupons'=>['decision'=>'OPEN','guard'=>'reject_applied_codes'],
        'discounts'=>['decision'=>'OPEN_OTHER_DISCOUNTS','guard'=>'reject_unverified_discount'],
        'repeat_discount'=>['decision'=>'DECIDED_5_PERCENT','eligibility'=>'verified_design_email_and_native_paid_history','guard'=>'server_net_unit_snapshot'],
        'shipping'=>['source'=>'native_Woo_server','guard'=>'freeze_whole_IDR_snapshot'],
        'native_variations'=>['scope'=>'deferred_unless_catalog_requires','v1'=>'design_size_and_sleeve_map']];
}
function sanro_studio_cart_has_studio($cart) {
    if(!is_object($cart)||!method_exists($cart,'get_cart'))return false;
    foreach($cart->get_cart() as $line)if(sanro_studio_pricing_is_line($line)
        ||(is_array($line)&&is_string($line['sanro_studio_design_id']??null)
            &&preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD',$line['sanro_studio_design_id'])))return true;
    return false;
}
function sanro_studio_cart_policy_issues($cart) {
    if(!sanro_studio_cart_has_studio($cart))return [];$issues=[];
    if(method_exists($cart,'get_applied_coupons')&&$cart->get_applied_coupons())$issues[]='coupon_policy_open';
    if(method_exists($cart,'get_fees')&&$cart->get_fees())$issues[]='fee_policy_open';
    if(method_exists($cart,'get_total_tax')&&sanro_studio_idr_charge($cart->get_total_tax())!==0)$issues[]='tax_policy_open';
    if(method_exists($cart,'get_discount_total')&&sanro_studio_idr_charge($cart->get_discount_total())!==0)$issues[]='discount_not_verified';
    return $issues;
}
function sanro_studio_checkout_policy_check() {
    if(sanro_studio_cart_has_studio(sanro_studio_pricing_wc_cart())
        &&(!function_exists('sanro_studio_pricing_wc_enabled')||!sanro_studio_pricing_wc_enabled())){
        wc_add_notice('SANRO Studio: konfigurasi harga desain tidak aktif. Hapus baris desain Studio dan buat ulang keranjang setelah layanan pulih.','error');
        return;
    }
    if(sanro_studio_cart_policy_issues(sanro_studio_pricing_wc_cart()))wc_add_notice('SANRO Studio: kupon, pajak atau biaya tambahan pada pesanan ini belum didukung. Hubungi toko sebelum melanjutkan.','error');
}
/** Store API/Blocks requires a WP_Error instead of legacy notices. */
function sanro_studio_store_api_checkout_policy_check($errors,$cart) {
    if(!is_object($errors)||!method_exists($errors,'add')||!sanro_studio_cart_has_studio($cart))return;
    // Studio's signed quote/lock workflow currently targets Classic Checkout.
    // Store API uses a separate order lifecycle and must not create an ordinary
    // WooCommerce line from a Studio cart until its equivalent lock is enabled.
    $errors->add('sanro_studio_store_api_checkout_unavailable',
        'SANRO Studio checkout is not available through this Checkout Block. Use the supported Classic Checkout flow.');
    return;
}
/** Final Store API boundary before payment: block a Studio cart if a caller bypassed cart validation. */
function sanro_studio_store_api_reject_unbound_order($order) {
    if (!sanro_studio_cart_has_studio(sanro_studio_pricing_wc_cart())) return;
    if (class_exists('Automattic\\WooCommerce\\StoreApi\\Exceptions\\RouteException')) {
        throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
            'sanro_studio_store_api_checkout_unavailable',
            'SANRO Studio order locking is not enabled for Store API checkout.', 409);
    }
    throw new RuntimeException('SANRO Studio order locking is not enabled for Store API checkout.');
}
function sanro_studio_coupon_valid_for_cart($valid,$coupon=null,$discounts=null) {
    return sanro_studio_cart_has_studio(sanro_studio_pricing_wc_cart())?false:$valid;
}
function sanro_studio_register_checkout_policy_hooks() {
    if(sanro_studio_pricing_wc_enabled())add_filter('woocommerce_coupon_is_valid','sanro_studio_coupon_valid_for_cart',20,3);
    add_action('woocommerce_check_cart_items','sanro_studio_checkout_policy_check',5);
    add_action('woocommerce_store_api_cart_errors','sanro_studio_store_api_checkout_policy_check',5,2);
    add_action('woocommerce_checkout_process','sanro_studio_checkout_policy_check',1);
}
