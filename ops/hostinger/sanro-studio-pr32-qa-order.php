<?php
/**
 * Sprint 142 — exactly one owner-approved, zero-total, no-product Woo order
 * fixture for SANRO Studio admin panel. PHP CLI on exact Hostinger staging.
 *
 * MODE=plan: read-only. MODE=create: the ONLY authorized DB change is one
 * synthetic pending WooCommerce order with one unlinked QA line item.
 * Does not checkout, take payment, upload files or trigger DTF candidate work.
 */
if (PHP_SAPI !== 'cli' || count($argv) !== 1) {
    fwrite(STDERR, "BLOCKED: isolated CLI entrypoint only\n");
    exit(2);
}
require_once __DIR__ . '/sanro-studio-pr32-qa-order-policy.php';
$mode = getenv('SANRO_STUDIO_QA_ORDER_MODE') ?: 'plan';
$qaEnv = getenv('SANRO_STUDIO_QA_ENV') ?: '';
$node = gethostname();
$host = is_string($node) ? explode('.', $node)[0] : '';
$user = function_exists('get_current_user') ? get_current_user() : '';
if (!in_array($mode, ['plan','create'], true)
    || $qaEnv !== 'staging'
    || $host !== 'id-dci-web1631'
    || $user !== 'u606139982'
    || (function_exists('posix_geteuid') && posix_geteuid() === 0)
    || ($mode === 'create'
        && getenv('SANRO_STUDIO_APPROVE_ONE_QA_ORDER')
            !== 'ONE_ZERO_STAGING_ORDER_APPROVED')) {
    fwrite(STDERR, "BLOCKED: exact staging owner, mode and approval required\n");
    exit(2);
}

$root = realpath(SANRO_STUDIO_S142_STAGE_ROOT);
if ($root !== SANRO_STUDIO_S142_STAGE_ROOT
    || !is_file($root . '/wp-load.php')
    || !is_file($root . '/wp-config.php')
       && !is_file(dirname($root) . '/wp-config.php')
    || !defined('SANRO_STUDIO_S142_STAGE_ROOT')) {
    fwrite(STDERR, "BLOCKED: exact Hostinger staging WordPress root not found\n");
    exit(2);
}
if (!defined('DISABLE_WP_CRON')) define('DISABLE_WP_CRON', true);
if (!defined('WP_USE_THEMES')) define('WP_USE_THEMES', false);
$_SERVER['HTTP_HOST'] = SANRO_STUDIO_S142_STAGE_HOST;
$_SERVER['SERVER_NAME'] = SANRO_STUDIO_S142_STAGE_HOST;
$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_PORT'] = '443';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
require_once $root . '/wp-load.php';

$home = function_exists('home_url') ? rtrim(home_url('/'), '/') : '';
$site = function_exists('site_url') ? rtrim(site_url('/'), '/') : '';
if (!sanro_s142_staging_identity_ok($host, $user, $qaEnv,
        realpath(ABSPATH), $home, $site)
    || !class_exists('WC_Order')
    || !class_exists('WC_Order_Item_Product')
    || !function_exists('wc_get_order')
    || !function_exists('wc_get_orders')
    || !function_exists('wp_generate_uuid4')
    || !class_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil')
    || !call_user_func(['Automattic\\WooCommerce\\Utilities\\OrderUtil', 'custom_orders_table_usage_is_enabled'])
    || !function_exists('sanro_studio_dtf_admin_status_enabled')
    || !sanro_studio_dtf_admin_status_enabled()
    || !function_exists('has_action')
    || has_action('woocommerce_after_order_itemmeta',
        'sanro_studio_admin_item_dtf_status') === false) {
    fwrite(STDERR, "BLOCKED: staging WooCommerce/HPOS/admin DTF integration guard failed\n");
    exit(2);
}

