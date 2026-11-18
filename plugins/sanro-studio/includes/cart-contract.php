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
        || !is_int($quote['total_price']) || $quote['total_price'] < 0) {
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
