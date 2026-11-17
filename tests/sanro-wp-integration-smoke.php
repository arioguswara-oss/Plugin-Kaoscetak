<?php
/**
 * SANRO Studio read-only WordPress integration probe.
 * Run ONLY in isolated staging: wp eval-file tests/sanro-wp-integration-smoke.php
 * No activation, schema creation, session mutation, cart creation or customer data output.
 */
if (!defined('ABSPATH') || !function_exists('did_action')) {
    fwrite(STDERR, "FAIL: WordPress bootstrap required (wp eval-file).\n");
    exit(2);
}
$checks = [];
$check = static function ($name, $ok, $detail = '') use (&$checks) {
    $checks[] = ['name' => $name, 'pass' => (bool) $ok, 'detail' => $detail];
};
$check('WordPress init completed', did_action('init') > 0);
$check('WooCommerce class loaded', class_exists('WooCommerce', false));
$check('SANRO Studio runtime function loaded', function_exists('sanro_studio_runtime_status'));
if (function_exists('sanro_studio_runtime_status')) {
    $state = sanro_studio_runtime_status();
    $check('Core runtime internal and loaded', ($state['mode'] ?? '') === 'internal' && ($state['state'] ?? '') === 'internal_loaded', (string) ($state['state'] ?? 'unknown'));
}
$check('Vendor editor instance available', function_exists('LW') && class_exists('LumiseWoo', false));
$check('Vendor init callback registered', function_exists('has_action') && has_action('init') !== false && did_action('init') > 0);
$check('SANRO bootstrap hook registered', function_exists('has_action') && has_action('plugins_loaded', 'sanro_studio_boot_runtime') !== false);
$check('WooCommerce initialized', did_action('woocommerce_init') > 0);
// Do not report an automatic PASS for schema migration: this read-only probe cannot prove its absence.
echo "NOT CHECKED: historical schema migrations or writes (requires separate audit).\n";
$failed = 0;
foreach ($checks as $entry) {
    $label = $entry['pass'] ? 'PASS' : 'FAIL';
    if (!$entry['pass']) $failed++;
    echo $label . ': ' . $entry['name'] . ($entry['detail'] !== '' ? ' [' . $entry['detail'] . ']' : '') . "\n";
}
echo 'SUMMARY: ' . (count($checks) - $failed) . '/' . count($checks) . " checks PASS\n";
if ($failed) exit(1);
echo "NOTE: This is NOT proof of browser canvas, customer sessions, checkout, or Flatsome compatibility.\n";
