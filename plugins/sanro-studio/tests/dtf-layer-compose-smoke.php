<?php
/** Sprint 135 fail-closed compositor regression, no WP, WC or network. */
define('SANRO_STUDIO_ENABLE_DTF_LAYER_RASTER', true);
$font135 = getenv('SANRO_STUDIO_TEST_FONT');
if(is_string($font135)&&$font135!=='')define('SANRO_STUDIO_DTF_FONT_PATH',$font135);
require __DIR__ . '/dtf-image-render-smoke.php';
require dirname(__DIR__) . '/includes/dtf-layer-render.php';

$checks135 = 0;
function check135($good, $label) {
    global $checks135;
    if (!$good) {
        fwrite(STDERR, "FAIL: layered DTF: " . $label . "\n");
        exit(1);
    }
    $checks135++;
}
$order = fixture133();
check135(sanro_studio_dtf_layer_enabled(), 'explicit layered flag');
$plan = sanro_studio_dtf_layer_plan($order, 71, 'front');
check135(!empty($plan['valid'])
    && $plan['kind'] === 'layered_dtf_raster_candidate'
    && $plan['quality_approved'] === false
    && $plan['font_parity_approved'] === false,
    'image-only plan is an unapproved candidate');
check135($plan['width_px'] === 12 && $plan['height_px'] === 12
    && $plan['offset_x_cm'] === 2.0 && $plan['offset_y_cm'] === 3.0,
    'existing physical geometry remains 300 DPI');
check135(is_array($plan['image']) && !empty($plan['image']['bytes'])
    && $plan['text_layer'] === null, 'master stays private on plan');
check135(!empty(sanro_studio_dtf_layer_plan($order, 71, 'back')['skipped']),
    'blank back skipped');
check135(sanro_studio_dtf_layer_plan($order, 72, 'front')['error']
    === 'dtf_master_unavailable', 'other order line source denied');
$order->id=101;
check135(sanro_studio_dtf_layer_plan($order, 71, 'front')['error']
    === 'draft_order_binding_missing', 'cross order denied');
$order = fixture133();
$GLOBALS['record133']['status'] = 'draft';
check135(sanro_studio_dtf_layer_plan($order, 71, 'front')['error']
    === 'draft_order_binding_missing', 'unlocked design denied');
$order=fixture133();
$GLOBALS['record133']['design']['front']['objects'][] = [
    'type'=>'text','x'=>3.0,'y'=>3.1,'width'=>1.0,'height'=>0.5
];
$GLOBALS['record133']['design']['front']['text'] = 'HELLO';
$GLOBALS['record133']['design']['front']['font_size'] = 22;
$GLOBALS['record133']['design']['front']['text_color'] = '#171717';
$GLOBALS['image133']=png133(60,60);
$GLOBALS['sha133']=hash('sha256',$GLOBALS['image133']);
$GLOBALS['record133']['design']['front']['objects'][0]['width']=0.5;
$GLOBALS['record133']['design']['front']['objects'][0]['height']=0.5;
$font_ready = sanro_studio_dtf_private_font() !== false;
$composed = sanro_studio_dtf_layer_plan($order, 71, 'front');
if (!$font_ready) {
    check135(($composed['error']??null)==='dtf_private_font_required',
        'text cannot render with absent/untrusted font');
} else {
    check135(!empty($composed['valid']) && is_array($composed['text_layer']),
        'trusted local font allows mixed-layer plan');
}
$GLOBALS['record133']['design']['front']['text'] = "HELLO\nWORLD";
check135(!empty(sanro_studio_dtf_layer_plan($order,71,'front')['valid']), 'multiline text retained in plan');
$GLOBALS['record133']['design']['front']['text'] = "مرحبا";
check135(sanro_studio_dtf_layer_plan($order,71,'front')['error']
    === 'dtf_text_layout_unsupported', 'unsupported complex shaping refused');
$GLOBALS['record133']['design']['front']['text'] = 'HELLO';
$GLOBALS['record133']['design']['front']['text_color'] = '#BAD';
check135(sanro_studio_dtf_layer_plan($order,71,'front')['error']
    === 'dtf_text_layout_unsupported', 'invalid text color rejected');
$GLOBALS['record133']['design']['front']['text_color'] = '#171717';
$GLOBALS['record133']['design']['front']['objects'][] = [
    'type'=>'text','x'=>4.0,'y'=>4.0,'width'=>1.0,'height'=>0.5
];
check135(sanro_studio_dtf_layer_plan($order,71,'front')['error']
    === 'invalid_print_geometry', 'duplicate text rejected');
$GLOBALS['record133']['design']['front']['objects'] = [[
    'type'=>'text','x'=>3.0,'y'=>3.1,'width'=>1.0,'height'=>0.5
]];
$text_only = sanro_studio_dtf_layer_plan($order,71,'front');
check135($font_ready ? !empty($text_only['valid'])
    : (($text_only['error']??null)==='dtf_private_font_required'),
    'text-only checks font availability');
$GLOBALS['record133']['design']['front']['objects'][] = [
    'type'=>'image','x'=>2.0,'y'=>3.0,'width'=>0.5,'height'=>0.5
];
$GLOBALS['record133']['design']['front']['text'] = 'HELLO';
$GLOBALS['record133']['design']['front']['text_color'] = '#171717';
$plan = sanro_studio_dtf_layer_plan($order,71,'front');
check135($font_ready ? !empty($plan['valid'])
        : (($plan['error']??null)==='dtf_private_font_required'),
    'mixed image and text checked without dropping either');

if ($font_ready && function_exists('imagettfbbox') && function_exists('imagecreatefromstring')) {
    $render = sanro_studio_dtf_render_layer_side($order,71,'front');
    check135(!empty($render['valid']) && ($render['kind']??'')==='layered_dtf_raster_candidate',
        'real GD draws buyer-bound image and text');
    check135($render['quality_approved'] === false && $render['font_parity_approved'] === false
        && isset($render['sha256']) && !isset($render['image']),
        'render remains unapproved and excludes raw master');
    // The image finishes before the text starts, so an opaque pixel in the
    // text rectangle proves FreeType actually painted a second layer.
    $decoded = imagecreatefromstring($render['bytes']);
    $layer = $plan['text_layer'];
    $painted = false;
    for ($y = $layer['y']; $y < $layer['y'] + $layer['height']; $y++) {
        for ($x = $layer['x']; $x < $layer['x'] + $layer['width']; $x++) {
            $rgba = imagecolorsforindex($decoded, imagecolorat($decoded, $x, $y));
            if ($rgba['alpha'] < 100 && $rgba['red'] <= 50
                && $rgba['green'] <= 50 && $rgba['blue'] <= 50) {
                $painted = true; break 2;
            }
        }
    }
    imagedestroy($decoded);
    check135($painted, 'actual private font glyphs painted over transparent pixels');
    echo "DTF_LAYER_REAL: PASS (both image and text pixels rasterized)\n";
} else {
    $render = sanro_studio_dtf_render_layer_side($order,71,'front');
    check135(($render['error']??null) === ($font_ready ? 'dtf_gd_unavailable' : 'dtf_private_font_required'),
        'no trusted font/GD cannot fabricate final artwork');
    check135(!isset($render['bytes']), 'invalid render has no output PNG');
    check135(($render['error'] ?? null) === ($font_ready ? 'dtf_gd_unavailable' : 'dtf_private_font_required'),
        'fallback explicitly names unavailable rendering prerequisite');
    echo "DTF_LAYER_REAL: NOT_TESTED (trusted private font or GD missing)\n";
}
echo "PASS: {$checks135} SANRO layered DTF composition checks\n";
