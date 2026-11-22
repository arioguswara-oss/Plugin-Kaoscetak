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
add_action('admin_menu', 'sanro_studio_bootstrap_admin_menu');
