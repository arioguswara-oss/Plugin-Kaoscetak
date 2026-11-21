<?php
/**
 * Sprint 137 synthetic two-sided render -> persist -> readback regression.
 * No WordPress DB, public route, GD, cron, Drive or live order.
 */
define('SANRO_STUDIO_ENABLE_DTF_PRIVATE_ARTIFACTS', true);
define('SANRO_STUDIO_ENABLE_DTF_ORDER_BATCH', true);
$dir137 = sys_get_temp_dir() . '/sanro-dtf-batch-qa-' . bin2hex(random_bytes(8));
if (!@mkdir($dir137, 0700)) {
    fwrite(STDERR, "FAIL: unable to make private synthetic QA directory\n"); exit(2);
}
define('SANRO_STUDIO_DTF_ARTIFACT_DIR', $dir137);
register_shutdown_function(static function () use ($dir137) {
    @chmod($dir137, 0700);
    foreach (glob($dir137 . '/*') ?: [] as $entry) {
        if (is_file($entry) || is_link($entry)) @unlink($entry);
    }
    @rmdir($dir137);
});
require __DIR__ . '/dtf-image-render-smoke.php';
$_SERVER['DOCUMENT_ROOT'] = realpath(ABSPATH);

// Only the renderer is stubbed. The private production file writer and
// reader are REAL; this verifies the orchestration without PHP GD on Hermes.
function sanro_studio_dtf_layer_enabled() { return true; }
$GLOBALS['renderFail137'] = '';
$GLOBALS['renderVariant137'] = false;
function sanro_studio_dtf_render_layer_side($order, $item_id, $side) {
    if ($GLOBALS['renderFail137'] === $side) {
        return ['valid' => false, 'error' => 'synthetic_renderer_unavailable'];
    }
    $binding = sanro_studio_dtf_artifact_binding($order, $item_id, $side);
    if (empty($binding['valid']) || !empty($binding['skipped'])) return $binding;
    $g = sanro_studio_dtf_artifact_locked_geometry($binding);
    if (!$g) return ['valid' => false, 'error' => 'synthetic_geometry_error'];
    $png = png133(12, 12);
    $density = !empty($GLOBALS['renderVariant137']) ? 11812 : 11811;
    $png = substr($png, 0, 33)
        . chunk133('pHYs', pack('NNC', $density, 11811, 1))
        . substr($png, 33);
    return $binding + $g + [
        'kind' => 'layered_dtf_raster_candidate',
        'quality_approved' => false, 'font_parity_approved' => false,
        'dpi' => 300, 'mime' => 'image/png',
        'width_px' => 12, 'height_px' => 12,
        'bytes' => $png, 'sha256' => hash('sha256', $png),
    ];
}
require dirname(__DIR__) . '/includes/dtf-private-artifacts.php';
require dirname(__DIR__) . '/includes/dtf-order-workflow.php';
$checks137 = 0;
function check137($ok, $description) {
    global $checks137;
    if (!$ok) {
        fwrite(STDERR, "FAIL: DTF order batch: {$description}\n");
        exit(1);
    }
    $checks137++;
}

$order = fixture133();
$GLOBALS['record133']['design']['back'] = [
    'category' => 'A5', 'objects' => [
        ['type'=>'image','x'=>3.0,'y'=>4.0,'width'=>0.1,'height'=>0.1],
    ],
];
check137(sanro_studio_dtf_order_batch_enabled(), 'explicit internal batch gate enabled');
$missing = sanro_studio_dtf_order_item_private_status($order, 71);
check137(empty($missing['valid']) && ($missing['failed_side'] ?? null) === 'front'
    && ($missing['error'] ?? null) === 'artifact_missing_or_incomplete',
    'not-yet-rendered order status cannot claim ready');
$first = sanro_studio_dtf_prepare_order_item_candidates($order, 71);
check137(!empty($first['valid']) && $first['complete'] === true
    && $first['candidate_count'] === 2 && $first['production_ready'] === false,
    'both sides render and persist, never production ready');
check137(($first['sides']['front']['state'] ?? null) === 'candidate'
    && ($first['sides']['back']['state'] ?? null) === 'candidate',
    'front and back results independently bound');
check137(($first['sides']['front']['idempotent'] ?? null) === false
    && ($first['sides']['back']['idempotent'] ?? null) === false,
    'first persistence is not reported as duplicate');
check137(!isset($first['sides']['front']['bytes'])
    && !isset($first['sides']['front']['path'])
    && !isset($first['sides']['front']['url'])
    && !isset($first['sides']['front']['owner_token']),
    'side output is allowlisted metadata without private PNG bytes or path');
$front = sanro_studio_dtf_artifact_binding($order, 71, 'front');
$back = sanro_studio_dtf_artifact_binding($order, 71, 'back');
$f = $dir137 . '/' . sanro_studio_dtf_artifact_basename($front);
$b = $dir137 . '/' . sanro_studio_dtf_artifact_basename($back);
check137(is_file($f . '.png') && is_file($f . '.json')
    && is_file($b . '.png') && is_file($b . '.json'),
    'separate private files exist for both sides');
check137(hash_equals(hash_file('sha256', $f . '.png'), $first['sides']['front']['sha256'])
    && hash_equals(hash_file('sha256', $b . '.png'), $first['sides']['back']['sha256']),
    'both persisted PNG hashes match the returned safe metadata');
$status = sanro_studio_dtf_order_item_private_status($order, 71);
check137(!empty($status['valid']) && $status['complete'] === true
    && $status['candidate_count'] === 2 && $status['production_ready'] === false,
    'admin-safe status readback verifies both stored PNG hashes');
