<?php
/** Sprint 138 WooCommerce-like admin fixture; no WordPress DB or browser. */
define('ABSPATH', __DIR__ . '/');
define('SANRO_STUDIO_ENABLE_DTF_ADMIN_STATUS', true);
$GLOBALS['allow138'] = false;
$GLOBALS['private138'] = true;
$GLOBALS['readCalls138'] = 0;
$GLOBALS['status138'] = null;
$GLOBALS['orders138'] = [];

function current_user_can($capability) {
    return $capability === 'manage_woocommerce' && $GLOBALS['allow138'];
}
function sanro_studio_dtf_artifacts_enabled() { return $GLOBALS['private138']; }
function sanro_studio_dtf_order_item_private_status($order, $item_id) {
    $GLOBALS['readCalls138']++;
    return $GLOBALS['status138'];
}
function esc_html($text) {
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function wc_get_order($order_id) { return $GLOBALS['orders138'][$order_id] ?? false; }
class Item138 {
    public $id = 71;
    public $order_id = 100;
    public $design_id = '123e4567-e89b-42d3-a456-123456789012';
    function get_id() { return $this->id; }
    function get_order_id() { return $this->order_id; }
    function get_meta($name, $single = true) {
        return $name === '_sanro_studio_design_id' ? $this->design_id : '';
    }
}
class Order138 {
    public $id = 100;
    public $items = [];
    function get_id() { return $this->id; }
    function get_items($type = 'line_item') { return $type === 'line_item' ? $this->items : []; }
}
require dirname(__DIR__) . '/includes/dtf-admin-order-status.php';

$count138 = 0;
function check138($ok, $description) {
    global $count138;
    if (!$ok) {
        fwrite(STDERR, 'FAIL: admin private DTF status: ' . $description . "\n");
        exit(1);
    }
    $count138++;
}
$item = new Item138();
$order = new Order138();
$order->items = [71 => $item];
$GLOBALS['orders138'][100] = $order;
$sha = str_repeat('a', 64);
$candidate = [
    'state' => 'candidate', 'private' => true,
    'order_id' => 100, 'item_id' => 71,
    'design_id' => '123e4567-e89b-42d3-a456-123456789012',
    'side' => 'front', 'sha256' => $sha,
    'width_px' => 591, 'height_px' => 591,
    'width_cm' => 5.0, 'height_cm' => 5.0,
    'dpi' => 300, 'quality_approved' => false,
    'font_parity_approved' => false,
    'path' => '/private/secret.png',
    'url' => 'https://unsafe.example/artwork.png',
    'bytes' => 'SECRET_PNG_BYTES',
];
$good = [
    'valid' => true, 'complete' => true,
    'production_ready' => false, 'quality_approved' => false,
    'candidate_count' => 1,
    'sides' => [
        'front' => $candidate,
        'back' => ['state' => 'blank', 'reason' => 'blank_side'],
    ],
];
$GLOBALS['status138'] = $good;

check138(sanro_studio_dtf_admin_status_enabled(), 'explicit flag enables status');
check138(sanro_studio_dtf_admin_item_status($order, 71)['error'] === 'forbidden',
    'non-admin read denied before file access');
check138($GLOBALS['readCalls138'] === 0, 'non-admin cannot probe private disk');
ob_start();
sanro_studio_admin_item_dtf_status(71, $item, null);
$html = ob_get_clean();
check138($html === '', 'unauthorized panel is not rendered');

$GLOBALS['allow138'] = true;
$GLOBALS['private138'] = false;
check138(sanro_studio_dtf_admin_item_status($order, 71)['error'] === 'admin_status_disabled',
    'requires existing private storage flag');
check138($GLOBALS['readCalls138'] === 0, 'disabled private storage not accessed');
$GLOBALS['private138'] = true;

check138(sanro_studio_dtf_admin_item_status($order, 0)['error'] === 'invalid_order_item',
    'zero item cannot access private files');
check138(sanro_studio_dtf_admin_item_status($order, 72)['error'] === 'invalid_order_item',
    'foreign item cannot access this order');
$item->order_id = 101;
check138(sanro_studio_dtf_admin_item_status($order, 71)['error'] === 'invalid_order_item',
    'line order mismatch rejected');
$item->order_id = 100;
$item->design_id = '';
check138(sanro_studio_dtf_admin_item_status($order, 71)['error'] === 'design_not_bound',
    'orphaned candidate denied without design UUID');
$item->design_id = $candidate['design_id'];
check138($GLOBALS['readCalls138'] === 0,
    'invalid order, item and design rejected before private disk read');

$GLOBALS['status138'] = ['valid' => false, 'error' => 'artifact_integrity_failed',
    'sides' => ['front' => $candidate]];
check138(sanro_studio_dtf_admin_item_status($order, 71)['error'] === 'artifact_status_unavailable',
    'tampered private candidate treated as unavailable');
ob_start(); sanro_studio_admin_item_dtf_status(71, $item, null);
$html = ob_get_clean();
check138(strpos($html, 'belum tersedia') !== false
    && strpos($html, 'secret') === false && strpos($html, 'SHA-256') === false,
    'corrupt candidate hides partial metadata and paths');

$GLOBALS['status138'] = $good;
$valid = sanro_studio_dtf_admin_item_status($order, 71);
check138($valid['valid'] === true && $valid['candidate_count'] === 1
    && $valid['production_ready'] === false,
    'one verified front candidate and blank back render as unapproved');
check138($valid['sides']['front']['sha256_prefix'] === str_repeat('a', 12),
    'SHA prefix is truncated to a short diagnostic identifier');
check138($valid['sides']['back']['state'] === 'blank',
    'blank back is represented explicitly');
check138(!isset($valid['sides']['front']['path'])
    && !isset($valid['sides']['front']['bytes'])
    && !isset($valid['sides']['front']['url'])
    && !isset($valid['sides']['front']['design_id']),
    'raw file paths, bytes, URL and design identifiers never leave allowlist');
ob_start(); sanro_studio_admin_item_dtf_status(71, $item, null);
$html = ob_get_clean();
check138(strpos($html, 'Kandidat tersimpan privat') !== false
    && strpos($html, 'Tanpa desain') !== false
    && strpos($html, 'Belum disetujui untuk cetak') !== false,
    'admin panel names status, blank side and non-production warning');
check138(strpos($html, 'SECRET_PNG_BYTES') === false
    && strpos($html, '/private/secret.png') === false
    && strpos($html, 'unsafe.example') === false
    && strpos($html, str_repeat('a', 64)) === false,
    'admin HTML cannot reveal private bytes, path, URLs or full SHA');
check138(strpos($html, '<a ') === false
    && strpos($html, '<form') === false
    && strpos($html, '<button') === false,
    'candidate status has no file delivery links or write actions');

$GLOBALS['status138']['sides']['front']['side'] = 'back';
check138(sanro_studio_dtf_admin_item_status($order, 71)['error'] === 'artifact_status_unavailable',
    'cross-side metadata rejected');
$GLOBALS['status138'] = $good;
$GLOBALS['status138']['sides']['front']['design_id'] = '223e4567-e89b-42d3-a456-123456789012';
check138(sanro_studio_dtf_admin_item_status($order, 71)['error'] === 'artifact_status_unavailable',
    'cross-design artifact metadata rejected');
$GLOBALS['status138'] = $good;
$GLOBALS['status138']['sides']['front']['quality_approved'] = true;
check138(sanro_studio_dtf_admin_item_status($order, 71)['error'] === 'artifact_status_unavailable',
    'untrusted claim of press readiness rejected');
$GLOBALS['status138'] = $good;
$GLOBALS['status138']['sides']['front']['dpi'] = 72;
check138(sanro_studio_dtf_admin_item_status($order, 71)['error'] === 'artifact_status_unavailable',
    'invalid DPI rejected');
$GLOBALS['status138'] = $good;
$GLOBALS['status138']['candidate_count'] = 2;
check138(sanro_studio_dtf_admin_item_status($order, 71)['error'] === 'artifact_status_unavailable',
    'candidate count inconsistency rejected');
$GLOBALS['status138'] = $good;
$back = $candidate;
$back['side'] = 'back';
$GLOBALS['status138']['sides']['back'] = $back;
$GLOBALS['status138']['candidate_count'] = 2;
$both = sanro_studio_dtf_admin_item_status($order, 71);
check138($both['valid'] && $both['candidate_count'] === 2
    && $both['sides']['back']['state'] === 'candidate',
    'both sides can show verified private candidates');
ob_start(); sanro_studio_admin_item_dtf_status(71, $item, null);
$html = ob_get_clean();
check138(substr_count($html, 'Kandidat tersimpan privat') === 2
    && strpos($html, 'Tanpa desain') === false,
    'two-sided admin panel displays two candidates without download links');
$GLOBALS['orders138'] = [];
ob_start(); sanro_studio_admin_item_dtf_status(71, $item, null);
$html = ob_get_clean();
check138(strpos($html, 'belum tersedia') !== false,
    'missing WooCommerce order fails to a generic admin-safe status');

echo 'PASS: ' . $count138 . " SANRO WooCommerce admin DTF status access checks\n";
