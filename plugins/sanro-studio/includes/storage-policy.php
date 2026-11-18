<?php
defined('ABSPATH') || exit;

/**
 * Validate a lightweight preview before any future private persistence.
 * No upload, filesystem write, public URL or master image storage here.
 */
function sanro_studio_validate_preview_bytes($bytes) {
    if (!is_string($bytes)) return ['valid' => false, 'error' => 'invalid_preview'];
    $length = strlen($bytes);
    if ($length < 100 || $length > 51200) {
        return ['valid' => false, 'error' => 'preview_size'];
    }
    if (!function_exists('getimagesizefromstring')) {
        return ['valid' => false, 'error' => 'image_inspection_unavailable'];
    }
    $image = @getimagesizefromstring($bytes);
    if (!is_array($image) || !isset($image[0], $image[1], $image['mime'])) {
        return ['valid' => false, 'error' => 'invalid_preview'];
    }
    if (!in_array($image['mime'], ['image/png', 'image/jpeg', 'image/webp'], true)
        || $image[0] < 1 || $image[1] < 1 || $image[0] > 2048 || $image[1] > 2048
        || $image[0] * $image[1] > 2097152) {
        return ['valid' => false, 'error' => 'preview_dimensions'];
    }
    return [
        'valid' => true,
        'mime' => $image['mime'],
        'width' => (int) $image[0],
        'height' => (int) $image[1],
        'bytes' => $length,
        'sha256' => hash('sha256', $bytes),
    ];
}

/**
 * Master production folder creation is permitted only after payment is
 * confirmed OR an authorized workflow has marked the order production-ready.
 * This function does not create folders or authorize an actor.
 */
function sanro_studio_production_folder_eligible($payment_confirmed, $production_ready) {
    return $payment_confirmed === true || $production_ready === true;
}

/**
 * Stage-only private preview persistence. Directory must already exist outside
 * the WordPress document root; never create directories or expose a public URL.
 * Caller must authenticate the draft owner before invoking this function.
 */
function sanro_studio_store_private_preview($design_id, $side, $bytes) {
    if (!defined('SANRO_STUDIO_ENABLE_PRIVATE_PREVIEWS')
        || SANRO_STUDIO_ENABLE_PRIVATE_PREVIEWS !== true
        || !defined('SANRO_STUDIO_PRIVATE_PREVIEW_DIR')
        || !is_string(SANRO_STUDIO_PRIVATE_PREVIEW_DIR)) {
        return ['valid' => false, 'error' => 'preview_storage_disabled'];
    }
    if (!is_string($design_id)
        || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $design_id)
        || !in_array($side, ['front', 'back'], true)) {
        return ['valid' => false, 'error' => 'invalid_preview_target'];
    }
    $checked = sanro_studio_validate_preview_bytes($bytes);
    if (empty($checked['valid'])) return $checked;
    $directory = realpath(SANRO_STUDIO_PRIVATE_PREVIEW_DIR);
    $root = defined('ABSPATH') ? realpath(ABSPATH) : false;
    if (!$directory || !is_dir($directory) || !is_writable($directory)
        || is_link(SANRO_STUDIO_PRIVATE_PREVIEW_DIR)
        || ($root && ($directory === $root || strpos($directory, $root . DIRECTORY_SEPARATOR) === 0))) {
        return ['valid' => false, 'error' => 'unsafe_preview_directory'];
    }
    $extension = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$checked['mime']];
    $filename = strtolower($design_id) . '-' . $side . '-' . $checked['sha256'] . '.' . $extension;
    $path = $directory . DIRECTORY_SEPARATOR . $filename;
    // Exclusive creation: no overwrite or symlink traversal of an existing file.
    $handle = @fopen($path, 'x');
    if ($handle === false) {
        // Repeated uploads of identical content are safe and idempotent.
        // Never trust an existing file without verifying its full digest.
        if (is_link($path) || !is_file($path) || !is_readable($path)
            || filesize($path) !== $checked['bytes']) {
            return ['valid' => false, 'error' => 'preview_write_failed'];
        }
        $existing = @file_get_contents($path);
        if (!is_string($existing) || !hash_equals($checked['sha256'], hash('sha256', $existing))) {
            return ['valid' => false, 'error' => 'preview_integrity_failed'];
        }
        return ['valid' => true, 'file_id' => $filename, 'sha256' => $checked['sha256'],
            'mime' => $checked['mime'], 'bytes' => $checked['bytes']];
    }
    if (!@chmod($path, 0600)) {
        fclose($handle);
        @unlink($path);
        return ['valid' => false, 'error' => 'preview_write_failed'];
    }
    $offset = 0;
    $length = strlen($bytes);
    while ($offset < $length) {
        $written = fwrite($handle, substr($bytes, $offset));
        if (!is_int($written) || $written <= 0) break;
        $offset += $written;
    }
    $flushed = $offset === $length && fflush($handle);
    $closed = fclose($handle);
    if (!$flushed || !$closed) {
        @unlink($path);
        return ['valid' => false, 'error' => 'preview_write_failed'];
    }
    return ['valid' => true, 'file_id' => $filename, 'sha256' => $checked['sha256'],
        'mime' => $checked['mime'], 'bytes' => $checked['bytes']];
}

