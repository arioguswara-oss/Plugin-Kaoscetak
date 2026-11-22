<?php
/**
 * Read-only SANRO Studio HPOS QA order inspection.
 *
 * Run from WordPress staging root:
 *   SANRO_STUDIO_QA_ENV=staging php /path/to/staging-hpos-order-metadata-readonly.php
 *
 * No create, update, save, delete, checkout or payment calls.
 */
declare(strict_types=1);

function sanro_hpos_qa_fail(string $code): void {
    fwrite(STDERR, "BLOCKED: {$code}\n");
    exit(2);
}
if (PHP_SAPI !== 'cli' || getenv('SANRO_STUDIO_QA_ENV') !== 'staging') {
    sanro_hpos_qa_fail('STAGING_CLI_ONLY');
}
$root = getcwd();
if (!$root || !is_file($root . '/wp-load.php')) {
    sanro_hpos_qa_fail('RUN_FROM_WORDPRESS_ROOT');
}
define('WP_USE_THEMES', false);
require_once $root . '/wp-load.php';

$expectedHost = 'saddlebrown-peafowl-616656.hostingersite.com';
$homeHost = parse_url(home_url(), PHP_URL_HOST);
$siteHost = parse_url(site_url(), PHP_URL_HOST);
if ($homeHost !== $expectedHost || $siteHost !== $expectedHost) {
    sanro_hpos_qa_fail('STAGING_SITE_IDENTITY');
}
if (!function_exists('wc_get_order') || !class_exists('WooCommerce')) {
    sanro_hpos_qa_fail('WOOCOMMERCE_NOT_LOADED');
}
if (!class_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil') ||
    !\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
    sanro_hpos_qa_fail('HPOS_NOT_ENABLED');
}
$order = wc_get_order(52);
if (!$order || (int) $order->get_id() !== 52) {
    sanro_hpos_qa_fail('QA_FIXTURE_52_NOT_FOUND');
}
if ((float) $order->get_total() !== 0.0 || $order->get_status() !== 'pending') {
    sanro_hpos_qa_fail('QA_FIXTURE_52_UNEXPECTED_STATE');
}
$items = $order->get_items('line_item');
if (count($items) !== 1) {
    sanro_hpos_qa_fail('QA_FIXTURE_ITEM_COUNT');
}
$item = reset($items);
$designId = $item->get_meta('_sanro_studio_design_id', true);
$size = $item->get_meta('_sanro_studio_size', true);
$designPresent = is_string($designId) && trim($designId) !== '';
$sizePresent = is_string($size) && in_array($size, ['S','M','L','XL','2XL','3XL','4XL','5XL'], true);
echo "TARGET: HOSTINGER_SANRO_STUDIO_STAGING\n";
echo "HPOS: ENABLED\n";
echo "ORDER_ID: 52\n";
echo "ORDER_STATUS: pending\n";
echo "ORDER_TOTAL: 0 IDR\n";
echo "ORDER_ITEMS: 1\n";
echo "DESIGN_ID: " . ($designPresent ? 'PRESENT' : 'NOT_PRESENT') . "\n";
echo "SIZE: " . ($sizePresent ? 'PRESENT_VALID' : 'NOT_PRESENT_OR_INVALID') . "\n";
echo "FIXTURE_CLASS: ADMIN_QA_NOT_BUYER_CHECKOUT\n";
echo "BUYER_ORDER_PERSISTENCE: NOT_TESTED\n";
echo "MODE: READ_ONLY\n";
echo "STATUS: HPOS_QA_FIXTURE_INSPECTED\n";
if (!$designPresent) {
    sanro_hpos_qa_fail('QA_FIXTURE_DESIGN_ID_MISSING');
}
