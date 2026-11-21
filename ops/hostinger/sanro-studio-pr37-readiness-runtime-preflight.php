<?php
/**
 * PR37 PLAN-only WordPress preflight, exact Hostinger staging only.
 * No user/order lookup, no database mutation, cron disabled.
 */
if (PHP_SAPI !== 'cli'
    || getenv('SANRO_STUDIO_QA_ENV') !== 'staging'
    || getenv('SANRO_STUDIO_PR37_MODE') !== 'plan'
    || explode('.', (string) gethostname())[0] !== 'id-dci-web1631'
    || get_current_user() !== 'u606139982') {
    fwrite(STDERR, "BLOCKED: Hostinger staging PLAN guard\n");
    exit(2);
}
$host = 'saddlebrown-peafowl-616656.hostingersite.com';
$root = '/home/u606139982/domains/' . $host . '/public_html';
if (realpath($root) !== $root || !is_file($root . '/wp-load.php')) {
    fwrite(STDERR, "BLOCKED: staging WordPress root mismatch\n");
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
if (realpath(ABSPATH) !== $root
    || !function_exists('home_url') || !function_exists('site_url')
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
        'sanro_studio_admin_item_generate_dtf_action') === false) {
    fwrite(STDERR, "BLOCKED: live PR35 Woo/HPOS/admin hooks or generator OFF drift\n");
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
        fwrite(STDERR, "BLOCKED: readiness or DTF/Drive writer flag unexpectedly ON\n");
        exit(2);
    }
}
if (function_exists('sanro_studio_dtf_admin_readiness_enabled')
    || has_action('woocommerce_after_order_itemmeta',
        'sanro_studio_admin_item_dtf_readiness') !== false
    || file_exists($root . '/wp-content/plugins/sanro-studio/includes/dtf-admin-readiness.php')) {
    fwrite(STDERR, "BLOCKED: readiness module already installed, do not overwrite\n");
    exit(2);
}
echo "PR37_WP_RUNTIME: VERIFIED\n";
echo "PR35_GENERATOR_HOOK: REGISTERED\n";
echo "PR35_GENERATOR_FLAG: OFF\n";
echo "PR37_READINESS_HOOK: ABSENT\n";
echo "PR37_READINESS_FLAG: OFF\n";
echo "DTF_RASTER_PRIVATE_BATCH: OFF\n";
echo "DRIVE_WRITERS: OFF\n";
echo "HPOS_RUNTIME: ENABLED\n";
echo "STAGING_WP_READ_ONLY: PASS\n";
