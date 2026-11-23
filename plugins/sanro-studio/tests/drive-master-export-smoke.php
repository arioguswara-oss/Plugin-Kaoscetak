<?php
/**
 * Sprint 131 isolated, no-network export QA: real private PNG bytes plus
 * synthetic locked WooCommerce item and Google Drive metadata/HTTP.
 */
$private = sys_get_temp_dir() . '/sanro-drive-master-' . bin2hex(random_bytes(8));
if (!mkdir($private, 0700)) { fwrite(STDERR, "FAIL: temporary storage\n"); exit(1); }
define('ABSPATH', dirname(__DIR__, 3) . '/');
define('SANRO_STUDIO_PRIVATE_MASTER_DIR', $private);
define('SANRO_STUDIO_ENABLE_PRIVATE_MASTERS', true);
define('SANRO_STUDIO_ENABLE_DRIVE_AUTO_PROVISION', true);
define('SANRO_STUDIO_ENABLE_VERIFIED_DRIVE_ATTACHMENT', true);
define('SANRO_STUDIO_DRIVE_PARENT_FOLDER_ID', 'privateParent_0123456789');
define('SANRO_STUDIO_ENABLE_DRIVE_MASTER_EXPORT', true);
$_SERVER['DOCUMENT_ROOT'] = ABSPATH;
require dirname(__DIR__) . '/includes/master-storage.php';
require dirname(__DIR__) . '/includes/order-master-source.php';
require dirname(__DIR__) . '/includes/drive-master-export.php';

