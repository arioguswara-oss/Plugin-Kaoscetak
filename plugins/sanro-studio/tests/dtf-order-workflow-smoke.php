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
$GLOBALS['preflightFail137'] = '';
$GLOBALS['preflightWrongSide137'] = '';
$GLOBALS['preflightWrongSkip137'] = '';
$GLOBALS['preflightApprove137'] = '';
$GLOBALS['renderCalls137'] = 0;
$GLOBALS['mutatePlan137'] = '';
$GLOBALS['mutateRender137'] = '';
// Simulates the real layer planner's private-master/font/geometry checks but
// never generates PNG or writes any files. The renderer remains a distinct stub.
function sanro_studio_dtf_layer_plan($order, $item_id, $side) {
    if ($GLOBALS['preflightFail137'] === $side) {
        return ['valid' => false, 'error' => 'synthetic_master_or_font_unavailable'];
    }
    if ($GLOBALS['preflightWrongSkip137'] === $side) {
        return ['valid' => true, 'skipped' => true, 'reason' => 'fake_blank'];
    }
    $binding = sanro_studio_dtf_artifact_binding($order, $item_id, $side);
    if (empty($binding['valid']) || !empty($binding['skipped'])) return $binding;
    $g = sanro_studio_dtf_artifact_locked_geometry($binding);
    if (!$g) return ['valid' => false, 'error' => 'synthetic_geometry_invalid'];
    $result = $binding + [
        'source_fingerprint'=>sanro_studio_artifact_source_fingerprint($GLOBALS['record133'],$order->get_id(),$item_id,$binding['design_id'],$side),
        'kind' => 'layered_dtf_raster_candidate',
        'quality_approved' => false, 'font_parity_approved' => false,
        'dpi' => 300, 'width_px' => 12, 'height_px' => 12,
        'private_master_bytes' => 'PRIVATE_DO_NOT_RETURN',
        'owner_token' => 'SECRET_DO_NOT_RETURN',
    ];
    if ($GLOBALS['preflightWrongSide137'] === $side) $result['side'] = 'other';
    if ($GLOBALS['preflightApprove137'] === $side) $result['quality_approved'] = true;
    if ($GLOBALS['mutatePlan137'] === $side) {
        // Emulate a concurrent buyer-draft mutation DURING side preflight.
        $GLOBALS['record133']['design']['front']['objects'][0]['x'] += 0.01;
    }
    return $result;
}
function sanro_studio_dtf_render_layer_side($order, $item_id, $side) {
    $GLOBALS['renderCalls137']++;
    if ($GLOBALS['mutateRender137'] === $side) {
        // A draft edit during the slow GD phase must never be persisted.
        $GLOBALS['record133']['design']['back']['objects'][0]['x'] += 0.01;
    }
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
        'source_fingerprint'=>sanro_studio_artifact_source_fingerprint($GLOBALS['record133'],$order->get_id(),$item_id,$binding['design_id'],$side),
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
$plan = sanro_studio_dtf_preflight_order_item_candidates($order, 71);
check137(!empty($plan['valid']) && $plan['candidate_count'] === 2
    && $plan['sides']['front']['state'] === 'planned'
    && $plan['sides']['back']['state'] === 'planned',
    'both locked sides planned safely before any render');
check137(!isset($plan['private_master_bytes']) && !isset($plan['owner_token'])
    && !isset($plan['sides']['front']['owner_token'])
    && !isset($plan['sides']['back']['private_master_bytes']),
    'preflight discards private master content and draft credentials');
check137($GLOBALS['renderCalls137'] === 0
    && count(glob($dir137 . '/*.png') ?: []) === 0,
    'read-only two-sided preflight creates no PNG and performs no render');
$GLOBALS['preflightFail137'] = 'back';
$blocked = sanro_studio_dtf_preflight_order_item_candidates($order, 71);
check137(empty($blocked['valid'])
    && $blocked['error'] === 'artifact_preflight_failed'
    && $blocked['failed_side'] === 'back'
    && $blocked['partial'] === false,
    'missing back master/font fails preflight without partial result');
$blockedBatch = sanro_studio_dtf_prepare_order_item_candidates($order, 71);
check137(empty($blockedBatch['valid']) && $blockedBatch['partial'] === false
    && $blockedBatch['failed_side'] === 'back'
    && $GLOBALS['renderCalls137'] === 0
    && count(glob($dir137 . '/*.png') ?: []) === 0,
    'back preflight failure prevents even valid front-side file creation');
$GLOBALS['preflightFail137'] = '';
$GLOBALS['preflightWrongSide137'] = 'back';
$wrongSide = sanro_studio_dtf_prepare_order_item_candidates($order, 71);
check137(empty($wrongSide['valid'])
    && $wrongSide['error'] === 'artifact_preflight_mismatch'
    && $wrongSide['failed_side'] === 'back'
    && $wrongSide['partial'] === false
    && $GLOBALS['renderCalls137'] === 0,
    'wrong-side planner result refused before private writes');
$GLOBALS['preflightWrongSide137'] = '';
$GLOBALS['preflightWrongSkip137'] = 'back';
$fakeBlank = sanro_studio_dtf_prepare_order_item_candidates($order, 71);
check137(empty($fakeBlank['valid'])
    && $fakeBlank['error'] === 'artifact_preflight_failed'
    && $fakeBlank['failed_side'] === 'back'
    && $GLOBALS['renderCalls137'] === 0,
    'planner cannot silently drop a locked printable back side');
$GLOBALS['preflightWrongSkip137'] = '';
$GLOBALS['preflightApprove137'] = 'front';
$fakeApproval = sanro_studio_dtf_prepare_order_item_candidates($order, 71);
check137(empty($fakeApproval['valid'])
    && $fakeApproval['error'] === 'artifact_preflight_mismatch'
    && $fakeApproval['failed_side'] === 'front'
    && $GLOBALS['renderCalls137'] === 0,
    'preflight cannot claim artwork print quality approved');
$GLOBALS['preflightApprove137'] = '';
// Retain the exact original JSON-serializable draft: floating-point
// increment/decrement is not a lossless rollback for SHA-256 fingerprints.
$baselineDraft137 = $GLOBALS['record133'];
$fixedFingerprint = sanro_studio_dtf_locked_order_item_fingerprint($order, 71);
check137(is_string($fixedFingerprint) && strlen($fixedFingerprint) === 64
    && sanro_studio_dtf_locked_snapshot_matches($order, 71, $fixedFingerprint),
    'private SHA256 fingerprint captures paid locked order/item design');
check137(!isset($plan['fingerprint']) && !isset($plan['snapshot'])
    && !isset($plan['sides']['front']['fingerprint']),
    'preflight never returns the private draft fingerprint');
$GLOBALS['mutatePlan137'] = 'back';
$changedDuringPlan = sanro_studio_dtf_prepare_order_item_candidates($order, 71);
check137(empty($changedDuringPlan['valid'])
    && $changedDuringPlan['error'] === 'artifact_snapshot_changed'
    && $changedDuringPlan['partial'] === false
    && $GLOBALS['renderCalls137'] === 0
    && count(glob($dir137 . '/*.png') ?: []) === 0,
    'draft mutation during back preflight rejects before PNG rendering');
$GLOBALS['mutatePlan137'] = '';
$GLOBALS['record133'] = $baselineDraft137;
$GLOBALS['mutateRender137'] = 'front';
$changedDuringFront = sanro_studio_dtf_prepare_order_item_candidates($order, 71);
check137(empty($changedDuringFront['valid'])
    && $changedDuringFront['error'] === 'artifact_snapshot_changed'
    && $changedDuringFront['failed_side'] === 'front'
    && $changedDuringFront['partial'] === false
    && count(glob($dir137 . '/*.png') ?: []) === 0,
    'front renderer draft change rejected before first private commit');
$GLOBALS['mutateRender137'] = '';
$GLOBALS['record133'] = $baselineDraft137;
$GLOBALS['mutateRender137'] = 'back';
$changedDuringBack = sanro_studio_dtf_prepare_order_item_candidates($order, 71);
check137(empty($changedDuringBack['valid'])
    && $changedDuringBack['error'] === 'artifact_snapshot_changed'
    && $changedDuringBack['failed_side'] === 'back'
    && $changedDuringBack['partial'] === true
    && count(glob($dir137 . '/*.png') ?: []) === 1,
    'after verified front side, back-draft mutation fails partial, never complete');
$GLOBALS['mutateRender137'] = '';
$GLOBALS['record133'] = $baselineDraft137;
foreach (glob($dir137 . '/*') ?: [] as $file) {
    if (is_file($file) || is_link($file)) @unlink($file);
}
check137(count(glob($dir137 . '/*') ?: []) === 0
    && sanro_studio_dtf_locked_snapshot_matches($order, 71, $fixedFingerprint),
    'QA mutation fixtures restored and removed without affecting clean baseline');
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
$beforeRefs=$GLOBALS['record133']['master_refs']??null;$GLOBALS['record133']['master_refs']['front']='different-master-same-geometry';
check137(empty(sanro_studio_dtf_verify_private_artifact($order,71,'front')['valid']),'same geometry cannot reuse a different master provenance');
if($beforeRefs===null)unset($GLOBALS['record133']['master_refs']);else $GLOBALS['record133']['master_refs']=$beforeRefs;
$callsBefore=$GLOBALS['renderCalls137'];
$GLOBALS['renderVariant137'] = true;
$conflict = sanro_studio_dtf_prepare_order_item_candidates($order, 71);
check137(!empty($conflict['valid'])&&$conflict['sides']['front']['idempotent']===true,'verified existing source is reused without generating variant output');
check137($GLOBALS['renderCalls137']===$callsBefore,'verified two-sided retry performs zero renders');
$GLOBALS['renderVariant137'] = false;
@unlink($b.'.png');@unlink($b.'.json');
$GLOBALS['renderFail137'] = 'back';
$partial = sanro_studio_dtf_prepare_order_item_candidates($order, 71);
check137(empty($partial['valid']) && $partial['partial'] === true
    && ($partial['failed_side'] ?? null) === 'back'
    && ($partial['error'] ?? null) === 'synthetic_renderer_unavailable'
    && $partial['sides']['front']['state'] === 'candidate',
    'back-side error produces explicit partial result, no false complete');
check137(!empty(sanro_studio_dtf_verify_private_artifact($order, 71, 'front')['valid'])
    && empty(sanro_studio_dtf_verify_private_artifact($order, 71, 'back')['valid']),
    'missing back is not fabricated; existing front remains valid');
@unlink($f.'.png');@unlink($f.'.json');
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
$singlePlan = sanro_studio_dtf_preflight_order_item_candidates($order, 71);
check137(!empty($singlePlan['valid']) && $singlePlan['candidate_count'] === 1
    && $singlePlan['sides']['back']['state'] === 'blank'
    && $singlePlan['sides']['front']['state'] === 'planned',
    'blank back remains blank during preflight, never becomes a fake candidate');
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
sanro_studio_dtf_persist_order_item_side($order,71,'back');
file_put_contents($b . '.png', 'CORRUPTED');
clearstatcache(true);
$corrupt = sanro_studio_dtf_order_item_private_status($order, 71);
check137(empty($corrupt['valid']) && $corrupt['partial'] === true
    && ($corrupt['failed_side'] ?? null) === 'back'
    && ($corrupt['error'] ?? null) === 'artifact_integrity_failed',
    'read-only order status rejects damaged back file');
echo "PASS: {$checks137} SANRO order-item DTF batch and private status checks\n";
