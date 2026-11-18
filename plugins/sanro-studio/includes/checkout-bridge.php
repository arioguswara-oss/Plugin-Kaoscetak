<?php
defined('ABSPATH') || exit;

/**
 * Staging integration boundary for an order item created by WooCommerce.
 * The caller must provide a freshly authorized draft contract and the cart
 * item from the same checkout. This is not a public endpoint or checkout hook.
 * Never save owner tokens, artwork, or client-provided prices.
 */
function sanro_studio_prepare_checkout_order_item($contract, $cart_item, $order_item) {
    if (!function_exists('sanro_studio_validate_cart_design_identity')
        || !function_exists('sanro_studio_validate_order_item_quote_amount')) {
        return ['valid' => false, 'error' => 'checkout_bridge_unavailable'];
    }
    $identity = sanro_studio_validate_cart_design_identity($contract, $cart_item);
    if (empty($identity['valid'])) return $identity;
    if (!is_object($order_item)
        || !method_exists($order_item, 'get_product_id')
        || !method_exists($order_item, 'get_quantity')
        || !method_exists($order_item, 'get_subtotal')
        || !method_exists($order_item, 'get_meta')
        || !method_exists($order_item, 'update_meta_data')) {
        return ['valid' => false, 'error' => 'invalid_order_item'];
    }
    if ($order_item->get_product_id() !== $identity['product_id']
        || $order_item->get_quantity() !== $identity['quantity']) {
        return ['valid' => false, 'error' => 'order_identity_mismatch'];
    }
    $amount = sanro_studio_validate_order_item_quote_amount($order_item, $contract['quote']);
    if (empty($amount['valid'])) return $amount;
    $existing = $order_item->get_meta('_sanro_studio_design_id', true);
    if ($existing !== '' && $existing !== null) {
        return ['valid' => false, 'error' => 'order_design_already_bound'];
    }
    $order_item->update_meta_data('_sanro_studio_design_id', $identity['design_id']);
    return [
        'valid' => true,
        'design_id' => $identity['design_id'],
        'requires_item_save' => true,
        'checkout_enabled' => false,
    ];
}

/**
 * Verify a WooCommerce order line against one server-priced size candidate.
 * Only after all lines are verified may the caller bind the design metadata.
 * No hook or order write is performed by this helper.
 */
function sanro_studio_validate_size_order_item($candidate, $order_item) {
    if (!is_array($candidate) || !is_object($order_item)
        || !method_exists($order_item, 'get_product_id')
        || !method_exists($order_item, 'get_quantity')
        || !method_exists($order_item, 'get_subtotal')
        || !isset($candidate['product_id'], $candidate['quantity'],
            $candidate['sanro_studio_line_subtotal'], $candidate['sanro_studio_size'])
        || !is_int($candidate['product_id']) || !is_int($candidate['quantity'])
        || !is_int($candidate['sanro_studio_line_subtotal'])
        || $candidate['quantity'] < 1
        || !in_array($candidate['sanro_studio_size'], ['S','M','L','XL','2XL','3XL','4XL','5XL'], true)
        || $order_item->get_product_id() !== $candidate['product_id']
        || $order_item->get_quantity() !== $candidate['quantity']) {
        return ['valid' => false, 'error' => 'size_order_identity_mismatch'];
    }
    $subtotal = $order_item->get_subtotal();
    if ((!is_string($subtotal) && !is_int($subtotal) && !is_float($subtotal))
        || !preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/D', (string) $subtotal)) {
        return ['valid' => false, 'error' => 'size_order_amount_invalid'];
    }
    $parts = explode('.', (string) $subtotal, 2);
    if ($parts[0] !== (string) $candidate['sanro_studio_line_subtotal']
        || str_pad($parts[1] ?? '', 2, '0') !== '00') {
        return ['valid' => false, 'error' => 'size_order_amount_mismatch'];
    }
    return ['valid' => true, 'size' => $candidate['sanro_studio_size'],
        'design_id' => $candidate['sanro_studio_design_id'] ?? null];
}

/**
 * Bind a verified size-line design to an unsaved WooCommerce order item.
 * Staging boundary only; the caller is responsible for atomic checkout flow.
 * Never store owner tokens, artwork bytes, private file paths or price claims.
 */
function sanro_studio_prepare_size_checkout_order_item($candidate, $order_item) {
    $verified = sanro_studio_validate_size_order_item($candidate, $order_item);
    if (empty($verified['valid'])) return $verified;
    if (!is_string($verified['design_id'])
        || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $verified['design_id'])
        || !method_exists($order_item, 'get_meta')
        || !method_exists($order_item, 'update_meta_data')
        || !isset($candidate['sanro_studio_unit_price'])
        || !is_int($candidate['sanro_studio_unit_price'])
        || $candidate['sanro_studio_unit_price'] < 1
        || $candidate['sanro_studio_line_subtotal'] !== $candidate['quantity'] * $candidate['sanro_studio_unit_price']
        || ($candidate['sanro_studio_quote_currency'] ?? null) !== 'IDR') {
        return ['valid' => false, 'error' => 'invalid_size_checkout_contract'];
    }
    foreach (['_sanro_studio_design_id', '_sanro_studio_size'] as $key) {
        $existing = $order_item->get_meta($key, true);
        if ($existing !== '' && $existing !== null) {
            return ['valid' => false, 'error' => 'size_order_already_bound'];
        }
    }
    $order_item->update_meta_data('_sanro_studio_design_id', $verified['design_id']);
    $order_item->update_meta_data('_sanro_studio_size', $verified['size']);
    return ['valid' => true, 'design_id' => $verified['design_id'],
        'size' => $verified['size'], 'requires_item_save' => true,
        'checkout_enabled' => false];
}

/**
 * Validate a complete set of unsaved WooCommerce order items before binding
 * any design metadata. Never partially bind a multi-size order.
 */
function sanro_studio_validate_size_checkout_batch($contract, $order_items) {
    if (!function_exists('sanro_studio_prepare_size_cart_lines')) {
        return ['valid' => false, 'error' => 'cart_contract_unavailable'];
    }
    $expected = sanro_studio_prepare_size_cart_lines($contract);
    if (empty($expected['valid'])) return $expected;
    if (!is_array($order_items) || count($order_items) !== count($expected['lines'])) {
        return ['valid' => false, 'error' => 'order_line_count_mismatch'];
    }
    // Explicit size keys are necessary: product ID alone cannot distinguish
    // two different size lines for the same shirt design.
    $remaining = [];
    foreach ($expected['lines'] as $line) $remaining[$line['sanro_studio_size']] = $line;
    foreach ($order_items as $size => $item) {
        if (!is_string($size) || !isset($remaining[$size])) {
            return ['valid' => false, 'error' => 'order_size_mismatch'];
        }
        $result = sanro_studio_validate_size_order_item($remaining[$size], $item);
        if (empty($result['valid'])) return $result;
        unset($remaining[$size]);
    }
    if ($remaining) return ['valid' => false, 'error' => 'order_missing_size'];
    return ['valid' => true, 'total_price' => $expected['total_price'],
        'total_quantity' => $expected['total_quantity'], 'checkout_enabled' => false];
}
