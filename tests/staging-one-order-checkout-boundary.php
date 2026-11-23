<?php
/**
 * Checkout integration boundary for a single synthetic staging order.
 *
 * Deliberately NOT registered as a WordPress/WooCommerce hook.
 * The caller must supply independently verified, server-side facts.
 * Never use client POST fields to populate trust assertions.
 */
defined('ABSPATH') || exit;
require_once __DIR__ . '/staging-one-order-preflight.php';

function sanro_studio_qa_one_order_checkout_decision($context) {
    $preflight = sanro_studio_qa_one_order_preflight($context);
    if (empty($preflight['valid'])) {
        return ['allowed' => false, 'reason' => $preflight['reason'] ?? 'preflight_failed'];
    }
    // The preflight's booleans alone do not prove server-side validation.
    // Require distinct evidence from the authoritative WooCommerce checkout.
    if (!isset($context['verified_line_items']) || !is_array($context['verified_line_items'])
        || count($context['verified_line_items']) !== 1) {
        return ['allowed' => false, 'reason' => 'line_item_evidence_missing'];
    }
    $line = $context['verified_line_items'][0];
    if (!is_array($line)
        || ($line['product_id'] ?? null) !== 24
        || ($line['quantity'] ?? null) !== 1
        || ($line['size'] ?? null) !== 'M'
        || ($line['design_id'] ?? null) !== $context['design_id']
        || ($line['server_subtotal'] ?? null) !== 130000
        || ($line['currency'] ?? null) !== 'IDR') {
        return ['allowed' => false, 'reason' => 'line_item_evidence_mismatch'];
    }
    if (($context['checkout_transport'] ?? null) !== 'classic'
        || ($context['verified_checkout_nonce'] ?? null) !== true
        || ($context['shipping_total'] ?? null) !== 0
        || ($context['tax_total'] ?? null) !== 0
        || ($context['discount_total'] ?? null) !== 0
        || ($context['order_write_cap'] ?? null) !== 1) {
        return ['allowed' => false, 'reason' => 'checkout_boundary_not_verified'];
    }
    return ['allowed' => true, 'reason' => 'eligible_for_future_isolated_runner_no_write'];
}
