<?php
/** Default OFF: no WordPress, GD, fonts, WooCommerce or Drive. */
define('ABSPATH', __DIR__ . '/');
require dirname(__DIR__) . '/includes/dtf-layer-render.php';
if (sanro_studio_dtf_layer_enabled()) {
    fwrite(STDERR, "FAIL: layered raster enabled by default\n");
    exit(1);
}
$plan = sanro_studio_dtf_layer_plan(null, 0, 'front');
$render = sanro_studio_dtf_render_layer_side(null, 0, 'front');
if (($plan['error'] ?? null) !== 'layer_render_disabled'
    || ($render['error'] ?? null) !== 'layer_render_disabled'
    || isset($render['bytes'])) {
    fwrite(STDERR, "FAIL: disabled layered renderer did not fail closed\n");
    exit(1);
}
echo "PASS: SANRO layered DTF raster defaults disabled\n";
