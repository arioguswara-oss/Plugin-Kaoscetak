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
    if (!is_string($id) || !preg_match('/^[a-f0-9-]{36}$/i', $id)
        || !is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return ['valid' => false, 'error' => 'invalid_credentials'];
    }
    $record = get_option('sanro_studio_draft_' . $id, false);
    if (!is_array($record) || !isset($record['token_hash'])
        || !is_string($record['token_hash'])
        || !hash_equals($record['token_hash'], hash('sha256', $token))) {
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
