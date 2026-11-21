<?php
/** Sprint 136 synthetic filesystem QA — no WordPress, real orders or GD. */
define('SANRO_STUDIO_ENABLE_DTF_PRIVATE_ARTIFACTS', true);
$dir136 = sys_get_temp_dir() . '/sanro-dtf-private-qa-' . bin2hex(random_bytes(8));
if (!@mkdir($dir136, 0700)) {
    fwrite(STDERR, "FAIL: cannot create isolated private QA dir\n"); exit(2);
}
define('SANRO_STUDIO_DTF_ARTIFACT_DIR', $dir136);
register_shutdown_function(static function () use ($dir136) {
    @chmod($dir136, 0700);
    foreach (glob($dir136 . '/*') ?: [] as $file) {
        if (is_file($file) || is_link($file)) @unlink($file);
    }
    @rmdir($dir136);
});
require __DIR__ . '/dtf-layer-compose-smoke.php';
require dirname(__DIR__) . '/includes/dtf-private-artifacts.php';
$_SERVER['DOCUMENT_ROOT'] = realpath(ABSPATH);
clearstatcache();

$checks136 = 0;
function check136($value, $description) {
    global $checks136;
    if (!$value) {
        fwrite(STDERR, "FAIL: private DTF artifact: {$description}\n");
        exit(1);
    }
    $checks136++;
}
function candidate136($side) {
    $png = png133(12, 12);
    // Embed 300 DPI / pHYs BEFORE IDAT, using valid PNG chunk CRC.
    $png = substr($png, 0, 33)
        . chunk133('pHYs', pack('NNC', 11811, 11811, 1))
        . substr($png, 33);
    return [
        'valid' => true, 'skipped' => false,
        'kind' => 'layered_dtf_raster_candidate',
        'quality_approved' => false, 'font_parity_approved' => false,
        'order_id' => 100, 'item_id' => 71,
        'design_id' => '123e4567-e89b-42d3-a456-123456789012',
        'side' => $side, 'dpi' => 300,
        'width_px' => 12, 'height_px' => 12, 'mime' => 'image/png',
        'width_cm' => 0.1, 'height_cm' => 0.1,
        'offset_x_cm' => $side === 'front' ? 2.0 : 3.0,
        'offset_y_cm' => $side === 'front' ? 3.0 : 4.0,
        'bytes' => $png, 'sha256' => hash('sha256', $png),
    ];
}
$order = fixture133();
check136(sanro_studio_dtf_artifacts_enabled(), 'explicit storage flag plus renderer gate');
check136(sanro_studio_dtf_artifact_directory() === $dir136,
    'owner-only outside-webroot directory');
check136(sanro_studio_dtf_png_has_300dpi(candidate136('front')['bytes']),
    'synthetic RGBA PNG contains valid 300 DPI metadata');
$front = candidate136('front');
$saved = sanro_studio_dtf_commit_private_candidate($order, 71, 'front', $front);
check136(!empty($saved['valid']) && ($saved['idempotent'] ?? null) === false,
    'first front commit persists private PNG and manifest');
check136(!isset($saved['bytes_raw']) && !isset($saved['path'])
    && !isset($saved['url']) && !isset($saved['bytes_content']),
    'result contains no raw artwork, private filesystem path or URL');
$verified = sanro_studio_dtf_verify_private_artifact($order, 71, 'front');
check136(!empty($verified['valid']) && ($verified['side'] ?? '') === 'front'
    && hash_equals($verified['sha256'], $front['sha256']),
    'fresh readback validates order-bound hash and side');
check136(($verified['private'] ?? false) === true
    && ($verified['quality_approved'] ?? null) === false
    && ($verified['font_parity_approved'] ?? null) === false,
    'stored candidate never claims production approval');
$repeated = sanro_studio_dtf_commit_private_candidate($order, 71, 'front', $front);
check136(!empty($repeated['valid']) && ($repeated['idempotent'] ?? null) === true,
    'repeated matching writes are idempotent');
$conflict = $front;
// Change only the physical-density metadata, recomputing pHYs CRC. This is a
// different, still-valid ~300 DPI PNG, and MUST NOT replace an existing file.
$conflict['bytes'] = str_replace(
    chunk133('pHYs', pack('NNC', 11811, 11811, 1)),
    chunk133('pHYs', pack('NNC', 11812, 11811, 1)),
    $front['bytes']
);
$conflict['sha256'] = hash('sha256', $conflict['bytes']);
check136(sanro_studio_dtf_commit_private_candidate($order, 71, 'front', $conflict)['error']
    === 'artifact_conflict', 'different valid render cannot overwrite locked candidate');
