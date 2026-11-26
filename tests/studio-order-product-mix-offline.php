<?php
/** Exercise real Studio batch and payment guards using an in-memory Woo checkout. */
require __DIR__ . '/studio-pricing-quote-offline.php';
define('SANRO_STUDIO_ENABLE_WC_PRICING', true);

function get_option($key,$default = false) { return $GLOBALS['options'][$key] ?? $default; }
function maybe_serialize($value) { return is_array($value) ? serialize($value) : $value; }
function wp_verify_nonce($nonce,$action) { return $nonce === 'fixture' && $action === 'sanro_studio_checkout_auth'; }
function wc_add_notice($message,$type) { $GLOBALS['notices'][] = $message; }
function WC() { return $GLOBALS['woo']; }
function sanro_studio_draft_image_binding_allowed($design,$refs,$id) { return true; }
function sanro_studio_read_draft($id,$token) {
    $record = get_option('sanro_studio_draft_'.$id);
    if (!is_array($record) || $record['token_hash'] !== hash('sha256',$token)) return ['valid'=>false];
    return ['valid'=>true,'design_id'=>$id,'design_hash'=>hash('sha256',maybe_serialize($record['design']))] + $record;
}
foreach (['cart-contract','checkout-bridge','checkout-batch-lock','pricing-woocommerce','pricing-integration'] as $file) {
    require dirname(__DIR__) . '/plugins/sanro-studio/includes/' . $file . '.php';
}

class StudioMixedFixtureItem {
    private array $meta = [];
    public function __construct(private int $id, private int $product, private int $quantity, public int $total) {}
    public function get_id() { return $this->id; }
    public function get_product_id() { return $this->product; }
    public function get_quantity() { return $this->quantity; }
    public function get_subtotal() { return (string) $this->total; }
    public function get_total() { return (string) $this->total; }
    public function update_meta_data($key,$value) { $this->meta[$key] = $value; }
    public function get_meta($key,$single) { return $this->meta[$key] ?? ''; }
}
class StudioMixedFixtureOrder {
    public array $items = [];
    private array $meta = [];
    public function get_id() { return 56; }
    public function get_currency() { return 'IDR'; }
    public function get_total() { return (string) array_sum(array_column($this->items,'total')); }
    public function get_items($type) { return $type === 'line_item' ? $this->items : []; }
    public function get_meta($key,$single) { return $this->meta[$key] ?? ''; }
    public function update_meta_data($key,$value) { $this->meta[$key] = $value; }
    public function save() { return 56; }
}
$a = '12345678-1234-4123-8123-123456789012';
$b = '12345678-1234-4123-8123-123456789013';
$tokens = [$a=>str_repeat('a',64),$b=>str_repeat('b',64)];
$GLOBALS['options'] = $GLOBALS['notices'] = [];
$cart = new class {
    public array $items = [];
    public function get_cart() { return $this->items; }
    public function get_totals() { return ['total'=>177750]; }
};
$GLOBALS['woo'] = (object) ['cart'=>$cart];
foreach ([$a=>7,$b=>8] as $id=>$product) {
    $design = ['front'=>side('A3'),'quantities'=>['S'=>1]];
    $GLOBALS['options']['sanro_studio_draft_'.$id] = [
        'status'=>'draft','product_id'=>$product,'design'=>$design,'token_hash'=>hash('sha256',$tokens[$id]),
    ];
    $cart->items[] = ['product_id'=>$product,'quantity'=>1,'sanro_studio_size'=>'S',
        'sanro_studio_pricing_version'=>1,'sanro_studio_design_id'=>$id];
}
$count = $checks;
$_POST = ['sanro_studio_checkout_nonce'=>'fixture','sanro_studio_checkout_token'=>[$a=>$tokens[$a],$b=>$tokens[$a]]];
sanro_studio_pricing_checkout_authorize();
check_quote(!isset($GLOBALS['sanro_studio_pricing_checkout']) && count($GLOBALS['notices']) === 1,'one invalid design token prevents checkout');
$_POST['sanro_studio_checkout_token'] = $tokens;
sanro_studio_pricing_checkout_authorize();
$checkout = $GLOBALS['sanro_studio_pricing_checkout']['designs'] ?? [];
check_quote(array_keys($checkout) === [$a,$b],'each design has independent authorization');

$order = new StudioMixedFixtureOrder();
$order->items = [11=>new StudioMixedFixtureItem(11,7,1,82000),12=>new StudioMixedFixtureItem(12,8,1,85750)];
foreach ($order->items as $number=>$item) sanro_studio_pricing_order_item($item,'',$cart->items[$number-11],$order);
$cart->items[0]['quantity'] = 1;
try {
    sanro_studio_pricing_validate_order($order,[]);
    check_quote(false,'cart subtotal mismatch must throw');
} catch (RuntimeException $error) {
    check_quote(str_contains($error->getMessage(),'shipping'),'merged checkout sums product pricing');
}
$order->items[11]->total = 82000;
$GLOBALS['woo']->cart = new class($cart->items) {
    public function __construct(private array $items) {}
    public function get_cart() { return $this->items; }
    public function get_totals() { return ['total'=>167750]; }
};
sanro_studio_pricing_validate_order($order,[]);
check_quote($order->items[11]->get_meta('_sanro_studio_design_snapshot',true)['sleeve_label'] === 'short'
    && $order->items[12]->get_meta('_sanro_studio_design_snapshot',true)['sleeve_label'] === 'long','same size keeps separate product price and snapshots');
$records = [];
foreach ([$a=>11,$b=>12] as $id=>$item_id) {
    $draft = $GLOBALS['options']['sanro_studio_draft_'.$id];
    $draft['status'] = 'locked';
    $draft['order_id'] = 56;
    $draft['order_item_ids'] = [$item_id];
    $draft['checkout_quote'] = $checkout[$id]['contract']['quote'];
    $GLOBALS['options']['sanro_studio_draft_'.$id] = $records[$id] = $draft;
}
sanro_studio_pricing_finish_multi_order_binding($order,$checkout,$records);
$verified = sanro_studio_saved_order_context($order,false);
check_quote(!empty($verified['valid']) && $verified['item_ids'] === [11,12],'saved order matches both independent quotes');
$order->items[12]->update_meta_data('_sanro_studio_design_id',$a);
check_quote(empty(sanro_studio_saved_order_context($order,false)['valid']),'cross-design substitution blocked');
$order->items[12]->update_meta_data('_sanro_studio_design_id',$b);
$order->items[12]->total = 85000;
check_quote(empty(sanro_studio_saved_order_context($order,false)['valid']),'modified saved line total blocked');
echo 'PASS: ' . ($checks-$count) . " mixed Studio checkout assertions\n";
