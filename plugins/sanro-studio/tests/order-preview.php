<?php
define('ABSPATH', __DIR__);
require dirname(__DIR__) . '/includes/order-preview.php';
require dirname(__DIR__) . '/includes/production-ready-admin.php';
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
$item1 = new SanroOrderPreviewFixture([
    '_sanro_studio_design_id' => $id,
    '_sanro_studio_preview_front_ref' => $ref,
]);
$item2 = new SanroOrderPreviewFixture([
    '_sanro_studio_design_id' => $id,
    '_sanro_studio_preview_back_ref' => str_replace('-front-', '-back-', $ref),
]);
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

$crossDesign = new SanroOrderPreviewFixture([
    '_sanro_studio_design_id' => $otherId,
    '_sanro_studio_preview_front_ref' => $ref,
]);
$crossOrder = new SanroProductionItemOrderFixture([52 => $crossDesign], true);
verify(sanro_studio_order_item_production_manifest($crossOrder, 52)['error'] === 'preview_design_mismatch',
    'paid order cannot use another design preview in production manifest');
$noPreview = new SanroOrderPreviewFixture(['_sanro_studio_design_id' => $id]);
$noPreviewOrder = new SanroProductionItemOrderFixture([53 => $noPreview], true);
$noPreviewManifest = sanro_studio_order_item_production_manifest($noPreviewOrder, 53);
verify($noPreviewManifest['valid'] && $noPreviewManifest['eligible']
    && !$noPreviewManifest['ready_for_drive_integration'],
    'paid order without preview remains ineligible for Drive integration');

class SanroProductionReadyOrderFixture {
    public $meta = [];
    public $saveResult = 1;
    public function get_id() { return 99; }
    public function get_meta($key, $single = true) { return $this->meta[$key] ?? ''; }
    public function update_meta_data($key, $value) { $this->meta[$key] = $value; }
    public function save() { return $this->saveResult; }
    public function is_paid() { return false; }
}
$readyOrder = new SanroProductionReadyOrderFixture();
$GLOBALS['admin_enabled'] = false;
verify(sanro_studio_mark_order_production_ready($readyOrder)['error'] === 'forbidden',
    'non-admin cannot mark production ready');
$GLOBALS['admin_enabled'] = true;
verify(sanro_studio_mark_order_production_ready($readyOrder)['valid']
    && sanro_studio_order_production_file_status($readyOrder)['eligible'],
    'admin production-ready transition unlocks eligibility');
verify(sanro_studio_mark_order_production_ready($readyOrder)['already_ready'],
    'production-ready transition idempotent');
$failedReady = new SanroProductionReadyOrderFixture();
$failedReady->saveResult = false;
verify(sanro_studio_mark_order_production_ready($failedReady)['error'] === 'production_ready_save_failed',
    'production-ready persistence failure reported');

$folderItem = new SanroMutablePreviewItem();
$folderItem->meta['_sanro_studio_design_id'] = $id;
$folderItem->meta['_sanro_studio_preview_front_ref'] = $ref;
$folderOrder = new SanroProductionItemOrderFixture([60 => $folderItem], true);
$folderId = 'driveFolder_0123456789';
$GLOBALS['admin_enabled'] = false;
verify(sanro_studio_attach_item_production_folder_id($folderOrder, 60, $folderId)['error'] === 'forbidden',
    'folder reference attachment denies non-admin');
$GLOBALS['admin_enabled'] = true;
verify(sanro_studio_attach_item_production_folder_id($folderOrder, 60, $folderId)['error'] === 'drive_verification_required',
    'unverified Drive folder attachment disabled by default');
define('SANRO_STUDIO_ENABLE_VERIFIED_DRIVE_ATTACHMENT', true);
verify(sanro_studio_attach_item_production_folder_id($folderOrder, 60, 'https://drive.google.com/folder')['error'] === 'invalid_folder_id',
    'folder URL rejected in favor of opaque ID');
verify(sanro_studio_attach_item_production_folder_id($folderOrder, 60, $folderId)['valid'],
    'eligible paid item accepts opaque folder ID');
verify(sanro_studio_attach_item_production_folder_id($folderOrder, 60, $folderId)['already_attached'],
    'same folder ID is idempotent');
