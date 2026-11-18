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
