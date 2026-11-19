<?php
/**
 * Plugin Name: SANRO Studio Staging QA
 * Description: Temporary SANRO staging-only cart/checkout preview; no order creation.
 * Version: 0.1.0
 * Author: SANRO
 * Requires PHP: 7.4
 */
defined('ABSPATH') || exit;

/** Never enable the staging QA gates outside the explicitly authorized host. */
function sanro_studio_qa_is_staging_host() {
    if (!function_exists('home_url')) return false;
    $host = parse_url(home_url('/'), PHP_URL_HOST);
    return is_string($host)
        && strtolower($host) === 'saddlebrown-peafowl-616656.hostingersite.com';
}
if (!sanro_studio_qa_is_staging_host()) return;

// WP loads active plugins by sorted plugin file name. This plugin's slug,
// sanro-studio-qa, precedes sanro-studio and sets gates before its bootstrap.
foreach ([
    'SANRO_STUDIO_ENABLE_STAGING_CART',
    'SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT',
    'SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK',
] as $gate) {
    if (!defined($gate)) define($gate, true);
}

/** WooCommerce's documented, reversible exclusion; no global Live toggle. */
function sanro_studio_qa_visible_store_pages($already_excluded) {
    if ($already_excluded || !sanro_studio_qa_is_staging_host()
        || !function_exists('get_queried_object_id')) return $already_excluded;
    $page_id = (int) get_queried_object_id();
    return $page_id === 16 || $page_id === 17;
}
add_filter('woocommerce_coming_soon_exclude', 'sanro_studio_qa_visible_store_pages', 99);

/** This QA scope stops at the checkout page, never creates an order. */
function sanro_studio_qa_block_classic_order() {
    if (!sanro_studio_qa_is_staging_host()) return;
    if (function_exists('wc_add_notice')) {
        wc_add_notice('SANRO Studio QA: halaman checkout hanya untuk uji tampilan; pemesanan dinonaktifkan.', 'error');
    }
}
add_action('woocommerce_checkout_process', 'sanro_studio_qa_block_classic_order', 1);
add_action('woocommerce_checkout_create_order', function() {
    if (sanro_studio_qa_is_staging_host()) {
        throw new RuntimeException('SANRO Studio QA: order creation disabled on staging.');
    }
}, 1);

add_filter('rest_pre_dispatch', function($result, $server, $request) {
    if (!sanro_studio_qa_is_staging_host()
        || !is_object($request)
        || !method_exists($request, 'get_method')
        || !method_exists($request, 'get_route')) return $result;
    if ($request->get_method() === 'POST'
        && preg_match('~^/wc/store/v1/checkout(?:/|$)~D', $request->get_route())) {
        return new WP_Error('sanro_qa_checkout_disabled',
            'SANRO Studio staging: order submission disabled.',
            ['status' => 403]);
    }
    return $result;
}, 1, 3);