verify(sanro_studio_attach_item_production_folder_id($folderOrder, 60, 'differentFolder_123456')['error'] === 'folder_conflict',
    'conflicting Drive folder reference rejected');
$pendingFolderOrder = new SanroProductionItemOrderFixture([60 => $folderItem], false);
verify(sanro_studio_attach_item_production_folder_id($pendingFolderOrder, 60, $folderId)['error'] === 'production_not_ready',
    'unpaid and not-ready order rejects folder attachment');

/** Sprint 128: real WooCommerce admin production-ready workflow tests. */
class SanroAdminReadyOrderFixture extends SanroProductionReadyOrderFixture {
    public $items = [];
    public $status = 'on-hold';
    public $paid = false;
    public function get_status() { return $this->status; }
    public function get_items($type) { return $type === 'line_item' ? $this->items : []; }
    public function is_paid() { return $this->paid; }
    public function get_edit_order_url() { return 'https://staging.example.test/wp-admin/post.php?post=99&action=edit'; }
}
$adminOrder = new SanroAdminReadyOrderFixture();
$adminItem = new SanroMutablePreviewItem();
$adminItem->meta['_sanro_studio_design_id'] = $id;
$adminItem->meta['_sanro_studio_preview_front_ref'] = $ref;
$adminOrder->items = [71 => $adminItem];
$preflight = sanro_studio_production_ready_admin_preflight($adminOrder);
verify($preflight['valid'] && $preflight['order_id'] === 99,
    'unpaid on-hold order with bound preview can be marked ready');
verify(sanro_studio_production_ready_admin_preflight(null)['error'] === 'invalid_order',
    'admin readiness rejects missing order');
$adminOrder->paid = true;
verify(sanro_studio_production_ready_admin_preflight($adminOrder)['error'] === 'already_paid',
    'already-paid orders do not receive manual approval button');
$adminOrder->paid = false;
foreach (['cancelled', 'refunded', 'failed', 'completed', 'processing'] as $status) {
    $adminOrder->status = $status;
    verify(sanro_studio_production_ready_admin_preflight($adminOrder)['error'] === 'order_status_not_allowed',
        'disallowed order status ' . $status . ' cannot be manually reactivated');
}
$adminOrder->status = 'pending';
verify(sanro_studio_production_ready_admin_preflight($adminOrder)['valid'],
    'pending valid design can be approved without payment');
$adminOrder->status = 'on-hold';
$adminOrder->items = [71 => new SanroMutablePreviewItem()];
verify(sanro_studio_production_ready_admin_preflight($adminOrder)['error'] === 'missing_design_preview',
    'no Studio design or private preview blocks production readiness');
$badItem = new SanroMutablePreviewItem();
$badItem->meta['_sanro_studio_design_id'] = $otherId;
$badItem->meta['_sanro_studio_preview_front_ref'] = $ref;
$adminOrder->items = [71 => $badItem];
verify(sanro_studio_production_ready_admin_preflight($adminOrder)['error'] === 'missing_design_preview',
    'a preview belonging to another design cannot unlock production');
$incompleteItem = new SanroMutablePreviewItem();
$incompleteItem->meta['_sanro_studio_design_id'] = $id;
$adminOrder->items = [71 => $adminItem, 72 => $incompleteItem];
verify(sanro_studio_production_ready_admin_preflight($adminOrder)['error'] === 'missing_design_preview',
    'order with two Studio designs cannot approve if one design lacks a preview');
$ordinaryItem = new SanroMutablePreviewItem();
$adminOrder->items = [71 => $adminItem, 72 => $ordinaryItem];
verify(sanro_studio_production_ready_admin_preflight($adminOrder)['valid'],
    'unrelated ordinary WooCommerce item does not block valid Studio approval');
$adminOrder->items = [71 => $adminItem];

