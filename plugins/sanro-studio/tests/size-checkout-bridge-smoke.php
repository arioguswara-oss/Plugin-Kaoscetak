<?php
/** Isolated checkout order-item binding regression, no WordPress writes. */
define('ABSPATH', dirname(__DIR__, 3) . '/');
require_once __DIR__ . '/../includes/checkout-bridge.php';
function check_size($condition, $label) {
    if (!$condition) { fwrite(STDERR, "FAIL: ".$label."\n"); exit(1); }
}
class SanroSizeOrderItem {
    private $meta = [];
    private $product; private $quantity; private $subtotal;
    function __construct($product, $quantity, $subtotal) {
        $this->product = $product; $this->quantity = $quantity; $this->subtotal = $subtotal;
    }
    function get_product_id() { return $this->product; }
    function get_quantity() { return $this->quantity; }
    function get_subtotal() { return $this->subtotal; }
    function get_meta($key, $single = true) { return $this->meta[$key] ?? ''; }
    function update_meta_data($key, $value) { $this->meta[$key] = $value; }
}
$id = '00000000-0000-4000-8000-000000000000';
$candidate = ['product_id'=>42,'quantity'=>2,'sanro_studio_design_id'=>$id,
    'sanro_studio_size'=>'M','sanro_studio_unit_price'=>65000,
    'sanro_studio_line_subtotal'=>130000,'sanro_studio_quote_currency'=>'IDR'];
$item = new SanroSizeOrderItem(42, 2, '130000.00');
check_size(sanro_studio_validate_size_order_item($candidate, $item)['valid'] === true, 'valid decimal subtotal');
check_size(sanro_studio_prepare_size_checkout_order_item($candidate, $item)['valid'] === true, 'metadata bound');
check_size($item->get_meta('_sanro_studio_design_id') === $id, 'design identity persisted in memory');
check_size($item->get_meta('_sanro_studio_size') === 'M', 'size persisted in memory');
check_size(sanro_studio_prepare_size_checkout_order_item($candidate, $item)['valid'] === false, 'duplicate bind rejected');
$wrong = new SanroSizeOrderItem(42, 2, '130001.00');
check_size(sanro_studio_validate_size_order_item($candidate, $wrong)['valid'] === false, 'amount mismatch rejected');
$wrong = new SanroSizeOrderItem(42, 3, '130000.00');
check_size(sanro_studio_validate_size_order_item($candidate, $wrong)['valid'] === false, 'quantity mismatch rejected');
$wrong = $candidate; $wrong['sanro_studio_unit_price'] = 1;
check_size(sanro_studio_prepare_size_checkout_order_item($wrong, new SanroSizeOrderItem(42, 2, 130000))['valid'] === false, 'tampered unit price rejected');
$wrong = $candidate; $wrong['sanro_studio_quote_currency'] = 'USD';
check_size(sanro_studio_prepare_size_checkout_order_item($wrong, new SanroSizeOrderItem(42, 2, 130000))['valid'] === false, 'currency mismatch rejected');
echo "PASS: 9 isolated size-checkout checks\n";
