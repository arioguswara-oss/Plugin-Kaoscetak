<?php
defined('ABSPATH') || exit;

/**
 * Sprint 136: private, immutable per-order DTF PNG candidate storage.
 * NO hooks, routes, public downloads, Drive upload or production approval.
 * Directory must pre-exist, be owner-only and outside both WordPress roots.
 */
function sanro_studio_dtf_artifacts_enabled() {
    return defined('SANRO_STUDIO_ENABLE_DTF_PRIVATE_ARTIFACTS')
        && SANRO_STUDIO_ENABLE_DTF_PRIVATE_ARTIFACTS === true
        && function_exists('sanro_studio_dtf_layer_enabled')
        && sanro_studio_dtf_layer_enabled();
}

function sanro_studio_dtf_artifact_directory() {
    if (!defined('SANRO_STUDIO_DTF_ARTIFACT_DIR')
        || !is_string(SANRO_STUDIO_DTF_ARTIFACT_DIR)
        || SANRO_STUDIO_DTF_ARTIFACT_DIR === ''
        || is_link(SANRO_STUDIO_DTF_ARTIFACT_DIR)) return false;
    $dir = realpath(SANRO_STUDIO_DTF_ARTIFACT_DIR);
    $root = defined('ABSPATH') ? realpath(ABSPATH) : false;
    $webroot = isset($_SERVER['DOCUMENT_ROOT']) && is_string($_SERVER['DOCUMENT_ROOT'])
        ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    if (!$dir || !$root || !$webroot || !is_dir($dir)
        || !is_readable($dir) || !is_writable($dir)
        || (@fileperms($dir) & 0077) !== 0) return false;
    foreach ([$root, $webroot] as $protected) {
        if ($dir === $protected
            || strpos($dir, rtrim($protected, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR) === 0) return false;
    }
    return $dir;
}

/** Revalidate immutable WooCommerce order/item/draft binding before ANY IO. */
function sanro_studio_dtf_artifact_binding($order, $item_id, $side) {
    if (!sanro_studio_dtf_artifacts_enabled()) {
        return ['valid' => false, 'error' => 'artifact_storage_disabled'];
    }
    if (!is_object($order) || !method_exists($order, 'get_id')
        || !method_exists($order, 'get_items') || !is_int($item_id)
        || $item_id < 1 || !in_array($side, ['front', 'back'], true)
        || !function_exists('sanro_studio_auto_drive_order_eligible')
        || !sanro_studio_auto_drive_order_eligible($order)
        || !function_exists('sanro_studio_locked_record_has_item')
        || !function_exists('get_option')
        || !function_exists('sanro_studio_validate_product_design')) {
        return ['valid' => false, 'error' => 'artifact_order_ineligible'];
    }
    $order_id = $order->get_id();
    if (!is_int($order_id) || $order_id < 1) {
        return ['valid' => false, 'error' => 'artifact_order_ineligible'];
    }
    $items = $order->get_items('line_item');
    if (!is_array($items) || !isset($items[$item_id])
        || !is_object($items[$item_id])
        || !method_exists($items[$item_id], 'get_meta')
        || !method_exists($items[$item_id], 'get_product_id')) {
        return ['valid' => false, 'error' => 'artifact_item_invalid'];
    }
    $item = $items[$item_id];
    $design_id = $item->get_meta('_sanro_studio_design_id', true);
    $product_id = $item->get_product_id();
    if (!is_string($design_id)
        || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $design_id)
        || !is_int($product_id) || $product_id < 1) {
        return ['valid' => false, 'error' => 'artifact_item_invalid'];
    }
    $record = get_option('sanro_studio_draft_' . $design_id, false);
    if (!is_array($record) || ($record['status'] ?? null) !== 'locked'
        || ($record['order_id'] ?? null) !== $order_id
        || ($record['product_id'] ?? null) !== $product_id
        || !sanro_studio_locked_record_has_item($record, $item_id)
        || !is_array($record['design'] ?? null)
        || empty(sanro_studio_validate_product_design(
            $product_id, $record['design'])['valid'])) {
        return ['valid' => false, 'error' => 'artifact_draft_binding_missing'];
    }
    $objects = $record['design'][$side]['objects'] ?? null;
    if (!is_array($objects) || !$objects) {
        return ['valid' => true, 'skipped' => true, 'reason' => 'blank_side'];
    }
    return [
        'valid' => true, 'skipped' => false, 'order_id' => $order_id,
        'item_id' => $item_id, 'design_id' => strtolower($design_id),
        'side' => $side,
    ];
}

