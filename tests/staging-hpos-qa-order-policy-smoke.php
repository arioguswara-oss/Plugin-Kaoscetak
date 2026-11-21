<?php
/** Sprint 142 deterministic offline tests — no WordPress, DB or order writes. */
require dirname(__DIR__) . '/ops/hostinger/sanro-studio-pr32-qa-order-policy.php';
class S142FakeItem {
    public $id = 75;
    public $order_id = 302;
    public $product_id = 0;
    public $quantity = 1;
    public $total = 0;
    public $subtotal = 0;
    public $design = '123e4567-e89b-42d3-a456-123456789012';
    function get_id() { return $this->id; }
    function get_order_id() { return $this->order_id; }
    function get_product_id() { return $this->product_id; }
    function get_quantity() { return $this->quantity; }
    function get_total() { return $this->total; }
    function get_subtotal() { return $this->subtotal; }
    function get_meta($key, $single=true) { return $key === '_sanro_studio_design_id' ? $this->design : ''; }
}
class S142FakeOrder {
    public $id = 302;
    public $marker = SANRO_STUDIO_S142_FIXTURE;
    public $created_via = 'sanro_studio_staging_qa';
    public $status = 'pending';
    public $total = 0;
    public $customer_id = 0;
    public $payment_method = '';
    public $transaction_id = '';
    public $paid = false;
    public $items;
    function __construct() { $this->items = [75 => new S142FakeItem()]; }
    function get_id() { return $this->id; }
    function get_meta($key, $single=true) { return $key === '_sanro_studio_qa_fixture' ? $this->marker : ''; }
    function get_created_via() { return $this->created_via; }
    function get_status() { return $this->status; }
    function get_total() { return $this->total; }
    function get_customer_id() { return $this->customer_id; }
    function get_payment_method() { return $this->payment_method; }
    function get_transaction_id() { return $this->transaction_id; }
    function is_paid() { return $this->paid; }
    function get_items($type='line_item') { return $type === 'line_item' ? $this->items : []; }
}
$totalChecks = 0;
function s142_check($pass, $what) {
    global $totalChecks;
    if (!$pass) { fwrite(STDERR,"FAIL: $what\n"); exit(1); }
    $totalChecks++;
}
$host = SANRO_STUDIO_S142_STAGE_HOST;
$root = SANRO_STUDIO_S142_STAGE_ROOT;
$url = 'https://' . $host;
s142_check(sanro_s142_staging_identity_ok('id-dci-web1631','u606139982','staging',$root,$url,$url),
    'exact staging host user and WP URLs pass');
s142_check(!sanro_s142_staging_identity_ok('wrong-host','u606139982','staging',$root,$url,$url),
    'other host blocked');
s142_check(!sanro_s142_staging_identity_ok('id-dci-web1631','root','staging',$root,$url,$url),
    'root and other users blocked');
s142_check(!sanro_s142_staging_identity_ok('id-dci-web1631','u606139982','production',$root,$url,$url),
    'production mode blocked');
s142_check(!sanro_s142_staging_identity_ok('id-dci-web1631','u606139982','staging','/var/www/html',$url,$url),
    'other WordPress root blocked');
s142_check(!sanro_s142_staging_identity_ok('id-dci-web1631','u606139982','staging',$root,
    'https://kaoscetak.id',$url),'production home blocked');
s142_check(!sanro_s142_staging_identity_ok('id-dci-web1631','u606139982','staging',$root,
    $url,'https://kaoscetak.id'),'production siteurl blocked');
s142_check(sanro_s142_valid_design_uuid('123e4567-e89b-42d3-a456-123456789012'),
    'valid version4 UUID accepted');
s142_check(!sanro_s142_valid_design_uuid('not-a-uuid'),'malformed design id refused');
s142_check(!sanro_s142_valid_design_uuid(''),'empty design id refused');
$order=new S142FakeOrder();
s142_check(sanro_s142_fixture_is_safe($order),'one pending Rp0 productless order accepted');
$order->marker='another-marker';
s142_check(!sanro_s142_fixture_is_safe($order),'foreign order marker rejected');
$order->marker=SANRO_STUDIO_S142_FIXTURE;
$order->created_via='checkout';
s142_check(!sanro_s142_fixture_is_safe($order),'real checkout origin rejected');
$order->created_via='sanro_studio_staging_qa';
$order->status='processing';
s142_check(!sanro_s142_fixture_is_safe($order),'processing order rejected');
$order->status='pending';
$order->total=100;
s142_check(!sanro_s142_fixture_is_safe($order),'nonzero order rejected');
$order->total=0;
$order->customer_id=123;
s142_check(!sanro_s142_fixture_is_safe($order),'real customer rejected');
$order->customer_id=0;
$order->payment_method='bacs';
s142_check(!sanro_s142_fixture_is_safe($order),'payment method rejected');
$order->payment_method='';
$order->transaction_id='tr_123';
s142_check(!sanro_s142_fixture_is_safe($order),'transaction id rejected');
$order->transaction_id='';
$order->paid=true;
s142_check(!sanro_s142_fixture_is_safe($order),'paid order rejected');
$order->paid=false;
$order->items[76]=new S142FakeItem();
s142_check(!sanro_s142_fixture_is_safe($order),'two items rejected');
unset($order->items[76]);
$item=$order->items[75];
$item->order_id=304;
s142_check(!sanro_s142_fixture_is_safe($order),'foreign order line rejected');
$item->order_id=302;
$item->product_id=4;
s142_check(!sanro_s142_fixture_is_safe($order),'stock-linked product rejected');
$item->product_id=0;
$item->quantity=2;
s142_check(!sanro_s142_fixture_is_safe($order),'quantity over one rejected');
$item->quantity=1;
$item->total=10;
s142_check(!sanro_s142_fixture_is_safe($order),'nonzero item charge rejected');
$item->total=0;
$item->subtotal=20;
s142_check(!sanro_s142_fixture_is_safe($order),'nonzero item subtotal rejected');
$item->subtotal=0;
$item->design='223e4567-e89b-72d3-a456-123456789012';
s142_check(!sanro_s142_fixture_is_safe($order),'invalid UUID version rejected');
$item->design='123e4567-e89b-42d3-a456-123456789012';
$order->id=0;
s142_check(!sanro_s142_fixture_is_safe($order),'unsaved order cannot pass readback');
$order->id=302;
s142_check(sanro_s142_fixture_is_safe($order),'restored safe fixture passes');
echo 'PASS: ' . $totalChecks . " SANRO HPOS staging synthetic zero-order safety checks\n";
