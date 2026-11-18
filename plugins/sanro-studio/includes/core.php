<?php
defined('ABSPATH') || exit;

/**
 * Native SANRO buyer capability report, independent of the legacy editor.
 * This is a capability inventory, not a release-readiness assertion.
 */
function sanro_studio_native_capabilities() {
    return [
        'buyer_editor' => function_exists('sanro_studio_render_buyer_editor'),
        'product_configuration' => function_exists('sanro_studio_public_editor_config'),
        'design_validation' => function_exists('sanro_studio_validate_product_design'),
        'server_quote' => function_exists('sanro_studio_quote_size_quantities'),
        'private_text_drafts' => function_exists('sanro_studio_create_draft'),
        'private_preview_storage' => function_exists('sanro_studio_store_owned_draft_preview'),
        'cart_contract' => function_exists('sanro_studio_prepare_cart_design_contract'),
        'order_item_manifest' => function_exists('sanro_studio_order_item_production_manifest'),
        'legacy_runtime_loaded' => function_exists('sanro_studio_boot_runtime'),
        'woocommerce_loaded' => class_exists('WooCommerce', false),
        'private_master_storage' => false,
        'checkout' => false,
        'production_ready' => false,
    ];
}

/**
 * Native readiness is deliberately conservative: presence of PHP functions
 * does not prove that buyer workflows or production integrations are tested.
 */
function sanro_studio_native_core_status() {
    $capabilities = sanro_studio_native_capabilities();
    $required = ['buyer_editor', 'product_configuration', 'design_validation',
        'server_quote', 'private_text_drafts', 'private_preview_storage',
        'cart_contract', 'order_item_manifest'];
    $missing = [];
    foreach ($required as $name) {
        if (empty($capabilities[$name])) $missing[] = $name;
    }
    return [
        'valid' => true,
        'native_components_present' => !$missing,
        'woocommerce_loaded' => $capabilities['woocommerce_loaded'],
        'runtime_dependencies_satisfied' => !$missing && $capabilities['woocommerce_loaded'],
        'ready_for_staging' => !$missing && $capabilities['woocommerce_loaded']
            && defined('SANRO_STUDIO_ENABLE_BUYER_EDITOR')
            && SANRO_STUDIO_ENABLE_BUYER_EDITOR === true,
        'missing_components' => $missing,
        'legacy_compat_enabled' => defined('SANRO_STUDIO_ENABLE_LEGACY_COMPAT')
            && SANRO_STUDIO_ENABLE_LEGACY_COMPAT === true,
        'legacy_runtime_loaded' => $capabilities['legacy_runtime_loaded'],
        'checkout_enabled' => false,
        'production_ready' => false,
        'verified_end_to_end' => false,
    ];
}
