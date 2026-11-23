<?php
/**
 * Plugin Name: SANRO Studio Staging DTF Admin Display Gate
 * Description: SANRO Studio DTF status panel, only at the pinned Hostinger staging root.
 * Version: 0.1.0
 *
 * No raster, private artifact writer, order batch, Google Drive, checkout,
 * payment, buyer upload, WooCommerce order mutation or REST endpoint enabled.
 * Do not copy this must-use plugin to kaoscetak.id production.
 */
defined('ABSPATH') || exit;

$sanro_studio_stage_host = 'saddlebrown-peafowl-616656.hostingersite.com';
$sanro_studio_stage_root =
    '/home/u606139982/domains/saddlebrown-peafowl-616656.hostingersite.com/public_html';
$sanro_studio_stage_live_root = realpath(ABSPATH);
$sanro_studio_stage_current_host =
    function_exists('home_url') ? wp_parse_url(home_url(), PHP_URL_HOST) : null;

if (is_string($sanro_studio_stage_live_root)
    && $sanro_studio_stage_live_root === $sanro_studio_stage_root
    && $sanro_studio_stage_current_host === $sanro_studio_stage_host
    && !defined('SANRO_STUDIO_ENABLE_DTF_ADMIN_STATUS')) {
    define('SANRO_STUDIO_ENABLE_DTF_ADMIN_STATUS', true);
}

unset(
    $sanro_studio_stage_host,
    $sanro_studio_stage_root,
    $sanro_studio_stage_live_root,
    $sanro_studio_stage_current_host
);
