<?php
defined('ABSPATH') || exit;

/**
 * Private master image storage primitive. Deliberately NOT exposed as a REST
 * route: an authenticated, quota-limited draft-owner upload boundary must be
 * implemented and tested before accepting buyer uploads.
 *
 * Configure an existing writable directory outside BOTH ABSPATH and the
 * document root. No directory creation, public URLs or Google Drive writes.
 */
function sanro_studio_validate_master_bytes($bytes) {
    if (!is_string($bytes) || strlen($bytes) < 100 || strlen($bytes) > 10485760) {
        return ['valid' => false, 'error' => 'master_size'];
    }
    if (!function_exists('getimagesizefromstring')) {
        return ['valid' => false, 'error' => 'image_inspection_unavailable'];
    }
    $info = @getimagesizefromstring($bytes);
    if (!is_array($info) || !isset($info[0], $info[1], $info['mime'])
        || !in_array($info['mime'], ['image/png', 'image/jpeg'], true)
        || $info[0] < 1 || $info[1] < 1 || $info[0] > 10000
        || $info[1] > 10000 || $info[0] * $info[1] > 25000000) {
        return ['valid' => false, 'error' => 'master_image_invalid'];
    }
    return ['valid' => true, 'mime' => $info['mime'],
        'bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes),
        'width' => (int) $info[0], 'height' => (int) $info[1]];
}

/**
 * Low-level writer; the caller MUST authenticate and authorize ownership.
 * This function is not called from WordPress hooks or public REST routes.
 */
function sanro_studio_store_private_master($design_id, $side, $bytes) {
    if (!defined('SANRO_STUDIO_ENABLE_PRIVATE_MASTERS')
        || SANRO_STUDIO_ENABLE_PRIVATE_MASTERS !== true
        || !defined('SANRO_STUDIO_PRIVATE_MASTER_DIR')
        || !is_string(SANRO_STUDIO_PRIVATE_MASTER_DIR)) {
        return ['valid' => false, 'error' => 'master_storage_disabled'];
    }
    if (!is_string($design_id)
        || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $design_id)
        || !in_array($side, ['front', 'back'], true)) {
        return ['valid' => false, 'error' => 'invalid_master_target'];
    }
    $checked = sanro_studio_validate_master_bytes($bytes);
    if (empty($checked['valid'])) return $checked;
    $directory = realpath(SANRO_STUDIO_PRIVATE_MASTER_DIR);
    $root = defined('ABSPATH') ? realpath(ABSPATH) : false;
    $webroot = isset($_SERVER['DOCUMENT_ROOT']) && is_string($_SERVER['DOCUMENT_ROOT'])
        ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    if (!$directory || !is_dir($directory) || !is_writable($directory)
        || is_link(SANRO_STUDIO_PRIVATE_MASTER_DIR) || !$root || !$webroot
        || $directory === $root || strpos($directory, $root . DIRECTORY_SEPARATOR) === 0
        || $directory === $webroot || strpos($directory, $webroot . DIRECTORY_SEPARATOR) === 0) {
        return ['valid' => false, 'error' => 'unsafe_master_directory'];
    }
    $extension = $checked['mime'] === 'image/png' ? 'png' : 'jpg';
    $filename = strtolower($design_id) . '-' . $side . '-' . $checked['sha256'] . '.' . $extension;
    $path = $directory . DIRECTORY_SEPARATOR . $filename;
    $handle = @fopen($path, 'x');
    if ($handle === false) {
        if (is_link($path) || !is_file($path) || !is_readable($path)
            || filesize($path) !== $checked['bytes']) {
            return ['valid' => false, 'error' => 'master_write_failed'];
        }
        $existing = @file_get_contents($path);
        if (!is_string($existing) || !hash_equals($checked['sha256'], hash('sha256', $existing))) {
            return ['valid' => false, 'error' => 'master_integrity_failed'];
        }
        return ['valid' => true, 'file_id' => $filename, 'sha256' => $checked['sha256'],
            'mime' => $checked['mime'], 'bytes' => $checked['bytes']];
    }
    if (!@chmod($path, 0600)) {
        fclose($handle);
        @unlink($path);
        return ['valid' => false, 'error' => 'master_write_failed'];
    }
    $length = strlen($bytes);
    $offset = 0;
    while ($offset < $length) {
        $written = fwrite($handle, substr($bytes, $offset));
        if (!is_int($written) || $written <= 0) break;
        $offset += $written;
    }
    $flushed = $offset === $length && fflush($handle);
    $closed = fclose($handle);
    if (!$flushed || !$closed) {
        @unlink($path);
        return ['valid' => false, 'error' => 'master_write_failed'];
    }
    return ['valid' => true, 'file_id' => $filename, 'sha256' => $checked['sha256'],
        'mime' => $checked['mime'], 'bytes' => $checked['bytes']];
}

/**
 * Authenticated draft-owner boundary for future private master uploads.
 * This is deliberately internal: no REST registration or checkout activation.
 * Files are content-addressed and uploads are idempotent. Binding one active
 * master per side and enforcing upload quotas remain separate prerequisites.
 */
function sanro_studio_store_owned_draft_master($design_id, $owner_token, $side, $bytes) {
    if (!function_exists('sanro_studio_read_draft')) {
        return ['valid' => false, 'error' => 'draft_store_unavailable'];
    }
    $draft = sanro_studio_read_draft($design_id, $owner_token);
    if (empty($draft['valid'])) return $draft;
    if (($draft['status'] ?? null) !== 'draft') {
        return ['valid' => false, 'error' => 'draft_locked'];
    }
    if (!in_array($side, ['front', 'back'], true)) {
        return ['valid' => false, 'error' => 'invalid_master_target'];
    }
    // Enforce the byte limit before the filesystem writer is reached.
    $checked = sanro_studio_validate_master_bytes($bytes);
    if (empty($checked['valid'])) return $checked;
    return sanro_studio_store_private_master($design_id, $side, $bytes);
}