/** Fixed name, not derived from a buyer-controlled file path or URL. */
function sanro_studio_dtf_artifact_basename($binding) {
    return 'order-' . $binding['order_id'] . '-item-' . $binding['item_id']
        . '-' . $binding['design_id'] . '-' . $binding['side'];
}

/** Private metadata only. Never return raw PNG bytes, filesystem path or URL. */
function sanro_studio_dtf_verify_private_artifact($order, $item_id, $side) {
    $binding = sanro_studio_dtf_artifact_binding($order, $item_id, $side);
    if (empty($binding['valid']) || !empty($binding['skipped'])) return $binding;
    $dir = sanro_studio_dtf_artifact_directory();
    if (!$dir) return ['valid' => false, 'error' => 'artifact_directory_unsafe'];
    $base = $dir . DIRECTORY_SEPARATOR . sanro_studio_dtf_artifact_basename($binding);
    $png_file = $base . '.png';
    $meta_file = $base . '.json';
    if (is_link($png_file) || is_link($meta_file)
        || !is_file($png_file) || !is_file($meta_file)
        || !is_readable($png_file) || !is_readable($meta_file)) {
        return ['valid' => false, 'error' => 'artifact_missing_or_incomplete'];
    }
    foreach ([$png_file, $meta_file] as $file) {
        if ((@fileperms($file) & 0077) !== 0) {
            return ['valid' => false, 'error' => 'artifact_permissions_unsafe'];
        }
    }
    $png_size = @filesize($png_file);
    $meta_size = @filesize($meta_file);
    if (!is_int($png_size) || $png_size < 64 || $png_size > 33554432
        || !is_int($meta_size) || $meta_size < 30 || $meta_size > 4096) {
        return ['valid' => false, 'error' => 'artifact_integrity_failed'];
    }
    $json = @file_get_contents($meta_file);
    $meta = is_string($json) ? json_decode($json, true) : null;
    if (!is_array($meta) || ($meta['schema'] ?? null) !== 1
        || ($meta['order_id'] ?? null) !== $binding['order_id']
        || ($meta['item_id'] ?? null) !== $binding['item_id']
        || ($meta['design_id'] ?? null) !== $binding['design_id']
        || ($meta['side'] ?? null) !== $binding['side']
        || ($meta['kind'] ?? null) !== 'layered_dtf_raster_candidate'
        || ($meta['mime'] ?? null) !== 'image/png'
        || ($meta['quality_approved'] ?? null) !== false
        || ($meta['font_parity_approved'] ?? null) !== false
        || ($meta['dpi'] ?? null) !== 300
        || ($meta['bytes'] ?? null) !== $png_size
        || !is_int($meta['width_px'] ?? null) || !is_int($meta['height_px'] ?? null)
        || $meta['width_px'] < 1 || $meta['height_px'] < 1
        || $meta['width_px'] > 4096 || $meta['height_px'] > 4096
        || !is_string($meta['sha256'] ?? null)
        || !preg_match('/^[a-f0-9]{64}$/D', $meta['sha256'])) {
        return ['valid' => false, 'error' => 'artifact_integrity_failed'];
    }
    $bytes = @file_get_contents($png_file);
    if (!is_string($bytes) || strlen($bytes) !== $png_size
        || !hash_equals($meta['sha256'], hash('sha256', $bytes))
        || substr($bytes, 0, 8) !== "\x89PNG\r\n\x1a\n"
        || ord($bytes[25]) !== 6
        || !function_exists('sanro_studio_dtf_png_has_300dpi')
        || !sanro_studio_dtf_png_has_300dpi($bytes)) {
        return ['valid' => false, 'error' => 'artifact_integrity_failed'];
    }
    $image = function_exists('getimagesizefromstring')
        ? @getimagesizefromstring($bytes) : false;
    if (!is_array($image) || ($image['mime'] ?? null) !== 'image/png'
        || $image[0] !== $meta['width_px'] || $image[1] !== $meta['height_px']) {
        return ['valid' => false, 'error' => 'artifact_integrity_failed'];
    }
    return [
        'valid' => true, 'skipped' => false, 'private' => true,
        'order_id' => $meta['order_id'], 'item_id' => $meta['item_id'],
        'design_id' => $meta['design_id'], 'side' => $meta['side'],
        'sha256' => $meta['sha256'], 'bytes' => $png_size,
        'width_px' => $meta['width_px'], 'height_px' => $meta['height_px'],
        'dpi' => 300, 'kind' => $meta['kind'],
        'quality_approved' => false, 'font_parity_approved' => false,
    ];
}