check137(($status['sides']['front']['order_id'] ?? null) === 100
    && ($status['sides']['front']['item_id'] ?? null) === 71
    && ($status['sides']['back']['side'] ?? null) === 'back',
    'readback retains order, line item and side identity');
$repeat = sanro_studio_dtf_prepare_order_item_candidates($order, 71);
check137(!empty($repeat['valid'])
    && $repeat['sides']['front']['idempotent'] === true
    && $repeat['sides']['back']['idempotent'] === true,
    'rerunning same render is idempotent on both sides');
$GLOBALS['renderVariant137'] = true;
$conflict = sanro_studio_dtf_prepare_order_item_candidates($order, 71);
check137(empty($conflict['valid']) && ($conflict['complete'] ?? false) !== true
    && ($conflict['error'] ?? '') === 'artifact_conflict'
    && ($conflict['failed_side'] ?? '') === 'front'
    && $conflict['partial'] === false,
    'conflicting new render cannot replace front candidate');
$GLOBALS['renderVariant137'] = false;
$GLOBALS['renderFail137'] = 'back';
$partial = sanro_studio_dtf_prepare_order_item_candidates($order, 71);
check137(empty($partial['valid']) && $partial['partial'] === true
    && ($partial['failed_side'] ?? null) === 'back'
    && ($partial['error'] ?? null) === 'synthetic_renderer_unavailable'
    && $partial['sides']['front']['state'] === 'candidate',
    'back-side error produces explicit partial result, no false complete');
check137(!empty(sanro_studio_dtf_verify_private_artifact($order, 71, 'front')['valid'])
    && !empty(sanro_studio_dtf_verify_private_artifact($order, 71, 'back')['valid']),
    'failed rerun preserves existing valid artwork');
$GLOBALS['renderFail137'] = 'front';
$failed = sanro_studio_dtf_prepare_order_item_candidates($order, 71);
check137(empty($failed['valid']) && $failed['partial'] === false
    && ($failed['failed_side'] ?? null) === 'front',
    'front failure never claims partial committed side');
$GLOBALS['renderFail137'] = '';
$order->paid = false;
check137((sanro_studio_dtf_prepare_order_item_candidates($order, 71)['error'] ?? null)
    === 'artifact_order_ineligible', 'unpaid batch is denied');
$order->paid = true;
$order->id = 101;
check137((sanro_studio_dtf_order_item_private_status($order, 71)['error'] ?? null)
    === 'artifact_draft_binding_missing', 'cross-order read is denied');
$order = fixture133();
$GLOBALS['record133']['status'] = 'draft';
check137((sanro_studio_dtf_prepare_order_item_candidates($order, 71)['error'] ?? null)
    === 'artifact_draft_binding_missing', 'unlocked draft cannot be exported');
$order = fixture133();
check137(!empty(sanro_studio_dtf_artifact_binding($order, 71, 'back')['skipped']),
    'blank back is recognized as skipped');
$single = sanro_studio_dtf_prepare_order_item_candidates($order, 71);
check137(!empty($single['valid']) && $single['candidate_count'] === 1
    && $single['sides']['back']['state'] === 'blank',
    'blank side not fabricated and image side remains verified');
$single_status = sanro_studio_dtf_order_item_private_status($order, 71);
check137(!empty($single_status['valid']) && $single_status['candidate_count'] === 1
    && $single_status['sides']['back']['state'] === 'blank',
    'read-only status preserves blank-side semantics');
// A completely empty locked design is rejected by the existing product
// print-design validator as empty_design BEFORE artifact binding or batching.
// Do not loosen that central gate merely to reach the downstream
// artifact_no_printable_sides defense-in-depth check.
$GLOBALS['record133']['design']['front']['objects'] = [];
$validation = sanro_studio_validate_product_design(
    42, $GLOBALS['record133']['design']);
check137(empty($validation['valid']) && ($validation['error'] ?? null) === 'empty_design',
    'central product validator rejects an order with two blank sides');
$empty = sanro_studio_dtf_prepare_order_item_candidates($order, 71);
check137(empty($empty['valid']) && ($empty['complete'] ?? false) === false
    && ($empty['error'] ?? null) === 'artifact_draft_binding_missing'
    && !isset($empty['sides']['front']['sha256']),
    'empty locked order fails at design binding; no completed batch or artwork');
$empty_status = sanro_studio_dtf_order_item_private_status($order, 71);
check137(empty($empty_status['valid'])
    && ($empty_status['complete'] ?? false) === false
    && ($empty_status['error'] ?? null) === 'artifact_draft_binding_missing'
    && !isset($empty_status['sides']['front']['sha256']),
    'read-only status rejects empty design before verifying any private file');
$GLOBALS['record133']['design']['front']['objects'] = [
    ['type'=>'image','x'=>2.0,'y'=>3.0,'width'=>0.1,'height'=>0.1],
];
$GLOBALS['record133']['design']['back'] = [
    'category' => 'A5', 'objects' => [
        ['type'=>'image','x'=>3.0,'y'=>4.0,'width'=>0.1,'height'=>0.1],
    ],
];
file_put_contents($b . '.png', 'CORRUPTED');
clearstatcache(true);
$corrupt = sanro_studio_dtf_order_item_private_status($order, 71);
check137(empty($corrupt['valid']) && $corrupt['partial'] === true
    && ($corrupt['failed_side'] ?? null) === 'back'
    && ($corrupt['error'] ?? null) === 'artifact_integrity_failed',
    'read-only order status rejects damaged back file');
echo "PASS: {$checks137} SANRO order-item DTF batch and private status checks\n";
