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
