<?php
// Run: php plugins/sanro-studio/tests/draft-lock.php
define('ABSPATH', __DIR__);
define('DAY_IN_SECONDS', 86400);
$GLOBALS['record'] = [
    'product_id' => 12, 'design' => [], 'token_hash' => hash('sha256', str_repeat('a', 64)),
    'expires_at' => time() + 3600, 'status' => 'draft',
];
function get_option($key, $default = false) { return $GLOBALS['record']; }
function maybe_serialize($value) { return serialize($value); }
function wp_cache_delete($key, $group) { return true; }
class SanroDraftLockDB {
    public $options = 'wp_options';
    public $affected = 1;
    public function prepare($sql, ...$args) { return [$sql, $args]; }
    public function query($prepared) {
        if ($this->affected !== 1) return $this->affected;
        $args = $prepared[1];
        if (serialize($GLOBALS['record']) !== $args[2]) return 0;
        $GLOBALS['record'] = unserialize($args[0]);
        return 1;
    }
}
$wpdb = new SanroDraftLockDB();
require dirname(__DIR__) . '/includes/draft.php';
function verify($ok, $label) {
    if (!$ok) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    echo "PASS: $label\n";
}
$id = '123e4567-e89b-42d3-a456-123456789012';
$token = str_repeat('a', 64);
verify(sanro_studio_lock_draft_for_order($id, str_repeat('b', 64), 20)['error'] === 'not_found', 'wrong owner denied');
verify(sanro_studio_lock_draft_for_order($id, $token, 0)['error'] === 'invalid_order', 'invalid order rejected');
$locked = sanro_studio_lock_draft_for_order($id, $token, 20);
verify($locked['valid'] && $GLOBALS['record']['status'] === 'locked' && $GLOBALS['record']['order_id'] === 20, 'atomic lock persists order association');
verify(sanro_studio_lock_draft_for_order($id, $token, 21)['error'] === 'draft_locked', 'second order cannot claim locked draft');
verify(!isset($GLOBALS['record']['owner_token']), 'owner secret not persisted');

$GLOBALS['record']['status'] = 'draft';
$wpdb->affected = 0;
verify(sanro_studio_lock_draft_for_order($id, $token, 22)['error'] === 'draft_conflict', 'compare-and-swap conflict rejects lock');
verify($GLOBALS['record']['status'] === 'draft', 'failed compare-and-swap leaves draft unchanged');

$GLOBALS['can_manage'] = false;
function current_user_can($cap) { return $cap === 'manage_woocommerce' && $GLOBALS['can_manage']; }
class SanroLockItem {
    private $product;
    public function __construct($product) { $this->product = $product; }
    public function get_product_id() { return $this->product; }
}
class SanroLockOrder {
    private $items;
    public function __construct($items) { $this->items = $items; }
    public function get_id() { return 30; }
    public function get_items($type) { return $type === 'line_item' ? $this->items : []; }
}
$order = new SanroLockOrder([7 => new SanroLockItem(12), 8 => new SanroLockItem(13)]);
verify(sanro_studio_lock_draft_for_order_item($id, $token, $order, 7)['error'] === 'forbidden', 'order lock requires admin');
$GLOBALS['can_manage'] = true;
verify(sanro_studio_lock_draft_for_order_item($id, $token, $order, 9)['error'] === 'invalid_order_item', 'unknown order item denied');
verify(sanro_studio_lock_draft_for_order_item($id, $token, $order, 8)['error'] === 'product_mismatch', 'mismatched item product denied');
$wpdb->affected = 1;
$claimed = sanro_studio_lock_draft_for_order_item($id, $token, $order, 7);
verify(!empty($claimed['valid']) && $GLOBALS['record']['order_id'] === 30, 'matching order item locks draft');
verify(sanro_studio_lock_draft_for_order_item($id, $token, $order, 7)['error'] === 'draft_locked', 'repeat order lock denied');