/** x-mode prevents overwrite or symlink-following, even under contention. */
function sanro_studio_dtf_artifact_write_exclusive($path, $bytes) {
    $handle = @fopen($path, 'xb');
    if ($handle === false) return false;
    $ok = @chmod($path, 0600);
    $offset = 0; $total = strlen($bytes);
    while ($ok && $offset < $total) {
        $part = @fwrite($handle, substr($bytes, $offset));
        if (!is_int($part) || $part <= 0) { $ok = false; break; }
        $offset += $part;
    }
    $ok = $ok && $offset === $total && @fflush($handle);
    $closed = @fclose($handle);
    if (!$ok || !$closed) {
        @unlink($path);
        return false;
    }
    return true;
}

/**
 * Low-level internal candidate commit, intended ONLY after trusted rendering.
 * Rechecks the paid/ready, locked order binding; never trusts a buyer filename.
 */
function sanro_studio_dtf_commit_private_candidate($order, $item_id, $side, $candidate) {
    $binding = sanro_studio_dtf_artifact_binding($order, $item_id, $side);
    if (empty($binding['valid']) || !empty($binding['skipped'])) return $binding;
    $dir = sanro_studio_dtf_artifact_directory();
    if (!$dir) return ['valid' => false, 'error' => 'artifact_directory_unsafe'];
    $bytes = is_array($candidate) ? ($candidate['bytes'] ?? null) : null;
    if (!is_array($candidate) || ($candidate['valid'] ?? null) !== true
        || !empty($candidate['skipped'])
        || ($candidate['kind'] ?? null) !== 'layered_dtf_raster_candidate'
        || ($candidate['order_id'] ?? null) !== $binding['order_id']
        || ($candidate['item_id'] ?? null) !== $binding['item_id']
        || strtolower((string) ($candidate['design_id'] ?? '')) !== $binding['design_id']
        || ($candidate['side'] ?? null) !== $binding['side']
        || ($candidate['quality_approved'] ?? null) !== false
        || ($candidate['font_parity_approved'] ?? null) !== false
        || ($candidate['mime'] ?? null) !== 'image/png'
        || ($candidate['dpi'] ?? null) !== 300
        || !is_int($candidate['width_px'] ?? null)
        || !is_int($candidate['height_px'] ?? null)
        || $candidate['width_px'] < 1 || $candidate['height_px'] < 1
        || $candidate['width_px'] > 4096 || $candidate['height_px'] > 4096
        || $candidate['width_px'] * $candidate['height_px'] > 6000000
        || !is_string($bytes) || strlen($bytes) < 64 || strlen($bytes) > 33554432
        || !is_string($candidate['sha256'] ?? null)
        || !preg_match('/^[a-f0-9]{64}$/D', $candidate['sha256'])
        || !hash_equals($candidate['sha256'], hash('sha256', $bytes))
        || substr($bytes, 0, 8) !== "\x89PNG\r\n\x1a\n"
        || ord($bytes[25]) !== 6
        || !function_exists('sanro_studio_dtf_png_has_300dpi')
        || !sanro_studio_dtf_png_has_300dpi($bytes)) {
        return ['valid' => false, 'error' => 'artifact_candidate_invalid'];
    }
    $image = function_exists('getimagesizefromstring')
        ? @getimagesizefromstring($bytes) : false;
    if (!is_array($image) || ($image['mime'] ?? null) !== 'image/png'
        || $image[0] !== $candidate['width_px']
        || $image[1] !== $candidate['height_px']) {
        return ['valid' => false, 'error' => 'artifact_candidate_invalid'];
    }
    $base = $dir . DIRECTORY_SEPARATOR . sanro_studio_dtf_artifact_basename($binding);
    $png_file = $base . '.png';
    $meta_file = $base . '.json';
    $lock_file = $base . '.lock';
    if (is_link($lock_file)) return ['valid' => false, 'error' => 'artifact_lock_unsafe'];
    $lock = @fopen($lock_file, 'cb');
    if ($lock === false || !@chmod($lock_file, 0600)) {
        if (is_resource($lock)) fclose($lock);
        return ['valid' => false, 'error' => 'artifact_lock_failed'];
    }
    try {
        if (!@flock($lock, LOCK_EX | LOCK_NB)) {
            return ['valid' => false, 'error' => 'artifact_busy'];
        }
        if (file_exists($png_file) || file_exists($meta_file)
            || is_link($png_file) || is_link($meta_file)) {
            $existing = sanro_studio_dtf_verify_private_artifact($order, $item_id, $side);
            if (empty($existing['valid'])) {
                return ['valid' => false, 'error' => 'artifact_integrity_failed'];
            }
            if (!hash_equals($candidate['sha256'], $existing['sha256'])) {
                return ['valid' => false, 'error' => 'artifact_conflict'];
            }
            return $existing + ['idempotent' => true];
        }
        $meta = [
            'schema' => 1, 'order_id' => $binding['order_id'],
            'item_id' => $binding['item_id'], 'design_id' => $binding['design_id'],
            'side' => $binding['side'], 'kind' => $candidate['kind'],
            'quality_approved' => false, 'font_parity_approved' => false,
            'mime' => 'image/png', 'dpi' => 300,
            'width_px' => $candidate['width_px'],
            'height_px' => $candidate['height_px'],
            'bytes' => strlen($bytes), 'sha256' => $candidate['sha256'],
        ];
        $json = json_encode($meta, JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || strlen($json) > 4096) {
            return ['valid' => false, 'error' => 'artifact_manifest_invalid'];
        }
        if (!sanro_studio_dtf_artifact_write_exclusive($png_file, $bytes)) {
            return ['valid' => false, 'error' => 'artifact_write_failed'];
        }
        if (!sanro_studio_dtf_artifact_write_exclusive($meta_file, $json)) {
            @unlink($png_file);
            return ['valid' => false, 'error' => 'artifact_write_failed'];
        }
        $verified = sanro_studio_dtf_verify_private_artifact($order, $item_id, $side);
        if (empty($verified['valid'])) {
            return ['valid' => false, 'error' => 'artifact_integrity_failed'];
        }
        return $verified + ['idempotent' => false];
    } finally {
        @flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** No registered hooks: a future authorized worker must invoke explicitly. */
function sanro_studio_dtf_persist_order_item_side($order, $item_id, $side) {
    $binding = sanro_studio_dtf_artifact_binding($order, $item_id, $side);
    if (empty($binding['valid']) || !empty($binding['skipped'])) return $binding;
    if (!function_exists('sanro_studio_dtf_render_layer_side')) {
        return ['valid' => false, 'error' => 'artifact_renderer_unavailable'];
    }
    $candidate = sanro_studio_dtf_render_layer_side($order, $item_id, $side);
    if (empty($candidate['valid']) || !empty($candidate['skipped'])) return $candidate;
    return sanro_studio_dtf_commit_private_candidate($order, $item_id, $side, $candidate);
}
