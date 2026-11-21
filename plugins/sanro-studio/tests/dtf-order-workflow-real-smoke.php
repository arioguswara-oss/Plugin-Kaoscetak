<?php
/**
 * Sprint 137 real GD/FreeType end-to-end QA: locked synthetic order,
 * two sides, PNG raster -> private disk -> SHA256 readback.
 * Must run in isolated staging Git checkout with SANRO_STUDIO_TEST_FONT
 * pointing to an already available trusted TTF outside the WordPress root.
 * Never calls any WooCommerce API or uploads to Google Drive.
 */
define('SANRO_STUDIO_ENABLE_DTF_PRIVATE_ARTIFACTS', true);
define('SANRO_STUDIO_ENABLE_DTF_ORDER_BATCH', true);
$dir137real = sys_get_temp_dir() . '/sanro-dtf-order-real-' . bin2hex(random_bytes(8));
if (!@mkdir($dir137real, 0700)) {
    fwrite(STDERR, "BLOCKED: cannot create private synthetic staging QA folder\n");
    exit(2);
}
define('SANRO_STUDIO_DTF_ARTIFACT_DIR', $dir137real);
register_shutdown_function(static function () use ($dir137real) {
    @chmod($dir137real, 0700);
    foreach (glob($dir137real . '/*') ?: [] as $f) {
        if (is_file($f) || is_link($f)) @unlink($f);
    }
    @rmdir($dir137real);
});
require __DIR__ . '/dtf-layer-compose-smoke.php';
require dirname(__DIR__) . '/includes/dtf-private-artifacts.php';
require dirname(__DIR__) . '/includes/dtf-order-workflow.php';

if (!extension_loaded('gd') || !function_exists('imagepng')
    || !function_exists('imagettftext')
    || !sanro_studio_dtf_private_font()) {
    fwrite(STDERR, "BLOCKED: GD/FreeType and private, trusted TTF are mandatory\n");
    exit(2);
}
$_SERVER['DOCUMENT_ROOT'] = realpath(ABSPATH);
$checks137real = 0;
function check137real($ok, $message) {
    global $checks137real;
    if (!$ok) {
        fwrite(STDERR, "FAIL: real DTF order lifecycle: {$message}\n");
        exit(1);
    }
    $checks137real++;
}
$order = fixture133();
$GLOBALS['image133'] = png133(60, 60);
$GLOBALS['sha133'] = hash('sha256', $GLOBALS['image133']);
$GLOBALS['record133']['design']['front'] = [
    'category' => 'A5',
    'objects' => [
        ['type'=>'image','x'=>2.0,'y'=>3.0,'width'=>0.5,'height'=>0.5],
        ['type'=>'text','x'=>3.0,'y'=>3.1,'width'=>1.0,'height'=>0.5],
    ],
    'text' => 'HELLO', 'font_size' => 22, 'text_color' => '#171717',
];
$GLOBALS['record133']['design']['back'] = [
    'category' => 'A5',
    'objects' => [
        ['type'=>'text','x'=>3.0,'y'=>4.0,'width'=>1.0,'height'=>0.5],
    ],
    'text' => 'BACK', 'font_size' => 22, 'text_color' => '#171717',
];
check137real(sanro_studio_dtf_order_batch_enabled()
    && sanro_studio_dtf_artifact_directory() === $dir137real,
    'staging-only private batch feature enabled');
// Real layer planner scans BOTH master/fonts before first GD composition.
$planned = sanro_studio_dtf_preflight_order_item_candidates($order, 71);
check137real(!empty($planned['valid'])
    && $planned['candidate_count'] === 2
    && $planned['sides']['front']['state'] === 'planned'
    && $planned['sides']['back']['state'] === 'planned',
    'real two-sided source/font/locked-draft planning succeeds read-only');
