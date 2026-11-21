<?php
/**
 * Sprint 139: Hostinger SANRO Studio WordPress/WooCommerce runtime preflight.
 * Run only from an isolated QA checkout with explicit SANRO_STUDIO_QA_ENV.
 * Does not create/edit/delete orders, users, products, settings or files.
 *
 * Loading wp-load.php executes the site's ordinary bootstrap and init hooks;
 * this probe adds no mutations of its own. No production host is permitted.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "BLOCKED: CLI only\n");
    exit(2);
}
$expectedHost = 'saddlebrown-peafowl-616656.hostingersite.com';
$expectedRoot = '/home/u606139982/domains/'
    . $expectedHost . '/public_html';
$node = gethostname();
if (getenv('SANRO_STUDIO_QA_ENV') !== 'staging'
    || !is_string($node) || strpos($node, 'id-dci-web1631') !== 0) {
    fwrite(STDERR, "BLOCKED: Hostinger SANRO Studio staging only\n");
    exit(2);
}
$root = realpath(getenv('SANRO_STUDIO_STAGING_WP_ROOT') ?: $expectedRoot);
if (!$root || $root !== realpath($expectedRoot)
    || !is_file($root . '/wp-load.php')
    || (!is_file($root . '/wp-config.php')
        && !is_file(dirname($root) . '/wp-config.php'))) {
    fwrite(STDERR, "BLOCKED: exact staging WordPress root not found\n");
    exit(2);
}
$sourceRoot = realpath(dirname(__DIR__, 2));
if (!$sourceRoot || strpos($sourceRoot, $root . DIRECTORY_SEPARATOR) === 0
    || $sourceRoot === $root) {
    fwrite(STDERR, "BLOCKED: QA source cannot reside inside WordPress document root\n");
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
require_once $root . '/wp-load.php';

function sanro_s139_out($key, $value) {
    if (!preg_match('/^[A-Z0-9_]+$/D', $key)) return;
    $value = is_bool($value) ? ($value ? 'YES' : 'NO') : (string) $value;
    echo $key . ': ' . preg_replace('/[^a-zA-Z0-9._: -]/', '_', $value) . "\n";
}
function sanro_s139_flag($constant) {
    return defined($constant) && constant($constant) === true;
}
$home = function_exists('home_url') ? rtrim(home_url('/'), '/') : '';
$site = function_exists('site_url') ? rtrim(site_url('/'), '/') : '';
if ($home !== 'https://' . $expectedHost || $site !== 'https://' . $expectedHost) {
    fwrite(STDERR, "BLOCKED: WordPress home/site URL mismatches staging host\n");
    exit(2);
}
sanro_s139_out('STAGING_HOST', $expectedHost);
sanro_s139_out('WP_RUNTIME', 'AVAILABLE');
sanro_s139_out('WP_VERSION', function_exists('get_bloginfo') ? get_bloginfo('version') : 'UNKNOWN');
sanro_s139_out('PHP_VERSION', PHP_VERSION);
sanro_s139_out('WP_ENVIRONMENT', function_exists('wp_get_environment_type')
    ? wp_get_environment_type() : 'UNKNOWN');

// Inspect LIVE loaded state; do not infer it from the Git checkout.
if (!function_exists('get_plugins')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
$plugins = function_exists('get_plugins') ? get_plugins() : [];
$studioRel = null;
foreach ($plugins as $file => $data) {
    if (is_array($data) && ($data['Name'] ?? null) === 'SANRO Studio') {
        $studioRel = $file;
        break;
    }
}
$studioActive = $studioRel !== null && function_exists('is_plugin_active')
    && is_plugin_active($studioRel);
$wooActive = function_exists('is_plugin_active')
    && is_plugin_active('woocommerce/woocommerce.php');
$wooAvailable = $wooActive && function_exists('wc_get_order')
    && class_exists('WooCommerce');
sanro_s139_out('WOOCOMMERCE_ACTIVE', $wooActive);
sanro_s139_out('WOOCOMMERCE_RUNTIME', $wooAvailable ? 'AVAILABLE' : 'UNAVAILABLE');
sanro_s139_out('WOOCOMMERCE_VERSION', defined('WC_VERSION') ? WC_VERSION : 'UNKNOWN');
sanro_s139_out('SANRO_STUDIO_INSTALLED', $studioRel !== null);
sanro_s139_out('SANRO_STUDIO_ACTIVE', $studioActive);
sanro_s139_out('SANRO_STUDIO_VERSION', $studioRel !== null
    ? ($plugins[$studioRel]['Version'] ?? 'UNKNOWN') : 'NOT_INSTALLED');

// Compare live deployed files with this *candidate* checkout without
// modifying the live plugin; files differing is NOT a QA FAIL by itself.
$localDir = $studioRel !== null ? realpath(WP_PLUGIN_DIR . '/' . dirname($studioRel)) : false;
$repoPlugin = $sourceRoot . '/plugins/sanro-studio';
$liveMain = $studioRel !== null ? WP_PLUGIN_DIR . '/' . $studioRel : '';
$repoMain = $repoPlugin . '/sanro-studio.php';
$liveAdmin = $localDir ? $localDir . '/includes/dtf-admin-order-status.php' : '';
$repoAdmin = $repoPlugin . '/includes/dtf-admin-order-status.php';
$mainMatch = $studioRel !== null && is_file($liveMain) && is_file($repoMain)
    && hash_equals(hash_file('sha256', $repoMain), hash_file('sha256', $liveMain));
$adminMatch = $localDir && is_file($liveAdmin) && is_file($repoAdmin)
    && hash_equals(hash_file('sha256', $repoAdmin), hash_file('sha256', $liveAdmin));
sanro_s139_out('STAGING_PLUGIN_MAIN_MATCHES_CANDIDATE', $mainMatch);
sanro_s139_out('STAGING_ADMIN_MODULE_MATCHES_CANDIDATE', (bool) $adminMatch);
sanro_s139_out('CURRENT_ADMIN_HOOK', $studioActive
    && function_exists('has_action')
    && has_action('woocommerce_after_order_itemmeta',
        'sanro_studio_admin_item_dtf_status') !== false ? 'REGISTERED' : 'ABSENT');
sanro_s139_out('CURRENT_PREVIEW_HOOK', $studioActive
    && function_exists('has_action')
    && has_action('woocommerce_after_order_itemmeta',
        'sanro_studio_admin_item_preview_status') !== false ? 'REGISTERED' : 'ABSENT');

sanro_s139_out('DTF_ADMIN_STATUS_FLAG', sanro_s139_flag('SANRO_STUDIO_ENABLE_DTF_ADMIN_STATUS'));
sanro_s139_out('DTF_PRIVATE_ARTIFACT_FLAG', sanro_s139_flag('SANRO_STUDIO_ENABLE_DTF_PRIVATE_ARTIFACTS'));
sanro_s139_out('DTF_ORDER_BATCH_FLAG', sanro_s139_flag('SANRO_STUDIO_ENABLE_DTF_ORDER_BATCH'));

// HPOS and compatibility synchronization are READ, never changed.
$hpos = 'UNKNOWN';
if ($wooAvailable
    && class_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil')
    && is_callable(['Automattic\\WooCommerce\\Utilities\\OrderUtil',
        'custom_orders_table_usage_is_enabled'])) {
    $hpos = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
        ? 'ENABLED' : 'DISABLED';
}
sanro_s139_out('HPOS_RUNTIME', $hpos);
$sync = function_exists('get_option')
    ? get_option('woocommerce_custom_orders_table_data_sync_enabled', 'UNKNOWN') : 'UNKNOWN';
sanro_s139_out('HPOS_SYNC_OPTION', in_array($sync, ['yes', 'no'], true)
    ? strtoupper($sync) : 'UNKNOWN');

// Role capability audit reads role definitions, never user identities.
foreach ([
    'ADMINISTRATOR' => 'administrator',
    'SHOP_MANAGER' => 'shop_manager',
    'SUBSCRIBER' => 'subscriber',
] as $label => $slug) {
    $role = function_exists('get_role') ? get_role($slug) : null;
    sanro_s139_out('ROLE_' . $label . '_MANAGE_WOO',
        $role && $role->has_cap('manage_woocommerce') ? 'YES' : 'NO');
}

// Optional REAL WooCommerce order readback. Requires a known STAGING-ONLY
// synthetic test order ID; never discover or print customer/order data.
$orderValue = getenv('SANRO_STUDIO_QA_ORDER_ID');
if ($orderValue === false || $orderValue === '') {
    sanro_s139_out('STAGING_ORDER_BINDING_TEST', 'NOT_TESTED');
} elseif (!ctype_digit($orderValue) || (int) $orderValue < 1 || !$wooAvailable) {
    sanro_s139_out('STAGING_ORDER_BINDING_TEST', 'BLOCKED');
} else {
    $order = wc_get_order((int) $orderValue);
    $items = $order && method_exists($order, 'get_items')
        ? $order->get_items('line_item') : [];
    sanro_s139_out('STAGING_ORDER_BINDING_TEST',
        $order && is_array($items) && count($items) > 0 ? 'READABLE' : 'NOT_FOUND');
    // Never print order ID, names, emails, item contents, shipping or payment.
}

// Real wp-admin browser visibility is NOT tested by this CLI probe.
sanro_s139_out('WP_ADMIN_BROWSER_E2E', 'NOT_TESTED');
sanro_s139_out('GITHUB_CANDIDATE_DEPLOYED', $studioActive && $mainMatch && $adminMatch);
sanro_s139_out('PRODUCTION_MODIFIED', 'NO');
sanro_s139_out('RUNTIME_PREFLIGHT', $wooAvailable ? 'PASS' : 'BLOCKED_WOOCOMMERCE');
exit($wooAvailable ? 0 : 2);
