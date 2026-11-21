<?php
/**
 * PR38 exact Hostinger staging WP runtime verification, BEFORE and AFTER.
 * Strict read-only GET CLI bootstrap; no orders, products or customer records.
 * No new readiness display is activated; all DTF/Drive writers stay OFF.
 */
$phase = getenv('SANRO_STUDIO_PR38_PROBE_PHASE');
$host = 'saddlebrown-peafowl-616656.hostingersite.com';
$root = '/home/u606139982/domains/' . $host . '/public_html';
if (PHP_SAPI !== 'cli'
    || !in_array($phase, ['before', 'after'], true)
    || getenv('SANRO_STUDIO_QA_ENV') !== 'staging'
    || explode('.', (string) gethostname())[0] !== 'id-dci-web1631'
    || get_current_user() !== 'u606139982'
    || (function_exists('posix_geteuid') && posix_geteuid() === 0)
    || realpath($root) !== $root
    || !is_file($root . '/wp-load.php')) {
    fwrite(STDERR, "BLOCKED: exact staging probe scope or identity invalid\n");
    exit(2);
}
if (!defined('DISABLE_WP_CRON')) define('DISABLE_WP_CRON', true);
if (!defined('WP_USE_THEMES')) define('WP_USE_THEMES', false);
$_SERVER['HTTP_HOST'] = $host;
$_SERVER['SERVER_NAME'] = $host;
$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_PORT'] = '443';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
require_once $root . '/wp-load.php';

if (!function_exists('home_url') || !function_exists('site_url')
    || realpath(ABSPATH) !== $root
    || rtrim(home_url('/'), '/') !== 'https://' . $host
    || rtrim(site_url('/'), '/') !== 'https://' . $host
    || !class_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil')
    || !is_callable(['Automattic\\WooCommerce\\Utilities\\OrderUtil',
        'custom_orders_table_usage_is_enabled'])
    || !call_user_func(['Automattic\\WooCommerce\\Utilities\\OrderUtil',
        'custom_orders_table_usage_is_enabled'])
    || !function_exists('sanro_studio_dtf_admin_status_enabled')
    || !sanro_studio_dtf_admin_status_enabled()
    || !function_exists('sanro_studio_dtf_admin_generate_enabled')
    || sanro_studio_dtf_admin_generate_enabled()
    || !function_exists('has_action')
    || has_action('woocommerce_after_order_itemmeta',
        'sanro_studio_admin_item_preview_status') === false
    || has_action('woocommerce_after_order_itemmeta',
        'sanro_studio_admin_item_dtf_status') === false
    || has_action('woocommerce_after_order_itemmeta',
        'sanro_studio_admin_item_generate_dtf_action') === false
    || has_action('admin_footer',
        'sanro_studio_dtf_admin_generate_footer_forms') === false
    || has_action('admin_post_sanro_studio_generate_dtf_candidates',
        'sanro_studio_admin_generate_dtf_candidates_post') === false) {
    fwrite(STDERR, "BLOCKED: WooCommerce HPOS or old PR35 hooks/runtime drift\n");
    exit(2);
}
foreach ([
    'SANRO_STUDIO_ENABLE_DTF_ADMIN_READINESS',
    'SANRO_STUDIO_ENABLE_DTF_ADMIN_GENERATE',
    'SANRO_STUDIO_ENABLE_DTF_IMAGE_RASTER',
    'SANRO_STUDIO_ENABLE_DTF_LAYER_RASTER',
    'SANRO_STUDIO_ENABLE_DTF_PRIVATE_ARTIFACTS',
    'SANRO_STUDIO_ENABLE_DTF_ORDER_BATCH',
    'SANRO_STUDIO_ENABLE_DRIVE_AUTO_PROVISION',
    'SANRO_STUDIO_ENABLE_VERIFIED_DRIVE_ATTACHMENT',
] as $flag) {
    if (defined($flag) && constant($flag) === true) {
        fwrite(STDERR, "BLOCKED: one or more read-only/DTF/Drive feature flags unexpectedly ON\n");
        exit(2);
    }
}
$module = $root . '/wp-content/plugins/sanro-studio/includes/dtf-admin-readiness.php';
$present = is_file($module)
    && !is_link($module)
    && function_exists('sanro_studio_dtf_admin_readiness_enabled')
    && has_action('woocommerce_after_order_itemmeta',
        'sanro_studio_admin_item_dtf_readiness') !== false;
$absent = !file_exists($module)
    && !is_link($module)
    && !function_exists('sanro_studio_dtf_admin_readiness_enabled')
    && has_action('woocommerce_after_order_itemmeta',
        'sanro_studio_admin_item_dtf_readiness') === false;
if ($phase === 'before' ? !$absent : (!$present
    || sanro_studio_dtf_admin_readiness_enabled() !== false)) {
    fwrite(STDERR, "BLOCKED: readiness pre/postflight mismatch or read-only feature ON\n");
    exit(2);
}
echo "PROBE_PHASE: " . strtoupper($phase) . "\n";
echo "PR38_WP_RUNTIME: VERIFIED\n";
echo "HPOS_RUNTIME: ENABLED\n";
echo "LEGACY_PREVIEW_STATUS_GENERATOR_HOOKS: REGISTERED\n";
echo "READINESS_HOOK: " . ($phase === 'before' ? 'ABSENT' : 'REGISTERED') . "\n";
echo "READINESS_ENABLED: NO\n";
echo "GENERATOR_ENABLED: NO\n";
echo "DTF_RASTER_PRIVATE_BATCH: OFF\n";
echo "DRIVE_WRITERS: OFF\n";
echo "PROBE_STATUS: PASS\n";
