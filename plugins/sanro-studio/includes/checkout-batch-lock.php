<?php
defined('ABSPATH') || exit;

/**
 * Optional classic-checkout-only draft lock, disabled by default.
 * This does not run in WooCommerce Blocks or on ordinary WooCommerce orders.
 */
function sanro_studio_stage_checkout_batch_lock_enabled() {
    return defined('SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK')
        && SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK === true
        && defined('SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT')
        && SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT === true
        && defined('SANRO_STUDIO_ENABLE_STAGING_DRAFT_AUTO_LOCK')
        && SANRO_STUDIO_ENABLE_STAGING_DRAFT_AUTO_LOCK === true;
}

/**
 * Validate each persisted line against the owner-authorized server quote.
 * The returned IDs are immutable WC order item IDs, not cart item keys.
 * No draft, order or option writes occur here.
 */
function sanro_studio_checkout_batch_lock_preflight($order, $contract) {
    if (!is_object($order) || !method_exists($order, 'get_id')
        || !method_exists($order, 'get_items')
        || !method_exists($order, 'get_currency')
        || !method_exists($order, 'get_total')
        || !is_int($order->get_id()) || $order->get_id() < 1
        || $order->get_currency() !== 'IDR'
        || !is_array($contract) || ($contract['valid'] ?? null) !== true
        || !function_exists('sanro_studio_prepare_size_cart_lines')
        || !function_exists('sanro_studio_validate_size_order_item')
        || !function_exists('sanro_studio_validate_order_grand_total')) {
        return ['valid' => false, 'error' => 'invalid_order'];
    }
    $expected = sanro_studio_prepare_size_cart_lines($contract);
    if (empty($expected['valid']) || !isset($expected['lines'])
        || !is_array($expected['lines'])) {
        return ['valid' => false, 'error' => 'invalid_order_contract'];
    }
    $expected_by_size = [];
    foreach ($expected['lines'] as $line) {
        $size = $line['sanro_studio_size'] ?? null;
        if (!is_string($size) || isset($expected_by_size[$size])) {
            return ['valid' => false, 'error' => 'duplicate_quote_size'];
        }
        $expected_by_size[$size] = $line;
    }
    $items = $order->get_items('line_item');
    if (!is_array($items) || !$items || count($items) !== count($expected_by_size)) {
        return ['valid' => false, 'error' => 'order_line_count_mismatch'];
    }
    $ids = [];
    foreach ($items as $item_id => $item) {
        // WC_Order::get_items normally returns an array keyed by item ID.
        // Do not accept unsaved, duplicate or caller-supplied IDs.
        if (!is_int($item_id) || $item_id < 1 || !is_object($item)
            || !method_exists($item, 'get_id')
            || !method_exists($item, 'get_meta')
            || !method_exists($item, 'get_product_id')
            || $item->get_id() !== $item_id
            || !is_int($item->get_product_id())
            || $item->get_product_id() !== ($contract['product_id'] ?? null)) {
            return ['valid' => false, 'error' => 'invalid_order_item'];
        }
        $size = $item->get_meta('_sanro_studio_size', true);
        $design_id = $item->get_meta('_sanro_studio_design_id', true);
        if (!is_string($size) || !isset($expected_by_size[$size])
            || !is_string($design_id)
            || $design_id !== ($contract['design_id'] ?? null)) {
            return ['valid' => false, 'error' => 'order_design_mismatch'];
        }
        // Persisted metadata must agree with the server-authorized DTF quote.
        // Checking WC totals alone cannot detect a modified quote label.
        $quote = $expected_by_size[$size];
        if ($item->get_meta('_sanro_studio_quote_currency', true) !== 'IDR'
            || (string) $item->get_meta('_sanro_studio_unit_price', true)
                !== (string) $quote['sanro_studio_unit_price']) {
            return ['valid' => false, 'error' => 'order_quote_metadata_mismatch'];
        }
        $checked = sanro_studio_validate_size_order_item($quote, $item);
        if (empty($checked['valid'])) {
            return ['valid' => false, 'error' => 'order_quote_mismatch'];
        }
        $ids[] = $item_id;
        unset($expected_by_size[$size]);
    }
    if ($expected_by_size || empty(sanro_studio_validate_order_grand_total(
        $contract, $order->get_total()
    )['valid'])) {
        return ['valid' => false, 'error' => 'order_total_mismatch'];
    }
    sort($ids, SORT_NUMERIC);
    return ['valid' => true, 'item_ids' => $ids, 'order_id' => $order->get_id()];
}

/**
 * Atomic, idempotent draft lock scoped to a single order containing multiple
 * size line items of ONE design. Uses the checkout's transient owner token;
 * never persists it to WooCommerce, carts or logs. No admin impersonation.
 */
