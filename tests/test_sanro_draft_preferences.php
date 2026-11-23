<?php
define('ABSPATH', __DIR__ . '/');
function absint($value) { return abs((int) $value); }
function get_post_meta($id, $key, $single) {
    return ['front' => ['width' => 32, 'height' => 48], 'back' => ['width' => 32, 'height' => 48]];
}
require __DIR__ . '/../plugins/sanro-studio/includes/print-area.php';
require __DIR__ . '/../plugins/sanro-studio/includes/product-area.php';
function assert_preference($ok, $label) {
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
}
$design = [
    'front' => [
        'category' => 'A5',
        'objects' => [['type'=>'text','x'=>1,'y'=>1,'width'=>8,'height'=>4]],
        'text' => 'Halo', 'font_size' => 22,
    ],
    'shirt_color' => '#171717',
    'quantities' => ['S'=>2, 'XL'=>3],
];
assert_preference(sanro_studio_validate_product_design(17, $design)['valid'], 'valid preferences');
$cases = [
    ['shirt_color', '#123456'],
    ['quantities', ['S'=>-1]],
    ['quantities', ['S'=>1000]],
    ['quantities', ['S'=>'2']],
    ['quantities', ['S'=>900, 'M'=>900, 'L'=>900, 'XL'=>900, '2XL'=>900, '3XL'=>900]],
    ['quantities', ['INVALID'=>1]],
];
foreach ($cases as [$key, $value]) {
    $bad = $design; $bad[$key] = $value;
    assert_preference(!sanro_studio_validate_product_design(17, $bad)['valid'], 'reject ' . $key);
}
echo "SANRO draft preferences: 7 assertions PASS, 0 FAIL\n";