/**
 * Owner-authorized readback of a private master, with full content integrity
 * verification. No public URLs, REST routes, or download hooks are exposed.
 */
function sanro_studio_read_owned_draft_master($design_id, $owner_token, $side, $file_id) {
    if (!defined('SANRO_STUDIO_ENABLE_PRIVATE_MASTERS')
        || SANRO_STUDIO_ENABLE_PRIVATE_MASTERS !== true
        || !defined('SANRO_STUDIO_PRIVATE_MASTER_DIR')
        || !is_string(SANRO_STUDIO_PRIVATE_MASTER_DIR)) {
        return ['valid' => false, 'error' => 'master_storage_disabled'];
    }
    if (!is_string($design_id) || !is_string($file_id)
        || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $design_id)
        || !in_array($side, ['front', 'back'], true)) {
        return ['valid' => false, 'error' => 'invalid_master_reference'];
    }
    $prefix = strtolower($design_id) . '-' . $side . '-';
    if (strpos($file_id, $prefix) !== 0
        || !preg_match('/^[a-f0-9]{64}[.](?:png|jpg)$/D', substr($file_id, strlen($prefix)))) {
        return ['valid' => false, 'error' => 'invalid_master_reference'];
    }
    if (!function_exists('sanro_studio_read_draft')) {
        return ['valid' => false, 'error' => 'draft_store_unavailable'];
    }
    $draft = sanro_studio_read_draft($design_id, $owner_token);
    if (empty($draft['valid'])) return $draft;

    $directory = realpath(SANRO_STUDIO_PRIVATE_MASTER_DIR);
    $root = defined('ABSPATH') ? realpath(ABSPATH) : false;
    $webroot = isset($_SERVER['DOCUMENT_ROOT']) && is_string($_SERVER['DOCUMENT_ROOT'])
        ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    if (!$directory || !is_dir($directory) || is_link(SANRO_STUDIO_PRIVATE_MASTER_DIR)
        || !$root || !$webroot
        || $directory === $root || strpos($directory, $root . DIRECTORY_SEPARATOR) === 0
        || $directory === $webroot || strpos($directory, $webroot . DIRECTORY_SEPARATOR) === 0) {
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
    $expected_hash = substr($file_id, strlen($prefix), 64);
    $expected_ext = $checked['mime'] ?? null;
    $expected_ext = $expected_ext === 'image/png' ? 'png' : ($expected_ext === 'image/jpeg' ? 'jpg' : null);
    if (empty($checked['valid'])
        || !hash_equals($expected_hash, $checked['sha256'])
        || $expected_ext === null
        || substr($file_id, -strlen($expected_ext) - 1) !== '.' . $expected_ext) {
        return ['valid' => false, 'error' => 'master_integrity_failed'];
    }
    return ['valid' => true, 'bytes' => $bytes, 'mime' => $checked['mime'],
        'sha256' => $checked['sha256'], 'width' => $checked['width'],
        'height' => $checked['height']];
}

/**
 * Bind a verified private master to an editable draft using an atomic option
 * compare-and-swap. Never trust a client-provided file ID without readback.
 * No REST route is registered; upload quotas and buyer API remain prerequisites.
 */
function sanro_studio_bind_owned_draft_master($design_id, $owner_token, $side, $file_id) {
    if (!in_array($side, ['front', 'back'], true)) {
        return ['valid' => false, 'error' => 'invalid_master_target'];
    }
    $verified = sanro_studio_read_owned_draft_master($design_id, $owner_token, $side, $file_id);
    if (empty($verified['valid'])) return $verified;
    global $wpdb;
    if (!isset($wpdb) || !is_object($wpdb) || !isset($wpdb->options)
        || !preg_match('/^[A-Za-z0-9_]+$/D', $wpdb->options)
        || !method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'query')
        || !function_exists('get_option') || !function_exists('maybe_serialize')
        || !function_exists('wp_cache_delete')) {
        return ['valid' => false, 'error' => 'storage_unavailable'];
    }
    $key = 'sanro_studio_draft_' . $design_id;
    $before = get_option($key, false);
    if (!is_array($before) || ($before['status'] ?? null) !== 'draft'
        || !isset($before['token_hash'], $before['expires_at'])
        || !is_string($before['token_hash']) || !is_int($before['expires_at'])
        || $before['expires_at'] <= time()
        || !hash_equals($before['token_hash'], hash('sha256', $owner_token))) {
        return ['valid' => false, 'error' => 'draft_conflict'];
    }
    $after = $before;
    if (!isset($after['master_refs']) || !is_array($after['master_refs'])) {
        $after['master_refs'] = [];
    }
    $after['master_refs'][$side] = $file_id;
    $after['updated_at'] = max(time(), (int) ($before['updated_at'] ?? $before['created_at'] ?? 0) + 1);
    $changed = $wpdb->query($wpdb->prepare(
        "UPDATE `{$wpdb->options}` SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s",
        maybe_serialize($after), $key, maybe_serialize($before)
    ));
    if ($changed !== 1) {
        return ['valid' => false, 'error' => $changed === false ? 'storage_failed' : 'draft_conflict'];
    }
    wp_cache_delete($key, 'options');
    return ['valid' => true, 'design_id' => $design_id, 'side' => $side,
        'file_id' => $file_id, 'sha256' => $verified['sha256']];
}
