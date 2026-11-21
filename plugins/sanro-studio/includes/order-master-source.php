<?php
defined('ABSPATH') || exit;

/**
 * Read a private, original master image for ONE locked order item. This is an
 * internal server worker primitive, never a buyer/admin download endpoint.
 * An original image is NOT a flattened, print-ready DTF composition.
 */
function sanro_studio_locked_item_master_source($order, $item_id, $side) {
    if (!function_exists('sanro_studio_auto_drive_order_eligible')
        || !sanro_studio_auto_drive_order_eligible($order)
        || !is_int($item_id) || $item_id < 1
        || !in_array($side, ['front', 'back'], true)
        || !function_exists('get_option')
        || !function_exists('sanro_studio_validate_master_bytes')
        || !defined('SANRO_STUDIO_ENABLE_PRIVATE_MASTERS')
        || SANRO_STUDIO_ENABLE_PRIVATE_MASTERS !== true
        || !defined('SANRO_STUDIO_PRIVATE_MASTER_DIR')
        || !is_string(SANRO_STUDIO_PRIVATE_MASTER_DIR)) {
        return ['valid' => false, 'error' => 'private_master_unavailable'];
    }
    $items = $order->get_items('line_item');
    if (!is_array($items) || !isset($items[$item_id])
        || !is_object($items[$item_id]) || !method_exists($items[$item_id], 'get_meta')) {
        return ['valid' => false, 'error' => 'invalid_order_item'];
    }
    $manifest = sanro_studio_order_item_production_manifest($order, $item_id);
    if (empty($manifest['valid']) || empty($manifest['ready_for_drive_integration'])) {
        return ['valid' => false, 'error' => 'production_not_ready'];
    }
    $design_id = $items[$item_id]->get_meta('_sanro_studio_design_id', true);
    if (!is_string($design_id)
        || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $design_id)) {
        return ['valid' => false, 'error' => 'design_not_bound'];
    }
    $record = get_option('sanro_studio_draft_' . $design_id, false);
    if (!is_array($record) || ($record['status'] ?? null) !== 'locked'
        || ($record['order_id'] ?? null) !== (int) $order->get_id()
        || ($record['order_item_id'] ?? null) !== $item_id
        || !is_array($record['master_refs'] ?? null)
        || !is_array($record['design'] ?? null)) {
        return ['valid' => false, 'error' => 'draft_order_binding_missing'];
    }
    // Never export stale source images that are no longer used by this side.
    $objects = $record['design'][$side]['objects'] ?? null;
    if (!is_array($objects)) {
        return ['valid' => false, 'error' => 'design_side_missing'];
    }
    $has_image = false;
    foreach ($objects as $object) {
        if (is_array($object) && ($object['type'] ?? null) === 'image') {
            $has_image = true;
            break;
        }
    }
    if (!$has_image) return ['valid' => true, 'skipped' => true, 'reason' => 'no_image_master'];
    $file_id = $record['master_refs'][$side] ?? null;
    $prefix = strtolower($design_id) . '-' . $side . '-';
    if (!is_string($file_id) || strpos($file_id, $prefix) !== 0
        || !preg_match('/^[a-f0-9]{64}\.(?:png|jpg)$/D',
            substr($file_id, strlen($prefix)))) {
        return ['valid' => false, 'error' => 'invalid_master_reference'];
    }
    $directory = realpath(SANRO_STUDIO_PRIVATE_MASTER_DIR);
    $root = defined('ABSPATH') ? realpath(ABSPATH) : false;
    $webroot = isset($_SERVER['DOCUMENT_ROOT']) && is_string($_SERVER['DOCUMENT_ROOT'])
        ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    if (!$directory || !is_dir($directory)
        || is_link(SANRO_STUDIO_PRIVATE_MASTER_DIR)
        || !$root || !$webroot
        || $directory === $root || $directory === $webroot
        || strpos($directory, $root . DIRECTORY_SEPARATOR) === 0
        || strpos($directory, $webroot . DIRECTORY_SEPARATOR) === 0) {
        return ['valid' => false, 'error' => 'unsafe_master_directory'];
    }
    $path = $directory . DIRECTORY_SEPARATOR . $file_id;
    if (is_link($path) || !is_file($path) || !is_readable($path)) {
        return ['valid' => false, 'error' => 'master_not_found'];
    }
    $size = @filesize($path);
    if (!is_int($size) || $size < 100 || $size > 10485760) {
        return ['valid' => false, 'error' => 'master_integrity_failed'];
    }
    $bytes = @file_get_contents($path);
    if (!is_string($bytes) || strlen($bytes) !== $size) {
        return ['valid' => false, 'error' => 'master_integrity_failed'];
    }
    $checked = sanro_studio_validate_master_bytes($bytes);
    $ext = $checked['mime'] === 'image/png' ? 'png'
        : (($checked['mime'] ?? null) === 'image/jpeg' ? 'jpg' : null);
    if (empty($checked['valid']) || $ext === null
        || !hash_equals(substr($file_id, strlen($prefix), 64), $checked['sha256'])
        || substr($file_id, -strlen($ext) - 1) !== '.' . $ext) {
        return ['valid' => false, 'error' => 'master_integrity_failed'];
    }
    return ['valid' => true, 'skipped' => false,
        'bytes' => $bytes, 'sha256' => $checked['sha256'],
        'mime' => $checked['mime'], 'ext' => $ext,
        'design_id' => $design_id, 'side' => $side];
}
