<?php
/**
 * SANRO Studio staging-only WooCommerce cart runtime diagnostic.
 * Read-only: never starts a customer session, sets a cookie, mutates a cart,
 * calls checkout, creates an order, or prints private customer information.
 * Execute from the staging WordPress root with PHP CLI.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = '/home/u606139982/domains/saddlebrown-peafowl-616656.hostingersite.com/public_html';
$expectedHost = 'saddlebrown-peafowl-616656.hostingersite.com';
if (getcwd() !== $root || !is_file($root . '/wp-load.php')) {
    fwrite(STDERR, "BLOCKED: WRONG_STAGING_ROOT\n"); exit(2);
}
if (!defined('WP_USE_THEMES')) define('WP_USE_THEMES', false);
require $root . '/wp-load.php';
if (!function_exists('home_url')
    || parse_url(home_url(), PHP_URL_HOST) !== $expectedHost
    || !function_exists('WC')
    || !class_exists('WC_Cart')
    || !function_exists('wc_get_product')) {
    fwrite(STDERR, "BLOCKED: STAGING_OR_WOO_IDENTITY_MISMATCH\n"); exit(2);
}
$product = wc_get_product(24);
$price = $product && method_exists($product, 'get_price') ? $product->get_price('edit') : null;
$cart = WC()->cart;
$session = WC()->session;
$checks = [
    'TARGET' => 'HOSTINGER_STAGING_ONLY',
    'MODE' => 'READ_ONLY',
    'WOO_CART_CLASS' => class_exists('WC_Cart') ? 'PRESENT' : 'MISSING',
    'WOO_CART_RUNTIME' => is_object($cart) ? get_class($cart) : 'NOT_INITIALIZED_IN_CLI',
    'WOO_SESSION_RUNTIME' => is_object($session) ? get_class($session) : 'NOT_INITIALIZED_IN_CLI',
    'PRODUCT24' => $product ? 'PRESENT' : 'MISSING',
    'PRODUCT24_PRICE_TYPE' => get_debug_type($price),
    'PRODUCT24_PRICE_IS_INTEGER_IDR' => (
        is_int($price) || (is_string($price) && preg_match('/^[1-9][0-9]*$/D', $price))
    ) ? 'YES' : 'NO',
    'PRODUCT24_PRICE_EQUALS_130000' => (string) $price === '130000' ? 'YES' : 'NO',
    'QA_ADAPTER_DEPLOYED' => is_file($root . '/wp-content/plugins/sanro-studio/tests/staging-one-order-woo-cart-adapter.php') ? 'YES' : 'NO',
    'CART_SESSION_E2E' => 'NOT_TESTED_CLI_HAS_NO_BUYER_SESSION',
    'ORDER_CREATED' => 'NO',
    'DATABASE_WRITES_BY_SCRIPT' => 'NONE_INTENDED',
];
foreach ($checks as $key => $value) {
    echo $key . ': ' . $value . "\n";
}
echo "STATUS: STAGING_WOO_CART_RUNTIME_DIAGNOSTIC_COMPLETE\n";
