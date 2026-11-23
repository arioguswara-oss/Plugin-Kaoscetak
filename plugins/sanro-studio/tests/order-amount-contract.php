<?php
/** Isolated order subtotal verification; no WooCommerce or database writes. */
define('ABSPATH', __DIR__);
require dirname(__DIR__) . '/includes/draft.php';

final class SanroStudioTestOrderItem {
    private $subtotal;
    public function __construct($subtotal) { $this->subtotal = $subtotal; }
    public function get_subtotal() { return $this->subtotal; }
}
$quote = ['valid' => true, 'currency' => 'IDR', 'total_price' => 90000];
$cases = [
    'valid_integer' => [90000, true, null],
    'valid_decimal' => ['90000.00', true, null],
    'wrong_subtotal' => ['89999.00', false, 'order_amount_mismatch'],
    'fractional_idr' => ['90000.01', false, 'order_amount_mismatch'],
    'negative_subtotal' => [-1, false, 'order_amount_unavailable'],
    'malformed_subtotal' => ['90,000', false, 'order_amount_unavailable'],
    'string_scientific' => ['9e4', false, 'order_amount_unavailable'],
];
foreach ($cases as $name => [$subtotal, $expected, $error]) {
    $result = sanro_studio_validate_order_item_quote_amount(new SanroStudioTestOrderItem($subtotal), $quote);
    if (($result['valid'] ?? false) !== $expected
        || (!$expected && ($result['error'] ?? null) !== $error)) {
        fwrite(STDERR, "FAIL: $name\n");
        exit(1);
    }
    echo "PASS: $name\n";
}
$invalid_quote = array_merge($quote, ['currency' => 'USD']);
$result = sanro_studio_validate_order_item_quote_amount(new SanroStudioTestOrderItem(90000), $invalid_quote);
if (($result['error'] ?? null) !== 'order_amount_unavailable') {
    fwrite(STDERR, "FAIL: invalid_quote_currency\n");
    exit(1);
}
echo "PASS: invalid_quote_currency\n";

final class SanroStudioTestMetaItem {
    private $meta;
    public function __construct($meta) { $this->meta = $meta; }
    public function get_meta($key, $single = false) { return $this->meta[$key] ?? null; }
}
$design_id = '11111111-1111-4111-8111-111111111111';
$identity_cases = [
    'bound_design' => [['_sanro_studio_design_id' => $design_id], true],
    'different_design' => [['_sanro_studio_design_id' => '22222222-2222-4222-8222-222222222222'], false],
    'missing_design' => [[], false],
    'array_design' => [['_sanro_studio_design_id' => [$design_id]], false],
];
foreach ($identity_cases as $name => [$meta, $expected]) {
    $result = sanro_studio_validate_order_item_design_identity(new SanroStudioTestMetaItem($meta), $design_id);
    if (($result['valid'] ?? false) !== $expected
        || (!$expected && ($result['error'] ?? null) !== 'order_design_mismatch')) {
        fwrite(STDERR, "FAIL: $name\n");
        exit(1);
    }
    echo "PASS: $name\n";
}
