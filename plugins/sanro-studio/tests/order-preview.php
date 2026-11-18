<?php
define('ABSPATH', __DIR__);
require dirname(__DIR__) . '/includes/order-preview.php';
function verify($ok, $name) {
    if (!$ok) { fwrite(STDERR, "FAIL: $name\n"); exit(1); }
    echo "PASS: $name\n";
}
class SanroOrderPreviewFixture {
    private $values;
    public function __construct($values) { $this->values = $values; }
    public function get_meta($key, $single = true) { return $this->values[$key] ?? ''; }
}
verify(!sanro_studio_order_preview_inventory(null)['valid'], 'reject missing order');
$empty = sanro_studio_order_preview_inventory(new SanroOrderPreviewFixture([]));
verify($empty['valid'] && $empty['front'] === null && $empty['back'] === null, 'empty preview refs');
$id = '123e4567-e89b-42d3-a456-123456789012';
$ref = $id . '-front-' . str_repeat('a', 64) . '.png';
$valid = sanro_studio_order_preview_inventory(new SanroOrderPreviewFixture([
    '_sanro_studio_preview_front_ref' => $ref,
    '_sanro_studio_preview_back_ref' => '../secret',
]));
verify($valid['front'] === $ref && $valid['back'] === null, 'accept valid front, reject traversal');

function current_user_can($cap) { return false; }
verify(sanro_studio_attach_order_preview_refs(new SanroOrderPreviewFixture([]), $id, 'secret', ['front' => $ref])['error'] === 'forbidden', 'order attachment rejects unauthorized caller');

verify(sanro_studio_admin_order_preview_bytes(new SanroOrderPreviewFixture([]), 'front')['error'] === 'forbidden', 'private order preview rejects non-admin');
verify(sanro_studio_admin_order_preview_bytes(null, '../front')['error'] === 'forbidden', 'authorization precedes side inspection');

class SanroItemPreviewOrderFixture {
    private $items;
    public function __construct($items) { $this->items = $items; }
    public function get_items($type) { return $type === 'line_item' ? $this->items : []; }
}
$item1 = new SanroOrderPreviewFixture(['_sanro_studio_preview_front_ref' => $ref]);
$item2 = new SanroOrderPreviewFixture(['_sanro_studio_preview_back_ref' => str_replace('-front-', '-back-', $ref)]);
$multi = new SanroItemPreviewOrderFixture([10 => $item1, 11 => $item2]);
$one = sanro_studio_order_item_preview_inventory($multi, 10);
$two = sanro_studio_order_item_preview_inventory($multi, 11);
verify($one['valid'] && $one['front'] === $ref && $one['back'] === null, 'item 10 isolated');
verify($two['valid'] && $two['front'] === null && $two['back'] !== null, 'item 11 isolated');
verify(!sanro_studio_order_item_preview_inventory($multi, 12)['valid'], 'unknown item rejected');
verify(!sanro_studio_order_item_preview_inventory($multi, 0)['valid'], 'invalid item ID rejected');
