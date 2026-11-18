<?php
/** Isolated checkout gate tests; no WordPress or WooCommerce writes. */
define('ABSPATH', dirname(__DIR__, 3) . '/');
require_once __DIR__ . '/../includes/cart-contract.php';
require_once __DIR__ . '/../includes/checkout-bridge.php';
define('SANRO_STUDIO_ENABLE_STAGING_CART', true);
define('SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT', true);
function get_woocommerce_currency() { return 'IDR'; }
function sanro_studio_read_draft($id, $token) {
    if ($token !== 'owner' || $id !== '00000000-0000-4000-8000-000000000000') return ['valid'=>false];
    return ['valid'=>true,'status'=>'draft','design_id'=>$id,'product_id'=>42,
        'design'=>['quantities'=>['M'=>2]],'master_refs'=>[]];
}
function sanro_studio_validate_product_design($id, $design) { return ['valid'=>true]; }
function sanro_studio_draft_image_binding_allowed($design, $refs, $id) { return true; }
function sanro_studio_quote_size_quantities($id, $design, $quantities, $sleeve) {
    return ['valid'=>true,'currency'=>'IDR','total_quantity'=>2,'total_price'=>130000,
        'items'=>['M'=>['quantity'=>2,'unit_price'=>65000,'subtotal'=>130000]]];
}
class GateProduct {
    public $price;
    function __construct($price) { $this->price=$price; }
    function get_price() { return $this->price; }
}
class GateCart {
    public $lines;
    function __construct($price) {
        $this->lines = [['product_id'=>42,'quantity'=>2,
            'sanro_studio_design_id'=>'00000000-0000-4000-8000-000000000000',
            'sanro_studio_size'=>'M','sanro_studio_unit_price'=>65000,
            'sanro_studio_line_subtotal'=>130000,
            'sanro_studio_quote_currency'=>'IDR','data'=>new GateProduct($price)]];
    }
    function get_cart() { return $this->lines; }
}
function gate_check($pass, $message) {
    if (!$pass) { fwrite(STDERR, "FAIL: ".$message."\n"); exit(1); }
}
$id='00000000-0000-4000-8000-000000000000';
$cart=new GateCart('65000.00');
gate_check(sanro_studio_stage_authorize_checkout($id,'owner',42,$cart)['valid']===true, 'valid checkout');
gate_check(sanro_studio_stage_authorize_checkout($id,'wrong',42,$cart)['valid']===false, 'wrong token');
gate_check(sanro_studio_stage_authorize_checkout($id,'owner',43,$cart)['valid']===false, 'wrong product');
$cart->lines[0]['data']->price='1';
gate_check(sanro_studio_stage_authorize_checkout($id,'owner',42,$cart)['valid']===false, 'repriced product rejected');
$cart->lines[0]['data']->price='65000';
$cart->lines[0]['quantity']=3;
gate_check(sanro_studio_stage_authorize_checkout($id,'owner',42,$cart)['valid']===false, 'quantity tampering');
$cart->lines[0]['quantity']=2;
$cart->lines[0]['sanro_studio_design_id']='other';
gate_check(sanro_studio_stage_authorize_checkout($id,'owner',42,$cart)['valid']===false, 'design tampering');
echo "PASS: 6 checkout gate checks\n";
