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

/**
 * Sprint 150: internal fingerprint of the ENTIRE locked draft record plus the
 * live Woo order/item/product/Design ID binding. Hash never enters metadata,
 * HTML, manifest, browser, or the outward preflight result. This detects
 * changes that keep the same physical geometry (e.g. text, color or master).
 *
 * Fail closed on missing order/record, malformed JSON, an unpaid order or an
 * unlocked/cross-item draft. No database writes and no private master reads.
 */
function sanro_studio_dtf_locked_order_item_fingerprint($order, $item_id) {
    if (!sanro_studio_dtf_order_batch_enabled()
        || !is_object($order) || !method_exists($order, 'get_id')
        || !method_exists($order, 'get_items')
        || !is_int($item_id) || $item_id < 1
        || !function_exists('sanro_studio_auto_drive_order_eligible')
        || !sanro_studio_auto_drive_order_eligible($order)
        || !function_exists('sanro_studio_locked_record_has_item')
        || !function_exists('get_option')) return false;
    $order_id = $order->get_id();
    $items = $order->get_items('line_item');
    if (!is_int($order_id) || $order_id < 1
        || !is_array($items) || !isset($items[$item_id])
        || !is_object($items[$item_id])
        || !method_exists($items[$item_id], 'get_meta')
        || !method_exists($items[$item_id], 'get_product_id')) return false;
    $item = $items[$item_id];
    $design_id = $item->get_meta('_sanro_studio_design_id', true);
    $product_id = $item->get_product_id();
    if (!is_string($design_id)
        || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $design_id)
        || !is_int($product_id) || $product_id < 1) return false;
    $record = get_option('sanro_studio_draft_' . $design_id, false);
    if (!is_array($record) || ($record['status'] ?? null) !== 'locked'
        || ($record['order_id'] ?? null) !== $order_id
        || ($record['product_id'] ?? null) !== $product_id
        || !sanro_studio_locked_record_has_item($record, $item_id)
        || !is_array($record['design'] ?? null)) return false;
    // Full record rather than geometry alone: buyer image/text/color/master
    // references and locking metadata must remain identical across phases.
    $json = json_encode([1, $order_id, $item_id, $product_id,
        strtolower($design_id), $record],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    if (!is_string($json) || strlen($json) > 1048576) return false;
    return hash('sha256', $json);
}

function sanro_studio_dtf_locked_snapshot_matches($order, $item_id, $expected) {
    if (!is_string($expected) || !preg_match('/^[a-f0-9]{64}$/D', $expected)) return false;
    $current = sanro_studio_dtf_locked_order_item_fingerprint($order, $item_id);
    return is_string($current) && hash_equals($expected, $current);
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
    if ($stored === 0) {
        return ['valid' => false, 'complete' => false,
            'error' => 'artifact_no_printable_sides', 'partial' => false,
            'candidate_count' => 0, 'sides' => $sides];
    }
    return ['valid' => true, 'complete' => true,
        'candidate_count' => $stored, 'sides' => $sides,
        'quality_approved' => false, 'production_ready' => false];
}

/**
 * Sprint 149 — read-only two-sided raster/source preflight BEFORE first write.
 * The layer planner validates the actual locked design, image-master digest,
 * source resolution/aspect and trusted font with no PNG output or file writes.
 * Never return the planner itself: it may contain private master bytes/text.
 * This worker is already gated by the parent DTF batch flag, OFF in staging.
 */
