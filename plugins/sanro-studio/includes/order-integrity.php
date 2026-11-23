<?php
defined('ABSPATH') || exit;
require_once __DIR__.'/checkout-charges.php';

/** One internal saved-order contract for retry, payment, production and receipts. No repricing. */
function sanro_studio_saved_order_context($order, $require_previews = true) {
    if (!is_object($order) || !method_exists($order,'get_items') || !method_exists($order,'get_id')) return ['valid'=>false,'error'=>'invalid_order'];
    $items=$order->get_items('line_item');$id=null;$ids=[];
    if (!is_array($items)) return ['valid'=>false,'error'=>'invalid_items'];
    foreach($items as $item_id=>$item){
        if(!is_object($item)||!method_exists($item,'get_meta')) return ['valid'=>false,'error'=>'invalid_item'];
        $design=$item->get_meta('_sanro_studio_design_id',true);
        if($design===''){foreach(['_sanro_studio_size','_sanro_studio_design_snapshot','_sanro_studio_preview_front_ref','_sanro_studio_preview_back_ref','_sanro_studio_repeat_discount'] as $key)if($item->get_meta($key,true)!=='')return ['valid'=>false,'error'=>'order_design_mismatch'];continue;}
        if(!is_string($design)||!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD',$design)
            ||($id!==null&&$id!==$design)||!is_int($item_id)||$item_id<1)return ['valid'=>false,'error'=>'order_design_mismatch'];
        $id=$design;$ids[]=$item_id;
    }
    $marker=method_exists($order,'get_meta')?$order->get_meta('_sanro_studio_checkout_design_id',true):'';
    if($marker!==''&&$marker!==$id)return ['valid'=>false,'error'=>'order_design_mismatch'];
    if($id===null)return ['valid'=>true,'studio'=>false];
    sort($ids,SORT_NUMERIC);$record=get_option('sanro_studio_draft_'.$id,false);
    if(!is_array($record)||($record['status']??null)!=='locked'||($record['order_id']??null)!==$order->get_id()
        ||($record['order_item_ids']??null)!==$ids||!is_array($record['design']??null)||!is_array($record['checkout_quote']??null))return ['valid'=>false,'error'=>'order_snapshot_missing'];
    if(function_exists('sanro_studio_repeat_saved_order_allowed')&&!sanro_studio_repeat_saved_order_allowed($order,$record))return ['valid'=>false,'error'=>'repeat_order_integrity_failed'];
    $pinned=method_exists($order,'get_meta')?$order->get_meta('_sanro_studio_checkout_quote_hash',true):'';
    if($pinned!==''&&$pinned!==sanro_studio_frozen_quote_hash($record))return ['valid'=>false,'error'=>'order_quote_snapshot_mismatch'];
    $contract=['valid'=>true,'design_id'=>$id,'product_id'=>$record['product_id']??null,'quote'=>$record['checkout_quote'],'shipping_total'=>$record['shipping_total']??0];
    if(empty(sanro_studio_checkout_batch_lock_preflight($order,$contract)['valid']))return ['valid'=>false,'error'=>'order_amount_or_allocation_mismatch'];
    $hash=hash('sha256',maybe_serialize($record['design']));$allocation=[];
    foreach($record['design']['quantities']??[] as $size=>$qty)if($qty>0)$allocation[$size]=$qty;
    $quoted=[];foreach($record['checkout_quote']['items']??[] as $size=>$line)$quoted[$size]=$line['quantity']??null;
    ksort($allocation);ksort($quoted);if($allocation!==$quoted)return ['valid'=>false,'error'=>'order_quantity_snapshot_mismatch'];
    $lines=[];
    foreach($ids as $item_id){$item=$items[$item_id];
        if(function_exists('sanro_studio_repeat_item_evidence')){
            $expected=sanro_studio_repeat_item_evidence($record['checkout_quote'],$item->get_meta('_sanro_studio_size',true));$actual=$item->get_meta('_sanro_studio_repeat_discount',true);
            if($expected!==null?$actual!==$expected:($actual!==''&&$actual!==[]))return ['valid'=>false,'error'=>'repeat_item_evidence_mismatch'];
        }
        $snapshot=$item->get_meta('_sanro_studio_design_snapshot',true);
        if(!is_array($snapshot)||($snapshot['version']??null)!==1||($snapshot['design_id']??null)!==$id||($snapshot['design_hash']??null)!==$hash)return ['valid'=>false,'error'=>'order_design_snapshot_mismatch'];
        if($require_previews){
            if(($record['preview_design_hash']??null)!==$hash)return ['valid'=>false,'error'=>'order_preview_revision_mismatch'];
            foreach(['front','back'] as $side){$ref=$item->get_meta('_sanro_studio_preview_'.$side.'_ref',true);
                if(!is_string($ref)||$ref!==($record['preview_refs'][$side]??null)||empty(sanro_studio_bound_order_preview_bytes($order,$side,$item_id)['valid']))return ['valid'=>false,'error'=>'order_preview_mismatch'];
            }
        }
        $lines[$item_id]=['size'=>$item->get_meta('_sanro_studio_size',true),'quantity'=>sanro_studio_pricing_quantity($item->get_quantity()),'subtotal'=>(string)$item->get_subtotal(),'total'=>(string)$item->get_total(),
            'name'=>method_exists($item,'get_name')?(string)$item->get_name():''];
    }
    $payload=[1,$order->get_id(),method_exists($order,'get_order_number')?(string)$order->get_order_number():(string)$order->get_id(),$id,$record['product_id'],$record['design'],$record['master_refs']??[],$record['preview_refs']??[],$record['checkout_quote'],$record['shipping_total']??0,$lines];
    return ['valid'=>true,'studio'=>true,'design_id'=>$id,'record'=>$record,'item_ids'=>$ids,'contract'=>$contract,
        'fingerprint'=>hash('sha256',maybe_serialize($payload))];
}

/** Runs inside WC_Order::payment_complete's try/catch, before it clears the retry session. */
function sanro_studio_pricing_before_payment_complete($order_id) {
    $order=function_exists('wc_get_order')?wc_get_order((int)$order_id):null;
    $context=sanro_studio_saved_order_context($order,true);
    if(empty($context['valid'])||(!empty($context['studio'])&&method_exists($order,'get_status')&&in_array($order->get_status(),['cancelled','refunded','trash'],true)))throw new RuntimeException('SANRO Studio: saved design/order integrity must be recovered before confirming payment.');
}