check137real(!isset($planned['sides']['front']['image'])
    && !isset($planned['sides']['front']['bytes'])
    && count(glob($dir137real . '/*.png') ?: []) === 0,
    'real preflight does not reveal image master or write output PNG');
$GLOBALS['record133']['design']['back']['text'] = "BACK 😀";
$badBack = sanro_studio_dtf_prepare_order_item_candidates($order, 71);
check137real(empty($badBack['valid']) && $badBack['partial'] === false
    && $badBack['failed_side'] === 'back'
    && $badBack['error'] === 'artifact_preflight_failed'
    && count(glob($dir137real . '/*.png') ?: []) === 0,
    'unsupported back text fails BEFORE any real front GD PNG is written');
$GLOBALS['record133']['design']['back']['text'] = 'BACK';
$batch = sanro_studio_dtf_prepare_order_item_candidates($order, 71);
check137real(!empty($batch['valid']) && $batch['complete'] === true
    && $batch['candidate_count'] === 2 && $batch['production_ready'] === false,
    'real GD render, immutable write and SHA readback succeed for both sides');
check137real($batch['sides']['front']['idempotent'] === false
    && $batch['sides']['back']['idempotent'] === false
    && $batch['sides']['front']['sha256'] !== '',
    'new private PNG candidates saved rather than skipped');
check137real($batch['sides']['front']['design_id'] === $batch['sides']['back']['design_id']
    && $batch['sides']['front']['item_id'] === 71
    && $batch['sides']['back']['order_id'] === 100,
    'both real rasters match the locked synthetic order and item');
foreach (['front', 'back'] as $side) {
    $binding = sanro_studio_dtf_artifact_binding($order, 71, $side);
    $base = $dir137real . '/' . sanro_studio_dtf_artifact_basename($binding);
    $file = $base . '.png';
    $manifest = $base . '.json';
    check137real(is_file($file) && is_file($manifest)
        && !is_link($file) && !is_link($manifest),
        "{$side} real PNG and private manifest exist");
    $bytes = @file_get_contents($file);
    check137real(is_string($bytes) && strlen($bytes) > 64
        && hash_equals(hash('sha256', $bytes), $batch['sides'][$side]['sha256'])
        && sanro_studio_dtf_png_has_300dpi($bytes),
        "{$side} real PNG has verified disk hash and 300-DPI metadata");
    $decoded = @imagecreatefromstring($bytes);
    check137real($decoded !== false && imagesx($decoded) === $batch['sides'][$side]['width_px']
        && imagesy($decoded) === $batch['sides'][$side]['height_px'],
        "{$side} real GD decoder confirms stored pixel dimensions");
    imagedestroy($decoded);
    $m = json_decode(file_get_contents($manifest), true);
    check137real(is_array($m) && $m['side'] === $side
        && $m['sha256'] === $batch['sides'][$side]['sha256']
        && $m['font_parity_approved'] === false
        && $m['quality_approved'] === false,
        "{$side} private manifest remains explicitly unapproved");
}
$readback = sanro_studio_dtf_order_item_private_status($order, 71);
check137real(!empty($readback['valid']) && $readback['complete'] === true
    && $readback['candidate_count'] === 2,
    'admin-safe internal readback confirms both real side files');
$repeat = sanro_studio_dtf_prepare_order_item_candidates($order, 71);
check137real(!empty($repeat['valid'])
    && $repeat['sides']['front']['idempotent'] === true
    && $repeat['sides']['back']['idempotent'] === true,
    'identical real GD outputs are idempotent without overwrite');
check137real(!isset($readback['sides']['front']['bytes'])
    && !isset($readback['sides']['front']['url'])
    && !isset($readback['sides']['front']['path']),
    'readback exposes no private artwork bytes, path or public URL');
echo "DTF_ORDER_REAL: PASS (front mixed image/text + back text-only; persisted, decoded, SHA verified)\n";
echo "PASS: {$checks137real} SANRO real GD two-sided DTF order lifecycle checks\n";
