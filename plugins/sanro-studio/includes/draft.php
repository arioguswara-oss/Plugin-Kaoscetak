<?php
defined('ABSPATH') || exit;

/**
 * Private draft metadata only. Master images never enter WordPress.
 * No public routes, share tokens or checkout permission are exposed here.
 */
/** Private draft lifetime; expired records cannot be read, edited or quoted. */
function sanro_studio_draft_ttl_seconds() {
    return 7 * DAY_IN_SECONDS;
}

function sanro_studio_create_draft($product_id, $design) {
    $product_id = absint($product_id);
    if (!$product_id) return ['valid' => false, 'error' => 'invalid_product'];
    $result = sanro_studio_validate_product_design($product_id, $design);
    if (empty($result['valid'])) return $result;
    $token = bin2hex(random_bytes(32));
    $id = wp_generate_uuid4();
    $record = [
        'product_id' => $product_id,
        'design' => $design,
        'token_hash' => hash('sha256', $token),
        'created_at' => time(),
        'expires_at' => time() + sanro_studio_draft_ttl_seconds(),
        'status' => 'draft',
    ];
    // Store minimal validated geometry in non-autoloaded options, not a public post.
    if (!add_option('sanro_studio_draft_' . $id, $record, '', false)) {
        return ['valid' => false, 'error' => 'storage_failed'];
    }
    return ['valid' => true, 'design_id' => $id, 'owner_token' => $token];
}

/** Read draft only with the independent secret owner token. */
function sanro_studio_read_draft($id, $token) {
    if (!is_string($id) || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $id)
        || !is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return ['valid' => false, 'error' => 'invalid_credentials'];
    }
    $record = get_option('sanro_studio_draft_' . $id, false);
    if (!is_array($record) || !isset($record['token_hash'])
        || !is_string($record['token_hash'])
        || !hash_equals($record['token_hash'], hash('sha256', $token))
        || !isset($record['product_id'], $record['design'], $record['status'])
        || !is_int($record['product_id']) || $record['product_id'] < 1
        || !is_array($record['design'])
        || !is_string($record['status'])) {
        return ['valid' => false, 'error' => 'not_found'];
    }
    if (!isset($record['expires_at']) || !is_int($record['expires_at'])
        || $record['expires_at'] <= time()) {
        return ['valid' => false, 'error' => 'draft_expired'];
    }
    return [
        'valid' => true,
        'design_id' => $id,
        'product_id' => $record['product_id'],
        'design' => $record['design'],
        'status' => $record['status'],
        'expires_at' => $record['expires_at'],
    ];
}

/**
 * Update a private draft only with its owner token.
 * Revalidates geometry against current product settings before replacing metadata.
 * Never accepts client-supplied status, product ID or price.
 */
function sanro_studio_update_draft($id, $token, $design) {
    $existing = sanro_studio_read_draft($id, $token);
    if (empty($existing['valid'])) return $existing;
    if ($existing['status'] !== 'draft') {
        return ['valid' => false, 'error' => 'draft_locked'];
    }
    $validation = sanro_studio_validate_product_design($existing['product_id'], $design);
    if (empty($validation['valid'])) return $validation;
    $key = 'sanro_studio_draft_' . $id;
    $record = get_option($key, false);
    if (!is_array($record) || !isset($record['token_hash'])
        || !is_string($record['token_hash'])
        || !hash_equals($record['token_hash'], hash('sha256', $token))) {
        return ['valid' => false, 'error' => 'not_found'];
    }
    // Recheck current status: it may have changed after the first authorized read.
    if (($record['status'] ?? null) !== 'draft'
        || (int) ($record['product_id'] ?? 0) !== (int) $existing['product_id']) {
        return ['valid' => false, 'error' => 'draft_locked'];
    }
    // Compare-and-swap against the serialized option value. update_option() is not
    // atomic with respect to a concurrent lock or another editor save.
    global $wpdb;
    if (!isset($wpdb) || !is_object($wpdb) || !isset($wpdb->options)
        || !function_exists('maybe_serialize')
        || !function_exists('wp_cache_delete')) {
        return ['valid' => false, 'error' => 'storage_unavailable'];
    }
    $before = get_option($key, false);
    if (!is_array($before) || !isset($before['token_hash'])
        || !is_string($before['token_hash'])
        || !hash_equals($before['token_hash'], hash('sha256', $token))
        || ($before['status'] ?? null) !== 'draft'
        || ($before['product_id'] ?? null) !== $existing['product_id']
        || !isset($before['expires_at']) || !is_int($before['expires_at'])
        || $before['expires_at'] <= time()) {
        return ['valid' => false, 'error' => 'draft_conflict'];
    }
    $record = $before;
    $record['design'] = $design;
    $record['updated_at'] = max(time(), (int) ($before['updated_at'] ?? $before['created_at'] ?? 0) + 1);
    // Compare serialized bytes exactly. wpdb->update() uses the column's
    // collation, which can be case-insensitive and is unsafe for CAS.
    if (!method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'query')
        || !preg_match('/^[A-Za-z0-9_]+$/D', $wpdb->options)) {
        return ['valid' => false, 'error' => 'storage_unavailable'];
    }
    $changed = $wpdb->query($wpdb->prepare(
        "UPDATE `{$wpdb->options}` SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s",
        maybe_serialize($record), $key, maybe_serialize($before)
    ));
    if ($changed !== 1) {
        return ['valid' => false, 'error' => $changed === false ? 'storage_failed' : 'draft_conflict'];
    }
    wp_cache_delete($key, 'options');
    return ['valid' => true, 'design_id' => $id];
}

/**
 * Delete only expired, still-draft records in small batches.
 * Never touch locked/order-linked records. WP options are private metadata,
 * not the Google Drive master file store.
 */
function sanro_studio_cleanup_expired_drafts($limit = 50) {
    global $wpdb;
    if (!is_object($wpdb) || !isset($wpdb->options)
        || !preg_match('/^[A-Za-z0-9_]+$/D', $wpdb->options)
        || !method_exists($wpdb, 'get_col') || !method_exists($wpdb, 'prepare')
        || !method_exists($wpdb, 'esc_like')
        || !function_exists('delete_option')) return 0;
    $limit = max(1, min(100, (int) $limit));
    // A bounded scan avoids loading every draft into memory.
    $keys = $wpdb->get_col($wpdb->prepare(
        "SELECT option_name FROM `{$wpdb->options}` WHERE option_name LIKE %s ORDER BY option_id ASC LIMIT %d",
        $wpdb->esc_like('sanro_studio_draft_') . '%', $limit
    ));
    if (!is_array($keys)) return 0;
    $deleted = 0;
    foreach ($keys as $key) {
        if (!is_string($key) || !preg_match('/^sanro_studio_draft_[a-f0-9-]{36}$/iD', $key)) continue;
        $record = get_option($key, false);
        if (!is_array($record) || ($record['status'] ?? null) !== 'draft') continue;
        $expires = $record['expires_at'] ?? null;
        if (!is_int($expires) || $expires > time()) continue;
        if (delete_option($key)) $deleted++;
    }
    return $deleted;
}
