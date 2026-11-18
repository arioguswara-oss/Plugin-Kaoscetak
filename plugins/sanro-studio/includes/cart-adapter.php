<?php
defined('ABSPATH') || exit;

/**
 * Explicit staging-only WooCommerce cart adapter. Never registers a public
 * endpoint or hook. The owner-authorized contract is supplied transiently by
 * the caller; owner credentials must not enter WC session/cart metadata.
 */
function sanro_studio_stage_add_cart_lines($contract, $cart) {
    if (!defined('SANRO_STUDIO_ENABLE_STAGING_CART')
        || SANRO_STUDIO_ENABLE_STAGING_CART !== true
        || !function_exists('sanro_studio_prepare_size_cart_lines')
        || !function_exists('sanro_studio_validate_size_cart_lines')
        || !is_object($cart) || !method_exists($cart, 'add_to_cart')
        || !method_exists($cart, 'remove_cart_item')
        || !method_exists($cart, 'get_cart')
        || !function_exists('get_woocommerce_currency')
        || get_woocommerce_currency() !== 'IDR') {
        return ['valid' => false, 'error' => 'staging_cart_disabled'];
    }
    $prepared = sanro_studio_prepare_size_cart_lines($contract);
    if (empty($prepared['valid'])) return $prepared;
    $verified = sanro_studio_validate_size_cart_lines($contract, $prepared['lines']);
    if (empty($verified['valid'])) return $verified;
    // Reject product configurations that require separate variation IDs or
    // extra options. These need a dedicated variation-aware adapter.
    if (!function_exists('wc_get_product')) {
        return ['valid' => false, 'error' => 'product_unavailable'];
    }
    $product = wc_get_product($contract['product_id']);
    if (!$product || !method_exists($product, 'is_type')
        || !$product->is_type('simple')
        || !method_exists($product, 'is_purchasable')
        || !$product->is_purchasable()) {
        return ['valid' => false, 'error' => 'unsupported_product_type'];
    }
    // Do not touch an existing cart: price calculation and rollback are not
    // yet integrated with WooCommerce totals and session restoration.
    if (count($cart->get_cart()) !== 0) {
        return ['valid' => false, 'error' => 'cart_must_be_empty'];
    }
    // Read-only candidate output for the future transactional adapter.
    // Calling add_to_cart here before server-owned unit-price hooks exist
    // would silently charge the product's ordinary catalog price.
    return ['valid' => true, 'lines' => $prepared['lines'],
        'requires_price_hook' => true, 'cart_mutation_enabled' => false];
}

/**
 * Apply a server-authorized per-size unit price to a WooCommerce cart product
 * object. Caller must reauthorize the draft and pass its fresh quote; no
 * client-provided amount is trusted. No global price hook is registered.
 */
function sanro_studio_stage_price_cart_line($contract, $line, $product) {
    if (!defined('SANRO_STUDIO_ENABLE_STAGING_CART')
        || SANRO_STUDIO_ENABLE_STAGING_CART !== true
        || !is_object($product) || !method_exists($product, 'set_price')
        || !function_exists('get_woocommerce_currency')
        || get_woocommerce_currency() !== 'IDR') {
        return ['valid' => false, 'error' => 'staging_pricing_disabled'];
    }
    $expected = sanro_studio_prepare_size_cart_lines($contract);
    if (empty($expected['valid'])) return $expected;
    if (!is_array($line) || !isset($line['sanro_studio_size'])
        || !is_string($line['sanro_studio_size'])) {
        return ['valid' => false, 'error' => 'invalid_cart_line'];
    }
    $matched = null;
    foreach ($expected['lines'] as $candidate) {
        if ($candidate['sanro_studio_size'] === $line['sanro_studio_size']) {
            $matched = $candidate;
            break;
        }
    }
    if ($matched === null) return ['valid' => false, 'error' => 'unknown_cart_size'];
    foreach (['product_id', 'quantity', 'sanro_studio_design_id',
        'sanro_studio_size', 'sanro_studio_unit_price',
        'sanro_studio_line_subtotal', 'sanro_studio_quote_currency'] as $key) {
        if (!array_key_exists($key, $line) || $line[$key] !== $matched[$key]) {
            return ['valid' => false, 'error' => 'cart_line_mismatch'];
        }
    }
    $product->set_price((string) $matched['sanro_studio_unit_price']);
    return ['valid' => true, 'unit_price' => $matched['sanro_studio_unit_price'],
        'currency' => 'IDR', 'requires_totals_recalculation' => true];
}

/**
 * Price every line only after the complete cart matches a fresh server quote.
 * This method is for an isolated staging transaction; no WooCommerce hook is
 * attached automatically. Caller must recalculate WC totals afterwards.
 */
