<?php
require __DIR__.'/feature-checkout-flow.php';
require __DIR__.'/../includes/pricing-profiles.php';
$checks=0;$GLOBALS['pricing_profile']='tunik_oversize_34';$GLOBALS['sanro_studio_pricing_checkout']=null;
$record=$GLOBALS['options'][$key];$record['status']='draft';foreach(['checkout_quote','order_id','order_item_ids','shipping_total'] as $field)unset($record[$field]);$record['design']['quantities']=['M'=>1,'L'=>2];$record['preview_design_hash']=hash('sha256',serialize($record['design']));$GLOBALS['options'][$key]=$record;WC()->cart=new FlowCart();
$result=sanro_studio_rest_pricing_draft_cart($request);flow_check(!empty($result['valid']),'Tunic draft passes native cart adapter');
sanro_studio_pricing_checkout_authorize();$tunic=new FlowOrder();foreach(WC()->cart->get_cart() as $cartkey=>$line){$item=new FlowItem(count($tunic->items)+71,(int)$line['quantity'],(int)$line['data']->get_price());sanro_studio_pricing_order_item($item,$cartkey,$line,$tunic);$tunic->items[$item->id]=$item;}
sanro_studio_pricing_validate_order($tunic,[]);sanro_studio_pricing_lock_order($tunic);
flow_check($tunic->get_total()===265000&&$GLOBALS['options'][$key]['checkout_quote']['sleeve_label']==='3/4','official M1 L2 Tunic quote locked at265000');
foreach($tunic->items as $item)flow_check($item->meta['_sanro_studio_design_snapshot']['sleeve_label']==='3/4','each multi-size item keeps fixed sleeve production metadata');
echo "PASS: $checks Tunic native checkout/locked snapshot checks\n";
