<?php
/**
 * Offline contract checks for the existing SANRO Studio staging QA paths.
 * This test never bootstraps WordPress or performs network/database actions.
 * Run with SANRO_STUDIO_REPO_ROOT set to the reviewed repository snapshot.
 */
declare(strict_types=1);

$root = getenv('SANRO_STUDIO_REPO_ROOT') ?: dirname(__DIR__);
$files = [
    'transport' => 'tools/staging-web-release/sanro-studio-staging-release.php',
    'mixed' => 'tools/staging-web-release/mixed-checkout-acceptance.php',
    'classic' => 'tools/staging-web-release/checkout-acceptance.php',
    'policy' => 'plugins/sanro-studio/includes/checkout-policy.php',
    'hpos_read' => 'tests/staging-hpos-order-metadata-readonly.php',
    'hpos_cli' => 'ops/hostinger/sanro-studio-pr32-qa-order.php',
    'hpos_policy' => 'ops/hostinger/sanro-studio-pr32-qa-order-policy.php',
    'builder' => 'tools/build-studio-candidate.py',
];
$source = [];
foreach ($files as $key => $relative) {
    $path = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relative;
    if (!is_file($path) || is_link($path)) {
        fwrite(STDERR, "BLOCKED: reviewed source missing or unsafe: {$relative}\n");
        exit(2);
    }
    $source[$key] = file_get_contents($path);
    if (!is_string($source[$key])) {
        fwrite(STDERR, "BLOCKED: reviewed source unreadable: {$relative}\n");
        exit(2);
    }
}

$checks = [];
$check = static function (string $name, bool $passed) use (&$checks): void {
    $checks[$name] = $passed;
};

$check('exact staging host is hard-coded', str_contains($source['transport'], "const SSWR_TARGET='https://saddlebrown-peafowl-616656.hostingersite.com';"));
$check('canonical document root is pinned', str_contains($source['transport'], 'realpath(ABSPATH)!==SSWR_DOCROOT') && str_contains($source['transport'], "PHP_SAPI!=='litespeed'"));
$check('admin install uses capability and nonce', str_contains($source['transport'], "current_user_can('install_plugins')") && str_contains($source['transport'], "check_admin_referer('sswr-'.SSWR_SOURCE)"));
$check('REST POST requires WordPress nonce', str_contains($source['transport'], 'wp_verify_nonce($request->get_header(\'X-WP-Nonce\'),\'wp_rest\')'));
$check('order and production dispatch remain disabled', str_contains($source['transport'], 'Legacy order and production acceptance actions are disabled') && str_contains($source['transport'], "['response'=>403]"));
$check('Classic checkout boundary adds an error before order creation', str_contains($source['classic'], 'woocommerce_after_checkout_validation') && str_contains($source['classic'], "add('sanro_staging_one_order_boundary'") && str_contains($source['classic'], "'order_creation_invoked'=>false"));
$check('mixed boundary rechecks native Studio quote and blocks order creation', str_contains($source['mixed'], 'sanro_studio_pricing_checkout_design') && str_contains($source['mixed'], 'woocommerce_after_checkout_validation') && str_contains($source['mixed'], 'berhenti sebelum profil pelanggan, order atau pembayaran'));
$check('mixed fixture requires two distinct designs and server total', str_contains($source['mixed'], 'count(array_unique(array_column($designs, \'public_id\'))) !== 2') && str_contains($source['mixed'], 'mixed_total_or_type_mismatch'));
$check('mixed lock probe checks stale and duplicate submission then rolls back', str_contains($source['mixed'], 'duplicate_lock_accepted') && str_contains($source['mixed'], 'ISOLATED_DATABASE_CAS_PASS') && str_contains($source['mixed'], 'ROLLBACK'));
$check('Woo Store API policy is an actual Studio hook', str_contains($source['policy'], "add_action('woocommerce_store_api_cart_errors','sanro_studio_store_api_checkout_policy_check',5,2)") && str_contains($source['policy'], "errors->add('sanro_studio_pricing_unavailable'"));
$check('HPOS metadata inspection is read-only', !preg_match('/->(?:save|delete|update_meta_data|add_item)\s*\(/i', $source['hpos_read']) && str_contains($source['hpos_read'], 'MODE: READ_ONLY'));
$check('CLI fixture requires exact staging and HPOS', str_contains($source['hpos_cli'], 'sanro_s142_staging_identity_ok') && str_contains($source['hpos_cli'], 'custom_orders_table_usage_is_enabled'));
$check('CLI fixture suppresses mail and prevents automatic duplicate retry', str_contains($source['hpos_cli'], "add_filter('pre_wp_mail'") && str_contains($source['hpos_cli'], 'no automatic retry or second order'));
$check('CLI fixture is zero-charge and has no product stock link', str_contains($source['hpos_cli'], 'No product_id => no stock') && str_contains($source['hpos_cli'], 'PAYMENT: NONE'));
$check('Final Release builder packages plugin subtree only', str_contains($source['builder'], "root='plugins/sanro-studio/'") && str_contains($source['builder'], "if relative.startswith('tests/'):continue"));

$failed = [];
foreach ($checks as $name => $passed) {
    echo ($passed ? 'PASS: ' : 'FAIL: ') . $name . "\n";
    if (!$passed) $failed[] = $name;
}
echo 'RESULT: ' . count($checks) . ' checks; ' . (count($checks) - count($failed)) . " PASS; " . count($failed) . " FAIL\n";
echo "SCOPE: OFFLINE_SOURCE_CONTRACT_ONLY; NO_WP_BOOTSTRAP_NO_NETWORK_NO_ORDER\n";
exit($failed ? 1 : 0);
