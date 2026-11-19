<?php
define('ABSPATH', __DIR__ . '/');
function absint($value) { return abs((int) $value); }
function get_post_meta($id, $key, $single) {
    return ['front' => ['width' => 32, 'height' => 48], 'back' => ['width' => 32, 'height' => 48]];
}
require __DIR__ . '/../plugins/sanro-studio/includes/print-area.php';
require __DIR__ . '/../plugins/sanro-studio/includes/product-area.php';
function check_font($condition, $message) {
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
}
$base = ['front' => [
    'category' => 'A5',
    'objects' => [['type' => 'text', 'x' => 1, 'y' => 1, 'width' => 8, 'height' => 4]],
    'text' => "Halo\nDunia",
    'font_size' => 22,
]];
check_font(sanro_studio_validate_product_design(17, $base)['valid'], 'valid multiline font');
foreach ([9, 49, 22.5, '22', null] as $invalid) {
    $design = $base;
    $design['front']['font_size'] = $invalid;
    check_font(!sanro_studio_validate_product_design(17, $design)['valid'], 'invalid font');
}
$design = $base;
$design['front']['text'] = "Halo\x00Dunia";
check_font(!sanro_studio_validate_product_design(17, $design)['valid'], 'control byte');
$design = $base;
$design['front']['font_size'] = 48;
check_font(sanro_studio_validate_product_design(17, $design)['valid'], 'maximum font');
echo "SANRO font metadata: 8 assertions PASS, 0 FAIL\n";
