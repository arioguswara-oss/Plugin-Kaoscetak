<?php
/**
 * Plugin Name: SANRO Studio
 * Description: SANRO Studio isolated bootstrap. Read-only admin status only.
 * Version: 0.1.0-dev
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Text Domain: sanro-studio
 * License: GPL-2.0-or-later
 */
defined('ABSPATH') || exit;

if (!defined('SANRO_STUDIO_VERSION')) {
    define('SANRO_STUDIO_VERSION', '0.1.0-dev');
}

if (!function_exists('sanro_studio_bootstrap_status')) {
    function sanro_studio_bootstrap_status() {
        return array(
            'version' => '0.1.0-dev',
            'editor' => 'not_integrated',
            'cart' => 'not_integrated',
            'checkout' => 'not_integrated',
            'production' => 'not_integrated',
        );
    }
}

if (!function_exists('sanro_studio_bootstrap_admin_page')) {
    function sanro_studio_bootstrap_admin_page() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions.', 'sanro-studio'));
        }
        echo '<div class="wrap"><h1>' . esc_html__('SANRO Studio — Integration Status', 'sanro-studio') . '</h1>';
        echo '<p>' . esc_html__('This bootstrap does not enable buyer editing, checkout, DTF rendering, or Drive exports.', 'sanro-studio') . '</p><table class="widefat striped"><tbody>';
        foreach (sanro_studio_bootstrap_status() as $name => $state) {
            echo '<tr><th scope="row">' . esc_html($name) . '</th><td>' . esc_html($state) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
}

if (!function_exists('sanro_studio_bootstrap_admin_menu')) {
    function sanro_studio_bootstrap_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('SANRO Studio', 'sanro-studio'),
            __('SANRO Studio', 'sanro-studio'),
            'manage_woocommerce',
            'sanro-studio-status',
            'sanro_studio_bootstrap_admin_page'
        );
    }
}
// Buyer template is loaded for integration, but frontend hooks stay disabled until
// product configuration and draft dependencies are present and tested.
require_once __DIR__ . '/includes/private-master-setup.php';
require_once __DIR__ . '/includes/core.php';
require_once __DIR__ . '/includes/print-area.php';
require_once __DIR__ . '/includes/product-area.php';
require_once __DIR__ . '/includes/product-print-zones.php';
require_once __DIR__ . '/includes/product-pricing.php';
require_once __DIR__ . '/includes/product-mockups.php';
require_once __DIR__ . '/includes/editor-config.php';
require_once __DIR__ . '/includes/pricing.php';
require_once __DIR__ . '/includes/buyer.php';
require_once __DIR__ . '/includes/quote.php';
require_once __DIR__ . '/includes/cart-contract.php';
require_once __DIR__ . '/includes/cart-adapter.php';
require_once __DIR__ . '/includes/checkout-bridge.php';
require_once __DIR__ . '/includes/checkout-batch-lock.php';
require_once __DIR__ . '/includes/draft.php';
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
require_once __DIR__ . '/includes/dtf-admin-order-status.php';
require_once __DIR__ . '/includes/dtf-admin-generate.php';
require_once __DIR__ . '/includes/dtf-admin-readiness.php';
require_once __DIR__ . '/includes/production-ready-admin.php';
require_once __DIR__ . '/includes/draft-rest.php';
require_once __DIR__ . '/includes/admin.php';
add_action('admin_menu', 'sanro_studio_bootstrap_admin_menu');

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

// Source modules keep their own opt-in guards. Buyer and staging checkout hooks
// are deliberately absent from this integration bootstrap.
add_action('rest_api_init', 'sanro_studio_register_draft_routes');
add_action('rest_api_init', 'sanro_studio_register_preview_upload_route');
add_action('rest_api_init', 'sanro_studio_register_master_upload_route');
add_action('woocommerce_admin_order_data_after_order_details', 'sanro_studio_admin_order_preview_status');
add_action('woocommerce_admin_order_data_after_order_details', 'sanro_studio_admin_production_status', 15);
add_action('woocommerce_admin_order_data_after_order_details', 'sanro_studio_admin_production_ready_action', 20);
add_action('admin_post_sanro_studio_mark_production_ready', 'sanro_studio_admin_mark_production_ready');
add_action('admin_footer', 'sanro_studio_admin_production_ready_footer_form');
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
add_action('sanro_studio_drive_provision_order', 'sanro_studio_run_drive_production', 10, 1);
