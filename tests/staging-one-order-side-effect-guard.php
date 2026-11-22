<?php
/**
 * SANRO Studio: isolated CLI-only synthetic checkout side-effect guard.
 * NOT a WordPress plugin. Require explicitly from a future approved runner,
 * before creating an order, and only after staging identity verification.
 * Never enable this module on production or a public web request.
 */
defined('ABSPATH') || exit;

function sanro_studio_qa_one_order_side_effect_guard($context) {
    if (PHP_SAPI !== 'cli' || !is_array($context)
        || ($context['host'] ?? '') !== 'saddlebrown-peafowl-616656.hostingersite.com'
        || ($context['root'] ?? '') !== '/home/u606139982/domains/saddlebrown-peafowl-616656.hostingersite.com/public_html'
        || ($context['approval'] ?? '') !== 'ONE_SYNTHETIC_CHECKOUT_ORDER_APPROVED'
        || ($context['order_cap'] ?? null) !== 1
        || !function_exists('add_filter') || !function_exists('add_action')
        || !function_exists('home_url')
        || parse_url(home_url(), PHP_URL_HOST) !== $context['host']
        || realpath(ABSPATH) !== $context['root']
        || !function_exists('posix_geteuid')
        || !function_exists('posix_getpwuid')
        || (posix_getpwuid(posix_geteuid())['name'] ?? '') !== 'u606139982') {
        return ['valid' => false, 'reason' => 'identity_or_scope_failed'];
    }
    // Intercept all wp_mail calls in this CLI process only. Return true to
    // short-circuit wp_mail without dispatching to an SMTP transport.
    add_filter('pre_wp_mail', static function ($pre) { return true; }, PHP_INT_MAX);
    // WooCommerce stock reduction is order-scoped. Prevent stock reduction
    // even if a checkout hook unexpectedly attempts to reduce stock.
    add_filter('woocommerce_can_reduce_order_stock', static function ($can, $order) {
        return false;
    }, PHP_INT_MAX, 2);
    // Explicitly suppress Woo transactional emails during this process.
    add_filter('woocommerce_email_enabled_new_order', '__return_false', PHP_INT_MAX);
    add_filter('woocommerce_email_enabled_customer_processing_order', '__return_false', PHP_INT_MAX);
    add_filter('woocommerce_email_enabled_customer_on_hold_order', '__return_false', PHP_INT_MAX);
    add_filter('woocommerce_email_enabled_customer_completed_order', '__return_false', PHP_INT_MAX);
    add_filter('woocommerce_email_enabled_customer_invoice', '__return_false', PHP_INT_MAX);
    return [
        'valid' => true,
        'mail_intercepted' => has_filter('pre_wp_mail') !== false,
        'stock_reduction_intercepted' => has_filter('woocommerce_can_reduce_order_stock') !== false,
        'scope' => 'this_cli_process_only',
    ];
}
