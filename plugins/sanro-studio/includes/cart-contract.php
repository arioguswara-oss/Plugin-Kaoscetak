<?php
defined('ABSPATH') || exit;

/**
 * Read-only draft-to-cart contract. This is NOT a cart insertion or checkout
 * authorization. Never persist owner tokens in WooCommerce cart/session data.
 */
function sanro_studio_prepare_cart_design_contract($design_id, $owner_token, $product_id) {
    if (!function_exists('sanro_studio_read_draft')
        || !function_exists('sanro_studio_quote_size_quantities')) {
        return ['valid' => false, 'error' => 'contract_unavailable'];
    }
    $draft = sanro_studio_read_draft($design_id, $owner_token);
    if (empty($draft['valid'])) return $draft;
    if (($draft['status'] ?? null) !== 'draft') {
        return ['valid' => false, 'error' => 'draft_locked'];
    }
    if (!is_int($product_id) || $product_id < 1
        || $product_id !== (int) $draft['product_id']) {
        return ['valid' => false, 'error' => 'product_mismatch'];
    }
    $design = $draft['design'] ?? null;
    if (!is_array($design) || !isset($design['quantities'])
        || !is_array($design['quantities'])) {
        return ['valid' => false, 'error' => 'invalid_design'];
    }
    $quote = sanro_studio_quote_size_quantities(
        $product_id, $design, $design['quantities'], $design['long_sleeve'] ?? false
    );
    if (empty($quote['valid'])) return $quote;
    if (!isset($quote['total_quantity'], $quote['total_price'])
        || !is_int($quote['total_quantity']) || $quote['total_quantity'] < 1
        || !is_int($quote['total_price']) || $quote['total_price'] <= 0
        || ($quote['currency'] ?? null) !== 'IDR') {
        return ['valid' => false, 'error' => 'invalid_quote'];
    }
    // Only opaque identity and server-computed quotation; no owner secret,
    // raw artwork, master file path or client-supplied pricing.
    return [
        'valid' => true,
        'design_id' => $draft['design_id'],
        'product_id' => $product_id,
        'quote' => $quote,
        'checkout_enabled' => false,
        'cart_mutation_enabled' => false,
    ];
}

/**
 * Verify a cart item's public design identity against a freshly authorized
 * draft contract. This does not add products or change WooCommerce prices.
 * Caller must hold the owner token transiently; never put it in cart metadata.
 */
function sanro_studio_validate_cart_design_identity($contract, $cart_item) {
    if (!is_array($contract) || empty($contract['valid'])
        || !is_array($cart_item)
        || !isset($contract['design_id'], $contract['product_id'], $contract['quote'])
        || !is_string($contract['design_id']) || !is_int($contract['product_id'])
        || !isset($cart_item['product_id'], $cart_item['sanro_studio_design_id'])
        || !is_int($cart_item['product_id']) || !is_string($cart_item['sanro_studio_design_id'])
        || !hash_equals($contract['design_id'], $cart_item['sanro_studio_design_id'])
        || $contract['product_id'] !== $cart_item['product_id']) {
        return ['valid' => false, 'error' => 'cart_identity_mismatch'];
    }
    if (!is_array($contract['quote']) || !isset($contract['quote']['total_quantity'])
        || !is_int($contract['quote']['total_quantity'])
        || !isset($cart_item['quantity']) || !is_int($cart_item['quantity'])
        || $cart_item['quantity'] !== $contract['quote']['total_quantity']) {
        return ['valid' => false, 'error' => 'cart_quantity_mismatch'];
    }
    return ['valid' => true, 'design_id' => $contract['design_id'],
        'product_id' => $contract['product_id'], 'quantity' => $cart_item['quantity'],
        'checkout_enabled' => false];
}