function sanro_studio_lock_checkout_order_draft($order, $contract, $owner_token, $expected_design_hash) {
    if (!sanro_studio_stage_checkout_batch_lock_enabled()) {
        return ['valid' => false, 'error' => 'batch_lock_disabled'];
    }
    $verified = sanro_studio_checkout_batch_lock_preflight($order, $contract);
    if (empty($verified['valid'])) return $verified;
    $design_id = $contract['design_id'] ?? null;
    if (!is_string($design_id)
        || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $design_id)
        || !is_string($owner_token)
        || !preg_match('/^[a-f0-9]{64}$/D', $owner_token)
        || !is_string($expected_design_hash)
        || !preg_match('/^[a-f0-9]{64}$/D', $expected_design_hash)
        || !function_exists('get_option')
        || !function_exists('maybe_serialize')
        || !function_exists('wp_cache_delete')) {
        return ['valid' => false, 'error' => 'invalid_draft_authorization'];
    }
    global $wpdb;
    if (!is_object($wpdb) || !isset($wpdb->options)
        || !is_string($wpdb->options)
        || !preg_match('/^[A-Za-z0-9_]+$/D', $wpdb->options)
        || !method_exists($wpdb, 'query') || !method_exists($wpdb, 'prepare')) {
        return ['valid' => false, 'error' => 'storage_unavailable'];
    }
    $key = 'sanro_studio_draft_' . $design_id;
    $before = get_option($key, false);
    if (!is_array($before)
        || !isset($before['token_hash'], $before['design'], $before['expires_at'])
        || !is_string($before['token_hash'])
        || !is_array($before['design'])
        || !is_int($before['expires_at'])
        || $before['expires_at'] <= time()
        || !hash_equals($before['token_hash'], hash('sha256', $owner_token))
        || !hash_equals($expected_design_hash, hash('sha256', maybe_serialize($before['design'])))
        || ($before['product_id'] ?? null) !== ($contract['product_id'] ?? null)) {
        return ['valid' => false, 'error' => 'draft_conflict'];
    }
    $state = $before['status'] ?? null;
    if ($state === 'locked') {
        // Repeated WooCommerce hooks must not alter a different order's lock.
        $same_items = ($before['order_item_ids'] ?? null) === $verified['item_ids'];
        return $same_items && ($before['order_id'] ?? null) === $verified['order_id']
            ? ['valid' => true, 'already_locked' => true, 'item_ids' => $verified['item_ids']]
            : ['valid' => false, 'error' => 'draft_already_locked'];
    }
    if ($state !== 'draft'
        || !function_exists('sanro_studio_draft_image_binding_allowed')
        || !sanro_studio_draft_image_binding_allowed(
            $before['design'], is_array($before['master_refs'] ?? null) ? $before['master_refs'] : [], $design_id
        )) {
        return ['valid' => false, 'error' => 'draft_not_print_ready'];
    }
    $after = $before;
    $after['status'] = 'locked';
    $after['order_id'] = $verified['order_id'];
    $after['order_item_ids'] = $verified['item_ids'];
    unset($after['order_item_id']);
    $after['locked_at'] = time();
    $changed = $wpdb->query($wpdb->prepare(
        "UPDATE `{$wpdb->options}` SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s",
        maybe_serialize($after), $key, maybe_serialize($before)
    ));
    if ($changed !== 1) {
        return ['valid' => false, 'error' => $changed === false ? 'storage_failed' : 'draft_conflict'];
    }
    wp_cache_delete($key, 'options');
    return ['valid' => true, 'already_locked' => false, 'item_ids' => $verified['item_ids']];
}

/**
 * Called only after WooCommerce has persisted order and line item IDs. A
 * rejection aborts payment handoff rather than pretending artwork is linked.
 * An orphan pending order may remain and requires staging QA cleanup.
 */
function sanro_studio_stage_checkout_lock_created_order($order) {
    if (!sanro_studio_stage_checkout_batch_lock_enabled()) return;
    $verified = $GLOBALS['sanro_studio_checkout_verified'] ?? null;
    if (!is_array($verified) || empty($verified['contract']['valid'])) {
        // Ordinary WooCommerce orders must be unaffected by this staging-only
        // hook. An order with SANRO metadata must never bypass authorization.
        if (!is_object($order) || !method_exists($order, 'get_items')) return;
        $items = $order->get_items('line_item');
        if (!is_array($items)) return;
        foreach ($items as $item) {
            if (is_object($item) && method_exists($item, 'get_meta')
                && $item->get_meta('_sanro_studio_design_id', true) !== '') {
                throw new RuntimeException('SANRO Studio: verified checkout lock is missing.');
            }
        }
        return;
    }
    if (!isset($verified['lock_owner_token'], $verified['lock_design_hash'])) {
        throw new RuntimeException('SANRO Studio: verified checkout lock is missing.');
    }
    $result = sanro_studio_lock_checkout_order_draft(
        $order, $verified['contract'], $verified['lock_owner_token'], $verified['lock_design_hash']
    );
    if (!empty($result['valid'])
        && function_exists('sanro_studio_checkout_bind_existing_item_previews')) {
        // Preview binding is best-effort: missing artwork remains explicitly
        // unavailable in admin, without blocking a valid order or payment.
        sanro_studio_checkout_bind_existing_item_previews(
            $order, $verified['contract']['design_id'],
            $verified['lock_owner_token'], $result['item_ids']
        );
    }
    // Drop the transient secret whether the lock succeeded or not.
    unset($GLOBALS['sanro_studio_checkout_verified']['lock_owner_token']);
    if (empty($result['valid'])) {
        throw new RuntimeException('SANRO Studio: order design lock failed before payment.');
    }
}
