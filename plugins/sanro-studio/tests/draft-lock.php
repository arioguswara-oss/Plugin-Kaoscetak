<?php
// Run: php plugins/sanro-studio/tests/draft-lock.php
define('ABSPATH', __DIR__);
define('DAY_IN_SECONDS', 86400);
$GLOBALS['record'] = [
    'product_id' => 12, 'design' => ['quantities' => ['M' => 2], 'long_sleeve' => false], 'token_hash' => hash('sha256', str_repeat('a', 64)),
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
$GLOBALS['can_manage'] = true;
require dirname(__DIR__) . '/includes/draft.php';
function verify($ok, $label) {
    if (!$ok) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    echo "PASS: $label\n";
}
$id = '123e4567-e89b-42d3-a456-123456789012';
$token = str_repeat('a', 64);
verify(sanro_studio_lock_draft_for_order($id, str_repeat('b', 64), 20)['error'] === 'not_found', 'wrong owner denied');
verify(sanro_studio_lock_draft_for_order($id, $token, 0)['error'] === 'invalid_order', 'invalid order rejected');
$GLOBALS['can_manage'] = false;
verify(sanro_studio_lock_draft_for_order($id, $token, 20)['error'] === 'forbidden', 'direct draft lock requires admin');
$GLOBALS['can_manage'] = true;
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
    private $quantity;
    public function __construct($product, $quantity = 2) { $this->product = $product; $this->quantity = $quantity; }
    public function get_product_id() { return $this->product; }
    public function get_quantity() { return $this->quantity; }
    public function get_subtotal() { return '150000.00'; }
    public function get_total() { return '135000.00'; }
    public function get_total_tax() { return '14850.00'; }
}
class SanroLockOrder {
    private $items;
    public function __construct($items) { $this->items = $items; }
    public function get_id() { return 30; }
    public function get_currency() { return 'IDR'; }
    public function get_items($type) { return $type === 'line_item' ? $this->items : []; }
}
$order = new SanroLockOrder([7 => new SanroLockItem(12), 8 => new SanroLockItem(13), 10 => new SanroLockItem(12, 3)]);
verify(sanro_studio_lock_draft_for_order_item($id, $token, $order, 7)['error'] === 'forbidden', 'order lock requires admin');
$GLOBALS['can_manage'] = true;
verify(sanro_studio_lock_draft_for_order_item($id, $token, $order, 9)['error'] === 'invalid_order_item', 'unknown order item denied');
verify(sanro_studio_lock_draft_for_order_item($id, $token, $order, 8)['error'] === 'product_mismatch', 'mismatched item product denied');
verify(sanro_studio_lock_draft_for_order_item($id, $token, $order, 10)['error'] === 'order_quantity_mismatch', 'mismatched order quantity denied');
$wpdb->affected = 1;
$claimed = sanro_studio_lock_draft_for_order_item($id, $token, $order, 7);
verify(!empty($claimed['valid']) && $GLOBALS['record']['order_id'] === 30 && $GLOBALS['record']['order_item_id'] === 7, 'matching order item locks draft');
verify(sanro_studio_lock_draft_for_order_item($id, $token, $order, 7)['error'] === 'draft_locked', 'repeat order lock denied');

function sanro_studio_quote_size_quantities($product, $design, $quantities, $sleeve) {
    if ($product !== 12 || $quantities !== ['M' => 2]) {
        return ['valid' => false, 'error' => 'invalid_quantities'];
    }
    return ['valid' => true, 'total_quantity' => 2, 'total_price' => 150000, 'currency' => 'IDR'];
}

$priceQuote = ['valid' => true, 'currency' => 'IDR', 'total_price' => 150000];
class SanroSubtotalFixture {
    private $subtotal;
    public function __construct($subtotal) { $this->subtotal = $subtotal; }
    public function get_subtotal() { return $this->subtotal; }
}
verify(sanro_studio_validate_order_item_quote_amount(new SanroSubtotalFixture('150000.00'), $priceQuote)['valid'], 'undiscounted IDR subtotal matches');
verify(sanro_studio_validate_order_item_quote_amount(new SanroLockItem(12), $priceQuote)['valid'], 'discount and tax do not change pre-discount quote comparison');
verify(sanro_studio_validate_order_item_quote_amount(new SanroSubtotalFixture('149999.00'), $priceQuote)['error'] === 'order_amount_mismatch', 'underpriced order rejected');
verify(sanro_studio_validate_order_item_quote_amount(new SanroSubtotalFixture('150000.01'), $priceQuote)['error'] === 'order_amount_mismatch', 'fractional mismatch rejected');
verify(sanro_studio_validate_order_item_quote_amount(new SanroSubtotalFixture('150000e0'), $priceQuote)['error'] === 'order_amount_unavailable', 'scientific notation rejected');
$foreignQuote = $priceQuote; $foreignQuote['currency'] = 'USD';
verify(sanro_studio_validate_order_item_quote_amount(new SanroSubtotalFixture('150000'), $foreignQuote)['error'] === 'order_amount_unavailable', 'foreign currency quote rejected');

class SanroForeignCurrencyOrder extends SanroLockOrder {
    public function get_currency() { return 'USD'; }
}
$foreignOrder = new SanroForeignCurrencyOrder([7 => new SanroLockItem(12)]);
verify(sanro_studio_lock_draft_for_order_item($id, $token, $foreignOrder, 7)['error'] === 'order_currency_mismatch', 'non-IDR order denied before locking');
