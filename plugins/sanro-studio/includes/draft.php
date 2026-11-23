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
    // A strictly empty, short-lived draft can reserve an owner token for an
    // image-only design. It cannot be quoted or checked out until an image
    // master is bound and valid geometry is saved.
    $empty_bootstrap = function_exists('sanro_studio_image_bootstrap_allowed')
        && sanro_studio_image_bootstrap_allowed($design);
    if (!$empty_bootstrap) {
        $result = sanro_studio_validate_product_design($product_id, $design);
        if (empty($result['valid'])) return $result;
    }
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
        'design_hash' => hash('sha256', maybe_serialize($record['design'])),
        'status' => $record['status'],
        'expires_at' => $record['expires_at'],
        'owner_email_verified'=>function_exists('sanro_studio_repeat_verified_email')&&sanro_studio_repeat_verified_email($record)!=='',
        'master_refs' => is_array($record['master_refs'] ?? null)
            ? array_intersect_key($record['master_refs'], array_flip(['front', 'back'])) : [],
    ];
}

/**
 * Update a private draft only with its owner token.
 * Revalidates geometry against current product settings before replacing metadata.
 * Never accepts client-supplied status, product ID or price.
 */
function sanro_studio_update_draft($id, $token, $design, $expected_design_hash = null) {
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
    // Compare the client's last observed revision before applying any edits.
    // Prevent stale tabs from silently replacing another tab's saved design.
    if ($expected_design_hash !== null
        && (!is_string($expected_design_hash)
            || !preg_match('/^[a-f0-9]{64}$/D', $expected_design_hash)
            || !hash_equals(hash('sha256', maybe_serialize($before['design'])), $expected_design_hash))) {
        return ['valid' => false, 'error' => 'draft_conflict'];
    }
    // A retry of an unchanged save must not revoke verified master bindings.
    // The caller may legitimately retry after a network timeout.
    if (hash_equals(
        hash('sha256', maybe_serialize($before['design'])),
        hash('sha256', maybe_serialize($design))
    )) {
        return ['valid' => true, 'design_id' => $id, 'unchanged' => true];
    }
    // Image geometry may only reference masters already bound to this
    // exact owner-authorized draft. This check runs against the CAS snapshot.
    if (!function_exists('sanro_studio_draft_image_binding_allowed')
        || !sanro_studio_draft_image_binding_allowed(
            $design, is_array($before['master_refs'] ?? null) ? $before['master_refs'] : [], $id
        )) {
        return ['valid' => false, 'error' => 'image_master_unbound'];
    }
    $record = $before;
    $record['design'] = $design;
    // Any geometry/text change invalidates both lightweight previews.
    // Buyer must regenerate them from the saved design before checkout.
    unset($record['preview_refs'], $record['preview_design_hash']);
    // Retain a master only when the new design actually contains an image
    // on that side. A removed image must not leave an active master binding.
    $record['master_refs'] = [];
    foreach (['front', 'back'] as $side) {
        foreach (($design[$side]['objects'] ?? []) as $object) {
            if (is_array($object) && ($object['type'] ?? null) === 'image'
                && isset($before['master_refs'][$side])) {
                $record['master_refs'][$side] = $before['master_refs'][$side];
                break;
            }
        }
    }
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
 * Bounded, cursor-based cleanup. The caller advances next_cursor on every
 * invocation, including when no records were deleted. No cron is registered.
 * Deletion uses binary compare-and-swap to avoid racing with a draft update.
 */
function sanro_studio_cleanup_expired_drafts($limit = 50, $after_id = 0) {
    global $wpdb;
    $empty = ['scanned' => 0, 'deleted' => 0, 'next_cursor' => max(0, (int) $after_id), 'has_more' => false];
    if (!is_object($wpdb) || !isset($wpdb->options)
        || !preg_match('/^[A-Za-z0-9_]+$/D', $wpdb->options)
        || !method_exists($wpdb, 'get_results') || !method_exists($wpdb, 'prepare')
        || !method_exists($wpdb, 'esc_like') || !method_exists($wpdb, 'query')
        || !function_exists('maybe_unserialize') || !function_exists('wp_cache_delete')) {
        return $empty + ['error' => 'storage_unavailable'];
    }
    $limit = max(1, min(100, (int) $limit));
    $after_id = max(0, (int) $after_id);
    if (!defined('ARRAY_A')) return $empty + ['error' => 'storage_unavailable'];
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT option_id, option_name, option_value FROM `{$wpdb->options}`
         WHERE option_id > %d AND option_name LIKE %s
         ORDER BY option_id ASC LIMIT %d",
        $after_id, $wpdb->esc_like('sanro_studio_draft_') . '%', $limit + 1
    ), ARRAY_A);
    if (!is_array($rows)) return $empty + ['error' => 'storage_failed'];
    $has_more = count($rows) > $limit;
    $batch = array_slice($rows, 0, $limit);
    $deleted = 0;
    $cursor = $after_id;
    foreach ($batch as $row) {
        if (!isset($row['option_id'], $row['option_name'], $row['option_value'])) continue;
        $cursor = max($cursor, (int) $row['option_id']);
        $key = $row['option_name'];
        if (!is_string($key) || !preg_match('/^sanro_studio_draft_[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $key)) continue;
        $record = maybe_unserialize($row['option_value']);
        if (!is_array($record) || ($record['status'] ?? null) !== 'draft') continue;
        $expires = $record['expires_at'] ?? null;
        if (!is_int($expires) || $expires > time()) continue;
        $affected = $wpdb->query($wpdb->prepare(
            "DELETE FROM `{$wpdb->options}` WHERE option_id = %d
             AND option_name = %s AND BINARY option_value = BINARY %s",
            (int) $row['option_id'], $key, $row['option_value']
        ));
        if ($affected === 1) {
            $deleted++;
            wp_cache_delete($key, 'options');
            wp_cache_delete('alloptions', 'options');
            wp_cache_delete('notoptions', 'options');
        }
    }
    return ['scanned' => count($batch), 'deleted' => $deleted,
        'next_cursor' => $cursor, 'has_more' => $has_more];
}

/**
 * Internal-only atomic draft lock for future order integration.
 * Never register as a public REST action. This does not enable checkout.
 */
function sanro_studio_lock_draft_for_order($id, $token, $order_id, $item_id = null) {
    $draft = sanro_studio_read_draft($id, $token);
    if (empty($draft['valid'])) return $draft;
    if (($draft['status'] ?? null) !== 'draft') {
        return ['valid' => false, 'error' => 'draft_locked'];
    }
    if (!is_int($order_id) || $order_id < 1) {
        return ['valid' => false, 'error' => 'invalid_order'];
    }
    // Direct calls must not bypass the administrator-only order boundary.
    if (!function_exists('current_user_can') || !current_user_can('manage_woocommerce')) {
        return ['valid' => false, 'error' => 'forbidden'];
    }
    if ($item_id !== null && (!is_int($item_id) || $item_id < 1)) {
        return ['valid' => false, 'error' => 'invalid_order_item'];
    }
    global $wpdb;
    if (!isset($wpdb) || !is_object($wpdb) || !isset($wpdb->options)
        || !preg_match('/^[A-Za-z0-9_]+$/D', $wpdb->options)
        || !method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'query')
        || !function_exists('maybe_serialize') || !function_exists('wp_cache_delete')) {
        return ['valid' => false, 'error' => 'storage_unavailable'];
    }
    $key = 'sanro_studio_draft_' . $id;
    $before = get_option($key, false);
    if (!is_array($before) || ($before['status'] ?? null) !== 'draft'
        || !isset($before['token_hash'], $before['expires_at'])
        || !is_string($before['token_hash']) || !is_int($before['expires_at'])
        || $before['expires_at'] <= time()
        || !hash_equals($before['token_hash'], hash('sha256', $token))) {
        return ['valid' => false, 'error' => 'draft_conflict'];
    }
    $after = $before;
    $after['status'] = 'locked';
    $after['order_id'] = $order_id;
    if ($item_id !== null) $after['order_item_id'] = $item_id;
    $after['locked_at'] = time();
    $changed = $wpdb->query($wpdb->prepare(
        "UPDATE `{$wpdb->options}` SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s",
        maybe_serialize($after), $key, maybe_serialize($before)
    ));
    if ($changed !== 1) {
        return ['valid' => false, 'error' => $changed === false ? 'storage_failed' : 'draft_conflict'];
    }
    wp_cache_delete($key, 'options');
    return ['valid' => true, 'design_id' => $id, 'order_id' => $order_id, 'status' => 'locked'];
}

