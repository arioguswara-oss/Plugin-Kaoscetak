<?php
/** Offline staging-cart preflight smoke test; never touches WooCommerce. */
define('ABSPATH', dirname(__DIR__, 3) . '/');
require_once __DIR__ . '/../includes/cart-contract.php';
require_once __DIR__ . '/../includes/cart-adapter.php';
function get_woocommerce_currency() { return 'IDR'; }
function wc_get_product($id) { return new SanroMockProduct(); }
class SanroMockProduct {
    function is_type($type) { return $type === 'simple'; }
    function is_purchasable() { return true; }
}
class SanroMockCart {
    public $added = 0;
    public $contents = [];
    function add_to_cart() { $this->added++; return 'key'; }
    function remove_cart_item($key) { return true; }
    function get_cart() { return $this->contents; }
}
function check_adapter($ok, $message) {
    if (!$ok) { fwrite(STDERR, "FAIL: ".$message."\n"); exit(1); }
}
$contract = ['valid'=>true,'design_id'=>'00000000-0000-4000-8000-000000000000',
    'product_id'=>42,'quote'=>['valid'=>true,'currency'=>'IDR',
    'total_quantity'=>2,'total_price'=>130000,'items'=>[
        'M'=>['quantity'=>2,'unit_price'=>65000,'subtotal'=>130000]]]];
$cart = new SanroMockCart();
$disabled = sanro_studio_stage_add_cart_lines($contract, $cart);
check_adapter($disabled['valid'] === false && $cart->added === 0, 'disabled by default');
define('SANRO_STUDIO_ENABLE_STAGING_CART', true);
$ready = sanro_studio_stage_add_cart_lines($contract, $cart);
check_adapter($ready['valid'] === true && $ready['requires_price_hook'] === true, 'preflight returns candidates');
check_adapter($cart->added === 0 && $ready['cart_mutation_enabled'] === false, 'no premature cart mutation');
$cart->contents = ['existing'=>[]];
check_adapter(sanro_studio_stage_add_cart_lines($contract, $cart)['valid'] === false, 'existing cart rejected');
$cart->contents = [];
$bad = $contract; $bad['quote']['items']['M']['unit_price'] = 1;
check_adapter(sanro_studio_stage_add_cart_lines($bad, $cart)['valid'] === false, 'bad quote rejected');
echo "PASS: 5 staging cart adapter checks\n";
