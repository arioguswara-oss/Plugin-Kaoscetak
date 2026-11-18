<?php
/** Fail-closed order lock boundary: wrong/missing design cannot mutate a draft. */
define('ABSPATH', __DIR__);
function current_user_can($cap) { return $cap === 'manage_woocommerce'; }
function sanro_studio_read_draft($id, $token) {
    return ['valid'=>true, 'status'=>'draft', 'product_id'=>42,
        'design'=>['quantities'=>['M'=>2]]];
}
function sanro_studio_quote_size_quantities($product, $design, $quantities, $sleeve) {
    return ['valid'=>true, 'currency'=>'IDR', 'total_quantity'=>2, 'total_price'=>90000];
}
require dirname(__DIR__) . '/includes/draft.php';
final class SanroStudioLockItem {
    private $meta;
    public function __construct($meta) { $this->meta=$meta; }
    public function get_product_id() { return 42; }
    public function get_quantity() { return 2; }
    public function get_subtotal() { return '90000.00'; }
    public function get_meta($key, $single=false) { return $this->meta[$key] ?? null; }
}
final class SanroStudioLockOrder {
    private $item;
    public function __construct($item) { $this->item=$item; }
    public function get_id() { return 10; }
    public function get_currency() { return 'IDR'; }
    public function get_items($type='line_item') { return [7=>$this->item]; }
}
$id='11111111-1111-4111-8111-111111111111';
foreach ([
    'missing_line_design'=>[],
    'foreign_line_design'=>['_sanro_studio_design_id'=>'22222222-2222-4222-8222-222222222222'],
    'malformed_line_design'=>['_sanro_studio_design_id'=>[$id]],
] as $name=>$meta) {
    $result=sanro_studio_lock_draft_for_order_item($id, 'test-secret', new SanroStudioLockOrder(new SanroStudioLockItem($meta)), 7);
    if (($result['valid'] ?? false)!==false || ($result['error'] ?? null)!=='order_design_mismatch') {
        fwrite(STDERR, "FAIL: $name\n"); exit(1);
    }
    echo "PASS: $name\n";
}
