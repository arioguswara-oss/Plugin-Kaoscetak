<?php
// Native WC numeric quantity boundary: no WordPress, order or DB writes.
define('ABSPATH',__DIR__);
require __DIR__.'/../includes/pricing-woocommerce.php';
require __DIR__.'/../includes/pricing-integration.php';
class TestPricingItem {public $meta=[];function update_meta_data($key,$value){$this->meta[$key]=$value;}}
function verified_context(){return ['id'=>'design','contract'=>['quote'=>['valid'=>true,'items'=>['M'=>['quantity'=>2,'unit_price'=>75000]]]],'sizes'=>[]];}
$checks=0;
foreach ([2,'2',2.0,'2.0'] as $qty){
    $GLOBALS['sanro_studio_pricing_checkout']=verified_context();$item=new TestPricingItem();
    sanro_studio_pricing_order_item($item,'key',['sanro_studio_pricing_version'=>1,'sanro_studio_size'=>'M','sanro_studio_design_id'=>'design','quantity'=>$qty],null);
    if (($item->meta['_sanro_studio_unit_price']??null)!==75000 || count($item->meta)!==4) throw new RuntimeException('Native integral quantity must retain verified metadata');$checks++;
}
foreach ([0,-1,1,2.5,'2oops',null,[],1000] as $qty){
    $GLOBALS['sanro_studio_pricing_checkout']=verified_context();$item=new TestPricingItem();
    try {sanro_studio_pricing_order_item($item,'key',['sanro_studio_pricing_version'=>1,'sanro_studio_size'=>'M','sanro_studio_design_id'=>'design','quantity'=>$qty],null);throw new LogicException('Mismatch accepted');}
    catch(RuntimeException $error){if($item->meta)throw new LogicException('Rejected allocation mutated metadata');$checks++;}
}
echo "PASS: $checks native checkout quantity boundary assertions\n";
