<?php
/**
 * Sprint 145 — Hostinger SANRO Studio staging-only read-only runtime probe.
 * A "before" probe requires no generator module/hook. An "after" probe
 * requires the new module/hooks but the ENTIRE DTF generation stack OFF.
 *
 * No WP database, WooCommerce order, external API or private file mutations.
 * Loading WP core has ordinary bootstrap side effects; DISABLE_WP_CRON
 * prevents cron dispatch from this isolated CLI probe.
 */
if (PHP_SAPI !== 'cli' || count($argv) !== 1) {
    fwrite(STDERR, "BLOCKED: CLI probe only\n");
    exit(2);
}
$phase = getenv('SANRO_STUDIO_PR35_PROBE_PHASE');
$expectedHost = 'saddlebrown-peafowl-616656.hostingersite.com';
$expectedRoot = '/home/u606139982/domains/' . $expectedHost . '/public_html';
$hostname = gethostname();
$shortHost = is_string($hostname) ? explode('.', $hostname)[0] : '';
if (!in_array($phase, ['before', 'after'], true)
    || $shortHost !== 'id-dci-web1631'
    || getenv('SANRO_STUDIO_QA_ENV') !== 'staging'
    || (function_exists('posix_geteuid') && posix_geteuid() === 0)
    || get_current_user() !== 'u606139982'
    || realpath($expectedRoot) !== $expectedRoot
    || !is_file($expectedRoot . '/wp-load.php')) {
    fwrite(STDERR, "BLOCKED: exact SANRO staging probe identity failed\n");
    exit(2);
}
if (!defined('DISABLE_WP_CRON')) define('DISABLE_WP_CRON', true);
if (!defined('WP_USE_THEMES')) define('WP_USE_THEMES', false);
$_SERVER['HTTP_HOST'] = $expectedHost;
$_SERVER['SERVER_NAME'] = $expectedHost;
$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_PORT'] = '443';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
require_once $expectedRoot . '/wp-load.php';

$home = function_exists('home_url') ? rtrim(home_url('/'), '/') : '';
$site = function_exists('site_url') ? rtrim(site_url('/'), '/') : '';
if ($home !== 'https://' . $expectedHost
    || $site !== 'https://' . $expectedHost
    || realpath(ABSPATH) !== $expectedRoot
    || !function_exists('has_action')
    || !function_exists('sanro_studio_dtf_admin_status_enabled')
    || !sanro_studio_dtf_admin_status_enabled()
    || !function_exists('wc_get_order')
    || !class_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil')
    || !is_callable(['Automattic\\WooCommerce\\Utilities\\OrderUtil',
        'custom_orders_table_usage_is_enabled'])
    || !call_user_func(['Automattic\\WooCommerce\\Utilities\\OrderUtil',
        'custom_orders_table_usage_is_enabled'])) {
    fwrite(STDERR, "BLOCKED: SANRO staging WooCommerce/HPOS/preexisting panel drift\n");
    exit(2);
}
if (has_action('woocommerce_after_order_itemmeta',
        'sanro_studio_admin_item_dtf_status') === false
    || has_action('woocommerce_after_order_itemmeta',
        'sanro_studio_admin_item_preview_status') === false) {
    fwrite(STDERR, "BLOCKED: original WordPress admin/preview hooks altered\n");
    exit(2);
}
$writerFlags = [
    'SANRO_STUDIO_ENABLE_DTF_ADMIN_GENERATE',
    'SANRO_STUDIO_ENABLE_DTF_IMAGE_RASTER',
    'SANRO_STUDIO_ENABLE_DTF_LAYER_RASTER',
    'SANRO_STUDIO_ENABLE_DTF_PRIVATE_ARTIFACTS',
    'SANRO_STUDIO_ENABLE_DTF_ORDER_BATCH',
    'SANRO_STUDIO_ENABLE_DRIVE_AUTO_PROVISION',
    'SANRO_STUDIO_ENABLE_VERIFIED_DRIVE_ATTACHMENT',
];
foreach ($writerFlags as $flag) {
    if (defined($flag) && constant($flag) === true) {
        fwrite(STDERR, "BLOCKED: staging DTF or Drive writer unexpectedly enabled\n");
        exit(2);
    }
}
$callbacks = [
    ['woocommerce_after_order_itemmeta', 'sanro_studio_admin_item_generate_dtf_action'],
    ['admin_footer', 'sanro_studio_dtf_admin_generate_footer_forms'],
    ['admin_post_sanro_studio_generate_dtf_candidates',
        'sanro_studio_admin_generate_dtf_candidates_post'],
];
$hooksPresent = true;
foreach ($callbacks as $pair) {
    if (has_action($pair[0], $pair[1]) === false) {
        $hooksPresent = false;
        break;
    }
}
$moduleExists = is_file($expectedRoot
    . '/wp-content/plugins/sanro-studio/includes/dtf-admin-generate.php');
$functionExists = function_exists('sanro_studio_dtf_admin_generate_enabled');
if ($phase === 'before') {
    if ($moduleExists || $hooksPresent || $functionExists) {
        fwrite(STDERR, "BLOCKED: generator already installed; no blind overwrite\n");
        exit(2);
    }
} else {
    if (!$moduleExists || !$hooksPresent || !$functionExists
        || sanro_studio_dtf_admin_generate_enabled() !== false) {
        fwrite(STDERR, "FAIL: generator module/hooks not installed or unexpectedly active\n");
        exit(1);
    }
}
echo 'PROBE_PHASE: ' . strtoupper($phase) . "\n";
echo 'SANRO_STAGING_RUNTIME: VERIFIED\n';
echo 'WOOCOMMERCE_HPOS: ENABLED\n';
echo 'LEGACY_ITEM_PREVIEW_HOOK: REGISTERED\n';
echo 'DTF_READONLY_ADMIN_PANEL: REGISTERED\n';
echo 'DTF_GENERATOR_HOOKS: ' . ($phase === 'before' ? 'ABSENT' : 'REGISTERED') . "\n";
echo "DTF_ADMIN_GENERATE: OFF\n";
echo "DTF_IMAGE_RASTER: OFF\n";
echo "DTF_LAYER_RASTER: OFF\n";
echo "DTF_PRIVATE_ARTIFACTS: OFF\n";
echo "DTF_ORDER_BATCH: OFF\n";
echo "DRIVE_AUTO_PROVISION: OFF\n";
echo "DRIVE_ATTACHMENT: OFF\n";
echo 'PROBE_STATUS: PASS' . "\n";