function sanro_studio_stage_price_cart_batch($contract, $cart_lines) {
    if (!defined('SANRO_STUDIO_ENABLE_STAGING_CART')
        || SANRO_STUDIO_ENABLE_STAGING_CART !== true
        || !is_array($cart_lines)) {
        return ['valid' => false, 'error' => 'staging_pricing_disabled'];
    }
    $snapshots = [];
    foreach ($cart_lines as $line) {
        if (!is_array($line) || !isset($line['data'])
            || !is_object($line['data']) || !method_exists($line['data'], 'set_price')) {
            return ['valid' => false, 'error' => 'invalid_cart_product'];
        }
        $snapshot = $line;
        unset($snapshot['data']);
        $snapshots[] = $snapshot;
    }
    $verified = sanro_studio_validate_size_cart_lines($contract, $snapshots);
    if (empty($verified['valid'])) return $verified;
    // No product price is mutated until every line has passed validation.
    foreach ($cart_lines as $line) {
        $priced = sanro_studio_stage_price_cart_line($contract, $line, $line['data']);
        if (empty($priced['valid'])) return $priced;
    }
    return ['valid' => true, 'total_quantity' => $verified['total_quantity'],
        'total_price' => $verified['total_price'], 'requires_totals_recalculation' => true];
}

/**
 * Opt-in staging transaction: add a complete, empty-cart design batch, apply
 * verified per-size prices and recalculate totals. No endpoint/hook is exposed.
 * A separate checkout/session gate is still required before buyer use.
 */
function sanro_studio_stage_commit_cart_lines($contract, $cart) {
    if (!defined('SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT')
        || SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT !== true
        || !defined('SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK')
        || SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK !== true
        || !is_object($cart) || !method_exists($cart, 'calculate_totals')) {
        return ['valid' => false, 'error' => 'cart_commit_disabled'];
    }
    $preflight = sanro_studio_stage_add_cart_lines($contract, $cart);
    if (empty($preflight['valid'])) return $preflight;
    $keys = [];
    foreach ($preflight['lines'] as $line) {
        // Explicit unique per-size identity prevents WooCommerce merging sizes.
        $meta = [
            'sanro_studio_design_id' => $line['sanro_studio_design_id'],
            'sanro_studio_size' => $line['sanro_studio_size'],
            'sanro_studio_unit_price' => $line['sanro_studio_unit_price'],
            'sanro_studio_line_subtotal' => $line['sanro_studio_line_subtotal'],
            'sanro_studio_quote_currency' => 'IDR',
        ];
        $key = $cart->add_to_cart($line['product_id'], $line['quantity'], 0, [], $meta);
        if (!is_string($key) || $key === '' || in_array($key, $keys, true)) {
            foreach ($keys as $added) $cart->remove_cart_item($added);
            return ['valid' => false, 'error' => 'cart_add_failed'];
        }
        $keys[] = $key;
    }
    $actual = $cart->get_cart();
    $selected = [];
    foreach ($keys as $key) {
        if (!isset($actual[$key]) || !is_array($actual[$key])) {
            foreach ($keys as $added) $cart->remove_cart_item($added);
            return ['valid' => false, 'error' => 'cart_line_missing'];
        }
        $selected[] = $actual[$key];
    }
    $priced = sanro_studio_stage_price_cart_batch($contract, $selected);
    if (empty($priced['valid'])) {
        foreach ($keys as $added) $cart->remove_cart_item($added);
        return $priced;
    }
    $cart->calculate_totals();
    return ['valid' => true, 'cart_keys' => $keys,
        'total_price' => $priced['total_price'],
        'requires_session_checkout_guard' => true];
}

/**
 * Restore and reprice a cart after session reload ONLY when the caller
 * presents the draft owner token again. Never persist that token in WC.
 * A missing, expired, modified or revoked draft fails closed.
 */
function sanro_studio_stage_reauthorize_cart($design_id, $owner_token, $product_id, $cart) {
    if (!defined('SANRO_STUDIO_ENABLE_STAGING_CART')
        || SANRO_STUDIO_ENABLE_STAGING_CART !== true
        || !is_object($cart) || !method_exists($cart, 'get_cart')
        || !method_exists($cart, 'calculate_totals')
        || !is_int($product_id) || $product_id < 1
        || !function_exists('sanro_studio_prepare_cart_design_contract')) {
        return ['valid' => false, 'error' => 'cart_reauthorization_disabled'];
    }
    $contract = sanro_studio_prepare_cart_design_contract($design_id, $owner_token, $product_id);
    if (empty($contract['valid'])) return $contract;
    $actual = $cart->get_cart();
    if (!is_array($actual) || !$actual) {
        return ['valid' => false, 'error' => 'cart_empty'];
    }
    $priced = sanro_studio_stage_price_cart_batch($contract, array_values($actual));
    if (empty($priced['valid'])) return $priced;
    $cart->calculate_totals();
    return ['valid' => true, 'design_id' => $contract['design_id'],
        'total_price' => $priced['total_price'],
        'requires_checkout_reauthorization' => true];
}
