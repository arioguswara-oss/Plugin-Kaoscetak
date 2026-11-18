<?php
define('ABSPATH', __DIR__ . '/');
require __DIR__ . '/../plugins/sanro-studio/includes/order-preview.php';
$checks = 0;
function assert_item_preview($ok, $label) {
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL ' . $label);
    $checks++;
}
class StudioItemFixture {
    private $meta;
    public function __construct($meta) { $this->meta = $meta; }
    public function get_meta($key, $single = true) { return $this->meta[$key] ?? ''; }
}
class StudioOrderFixture {
    private $items;
    public function __construct($items) { $this->items = $items; }
    public function get_items($type = 'line_item') { return $this->items; }
}
$id = '12345678-1234-4234-8234-123456789abc';
$other = '87654321-4321-4321-8234-123456789abc';
$front = $id . '-front-' . str_repeat('a', 64) . '.png';
$back = $id . '-back-' . str_repeat('b', 64) . '.webp';
$order = new StudioOrderFixture([
    101 => new StudioItemFixture([
        '_sanro_studio_design_id' => $id,
        '_sanro_studio_preview_front_ref' => $front,
        '_sanro_studio_preview_back_ref' => $back,
    ]),
    102 => new StudioItemFixture([
        '_sanro_studio_design_id' => $other,
        '_sanro_studio_preview_front_ref' => $front,
    ]),
    103 => new StudioItemFixture([
        '_sanro_studio_preview_front_ref' => $front,
    ]),
    104 => new StudioItemFixture([
        '_sanro_studio_design_id' => $id,
    ]),
]);
$valid = sanro_studio_order_item_preview_inventory($order, 101);
assert_item_preview($valid['valid'] && $valid['front'] === $front && $valid['back'] === $back, 'matching item previews');
assert_item_preview(sanro_studio_order_item_preview_inventory($order, 102)['error'] === 'preview_design_mismatch', 'cross-design reference rejected');
assert_item_preview(sanro_studio_order_item_preview_inventory($order, 103)['error'] === 'design_not_bound', 'orphan reference rejected');
$missing = sanro_studio_order_item_preview_inventory($order, 104);
assert_item_preview($missing['valid'] && $missing['front'] === null, 'missing preview not fabricated');
assert_item_preview(sanro_studio_order_item_preview_inventory($order, 999)['error'] === 'invalid_order_item', 'unknown order item rejected');
assert_item_preview(sanro_studio_order_item_preview_inventory($order, 0)['error'] === 'invalid_order_item', 'zero item rejected');
echo "SANRO item preview isolation: {$checks} assertions PASS, 0 FAIL\n";
