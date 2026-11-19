<?php
/** Isolated checkout gate tests; no WordPress or WooCommerce writes. */
define('ABSPATH', dirname(__DIR__, 3) . '/');
require_once __DIR__ . '/../includes/cart-contract.php';
require_once __DIR__ . '/../includes/checkout-bridge.php';
define('SANRO_STUDIO_ENABLE_STAGING_CART', true);
define('SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT', true);
function get_woocommerce_currency() { return 'IDR'; }
function sanro_studio_read_draft($id, $token) {
    if (!in_array($token, ['owner', str_repeat('a', 64)], true) || $id !== '00000000-0000-4000-8000-000000000000') return ['valid'=>false];
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
class GateOrderItem {
    public $meta = [];
    public $subtotal;
    public $total;
    function __construct($subtotal, $total = null) {
        $this->subtotal=$subtotal;
        $this->total=$total === null ? $subtotal : $total;
    }
    function get_product_id() { return 42; }
    function get_quantity() { return 2; }
    function get_subtotal() { return $this->subtotal; }
    function get_total() { return $this->total; }
    function get_meta($key, $single=true) { return $this->meta[$key] ?? ''; }
    function update_meta_data($key, $value) { $this->meta[$key]=$value; }
}
$contract = sanro_studio_prepare_cart_design_contract($id, 'owner', 42);
$item = new GateOrderItem('130000.00');
$items = ['M'=>$item];
gate_check(sanro_studio_stage_bind_size_order_batch($contract, $items)['valid']===true, 'order metadata batch bound');
gate_check($item->get_meta('_sanro_studio_design_id')===$id, 'design id metadata');
gate_check($item->get_meta('_sanro_studio_size')==='M', 'size metadata');
gate_check(sanro_studio_stage_bind_size_order_batch($contract, $items)['valid']===false, 'duplicate binding refused');
$bad = new GateOrderItem('1.00');
gate_check(sanro_studio_stage_bind_size_order_batch($contract, ['M'=>$bad])['valid']===false, 'wrong order subtotal refused');
gate_check($bad->meta===[], 'invalid order never mutated');
$discounted = new GateOrderItem('130000.00', '1.00');
gate_check(sanro_studio_stage_bind_size_order_batch($contract, ['M'=>$discounted])['valid']===false,
    'discounted WooCommerce total rejected before metadata binding');
gate_check($discounted->meta===[], 'discounted line never mutates order metadata');
$GLOBALS['gate_notices'] = [];
$GLOBALS['gate_filter_token'] = null;
$GLOBALS['gate_wc'] = (object) ['cart'=>new GateCart('65000')];
function WC() { return $GLOBALS['gate_wc']; }
function wc_add_notice($message, $type) { $GLOBALS['gate_notices'][] = [$message, $type]; }
function apply_filters($name, $default, $id, $product) { return $GLOBALS['gate_filter_token']; }
define('SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK', true);
sanro_studio_stage_checkout_process();
gate_check(count($GLOBALS['gate_notices'])===1, 'checkout blocked without transient credentials');
$GLOBALS['gate_notices']=[];
$GLOBALS['gate_wc']->cart->lines=[];
sanro_studio_stage_checkout_process();
gate_check(count($GLOBALS['gate_notices'])===0, 'ordinary empty cart untouched');
$GLOBALS['gate_wc']->cart = new GateCart('65000');
$GLOBALS['gate_wc']->cart->lines[] = ['product_id'=>999];
sanro_studio_stage_checkout_process();
gate_check(count($GLOBALS['gate_notices'])===1, 'mixed cart blocked');
$GLOBALS['gate_notices']=[];
$GLOBALS['gate_wc']->cart = new GateCart('65000');
unset($GLOBALS['gate_wc']->cart->lines[0]['sanro_studio_design_id']);
sanro_studio_stage_checkout_process();
gate_check(count($GLOBALS['gate_notices'])===1, 'partial SANRO metadata cannot bypass checkout gate');
$GLOBALS['gate_wc']->cart = new GateCart('65000');
$GLOBALS['gate_filter_token'] = str_repeat('a', 64);
$GLOBALS['gate_notices'] = [];
sanro_studio_stage_checkout_process();
gate_check(count($GLOBALS['gate_notices'])===0, 'authorized classic checkout accepts fresh valid draft');
gate_check(!empty($GLOBALS['sanro_studio_checkout_verified']['contract']['valid']), 'authorized checkout stores valid request-scoped contract');
$GLOBALS['gate_filter_token'] = null;
$failed_closed = false;
try {
    sanro_studio_stage_checkout_create_order_line_item(
        new GateOrderItem('130000.00'), 'key',
        $GLOBALS['gate_wc']->cart->lines[0], null
    );
} catch (RuntimeException $exception) {
    $failed_closed = true;
}
gate_check(!$failed_closed, 'order binding accepts prior authorized request');
unset($GLOBALS['sanro_studio_checkout_verified']);
$failed_closed = false;
try {
    sanro_studio_stage_checkout_create_order_line_item(
        new GateOrderItem('130000.00'), 'key',
        $GLOBALS['gate_wc']->cart->lines[0], null
    );
} catch (RuntimeException $exception) { $failed_closed = true; }
gate_check($failed_closed, 'order metadata cannot bind without request authorization');
$GLOBALS['sanro_studio_checkout_verified'] = ['contract'=>$contract, 'bound_sizes'=>[]];
$order_line = new GateOrderItem('130000.00');
sanro_studio_stage_checkout_create_order_line_item(
    $order_line, 'key', $GLOBALS['gate_wc']->cart->lines[0], null
);
gate_check($order_line->get_meta('_sanro_studio_size')==='M', 'verified order item bound');
gate_check($order_line->get_meta('_sanro_studio_design_id')===$id, 'verified order design bound');
$duplicate_rejected = false;
try {
    sanro_studio_stage_checkout_create_order_line_item(
        new GateOrderItem('130000.00'), 'duplicate',
        $GLOBALS['gate_wc']->cart->lines[0], null
    );
} catch (RuntimeException $exception) {
    $duplicate_rejected = true;
}
gate_check($duplicate_rejected, 'duplicate size binding rejected');
gate_check(count($GLOBALS['sanro_studio_checkout_verified']['bound_sizes'])===1, 'bound size tracked once');
class GateOrder {
    public $items;
    public $total;
    public $currency;
    function __construct($items, $total='130000.00', $currency='IDR') {
        $this->items=$items;
        $this->total=$total;
        $this->currency=$currency;
    }
    function get_items($type='line_item') { return $this->items; }
    function get_total() { return $this->total; }
    function get_currency() { return $this->currency; }
}
$validOrder = new GateOrder([$order_line]);
sanro_studio_stage_checkout_verify_order_batch($validOrder, []);
gate_check(count($GLOBALS['sanro_studio_checkout_verified']['bound_sizes'])===1, 'complete batch accepted');
$currency_rejected = false;
try {
    sanro_studio_stage_checkout_verify_order_batch(
        new GateOrder([$order_line], '130000.00', 'USD'), []
    );
} catch (RuntimeException $exception) { $currency_rejected = true; }
gate_check($currency_rejected, 'currency drift rejected before order save');
class GateOrderWithoutCurrency {
    function get_items($type='line_item') { return []; }
    function get_total() { return '130000.00'; }
}
$missing_currency_rejected = false;
try {
    sanro_studio_stage_checkout_verify_order_batch(new GateOrderWithoutCurrency(), []);
} catch (RuntimeException $exception) { $missing_currency_rejected = true; }
gate_check($missing_currency_rejected, 'uninspectable order currency fails closed');
sanro_studio_stage_checkout_verify_order_batch(
    new GateOrder([$order_line], '135000.00'), []
);
gate_check(true, 'shipping or tax may raise the order grand total');
$grand_total_rejected = false;
try {
    sanro_studio_stage_checkout_verify_order_batch(
        new GateOrder([$order_line], '129999.99'), []
    );
} catch (RuntimeException $exception) { $grand_total_rejected = true; }
gate_check($grand_total_rejected, 'order-level negative fee underquote rejected before save');
$malformed_grand_total_rejected = false;
try {
    sanro_studio_stage_checkout_verify_order_batch(
        new GateOrder([$order_line], '1e6'), []
    );
} catch (RuntimeException $exception) { $malformed_grand_total_rejected = true; }
gate_check($malformed_grand_total_rejected, 'malformed grand total rejected before save');
$extra_rejected = false;
try {
    sanro_studio_stage_checkout_verify_order_batch(new GateOrder([$order_line, new GateOrderItem('130000.00')]), []);
} catch (RuntimeException $exception) { $extra_rejected = true; }
gate_check($extra_rejected, 'extra order item rejected');
$wrong_design = new GateOrderItem('130000.00');
$wrong_design->update_meta_data('_sanro_studio_size', 'M');
$wrong_design->update_meta_data('_sanro_studio_design_id', 'other');
$wrong_rejected = false;
try {
    sanro_studio_stage_checkout_verify_order_batch(new GateOrder([$wrong_design]), []);
} catch (RuntimeException $exception) { $wrong_rejected = true; }
gate_check($wrong_rejected, 'mismatched design order metadata rejected');
$underpriced = new GateOrderItem('1.00');
$underpriced->update_meta_data('_sanro_studio_size', 'M');
$underpriced->update_meta_data('_sanro_studio_design_id', $id);
$underprice_rejected = false;
try {
    sanro_studio_stage_checkout_verify_order_batch(new GateOrder([$underpriced]), []);
} catch (RuntimeException $exception) { $underprice_rejected = true; }
gate_check($underprice_rejected, 'underpriced order line rejected before save');
$discounted_order = new GateOrderItem('130000.00', '1.00');
$discounted_order->update_meta_data('_sanro_studio_size', 'M');
$discounted_order->update_meta_data('_sanro_studio_design_id', $id);
$discount_rejected = false;
try {
    sanro_studio_stage_checkout_verify_order_batch(new GateOrder([$discounted_order]), []);
} catch (RuntimeException $exception) { $discount_rejected = true; }
gate_check($discount_rejected, 'final pre-save guard rejects coupon discount on order total');
$GLOBALS['sanro_studio_checkout_verified']['bound_sizes'] = [];
$incomplete_rejected = false;
try {
    sanro_studio_stage_checkout_verify_order_batch($validOrder, []);
} catch (RuntimeException $exception) {
    $incomplete_rejected = true;
}
gate_check($incomplete_rejected, 'incomplete batch rejected before save');
unset($GLOBALS['sanro_studio_checkout_verified']);
$missing_guard_rejected = false;
try {
    sanro_studio_stage_checkout_verify_order_batch($validOrder, []);
} catch (RuntimeException $exception) { $missing_guard_rejected = true; }
gate_check($missing_guard_rejected, 'final save rejects Studio cart without authorization');
$GLOBALS['gate_wc']->cart->lines = [];
sanro_studio_stage_checkout_verify_order_batch($validOrder, []);
gate_check(true, 'ordinary cart remains unaffected by final save guard');
function wp_verify_nonce($nonce, $action) {
    return $nonce === 'validnonce' && $action === 'sanro_studio_checkout_auth';
}
$token64 = str_repeat('a', 64);
$_POST['sanro_studio_checkout_nonce'] = 'validnonce';
$_POST['sanro_studio_checkout_token'] = $token64;
gate_check(sanro_studio_stage_checkout_post_token(null, $id, 42) === $token64, 'nonce-verified checkout token');
$_POST['sanro_studio_checkout_nonce'] = 'invalidnonce';
gate_check(sanro_studio_stage_checkout_post_token(null, $id, 42) === null, 'invalid nonce rejected');
$_POST['sanro_studio_checkout_nonce'] = 'validnonce';
$_POST['sanro_studio_checkout_token'] = 'not-a-token';
gate_check(sanro_studio_stage_checkout_post_token(null, $id, 42) === null, 'invalid token rejected');
unset($_POST['sanro_studio_checkout_token'], $_POST['sanro_studio_checkout_nonce']);
gate_check(sanro_studio_stage_checkout_post_token(null, $id, 42) === null, 'missing checkout credentials rejected');
echo "PASS: 43 checkout gate checks\n";
