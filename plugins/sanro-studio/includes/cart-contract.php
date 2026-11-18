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
    // Never issue a cart contract for an image-only bootstrap, malformed
    // geometry, or a draft whose image master binding was revoked.
    if (!function_exists('sanro_studio_validate_product_design')
        || !function_exists('sanro_studio_draft_image_binding_allowed')
        || empty(sanro_studio_validate_product_design($product_id, $design)['valid'])
        || !sanro_studio_draft_image_binding_allowed(
            $design, $draft['master_refs'] ?? [], $draft['design_id']
        )) {
        return ['valid' => false, 'error' => 'draft_not_print_ready'];
    }
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
 * Construct a sanitized WooCommerce cart-line snapshot. This helper does not
 * insert a product, store credentials, or enable checkout hooks.
 * Only server-authorized draft identity and server-computed totals survive.
 */
function sanro_studio_prepare_cart_line_snapshot($contract) {
    if (!is_array($contract) || ($contract['valid'] ?? null) !== true
        || !isset($contract['design_id'], $contract['product_id'], $contract['quote'])
        || !is_string($contract['design_id'])
        || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $contract['design_id'])
        || !is_int($contract['product_id']) || $contract['product_id'] < 1
        || !is_array($contract['quote']) || ($contract['quote']['valid'] ?? null) !== true
        || ($contract['quote']['currency'] ?? null) !== 'IDR'
        || !isset($contract['quote']['total_quantity'], $contract['quote']['total_price'])
        || !is_int($contract['quote']['total_quantity']) || $contract['quote']['total_quantity'] < 1
        || !is_int($contract['quote']['total_price']) || $contract['quote']['total_price'] < 1) {
        return ['valid' => false, 'error' => 'invalid_cart_contract'];
    }
    return [
        'valid' => true,
        'product_id' => $contract['product_id'],
        'quantity' => $contract['quote']['total_quantity'],
        'sanro_studio_design_id' => $contract['design_id'],
        'sanro_studio_quote_total' => $contract['quote']['total_price'],
        'sanro_studio_quote_currency' => 'IDR',
        'cart_mutation_enabled' => false,
    ];
}

/**
 * Build per-size WooCommerce line candidates using the server quote.
 * WooCommerce prices are per unit: never apply a multi-size order total as
 * the unit price. This helper remains read-only; no cart hook is registered.
 */
function sanro_studio_prepare_size_cart_lines($contract) {
    $snapshot = sanro_studio_prepare_cart_line_snapshot($contract);
    if (empty($snapshot['valid'])) return $snapshot;
    $items = $contract['quote']['items'] ?? null;
    if (!is_array($items) || !$items || count($items) > 8) {
        return ['valid' => false, 'error' => 'invalid_size_breakdown'];
    }
    $lines = [];
    $sum_quantity = 0;
    $sum_price = 0;
    foreach ($items as $size => $item) {
        if (!is_string($size) || !in_array($size, ['S','M','L','XL','2XL','3XL','4XL','5XL'], true)
            || !is_array($item)
            || !isset($item['quantity'], $item['unit_price'], $item['subtotal'])
            || !is_int($item['quantity']) || $item['quantity'] < 1 || $item['quantity'] > 999
            || !is_int($item['unit_price']) || $item['unit_price'] < 1
            || !is_int($item['subtotal'])
            || $item['subtotal'] !== $item['quantity'] * $item['unit_price']) {
            return ['valid' => false, 'error' => 'invalid_size_breakdown'];
        }
        $sum_quantity += $item['quantity'];
        $sum_price += $item['subtotal'];
        $lines[] = [
            'product_id' => $snapshot['product_id'],
            'quantity' => $item['quantity'],
            'sanro_studio_design_id' => $snapshot['sanro_studio_design_id'],
            'sanro_studio_size' => $size,
            'sanro_studio_unit_price' => $item['unit_price'],
            'sanro_studio_line_subtotal' => $item['subtotal'],
            'sanro_studio_quote_currency' => 'IDR',
        ];
    }
    if ($sum_quantity !== $snapshot['quantity']
        || $sum_price !== $snapshot['sanro_studio_quote_total']) {
        return ['valid' => false, 'error' => 'size_totals_mismatch'];
    }
    return ['valid' => true, 'lines' => $lines, 'total_quantity' => $sum_quantity,
        'total_price' => $sum_price, 'cart_mutation_enabled' => false];
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
    if (!is_array($contract['quote']) || ($contract['quote']['valid'] ?? null) !== true
        || ($contract['quote']['currency'] ?? null) !== 'IDR'
        || !isset($contract['quote']['total_price'])
        || !is_int($contract['quote']['total_price']) || $contract['quote']['total_price'] <= 0
        || !isset($contract['quote']['total_quantity'])
        || !is_int($contract['quote']['total_quantity'])
        || $contract['quote']['total_quantity'] < 1
        || !isset($cart_item['quantity']) || !is_int($cart_item['quantity'])
        || $cart_item['quantity'] !== $contract['quote']['total_quantity']) {
        return ['valid' => false, 'error' => 'cart_quantity_mismatch'];
    }
    // When the cart carries a quoted snapshot, reject any modified price
    // or currency. Never silently trust a client-written quote field.
    if (array_key_exists('sanro_studio_quote_total', $cart_item)
        || array_key_exists('sanro_studio_quote_currency', $cart_item)) {
        if (($cart_item['sanro_studio_quote_total'] ?? null) !== $contract['quote']['total_price']
            || ($cart_item['sanro_studio_quote_currency'] ?? null) !== 'IDR') {
            return ['valid' => false, 'error' => 'cart_quote_mismatch'];
        }
    }
    return ['valid' => true, 'design_id' => $contract['design_id'],
        'product_id' => $contract['product_id'], 'quantity' => $cart_item['quantity'],
        'checkout_enabled' => false];
}

/**
 * Read-only checkout totals boundary. Values must come from a fresh,
 * owner-authorized server quote; this function never changes cart prices.
 */
function sanro_studio_validate_checkout_totals($contract, $cart_item, $currency, $total_price) {
    $identity = sanro_studio_validate_cart_design_identity($contract, $cart_item);
    if (empty($identity['valid'])) return $identity;
    $quote = $contract['quote'];
    if (!isset($quote['currency'], $quote['total_price'])
        || $quote['currency'] !== 'IDR' || $currency !== 'IDR'
        || !is_int($quote['total_price']) || $quote['total_price'] <= 0
        || !is_int($total_price) || $total_price <= 0
        || $quote['total_price'] !== $total_price) {
        return ['valid' => false, 'error' => 'checkout_total_mismatch'];
    }
    return ['valid' => true, 'design_id' => $identity['design_id'],
        'product_id' => $identity['product_id'], 'quantity' => $identity['quantity'],
        'total_price' => $total_price, 'currency' => 'IDR',
        'checkout_enabled' => false];
}
