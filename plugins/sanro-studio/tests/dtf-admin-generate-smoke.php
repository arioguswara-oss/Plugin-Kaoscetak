<?php
/** Sprint 143: WooCommerce-like fixture; no WordPress, files, orders or HTTP. */
define('ABSPATH', __DIR__ . '/');
define('SANRO_STUDIO_ENABLE_DTF_ADMIN_GENERATE', true);
$GLOBALS['allow143'] = false;
$GLOBALS['batch143'] = true;
$GLOBALS['dir143'] = '/isolated/private/only';
$GLOBALS['front143'] = ['valid' => true, 'skipped' => false];
$GLOBALS['back143'] = ['valid' => true, 'skipped' => true];
$GLOBALS['workCalls143'] = 0;
$GLOBALS['bindingCalls143'] = 0;
$GLOBALS['nonceCalls143'] = 0;
$GLOBALS['nonceValid143'] = true;
$GLOBALS['orders143'] = [];

function current_user_can($cap) { return $GLOBALS['allow143'] && $cap === 'manage_woocommerce'; }
function sanro_studio_dtf_order_batch_enabled() { return $GLOBALS['batch143']; }
function sanro_studio_dtf_artifact_directory() { return $GLOBALS['dir143']; }
function sanro_studio_dtf_artifact_binding($order, $id, $side) {
    $GLOBALS['bindingCalls143']++;
    return $side === 'front' ? $GLOBALS['front143'] : $GLOBALS['back143'];
}
function sanro_studio_dtf_prepare_order_item_candidates($order, $id) {
    $GLOBALS['workCalls143']++;
    return $GLOBALS['generated143'];
}
function sanro_studio_dtf_order_item_private_status($order, $id) {
    return $GLOBALS['status143'];
}
function wc_get_order($id) { return $GLOBALS['orders143'][$id] ?? false; }
function esc_html($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function esc_url($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function admin_url($path) { return 'https://staging.example/wp-admin/' . $path; }
function wp_nonce_field($action) { echo '<input type="hidden" name="_wpnonce" value="fixture">'; }
function wp_die($text, $title = '', $args = []) {
    throw new RuntimeException('WP_DIE_' . ($args['response'] ?? 500));
}
function check_admin_referer($action) {
    $GLOBALS['nonceCalls143']++;
    if (!$GLOBALS['nonceValid143']) throw new RuntimeException('BAD_NONCE');
}
function wp_safe_redirect($url, $code = 302) {
    throw new RuntimeException('REDIRECT_' . $code);
}
class Item143 {
    public $id = 71; public $order_id = 52;
    function get_id() { return $this->id; }
    function get_order_id() { return $this->order_id; }
}
class Order143 {
    public $id = 52; public $items = [];
    function get_id() { return $this->id; }
    function get_items($type = 'line_item') {
        return $type === 'line_item' ? $this->items : [];
    }
    function get_edit_order_url() { return 'https://staging.example/wp-admin/admin.php?page=wc-orders&action=edit&id=52'; }
}
require dirname(__DIR__) . '/includes/dtf-admin-generate.php';

$checked143 = 0;
function check143($ok, $desc) {
    global $checked143;
    if (!$ok) { fwrite(STDERR, "FAIL: DTF generation admin: $desc\n"); exit(1); }
    $checked143++;
}
function reject143($call, $response) {
    try { $call(); } catch (RuntimeException $e) {
        return $e->getMessage() === $response;
    }
    return false;
}
$order = new Order143();
$item = new Item143();
$order->items = [71 => $item];
$GLOBALS['orders143'][52] = $order;
$hash = str_repeat('a', 64);
$front = ['state' => 'candidate', 'sha256' => $hash];
$blank = ['state' => 'blank'];
$GLOBALS['generated143'] = [
    'valid' => true, 'complete' => true, 'candidate_count' => 1,
    'quality_approved' => false, 'production_ready' => false,
    'sides' => ['front' => $front, 'back' => $blank],
    'path' => '/PRIVATE/SECRET', 'bytes' => 'PNG_SECRET_BYTES',
];
$GLOBALS['status143'] = $GLOBALS['generated143'];

check143(sanro_studio_dtf_admin_generate_enabled(), 'explicit admin flag and batch required');
check143(sanro_studio_dtf_admin_positive_id('52') === 52, 'valid order id');
check143(sanro_studio_dtf_admin_positive_id('071') === false, 'leading zero refused');
check143(sanro_studio_dtf_admin_positive_id('0') === false, 'zero refused');
check143(sanro_studio_dtf_admin_positive_id(52) === false, 'non-string refused');
check143(sanro_studio_dtf_admin_positive_id('1000000000') === false, 'oversized id refused');
check143(sanro_studio_dtf_admin_positive_id('../52') === false, 'path refused');

check143(sanro_studio_dtf_admin_generate_preflight($order, 71)['error'] === 'forbidden',
    'unauthorized role refused');
check143($GLOBALS['bindingCalls143'] === 0, 'no private reads for unauthorized role');
$GLOBALS['allow143'] = true;
$GLOBALS['batch143'] = false;
check143(sanro_studio_dtf_admin_generate_preflight($order, 71)['error']
    === 'admin_generation_disabled', 'batch disabled refuses generation');
$GLOBALS['batch143'] = true;
$GLOBALS['dir143'] = false;
check143(sanro_studio_dtf_admin_generate_preflight($order, 71)['error']
    === 'private_storage_unavailable', 'unsafe/missing private directory refused');
$GLOBALS['dir143'] = '/isolated/private/only';
check143(sanro_studio_dtf_admin_generate_preflight($order, 99)['error']
    === 'invalid_order_item', 'foreign WooCommerce item refused');
$item->order_id = 53;
check143(sanro_studio_dtf_admin_generate_preflight($order, 71)['error']
    === 'invalid_order_item', 'cross-order item refused');
$item->order_id = 52;
$GLOBALS['front143'] = ['valid' => false, 'error' => 'artifact_order_ineligible'];
check143(sanro_studio_dtf_admin_generate_preflight($order, 71)['error']
    === 'order_design_not_eligible', 'unpaid or unlocked order denied');
$GLOBALS['front143'] = ['valid' => true, 'skipped' => true];
check143(sanro_studio_dtf_admin_generate_preflight($order, 71)['error']
    === 'design_has_no_printable_sides', 'empty design refused');
$GLOBALS['front143'] = ['valid' => true, 'skipped' => false];
$allowed = sanro_studio_dtf_admin_generate_preflight($order, 71);
check143($allowed['valid'] === true
    && $allowed['order_id'] === 52 && $allowed['item_id'] === 71
    && $allowed['printable_sides'] === 1, 'one printable side eligible');

ob_start(); sanro_studio_admin_item_generate_dtf_action(71, $item, null);
$html = ob_get_clean();
check143(strpos($html, 'Buat / Verifikasi Kandidat DTF') !== false
    && strpos($html, 'form="sanro-studio-dtf-generate-52-71"') !== false,
    'one separate admin generation button displayed');
check143(strpos($html, '<form') === false
    && strpos($html, '/PRIVATE/SECRET') === false,
    'button cannot nest forms or leak private paths');
ob_start(); sanro_studio_dtf_admin_generate_footer_forms();
$forms = ob_get_clean();
check143(substr_count($forms, '<form') === 1
    && strpos($forms, 'method="post"') !== false
    && strpos($forms, 'name="order_id" value="52"') !== false
    && strpos($forms, 'name="item_id" value="71"') !== false,
    'separate POST form is exact order and item bound');
check143(strpos($forms, '_wpnonce') !== false
    && strpos($forms, 'admin-post.php') !== false,
    'nonce-protected WP admin-post action');
check143(sanro_studio_dtf_admin_generate_candidates($order, 71)['valid'] === true,
    'complete candidate independently reverified');
check143($GLOBALS['workCalls143'] === 1, 'one explicit worker invocation');
$result = sanro_studio_dtf_admin_generate_candidates($order, 71);
check143(!isset($result['bytes']) && !isset($result['path'])
    && !isset($result['sides']) && $result['production_ready'] === false,
    'worker output allowlisted; never print-ready');
$GLOBALS['status143']['sides']['front']['sha256'] = str_repeat('b',64);
check143(sanro_studio_dtf_admin_generate_candidates($order, 71)['error']
    === 'candidate_readback_failed', 'sha mismatch fails closed');
$GLOBALS['status143'] = $GLOBALS['generated143'];
$GLOBALS['generated143']['valid'] = false;
check143(sanro_studio_dtf_admin_generate_candidates($order, 71)['error']
    === 'candidate_generation_failed', 'partial worker failure denied');
$GLOBALS['generated143']['valid'] = true;
$GLOBALS['generated143']['production_ready'] = true;
check143(sanro_studio_dtf_admin_generate_candidates($order, 71)['error']
    === 'candidate_generation_failed', 'production-ready claim rejected');
$GLOBALS['generated143']['production_ready'] = false;
$GLOBALS['back143'] = ['valid' => true, 'skipped' => false];
$GLOBALS['generated143']['candidate_count'] = 2;
$GLOBALS['status143']['candidate_count'] = 2;
$GLOBALS['generated143']['sides']['back'] = $front;
$GLOBALS['status143']['sides']['back'] = $front;
check143(sanro_studio_dtf_admin_generate_candidates($order, 71)['valid'] === true,
    'two-sided front/back candidate verification');
$GLOBALS['back143'] = ['valid' => true, 'skipped' => true];
$GLOBALS['generated143'] = $GLOBALS['status143'];
$GLOBALS['generated143']['candidate_count'] = 1;
$GLOBALS['status143']['candidate_count'] = 1;
$GLOBALS['generated143']['sides']['back'] = $blank;
$GLOBALS['status143']['sides']['back'] = $blank;
$_SERVER['REQUEST_METHOD'] = 'GET';
check143(reject143(fn()=>sanro_studio_admin_generate_dtf_candidates_post(), 'WP_DIE_405'),
    'GET admin action denied');
$_SERVER['REQUEST_METHOD'] = 'POST';
$GLOBALS['allow143'] = false;
check143(reject143(fn()=>sanro_studio_admin_generate_dtf_candidates_post(), 'WP_DIE_403'),
    'unauthorized POST denied');
$GLOBALS['allow143'] = true;
$_POST = ['order_id' => '52', 'item_id' => '01'];
check143(reject143(fn()=>sanro_studio_admin_generate_dtf_candidates_post(), 'WP_DIE_400'),
    'invalid item ID denied before nonce');
$_POST = ['order_id' => '52', 'item_id' => '71'];
$GLOBALS['nonceValid143'] = false;
check143(reject143(fn()=>sanro_studio_admin_generate_dtf_candidates_post(), 'BAD_NONCE'),
    'bad or absent nonce denied');
$GLOBALS['nonceValid143'] = true;
$GLOBALS['batch143'] = false;
check143(reject143(fn()=>sanro_studio_admin_generate_dtf_candidates_post(), 'WP_DIE_403'),
    'disabled writer gate denied before nonce');
$GLOBALS['batch143'] = true;
$GLOBALS['front143'] = ['valid' => false, 'error' => 'artifact_order_ineligible'];
$writes = $GLOBALS['workCalls143'];
check143(reject143(fn()=>sanro_studio_admin_generate_dtf_candidates_post(), 'WP_DIE_409')
    && $GLOBALS['workCalls143'] === $writes, 'unpaid order refused before any rendering');
$GLOBALS['front143'] = ['valid' => true, 'skipped' => false];
check143(reject143(fn()=>sanro_studio_admin_generate_dtf_candidates_post(), 'REDIRECT_303'),
    'valid nonce/admin POST succeeds through safe 303 order edit redirect');

echo 'PASS: ' . $checked143 . " SANRO nonce-protected DTF admin candidate generation checks\n";
