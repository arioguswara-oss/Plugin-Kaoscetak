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
require_once __DIR__ . '/includes/runtime.php';
require_once __DIR__ . '/includes/admin.php';

// Register before Lumise initializes its core at init priority 4, regardless of plugin load order.
add_filter('lumise_settings', 'sanro_studio_filter_settings', 100);
add_action('plugins_loaded', 'sanro_studio_boot_runtime', 20);
add_action('admin_menu', 'sanro_studio_admin_menu');
add_action('admin_post_sanro_studio_save', 'sanro_studio_save_settings');

add_action('woocommerce_product_options_general_product_data', 'sanro_studio_product_area_fields');
add_action('woocommerce_admin_process_product_object', 'sanro_studio_save_product_areas');
