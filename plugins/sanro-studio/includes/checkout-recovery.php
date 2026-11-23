<?php
defined('ABSPATH') || exit;

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
    $items=$order->get_items('line_item');$ids=array_map('intval',array_keys($items));sort($ids,SORT_NUMERIC);
    if ($ids!==($record['order_item_ids']??null)) return null;
    $contract=['valid'=>true,'design_id'=>$record['design_id']??'', 'product_id'=>$record['product_id'],'quote'=>$record['checkout_quote']];
    if (empty(sanro_studio_checkout_batch_lock_preflight($order,$contract)['valid'])) return null;
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

    if (defined('SANRO_STUDIO_ENABLE_PRIVATE_PREVIEWS') && SANRO_STUDIO_ENABLE_PRIVATE_PREVIEWS===true) {
        $bound=sanro_studio_checkout_bind_existing_item_previews($order,$verified['id'],$verified['token'],$record['order_item_ids']);
        if (empty($bound['valid'])) throw new RuntimeException('SANRO Studio: private previews were not bound; retry this checkout.');
    }
    unset($GLOBALS['sanro_studio_pricing_checkout']);
}

/** Existing-order payment uses Woo nonce/order-key authorization, not a fresh draft/cart. */
function sanro_studio_pricing_pay_order_preflight($order) {
    if(!is_object($order)||!method_exists($order,'get_items'))return false;
    $ids=[];$design_id=null;
    foreach($order->get_items('line_item') as $item_id=>$item){$id=$item->get_meta('_sanro_studio_design_id',true);if($id==='')continue;if(!is_string($id)||($design_id!==null&&$design_id!==$id))return false;$design_id=$id;$ids[]=(int)$item_id;}
    if($design_id===null)return true; // ordinary Woo orders are unaffected
    $record=get_option('sanro_studio_draft_'.$design_id,false);sort($ids,SORT_NUMERIC);
    if(!is_array($record)||($record['status']??null)!=='locked'||($record['order_id']??null)!==$order->get_id()
        ||($record['order_item_ids']??null)!==$ids||!is_array($record['checkout_quote']??null))return false;
    $contract=['valid'=>true,'design_id'=>$design_id,'product_id'=>$record['product_id'],'quote'=>$record['checkout_quote']];
    if(empty(sanro_studio_checkout_batch_lock_preflight($order,$contract)['valid']))return false;
    foreach($ids as $item_id)foreach(['front','back'] as $side)if(empty(sanro_studio_bound_order_preview_bytes($order,$side,$item_id)['valid']))return false;
    return true;
}
function sanro_studio_pricing_before_pay_order($order) {
    if(!sanro_studio_pricing_pay_order_preflight($order))wp_die('SANRO Studio: design, preview or saved order price needs recovery before payment. Reopen the original checkout.','',['response'=>409]);
}
