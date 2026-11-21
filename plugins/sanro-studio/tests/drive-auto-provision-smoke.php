<?php
/** Offline Sprint 130 regression. No real WordPress, Google, or WooCommerce. */
define('ABSPATH', __DIR__ . '/');
define('SANRO_STUDIO_ENABLE_DRIVE_AUTO_PROVISION', true);
define('SANRO_STUDIO_ENABLE_VERIFIED_DRIVE_ATTACHMENT', true);
define('SANRO_STUDIO_DRIVE_PARENT_FOLDER_ID', 'privateParent_0123456789');
require dirname(__DIR__) . '/includes/drive-auto-provision.php';

$checks = 0;
function check130($condition, $message) {
    global $checks;
    if (!$condition) { fwrite(STDERR, 'FAIL: ' . $message . "\n"); exit(1); }
    $checks++;
}
class WpError130 {}
function is_wp_error($v) { return $v instanceof WpError130; }
function home_url($path = '/') { return 'https://staging.example.test' . $path; }
function apply_filters($hook, $default) { return $GLOBALS['token130'] ?? $default; }
function wp_json_encode($v) { return json_encode($v); }
function wp_remote_retrieve_response_code($response) { return $response['code']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
function add_option($name, $value, $deprecated = '', $autoload = false) {
    if (isset($GLOBALS['locks130'][$name])) return false;
    $GLOBALS['locks130'][$name] = $value; return true;
}
function delete_option($name) { unset($GLOBALS['locks130'][$name]); return true; }
function as_enqueue_async_action($hook, $args, $group, $unique) {
    $GLOBALS['jobs130'][] = compact('hook', 'args', 'group', 'unique');
}
class Item130 {
    public $meta = []; public $saves = 0; public $fail_save = false;
    function get_meta($key, $single = true) { return $this->meta[$key] ?? ''; }
    function update_meta_data($key, $value) { $this->meta[$key] = $value; }
    function save() { $this->saves++; return !$this->fail_save; }
}
class Order130 {
    public $items = []; public $status = 'processing'; public $paid = true; public $meta = [];
    function get_id() { return 100; }
    function get_items($type = 'line_item') { return $this->items; }
    function get_status() { return $this->status; }
    function is_paid() { return $this->paid; }
    function get_meta($key, $single = true) { return $this->meta[$key] ?? ''; }
}
function wc_get_order($id) { return $id === 100 ? $GLOBALS['order130'] : false; }
function sanro_studio_order_item_production_manifest($order, $item_id) {
    $item = $order->items[$item_id] ?? null;
    $valid = $item && $item->get_meta('_sanro_studio_design_id') !== ''
        && $item->get_meta('_sanro_studio_preview_front_ref') !== '';
    return ['valid' => (bool) $valid,
        'ready_for_drive_integration' => $valid && sanro_studio_auto_drive_order_eligible($order)];
}
function sanro_studio_verify_drive_item_folder($order, $item_id, $id) {
    $file = $GLOBALS['remote130'][$id] ?? null;
    $valid = is_array($file) && ($file['id'] ?? '') === $id
        && ($file['parents'] ?? []) === [SANRO_STUDIO_DRIVE_PARENT_FOLDER_ID]
        && ($file['permissions'] ?? []) === [['type' => 'user']]
        && ($file['appProperties']['sanroOrderId'] ?? '') === (string) $order->get_id()
        && ($file['appProperties']['sanroItemId'] ?? '') === (string) $item_id
        && ($file['appProperties']['sanroSite'] ?? '') === hash('sha256', 'https://staging.example.test');
    return ['valid' => $valid];
}
function wp_remote_get($url, $args) {
    $GLOBALS['get130'][] = ['url' => $url, 'args' => $args];
    if (($GLOBALS['fail_search130'] ?? false) === true) return new WpError130();
    if (strpos($url, '/drive/v3/files?') !== false) {
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $GLOBALS['query130'] = $query;
        $files = array_map(function ($f) { return ['id' => $f['id']]; }, array_values($GLOBALS['remote130']));
        if ($GLOBALS['ambiguous130'] ?? false) $files[] = ['id' => 'anotherFolder_123456789'];
        return ['code' => 200, 'body' => json_encode(['files' => $files])];
    }
    return ['code' => 404, 'body' => '{}'];
}
function wp_remote_post($url, $args) {
    $GLOBALS['post130'][] = ['url' => $url, 'args' => $args];
    if ($GLOBALS['fail_create130'] ?? false) return ['code' => 503, 'body' => '{}'];
    $payload = json_decode($args['body'], true);
    $id = 'createdFolder_100_71';
    $GLOBALS['remote130'][$id] = $payload + ['id' => $id, 'permissions' => [['type' => 'user']]];
    return ['code' => 201, 'body' => json_encode(['id' => $id])];
}
function reset130() {
    $GLOBALS['remote130'] = []; $GLOBALS['locks130'] = []; $GLOBALS['jobs130'] = [];
    $GLOBALS['get130'] = []; $GLOBALS['post130'] = [];
    $GLOBALS['token130'] = 'ya29.synthetic-scope-token';
    unset($GLOBALS['ambiguous130'], $GLOBALS['fail_search130'], $GLOBALS['fail_create130']);
    $order = new Order130(); $item = new Item130();
    $item->meta['_sanro_studio_design_id'] = '123e4567-e89b-42d3-a456-123456789012';
    $item->meta['_sanro_studio_preview_front_ref'] = 'private-preview-reference';
    $order->items[71] = $item; $GLOBALS['order130'] = $order;
    return [$order, $item];
}
list($order, $item) = reset130();
check130(sanro_studio_auto_drive_enabled(), 'explicit integration gates enabled');
$order->paid = false;
check130(!sanro_studio_auto_drive_order_eligible($order), 'unpaid order not eligible');
sanro_studio_schedule_drive_production(100);
check130(count($GLOBALS['jobs130']) === 0, 'unpaid order schedules nothing');
$order->meta['_sanro_studio_production_ready'] = 'yes'; $order->status = 'on-hold';
check130(sanro_studio_auto_drive_order_eligible($order), 'manual-ready on-hold eligible');
$order->status = 'cancelled';
check130(!sanro_studio_auto_drive_order_eligible($order), 'cancelled manual-ready prohibited');
$order->status = 'refunded'; $order->paid = true;
check130(!sanro_studio_auto_drive_order_eligible($order), 'refunded paid prohibited');
$order->status = 'processing';
sanro_studio_schedule_drive_production(100);
check130(count($GLOBALS['jobs130']) === 1 && $GLOBALS['jobs130'][0]['unique'] === true
    && $GLOBALS['jobs130'][0]['hook'] === 'sanro_studio_drive_provision_order',
    'ready order queues unique asynchronous server job');
$result = sanro_studio_run_drive_production(100);
check130(!empty($result['valid']) && $result['created'] === 1 && $result['reused'] === 0,
    'worker creates private folder after authorization');
check130(count($GLOBALS['post130']) === 1 && $item->saves === 1,
    'exactly one Drive POST and one item CRUD save');
$payload = json_decode($GLOBALS['post130'][0]['args']['body'], true);
check130($payload['parents'] === [SANRO_STUDIO_DRIVE_PARENT_FOLDER_ID]
    && $payload['mimeType'] === 'application/vnd.google-apps.folder'
    && $payload['appProperties']['sanroOrderId'] === '100'
    && $payload['appProperties']['sanroItemId'] === '71', 'private parent and immutable binding');
check130($GLOBALS['query130']['pageSize'] === '2'
    && strpos($GLOBALS['query130']['q'], "appProperties has { key='sanroSite'") !== false,
    'pre-creation exact lookup with duplicate detection');
check130(strpos($GLOBALS['post130'][0]['url'], 'https://www.googleapis.com/drive/v3/files?') === 0
    && $GLOBALS['post130'][0]['args']['redirection'] === 0, 'restricted HTTPS Drive API');
check130($item->meta['_sanro_studio_drive_folder_id'] === 'createdFolder_100_71',
    'verified ID saved only to target order line');
$result = sanro_studio_run_drive_production(100);
check130(!empty($result['valid']) && $result['reused'] === 1 && count($GLOBALS['post130']) === 1
    && $item->saves === 1, 'repeated job does not create or write again');
$item->meta['_sanro_studio_drive_folder_id'] = '';
$result = sanro_studio_run_drive_production(100);
check130(!empty($result['valid']) && $result['reused'] === 1
    && count($GLOBALS['post130']) === 1, 'interrupted save recovered by remote lookup');
list($order, $item) = reset130();
$GLOBALS['fail_search130'] = true;
check130(sanro_studio_run_drive_production(100)['error'] === 'drive_search_failed'
    && count($GLOBALS['post130']) === 0, 'provider search error fails closed');
list($order, $item) = reset130();
$GLOBALS['ambiguous130'] = true;
$GLOBALS['remote130']['existingFolder_123456'] = ['id' => 'existingFolder_123456'];
check130(sanro_studio_run_drive_production(100)['error'] === 'drive_search_ambiguous'
    && count($GLOBALS['post130']) === 0, 'multiple matches fail closed');
list($order, $item) = reset130();
$GLOBALS['token130'] = "evil\r\nHeader:";
check130(sanro_studio_run_drive_production(100)['error'] === 'drive_not_connected'
    && count($GLOBALS['get130']) === 0, 'unsafe token rejected before HTTP');
list($order, $item) = reset130();
$GLOBALS['fail_create130'] = true;
check130(sanro_studio_run_drive_production(100)['error'] === 'drive_create_failed'
    && $item->saves === 0, 'Drive creation failure does not persist ID');
list($order, $item) = reset130();
$item->meta['_sanro_studio_preview_front_ref'] = '';
check130(sanro_studio_run_drive_production(100)['error'] === 'invalid_production_manifest'
    && count($GLOBALS['get130']) === 0, 'invalid design never reaches network');
list($order, $item) = reset130();
$GLOBALS['locks130']['sanro_drive_lock_' . md5(home_url('/') . ':100:71')] = 'in progress';
check130(sanro_studio_run_drive_production(100)['error'] === 'drive_provision_locked'
    && count($GLOBALS['get130']) === 0, 'concurrent request denied by atomic lock');
list($order, $item) = reset130();
$GLOBALS['remote130']['foreignFolder_123456'] = ['id' => 'foreignFolder_123456'];
check130(sanro_studio_run_drive_production(100)['error'] === 'drive_created_folder_unverified'
    && $item->saves === 0, 'foreign folder never attached');
list($order, $item) = reset130();
$other = new Item130(); $other->meta['_sanro_studio_design_id'] = 'other';
$order->items[72] = $other;
check130(sanro_studio_run_drive_production(100)['error'] === 'invalid_production_manifest'
    && count($GLOBALS['get130']) === 0, 'batch preflight blocks partial provisioning');
echo 'PASS: ' . $checks . ' SANRO Drive auto-provision regression checks' . "\n";
