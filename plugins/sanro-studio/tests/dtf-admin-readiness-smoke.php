<?php
/** Sprint 146 synthetic Woo admin readiness tests. No WP DB or file IO. */
define('ABSPATH', __DIR__ . '/');
define('SANRO_STUDIO_ENABLE_DTF_ADMIN_READINESS', true);
$GLOBALS['allow146'] = false;
$GLOBALS['status146'] = true;
$GLOBALS['generate146'] = false;
$GLOBALS['optionCalls146'] = 0;
$GLOBALS['orders146'] = [];
$GLOBALS['geometry146'] = true;
$GLOBALS['draft146'] = [
    'status' => 'locked',
    'order_id' => 100, 'product_id' => 55,
    'items' => [71], 'owner_token' => 'SUPERSECRET_TOKEN',
    'private_path' => '/private/SECRET_FILE',
    'design' => [
        'front' => ['objects' => [
            ['type' => 'image', 'x' => 2.0, 'y' => 3.0,
             'width' => 5.0, 'height' => 5.0],
        ]],
        'back' => ['objects' => []],
    ],
];
function current_user_can($cap) {
    return $cap === 'manage_woocommerce' && $GLOBALS['allow146'];
}
function sanro_studio_dtf_admin_status_enabled() { return $GLOBALS['status146']; }
function sanro_studio_dtf_admin_generate_enabled() { return $GLOBALS['generate146']; }
function sanro_studio_auto_drive_order_eligible($order) {
    return is_object($order) && ($order->paid || $order->ready);
}
function get_option($key, $fallback = false) {
    $GLOBALS['optionCalls146']++;
    return $key === 'sanro_studio_draft_123e4567-e89b-42d3-a456-123456789012'
        ? $GLOBALS['draft146'] : $fallback;
}
function sanro_studio_locked_record_has_item($record, $item_id) {
    return in_array($item_id, $record['items'] ?? [], true);
}
function sanro_studio_validate_product_design($product_id, $design) {
    return ['valid' => $GLOBALS['geometry146']
        && $product_id === 55 && is_array($design)];
}
function wc_get_order($id) { return $GLOBALS['orders146'][$id] ?? false; }
function esc_html($text) {
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
class Item146 {
    public $id = 71, $order_id = 100, $product_id = 55;
    public $design_id = '123e4567-e89b-42d3-a456-123456789012';
    function get_id() { return $this->id; }
    function get_order_id() { return $this->order_id; }
    function get_product_id() { return $this->product_id; }
    function get_meta($key, $single=true) {
        return $key === '_sanro_studio_design_id' ? $this->design_id : '';
    }
}
class Order146 {
    public $id = 100, $paid = false, $ready = false, $items = [];
    function get_id() { return $this->id; }
    function get_items($type = 'line_item') {
        return $type === 'line_item' ? $this->items : [];
    }
}
require dirname(__DIR__) . '/includes/dtf-admin-readiness.php';

$checks146 = 0;
function check146($pass, $description) {
    global $checks146;
    if (!$pass) {
        fwrite(STDERR, "FAIL: DTF admin readiness: $description\n");
        exit(1);
    }
    $checks146++;
}
$order = new Order146();
$item = new Item146();
$order->items = [71 => $item];
$GLOBALS['orders146'][100] = $order;

check146(sanro_studio_dtf_admin_readiness_enabled(), 'explicit opted-in read-only diagnostic');
check146(sanro_studio_dtf_admin_readiness_report($order,71)['code'] === 'forbidden',
    'unauthorized user denied');
check146($GLOBALS['optionCalls146'] === 0, 'unauthorized role does not read private draft');
ob_start(); sanro_studio_admin_item_dtf_readiness(71,$item,null);
check146(ob_get_clean() === '', 'unauthorized admin panel not rendered');

$GLOBALS['allow146'] = true;
check146(sanro_studio_dtf_admin_readiness_report(null,71)['code'] === 'invalid_order_item',
    'missing order rejected');
check146(sanro_studio_dtf_admin_readiness_report($order,0)['code'] === 'invalid_order_item',
    'zero item rejected');
check146(sanro_studio_dtf_admin_readiness_report($order,72)['code'] === 'invalid_order_item',
    'other order item rejected');
$item->order_id = 101;
check146(sanro_studio_dtf_admin_readiness_report($order,71)['code'] === 'invalid_order_item',
    'mismatched line order rejected');
$item->order_id = 100;
$item->design_id = 'bad';
check146(sanro_studio_dtf_admin_readiness_report($order,71)['code'] === 'product_design_missing',
    'invalid design ID rejected');
$item->design_id = '123e4567-e89b-42d3-a456-123456789012';
$item->product_id = 0;
check146(sanro_studio_dtf_admin_readiness_report($order,71)['code'] === 'product_design_missing',
    'synthetic productless order rejected');
$item->product_id = 55;
check146(sanro_studio_dtf_admin_readiness_report($order,71)['code'] === 'order_not_paid_or_ready',
    'unpaid pending Woo order not eligible');
check146($GLOBALS['optionCalls146'] === 0, 'unpaid order blocked before draft read');
ob_start(); sanro_studio_admin_item_dtf_readiness(71,$item,null);
$pending = ob_get_clean();
check146(strpos($pending,'belum dibayar') !== false,
    'Woo admin explains pending payment/ready without changing order');

$order->paid = true;
$GLOBALS['draft146']['status'] = 'draft';
check146(sanro_studio_dtf_admin_readiness_report($order,71)['code'] === 'draft_not_locked_or_bound',
    'unlocked draft refused');
$GLOBALS['draft146']['status'] = 'locked';
$GLOBALS['draft146']['order_id'] = 101;
check146(sanro_studio_dtf_admin_readiness_report($order,71)['code'] === 'draft_not_locked_or_bound',
    'cross-order draft refused');
$GLOBALS['draft146']['order_id'] = 100;
$GLOBALS['draft146']['product_id'] = 56;
check146(sanro_studio_dtf_admin_readiness_report($order,71)['code'] === 'draft_not_locked_or_bound',
    'cross-product draft refused');
$GLOBALS['draft146']['product_id'] = 55;
$GLOBALS['draft146']['items'] = [72];
check146(sanro_studio_dtf_admin_readiness_report($order,71)['code'] === 'draft_not_locked_or_bound',
    'cross-item draft refused');
$GLOBALS['draft146']['items'] = [71];
$GLOBALS['geometry146'] = false;
check146(sanro_studio_dtf_admin_readiness_report($order,71)['code'] === 'design_geometry_invalid',
    'invalid placement cannot look candidate eligible');
$GLOBALS['geometry146'] = true;
$GLOBALS['draft146']['design']['front']['objects'] = [];
check146(sanro_studio_dtf_admin_readiness_report($order,71)['code'] === 'no_printable_sides',
    'both blank sides must fail closed');
$GLOBALS['draft146']['design']['front']['objects'] = [
    ['type' => 'image', 'x' => 2, 'y' => 3, 'width' => 5, 'height' => 5],
];
unset($GLOBALS['draft146']['design']['back']);
$report = sanro_studio_dtf_admin_readiness_report($order,71);
check146($report['valid'] === true && $report['code'] === 'generator_disabled'
    && $report['printable_sides'] === 1
    && $report['sides']['front'] === 'artwork'
    && $report['sides']['back'] === 'blank',
    'one-sided locked design valid while generator remains OFF');
check146($report['production_ready'] === false
    && $report['quality_approved'] === false,
    'preflight never asserts artwork is press-ready');
check146(!isset($report['owner_token'])
    && !isset($report['design_id'])
    && !isset($report['private_path']),
    'preflight output allowlisted, no confidential draft fields');

ob_start(); sanro_studio_admin_item_dtf_readiness(71,$item,null);
$html=ob_get_clean();
check146(strpos($html,'generator DTF masih OFF') !== false
    && strpos($html,'Depan: Desain terikat') !== false
    && strpos($html,'Belakang: Tanpa desain') !== false,
    'admin can diagnose safe order binding and blank back');
check146(strpos($html,'SUPERSECRET_TOKEN') === false
    && strpos($html,'/private/SECRET_FILE') === false
    && strpos($html,$item->design_id) === false,
    'admin panel never exposes private credentials, path or UUID');
check146(strpos($html,'<form') === false
    && strpos($html,'<button') === false
    && strpos($html,'<a ') === false
    && strpos($html,'<img') === false,
    'diagnostic panel has no POST, links, or image delivery');

$GLOBALS['generate146'] = true;
$report = sanro_studio_dtf_admin_readiness_report($order,71);
check146($report['valid'] === true
    && $report['code'] === 'binding_prerequisites_pass'
    && $report['production_ready'] === false,
    'generator ON does not imply printable quality approved');
$order->paid=false;
$order->ready=true;
check146(sanro_studio_dtf_admin_readiness_report($order,71)['valid'] === true,
    'explicitly ready Woo order can pass same locked-draft preflight');
$order->ready=false;
$GLOBALS['status146'] = false;
check146(sanro_studio_dtf_admin_readiness_report($order,71)['code'] === 'disabled',
    'read-only panel flag independently gates preflight');
ob_start(); sanro_studio_admin_item_dtf_readiness(71,$item,null);
check146(ob_get_clean() === '', 'disabled admin readiness has no output');
echo 'PASS: ' . $checks146 . " SANRO read-only admin DTF eligibility checks\n";
