<?php
/**
 * Offline order-item binding verification. Never loads WordPress or creates
 * WooCommerce orders. Uses the real checkout bridge and fake unsaved items.
 */
define('ABSPATH', __DIR__ . '/');
require __DIR__ . '/../plugins/sanro-studio/includes/checkout-bridge.php';
final class SanroS168OrderItem {
    public $product_id = 24;
    public $quantity = 1;
    public $subtotal = '130000.00';
    public $total = '130000.00';
    public $meta = [];
    function get_product_id() { return $this->product_id; }
    function get_quantity() { return $this->quantity; }
    function get_subtotal() { return $this->subtotal; }
    function get_total() { return $this->total; }
    function get_meta($key, $single = true) { return $this->meta[$key] ?? ''; }
    function update_meta_data($key, $value) { $this->meta[$key] = $value; }
}
$count = 0;
function s168_check($ok, $label) {
    global $count;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    ++$count;
}
$design = '123e4567-e89b-42d3-a456-123456789012';
$line = [
    'product_id' => 24, 'quantity' => 1, 'sanro_studio_size' => 'M',
    'sanro_studio_design_id' => $design,
    'sanro_studio_unit_price' => 130000,
    'sanro_studio_line_subtotal' => 130000,
    'sanro_studio_quote_currency' => 'IDR'
];
$good = new SanroS168OrderItem();
$result = sanro_studio_prepare_size_checkout_order_item($line, $good);
s168_check(($result['valid'] ?? false) === true, 'real bridge accepts authorized unsaved line');
s168_check(($result['requires_item_save'] ?? false) === true, 'explicit item save remains required');
s168_check($good->get_meta('_sanro_studio_design_id') === $design, 'design ID bound');
s168_check($good->get_meta('_sanro_studio_size') === 'M', 'size bound');
s168_check(count($good->meta) === 2, 'no token, master bytes or private path written');
s168_check(($result['checkout_enabled'] ?? true) === false, 'helper never enables checkout');
s168_check(empty(sanro_studio_prepare_size_checkout_order_item($line, $good)['valid']), 'duplicate binding rejected');
$mutations = [
    'wrong product' => function($item,$candidate) { $item->product_id = 25; },
    'wrong quantity' => function($item,$candidate) { $item->quantity = 2; },
    'undercharged subtotal' => function($item,$candidate) { $item->subtotal = '129999.00'; },
    'discounted total' => function($item,$candidate) { $item->total = '129999.00'; },
    'non IDR currency' => function($item,&$candidate) { $candidate['sanro_studio_quote_currency'] = 'USD'; },
    'invalid size' => function($item,&$candidate) { $candidate['sanro_studio_size'] = 'XXL'; },
    'wrong unit price' => function($item,&$candidate) { $candidate['sanro_studio_unit_price'] = 1; },
    'missing design' => function($item,&$candidate) { $candidate['sanro_studio_design_id'] = 'not-a-uuid'; },
    'existing design metadata' => function($item,$candidate) { $item->meta['_sanro_studio_design_id'] = 'existing'; },
    'existing size metadata' => function($item,$candidate) { $item->meta['_sanro_studio_size'] = 'S'; },
];
foreach ($mutations as $label => $mutate) {
    $item = new SanroS168OrderItem();
    $candidate = $line;
    $mutate($item, $candidate);
    $before = $item->meta;
    $out = sanro_studio_prepare_size_checkout_order_item($candidate, $item);
    s168_check(empty($out['valid']), $label . ' rejected');
    s168_check($item->meta === $before, $label . ' did not mutate metadata');
}
echo "PASS: {$count} SANRO offline unsaved Woo order-item binding assertions (NO ORDER, NO DB)\n";
