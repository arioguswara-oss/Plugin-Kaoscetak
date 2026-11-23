<?php
/**
 * Standalone Sprint 132 multi-size checkout lock regression. No WP database,
 * checkout/payment, server storage, OAuth or external requests.
 */
define('ABSPATH', dirname(__DIR__, 3) . '/');
define('SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK', true);
define('SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT', true);
define('SANRO_STUDIO_ENABLE_STAGING_DRAFT_AUTO_LOCK', true);
require dirname(__DIR__) . '/includes/checkout-batch-lock.php';
require dirname(__DIR__) . '/includes/order-master-source.php';
$checks = 0;
function check132($ok, $label) {
    global $checks;
    if (!$ok) { fwrite(STDERR, 'FAIL: ' . $label . "\n"); exit(1); }
    $checks++;
}
function maybe_serialize($v) { return serialize($v); }
function get_option($key, $default = false) {
    return $key === 'sanro_studio_draft_' . $GLOBALS['design132']
        ? $GLOBALS['draft132'] : $default;
}
function wp_cache_delete($key, $group) { $GLOBALS['cache132'][] = [$key,$group]; }
function sanro_studio_draft_image_binding_allowed($design, $masters, $id) {
    return empty($GLOBALS['denyImages132']);
}
function sanro_studio_prepare_size_cart_lines($contract) {
    $lines = [];
    foreach ($contract['quote']['items'] as $size => $row) {
        $lines[] = ['sanro_studio_size' => $size, 'product_id' => $contract['product_id'],
            'quantity' => $row['quantity'], 'sanro_studio_design_id' => $contract['design_id'],
            'sanro_studio_unit_price' => $row['unit_price'],
            'sanro_studio_line_subtotal' => $row['subtotal']];
    }
    return ['valid' => true, 'lines' => $lines];
}
function sanro_studio_validate_size_order_item($candidate, $item) {
    return ['valid' => $candidate['product_id'] === $item->get_product_id()
        && $candidate['quantity'] === $item->get_quantity()
        && (float) $candidate['sanro_studio_line_subtotal'] === (float) $item->get_subtotal()
        && (float) $candidate['sanro_studio_line_subtotal'] === (float) $item->get_total()];
}
function sanro_studio_validate_order_grand_total($contract, $amount) {
    return ['valid' => (float) $amount >= (float) $contract['quote']['total_price']];
}
class Db132 {
    public $options = 'wp_options';
    public $fail = false; public $race = false; public $saves = 0;
    function prepare($sql, ...$args) { return ['sql' => $sql, 'args' => $args]; }
    function query($statement) {
        if ($this->fail) return false;
        if ($this->race || !is_array($statement) || count($statement['args']) !== 3
            || $statement['args'][1] !== 'sanro_studio_draft_' . $GLOBALS['design132']
            || $statement['args'][2] !== serialize($GLOBALS['draft132'])
            || strpos($statement['sql'], 'BINARY option_value = BINARY') === false) return 0;
        $GLOBALS['draft132'] = unserialize($statement['args'][0]);
        $this->saves++;
        return 1;
    }
}
class Item132 {
    public $id; public $size; public $design; public $qty; public $product = 42;
    public $subtotal; public $unit_price; public $currency='IDR';
    function __construct($id,$size,$quantity,$subtotal,$design) {
        $this->id=$id;$this->size=$size;$this->qty=$quantity;
        $this->subtotal=$subtotal;$this->unit_price=(int) ($subtotal / $quantity);$this->design=$design;
    }
    function get_id() { return $this->id; }
    function get_product_id() { return $this->product; }
    function get_quantity() { return $this->qty; }
    function get_subtotal() { return $this->subtotal; }
    function get_total() { return $this->subtotal; }
    function get_meta($key,$single=true) {
        if ($key === '_sanro_studio_size') return $this->size;
        if ($key === '_sanro_studio_design_id') return $this->design;
        if ($key === '_sanro_studio_unit_price') return $this->unit_price;
        if ($key === '_sanro_studio_quote_currency') return $this->currency;
        return '';
    }
}
class Order132 {
    public $id=100; public $currency='IDR'; public $total=190000;
    public $items=[];
    function get_id() { return $this->id; }
    function get_currency() { return $this->currency; }
    function get_total() { return $this->total; }
    function get_items($type='line_item') { return $type==='line_item'?$this->items:[]; }
}
function reset132() {
    $GLOBALS['design132'] = '123e4567-e89b-42d3-a456-123456789012';
    $GLOBALS['token132'] = str_repeat('a',64);
    $GLOBALS['contract132'] = ['valid'=>true,
        'design_id'=>$GLOBALS['design132'],'product_id'=>42,
        'quote'=>['total_price'=>190000,'items'=>[
            'M'=>['quantity'=>2,'unit_price'=>50000,'subtotal'=>100000],
            'L'=>['quantity'=>3,'unit_price'=>30000,'subtotal'=>90000]
        ]]];
    $GLOBALS['draft132'] = [
        'status'=>'draft','design'=>['front'=>['objects'=>[]]],
        'product_id'=>42,
        'master_refs'=>[],
        'expires_at'=>time()+3600,
        'token_hash'=>hash('sha256',$GLOBALS['token132']),
    ];
    $GLOBALS['hash132'] = hash('sha256',serialize($GLOBALS['draft132']['design']));
    $GLOBALS['cache132'] = [];
    $GLOBALS['denyImages132'] = false;
    $GLOBALS['wpdb'] = new Db132();
    $order = new Order132();
    $order->items = [
        71=>new Item132(71,'M',2,100000,$GLOBALS['design132']),
        72=>new Item132(72,'L',3,90000,$GLOBALS['design132'])
    ];
    return $order;
}
$order = reset132();
check132(sanro_studio_stage_checkout_batch_lock_enabled(), 'staging-only opt-in enabled');
$pre = sanro_studio_checkout_batch_lock_preflight($order,$GLOBALS['contract132']);
check132(!empty($pre['valid']) && $pre['item_ids']===[71,72], 'complete persisted size batch accepted');
$order->items[72]->unit_price=1;
check132(sanro_studio_checkout_batch_lock_preflight($order,$GLOBALS['contract132'])['error']
    ==='order_quote_metadata_mismatch', 'tampered persisted quote denied');
