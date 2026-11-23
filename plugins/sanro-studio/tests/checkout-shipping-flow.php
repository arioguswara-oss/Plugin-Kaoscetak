<?php
require __DIR__.'/feature-checkout-flow.php';
function wc_get_order($id){return $GLOBALS['shipping_order']??null;}
class ShippingLine {function get_total(){return '15000.00';}}
class ShippingOrder extends FlowOrder {public $shipping=15000,$tax=0;function get_shipping_total(){return $this->shipping;}function get_total_tax(){return $this->tax;}function get_discount_total(){return 0;}function get_items($type='line_item'){return $type==='shipping'?[new ShippingLine()]:parent::get_items($type);}function get_total(){return parent::get_total()+$this->shipping;}function is_paid(){return false;}function get_status(){return 'pending';}}
class ShippingCart extends FlowCart {function get_shipping_total(){return '15000.00';}function get_totals(){return ['total'=>'269000.00'];}}
$checks=0;$record=get_option($key);$record['status']='draft';unset($record['checkout_quote'],$record['order_id'],$record['order_item_ids'],$record['shipping_total']);$GLOBALS['options'][$key]=$record;
$cart=new ShippingCart();$cart->cart_contents=WC()->cart->cart_contents;WC()->cart=$cart;$ship=new ShippingOrder();$GLOBALS['shipping_order']=$ship;
sanro_studio_pricing_checkout_authorize();foreach($cart->get_cart() as $cartkey=>$line){$item=new FlowItem(count($ship->items)+71,(int)$line['quantity'],(int)$line['data']->get_price());sanro_studio_pricing_order_item($item,$cartkey,$line,$ship);$ship->items[$item->id]=$item;}
sanro_studio_pricing_validate_order($ship,[]);sanro_studio_pricing_lock_order($ship);
flow_check($ship->get_total()===269000&&get_option($key)['shipping_total']===15000,'IDR254000 official design + native shipping15000 locked as total269000');
flow_check(sanro_studio_saved_order_context($ship,true)['valid'],'payment and production share saved shipping-inclusive order validation');
$ship->shipping=16000;flow_check(!sanro_studio_saved_order_context($ship,true)['valid'],'shipping changed after lock is rejected');$ship->shipping=15000;
$ship->tax=1;flow_check(!sanro_studio_saved_order_context($ship,true)['valid'],'unapproved tax cannot silently alter official table');$ship->tax=0;
flow_check(sanro_studio_idr_charge('15000.01')===null&&sanro_studio_idr_charge('-1')===null,'fractional/negative charge rejected');
echo "PASS: $checks native Woo shipping snapshot checks\n";
