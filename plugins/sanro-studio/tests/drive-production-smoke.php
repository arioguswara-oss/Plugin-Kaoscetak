<?php
/**
 * Standalone Drive metadata security test: all HTTP data synthetic, no OAuth,
 * Google account, WooCommerce DB, staging writes, or real Drive requests.
 */
define('ABSPATH', __DIR__ . '/');
define('SANRO_STUDIO_ENABLE_VERIFIED_DRIVE_ATTACHMENT', true);
define('SANRO_STUDIO_DRIVE_PARENT_FOLDER_ID', 'sanroPrivateParent_12345');
require dirname(__DIR__) . '/includes/order-preview.php';
require dirname(__DIR__) . '/includes/drive-production.php';

$checks = 0;
function drive_check($ok, $description) {
    global $checks;
    if (!$ok) {
        fwrite(STDERR, 'FAIL: ' . $description . "\n");
        exit(1);
    }
    $checks++;
}
function current_user_can($cap) {
    return !empty($GLOBALS['drive_admin']) && $cap === 'manage_woocommerce';
}
function home_url($path = '/') { return 'https://staging.example.test' . $path; }
function apply_filters($name, $default) {
    return $name === 'sanro_studio_drive_access_token' ? ($GLOBALS['drive_token'] ?? $default) : $default;
}
class WP_Error {}
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_remote_get($url, $args) {
    $GLOBALS['drive_calls'][] = ['url' => $url, 'args' => $args];
    if (!empty($GLOBALS['drive_transport_fail'])) return new WP_Error();
    return [
        'code' => $GLOBALS['drive_http'] ?? 200,
        'body' => ($GLOBALS['drive_invalid_json'] ?? false)
            ? '<html>bad response</html>'
            : json_encode($GLOBALS['drive_metadata'] ?? []),
    ];
}
function wp_remote_retrieve_response_code($response) { return $response['code']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
function esc_html($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function esc_url($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

class SanroDriveFixtureItem {
    public $meta = [];
    public $saveCount = 0;
    public function get_meta($key, $single = true) { return $this->meta[$key] ?? ''; }
    public function update_meta_data($key, $value) { $this->meta[$key] = $value; }
    public function save() { $this->saveCount++; return 1; }
}
class SanroDriveFixtureOrder {
    public $items = [];
    public $paid = true;
    public function get_id() { return 100; }
    public function get_meta($key, $single = true) { return ''; }
    public function is_paid() { return $this->paid; }
    public function get_items($type = 'line_item') {
        return $type === 'line_item' ? $this->items : [];
    }
}
$GLOBALS['drive_admin'] = true;
$GLOBALS['drive_token'] = 'ya29.synthetic-token-with-safe-characters';
$GLOBALS['drive_calls'] = [];
$id = 'sanroPrivateItemFolder_100_71';
$design = '123e4567-e89b-42d3-a456-123456789012';
$item = new SanroDriveFixtureItem();
$item->meta['_sanro_studio_design_id'] = $design;
$item->meta['_sanro_studio_preview_front_ref'] = $design . '-front-' . str_repeat('a', 64) . '.png';
$order = new SanroDriveFixtureOrder();
$order->items = [71 => $item];
$metadata = [
    'id' => $id,
    'mimeType' => 'application/vnd.google-apps.folder',
    'trashed' => false,
    'parents' => [SANRO_STUDIO_DRIVE_PARENT_FOLDER_ID],
    'permissions' => [['type' => 'user']],
    'appProperties' => [
        'sanroSite' => hash('sha256', 'https://staging.example.test'),
        'sanroOrderId' => '100',
        'sanroItemId' => '71',
    ],
];
$GLOBALS['drive_metadata'] = $metadata;
$good = sanro_studio_verify_drive_item_folder($order, 71, $id);
drive_check(!empty($good['valid']) && $good['folder_id'] === $id,
    'correct private Drive folder, parent and order-line binding accepted');
drive_check(count($GLOBALS['drive_calls']) === 1
    && strpos($GLOBALS['drive_calls'][0]['url'],
        'https://www.googleapis.com/drive/v3/files/') === 0
    && $GLOBALS['drive_calls'][0]['args']['redirection'] === 0
    && $GLOBALS['drive_calls'][0]['args']['timeout'] <= 15
    && $GLOBALS['drive_calls'][0]['args']['limit_response_size'] <= 8192,
    'Google metadata read uses constrained HTTPS request without redirects');
drive_check($GLOBALS['drive_calls'][0]['args']['headers']['Authorization']
    === 'Bearer ' . $GLOBALS['drive_token'],
    'token stays in outbound Authorization header');
drive_check(sanro_studio_verify_drive_item_folder($order, 71, '../private')['error']
    === 'invalid_folder_target', 'path traversal folder ID denied');
drive_check(sanro_studio_verify_drive_item_folder($order, 0, $id)['error']
    === 'invalid_folder_target', 'invalid item ID denied');
$GLOBALS['drive_token'] = '';
$before = count($GLOBALS['drive_calls']);
drive_check(sanro_studio_verify_drive_item_folder($order, 71, $id)['error']
    === 'drive_not_connected' && count($GLOBALS['drive_calls']) === $before,
    'missing OAuth token prevents network request');
$GLOBALS['drive_token'] = "token\r\nX-Injected";
drive_check(sanro_studio_verify_drive_item_folder($order, 71, $id)['error']
    === 'drive_not_connected', 'header-injection token rejected');
$GLOBALS['drive_token'] = 'ya29.synthetic-token-with-safe-characters';
$GLOBALS['drive_http'] = 403;
drive_check(sanro_studio_verify_drive_item_folder($order, 71, $id)['error']
    === 'drive_remote_unavailable', 'provider HTTP failure fails closed');
unset($GLOBALS['drive_http']);
$GLOBALS['drive_transport_fail'] = true;
drive_check(sanro_studio_verify_drive_item_folder($order, 71, $id)['error']
    === 'drive_remote_unavailable', 'transport error fails closed');
unset($GLOBALS['drive_transport_fail']);
$GLOBALS['drive_invalid_json'] = true;
drive_check(sanro_studio_verify_drive_item_folder($order, 71, $id)['error']
    === 'drive_folder_mismatch', 'malformed provider JSON fails closed');
unset($GLOBALS['drive_invalid_json']);
foreach ([
    ['id' => 'otherItemFolder_12345'],
    ['mimeType' => 'text/plain'],
    ['trashed' => true],
    ['parents' => ['otherPrivateParent12345']],
    ['parents' => [SANRO_STUDIO_DRIVE_PARENT_FOLDER_ID, 'unexpectedParent']],
] as $bad) {
    $GLOBALS['drive_metadata'] = array_replace($metadata, $bad);
    drive_check(sanro_studio_verify_drive_item_folder($order, 71, $id)['error']
        === 'drive_folder_mismatch',
        'provider wrong ID/type/trash/parent cannot be attached');
}
foreach ([null, [], [['type' => 'anyone']], [['type' => 'domain']],
    [['type' => 'group']], [['type' => 'user'], ['type' => 'anyone']]] as $permissions) {
    $GLOBALS['drive_metadata'] = $metadata;
    if ($permissions === null) unset($GLOBALS['drive_metadata']['permissions']);
    else $GLOBALS['drive_metadata']['permissions'] = $permissions;
    $expected = $permissions === null || !$permissions
        ? 'drive_permissions_unverified' : 'drive_folder_not_private';
    drive_check(sanro_studio_verify_drive_item_folder($order, 71, $id)['error']
        === $expected, 'unverified/public/domain/group permissions denied');
}
foreach ([
    ['sanroSite' => str_repeat('0', 64)],
    ['sanroOrderId' => '101'],
    ['sanroItemId' => '72'],
] as $bad) {
    $GLOBALS['drive_metadata'] = $metadata;
    $GLOBALS['drive_metadata']['appProperties'] =
        array_replace($metadata['appProperties'], $bad);
    drive_check(sanro_studio_verify_drive_item_folder($order, 71, $id)['error']
        === 'drive_folder_binding_mismatch', 'wrong site/order/item binding refused');
}
$GLOBALS['drive_metadata'] = $metadata;
drive_check(sanro_studio_verified_order_item_drive_url($order, 71) === null,
    'no Drive link emitted before folder attachment');
$attached = sanro_studio_attach_item_production_folder_id($order, 71, $id);
drive_check(!empty($attached['valid'])
    && $item->meta['_sanro_studio_drive_folder_id'] === $id
    && $item->saveCount === 1, 'verified Drive folder persisted on exact item');
drive_check(sanro_studio_attach_item_production_folder_id($order, 71, $id)['already_attached']
    && $item->saveCount === 1, 'repeated verified attachment idempotent');
$url = sanro_studio_verified_order_item_drive_url($order, 71);
drive_check($url === 'https://drive.google.com/drive/folders/' . $id,
    'only verified admin can see official Drive folder address');
$GLOBALS['drive_admin'] = false;
drive_check(sanro_studio_verified_order_item_drive_url($order, 71) === null,
    'non-admin never receives private folder link');
$GLOBALS['drive_admin'] = true;
$order->paid = false;
drive_check(sanro_studio_verified_order_item_drive_url($order, 71) === null,
    'order no longer production-eligible cannot expose Drive link');
$order->paid = true;
$GLOBALS['drive_metadata']['permissions'] = [['type' => 'anyone']];
drive_check(sanro_studio_verified_order_item_drive_url($order, 71) === null,
    'previously-attached folder loses link after permission becomes public');
$GLOBALS['drive_metadata'] = $metadata;
ob_start(); sanro_studio_admin_production_status($order); $html = ob_get_clean();
drive_check(strpos($html, 'Buka folder produksi') !== false
    && strpos($html, 'drive.google.com/drive/folders/') !== false,
    'order admin shows verified private production folder link');
$GLOBALS['drive_metadata']['appProperties']['sanroItemId'] = '999';
ob_start(); sanro_studio_admin_production_status($order); $html = ob_get_clean();
drive_check(strpos($html, 'Buka folder produksi') === false,
    'admin order status stops advertising folder after Drive binding changes');

echo 'PASS: ' . $checks . ' private SANRO Drive folder metadata and admin link checks' . "\n";
