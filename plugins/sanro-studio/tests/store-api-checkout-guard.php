<?php
/** Store API must not settle a Studio quote through its independent order lifecycle. */
define('ABSPATH', __DIR__);
function sanro_studio_pricing_is_line($line) { return is_array($line) && !empty($line['studio']); }
function sanro_studio_idr_charge($value) { return (int) $value; }
function sanro_studio_pricing_wc_cart() { return $GLOBALS['test_cart']; }
require __DIR__ . '/../includes/checkout-policy.php';

class StoreGuardCart {
    public function __construct(private array $lines) {}
    public function get_cart() { return $this->lines; }
}
class StoreGuardErrors {
    public $errors = [];
    public function add($code, $message) { $this->errors[$code] = $message; }
}
function store_guard_check($condition, $message) {
    if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
    echo "PASS: $message\n";
}

$ordinary = new StoreGuardCart([['product_id' => 12, 'studio' => false]]);
$GLOBALS['test_cart'] = $ordinary;
$errors = new StoreGuardErrors();
sanro_studio_store_api_checkout_policy_check($errors, $ordinary);
store_guard_check(!$errors->errors, 'ordinary WooCommerce Store API checkout is unaffected');
sanro_studio_store_api_reject_unbound_order((object) []);

$studio = new StoreGuardCart([['product_id' => 12, 'studio' => true]]);
$GLOBALS['test_cart'] = $studio;
$errors = new StoreGuardErrors();
sanro_studio_store_api_checkout_policy_check($errors, $studio);
store_guard_check(isset($errors->errors['sanro_studio_store_api_checkout_unavailable']),
    'Studio Checkout Block cart fails closed before order creation');
$blocked = false;
try { sanro_studio_store_api_reject_unbound_order((object) []); }
catch (RuntimeException $error) { $blocked = str_contains($error->getMessage(), 'order locking is not enabled'); }
store_guard_check($blocked, 'Store API order hook blocks a Studio cart before payment');
$bootstrap = file_get_contents(__DIR__ . '/../sanro-studio.php');
store_guard_check(str_contains($bootstrap, "woocommerce_store_api_checkout_order_processed")
    && str_contains($bootstrap, "sanro_studio_store_api_reject_unbound_order"),
    'official Store API pre-payment hook is registered');
