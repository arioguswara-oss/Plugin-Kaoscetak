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
require_once __DIR__ . '/includes/core.php';
require_once __DIR__ . '/includes/print-area.php';
require_once __DIR__ . '/includes/product-area.php';
require_once __DIR__ . '/includes/product-pricing.php';
require_once __DIR__ . '/includes/editor-config.php';
require_once __DIR__ . '/includes/buyer.php';
require_once __DIR__ . '/includes/pricing.php';
require_once __DIR__ . '/includes/quote.php';
require_once __DIR__ . '/includes/draft.php';
require_once __DIR__ . '/includes/storage-policy.php';
require_once __DIR__ . '/includes/order-preview.php';
require_once __DIR__ . '/includes/draft-rest.php';
require_once __DIR__ . '/includes/runtime.php';
require_once __DIR__ . '/includes/admin.php';

// Native SANRO Studio is the default. The legacy compatibility bootstrap is
// explicitly opt-in for isolated development only; it never runs by default.
if (defined('SANRO_STUDIO_ENABLE_LEGACY_COMPAT') && SANRO_STUDIO_ENABLE_LEGACY_COMPAT === true) {
    add_filter('lumise_settings', 'sanro_studio_filter_settings', 100);
    add_action('plugins_loaded', 'sanro_studio_boot_runtime', 20);
}
add_action('admin_menu', 'sanro_studio_admin_menu');
add_action('admin_post_sanro_studio_save', 'sanro_studio_save_settings');

add_action('woocommerce_product_options_general_product_data', 'sanro_studio_product_area_fields');
add_action('woocommerce_admin_process_product_object', 'sanro_studio_save_product_areas');

add_action('woocommerce_product_options_general_product_data', 'sanro_studio_product_pricing_fields');
add_action('woocommerce_admin_process_product_object', 'sanro_studio_save_product_pricing');

// Public read-only configuration only. No draft, upload or checkout endpoints.
add_action('rest_api_init', 'sanro_studio_register_editor_config_route');
// Staging opt-in only; no production draft route by default.
add_action('rest_api_init', 'sanro_studio_register_draft_routes');

// Disabled by default; staging must explicitly define SANRO_STUDIO_ENABLE_BUYER_EDITOR.
add_action('woocommerce_single_product_summary', 'sanro_studio_product_editor_link', 35);
add_action('template_redirect', 'sanro_studio_render_buyer_editor', 20);

// Admin-only, read-only order preview status; no public file delivery.
add_action('woocommerce_admin_order_data_after_order_details', 'sanro_studio_admin_order_preview_status');
