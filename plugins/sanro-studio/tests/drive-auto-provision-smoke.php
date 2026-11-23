<?php
/** Offline Sprint 130 regression. No real WordPress, Google, or WooCommerce. */
define('ABSPATH', __DIR__ . '/');
define('SANRO_STUDIO_ENABLE_DRIVE_AUTO_PROVISION', true);
define('SANRO_STUDIO_ENABLE_VERIFIED_DRIVE_ATTACHMENT', true);
define('SANRO_STUDIO_DRIVE_PARENT_FOLDER_ID', 'privateParent_0123456789');
require dirname(__DIR__) . '/includes/drive-auto-provision.php';
require dirname(__DIR__) . '/includes/drive-production.php';

$checks = 0;
function check130($condition, $message) {
    global $checks;
    if (!$condition) { fwrite(STDERR, 'FAIL: ' . $message . "\n"); exit(1); }
    $checks++;
}
class WpError130 {}
function is_wp_error($v) { return $v instanceof WpError130; }
function current_user_can($cap){return $cap==='manage_woocommerce';}
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
    public $fail_save=false; public $items = []; public $status = 'processing'; public $paid = true; public $meta = [];
    function get_id() { return 100; }
    function update_meta_data($key,$v){$this->meta[$key]=$v;}
    function save(){return !$this->fail_save;}
    function get_order_number(){return 'ORDER-100';}
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
function wp_remote_get($url, $args) {
    $GLOBALS['get130'][] = ['url' => $url, 'args' => $args];
    if (($GLOBALS['fail_search130'] ?? false) === true) return new WpError130();
    if (strpos($url, '/drive/v3/files?') !== false) {
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $GLOBALS['query130'] = $query;
        preg_match_all("/key='([^']+)' and value='([^']+)'/",$query['q'],$matches,PREG_SET_ORDER);
        preg_match("/^'([^']+)' in parents/",$query['q'],$parent);
        $files=[];foreach($GLOBALS['remote130'] as $file){$valid=($file['parents']??[])===[$parent[1]];foreach($matches as $m)if(($file['appProperties'][$m[1]]??null)!==$m[2])$valid=false;if($valid)$files[]=['id'=>$file['id']];}
        if($GLOBALS['ambiguous130']??false)$files=[['id'=>'ambiguous-one-100'],['id'=>'ambiguous-two-100']];
        if(($GLOBALS['foreign130']??false)&&str_contains($query['q'],"key='sanroItemId'"))$files=[['id'=>'foreignFolder_123456']];
        return ['code' => 200, 'body' => json_encode(['files' => $files])];
    }
    $id=basename(parse_url($url,PHP_URL_PATH));$file=$GLOBALS['remote130'][$id]??null;if($file&&($GLOBALS['public130']??false))$file['permissions']=[['type'=>'anyone']];
    return ['code'=>$file?200:404,'body'=>json_encode($file??[])];
}
function wp_remote_post($url, $args) {
    $GLOBALS['post130'][] = ['url' => $url, 'args' => $args];
    if ($GLOBALS['fail_create130'] ?? false) return ['code' => 503, 'body' => '{}'];
    $payload = json_decode($args['body'], true);
    $id = isset($payload['appProperties']['sanroItemId'])?'createdFolder_100_71':(($payload['appProperties']['sanroFolderKind']??'')==='order'?'createdOrder_100':'createdProduction_100');
    $GLOBALS['remote130'][$id] = $payload + ['id' => $id, 'trashed'=>false, 'permissions' => [['type' => 'user']]];
    return ['code' => 201, 'body' => json_encode(['id' => $id])];
}
function reset130() {
    $GLOBALS['remote130'] = []; $GLOBALS['locks130'] = []; $GLOBALS['jobs130'] = [];
    $GLOBALS['get130'] = []; $GLOBALS['post130'] = [];
    $GLOBALS['token130'] = 'ya29.synthetic-scope-token';
    unset($GLOBALS['ambiguous130'], $GLOBALS['fail_search130'], $GLOBALS['fail_create130'],$GLOBALS['foreign130'],$GLOBALS['public130']);
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
check130(count($GLOBALS['post130']) === 3 && $item->saves === 1,
    'order, production and item folders created once with one item save');
$payload = json_decode($GLOBALS['post130'][2]['args']['body'], true);
check130($payload['parents'] === ['createdProduction_100']
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
check130(!empty($result['valid']) && $result['reused'] === 1 && count($GLOBALS['post130']) === 3
    && $item->saves === 1, 'repeated job does not create or write again');
$item->meta['_sanro_studio_drive_folder_id'] = '';
$result = sanro_studio_run_drive_production(100);
check130(!empty($result['valid']) && $result['reused'] === 1
    && count($GLOBALS['post130']) === 3, 'interrupted save recovered by remote lookup');
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
$GLOBALS['remote130']['foreignFolder_123456'] = ['id' => 'foreignFolder_123456'];$GLOBALS['foreign130']=true;
check130(sanro_studio_run_drive_production(100)['error'] === 'drive_created_folder_unverified'
    && $item->saves === 0, 'foreign folder never attached');
list($order, $item) = reset130();
$other = new Item130(); $other->meta['_sanro_studio_design_id'] = 'other';
$order->items[72] = $other;
check130(sanro_studio_run_drive_production(100)['error'] === 'invalid_production_manifest'
    && count($GLOBALS['get130']) === 0, 'batch preflight blocks partial provisioning');
list($order,$item)=reset130();sanro_studio_run_drive_production(100);
check130($order->meta['_sanro_studio_drive_order_folder_id']==='createdOrder_100'&&$order->meta['_sanro_studio_drive_production_folder_id']==='createdProduction_100','order-level hierarchy persisted using CRUD');
$root=$GLOBALS['remote130']['createdOrder_100'];check130($root['name']==='SANRO-ORDER-100'&&$GLOBALS['remote130']['createdProduction_100']['parents']===['createdOrder_100'],'order number root and Produksi child');
check130(sanro_studio_verified_order_drive_url($order)==='https://drive.google.com/drive/folders/createdOrder_100','admin root link exists only after entire hierarchy verified');
$GLOBALS['public130']=true;check130(empty(sanro_studio_verify_drive_item_folder($order,71,'createdFolder_100_71')['valid']),'public ancestor denies otherwise bound item');check130(sanro_studio_verified_order_drive_url($order)===null,'unverified ancestor never emits a root link');unset($GLOBALS['public130']);
$GLOBALS['remote130']['createdProduction_100']['parents']=['foreignParent_12345'];check130(empty(sanro_studio_verify_drive_item_folder($order,71,'createdFolder_100_71')['valid']),'moved ancestor denies entire hierarchy');
list($order,$item)=reset130();$order->fail_save=true;
check130(sanro_studio_run_drive_production(100)['error']==='drive_layout_save_failed'&&count($GLOBALS['post130'])===1,'remote root create with failed order save stops before children');
$order->fail_save=false;$order->meta=[];
check130(!empty(sanro_studio_run_drive_production(100)['valid'])&&count($GLOBALS['post130'])===3,'lost order metadata recovers exact remote root without duplication');
echo 'PASS: ' . $checks . ' SANRO Drive auto-provision regression checks' . "\n";
