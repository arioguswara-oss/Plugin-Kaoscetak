<?php
define('ABSPATH', __DIR__);
define('SANRO_STUDIO_ENABLE_BUYER_EDITOR', true);
class WooCommerce {}
function sanro_studio_render_buyer_editor() {}
function sanro_studio_public_editor_config() {}
function sanro_studio_validate_product_design() {}
function sanro_studio_quote_size_quantities() {}
function sanro_studio_create_draft() {}
function sanro_studio_store_owned_draft_preview() {}
function sanro_studio_prepare_cart_design_contract() {}
function sanro_studio_order_item_production_manifest() {}
require dirname(__DIR__) . '/includes/core.php';
$status = sanro_studio_native_core_status();
if (!$status['native_components_present'] || !$status['woocommerce_loaded']
    || !$status['runtime_dependencies_satisfied'] || !$status['ready_for_staging']
    || $status['checkout_enabled'] || $status['production_ready']
    || $status['verified_end_to_end'] || $status['legacy_runtime_loaded']) {
    fwrite(STDERR, "FAIL: staging gate incorrectly reported\n");
    exit(1);
}
echo "PASS: staging gate enabled, production release gates closed\n";