$order->items[72]->unit_price=30000;
$order->items[73] = new Item132(73,'XL',1,10000,$GLOBALS['design132']);
check132(sanro_studio_checkout_batch_lock_preflight($order,$GLOBALS['contract132'])['error']
    ==='order_line_count_mismatch', 'extra order item denied');
unset($order->items[73]);
$order->items[72]->size='M';
check132(sanro_studio_checkout_batch_lock_preflight($order,$GLOBALS['contract132'])['error']
    ==='order_design_mismatch', 'duplicate size rejected');
$order->items[72]->size='L';
$order->items[72]->design='11111111-1111-4111-8111-111111111111';
check132(sanro_studio_checkout_batch_lock_preflight($order,$GLOBALS['contract132'])['error']
    ==='order_design_mismatch', 'other design rejected');
$order->items[72]->design=$GLOBALS['design132'];
$order->items[72]->product=45;
check132(sanro_studio_checkout_batch_lock_preflight($order,$GLOBALS['contract132'])['error']
    ==='invalid_order_item', 'wrong product denied');
$order->items[72]->product=42;
$order->items[72]->subtotal=100;
check132(sanro_studio_checkout_batch_lock_preflight($order,$GLOBALS['contract132'])['error']
    ==='order_quote_mismatch', 'price tamper denied');
$order->items[72]->subtotal=90000;
$order->total=1;
check132(sanro_studio_checkout_batch_lock_preflight($order,$GLOBALS['contract132'])['error']
    ==='order_total_mismatch', 'discounted grand total denied');
$order->total=190000;
$order->currency='USD';
check132(sanro_studio_checkout_batch_lock_preflight($order,$GLOBALS['contract132'])['error']
    ==='invalid_order', 'foreign currency denied');
$order->currency='IDR';
$order->items[72]->id=999;
check132(sanro_studio_checkout_batch_lock_preflight($order,$GLOBALS['contract132'])['error']
    ==='invalid_order_item', 'item ID/key mismatch denied');
$order->items[72]->id=72;
$GLOBALS['draft132']['expires_at']=time()-1;
check132(sanro_studio_lock_checkout_order_draft($order,$GLOBALS['contract132'],
    $GLOBALS['token132'],$GLOBALS['hash132'])['error']==='draft_conflict',
    'expired draft denied');
$GLOBALS['draft132']['expires_at']=time()+3600;
check132(sanro_studio_lock_checkout_order_draft($order,$GLOBALS['contract132'],
    str_repeat('b',64),$GLOBALS['hash132'])['error']==='draft_conflict',
    'wrong owner token denied');
check132(sanro_studio_lock_checkout_order_draft($order,$GLOBALS['contract132'],
    $GLOBALS['token132'],str_repeat('a',64))['error']==='draft_conflict',
    'stale design snapshot denied');
$GLOBALS['denyImages132']=true;
check132(sanro_studio_lock_checkout_order_draft($order,$GLOBALS['contract132'],
    $GLOBALS['token132'],$GLOBALS['hash132'])['error']==='draft_not_print_ready',
    'revoked image binding denied');
$GLOBALS['denyImages132']=false;
$locked=sanro_studio_lock_checkout_order_draft($order,$GLOBALS['contract132'],
    $GLOBALS['token132'],$GLOBALS['hash132']);
check132(!empty($locked['valid']) && $locked['already_locked']===false,
    'fresh valid draft locked atomically');