$GLOBALS['drive_lease_fixture_store']='locks131';require __DIR__.'/lease-fixture.php';
function maybe_serialize($v){return serialize($v);}
$checks = 0;
function check131($good, $message) {
    global $checks;
    if (!$good) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
}
class WP_Error_131 {}
function is_wp_error($value) { return $value instanceof WP_Error_131; }
function home_url($path = '/') { return 'https://staging.example.test' . $path; }
function apply_filters($name, $default) { return $GLOBALS['token131']; }
function get_option($key, $default = false) {
    return $key === 'sanro_studio_draft_' . $GLOBALS['design131'] ? $GLOBALS['draft131'] : ($GLOBALS['locks131'][$key]??$default);
}
function wp_json_encode($value) { return json_encode($value); }
function add_option($key, $value, $unused = '', $autoload = false) {
    if (isset($GLOBALS['locks131'][$key])) return false;
    $GLOBALS['locks131'][$key] = $value;
    return true;
}
function delete_option($key) { unset($GLOBALS['locks131'][$key]); return true; }
class Item131 {
    public $meta = []; public $saves = 0;
    function get_meta($key, $single = true) { return $this->meta[$key] ?? ''; }
    function update_meta_data($key, $value) { $this->meta[$key] = $value; }
    function save() { $this->saves++; return 1; }
}
class Order131 {
    public $items = []; public $paid = true; public $status = 'processing';
    function get_id() { return 100; }
    function get_items($type = 'line_item') { return $this->items; }
    function is_paid() { return $this->paid; }
    function get_status() { return $this->status; }
    function get_meta($key, $single = true) { return ''; }
}
function sanro_studio_auto_drive_enabled() { return true; }
function sanro_studio_auto_drive_order_eligible($order) {
    return $order->paid && !in_array($order->status, ['cancelled', 'refunded', 'failed'], true);
}
function sanro_studio_order_item_production_manifest($order, $item_id) {
    return ['valid' => true, 'ready_for_drive_integration' => sanro_studio_auto_drive_order_eligible($order)];
}
function sanro_studio_verify_drive_item_folder($order, $item_id, $id) {
    return ['valid' => $id === 'privateFolder_100_71' && !empty($GLOBALS['folderValid131'])];
}
function wp_remote_retrieve_response_code($response) { return $response['code']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
function wp_remote_get($url, $args) {
    $GLOBALS['gets131'][] = ['url' => $url, 'args' => $args];
    if (strpos($url, '/drive/v3/files?') !== false) {
        if (!empty($GLOBALS['failSearch131'])) return new WP_Error_131();
        $matches = array_map(function ($file) { return ['id' => $file['id']]; },
            array_values($GLOBALS['remote131']));
        return ['code' => 200, 'body' => json_encode(['files' => $matches])];
    }
    $prefix = 'https://www.googleapis.com/drive/v3/files/';
    if (strpos($url, $prefix) === 0) {
        $file_id = rawurldecode(explode('?', substr($url, strlen($prefix)))[0]);
        $remote = $GLOBALS['remote131'][$file_id] ?? null;
        if (!$remote) return ['code' => 404, 'body' => '{}'];
        if (!empty($GLOBALS['makePublic131'])) $remote['permissions'] = [['type' => 'anyone']];
        return ['code' => 200, 'body' => json_encode($remote)];
    }
    return ['code' => 500, 'body' => '{}'];
}
function wp_remote_post($url, $args) {
    $GLOBALS['posts131'][] = ['url' => $url, 'args' => $args];
    if (!empty($GLOBALS['failPost131'])) return ['code' => 500, 'body' => '{}'];
    $body = $args['body'];
    if (strpos($body, $GLOBALS['bytes131']) === false) return ['code' => 400, 'body' => '{}'];
    $start = strpos($body, "\r\n\r\n") + 4;
    $end = strpos($body, "\r\n--sanro-", $start);
    $metadata = json_decode(substr($body, $start, $end - $start), true);
    if (!is_array($metadata)) return ['code' => 400, 'body' => '{}'];
    $id = 'sourceFile_100_71_front';
    $GLOBALS['remote131'][$id] = $metadata + [
        'id' => $id, 'trashed' => false,
        'size' => (string) strlen($GLOBALS['bytes131']),
        'md5Checksum' => md5($GLOBALS['bytes131']),
        'permissions' => [['type' => 'user']],
    ];
    return ['code' => 201, 'body' => json_encode(['id' => $id])];
}
function fixture131() {
    $GLOBALS['remote131'] = []; $GLOBALS['locks131'] = [];
    $GLOBALS['gets131'] = []; $GLOBALS['posts131'] = [];
    $GLOBALS['token131'] = 'ya29.synthetic-token-safe';
    $GLOBALS['folderValid131'] = true;
    unset($GLOBALS['failSearch131'], $GLOBALS['failPost131'], $GLOBALS['makePublic131']);
    $item = new Item131();
    $item->meta['_sanro_studio_design_id'] = $GLOBALS['design131'];
    $order = new Order131(); $order->items[71] = $item;
    $GLOBALS['draft131'] = [
        'status' => 'locked', 'order_id' => 100, 'order_item_id' => 71,
        'master_refs' => ['front' => $GLOBALS['file131']],
        'design' => [
            'front' => ['objects' => [['type' => 'image']]],
            'back' => ['objects' => []],
        ],
    ];
    return [$order, $item];
}
try {
    $GLOBALS['design131'] = '123e4567-e89b-42d3-a456-123456789012';
    $GLOBALS['bytes131'] = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAIAAADZF8uwAAAATUlEQVR4nGNkYGBQFTbFjxj3PKkhrKjixA/CikzWlBBW9GHCB8KK1pTkEFaUEfGCsCIVmxTCih4oPCCsaA5LDGFFES9uEFYkciaEoCIAPtNKeUEU/EcAAAAASUVORK5CYII=', true);
    $GLOBALS['file131'] = $GLOBALS['design131'] . '-front-' . hash('sha256', $GLOBALS['bytes131']) . '.png';
    file_put_contents($private . '/' . $GLOBALS['file131'], $GLOBALS['bytes131']);
    list($order, $item) = fixture131();
    check131(sanro_studio_drive_master_export_enabled(), 'explicit export gate');
    $source = sanro_studio_locked_item_master_source($order, 71, 'front');
    check131(!empty($source['valid']) && $source['bytes'] === $GLOBALS['bytes131'],
        'validated private master is read from locked matching order line');
    check131(sanro_studio_locked_item_master_source($order, 71, 'back')['skipped'] === true,
        'no-image side skipped, never claimed print ready');
    check131(sanro_studio_locked_item_master_source($order, 72, 'front')['error']
        === 'invalid_order_item', 'cross-item denied');
    $GLOBALS['draft131']['order_item_id'] = 72;
    check131(sanro_studio_locked_item_master_source($order, 71, 'front')['error']
        === 'draft_order_binding_missing', 'wrong locked item denied');
    $GLOBALS['draft131']['order_item_id'] = 71;
    $GLOBALS['draft131']['order_id'] = 999;
    check131(sanro_studio_locked_item_master_source($order, 71, 'front')['error']
        === 'draft_order_binding_missing', 'cross-order denied');
    $GLOBALS['draft131']['order_id'] = 100;
    $GLOBALS['draft131']['status'] = 'draft';
    check131(sanro_studio_locked_item_master_source($order, 71, 'front')['error']
        === 'draft_order_binding_missing', 'editable draft denied');
    $GLOBALS['draft131']['status'] = 'locked';
    $GLOBALS['draft131']['master_refs']['front'] = '../tampered.png';
    check131(sanro_studio_locked_item_master_source($order, 71, 'front')['error']
        === 'invalid_master_reference', 'path traversal denied');
    $GLOBALS['draft131']['master_refs']['front'] = $GLOBALS['file131'];
    $order->paid = false;
    check131(sanro_studio_locked_item_master_source($order, 71, 'front')['error']
        === 'private_master_unavailable', 'unpaid export denied');
    $order->paid = true;
    $GLOBALS['folderValid131'] = false;
    check131(sanro_studio_drive_upload_locked_item_master($order, 71, 'privateFolder_100_71', 'front')['error']
        === 'folder_unverified', 'bad folder cannot receive master');
    $GLOBALS['folderValid131'] = true;
    $result = sanro_studio_drive_upload_locked_item_master($order, 71, 'privateFolder_100_71', 'front');
    check131(!empty($result['valid']) && !empty($result['created']), 'original master uploaded with verified binding');
    check131(count($GLOBALS['posts131']) === 1 && $item->saves === 1,
        'one remote upload and one local CRUD save');
    check131($item->get_meta('_sanro_studio_drive_master_front_id') === 'sourceFile_100_71_front',
        'order line stores opaque Drive file id only');
    $remote = $GLOBALS['remote131']['sourceFile_100_71_front'];
    check131($remote['appProperties']['sanroSha256'] === hash('sha256', $GLOBALS['bytes131'])
        && $remote['parents'] === ['privateFolder_100_71']
        && $remote['mimeType'] === 'image/png',
        'private source attached with exact folder and content digest');
    check131(strpos($GLOBALS['posts131'][0]['url'], 'https://www.googleapis.com/upload/drive/v3/files?') === 0
        && $GLOBALS['posts131'][0]['args']['redirection'] === 0
        && strpos($GLOBALS['posts131'][0]['args']['headers']['Content-Type'], 'multipart/related; boundary=') === 0,
        'restricted HTTPS multipart upload');
    $again = sanro_studio_drive_upload_locked_item_master($order, 71, 'privateFolder_100_71', 'front');
    check131(!empty($again['valid']) && empty($again['created'])
        && count($GLOBALS['posts131']) === 1 && $item->saves === 1,
        'duplicate hook reuses verified file without save');
    $item->meta['_sanro_studio_drive_master_front_id'] = '';
    $recovered = sanro_studio_drive_upload_locked_item_master($order, 71, 'privateFolder_100_71', 'front');
    check131(!empty($recovered['valid']) && empty($recovered['created'])
        && count($GLOBALS['posts131']) === 1,
        'lost local reference recovered by remote search');
    list($order, $item) = fixture131();
    $GLOBALS['token131'] = "unsafe\r\nInjected";
    check131(sanro_studio_drive_upload_locked_item_master($order, 71, 'privateFolder_100_71', 'front')['error']
        === 'drive_not_connected' && count($GLOBALS['posts131']) === 0, 'invalid OAuth token fails closed');
    list($order, $item) = fixture131();
    $GLOBALS['failSearch131'] = true;
    check131(sanro_studio_drive_upload_locked_item_master($order, 71, 'privateFolder_100_71', 'front')['error']
        === 'drive_master_search_failed' && count($GLOBALS['posts131']) === 0,
        'remote search failure avoids duplicate POST');
    list($order, $item) = fixture131();
    $GLOBALS['failPost131'] = true;
    check131(sanro_studio_drive_upload_locked_item_master($order, 71, 'privateFolder_100_71', 'front')['error']
        === 'drive_master_upload_failed' && $item->saves === 0,
        'upload failure never stores unverified id');
    list($order, $item) = fixture131();
    $GLOBALS['remote131']['orphan_abcdefghijk'] = ['id' => 'orphan_abcdefghijk'];
    $GLOBALS['remote131']['other_abcdefghijk'] = ['id' => 'other_abcdefghijk'];
    check131(sanro_studio_drive_upload_locked_item_master($order, 71, 'privateFolder_100_71', 'front')['error']
        === 'drive_master_search_ambiguous' && count($GLOBALS['posts131']) === 0,
        'multiple remote file candidates rejected');
    list($order, $item) = fixture131();
    $GLOBALS['locks131']['sanro_drive_master_lock_' . md5(home_url('/') . ':100:71:front')] = 'running';
    check131(sanro_studio_drive_upload_locked_item_master($order, 71, 'privateFolder_100_71', 'front')['error']
        === 'drive_master_locked' && count($GLOBALS['posts131']) === 0,
        'concurrent upload denied by atomic lock');
    list($order, $item) = fixture131();
    $first = sanro_studio_drive_upload_locked_item_master($order, 71, 'privateFolder_100_71', 'front');
    $GLOBALS['makePublic131'] = true;
    check131(sanro_studio_drive_upload_locked_item_master($order, 71, 'privateFolder_100_71', 'front')['error']
        === 'existing_master_unverified',
        'previously valid remote file loses verification when public');
    list($order, $item) = fixture131();
    file_put_contents($private . '/' . $GLOBALS['file131'], 'tampered source');
    check131(sanro_studio_drive_upload_locked_item_master($order, 71, 'privateFolder_100_71', 'front')['error']
        === 'master_integrity_failed' && count($GLOBALS['posts131']) === 0,
        'tampered source denied before network');
    echo "PASS: {$checks} SANRO private master Drive export regression checks\n";
} finally {
    foreach (glob($private . '/*') ?: [] as $file) {
        if (is_file($file) && !is_link($file)) @unlink($file);
    }
    @rmdir($private);
}
