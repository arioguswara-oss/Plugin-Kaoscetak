<?php
/**
 * SANRO Studio: staging-only one-order safety preflight.
 * Read-only by design. Does NOT authorize, create, or alter an order.
 * Run from WordPress document root: php /absolute/path/to/this/file
 */
declare(strict_types=1);
$root = '/home/u606139982/domains/saddlebrown-peafowl-616656.hostingersite.com/public_html';
$host = 'saddlebrown-peafowl-616656.hostingersite.com';
if (PHP_SAPI !== 'cli'
    || !is_file($root . '/wp-load.php')
    || realpath(getcwd()) !== realpath($root)) {
    fwrite(STDERR, "BLOCKED: INVALID_RUNTIME_OR_ROOT\n"); exit(2);
}
define('WP_USE_THEMES', false);
require $root . '/wp-load.php';
$checks = [];
$checks['staging_host'] = parse_url(home_url(), PHP_URL_HOST) === $host;
$checks['runtime_user'] = function_exists('posix_geteuid') && posix_getpwuid(posix_geteuid())['name'] === 'u606139982';
$checks['woocommerce'] = class_exists('WooCommerce') && function_exists('wc_get_product');
$checks['currency_idr'] = function_exists('get_woocommerce_currency') && get_woocommerce_currency() === 'IDR';
$product = function_exists('wc_get_product') ? wc_get_product(24) : false;
$checks['product_24_simple_published'] = $product && $product->is_type('simple') && $product->get_status() === 'publish' && $product->is_purchasable();
$checks['catalog_price_65000'] = $product && (string)$product->get_price('edit') === '65000';
$checks['hpos_enabled'] = class_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil')
    && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
$checks['qa_order_guard'] = function_exists('sanro_studio_qa_block_classic_order');
$checks['cart_reprice_callback'] = function_exists('sanro_studio_stage_reprice_signed_cart');
$checks['order_binding_callback'] = function_exists('sanro_studio_stage_checkout_create_order_line_item');
$checks['payment_gateways_off'] = true;
if (function_exists('WC') && WC()->payment_gateways()) {
    foreach (WC()->payment_gateways()->payment_gateways() as $gateway) {
        if ($gateway->enabled === 'yes') $checks['payment_gateways_off'] = false;
    }
}
$checks['staging_cart_flag'] = defined('SANRO_STUDIO_ENABLE_STAGING_CART') && SANRO_STUDIO_ENABLE_STAGING_CART === true;
$checks['staging_commit_flag'] = defined('SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT') && SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT === true;
$checks['staging_checkout_flag'] = defined('SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK') && SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK === true;
$checks['mail_disabled'] = defined('SANRO_STUDIO_QA_DISABLE_MAIL') && SANRO_STUDIO_QA_DISABLE_MAIL === true;
$checks['stock_disabled'] = defined('SANRO_STUDIO_QA_DISABLE_STOCK') && SANRO_STUDIO_QA_DISABLE_STOCK === true;
$checks['production_writers_disabled'] = !(defined('SANRO_STUDIO_ENABLE_DTF_GENERATOR') && SANRO_STUDIO_ENABLE_DTF_GENERATOR === true);
foreach ($checks as $key => $pass) echo strtoupper($key), ': ', ($pass ? 'PASS' : 'BLOCKED'), "\n";
echo "EXPECTED_QUOTE: 130000\nCATALOG_PRICE: 65000\nMODE: READ_ONLY_NO_ORDER\n";
echo 'STATUS: ', in_array(false, $checks, true) ? 'PREFLIGHT_BLOCKED' : 'PREFLIGHT_READY_FOR_SEPARATE_AUTHORIZED_RUNNER', "\n";
exit(in_array(false, $checks, true) ? 1 : 0);