/**
 * Authenticated application boundary for private preview writes.
 * Never expose the low-level writer directly as a REST callback.
 * The owner token is only checked against the draft store, never persisted
 * alongside preview bytes or returned in a preview reference.
 */
function sanro_studio_store_owned_draft_preview($design_id, $owner_token, $side, $bytes) {
    if (!function_exists('sanro_studio_read_draft')) {
        return ['valid' => false, 'error' => 'draft_store_unavailable'];
    }
    $draft = sanro_studio_read_draft($design_id, $owner_token);
    if (empty($draft['valid'])) return $draft;
    if (($draft['status'] ?? null) !== 'draft') {
        return ['valid' => false, 'error' => 'draft_locked'];
    }
    if (!in_array($side, ['front', 'back'], true)) {
        return ['valid' => false, 'error' => 'invalid_preview_target'];
    }
    return sanro_studio_store_private_preview($design_id, $side, $bytes);
}

/**
 * Resolve an opaque private preview reference only after draft ownership is
 * established. Never return a filesystem path or public URL to the caller.
 * The reference is an immutable filename from the content-addressed writer.
 */
function sanro_studio_read_owned_draft_preview($design_id, $owner_token, $side, $file_id) {
    if (!function_exists('sanro_studio_read_draft')) {
        return ['valid' => false, 'error' => 'draft_store_unavailable'];
    }
    $draft = sanro_studio_read_draft($design_id, $owner_token);
    if (empty($draft['valid'])) return $draft;
    if (!in_array($side, ['front', 'back'], true)
        || !is_string($file_id) || !is_string($design_id)
        || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $design_id)
        || !preg_match('/^[a-f0-9]{64}\\.(png|jpg|webp)$/D', substr($file_id, strlen($design_id) + strlen($side) + 2))
        || strpos($file_id, strtolower($design_id) . '-' . $side . '-') !== 0) {
        return ['valid' => false, 'error' => 'invalid_preview_reference'];
    }
    if (!defined('SANRO_STUDIO_ENABLE_PRIVATE_PREVIEWS')
        || SANRO_STUDIO_ENABLE_PRIVATE_PREVIEWS !== true
        || !defined('SANRO_STUDIO_PRIVATE_PREVIEW_DIR')
        || !is_string(SANRO_STUDIO_PRIVATE_PREVIEW_DIR)) {
        return ['valid' => false, 'error' => 'preview_storage_disabled'];
    }
    $directory = realpath(SANRO_STUDIO_PRIVATE_PREVIEW_DIR);
    $root = defined('ABSPATH') ? realpath(ABSPATH) : false;
    if (!$directory || !is_dir($directory) || is_link(SANRO_STUDIO_PRIVATE_PREVIEW_DIR)
        || ($root && ($directory === $root || strpos($directory, $root . DIRECTORY_SEPARATOR) === 0))) {
        return ['valid' => false, 'error' => 'unsafe_preview_directory'];
    }
    $path = $directory . DIRECTORY_SEPARATOR . $file_id;
    if (is_link($path) || !is_file($path) || !is_readable($path)
        || filesize($path) > 51200) {
        return ['valid' => false, 'error' => 'preview_not_found'];
    }
    $bytes = file_get_contents($path);
    $checked = sanro_studio_validate_preview_bytes($bytes);
    if (empty($checked['valid']) || strpos($file_id, '-' . $checked['sha256'] . '.') === false) {
        return ['valid' => false, 'error' => 'preview_integrity_failed'];
    }
    return ['valid' => true, 'bytes' => $bytes, 'mime' => $checked['mime']];
}
