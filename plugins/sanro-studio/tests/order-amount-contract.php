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
