<?php
/**
 * Plugin Name: SANRO Studio
 * Description: SANRO Studio native editor and WooCommerce integration, disabled by default.
 * Version: 1.1.0
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * Text Domain: sanro-studio
 * License: GPL-2.0-or-later
 */
defined('ABSPATH') || exit;

if (!defined('SANRO_STUDIO_VERSION')) {
    define('SANRO_STUDIO_VERSION', '1.1.0');
}

if (!function_exists('sanro_studio_bootstrap_status')) {
    function sanro_studio_bootstrap_status() {
        $state=['version'=>SANRO_STUDIO_VERSION];
        foreach(['editor'=>'sanro_studio_public_editor_config','cart'=>'sanro_studio_stage_add_cart_lines','checkout'=>'sanro_studio_pricing_wc_enabled','production'=>'sanro_studio_run_drive_production'] as $name=>$function){
            $state[$name]=function_exists($function)?'module_loaded; E2E_not_verified':'module_missing';
        }
        $state['wc_pricing_gate']=function_exists('sanro_studio_pricing_wc_enabled')&&sanro_studio_pricing_wc_enabled()?'ON':'OFF';
        $state['source']=function_exists('sanro_studio_build_identity')?sanro_studio_build_identity()['source_commit']:'unpackaged';
        return $state;
    }
}

if (!function_exists('sanro_studio_bootstrap_admin_page')) {
    function sanro_studio_bootstrap_admin_page() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions.', 'sanro-studio'));
        }
        echo '<div class="wrap"><h1>' . esc_html__('SANRO Studio — Integration Status', 'sanro-studio') . '</h1>';
        echo '<p>' . esc_html__('Modules shown here are source inventory. Feature flags and runtime verification are separate; this page enables nothing.', 'sanro-studio') . '</p><table class="widefat striped"><tbody>';
        foreach (sanro_studio_bootstrap_status() as $name => $state) {
            echo '<tr><th scope="row">' . esc_html($name) . '</th><td>' . esc_html($state) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
}

if (!function_exists('sanro_studio_bootstrap_admin_menu')) {
    function sanro_studio_bootstrap_admin_menu() {
        add_submenu_page(
            'sanro-studio',
            __('Status Integrasi SANRO Studio', 'sanro-studio'),
            __('Status Integrasi', 'sanro-studio'),
            'manage_woocommerce',
            'sanro-studio-status',
            'sanro_studio_bootstrap_admin_page'
        );
    }
}
// Load the historical source modules; their activation gates remain default OFF.
require_once __DIR__ . '/includes/private-master-setup.php';
if (function_exists('register_activation_hook')) {
    register_activation_hook(__FILE__, 'sanro_studio_provision_private_master_storage');
}
add_action('init', 'sanro_studio_initialize_auto_private_master_storage', 0);
require_once __DIR__ . '/includes/core.php';
require_once __DIR__ . '/includes/print-area.php';
require_once __DIR__ . '/includes/product-area.php';
require_once __DIR__ . '/includes/product-print-zones.php';
require_once __DIR__ . '/includes/pricing-profiles.php';
require_once __DIR__ . '/includes/repeat-discount.php';
require_once __DIR__ . '/includes/product-pricing.php';
require_once __DIR__ . '/includes/product-mockups.php';
require_once __DIR__ . '/includes/mockup-mapping.php';
require_once __DIR__ . '/includes/editor-config.php';
require_once __DIR__ . '/includes/pricing.php';
require_once __DIR__ . '/includes/pricing-quote.php';
require_once __DIR__ . '/includes/pricing-woocommerce.php';
require_once __DIR__ . '/includes/pricing-integration.php';
require_once __DIR__ . '/includes/checkout-policy.php';
require_once __DIR__ . '/includes/buyer.php';
require_once __DIR__ . '/includes/quote.php';
require_once __DIR__ . '/includes/cart-contract.php';
require_once __DIR__ . '/includes/cart-adapter.php';
require_once __DIR__ . '/includes/checkout-bridge.php';
require_once __DIR__ . '/includes/checkout-batch-lock.php';
require_once __DIR__ . '/includes/draft.php';
require_once __DIR__ . '/includes/storage-management.php';
require_once __DIR__ . '/includes/storage-policy.php';
require_once __DIR__ . '/includes/master-storage.php';
require_once __DIR__ . '/includes/order-preview.php';
require_once __DIR__ . '/includes/drive-production.php';
require_once __DIR__ . '/includes/drive-auto-provision.php';
require_once __DIR__ . '/includes/order-master-source.php';
require_once __DIR__ . '/includes/drive-master-export.php';
require_once __DIR__ . '/includes/dtf-image-render.php';
require_once __DIR__ . '/includes/dtf-layer-render.php';
require_once __DIR__ . '/includes/dtf-private-artifacts.php';
require_once __DIR__ . '/includes/dtf-order-workflow.php';
require_once __DIR__ . '/includes/production-package.php';
require_once __DIR__ . '/includes/production-status.php';
require_once __DIR__ . '/includes/drive-recovery-admin.php';
require_once __DIR__ . '/includes/dtf-admin-order-status.php';
require_once __DIR__ . '/includes/dtf-admin-generate.php';
require_once __DIR__ . '/includes/dtf-admin-readiness.php';
require_once __DIR__ . '/includes/production-ready-admin.php';
require_once __DIR__ . '/includes/draft-rest.php';
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/studio-ui.php';
require_once __DIR__ . '/includes/runtime-readiness.php';
if (defined('SANRO_STUDIO_ENABLE_LEGACY_COMPAT')
    && SANRO_STUDIO_ENABLE_LEGACY_COMPAT === true) {
    require_once __DIR__ . '/includes/runtime.php';
    add_filter('lumise_settings', 'sanro_studio_filter_settings', 100);
    add_action('plugins_loaded', 'sanro_studio_boot_runtime', 20);
}
// Register the root before children so WordPress resolves stable page hooks.
add_action('admin_menu', 'sanro_studio_admin_menu');
add_action('admin_menu', 'sanro_studio_bootstrap_admin_menu');
add_action('admin_post_sanro_studio_save', 'sanro_studio_save_settings');