$bad = $front; $bad['sha256'] = str_repeat('f', 64);
check136(sanro_studio_dtf_commit_private_candidate($order, 71, 'front', $bad)['error']
    === 'artifact_candidate_invalid', 'mismatched candidate SHA rejected');
$bad = $front; $bad['quality_approved'] = true;
check136(sanro_studio_dtf_commit_private_candidate($order, 71, 'front', $bad)['error']
    === 'artifact_candidate_invalid', 'false production-ready claim rejected');
$bad = $front; $bad['order_id'] = 101;
check136(sanro_studio_dtf_commit_private_candidate($order, 71, 'front', $bad)['error']
    === 'artifact_candidate_invalid', 'candidate cross-order binding rejected');
$bad = $front; $bad['item_id'] = 72;
check136(sanro_studio_dtf_commit_private_candidate($order, 71, 'front', $bad)['error']
    === 'artifact_candidate_invalid', 'candidate cross-item binding rejected');
$order->paid = false;
check136(sanro_studio_dtf_commit_private_candidate($order, 71, 'front', $front)['error']
    === 'artifact_order_ineligible', 'unpaid/not-ready order denied');
$order->paid = true;
$order->id = 101;
check136(sanro_studio_dtf_verify_private_artifact($order, 71, 'front')['error']
    === 'artifact_draft_binding_missing', 'cross-order readback denied');
$order = fixture133();
$GLOBALS['record133']['status'] = 'draft';
check136(sanro_studio_dtf_verify_private_artifact($order, 71, 'front')['error']
    === 'artifact_draft_binding_missing', 'unlocked draft read denied');
$order = fixture133();
check136(!empty(sanro_studio_dtf_artifact_binding($order, 71, 'back')['skipped']),
    'blank back is skipped without fabricating PNG');
$GLOBALS['record133']['design']['back'] = [
    'category' => 'A5', 'objects' => [
        ['type'=>'image','x'=>3.0,'y'=>4.0,'width'=>0.1,'height'=>0.1],
    ],
];
$back = candidate136('back');
$back_saved = sanro_studio_dtf_commit_private_candidate($order, 71, 'back', $back);
check136(!empty($back_saved['valid']) && $back_saved['side'] === 'back',
    'back candidate stored independently from front');
check136(!empty(sanro_studio_dtf_verify_private_artifact($order,71,'front')['valid'])
    && !empty(sanro_studio_dtf_verify_private_artifact($order,71,'back')['valid']),
    'front and back both verify after second side commit');
$binding = sanro_studio_dtf_artifact_binding($order, 71, 'front');
$base = $dir136 . '/' . sanro_studio_dtf_artifact_basename($binding);
check136(is_file($base . '.png') && is_file($base . '.json')
    && !is_link($base . '.png') && !is_link($base . '.json'),
    'private files use deterministic per-order names, not public media');
$meta = json_decode(file_get_contents($base . '.json'), true);
check136(is_array($meta) && $meta['side'] === 'front'
    && $meta['order_id'] === 100 && $meta['item_id'] === 71
    && $meta['design_id'] === $front['design_id']
    && $meta['sha256'] === $front['sha256']
    && $meta['width_cm'] === 0.1 && $meta['height_cm'] === 0.1
    && $meta['offset_x_cm'] === 2 && $meta['offset_y_cm'] === 3,
    'manifest binds order, item, design, side and digest');
@chmod($dir136, 0755);
clearstatcache(true);
check136(sanro_studio_dtf_verify_private_artifact($order, 71, 'front')['error']
    === 'artifact_directory_unsafe', 'world-readable directory denied');
@chmod($dir136, 0700);
clearstatcache(true);
check136(!empty(sanro_studio_dtf_verify_private_artifact($order, 71, 'front')['valid']),
    'readback restored after owner-only permissions');
file_put_contents($base . '.png', 'CORRUPTED');
clearstatcache(true);
check136(sanro_studio_dtf_verify_private_artifact($order, 71, 'front')['error']
    === 'artifact_integrity_failed', 'tampered PNG fails closed');
check136(sanro_studio_dtf_commit_private_candidate($order, 71, 'front', $front)['error']
    === 'artifact_integrity_failed', 'tampered existing artwork never silently overwritten');
check136(!isset(sanro_studio_dtf_verify_private_artifact($order,71,'front')['path']),
    'verification never exposes filesystem paths');
echo "PASS: {$checks136} SANRO order-bound private DTF artifact checks\n";
