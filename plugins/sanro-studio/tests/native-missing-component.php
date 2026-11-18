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
// Intentionally omit order item manifest to test fail-closed readiness.
require dirname(__DIR__) . '/includes/core.php';
$status = sanro_studio_native_core_status();
if ($status['native_components_present'] || $status['runtime_dependencies_satisfied']
    || $status['ready_for_staging'] || $status['verified_end_to_end']
    || $status['checkout_enabled'] || $status['production_ready']
    || $status['missing_components'] !== ['order_item_manifest']) {
    fwrite(STDERR, "FAIL: missing native component did not fail closed\n");
    exit(1);
}
echo "PASS: missing order manifest blocks staging readiness\n";
