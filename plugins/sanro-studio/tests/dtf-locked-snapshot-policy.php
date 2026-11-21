<?php
/**
 * Sprint 150 — source-only locked-draft fingerprint integration policy.
 * No WordPress runtime, no database lookup, no candidate PNG or Drive writes.
 */
$root = dirname(__DIR__);
$workflow = file_get_contents($root . '/includes/dtf-order-workflow.php');
$artifacts = file_get_contents($root . '/includes/dtf-private-artifacts.php');
$fixture = file_get_contents(__DIR__ . '/dtf-order-workflow-real-smoke.php');
if (!is_string($workflow) || !is_string($artifacts) || !is_string($fixture)) {
    fwrite(STDERR, "FAIL: locked snapshot source unreadable\n");
    exit(2);
}
$count150 = 0;
function snap150($valid, $label) {
    global $count150;
    if (!$valid) {
        fwrite(STDERR, "FAIL: DTF snapshot policy: $label\n");
        exit(1);
    }
    $count150++;
}
snap150(strpos($workflow, 'function sanro_studio_dtf_locked_order_item_fingerprint(') !== false,
    'private order/item fingerprint implementation exists');
snap150(strpos($workflow, "($record['status'] ?? null) !== 'locked'") !== false
    && strpos($workflow, 'sanro_studio_locked_record_has_item($record, $item_id)') !== false,
    'only locked correct order/product/item draft can be fingerprinted');
snap150(strpos($workflow, "'sanro_studio_draft_' . $design_id") !== false
    && strpos($workflow, 'JSON_PRESERVE_ZERO_FRACTION') !== false,
    'full immutable draft record including text and master metadata is covered');
snap150(strpos($workflow, 'function sanro_studio_dtf_locked_snapshot_matches(') !== false
    && strpos($workflow, 'hash_equals($expected, $current)') !== false,
    'sha256 fingerprint compared without ordinary equals');
snap150(strpos($workflow, 'return hash(\'sha256\', $json);') !== false
    && strpos($workflow, 'strlen($json) > 1048576') !== false,
    'private fingerprint size bound and hash format');
snap150(strpos($workflow, "'artifact_snapshot_changed'") !== false
    && strpos($workflow, "'partial' => false") !== false,
    'preflight drift fails closed before output');
$batch = strpos($workflow, 'function sanro_studio_dtf_prepare_order_item_candidates(');
$start = substr($workflow, $batch);
$prepare = strpos($start, '$snapshot = sanro_studio_dtf_locked_order_item_fingerprint(');
$preflight = strpos($start, '$preflight = sanro_studio_dtf_preflight_order_item_candidates(');
$persist = strpos($start, 'sanro_studio_dtf_persist_order_item_side($order, $item_id, $side, $snapshot)');
snap150($batch !== false && $prepare !== false && $preflight !== false
    && $persist !== false && $prepare < $preflight && $preflight < $persist,
    'fingerprint frozen before two-side preflight and passed to side writer');
snap150(substr_count($start, 'sanro_studio_dtf_locked_snapshot_matches(') >= 3,
    'snapshot rechecked on batch entry, per-side and final readback');
snap150(strpos($artifacts, 'function sanro_studio_dtf_persist_order_item_side($order, $item_id, $side, $expected_snapshot = null)') !== false,
    'private writer accepts internal expected snapshot');
snap150(strpos($artifacts, 'function sanro_studio_dtf_commit_private_candidate($order, $item_id, $side, $candidate, $expected_snapshot = null)') !== false,
    'private commit validates expected snapshot');
$safe = strpos($artifacts, '// Repeat the snapshot check WITH the private per-side lock acquired,');
$write = strpos($artifacts, 'sanro_studio_dtf_artifact_write_exclusive($png_file, $bytes)');
snap150($safe !== false && $write !== false && $safe < $write,
    'commit lock rechecks draft before actual PNG bytes written');
snap150(strpos($fixture, "CHANGED AFTER PREFLIGHT") !== false
    && strpos($fixture, "sourceReadCounter133") !== false,
    'real GD test deliberately changes opposing side during render');
snap150(strpos($workflow, "'production_ready' => false") !== false
    && strpos($workflow, "'quality_approved' => false") !== false
    && strpos($workflow, "'font_parity_approved' => false") !== false,
    'candidate and status never claim approval');
snap150(strpos($workflow, "['valid' => true, 'candidate_count' => $printable,") !== false
    && strpos($workflow, "'sides' => $sides, 'quality_approved' => false") !== false,
    'public preflight returns only safe side states, never fingerprint');
echo 'PASS: ' . $count150 . " SANRO locked DTF draft fingerprint policy checks\n";
