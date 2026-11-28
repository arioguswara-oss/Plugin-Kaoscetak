<?php
defined('ABSPATH') || exit;

/** Official prices are whole IDR. Native shipping may add whole IDR, never client price fields. */
function sanro_studio_idr_charge($value) {
    if((!is_string($value)&&!is_int($value)&&!is_float($value))||!preg_match('/^(0|[1-9][0-9]*)(?:\.0{1,2})?$/D',(string)$value))return null;
    $amount=(float)$value;return $amount<=1000000000?(int)$amount:null;
}
function sanro_studio_order_shipping_matches($order,$expected) {
    if(!is_int($expected)||$expected<0)return false;
    $amount=method_exists($order,'get_shipping_total')?sanro_studio_idr_charge($order->get_shipping_total()):0;
    if($amount!==$expected)return false;
    if(method_exists($order,'get_coupon_codes')&&$order->get_coupon_codes())return false;
    // No unapproved tax/fee/discount policy is inferred from this shipping bridge.
    foreach(['get_total_tax','get_discount_total'] as $method)if(method_exists($order,$method)&&sanro_studio_idr_charge($order->$method())!==0)return false;
    if(method_exists($order,'get_shipping_total')){
        $sum=0;foreach($order->get_items('shipping') as $line){if(!is_object($line)||!method_exists($line,'get_total'))return false;$part=sanro_studio_idr_charge($line->get_total());if($part===null)return false;$sum+=$part;}
        if($sum!==$expected||$order->get_items('fee'))return false;
    }
    return true;
}
function sanro_studio_capture_native_shipping($order,$contract) {
    $cart=sanro_studio_pricing_wc_cart();if(!is_object($cart))return ['valid'=>false];
    if(function_exists('sanro_studio_cart_policy_issues')&&sanro_studio_cart_policy_issues($cart))return ['valid'=>false];
    $shipping=is_object($cart)&&method_exists($cart,'get_shipping_total')?sanro_studio_idr_charge($cart->get_shipping_total()):0;
    if($shipping===null||!sanro_studio_order_shipping_matches($order,$shipping))return ['valid'=>false];
    if(method_exists($cart,'get_totals')){$totals=$cart->get_totals();if(!is_array($totals)||sanro_studio_idr_charge($totals['total']??null)!==($contract['quote']['total_price']??-1)+$shipping)return ['valid'=>false];}
    return ['valid'=>true,'shipping_total'=>$shipping];
}
function sanro_studio_frozen_quote_hash($record) {
    $shipping=$record['shipping_total']??0;
    return hash('sha256',maybe_serialize($shipping===0?$record['checkout_quote']:[$record['checkout_quote'],$shipping]));
}
