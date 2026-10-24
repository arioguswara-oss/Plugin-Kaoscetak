<?php
/** Isolated simulation of WooCommerce cart price hook and checkout-email guard. */
define('ABSPATH', __DIR__);
$GLOBALS['hooks'] = [];
$GLOBALS['prices'] = [501=>['S'=>['A5'=>62000],'L'=>['A3'=>88000]]];
$GLOBALS['designs'] = [201=>['_kld_owner_email'=>'repeat@example.com','_kld_sides'=>['front'=>['tier'=>'A5'],'back'=>['tier'=>'A3']], '_kld_product'=>501],202=>['_kld_owner_email'=>'','_kld_sides'=>['front'=>['tier'=>'A5'],'back'=>['tier'=>'A3']], '_kld_product'=>501]];
$GLOBALS['paid'] = ['repeat@example.com'=>[12]];
function add_action($hook,$fn,$priority=10,$accepted=1){$GLOBALS['hooks'][$hook][]=$fn;}
function add_filter($hook,$fn,$priority=10,$accepted=1){$GLOBALS['filters'][$hook][]=$fn;}
function absint($x){return abs((int)$x);}
function get_post_meta($id,$key,$single=true){return $GLOBALS['designs'][$id][$key]??'';}
function sanitize_email($s){return filter_var($s,FILTER_SANITIZE_EMAIL);}
function is_email($s){return filter_var($s,FILTER_VALIDATE_EMAIL)!==false;}
function wc_get_orders($args){return $GLOBALS['paid'][$args['billing_email']]??[];}
function wc_get_is_paid_statuses(){return ['processing','completed'];}
function is_admin(){return false;}
function wp_doing_ajax(){return false;}
function kld_unit_price($product,$size,$front,$back){return $product===501&&$size==='L'&&$front==='A5'&&$back==='A3'?103000:null;}
class FakeProduct {public $price=0;function set_price($n){$this->price=$n;}function get_price(){return $this->price;}}
class FakeCustomer {public $email='';function get_billing_email(){return $this->email;}}
class FakeCart {public $items=[];function get_cart(){return $this->items;}}
class FakeWoo {public $customer;public $cart;function __construct(){$this->customer=new FakeCustomer;$this->cart=new FakeCart;}}
class FakeErrors {public $errors=[];function add($code,$message){$this->errors[$code]=$message;}}
$GLOBALS['wc']=new FakeWoo;
function WC(){return $GLOBALS['wc'];}
require __DIR__.'/../plugins/kaoscetak-lite-designer/includes/repeat.php';
require __DIR__.'/../plugins/kaoscetak-lite-designer/includes/store.php';
function expect($condition,$message){if(!$condition)throw new Exception('FAIL '.$message);}
$checkout=$GLOBALS['hooks']['woocommerce_after_checkout_validation'][0];
$priceHook=$GLOBALS['hooks']['woocommerce_before_calculate_totals'][0];
$buyer=WC()->customer;$cart=WC()->cart;
$cart->items=[['kld_design_id'=>201,'kld_size'=>'L','kld_unit_price'=>103000,'data'=>new FakeProduct]];
$buyer->email='repeat@example.com';$priceHook($cart);
expect($cart->items[0]['data']->get_price()===97850,'verified repeat discount for one piece');
$err=new FakeErrors;$checkout(['billing_email'=>'changed@example.com'],$err);
expect(isset($err->errors['kld_repeat_email_mismatch']),'changed checkout email must reject discounted item');
$err=new FakeErrors;$checkout(['billing_email'=>'repeat@example.com'],$err);
expect(!$err->errors,'matching verified email allows discount');
$buyer->email='changed@example.com';$priceHook($cart);
expect($cart->items[0]['data']->get_price()===103000,'recalculate full rate on changed billing email');
$err=new FakeErrors;$checkout(['billing_email'=>'changed@example.com'],$err);
expect(!$err->errors,'full rate allows another buyer email');
$buyer->email='repeat@example.com';$cart->items[0]['kld_design_id']=202;$priceHook($cart);
expect($cart->items[0]['data']->get_price()===103000,'unverified fork cannot receive repeat discount');
$err=new FakeErrors;$checkout(['billing_email'=>'repeat@example.com'],$err);
expect(!$err->errors,'unverified design safe full price');
echo 'PASS: 7 isolated cart and checkout email assertions'.PHP_EOL;