function sanro_studio_dtf_preflight_order_item_candidates($order, $item_id) {
    if (!sanro_studio_dtf_order_batch_enabled()) {
        return ['valid' => false, 'error' => 'order_batch_disabled'];
    }
    if (!function_exists('sanro_studio_dtf_artifact_binding')
        || !function_exists('sanro_studio_dtf_artifact_directory')
        || !function_exists('sanro_studio_dtf_layer_plan')) {
        return ['valid' => false, 'error' => 'artifact_preflight_unavailable'];
    }
    if (!sanro_studio_dtf_artifact_directory()) {
        return ['valid' => false, 'error' => 'artifact_directory_unsafe',
            'partial' => false];
    }
    $sides = []; $printable = 0; $snapshot = null;
    foreach (['front', 'back'] as $side) {
        $binding = sanro_studio_dtf_artifact_binding($order, $item_id, $side);
        if (!is_array($binding) || empty($binding['valid'])) {
            return ['valid' => false,
                'error' => $binding['error'] ?? 'artifact_binding_failed',
                'failed_side' => $side, 'partial' => false];
        }
        $observed = sanro_studio_dtf_locked_order_item_fingerprint($order, $item_id);
        if (!is_string($observed) || ($snapshot !== null
            && !hash_equals($snapshot, $observed))) {
            return ['valid' => false, 'error' => 'artifact_snapshot_changed',
                'failed_side' => $side, 'partial' => false];
        }
        if ($snapshot === null) $snapshot = $observed;
        // Planner may load a private PNG master, but it performs no rendering
        // or persistence. Only fixed safe status codes leave this scope.
        $plan = sanro_studio_dtf_layer_plan($order, $item_id, $side);
        if (!is_array($plan) || empty($plan['valid'])
            || !empty($binding['skipped']) !== !empty($plan['skipped'])) {
            return ['valid' => false, 'error' => 'artifact_preflight_failed',
                'failed_side' => $side, 'partial' => false];
        }
        if (!sanro_studio_dtf_locked_snapshot_matches($order, $item_id, $snapshot)) {
            return ['valid' => false, 'error' => 'artifact_snapshot_changed',
                'failed_side' => $side, 'partial' => false];
        }
        if (!empty($binding['skipped'])) {
            $sides[$side] = ['state' => 'blank'];
            continue;
        }
        if (($plan['order_id'] ?? null) !== $binding['order_id']
            || ($plan['item_id'] ?? null) !== $binding['item_id']
            || ($plan['side'] ?? null) !== $side
            || !is_string($plan['design_id'] ?? null)
            || !hash_equals($binding['design_id'], strtolower($plan['design_id']))
            || ($plan['kind'] ?? null) !== 'layered_dtf_raster_candidate'
            || ($plan['quality_approved'] ?? null) !== false
            || ($plan['font_parity_approved'] ?? null) !== false
            || ($plan['dpi'] ?? null) !== 300
            || !is_int($plan['width_px'] ?? null)
            || !is_int($plan['height_px'] ?? null)
            || $plan['width_px'] < 1 || $plan['height_px'] < 1
            || $plan['width_px'] > 4096 || $plan['height_px'] > 4096
            || $plan['width_px'] * $plan['height_px'] > 6000000) {
            return ['valid' => false, 'error' => 'artifact_preflight_mismatch',
                'failed_side' => $side, 'partial' => false];
        }
        $sides[$side] = ['state' => 'planned'];
        $printable++;
    }
    if ($printable === 0) {
        return ['valid' => false, 'error' => 'artifact_no_printable_sides',
            'candidate_count' => 0, 'partial' => false];
    }
    return ['valid' => true, 'candidate_count' => $printable,
        'sides' => $sides, 'quality_approved' => false,
        'production_ready' => false];
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
    // Freeze a private digest BEFORE the two-side preflight. The real worker
    // re-reads the locked draft for rendering and committing, so every phase
    // must compare against this exact draft version before writing.
    $snapshot = sanro_studio_dtf_locked_order_item_fingerprint($order, $item_id);
    $preflight = sanro_studio_dtf_preflight_order_item_candidates($order, $item_id);
    if (empty($preflight['valid'])) return $preflight;
    if (!sanro_studio_dtf_locked_snapshot_matches($order, $item_id, $snapshot)) {
        return ['valid' => false, 'error' => 'artifact_snapshot_changed',
            'partial' => false];
    }
    $sides = []; $stored = 0;
    foreach (['front', 'back'] as $side) {
        $binding = sanro_studio_dtf_artifact_binding($order, $item_id, $side);
        if (empty($binding['valid'])) {
            return ['valid' => false, 'error' => $binding['error'] ?? 'artifact_binding_failed',
                'failed_side' => $side, 'partial' => $stored > 0, 'sides' => $sides];
        }
        if (!sanro_studio_dtf_locked_snapshot_matches($order, $item_id, $snapshot)) {
            return ['valid' => false, 'error' => 'artifact_snapshot_changed',
                'failed_side' => $side, 'partial' => $stored > 0, 'sides' => $sides];
        }
        if (!empty($binding['skipped'])) {
            $sides[$side] = ['state' => 'blank', 'reason' => 'blank_side'];
            continue;
        }
        $written = sanro_studio_dtf_persist_order_item_side($order, $item_id, $side, $snapshot);
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
        if (!sanro_studio_dtf_locked_snapshot_matches($order, $item_id, $snapshot)) {
            return ['valid' => false, 'error' => 'artifact_snapshot_changed',
                'failed_side' => $side, 'partial' => true, 'sides' => $sides];
        }
        $safe['idempotent'] = !empty($written['idempotent']);
        $sides[$side] = $safe;
        $stored++;
    }
    if (!sanro_studio_dtf_locked_snapshot_matches($order, $item_id, $snapshot)) {
        return ['valid' => false, 'error' => 'artifact_snapshot_changed',
            'partial' => $stored > 0, 'sides' => $sides];
    }
    if ($stored === 0) {
        return ['valid' => false, 'complete' => false,
            'error' => 'artifact_no_printable_sides', 'partial' => false,
            'candidate_count' => 0, 'sides' => $sides];
    }
    return ['valid' => true, 'complete' => true,
        'candidate_count' => $stored, 'sides' => $sides,
        'quality_approved' => false, 'production_ready' => false];
}