// Product configuration is editable only through WooCommerce product admin.
add_action('woocommerce_product_options_general_product_data', 'sanro_studio_product_area_fields');
add_action('woocommerce_product_options_general_product_data', 'sanro_studio_product_readiness_notice', 30);
add_action('woocommerce_admin_process_product_object', 'sanro_studio_save_product_areas');
add_action('woocommerce_product_options_general_product_data', 'sanro_studio_product_print_zone_fields');
add_action('woocommerce_admin_process_product_object', 'sanro_studio_save_product_print_zones');
add_action('woocommerce_product_options_general_product_data', 'sanro_studio_product_pricing_fields');
add_action('woocommerce_admin_process_product_object', 'sanro_studio_save_product_pricing');
add_action('woocommerce_product_options_general_product_data', 'sanro_studio_product_mockup_fields');
add_action('woocommerce_admin_process_product_object', 'sanro_studio_save_product_mockups');
add_action('admin_enqueue_scripts', 'sanro_studio_enqueue_product_mockup_admin');
add_action('rest_api_init', 'sanro_studio_register_editor_config_route');

// The draft and upload route registrars verify their own explicit opt-in gates.
add_action('rest_api_init', 'sanro_studio_register_draft_routes');
// Registers nothing unless the independent WooCommerce pricing opt-in is true.
sanro_studio_register_pricing_wc_hooks();
sanro_studio_register_pricing_integration_hooks();
sanro_studio_register_checkout_policy_hooks();
add_action('rest_api_init', 'sanro_studio_register_preview_upload_route');
add_action('rest_api_init', 'sanro_studio_register_master_upload_route');
add_action('woocommerce_admin_order_data_after_order_details', 'sanro_studio_admin_order_preview_status');
add_action('woocommerce_admin_order_data_after_order_details', 'sanro_studio_admin_production_status', 15);
add_action('woocommerce_admin_order_data_after_order_details', 'sanro_studio_admin_production_ready_action', 20);
add_action('admin_post_sanro_studio_mark_production_ready', 'sanro_studio_admin_mark_production_ready');
add_action('admin_footer', 'sanro_studio_admin_production_ready_footer_form');
add_action('woocommerce_admin_order_data_after_order_details','sanro_studio_admin_drive_retry_action',25);
add_action('admin_footer','sanro_studio_admin_drive_retry_footer');
add_action('admin_post_sanro_studio_retry_production','sanro_studio_admin_drive_retry_post');
add_action('admin_post_sanro_studio_order_preview', 'sanro_studio_admin_deliver_order_preview');
add_action('woocommerce_after_order_itemmeta', 'sanro_studio_admin_item_preview_status', 10, 3);
add_action('woocommerce_after_order_itemmeta', 'sanro_studio_admin_item_dtf_status', 20, 3);
add_action('woocommerce_after_order_itemmeta', 'sanro_studio_admin_item_dtf_readiness', 23, 3);
add_action('woocommerce_after_order_itemmeta', 'sanro_studio_admin_item_generate_dtf_action', 25, 3);
add_action('admin_footer', 'sanro_studio_dtf_admin_generate_footer_forms');
add_action('admin_post_sanro_studio_generate_dtf_candidates', 'sanro_studio_admin_generate_dtf_candidates_post');
add_action('woocommerce_payment_complete', 'sanro_studio_schedule_drive_production', 20, 1);
add_action('woocommerce_order_status_changed', 'sanro_studio_schedule_drive_production', 20, 1);
add_action('sanro_studio_production_ready_marked', 'sanro_studio_schedule_drive_production', 10, 1);
add_action('sanro_studio_drive_provision_order', 'sanro_studio_run_drive_production', 10, 2);
add_action('sanro_studio_drive_watchdog','sanro_studio_drive_job_watchdog',10,4);

