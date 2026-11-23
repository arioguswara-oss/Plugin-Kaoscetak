<?php
/**
 * Read-only WooCommerce cart adapter for isolated staging QA.
 * No hooks, no persistence, no token logging and no order creation.
 * Trust/approval facts must be supplied by a separate server-side runner.
 */
defined('ABSPATH') || exit;
require_once __DIR__ . '/staging-one-order-checkout-boundary.php';

function sanro_studio_qa_one_order_cart_readiness($cart, $context) {
    $deny = static function ($reason) {
        return ['ready' => false, 'reason' => $reason];
    };
    if (!is_object($cart) || !method_exists($cart, 'get_cart')
        || !is_array($context)) return $deny('cart_unavailable');
    $items = $cart->get_cart();
    if (!is_array($items) || count($items) !== 1) return $deny('cart_line_count_mismatch');
    $item = reset($items);
    if (!is_array($item) || !isset($item['data'])
        || !is_object($item['data']) || !method_exists($item['data'], 'get_price')) {
        return $deny('cart_product_unavailable');
    }
    $price = $item['data']->get_price();
    // Do not cast arbitrary numeric strings or round floats: the one-order
    // fixture requires an exact integer IDR price.
    if (!is_int($price) && !(is_string($price) && preg_match('/^[1-9][0-9]*$/D', $price))) {
        return $deny('cart_price_invalid');
    }
    if ((string) $price !== '130000'
        || ($item['product_id'] ?? null) !== 24
        || ($item['quantity'] ?? null) !== 1
        || ($item['sanro_studio_size'] ?? null) !== 'M'
        || ($item['sanro_studio_design_id'] ?? null) !== ($context['design_id'] ?? null)
        || ($item['sanro_studio_unit_price'] ?? null) !== 130000
        || ($item['sanro_studio_line_subtotal'] ?? null) !== 130000
        || ($item['sanro_studio_quote_currency'] ?? null) !== 'IDR') {
        return $deny('cart_quote_mismatch');
    }
    // Only derive evidence from the live WC cart. Do not replace the
    // separate server-side owner/nonce/quote verification with cart claims.
    $context['verified_line_items'] = [[
        'product_id' => $item['product_id'],
        'quantity' => $item['quantity'],
        'size' => $item['sanro_studio_size'],
        'design_id' => $item['sanro_studio_design_id'],
        'server_subtotal' => 130000,
        'currency' => 'IDR',
    ]];
    $decision = sanro_studio_qa_one_order_checkout_decision($context);
    return ['ready' => !empty($decision['allowed']), 'reason' => $decision['reason']];
}
