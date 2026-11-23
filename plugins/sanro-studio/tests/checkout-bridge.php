<?php
define('ABSPATH', __DIR__);
require dirname(__DIR__) . '/includes/cart-contract.php';
require dirname(__DIR__) . '/includes/checkout-bridge.php';
function sanro_studio_validate_order_item_quote_amount($item, $quote) {
    return $item->get_subtotal() === '90000.00'
        ? ['valid'=>true] : ['valid'=>false,'error'=>'order_amount_mismatch'];
}
class SanroCheckoutTestItem {
    public $writes=0;
    private $product, $qty, $subtotal, $meta;
    function __construct($product=42,$qty=2,$subtotal='90000.00',$meta='') {
        $this->product=$product; $this->qty=$qty; $this->subtotal=$subtotal; $this->meta=$meta;
    }
    function get_product_id(){return $this->product;}
    function get_quantity(){return $this->qty;}
    function get_subtotal(){return $this->subtotal;}
    function get_meta($key,$single=true){return $this->meta;}
    function update_meta_data($key,$value){$this->meta=$value; ++$this->writes;}
}
$id='11111111-1111-4111-8111-111111111111';
$contract=['valid'=>true,'design_id'=>$id,'product_id'=>42,
    'quote'=>['valid'=>true,'currency'=>'IDR','total_quantity'=>2,'total_price'=>90000]];
$cart=['product_id'=>42,'sanro_studio_design_id'=>$id,'quantity'=>2];
$cases=[
    'valid'=>[$contract,$cart,new SanroCheckoutTestItem(),true,null,1],
    'foreign_cart_design'=>[$contract,array_merge($cart,['sanro_studio_design_id'=>'forged']),new SanroCheckoutTestItem(),false,'cart_identity_mismatch',0],
    'wrong_order_product'=>[$contract,$cart,new SanroCheckoutTestItem(43),false,'order_identity_mismatch',0],
    'wrong_order_quantity'=>[$contract,$cart,new SanroCheckoutTestItem(42,3),false,'order_identity_mismatch',0],
    'tampered_subtotal'=>[$contract,$cart,new SanroCheckoutTestItem(42,2,'1.00'),false,'order_amount_mismatch',0],
    'existing_design'=>[$contract,$cart,new SanroCheckoutTestItem(42,2,'90000.00','foreign'),false,'order_design_already_bound',0],
];
foreach ($cases as $name=>[$contractCase,$cartCase,$item,$valid,$error,$writes]) {
    $result=sanro_studio_prepare_checkout_order_item($contractCase,$cartCase,$item);
    if (($result['valid']??false)!==$valid || (!$valid && ($result['error']??null)!==$error)
        || $item->writes!==$writes || ($result['checkout_enabled']??false)!==false) {
        fwrite(STDERR,"FAIL: $name\n");exit(1);
    }
    echo "PASS: $name\n";
}
