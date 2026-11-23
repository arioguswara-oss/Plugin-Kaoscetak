<?php
require __DIR__.'/feature-checkout-flow.php';
class RecoverySession extends FlowSession {public $awaiting=88;function get($key){return $key==='order_awaiting_payment'?$this->awaiting:null;}function set($key,$v){if($key==='order_awaiting_payment')$this->awaiting=$v;}}
class RecoveryOrder extends FlowOrder {public $paid=false,$status='pending';function is_paid(){return $this->paid;}function get_status(){return $this->status;}}
function wc_get_order($id){return $id===88?($GLOBALS['recovery_order']??null):null;}
$retry=new RecoveryOrder();$retry->items=$order->items;$GLOBALS['recovery_order']=$retry;WC()->session=new RecoverySession();$checks=0;
$record=get_option($key);flow_check(sanro_studio_pricing_recoverable_order($record)===$retry,'exact awaiting-payment order recoverable');
$line=WC()->cart->cart_contents['line-1'];$line['quantity']=3;
$quote=sanro_studio_pricing_wc_quote($line,3,'cart');flow_check($quote['unit_price']===62000,'retry uses locked official quote');
try{sanro_studio_pricing_wc_quote($line,4,'cart');throw new LogicException('changed quantity accepted');}catch(RuntimeException $e){$checks++;}
sanro_studio_pricing_checkout_authorize();flow_check(($GLOBALS['sanro_studio_pricing_checkout']['existing_order_id']??0)===88,'same-order retry authorized');
$verified=$GLOBALS['sanro_studio_pricing_checkout'];
# Native Woo classic checkout may replace saved line items on a retry.
$new=[];foreach($retry->items as $old){$item=new FlowItem($old->id+100,$old->qty,$old->unit);$item->meta=$old->meta;unset($item->meta['_sanro_studio_preview_front_ref'],$item->meta['_sanro_studio_preview_back_ref']);$new[$item->id]=$item;}$retry->items=$new;foreach($retry->items as $item){$size=$item->get_meta('_sanro_studio_size');sanro_studio_pricing_order_item($item,'retry',['sanro_studio_pricing_version'=>1,'sanro_studio_size'=>$size,'sanro_studio_design_id'=>$id,'quantity'=>$item->qty],$retry);}
sanro_studio_pricing_validate_order($retry,[]);sanro_studio_pricing_lock_order($retry);
flow_check(get_option($key)['order_item_ids']===[171,172],'retry replaces only item-ID allocation on original locked order');
flow_check($retry->items[171]->get_meta('_sanro_studio_preview_front_ref')===$GLOBALS['refs']['front'],'retry repairs private preview binding');
$GLOBALS['sanro_studio_pricing_checkout']=$verified;sanro_studio_pricing_lock_order($retry);flow_check(!isset($GLOBALS['sanro_studio_pricing_checkout']),'repeat binding is idempotent');
$retry->paid=true;flow_check(sanro_studio_pricing_recoverable_order(get_option($key))===null,'paid order cannot resume/reprice');$retry->paid=false;
foreach(['cancelled','refunded','on-hold'] as $status){$retry->status=$status;flow_check(sanro_studio_pricing_recoverable_order(get_option($key))===null,'ineligible status denied');}$retry->status='failed';
flow_check(sanro_studio_pricing_recoverable_order(get_option($key))===$retry,'failed payment retains same-order retry');
WC()->session->awaiting=99;flow_check(sanro_studio_pricing_recoverable_order(get_option($key))===null,'different awaiting order denied');
WC()->session->awaiting=88;$retry->items[171]->total=1;flow_check(sanro_studio_pricing_recoverable_order(get_option($key))===null,'tampered persisted amount denies resume');
$retry->items[171]->total=186000;$retry->status='pending';
class FailedPreviewItem extends FlowItem {public $fail=true;function save(){return $this->fail?false:$this->id;}}
$bad=new FailedPreviewItem(171,3,62000);$bad->meta=$retry->items[171]->meta;unset($bad->meta['_sanro_studio_preview_front_ref'],$bad->meta['_sanro_studio_preview_back_ref']);$retry->items[171]=$bad;
$verified['existing_order_id']=88;WC()->session->awaiting=0;$GLOBALS['sanro_studio_pricing_checkout']=$verified;
try{sanro_studio_pricing_lock_order($retry);throw new LogicException('failed preview save accepted');}catch(RuntimeException $e){$checks++;}
flow_check(WC()->session->awaiting===88,'early order-created failure preserves native awaiting-payment retry');
$bad->fail=false;unset($bad->meta['_sanro_studio_preview_front_ref'],$bad->meta['_sanro_studio_preview_back_ref']);
sanro_studio_pricing_lock_order($retry);flow_check(!isset($GLOBALS['sanro_studio_pricing_checkout'])&&$bad->get_meta('_sanro_studio_preview_back_ref')===$GLOBALS['refs']['back'],'same-order retry completes failed private-preview save');
flow_check(sanro_studio_pricing_pay_order_preflight($retry),'existing-order payment validates saved quote and complete bound previews without changing catalog prices');
$backref=$bad->meta['_sanro_studio_preview_back_ref'];unset($bad->meta['_sanro_studio_preview_back_ref']);flow_check(!sanro_studio_pricing_pay_order_preflight($retry),'existing-order payment cannot bypass incomplete preview recovery');$bad->meta['_sanro_studio_preview_back_ref']=$backref;
$bad->total=0;flow_check(!sanro_studio_pricing_pay_order_preflight($retry),'zeroed saved total cannot bypass pay-order guard');$bad->total=186000;
echo "PASS: $checks same-order checkout recovery checks\n";
