<?php
define('ABSPATH', __DIR__);
require dirname(__DIR__) . '/includes/core.php';
function assert_native($condition, $message) {
    if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
    echo "PASS: $message\n";
}
assert_native(!function_exists('sanro_studio_boot_runtime')
    && !function_exists('sanro_studio_dependencies')
    && !function_exists('sanro_studio_filter_settings'),
    'native core does not load legacy compatibility functions');
$status = sanro_studio_native_core_status();
assert_native($status['valid'] && $status['native_components_present'],
    'native function inventory detects provided fixtures');
assert_native(!$status['woocommerce_loaded'] && !$status['runtime_dependencies_satisfied'],
    'missing WooCommerce keeps runtime readiness false despite native symbols');
assert_native(!$status['checkout_enabled'] && !$status['production_ready']
    && !$status['verified_end_to_end'], 'release gates remain closed');
assert_native(!$status['legacy_compat_enabled'], 'legacy compatibility disabled by default');
function sanro_studio_render_buyer_editor() {}
function sanro_studio_public_editor_config() {}
function sanro_studio_validate_product_design() {}
function sanro_studio_quote_size_quantities() {}
function sanro_studio_create_draft() {}
function sanro_studio_store_owned_draft_preview() {}
function sanro_studio_prepare_cart_design_contract() {}
function sanro_studio_order_item_production_manifest() {}
$present = sanro_studio_native_core_status();
assert_native($present['native_components_present'] && !$present['missing_components'],
    'all required native symbols present');
assert_native(!$present['runtime_dependencies_satisfied'],
    'native symbols do not bypass missing WooCommerce');
assert_native(!$present['verified_end_to_end'], 'symbols never imply end-to-end verification');
