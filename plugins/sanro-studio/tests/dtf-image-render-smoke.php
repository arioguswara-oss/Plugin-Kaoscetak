<?php
/**
 * Sprint 133 synthetic RGBA DTF geometry/raster smoke. No WordPress database,
 * WooCommerce, filesystem, Google Drive, HTTP or payment gateway.
 */
define('ABSPATH', dirname(__DIR__, 3) . '/');
define('SANRO_STUDIO_ENABLE_DTF_IMAGE_RASTER', true);
require dirname(__DIR__) . '/includes/print-area.php';
require dirname(__DIR__) . '/includes/order-master-source.php';
require dirname(__DIR__) . '/includes/dtf-image-render.php';

$checks = 0;
function check133($ok, $description) {
    global $checks;
    if (!$ok) { fwrite(STDERR, 'FAIL: ' . $description . "\n"); exit(1); }
    $checks++;
}
/** Generate a real 8-bit PNG without relying on GD for fixture generation. */
function chunk133($kind, $data) {
    return pack('N', strlen($data)) . $kind . $data . pack('N', crc32($kind . $data));
}
function png133($width, $height, $type = 6) {
    $pixels = '';
    $pixel = $type === 6 ? "\xff\x00\x00\x00" : "\xff\x00\x00";
    for ($y = 0; $y < $height; $y++) {
        $pixels .= "\x00" . str_repeat($pixel, $width);
    }
    return "\x89PNG\r\n\x1a\n"
        . chunk133('IHDR', pack('NNCCCCC', $width, $height, 8, $type, 0, 0, 0))
        . chunk133('IDAT', gzcompress($pixels, 9))
        . chunk133('IEND', '');
}
class Item133 {
    public $design = '123e4567-e89b-42d3-a456-123456789012';
    public $product = 42;
    function get_meta($key, $single = true) {
        return $key === '_sanro_studio_design_id' ? $this->design : '';
    }
    function get_product_id() { return $this->product; }
}
class Order133 {
    public $id = 100; public $paid = true; public $status = 'processing';
    public $items = [];
    function get_id() { return $this->id; }
    function get_items($type='line_item') { return $type === 'line_item' ? $this->items : []; }
}
function sanro_studio_auto_drive_order_eligible($order) {
    return $order->paid === true && $order->status !== 'cancelled';
}
function sanro_studio_product_safe_areas($product_id) {
    return $product_id === 42 ? ['front'=>['width'=>20.0,'height'=>30.0],
        'back'=>['width'=>20.0,'height'=>30.0]] : [];
}
function sanro_studio_validate_product_design($product_id, $design) {
    if ($product_id !== 42 || !is_array($design)) return ['valid'=>false];
    $geometry = $design;
    foreach (['front','back'] as $side) {
        if (!isset($geometry[$side])) continue;
        unset($geometry[$side]['text'], $geometry[$side]['font_size'], $geometry[$side]['text_color']);
    }
    return sanro_studio_validate_print_design($geometry, sanro_studio_product_safe_areas($product_id));
}
function get_option($key, $default=false) {
    return $key === 'sanro_studio_draft_' . $GLOBALS['id133']
        ? $GLOBALS['record133'] : $default;
}
function sanro_studio_locked_item_master_source($order, $item_id, $side) {
    if ($order->get_id() !== 100 || $item_id !== 71 || $side !== 'front') {
        return ['valid'=>false,'error'=>'source_unavailable'];
    }
    $bytes = $GLOBALS['image133'];
    return ['valid'=>true, 'skipped'=>false, 'bytes'=>$bytes,
        'mime'=>$GLOBALS['mime133'],'sha256'=>$GLOBALS['sha133']];
}
function fixture133() {
    $GLOBALS['id133'] = '123e4567-e89b-42d3-a456-123456789012';
    $GLOBALS['image133'] = png133(12, 12);
    $GLOBALS['mime133'] = 'image/png';
    $GLOBALS['sha133'] = hash('sha256', $GLOBALS['image133']);
    $GLOBALS['record133'] = [
        'status'=>'locked','order_id'=>100,'product_id'=>42,
        'order_item_ids'=>[71,72],
        'design'=>['front'=>['category'=>'A5','objects'=>[
            ['type'=>'image','x'=>2.0,'y'=>3.0,'width'=>0.1,'height'=>0.1]]]]
    ];
    $order = new Order133();$order->items = [71=>new Item133(),72=>new Item133()];
    return $order;
}
$order = fixture133();
check133(sanro_studio_dtf_image_render_enabled(), 'explicit flag enabled');
$plan = sanro_studio_dtf_image_plan($order,71,'front');
check133(!empty($plan['valid']) && $plan['width_px']===12 && $plan['height_px']===12
    && $plan['dpi']===300, '300 DPI plan converts physical cm to correct pixels');
check133($plan['offset_x_cm']===2.0 && $plan['offset_y_cm']===3.0
    && $plan['width_cm']===0.1 && $plan['height_cm']===0.1,
    'physical placement retained independently of tightly cropped canvas');
check133(!isset($plan['owner_token']) && !isset($plan['file_path'])
    && isset($plan['source_sha256']), 'no credentials or filesystem location in plan');
check133(sanro_studio_dtf_image_plan($order,71,'back')['skipped']===true,
    'missing back design is explicitly skipped');
