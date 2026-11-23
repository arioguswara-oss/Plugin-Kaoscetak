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
require_once __DIR__ . '/includes/print-area.php';
require_once __DIR__ . '/includes/product-area.php';
require_once __DIR__ . '/includes/product-pricing.php';
require_once __DIR__ . '/includes/editor-config.php';
require_once __DIR__ . '/includes/pricing.php';
require_once __DIR__ . '/includes/buyer.php';
add_action('admin_menu', 'sanro_studio_bootstrap_admin_menu');

// Product configuration is editable only through WooCommerce product admin.
add_action('woocommerce_product_options_general_product_data', 'sanro_studio_product_area_fields');
add_action('woocommerce_product_options_general_product_data', 'sanro_studio_product_readiness_notice', 30);
add_action('woocommerce_admin_process_product_object', 'sanro_studio_save_product_areas');
// Pricing profile opt-in: without it sanro_studio_public_editor_config() rejects every product.
add_action('woocommerce_product_options_general_product_data', 'sanro_studio_product_pricing_fields');
add_action('woocommerce_admin_process_product_object', 'sanro_studio_save_product_pricing');
add_action('rest_api_init', 'sanro_studio_register_editor_config_route');