check132($GLOBALS['draft132']['status']==='locked'
    && $GLOBALS['draft132']['order_id']===100
    && $GLOBALS['draft132']['order_item_ids']===[71,72]
    && !isset($GLOBALS['draft132']['order_item_id']),
    'one design tied to both actual order line IDs');
check132(!isset($GLOBALS['draft132']['owner_token'])
    && strpos(serialize($GLOBALS['draft132']),$GLOBALS['token132'])===false
    && $GLOBALS['wpdb']->saves===1, 'no secret persisted and exactly one CAS update');
check132(count($GLOBALS['cache132'])===1, 'cache invalidated after CAS');
$retry=sanro_studio_lock_checkout_order_draft($order,$GLOBALS['contract132'],
    $GLOBALS['token132'],$GLOBALS['hash132']);
check132(!empty($retry['valid']) && $retry['already_locked']===true
    && $GLOBALS['wpdb']->saves===1, 'same order retry idempotent');
$order->id=101;
check132(sanro_studio_lock_checkout_order_draft($order,$GLOBALS['contract132'],
    $GLOBALS['token132'],$GLOBALS['hash132'])['error']==='draft_already_locked',
    'locked design cannot attach different order');
$order->id=100;
check132(sanro_studio_locked_record_has_item($GLOBALS['draft132'],71)
    && sanro_studio_locked_record_has_item($GLOBALS['draft132'],72)
    && !sanro_studio_locked_record_has_item($GLOBALS['draft132'],73),
    'private master reader only accepts explicitly locked size lines');
check132(!sanro_studio_locked_record_has_item([
    'order_item_ids'=>[71,71],'order_id'=>100],71), 'duplicate item IDs denied');
check132(!sanro_studio_locked_record_has_item([
    'order_item_ids'=>['71',72],'order_id'=>100],71), 'string item ID denied');
check132(!sanro_studio_locked_record_has_item([
    'order_item_ids'=>[71,72],'order_item_id'=>71],71),
    'ambiguous legacy and batch binding denied');
check132(sanro_studio_locked_record_has_item(['order_item_id'=>71],71)
    && !sanro_studio_locked_record_has_item(['order_item_id'=>71],72),
    'existing single-item admin lock remains compatible');
$order=reset132();$GLOBALS['wpdb']->race=true;
check132(sanro_studio_lock_checkout_order_draft($order,$GLOBALS['contract132'],
    $GLOBALS['token132'],$GLOBALS['hash132'])['error']==='draft_conflict',
    'concurrent CAS update safely rejected');
$order=reset132();$GLOBALS['wpdb']->fail=true;
check132(sanro_studio_lock_checkout_order_draft($order,$GLOBALS['contract132'],
    $GLOBALS['token132'],$GLOBALS['hash132'])['error']==='storage_failed',
    'failed DB update rejected');
$order=reset132();
$GLOBALS['sanro_studio_checkout_verified'] = ['contract'=>$GLOBALS['contract132']];
try {
    sanro_studio_stage_checkout_lock_created_order($order);
    fwrite(STDERR, "FAIL: missing request credential did not abort checkout\n");
    exit(1);
} catch (RuntimeException $exception) {
    check132(strpos($exception->getMessage(),'missing')!==false,
        'missing transient checkout credential rejects payment handoff');
}
$order=reset132();
$GLOBALS['sanro_studio_checkout_verified']=[
    'contract'=>$GLOBALS['contract132'],
    'lock_owner_token'=>$GLOBALS['token132'],
    'lock_design_hash'=>$GLOBALS['hash132']
];
sanro_studio_stage_checkout_lock_created_order($order);
check132(($GLOBALS['draft132']['status']??null)==='locked'
    && !isset($GLOBALS['sanro_studio_checkout_verified']['lock_owner_token']),
    'post-create hook locks and drops transient owner token');
// The staging hook must not interfere with an ordinary WooCommerce order.
unset($GLOBALS['sanro_studio_checkout_verified']);
$ordinary = new Order132();
$ordinary->items = [71 => new Item132(71, 'M', 2, 100000, '')];
sanro_studio_stage_checkout_lock_created_order($ordinary);
check132(true, 'ordinary WooCommerce checkout bypasses SANRO lock safely');
// But a SANRO order with missing request authorization must never bypass it.
$unauthorized = reset132();
unset($GLOBALS['sanro_studio_checkout_verified']);
try {
    sanro_studio_stage_checkout_lock_created_order($unauthorized);
    fwrite(STDERR, "FAIL: unauthorized SANRO order not rejected\n");
    exit(1);
} catch (RuntimeException $e) {
    check132(strpos($e->getMessage(), 'missing') !== false,
        'SANRO order with no verified checkout session rejected');
}
echo "PASS: {$checks} SANRO staging batch draft-lock checks\n";
