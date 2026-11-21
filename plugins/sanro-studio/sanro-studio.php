<?php
/**
 * Plugin Name: SANRO Studio
 * Description: Fondasi editor SANRO Studio untuk Kaoscetak. Tahap pengembangan, pratinjau admin saja.
 * Version: 0.1.1-dev
 * Author: SANRO
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * Text Domain: sanro-studio
 * License: GPL-2.0-or-later
 */
defined('ABSPATH') || exit;

define('SANRO_STUDIO_VERSION', '0.1.1-dev');
require_once __DIR__ . '/includes/private-master-setup.php';
// Activation provisions a private sibling directory when the hosting layout permits.
// Existing sites that update the plugin receive the same safe setup on init,
// but upload remains disabled unless BOTH buyer + draft gates are explicitly on.
register_activation_hook(__FILE__, 'sanro_studio_provision_private_master_storage');
add_action('init', 'sanro_studio_initialize_auto_private_master_storage', 0);
require_once __DIR__ . '/includes/core.php';
require_once __DIR__ . '/includes/print-area.php';
require_once __DIR__ . '/includes/product-area.php';
require_once __DIR__ . '/includes/product-print-zones.php';
require_once __DIR__ . '/includes/product-pricing.php';
require_once __DIR__ . '/includes/product-mockups.php';
require_once __DIR__ . '/includes/editor-config.php';
require_once __DIR__ . '/includes/buyer.php';
require_once __DIR__ . '/includes/pricing.php';
require_once __DIR__ . '/includes/quote.php';
require_once __DIR__ . '/includes/cart-contract.php';
require_once __DIR__ . '/includes/cart-adapter.php';
require_once __DIR__ . '/includes/checkout-bridge.php';
require_once __DIR__ . '/includes/draft.php';
require_once __DIR__ . '/includes/storage-policy.php';
// Private master writer is inert until explicitly called by an authorized workflow.
require_once __DIR__ . '/includes/master-storage.php';
require_once __DIR__ . '/includes/order-preview.php';
require_once __DIR__ . '/includes/drive-production.php';
require_once __DIR__ . '/includes/drive-auto-provision.php';
require_once __DIR__ . '/includes/order-master-source.php';
require_once __DIR__ . '/includes/drive-master-export.php';
require_once __DIR__ . '/includes/production-ready-admin.php';
require_once __DIR__ . '/includes/draft-rest.php';
require_once __DIR__ . '/includes/admin.php';

// Native SANRO Studio is the default. The legacy compatibility bootstrap is
// explicitly opt-in for isolated development only; it never runs by default.
if (defined('SANRO_STUDIO_ENABLE_LEGACY_COMPAT') && SANRO_STUDIO_ENABLE_LEGACY_COMPAT === true) {
    require_once __DIR__ . '/includes/runtime.php';
    add_filter('lumise_settings', 'sanro_studio_filter_settings', 100);
    add_action('plugins_loaded', 'sanro_studio_boot_runtime', 20);
}
add_action('admin_menu', 'sanro_studio_admin_menu');
add_action('admin_post_sanro_studio_save', 'sanro_studio_save_settings');

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

// Public read-only configuration only. No draft, upload or checkout endpoints.
add_action('rest_api_init', 'sanro_studio_register_editor_config_route');
// Staging opt-in only; no production draft route by default.
add_action('rest_api_init', 'sanro_studio_register_draft_routes');
// Separate staging-only preview upload opt-in; disabled by default.
add_action('rest_api_init', 'sanro_studio_register_preview_upload_route');
add_action('rest_api_init', 'sanro_studio_register_master_upload_route');

// Disabled by default; staging must explicitly define SANRO_STUDIO_ENABLE_BUYER_EDITOR.
add_action('woocommerce_single_product_summary', 'sanro_studio_product_editor_link', 35);
add_action('template_redirect', 'sanro_studio_render_buyer_editor', 20);

// Admin-only, read-only order preview status; no public file delivery.
add_action('woocommerce_admin_order_data_after_order_details', 'sanro_studio_admin_order_preview_status');
add_action('woocommerce_admin_order_data_after_order_details', 'sanro_studio_admin_production_status', 15);
add_action('woocommerce_admin_order_data_after_order_details',
    'sanro_studio_admin_production_ready_action', 20);
// Only authenticated wp-admin POST; never register a nopriv action.
add_action('admin_post_sanro_studio_mark_production_ready',
    'sanro_studio_admin_mark_production_ready');
add_action('admin_footer', 'sanro_studio_admin_production_ready_footer_form');

// Private, opt-in Drive provisioning. All callbacks validate eligibility again.
// Jobs run asynchronously through Action Scheduler (or WP-Cron fallback).
add_action('woocommerce_payment_complete', 'sanro_studio_schedule_drive_production', 20, 1);
add_action('woocommerce_order_status_changed', 'sanro_studio_schedule_drive_production', 20, 1);
add_action('sanro_studio_production_ready_marked', 'sanro_studio_schedule_drive_production', 10, 1);
add_action('sanro_studio_drive_provision_order', 'sanro_studio_run_drive_production', 10, 1);

// Authenticated admin-post route; never register a nopriv counterpart.
add_action('admin_post_sanro_studio_order_preview', 'sanro_studio_admin_deliver_order_preview');

// Per-line-item admin previews; no buyer-facing file URL.
add_action('woocommerce_after_order_itemmeta', 'sanro_studio_admin_item_preview_status', 10, 3);

 // Staging checkout validation is inert unless explicitly enabled.
if (defined('SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK')
    && SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK === true) {
    // Cart session restoration must never silently replace the signed Studio
    // unit quote with WooCommerce's ordinary product catalog price.
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
}