/**
 * Admin-only order-aware lock boundary. Checks an actual WooCommerce order and
 * one exact line item before delegating to the atomic draft lock.
 * No public route, checkout hook or token persistence.
 */
function sanro_studio_lock_draft_for_order_item($id, $token, $order, $item_id) {
    if (!function_exists('current_user_can') || !current_user_can('manage_woocommerce')) {
        return ['valid' => false, 'error' => 'forbidden'];
    }
    if (!is_object($order) || !method_exists($order, 'get_id')
        || !method_exists($order, 'get_items')
        || !is_int($item_id) || $item_id < 1) {
        return ['valid' => false, 'error' => 'invalid_order_item'];
    }
    $order_id = $order->get_id();
    if (!is_int($order_id) || $order_id < 1) {
        return ['valid' => false, 'error' => 'invalid_order'];
    }
    if (!method_exists($order, 'get_currency') || $order->get_currency() !== 'IDR') {
        return ['valid' => false, 'error' => 'order_currency_mismatch'];
    }
    $items = $order->get_items('line_item');
    if (!is_array($items) || !isset($items[$item_id])
        || !is_object($items[$item_id])
        || !method_exists($items[$item_id], 'get_product_id')) {
        return ['valid' => false, 'error' => 'invalid_order_item'];
    }
    $draft = sanro_studio_read_draft($id, $token);
    if (empty($draft['valid'])) return $draft;
    if (($draft['status'] ?? null) !== 'draft') {
        return ['valid' => false, 'error' => 'draft_locked'];
    }
    $item_product_id = $items[$item_id]->get_product_id();
    if (!is_int($item_product_id) || $draft['product_id'] !== $item_product_id) {
        return ['valid' => false, 'error' => 'product_mismatch'];
    }
    // Bind the exact order line to the authorized draft, not merely its product.
    $identity = sanro_studio_validate_order_item_design_identity($items[$item_id], $id);
    if (empty($identity['valid'])) return $identity;
    // Requote the authoritative saved design at the transaction boundary.
    // Never infer price or quantities from client-supplied order metadata.
    if (!function_exists('sanro_studio_quote_size_quantities')
        || !is_array($draft['design'] ?? null)
        || !is_array($draft['design']['quantities'] ?? null)) {
        return ['valid' => false, 'error' => 'quote_unavailable'];
    }
    $design = $draft['design'];
    $quote = sanro_studio_quote_size_quantities(
        (int) $draft['product_id'], $design, $design['quantities'],
        $design['long_sleeve'] ?? false
    );
    if (empty($quote['valid'])) return $quote;
    if (!isset($quote['total_quantity'], $quote['total_price'])
        || !is_int($quote['total_quantity']) || $quote['total_quantity'] < 1
        || !is_int($quote['total_price']) || $quote['total_price'] <= 0
        || !method_exists($items[$item_id], 'get_quantity')
        || !is_int($items[$item_id]->get_quantity())
        || $items[$item_id]->get_quantity() !== $quote['total_quantity']) {
        return ['valid' => false, 'error' => 'order_quantity_mismatch'];
    }
    // WooCommerce subtotal is pre-discount and pre-tax. Compare it to the
    // authoritative quote; never compare the discounted line total or taxes.
    $amount = sanro_studio_validate_order_item_quote_amount($items[$item_id], $quote);
    if (empty($amount['valid'])) return $amount;
    return sanro_studio_lock_draft_for_order($id, $token, $order_id, $item_id);
}

