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
    $check('Legacy external core not substituted', ($state['mode'] ?? '') !== 'external', (string) ($state['mode'] ?? 'unknown'));
}
$check('Vendor editor instance available', function_exists('LW') && class_exists('LumiseWoo', false));
// has_action('init') alone is not evidence that the vendor callback was registered.
$check('SANRO core entrypoint defined', defined('LUMISE_FILE') && strpos((string) constant('LUMISE_FILE'), '/sanro-studio/runtime/editor/') !== false);
// WP-CLI has no browser cookies; WooCommerce may intentionally defer session creation.
$check('WooCommerce singleton available', function_exists('WC') && is_object(WC()));
$wc = function_exists('WC') ? WC() : null;
if (is_object($wc) && isset($wc->session)) {
    $check('WooCommerce session object initialized', is_object($wc->session));
} else {
    echo "NOT CHECKED: customer HTTP session (WP-CLI has no browser cookies).\n";
}
if (function_exists('wp_get_theme')) {
    $theme = wp_get_theme();
    $parent = $theme->parent();
    $slugs = array_filter([$theme->get_stylesheet(), $parent ? $parent->get_stylesheet() : '']);
    $check('Flatsome theme or child active', in_array('flatsome', $slugs, true));
} else {
    $check('WordPress theme API available', false);
}
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
