<?php
/**
 * Sprint 149: isolated two-side preflight fail-closed policy.
 * No WordPress, customer/order lookup, font, PNG, GD, or file writes.
 */
define('ABSPATH', __DIR__ . '/');
define('SANRO_STUDIO_ENABLE_DTF_ORDER_BATCH', true);
function sanro_studio_dtf_artifacts_enabled() { return true; }
require dirname(__DIR__) . '/includes/dtf-order-workflow.php';
$check149_count = 0;
function check149($okay, $label) {
    global $check149_count;
    if (!$okay) {
        fwrite(STDERR, "FAIL: preflight contract: $label\n");
        exit(1);
    }
    $check149_count++;
}
check149(sanro_studio_dtf_order_batch_enabled(),
    'explicit isolated batch toggle recognized');
$missing = sanro_studio_dtf_preflight_order_item_candidates(null, 71);
check149($missing['error'] === 'artifact_preflight_unavailable',
    'missing layer planner/artifact reader fails closed');
check149(!isset($missing['sides']) && !isset($missing['bytes']),
    'unavailable preflight returns no artwork or source data');
$worker = sanro_studio_dtf_prepare_order_item_candidates(null, 71);
check149($worker['error'] === 'artifact_worker_unavailable',
    'no private writer or reader means no batch execution');
$source = file_get_contents(dirname(__DIR__) . '/includes/dtf-order-workflow.php');
check149(is_string($source), 'worker source available for security contract');
$pre = strpos($source, 'function sanro_studio_dtf_preflight_order_item_candidates(');
$batch = strpos($source, 'function sanro_studio_dtf_prepare_order_item_candidates(');
check149($pre !== false && $batch !== false && $pre < $batch,
    'two-side preflight defined before batch worker');
$workerBody = substr($source, $batch);
$checkPoint = strpos($workerBody, '$preflight = sanro_studio_dtf_preflight_order_item_candidates(');
$commit = strpos($workerBody, 'sanro_studio_dtf_persist_order_item_side($order, $item_id, $side, $snapshot)');
check149($checkPoint !== false && $commit !== false && $checkPoint < $commit,
    'batch MUST preflight both sides before first PNG persistence');
$preflightBody = substr($source, $pre, $batch - $pre);
check149(strpos($preflightBody, "['front', 'back']") !== false
    && strpos($preflightBody, 'sanro_studio_dtf_layer_plan(') !== false,
    'both locked sides use actual layer planner');
check149(strpos($preflightBody, 'sanro_studio_dtf_artifact_binding(') !== false
    && strpos($preflightBody, 'hash_equals(') !== false,
    'planner identity cross-checked against immutable binding');
check149(strpos($preflightBody, 'artifact_preflight_failed') !== false
    && strpos($preflightBody, 'artifact_preflight_mismatch') !== false,
    'font/master failure and identity mismatch fail closed');
check149(strpos($preflightBody, "'partial' => false") !== false
    && strpos($preflightBody, "'production_ready' => false") !== false
    && strpos($preflightBody, "'quality_approved' => false") !== false,
    'preflight can never claim partial committed or print-approved files');
check149(!preg_match('/(?:fopen|file_put_contents|imagepng|wp_remote_post|update_option)\s*\(/', $preflightBody),
    'preflight does not render, persist or modify WordPress');
echo 'PASS: ' . $check149_count . " SANRO two-side no-write preflight policy checks\n";