/**
 * Strict IDR line subtotal reconciliation before locking a design.
 * Discounts alter line total; tax is recorded separately by WooCommerce.
 * This function never mutates the order or approves checkout.
 */
function sanro_studio_validate_order_item_quote_amount($item, $quote) {
    if (!is_object($item) || !method_exists($item, 'get_subtotal')
        || !is_array($quote) || empty($quote['valid'])
        || ($quote['currency'] ?? null) !== 'IDR'
        || !isset($quote['total_price']) || !is_int($quote['total_price'])
        || $quote['total_price'] <= 0) {
        return ['valid' => false, 'error' => 'order_amount_unavailable'];
    }
    $subtotal = $item->get_subtotal();
    if ((!is_string($subtotal) && !is_int($subtotal) && !is_float($subtotal))
        || !preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/D', (string) $subtotal)) {
        return ['valid' => false, 'error' => 'order_amount_unavailable'];
    }
    // No floats: preserve exact WooCommerce decimal precision for IDR.
    $parts = explode('.', (string) $subtotal, 2);
    $fraction = str_pad($parts[1] ?? '', 2, '0');
    if ($parts[0] !== (string) $quote['total_price'] || $fraction !== '00') {
        return ['valid' => false, 'error' => 'order_amount_mismatch'];
    }
    return ['valid' => true, 'subtotal' => $quote['total_price'], 'currency' => 'IDR'];
}

