<?php
/**
 * Fail-closed readiness regression for the native editor.
 * Run each scenario in a separate PHP process:
 * php plugins/sanro-studio/tests/native-readiness-gates.php missing_woocommerce
 * php plugins/sanro-studio/tests/native-readiness-gates.php buyer_disabled
 * php plugins/sanro-studio/tests/native-readiness-gates.php buyer_enabled
 */
define('ABSPATH', __DIR__);
$scenario = $argv[1] ?? '';
if (!in_array($scenario, ['missing_woocommerce', 'buyer_disabled', 'buyer_enabled'], true)) {
    fwrite(STDERR, "Unknown scenario\n");
    exit(2);
}
if ($scenario !== 'missing_woocommerce') {
    class WooCommerce {}
}
if ($scenario === 'buyer_enabled') {
    define('SANRO_STUDIO_ENABLE_BUYER_EDITOR', true);
}
foreach ([
    'sanro_studio_render_buyer_editor',
    'sanro_studio_public_editor_config',
    'sanro_studio_validate_product_design',
    'sanro_studio_quote_size_quantities',
    'sanro_studio_create_draft',
    'sanro_studio_store_owned_draft_preview',
    'sanro_studio_prepare_cart_design_contract',
    'sanro_studio_prepare_checkout_order_item',
    'sanro_studio_order_item_production_manifest',
] as $name) {
    // Define only the capability names expected by the readiness inventory.
    eval('function ' . $name . '() {}');
}
require dirname(__DIR__) . '/includes/core.php';
$status = sanro_studio_native_core_status();
$expected = $scenario === 'buyer_enabled';
if ($status['native_components_present'] !== true
    || $status['woocommerce_loaded'] !== ($scenario !== 'missing_woocommerce')
    || $status['runtime_dependencies_satisfied'] !== ($scenario !== 'missing_woocommerce')
    || $status['ready_for_staging'] !== $expected
    || $status['checkout_enabled'] !== false
    || $status['production_ready'] !== false
    || $status['verified_end_to_end'] !== false
    || $status['missing_components'] !== []) {
    fwrite(STDERR, "FAIL: readiness gate: " . $scenario . "\n");
    exit(1);
}
echo "PASS: readiness gate " . $scenario . "\n";
