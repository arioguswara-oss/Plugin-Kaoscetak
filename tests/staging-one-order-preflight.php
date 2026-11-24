<?php
/**
 * Isolated staging checkout QA policy (not wired to WooCommerce).
 *
 * A future one-order runner must pass this pure preflight before any write.
 * This module intentionally does not bypass QA checkout blockers, create an
 * order, register hooks, or alter payment gateways.
 */
defined('ABSPATH') || exit;

function sanro_studio_qa_one_order_preflight($context) {
    $deny = static function ($reason) {
        return ['valid' => false, 'reason' => $reason];
    };
    if (!is_array($context)) return $deny('invalid_context');
    if (($context['host'] ?? null) !== 'saddlebrown-peafowl-616656.hostingersite.com'
        || ($context['environment'] ?? null) !== 'staging'
        || ($context['user'] ?? null) !== 'u606139982'
        || ($context['root'] ?? null) !== '/home/u606139982/domains/saddlebrown-peafowl-616656.hostingersite.com/public_html') {
        return $deny('wrong_staging_identity');
    }
    if (($context['approval'] ?? null) !== 'ONE_SYNTHETIC_CHECKOUT_ORDER_APPROVED'
        || ($context['mode'] ?? null) !== 'create'
        || ($context['existing_qa_orders'] ?? null) !== 0) {
        return $deny('approval_or_idempotence_missing');
    }
    if (($context['currency'] ?? null) !== 'IDR'
        || ($context['server_quote'] ?? null) !== 130000
        || ($context['order_total'] ?? null) !== 130000
        || ($context['quantity'] ?? null) !== 1
        || ($context['size'] ?? null) !== 'M'
        || ($context['product_id'] ?? null) !== 24) {
        return $deny('quote_or_product_mismatch');
    }
    $id = $context['design_id'] ?? null;
    if (!is_string($id)
        || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $id)
        || ($context['owner_authorized'] ?? null) !== true
        || ($context['server_quote_verified'] ?? null) !== true
        || ($context['all_items_bound'] ?? null) !== true
        || ($context['hp_os_enabled'] ?? null) !== true
        || ($context['disable_mail'] ?? null) !== true
        || ($context['disable_stock_changes'] ?? null) !== true
        || ($context['disable_production_writers'] ?? null) !== true) {
        return $deny('authorization_or_safety_missing');
    }
    return ['valid' => true, 'reason' => 'preflight_only_no_write'];
}