/**
 * Fail closed when a WooCommerce line item is not explicitly bound to the
 * authorized draft. Never trust an order line merely because its product matches.
 */
function sanro_studio_validate_order_item_design_identity($item, $design_id) {
    if (!is_object($item) || !method_exists($item, 'get_meta')
        || !is_string($design_id) || $design_id === '') {
        return ['valid' => false, 'error' => 'order_design_mismatch'];
    }
    $bound_id = $item->get_meta('_sanro_studio_design_id', true);
    if (!is_string($bound_id) || !hash_equals($design_id, $bound_id)) {
        return ['valid' => false, 'error' => 'order_design_mismatch'];
    }
    return ['valid' => true, 'design_id' => $design_id];
}

/**
 * Internal admin-only metadata binding for an existing WooCommerce order line.
 * Requires the same authorized draft and exact line identity; never called
 * from a public checkout hook. No owner token is persisted in item metadata.
 */
function sanro_studio_bind_order_item_design_identity($id, $token, $order, $item_id) {
    if (!function_exists('current_user_can') || !current_user_can('manage_woocommerce')) {
        return ['valid' => false, 'error' => 'forbidden'];
    }
    if (!is_object($order) || !method_exists($order, 'get_items')
        || !method_exists($order, 'get_id') || !is_int($item_id) || $item_id < 1
        || !is_int($order->get_id()) || $order->get_id() < 1) {
        return ['valid' => false, 'error' => 'invalid_order_item'];
    }
    $items = $order->get_items('line_item');
    if (!is_array($items) || !isset($items[$item_id])
        || !is_object($items[$item_id])) {
        return ['valid' => false, 'error' => 'invalid_order_item'];
    }
    $item = $items[$item_id];
    if (!method_exists($item, 'get_product_id') || !method_exists($item, 'get_quantity')
        || !method_exists($item, 'get_meta') || !method_exists($item, 'update_meta_data')
        || !method_exists($item, 'get_subtotal')) {
        return ['valid' => false, 'error' => 'invalid_order_item'];
    }
    $draft = sanro_studio_read_draft($id, $token);
    if (empty($draft['valid'])) return $draft;
    if (($draft['status'] ?? null) !== 'draft') {
        return ['valid' => false, 'error' => 'draft_locked'];
    }
    if ($draft['product_id'] !== $item->get_product_id()) {
        return ['valid' => false, 'error' => 'product_mismatch'];
    }
    if (!method_exists($order, 'get_currency') || $order->get_currency() !== 'IDR'
        || !function_exists('sanro_studio_quote_size_quantities')
        || !is_array($draft['design'] ?? null)
        || !is_array($draft['design']['quantities'] ?? null)) {
        return ['valid' => false, 'error' => 'quote_unavailable'];
    }
    $design = $draft['design'];
    $quote = sanro_studio_quote_size_quantities($draft['product_id'], $design,
        $design['quantities'], $design['long_sleeve'] ?? false);
    if (empty($quote['valid']) || !isset($quote['total_quantity'])
        || !is_int($quote['total_quantity']) || $quote['total_quantity'] < 1
        || $item->get_quantity() !== $quote['total_quantity']) {
        return ['valid' => false, 'error' => 'order_quantity_mismatch'];
    }
    $amount = sanro_studio_validate_order_item_quote_amount($item, $quote);
    if (empty($amount['valid'])) return $amount;
    $existing = $item->get_meta('_sanro_studio_design_id', true);
    if ($existing !== '' && $existing !== null) {
        return ['valid' => false, 'error' => 'order_design_already_bound'];
    }
    $item->update_meta_data('_sanro_studio_design_id', $draft['design_id']);
    // The caller must persist the order item through WooCommerce after
    // verifying the result. This helper does not save or lock the draft.
    return ['valid' => true, 'design_id' => $draft['design_id'],
        'order_item_id' => $item_id, 'requires_item_save' => true,
        'checkout_enabled' => false];
}
