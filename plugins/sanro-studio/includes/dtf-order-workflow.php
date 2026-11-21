<?php
defined('ABSPATH') || exit;

/**
 * Sprint 137 — internal per-order DTF candidate coordinator.
 * Not registered as a hook, REST endpoint, cron job, or admin action.
 * Caller must independently authorize access to this specific order.
 * These are unapproved private candidates, never press-ready output.
 */
function sanro_studio_dtf_order_batch_enabled() {
    return defined('SANRO_STUDIO_ENABLE_DTF_ORDER_BATCH')
        && SANRO_STUDIO_ENABLE_DTF_ORDER_BATCH === true
        && function_exists('sanro_studio_dtf_artifacts_enabled')
        && sanro_studio_dtf_artifacts_enabled();
}

/** Permitlisted metadata. No private path, raw PNG, credentials, or download URL. */
function sanro_studio_dtf_order_side_metadata($result) {
    if (!is_array($result) || empty($result['valid'])
        || !empty($result['skipped'])) return false;
    foreach (['order_id','item_id','design_id','side','sha256',
        'width_px','height_px','width_cm','height_cm',
        'offset_x_cm','offset_y_cm','dpi'] as $key) {
        if (!array_key_exists($key, $result)) return false;
    }
    if (($result['private'] ?? null) !== true
        || ($result['quality_approved'] ?? null) !== false
        || ($result['font_parity_approved'] ?? null) !== false
        || !is_string($result['sha256'])
        || !preg_match('/^[a-f0-9]{64}$/D', $result['sha256'])) return false;
    $out = [
        'state' => 'candidate', 'private' => true,
        'order_id' => $result['order_id'],
        'item_id' => $result['item_id'],
        'design_id' => $result['design_id'], 'side' => $result['side'],
        'sha256' => $result['sha256'],
        'width_px' => $result['width_px'], 'height_px' => $result['height_px'],
        'width_cm' => $result['width_cm'], 'height_cm' => $result['height_cm'],
        'offset_x_cm' => $result['offset_x_cm'],
        'offset_y_cm' => $result['offset_y_cm'],
        'dpi' => $result['dpi'], 'quality_approved' => false,
        'font_parity_approved' => false,
    ];
    if (isset($result['idempotent']) && is_bool($result['idempotent'])) {
        $out['idempotent'] = $result['idempotent'];
    }
    return $out;
}

/**
 * Read-only internal order-item status for a FUTURE authenticated admin bridge.
 * Enforces locked order binding for every side, and hashes the stored PNG again.
 * One corrupt side fails the entire status (no fake "ready" aggregate).
 */
function sanro_studio_dtf_order_item_private_status($order, $item_id) {
    if (!function_exists('sanro_studio_dtf_artifacts_enabled')
        || !sanro_studio_dtf_artifacts_enabled()
        || !function_exists('sanro_studio_dtf_artifact_binding')
        || !function_exists('sanro_studio_dtf_verify_private_artifact')) {
        return ['valid' => false, 'error' => 'artifact_storage_disabled'];
    }
    $sides = []; $stored = 0;
    foreach (['front', 'back'] as $side) {
        $binding = sanro_studio_dtf_artifact_binding($order, $item_id, $side);
        if (empty($binding['valid'])) {
            return ['valid' => false, 'error' => $binding['error'] ?? 'artifact_binding_failed',
                'failed_side' => $side, 'partial' => $stored > 0, 'sides' => $sides];
        }
        if (!empty($binding['skipped'])) {
            $sides[$side] = ['state' => 'blank', 'reason' => 'blank_side'];
            continue;
        }
        $result = sanro_studio_dtf_verify_private_artifact($order, $item_id, $side);
        if (empty($result['valid'])
            || !($safe = sanro_studio_dtf_order_side_metadata($result))) {
            return ['valid' => false,
                'error' => $result['error'] ?? 'artifact_status_invalid',
                'failed_side' => $side, 'partial' => $stored > 0, 'sides' => $sides];
        }
        $sides[$side] = $safe;
        $stored++;
    }
    return ['valid' => true, 'complete' => true,
        'candidate_count' => $stored, 'sides' => $sides,
        'quality_approved' => false, 'production_ready' => false];
}

/**
 * Explicit internal batch worker: render -> immutable persist -> SHA readback.
 * The two side writes cannot be a single filesystem transaction. If the
 * second fails, return partial=true, keep the already verified first side,
 * and NEVER report a complete order.
 */
function sanro_studio_dtf_prepare_order_item_candidates($order, $item_id) {
    if (!sanro_studio_dtf_order_batch_enabled()) {
        return ['valid' => false, 'error' => 'order_batch_disabled'];
    }
    if (!function_exists('sanro_studio_dtf_persist_order_item_side')
        || !function_exists('sanro_studio_dtf_artifact_binding')
        || !function_exists('sanro_studio_dtf_verify_private_artifact')) {
        return ['valid' => false, 'error' => 'artifact_worker_unavailable'];
    }
    $sides = []; $stored = 0;
    foreach (['front', 'back'] as $side) {
        $binding = sanro_studio_dtf_artifact_binding($order, $item_id, $side);
        if (empty($binding['valid'])) {
            return ['valid' => false, 'error' => $binding['error'] ?? 'artifact_binding_failed',
                'failed_side' => $side, 'partial' => $stored > 0, 'sides' => $sides];
        }
        if (!empty($binding['skipped'])) {
            $sides[$side] = ['state' => 'blank', 'reason' => 'blank_side'];
            continue;
        }
        $written = sanro_studio_dtf_persist_order_item_side($order, $item_id, $side);
        if (empty($written['valid']) || !empty($written['skipped'])) {
            return ['valid' => false, 'error' => $written['error'] ?? 'artifact_write_failed',
                'failed_side' => $side, 'partial' => $stored > 0, 'sides' => $sides];
        }
        $verified = sanro_studio_dtf_verify_private_artifact($order, $item_id, $side);
        if (empty($verified['valid'])
            || !isset($written['sha256'], $verified['sha256'])
            || !is_string($written['sha256'])
            || !hash_equals($written['sha256'], $verified['sha256'])
            || !($safe = sanro_studio_dtf_order_side_metadata($verified))) {
            return ['valid' => false, 'error' => 'artifact_readback_failed',
                'failed_side' => $side, 'partial' => $stored > 0, 'sides' => $sides];
        }
        $safe['idempotent'] = !empty($written['idempotent']);
        $sides[$side] = $safe;
        $stored++;
    }
    return ['valid' => true, 'complete' => true,
        'candidate_count' => $stored, 'sides' => $sides,
        'quality_approved' => false, 'production_ready' => false];
}
