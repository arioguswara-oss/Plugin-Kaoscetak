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

function current_user_can($cap) { return !empty($GLOBALS['admin_enabled']); }
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

class SanroMutablePreviewItem {
    public $meta = [];
    public $saves = 0;
    public function get_meta($key, $single = true) { return $this->meta[$key] ?? ''; }
    public function get_product_id() { return 12; }
    public function update_meta_data($key, $value) { $this->meta[$key] = $value; }
    public function save() { $this->saves++; return $GLOBALS['preview_save_result'] ?? 1; }
}
function sanro_studio_read_draft($id, $token) {
    return ['valid' => true, 'status' => 'draft', 'product_id' => 12];
}
function sanro_studio_read_owned_draft_preview($id, $token, $side, $ref) {
    return ['valid' => true];
}
$GLOBALS['admin_enabled'] = true;
$mutable = new SanroMutablePreviewItem();
$previewOrder = new SanroItemPreviewOrderFixture([20 => $mutable]);
$attached = sanro_studio_attach_item_preview_refs($previewOrder, 20, $id, 'owner', ['front' => $ref]);
verify(!empty($attached['valid']) && $mutable->get_meta('_sanro_studio_design_id') === $id, 'item binds design identity');
verify(sanro_studio_attach_item_preview_refs($previewOrder, 20, $id, 'owner', ['front' => $ref])['valid'], 'same preview reattach is idempotent');
$otherRef = $id . '-front-' . str_repeat('b', 64) . '.png';
verify(sanro_studio_attach_item_preview_refs($previewOrder, 20, $id, 'owner', ['front' => $otherRef])['error'] === 'preview_conflict', 'conflicting preview refused');
$otherId = '123e4567-e89b-42d3-a456-123456789013';
$otherDesignRef = $otherId . '-back-' . str_repeat('c', 64) . '.png';
verify(sanro_studio_attach_item_preview_refs($previewOrder, 20, $otherId, 'owner', ['back' => $otherDesignRef])['error'] === 'design_conflict', 'cross-design preview refused');
verify($mutable->get_meta('_sanro_studio_preview_front_ref') === $ref
    && $mutable->get_meta('_sanro_studio_preview_back_ref') === '', 'conflicts leave existing references intact');

$GLOBALS['preview_save_result'] = false;
$failedSaveItem = new SanroMutablePreviewItem();
$failedSaveOrder = new SanroItemPreviewOrderFixture([30 => $failedSaveItem]);
verify(sanro_studio_attach_item_preview_refs($failedSaveOrder, 30, $id, 'owner', ['front' => $ref])['error'] === 'preview_save_failed', 'failed WooCommerce item save not reported as success');
unset($GLOBALS['preview_save_result']);

$wrongBinding = new SanroOrderPreviewFixture([
    '_sanro_studio_design_id' => '123e4567-e89b-42d3-a456-123456789013',
    '_sanro_studio_preview_front_ref' => $ref,
]);
$wrongBindingOrder = new SanroItemPreviewOrderFixture([40 => $wrongBinding]);
verify(sanro_studio_order_item_preview_inventory($wrongBindingOrder, 40)['error'] === 'preview_design_mismatch', 'cross-design preview rejected during inventory read');
$invalidBinding = new SanroOrderPreviewFixture([
    '_sanro_studio_design_id' => '../escape',
    '_sanro_studio_preview_front_ref' => $ref,
]);
verify(sanro_studio_order_item_preview_inventory(new SanroItemPreviewOrderFixture([41 => $invalidBinding]), 41)['error'] === 'invalid_design_binding', 'invalid bound design rejected');

class SanroProductionEligibilityFixture {
    private $paid;
    private $ready;
    public function __construct($paid, $ready) { $this->paid = $paid; $this->ready = $ready; }
    public function is_paid() { return $this->paid; }
    public function get_meta($key, $single = true) {
        return $key === '_sanro_studio_production_ready' ? $this->ready : '';
    }
}
verify(sanro_studio_order_production_file_status(null)['error'] === 'invalid_order', 'invalid production order rejected');
$pending = sanro_studio_order_production_file_status(new SanroProductionEligibilityFixture(false, 'no'));
verify($pending['valid'] && !$pending['eligible'] && $pending['drive_url'] === null, 'pending order cannot create production folder');
$paid = sanro_studio_order_production_file_status(new SanroProductionEligibilityFixture(true, 'no'));
verify($paid['eligible'] && $paid['reason'] === 'payment_confirmed' && !$paid['drive_link_available'], 'paid order eligible without invented Drive URL');
$ready = sanro_studio_order_production_file_status(new SanroProductionEligibilityFixture(false, 'yes'));
verify($ready['eligible'] && $ready['reason'] === 'production_ready', 'production-ready order eligible');
$forged = sanro_studio_order_production_file_status(new SanroProductionEligibilityFixture(false, 'true'));
verify(!$forged['eligible'], 'arbitrary truthy production status rejected');

class SanroProductionItemOrderFixture {
    private $items;
    private $paid;
    public function __construct($items, $paid) { $this->items = $items; $this->paid = $paid; }
    public function get_items($type) { return $type === 'line_item' ? $this->items : []; }
    public function is_paid() { return $this->paid; }
    public function get_meta($key, $single = true) { return ''; }
}
$boundPreview = new SanroOrderPreviewFixture([
    '_sanro_studio_design_id' => $id,
    '_sanro_studio_preview_front_ref' => $ref,
]);
$unboundPreview = new SanroOrderPreviewFixture([
    '_sanro_studio_preview_front_ref' => $ref,
]);
$paidOrder = new SanroProductionItemOrderFixture([50 => $boundPreview, 51 => $unboundPreview], true);
$manifest = sanro_studio_order_item_production_manifest($paidOrder, 50);
verify($manifest['valid'] && $manifest['eligible'] && $manifest['ready_for_drive_integration']
    && $manifest['drive_url'] === null && $manifest['design_id'] === $id,
    'paid bound item yields private production manifest without Drive URL');
verify(sanro_studio_order_item_production_manifest($paidOrder, 51)['error'] === 'design_not_bound',
    'unbound item cannot produce manifest');
$pendingOrder = new SanroProductionItemOrderFixture([50 => $boundPreview], false);
$pendingManifest = sanro_studio_order_item_production_manifest($pendingOrder, 50);
verify($pendingManifest['valid'] && !$pendingManifest['eligible']
    && !$pendingManifest['ready_for_drive_integration'], 'pending item not Drive-ready');
verify(sanro_studio_order_item_production_manifest($paidOrder, 99)['error'] === 'invalid_order_item',
    'unknown item cannot produce manifest');
