<?php
define('ABSPATH', __DIR__ . '/');
$GLOBALS['fixture_meta'] = [
    17 => [
        '_sanro_studio_pricing_profile' => 'cotton_combed_24s',
        '_sanro_studio_safe_areas' => [
            'front' => ['width' => 32, 'height' => 48],
            'back' => ['width' => 32, 'height' => 48],
        ],
    ],
];
function absint($n) { return abs((int) $n); }
function get_post_meta($id, $key, $single) { return $GLOBALS['fixture_meta'][$id][$key] ?? null; }
foreach (['print-area', 'product-area', 'pricing', 'quote'] as $file) {
    require __DIR__ . '/../plugins/sanro-studio/includes/' . $file . '.php';
}
$checks = 0;
function check_quote($expected, $actual, $label) {
    global $checks;
    if ($expected !== $actual) throw new RuntimeException('FAIL ' . $label . ': ' . var_export($actual, true));
    $checks++;
}
$img = ['type' => 'image', 'x' => 1, 'y' => 1, 'width' => 10, 'height' => 10];
$front = ['category' => 'A4', 'objects' => [$img]];
$back = ['category' => 'A5', 'objects' => [$img]];
check_quote(90000, sanro_studio_quote_product_design(17, ['front' => $front, 'back' => $back], 'M')['unit_price'], 'two sides M');
check_quote(90000, sanro_studio_quote_product_design(17, ['front' => $back, 'back' => $front], 'M')['unit_price'], 'symmetric');
check_quote(75000, sanro_studio_quote_product_design(17, ['front' => $front], 'M')['unit_price'], 'front only');
check_quote(75000, sanro_studio_quote_product_design(17, ['back' => $front], 'M')['unit_price'], 'back only');
check_quote(100000, sanro_studio_quote_product_design(17, ['front' => $front, 'back' => $back], 'M', true)['unit_price'], 'long sleeve');
check_quote('pricing_profile_unconfigured', sanro_studio_quote_product_design(18, ['front' => $front], 'M')['error'], 'unknown profile rejected');
check_quote('invalid_design', sanro_studio_quote_product_design(17, ['front' => $front + ['price' => 1]], 'M')['error'], 'injected price rejected');
check_quote('category_exceeded', sanro_studio_quote_product_design(17, ['front' => ['category' => 'A5', 'objects' => [['type' => 'image', 'x' => 0, 'y' => 0, 'width' => 20, 'height' => 20]]]], 'M')['error'], 'oversize rejected');
check_quote('invalid_variant', sanro_studio_quote_product_design(17, ['front' => $front], '6XL')['error'], 'unknown size rejected');
check_quote('empty_design', sanro_studio_quote_product_design(17, ['front' => ['category' => 'A4', 'objects' => []]], 'M')['error'], 'empty rejected');
check_quote('invalid_product', sanro_studio_quote_product_design(0, ['front' => $front], 'M')['error'], 'invalid product');
check_quote(17, sanro_studio_quote_product_design(17, ['front' => $front], 'M')['product_id'], 'product id');
$multi = sanro_studio_quote_size_quantities(17, ['front' => $front, 'back' => $back], ['S' => 2, 'M' => 3]);
check_quote(true, $multi['valid'], 'multi-size valid');
check_quote(5, $multi['total_quantity'], 'multi-size total pieces');
check_quote(2 * (72000 + 15000) + 3 * (75000 + 15000), $multi['total_price'], 'multi-size trusted total');
check_quote('invalid_quantities', sanro_studio_quote_size_quantities(17, ['front' => $front], ['M' => 0])['error'], 'zero qty rejected');
check_quote('invalid_quantities', sanro_studio_quote_size_quantities(17, ['front' => $front], ['M' => '2'])['error'], 'string qty rejected');
check_quote('invalid_quantities', sanro_studio_quote_size_quantities(17, ['front' => $front], ['M' => 1001])['error'], 'oversized qty rejected');
check_quote('invalid_quantities', sanro_studio_quote_size_quantities(17, ['front' => $front], ['S' => 1000, 'M' => 1000, 'L' => 1000, 'XL' => 1000, '2XL' => 1000, '3XL' => 1])['error'], 'total quantity cap');
check_quote('invalid_quantities', sanro_studio_quote_size_quantities(17, ['front' => $front], ['6XL' => 1])['error'], 'unknown size qty');
check_quote('invalid_quantities', sanro_studio_quote_size_quantities(17, ['front' => $front], [])['error'], 'empty qty');
check_quote('invalid_design', sanro_studio_quote_size_quantities(17, ['front' => $front + ['price' => 1]], ['M' => 2])['error'], 'injected design price rejected');
check_quote('pricing_profile_unconfigured', sanro_studio_quote_size_quantities(18, ['front' => $front], ['M' => 2])['error'], 'unconfigured product rejected');
check_quote(85000 * 2, sanro_studio_quote_size_quantities(17, ['front' => $front], ['M' => 2], true)['total_price'], 'long sleeve totals');
echo "SANRO quote integration: {$checks} assertions PASS, 0 FAIL\n";