// These registrations are absent at the default-OFF setting. A staging
// environment must explicitly opt in before the buyer editor can run.
if (defined('SANRO_STUDIO_ENABLE_BUYER_EDITOR')
    && SANRO_STUDIO_ENABLE_BUYER_EDITOR === true) {
    add_action('woocommerce_single_product_summary', 'sanro_studio_product_editor_link', 35);
    add_action('template_redirect', 'sanro_studio_render_buyer_editor', 20);
}

// Keep the signed cart and checkout bridge disconnected until every staging
// gate is explicitly enabled. The source callbacks revalidate authorization.
if (defined('SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK')
    && SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK === true) {
    if (defined('SANRO_STUDIO_ENABLE_STAGING_CART')
        && SANRO_STUDIO_ENABLE_STAGING_CART === true
        && defined('SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT')
        && SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT === true) {
        add_action('woocommerce_before_calculate_totals',
            'sanro_studio_stage_reprice_signed_cart', 999, 1);
        add_filter('woocommerce_get_item_data',
            'sanro_studio_stage_cart_item_display', 20, 2);
    }
    add_action('wp_enqueue_scripts', 'sanro_studio_stage_enqueue_checkout_authorization', 30);
    add_action('woocommerce_review_order_before_submit',
        'sanro_studio_stage_checkout_credential_field', 5);
    add_filter('sanro_studio_staging_checkout_owner_token',
        'sanro_studio_stage_checkout_post_token', 10, 3);
    add_action('woocommerce_checkout_process', 'sanro_studio_stage_checkout_process', 5);
    add_action('woocommerce_checkout_create_order_line_item',
        'sanro_studio_stage_checkout_create_order_line_item', 10, 4);
    add_action('woocommerce_checkout_create_order',
        'sanro_studio_stage_checkout_verify_order_batch', 10, 2);
    if (sanro_studio_stage_checkout_batch_lock_enabled()) {
        add_action('woocommerce_checkout_order_created',
            'sanro_studio_stage_checkout_lock_created_order', 10, 1);
    }
}

add_action('rest_api_init','sanro_studio_register_readiness_route');
add_filter('rest_post_dispatch','sanro_studio_rest_source_header',10,3);

add_action('rest_api_init','sanro_studio_repeat_register_routes');
if(sanro_studio_pricing_wc_enabled())add_action('woocommerce_checkout_update_order_review','sanro_studio_repeat_billing_context',5,1);

add_action('admin_menu', 'sanro_studio_storage_admin_menu');

require_once __DIR__ . '/includes/design-collection.php';
add_action('rest_api_init', 'sanro_studio_collection_routes');

require_once __DIR__ . '/includes/background-removal.php';
add_action('rest_api_init', 'sanro_studio_background_routes');

add_action('woocommerce_product_options_general_product_data', 'sanro_studio_mockup_matrix_fields');
add_action('woocommerce_admin_process_product_object', 'sanro_studio_mockup_matrix_save');
add_action('rest_api_init', 'sanro_studio_mockup_variant_routes');

// Explicit administrator OAuth setup; registers no writer and defines no enable flags.
require_once __DIR__ . '/includes/drive-oauth.php';
sanro_studio_register_drive_oauth();
