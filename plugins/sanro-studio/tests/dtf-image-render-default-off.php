<?php
/** Default deny: no WordPress, WooCommerce, image files or GD required. */
define('ABSPATH', __DIR__ . '/');
require dirname(__DIR__) . '/includes/dtf-image-render.php';
if (sanro_studio_dtf_image_render_enabled()) {
    fwrite(STDERR, "FAIL: DTF image renderer enabled by default\n");
    exit(1);
}
$result = sanro_studio_dtf_render_image_side(null, 0, 'front');
if (!is_array($result) || ($result['error'] ?? null) !== 'dtf_render_disabled'
    || isset($result['bytes'])) {
    fwrite(STDERR, "FAIL: inactive renderer did not fail closed\n");
    exit(1);
}
echo "PASS: SANRO DTF RGBA image raster defaults disabled\n";
