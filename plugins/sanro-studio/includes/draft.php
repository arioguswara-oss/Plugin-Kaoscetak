<?php
defined('ABSPATH') || exit;

/**
 * Private draft metadata only. Master images never enter WordPress.
 * No public routes, share tokens or checkout permission are exposed here.
 */
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
    return [
        'valid' => true,
        'design_id' => $id,
        'product_id' => $record['product_id'],
        'design' => $record['design'],
        'status' => $record['status'],
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
    $record['design'] = $design;
    // WordPress update_option() returns false when the stored value is unchanged.
    // Keep timestamps monotonic so a legitimate rapid repeat save is not reported as failure.
    $record['updated_at'] = max(time(), (int) ($record['updated_at'] ?? $record['created_at'] ?? 0) + 1);
    // Compare-and-swap against the serialized option value. update_option() is not
    // atomic with respect to a concurrent lock or another editor save.
    global $wpdb;
    if (!isset($wpdb) || !is_object($wpdb) || !isset($wpdb->options)
        || !method_exists($wpdb, 'update') || !function_exists('maybe_serialize')
        || !function_exists('wp_cache_delete')) {
        return ['valid' => false, 'error' => 'storage_unavailable'];
    }
    $before = get_option($key, false);
    if (!is_array($before) || !isset($before['token_hash'])
        || !is_string($before['token_hash'])
        || !hash_equals($before['token_hash'], hash('sha256', $token))
        || ($before['status'] ?? null) !== 'draft'
        || ($before['product_id'] ?? null) !== $existing['product_id']) {
        return ['valid' => false, 'error' => 'draft_conflict'];
    }
    $record = $before;
    $record['design'] = $design;
    $record['updated_at'] = max(time(), (int) ($before['updated_at'] ?? $before['created_at'] ?? 0) + 1);
    $changed = $wpdb->update(
        $wpdb->options,
        ['option_value' => maybe_serialize($record)],
        ['option_name' => $key, 'option_value' => maybe_serialize($before)],
        ['%s'],
        ['%s', '%s']
    );
    if ($changed !== 1) {
        return ['valid' => false, 'error' => $changed === false ? 'storage_failed' : 'draft_conflict'];
    }
    wp_cache_delete($key, 'options');
    return ['valid' => true, 'design_id' => $id];
}