// Core print/production/Drive writers must stay OFF. Existing staging
// buyer checkout flags may stay ON; this standalone CLI never calls WC_Checkout
// and does not dispatch any checkout hook, even during WC_Order::save().
$writerNames = [
    'SANRO_STUDIO_ENABLE_DTF_IMAGE_RASTER',
    'SANRO_STUDIO_ENABLE_DTF_LAYER_RASTER',
    'SANRO_STUDIO_ENABLE_DTF_PRIVATE_ARTIFACTS',
    'SANRO_STUDIO_ENABLE_DTF_ORDER_BATCH',
    'SANRO_STUDIO_ENABLE_DRIVE_AUTO_PROVISION',
    'SANRO_STUDIO_ENABLE_VERIFIED_DRIVE_ATTACHMENT',
];
$writerFlags = [];
foreach ($writerNames as $name) {
    $writerFlags[$name] = defined($name) && constant($name) === true;
}
if (!sanro_s142_writer_flags_safe($writerFlags)) {
    fwrite(STDERR, "BLOCKED: DTF or Google Drive production writer enabled\n");
    exit(2);
}
$checkoutHooks = [
    'woocommerce_checkout_process',
    'woocommerce_checkout_create_order_line_item',
    'woocommerce_checkout_create_order',
    'woocommerce_checkout_order_created',
];
$checkoutActionsFired = [];
foreach ($checkoutHooks as $hook) {
    $checkoutActionsFired[] = function_exists('did_action')
        ? (int) did_action($hook) : -1;
}
if (!sanro_s142_checkout_not_invoked(PHP_SAPI === 'cli',
        $_SERVER['REQUEST_METHOD'] ?? null, $checkoutActionsFired)) {
    fwrite(STDERR, "BLOCKED: checkout workflow already entered in QA CLI\n");
    exit(2);
}

if (function_exists('wp_get_environment_type')
    && wp_get_environment_type() === 'production') {
    echo "WARNING: WP_ENVIRONMENT reports production; exact staging host/root/site and approval were independently checked\n";
}