check133(sanro_studio_dtf_image_plan($order,71,'left')['error']==='dtf_render_unavailable',
    'invalid print side refused');
check133(sanro_studio_dtf_image_plan($order,72,'front')['error']==='dtf_master_unavailable',
    'unavailable master never substituted from another order item');
$order->paid=false;
check133(sanro_studio_dtf_image_plan($order,71,'front')['error']==='dtf_render_unavailable',
    'unpaid order denied');
$order->paid=true;
$order->id=101;
check133(sanro_studio_dtf_image_plan($order,71,'front')['error']==='draft_order_binding_missing',
    'cross-order draft denied');
$order->id=100;
$order->items[71]->product=99;
check133(sanro_studio_dtf_image_plan($order,71,'front')['error']==='draft_order_binding_missing',
    'cross-product master denied');
$order->items[71]->product=42;
$GLOBALS['record133']['order_item_ids']=[72];
check133(sanro_studio_dtf_image_plan($order,71,'front')['error']==='draft_order_binding_missing',
    'unlisted WC line denied');
$GLOBALS['record133']['order_item_ids']=[71,72];
$GLOBALS['record133']['status']='draft';
check133(sanro_studio_dtf_image_plan($order,71,'front')['error']==='draft_order_binding_missing',
    'unlocked design denied');
$GLOBALS['record133']['status']='locked';
$GLOBALS['record133']['design']['front']['objects'][]=
    ['type'=>'text','x'=>2.0,'y'=>3.2,'width'=>0.05,'height'=>0.05];
$GLOBALS['record133']['design']['front']['text']='NO SILENT DROP';
check133(sanro_studio_dtf_image_plan($order,71,'front')['error']==='dtf_layers_unsupported',
    'mixed image/text cannot silently omit text');
$GLOBALS['record133']['design']['front']['objects']=
    [['type'=>'text','x'=>2.0,'y'=>3.0,'width'=>0.1,'height'=>0.1]];
check133(sanro_studio_dtf_image_plan($order,71,'front')['error']==='dtf_layers_unsupported',
    'text-only side denied until font renderer exists');
$order=fixture133();
$GLOBALS['image133']=png133(12,12,2);
$GLOBALS['sha133']=hash('sha256',$GLOBALS['image133']);
check133(sanro_studio_dtf_image_plan($order,71,'front')['error']==='dtf_rgba_png_required',
    'opaque RGB PNG rejected rather than claiming transparent transfer');
$order=fixture133();$GLOBALS['mime133']='image/jpeg';
check133(sanro_studio_dtf_image_plan($order,71,'front')['error']==='dtf_rgba_png_required',
    'JPEG originals rejected until alpha-aware processing exists');
$order=fixture133();$GLOBALS['image133']=png133(8,8);
$GLOBALS['sha133']=hash('sha256',$GLOBALS['image133']);
check133(sanro_studio_dtf_image_plan($order,71,'front')['error']==='dtf_source_resolution',
    'low DPI source rejected, no upscaling to pretend 300 DPI');
$order=fixture133();$GLOBALS['image133']=png133(24,12);
$GLOBALS['sha133']=hash('sha256',$GLOBALS['image133']);
check133(sanro_studio_dtf_image_plan($order,71,'front')['error']==='dtf_source_aspect_mismatch',
    'distorted source aspect rejected');
$order=fixture133();$GLOBALS['sha133']=str_repeat('a',64);
check133(sanro_studio_dtf_image_plan($order,71,'front')['error']==='dtf_source_integrity_failed',
    'SHA mismatch refused before rasterization');
$order=fixture133();$GLOBALS['record133']['design']['front']['objects'][0]['width']=80.0;
check133(sanro_studio_dtf_image_plan($order,71,'front')['error']==='invalid_print_geometry',
    'oversized geometry refused by product safe zone');
$order=fixture133();$GLOBALS['record133']['design']['front']['objects'][0]['width']=18.0;
$GLOBALS['record133']['design']['front']['objects'][0]['height']=18.0;
check133(sanro_studio_dtf_image_plan($order,71,'front')['error']==='dtf_canvas_limit',
    'high-memory giant canvas refused pre-render');
$order=fixture133();
$result=sanro_studio_dtf_render_image_side($order,71,'front');
if (function_exists('imagecreatefromstring') && function_exists('imageresolution')) {
    check133(!empty($result['valid']) && ($result['mime']??null)==='image/png'
        && ($result['kind']??null)==='image_only_dtf_raster_candidate'
        && $result['quality_approved']===false,
        'GD rasterizes real private source as unapproved PNG candidate');
    $info=getimagesizefromstring($result['bytes']);
    check133(is_array($info) && $info[0]===12 && $info[1]===12
        && ($info['mime']??null)==='image/png' && ord($result['bytes'][25])===6,
        'render output is RGBA PNG with exact physical pixel dimensions');
    check133(hash_equals(hash('sha256',$result['bytes']),$result['sha256'])
        && !isset($result['source_bytes']), 'render digest valid, raw source not in output metadata');
} else {
    check133(($result['error']??null)==='dtf_gd_unavailable',
        'missing PHP GD refuses rasterization without producing a false output');
    check133(!isset($result['bytes']), 'no fabricated artwork when renderer unavailable');
    check133(!isset($result['sha256']), 'no fabricated image hash when renderer unavailable');
}
echo "PASS: {$checks} SANRO image-only DTF raster safety checks\n";
