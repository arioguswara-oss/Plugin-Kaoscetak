<?php
/** Fail-closed order lock boundary: wrong/missing design cannot mutate a draft. */
define('ABSPATH', __DIR__);
function current_user_can($cap) { return $cap === 'manage_woocommerce'; }
function sanro_studio_read_draft($id, $token) {
    return ['valid'=>true, 'status'=>'draft', 'design_id'=>$id, 'product_id'=>42,
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

$result=sanro_studio_bind_order_item_design_identity($id, 'test-secret',
    new SanroStudioLockOrder(new SanroStudioLockItem([])), 7);
if (($result['error'] ?? null)!=='invalid_order_item') {
    fwrite(STDERR, "FAIL: missing_metadata_mutator\n"); exit(1);
}
echo "PASS: missing_metadata_mutator\n";

final class SanroStudioMutableItem {
    private $meta;
    public $writes = 0;
    public function __construct($meta=[]) { $this->meta=$meta; }
    public function get_product_id() { return 42; }
    public function get_quantity() { return 2; }
    public function get_subtotal() { return '90000.00'; }
    public function get_meta($key, $single=false) { return $this->meta[$key] ?? null; }
    public function update_meta_data($key, $value) { $this->meta[$key]=$value; ++$this->writes; }
}
$bind_cases = [
    'valid_binding' => [[], true, null, 1],
    'reject_existing_binding' => [['_sanro_studio_design_id'=>$id], false, 'order_design_already_bound', 0],
    'reject_foreign_binding' => [['_sanro_studio_design_id'=>'other-design'], false, 'order_design_already_bound', 0],
];
foreach ($bind_cases as $name=>[$meta,$expected,$error,$writes]) {
    $item=new SanroStudioMutableItem($meta);
    $result=sanro_studio_bind_order_item_design_identity($id, 'test-secret', new SanroStudioLockOrder($item), 7);
    if (($result['valid'] ?? false)!==$expected || (!$expected && ($result['error'] ?? null)!==$error)
        || $item->writes!==$writes
        || ($expected && ($item->get_meta('_sanro_studio_design_id',true)!==$id
            || ($result['requires_item_save'] ?? false)!==true
            || ($result['checkout_enabled'] ?? true)!==false))) {
        fwrite(STDERR, "FAIL: $name\n"); exit(1);
    }
    echo "PASS: $name\n";
}

final class SanroStudioWrongProductItem {
    public $writes=0;
    public function get_product_id() { return 43; }
    public function get_quantity() { return 2; }
    public function get_subtotal() { return '90000.00'; }
    public function get_meta($key,$single=false) { return ''; }
    public function update_meta_data($key,$value) { ++$this->writes; }
}
$wrong=new SanroStudioWrongProductItem();
$result=sanro_studio_bind_order_item_design_identity($id,'test-secret',new SanroStudioLockOrder($wrong),7);
if (($result['error'] ?? null)!=='product_mismatch' || $wrong->writes!==0) {
    fwrite(STDERR,"FAIL: binder_wrong_product\n"); exit(1);
}
echo "PASS: binder_wrong_product\n";
final class SanroStudioWrongQuantityItem {
    public $writes=0;
    public function get_product_id() { return 42; }
    public function get_quantity() { return 3; }
    public function get_subtotal() { return '90000.00'; }
    public function get_meta($key,$single=false) { return ''; }
    public function update_meta_data($key,$value) { ++$this->writes; }
}
$wrong=new SanroStudioWrongQuantityItem();
$result=sanro_studio_bind_order_item_design_identity($id,'test-secret',new SanroStudioLockOrder($wrong),7);
if (($result['error'] ?? null)!=='order_quantity_mismatch' || $wrong->writes!==0) {
    fwrite(STDERR,"FAIL: binder_wrong_quantity\n"); exit(1);
}
echo "PASS: binder_wrong_quantity\n";
