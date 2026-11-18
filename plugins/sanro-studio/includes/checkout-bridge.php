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