function admin_url($path) { return 'https://staging.example.test/wp-admin/' . $path; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_url($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function wp_nonce_field($action) {
    echo '<input name="_wpnonce" value="fixture" data-nonce-action="' . esc_attr($action) . '">';
}
$GLOBALS['admin_enabled'] = false;
ob_start(); sanro_studio_admin_production_ready_action($adminOrder); $nonAdminHtml = ob_get_clean();
verify($nonAdminHtml === '', 'non-admin never sees production ready form');
$GLOBALS['admin_enabled'] = true;
ob_start(); sanro_studio_admin_production_ready_action($adminOrder); $adminHtml = ob_get_clean();
verify(strpos($adminHtml, 'form="sanro-studio-production-ready-form-99"') !== false
    && strpos($adminHtml, '<form') === false,
    'admin action targets separate form instead of nesting inside order edit');
verify(sanro_studio_production_ready_form_order_id() === 99,
    'ready order is queued for one footer POST form');
verify(strpos($adminHtml, 'Google Drive') !== false
    && strpos($adminHtml, 'Tandai Siap Produksi') !== false,
    'admin clearly distinguishes approval from Drive folder creation');
$adminOrder->status = 'cancelled';
ob_start(); sanro_studio_admin_production_ready_action($adminOrder); $cancelHtml = ob_get_clean();
verify($cancelHtml === '', 'cancelled orders do not show production approval');
$adminOrder->status = 'on-hold';

// Exercise fail-closed endpoint paths without ever reaching redirect/exit.
function wp_die($message, $title = '', $options = []) {
    throw new RuntimeException('HTTP_' . ($options['response'] ?? 500));
}
$GLOBALS['ready_post_order'] = $adminOrder;
function wc_get_order($id) { return $id === 99 ? $GLOBALS['ready_post_order'] : false; }
ob_start(); sanro_studio_admin_production_ready_footer_form(); $footerHtml = ob_get_clean();
verify(strpos($footerHtml, '<form id="sanro-studio-production-ready-form-99" method="post"') !== false,
    'independent nonce-protected POST form is emitted in wp-admin footer');
verify(strpos($footerHtml, 'data-nonce-action="sanro_studio_mark_production_ready_99"') !== false,
    'footer POST form uses order-scoped nonce');
verify(strpos($footerHtml, 'name="order_id" value="99"') !== false
    && strpos($footerHtml, 'sanro_studio_mark_production_ready') !== false,
    'footer binds exact WooCommerce order and authenticated action');

function check_admin_referer($action) {
    if ($action !== 'sanro_studio_mark_production_ready_99'
        || empty($GLOBALS['valid_production_nonce'])) {
        throw new RuntimeException('invalid_nonce');
    }
}
function ready_post_fails_with($status) {
    try { sanro_studio_admin_mark_production_ready(); }
    catch (RuntimeException $error) { return $error->getMessage() === 'HTTP_' . $status; }
    return false;
}
$GLOBALS['admin_enabled'] = false;
$_SERVER['REQUEST_METHOD'] = 'POST'; $_POST = ['order_id' => '99'];
verify(ready_post_fails_with(403), 'admin-post requires WooCommerce capability');
$GLOBALS['admin_enabled'] = true;
$_SERVER['REQUEST_METHOD'] = 'GET';
verify(ready_post_fails_with(405), 'GET cannot trigger production approval');
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['order_id' => '../99'];
verify(ready_post_fails_with(400), 'path-like order ID denied');
$_POST = ['order_id' => '99'];
$GLOBALS['valid_production_nonce'] = false;
try { sanro_studio_admin_mark_production_ready(); $nonceRejected = false; }
catch (RuntimeException $error) { $nonceRejected = $error->getMessage() === 'invalid_nonce'; }
verify($nonceRejected, 'missing or invalid order nonce blocks admin POST');
$GLOBALS['valid_production_nonce'] = true;
$adminOrder->status = 'cancelled';
verify(ready_post_fails_with(409), 'stale submitted form cannot mark a cancelled order');
$adminOrder->status = 'on-hold';

verify(sanro_studio_mark_order_production_ready($adminOrder)['valid']
    && $adminOrder->get_meta('_sanro_studio_production_ready', true) === 'yes',
    'approved admin workflow persists WooCommerce readiness metadata');
verify(sanro_studio_order_production_file_status($adminOrder)['eligible']
    && !sanro_studio_order_production_file_status($adminOrder)['drive_link_available']
    && !$adminOrder->is_paid(),
    'ready status unlocks eligibility without faking payment or Drive link');
verify(sanro_studio_production_ready_admin_preflight($adminOrder)['error'] === 'already_ready',
    'subsequent manual approval remains idempotently hidden');
ob_start(); sanro_studio_admin_production_ready_action($adminOrder); $doneHtml = ob_get_clean();
verify($doneHtml === '', 'already-ready order does not show duplicate action');
