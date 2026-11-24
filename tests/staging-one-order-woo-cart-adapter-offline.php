<?php
/** Offline behavioral checks for the read-only Woo cart adapter. NO WP/DB. */
define('ABSPATH', __DIR__);
require_once __DIR__ . '/staging-one-order-woo-cart-adapter.php';
final class SanroQaPrice {
    private $price;
    public function __construct($price) { $this->price = $price; }
    public function get_price() { return $this->price; }
}
final class SanroQaCart {
    private $lines;
    public function __construct($lines) { $this->lines = $lines; }
    public function get_cart() { return $this->lines; }
}
function qa_assert($label, $actual, $expected) {
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL: $label expected=" . var_export($expected, true)
            . " actual=" . var_export($actual, true) . "\n");
        exit(1);
    }
    echo "PASS: $label\n";
}
$id = '12345678-1234-4234-8234-123456789abc';
$context = [
    'host' => 'saddlebrown-peafowl-616656.hostingersite.com',
    'environment' => 'staging', 'user' => 'u606139982',
    'root' => '/home/u606139982/domains/saddlebrown-peafowl-616656.hostingersite.com/public_html',
    'approval' => 'ONE_SYNTHETIC_CHECKOUT_ORDER_APPROVED',
    'mode' => 'create', 'existing_qa_orders' => 0,
    'currency' => 'IDR', 'server_quote' => 130000, 'order_total' => 130000,
    'quantity' => 1, 'size' => 'M', 'product_id' => 24, 'design_id' => $id,
    'owner_authorized' => true, 'server_quote_verified' => true,
    'all_items_bound' => true, 'hp_os_enabled' => true,
    'disable_mail' => true, 'disable_stock_changes' => true,
    'disable_production_writers' => true,
    'checkout_transport' => 'classic', 'verified_checkout_nonce' => true,
    'shipping_total' => 0, 'tax_total' => 0, 'discount_total' => 0,
    'order_write_cap' => 1,
];
// These are synthetic inputs to exercise the pure policy, NOT real-world
// approval, nonce validation, server quote, or authority evidence.
$line = [
    'data' => new SanroQaPrice('130000'), 'product_id' => 24, 'quantity' => 1,
    'sanro_studio_size' => 'M', 'sanro_studio_design_id' => $id,
    'sanro_studio_unit_price' => 130000,
    'sanro_studio_line_subtotal' => 130000,
    'sanro_studio_quote_currency' => 'IDR',
];
$run = static function ($lines, $ctx) {
    return sanro_studio_qa_one_order_cart_readiness(new SanroQaCart($lines), $ctx);
};
qa_assert('valid synthetic cart passes pure readiness', $run([$line], $context)['ready'], true);
$gatewayIndependent = $context; $gatewayIndependent['payment_gateways'] = 3;
qa_assert('gateway configuration does not gate cart readiness', $run([$line], $gatewayIndependent)['ready'], true);
foreach ([
    'wrong product' => ['product_id', 25],
    'wrong quantity' => ['quantity', 2],
    'wrong size' => ['sanro_studio_size', 'L'],
    'wrong design' => ['sanro_studio_design_id', '12345678-1234-4234-8234-123456789abd'],
    'tampered unit price' => ['sanro_studio_unit_price', 1],
    'tampered subtotal' => ['sanro_studio_line_subtotal', 1],
    'wrong currency' => ['sanro_studio_quote_currency', 'USD'],
] as $label => $mutation) {
    $changed = $line;
    $changed[$mutation[0]] = $mutation[1];
    qa_assert($label . ' rejected', $run([$changed], $context)['ready'], false);
}
foreach (['129999', '130001', '130000.00', 130000.0, '-130000', 'abc'] as $price) {
    $changed = $line;
    $changed['data'] = new SanroQaPrice($price);
    qa_assert('invalid price ' . var_export($price, true), $run([$changed], $context)['ready'], false);
}
qa_assert('empty cart rejected', $run([], $context)['ready'], false);
qa_assert('two lines rejected', $run([$line, $line], $context)['ready'], false);
qa_assert('missing cart rejected', sanro_studio_qa_one_order_cart_readiness(null, $context)['ready'], false);
foreach ([
    'approval' => 'NOT_APPROVED', 'owner_authorized' => false,
    'verified_checkout_nonce' => false,
    'order_write_cap' => 0, 'host' => 'kaoscetak.id',
] as $key => $value) {
    $changed = $context;
    $changed[$key] = $value;
    qa_assert('policy ' . $key . ' rejected', $run([$line], $changed)['ready'], false);
}
echo "STATUS: OFFLINE_CART_ADAPTER_BEHAVIOR_PASS_NO_ORDER_NO_DB\n";
