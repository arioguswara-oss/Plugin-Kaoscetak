<?php
defined('ABSPATH') || exit;
require_once __DIR__.'/order-integrity.php';

/** Resume only the exact unpaid order held by this Woo session. No new order authority. */
function sanro_studio_pricing_recoverable_order($record) {
    $wc=function_exists('WC')?WC():null;
    if (!is_array($record) || ($record['status']??null)!=='locked'
        || !is_string($record['wc_session_hash']??null)
        || !hash_equals($record['wc_session_hash'],(string)sanro_studio_pricing_session_hash())
        || !is_int($record['order_id']??null) || !function_exists('wc_get_order')
        || !is_object($wc) || !isset($wc->session) || !method_exists($wc->session,'get')
        || (int)$wc->session->get('order_awaiting_payment')!==$record['order_id']) return null;
    $order=wc_get_order($record['order_id']);
    if (!is_object($order) || !method_exists($order,'is_paid') || $order->is_paid()
        || !method_exists($order,'get_status') || !in_array($order->get_status(),['pending','failed'],true)
        || !is_array($record['checkout_quote']??null)) return null;
    if(empty(sanro_studio_saved_order_context($order,false)['valid']))return null;
    return $order;
}

/** Complete the same order's preview binding after a previous partial save. */
function sanro_studio_pricing_finish_order_binding($order,$verified,$record) {
    // Woo normally sets this after create_order returns. Preserve retry authority
    // even when a private-preview CRUD save fails inside order_created first.
    $wc=function_exists('WC')?WC():null;
    if(is_object($wc)&&isset($wc->session)&&method_exists($wc->session,'set')
        &&method_exists($order,'is_paid')&&!$order->is_paid()
        &&in_array($order->get_status(),['pending','failed'],true))$wc->session->set('order_awaiting_payment',$order->get_id());

    if(method_exists($order,'update_meta_data')&&method_exists($order,'save')){
        $marker=$order->get_meta('_sanro_studio_checkout_design_id',true);$quote_hash=sanro_studio_frozen_quote_hash($record);
        if($marker!==''&&$marker!==$verified['id'])throw new RuntimeException('SANRO Studio: saved order identity conflict.');
        $old_hash=$order->get_meta('_sanro_studio_checkout_quote_hash',true);
        if($old_hash!==''&&$old_hash!==$quote_hash)throw new RuntimeException('SANRO Studio: saved order quote conflict.');
        if($marker===''||$old_hash===''){$order->update_meta_data('_sanro_studio_checkout_design_id',$verified['id']);$order->update_meta_data('_sanro_studio_checkout_quote_hash',$quote_hash);if($order->save()===false)throw new RuntimeException('SANRO Studio: saved order identity needs recovery.');}
    }
    if (defined('SANRO_STUDIO_ENABLE_PRIVATE_PREVIEWS') && SANRO_STUDIO_ENABLE_PRIVATE_PREVIEWS===true) {
        $bound=sanro_studio_checkout_bind_existing_item_previews($order,$verified['id'],$verified['token'],$record['order_item_ids']);
        if (empty($bound['valid'])) throw new RuntimeException('SANRO Studio: private previews were not bound; retry this checkout.');
    }
    unset($GLOBALS['sanro_studio_pricing_checkout']);
}

/** Existing-order payment uses Woo nonce/order-key authorization, not a fresh draft/cart. */
function sanro_studio_pricing_pay_order_preflight($order) {
    return !empty(sanro_studio_saved_order_context($order,true)['valid']);
}
function sanro_studio_pricing_before_pay_order($order) {
    if(!sanro_studio_pricing_pay_order_preflight($order))wp_die('SANRO Studio: design, preview or saved order price needs recovery before payment. Reopen the original checkout.','',['response'=>409]);
}
