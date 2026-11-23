<?php
defined('ABSPATH') || exit;

/** Operational guard, not an invented tax/coupon/fee business decision. */
function sanro_studio_checkout_policy() {
    return ['version'=>1,'currency'=>'IDR','tax'=>['decision'=>'OPEN','guard'=>'reject_nonzero'],
        'fees'=>['decision'=>'OPEN','guard'=>'reject_fee_lines'],'coupons'=>['decision'=>'OPEN','guard'=>'reject_applied_codes'],
        'discounts'=>['decision'=>'OPEN_ELIGIBILITY','guard'=>'reject_unverified_discount'],
        'repeat_discount'=>['decision'=>'DECIDED_5_PERCENT','eligibility'=>'NOT_IMPLEMENTED','guard'=>'disabled'],
        'shipping'=>['source'=>'native_Woo_server','guard'=>'freeze_whole_IDR_snapshot'],
        'native_variations'=>['scope'=>'deferred_unless_catalog_requires','v1'=>'design_size_and_sleeve_map']];
}
function sanro_studio_cart_has_studio($cart) {
    if(!is_object($cart)||!method_exists($cart,'get_cart'))return false;
    foreach($cart->get_cart() as $line)if(sanro_studio_pricing_is_line($line))return true;return false;
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
    if(sanro_studio_cart_policy_issues(sanro_studio_pricing_wc_cart()))wc_add_notice('SANRO Studio: kupon, pajak atau biaya tambahan pada pesanan ini belum didukung. Hubungi toko sebelum melanjutkan.','error');
}
function sanro_studio_coupon_valid_for_cart($valid,$coupon=null,$discounts=null) {
    return sanro_studio_cart_has_studio(sanro_studio_pricing_wc_cart())?false:$valid;
}
function sanro_studio_register_checkout_policy_hooks() {
    if(!sanro_studio_pricing_wc_enabled())return;
    add_filter('woocommerce_coupon_is_valid','sanro_studio_coupon_valid_for_cart',20,3);
    add_action('woocommerce_check_cart_items','sanro_studio_checkout_policy_check',5);
    add_action('woocommerce_checkout_process','sanro_studio_checkout_policy_check',1);
}