// WC's meta_query is supported when HPOS is enabled. Limit 2 to fail closed
// if more than one fixture exists; never search or print real customer orders.
try {
    $existing = wc_get_orders([
        'type' => 'shop_order',
        'status' => array_keys(wc_get_order_statuses()),
        'limit' => 2,
        'return' => 'ids',
        'meta_query' => [[
            'key' => '_sanro_studio_qa_fixture',
            'value' => SANRO_STUDIO_S142_FIXTURE,
            'compare' => '=',
        ]],
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, "BLOCKED: HPOS QA fixture lookup failed; no create attempted\n");
    exit(2);
}
if (!is_array($existing) || count($existing) > 1) {
    fwrite(STDERR, "BLOCKED: QA fixture lookup ambiguous; no create attempted\n");
    exit(2);
}
$stateDir = '/home/u606139982/.local/state/sanro-studio-qa-order';
$stateFile = $stateDir . '/s142-order-id';
$stateId = null;
if (is_link($stateFile)) {
    fwrite(STDERR, "BLOCKED: unsafe local order state link\n");
    exit(2);
}
if (is_file($stateFile)) {
    if ((fileperms($stateFile) & 0077) !== 0) {
        fwrite(STDERR, "BLOCKED: unsafe local order state permissions\n");
        exit(2);
    }
    $content = trim((string) @file_get_contents($stateFile));
    if (!preg_match('/^[1-9][0-9]{0,8}$/D', $content)) {
        fwrite(STDERR, "BLOCKED: invalid QA order state\n");
        exit(2);
    }
    $stateId = (int) $content;
}

echo "TARGET: HOSTINGER_SANRO_STAGING_ONLY\n";
echo "HPOS: ENABLED\n";
echo "ORDER_FIXTURE: SANRO_DTF_ADMIN_SINGLE_QA\n";
echo "MODE: " . strtoupper($mode) . "\n";
if (count($existing) === 1) {
    $orderId = (int) reset($existing);
    $order = wc_get_order($orderId);
    if ($orderId < 1 || !sanro_s142_fixture_is_safe($order)
        || ($stateId !== null && $stateId !== $orderId)) {
        fwrite(STDERR, "BLOCKED: existing marked order is not the approved QA fixture\n");
        exit(2);
    }
    echo "FIXTURE_STATUS: EXISTING_SAFE_REUSE\n";
    echo "SANRO_STAGING_ADMIN_ORDER_ID: $orderId\n";
    echo "ORDER_TOTAL: 0 IDR\n";
    echo "PAYMENT: NONE\n";
    echo "PRODUCTION_MODIFIED: NO\n";
    exit(0);
}
if ($stateId !== null) {
    fwrite(STDERR, "BLOCKED: previous QA fixture state exists but HPOS order was not found\n");
    exit(2);
}
if ($mode === 'plan') {
    echo "FIXTURE_STATUS: READY_TO_CREATE_ONE_ZERO_PENDING_ORDER\n";
    echo "WOOCOMMERCE_ORDER_WRITE: NO\n";
    echo "PRODUCTION_MODIFIED: NO\n";
    exit(0);
}
if (!is_dir($stateDir) && !mkdir($stateDir, 0700, true)
    || is_link($stateDir) || !is_dir($stateDir)
    || (fileperms($stateDir) & 0077) !== 0) {
    fwrite(STDERR, "BLOCKED: private fixture directory unsafe\n");
    exit(2);
}

// Suppress outgoing wp_mail (including WooCommerce emails) for THIS isolated
// CLI invocation; never set a real customer/email/phone/billing/shipping.
if (function_exists('add_filter')) {
    add_filter('pre_wp_mail', static function ($value) { return true; }, PHP_INT_MAX);
}
$uuid = strtolower(wp_generate_uuid4());
if (!sanro_s142_valid_design_uuid($uuid)) {
    fwrite(STDERR, "BLOCKED: generated synthetic UUID invalid\n");
    exit(2);
}
try {
    // No product_id => no stock, price, SKU, customer or inventory effects.
    $order = new WC_Order();
    $order->set_created_via('sanro_studio_staging_qa');
    $order->set_status('pending');
    $order->set_currency('IDR');
    $order->set_customer_id(0);
    $order->set_payment_method('');
    $order->set_transaction_id('');
    $order->set_total(0);
    $order->update_meta_data('_sanro_studio_qa_fixture',
        SANRO_STUDIO_S142_FIXTURE);

    $item = new WC_Order_Item_Product();
    $item->set_name('SANRO STUDIO QA - NON PRODUCTION');
    $item->set_product_id(0);
    $item->set_quantity(1);
    $item->set_subtotal(0);
    $item->set_total(0);
    $item->add_meta_data('_sanro_studio_design_id', $uuid, true);
    $order->add_item($item);
    $newId = (int) $order->save();
    // Recheck no WooCommerce checkout flow was dispatched by CRUD save.
    // A failed post-write guard is a hard stop: NEVER automatically retry.
    $checkoutActionsAfter = [];
    foreach ($checkoutHooks as $hook) {
        $checkoutActionsAfter[] = (int) did_action($hook);
    }
    if (!sanro_s142_checkout_not_invoked(true, 'GET', $checkoutActionsAfter)) {
        fwrite(STDERR,
            "FAIL: Woo checkout hooks fired during synthetic fixture; do not retry\n");
        exit(1);
    }
    $readback = $newId > 0 ? wc_get_order($newId) : false;
    if (!sanro_s142_fixture_is_safe($readback)) {
        fwrite(STDERR,
            "FAIL: QA order save/readback did not satisfy safe fixture contract\n");
        exit(1);
    }
    // Local idempotence ledger contains ONLY this synthetic order ID.
    // On unexpected write failure, HPOS marker lookup still prevents a second.
    if (file_put_contents($stateFile, (string)$newId . "\n",
            LOCK_EX) === false || !chmod($stateFile, 0600)) {
        fwrite(STDERR,
            "FAIL: order saved but private idempotence marker write failed; do not rerun blindly\n");
        exit(1);
    }
    echo "FIXTURE_STATUS: CREATED_VERIFIED\n";
    echo "SANRO_STAGING_ADMIN_ORDER_ID: $newId\n";
    echo "ORDER_STATUS: pending\n";
    echo "ORDER_TOTAL: 0 IDR\n";
    echo "ORDER_ITEMS: 1 SYNTHETIC\n";
    echo "PAYMENT: NONE\n";
    echo "DTF_PRIVATE_ARTIFACTS: OFF\n";
    echo "DTF_ORDER_BATCH: OFF\n";
    echo "PRODUCTION_MODIFIED: NO\n";
} catch (Throwable $e) {
    fwrite(STDERR,
        "FAIL: WooCommerce synthetic order operation failed; no automatic retry or second order\n");
    exit(1);
}
