<?php
// Synthetic WordPress/WooCommerce boundaries; never writes data or calls a site.
define('ABSPATH', __DIR__ . '/');
$GLOBALS['currency'] = 'IDR';
$GLOBALS['profile'] = 'cotton_combed_24s';
$GLOBALS['published'] = true;
$GLOBALS['purchasable'] = true;
$GLOBALS['areas'] = ['front' => ['width' => 32, 'height' => 48], 'back' => ['width' => 32, 'height' => 48]];
function absint($id) { return abs((int) $id); }
function get_woocommerce_currency() { return $GLOBALS['currency']; }
function get_post_meta($id, $key, $single) {
    if ($key === '_sanro_studio_pricing_profile') return $GLOBALS['profile'];
    if ($key === '_sanro_studio_product_sleeve') return $id === 8 ? 'long' : '';
    if ($key === '_sanro_studio_price_table') return $id === 8 ? ($GLOBALS['long_table'] ?? '') : '';
    if ($key === '_sanro_studio_second_side_prices') return $GLOBALS['custom_sides'] ?? '';
    if ($key === '_sanro_studio_safe_areas') return $GLOBALS['areas'];
    return '';
}
function wc_get_product($id) {
    return in_array($id,[7,8],true) ? new class {
        public function get_status() { return $GLOBALS['published'] ? 'publish' : 'draft'; }
        public function is_purchasable() { return $GLOBALS['purchasable']; }
    } : false;
}
foreach (['print-area', 'product-area', 'editor-config', 'pricing', 'pricing-profiles', 'pricing-quote', 'quote'] as $file) {
    require dirname(__DIR__) . '/plugins/sanro-studio/includes/' . $file . '.php';
}
$checks = 0;
function check_quote($condition, $label) {
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
}
function side($category, $width = null, $height = null) {
    [$limit_w,$limit_h] = sanro_studio_print_categories()[$category];
    $width = $width ?? $limit_w;
    $height = $height ?? $limit_h;
    return ['category' => $category, 'objects' => [['type' => 'image', 'x' => 0, 'y' => 0, 'width' => $width, 'height' => $height]]];
}
function design() { return ['front' => side('A3'), 'back' => side('A5'), 'quantities' => ['S' => 2, 'L' => 1]]; }
$official = [
    'S' => [62000,72000,82000,87000], 'M' => [65000,75000,85000,90000],
    'L' => [68000,78000,88000,93000], 'XL' => [72000,82000,92000,97000],
    '2XL' => [77000,87000,97000,102000], '3XL' => [80000,90000,100000,105000],
    '4XL' => [83000,93000,103000,108000], '5XL' => [85000,95000,105000,110000],
];
$categories = ['A5','A4','A3','A3+'];
$extras = [15000,25000,35000,40000];
foreach ($official as $size => $prices) {
    foreach ($categories as $i => $front) {
        foreach ([false, true] as $long) {
            foreach ([null, 'A5','A4','A3','A3+'] as $back) {
                $d = ['front' => side($front), 'quantities' => [$size => 1], 'long_sleeve' => $long];
                if ($back !== null) $d['back'] = side($back);
                $j = $back === null ? $i : array_search($back, $categories, true);
                $expected = $prices[max($i,$j)] + ($back === null ? 0 : $extras[min($i,$j)]);
                $q = sanro_studio_product_quote(7, $d);
                if ($long) {
                    check_quote(!$q['valid'] && $q['error'] === 'select_long_sleeve_product', 'legacy flag cannot switch product');
                    continue;
                }
                check_quote($q['valid'] && $q['total'] === $expected, 'official full price matrix');
                $swap = $d; unset($swap['front'], $swap['back']);
                $swap['back'] = side($front);
                if ($back !== null) $swap['front'] = side($back);
                check_quote(sanro_studio_product_quote(7, $swap)['total'] === $expected, 'front/back symmetry');
            }
        }
    }
}
check_quote(sanro_studio_product_quote(7, design())['total'] === 297000, 'multi-size quantity totals');
$GLOBALS['custom_sides'] = [12500,25000,35000,40000];
check_quote(sanro_studio_product_quote(7,design())['total'] === 289500, 'shop second-side rate uses the computed smaller tier');
unset($GLOBALS['custom_sides']);
$q = sanro_studio_product_quote(7, ['front' => side('A5'), 'quantities' => ['S' => 1], 'long_sleeve' => true]);
check_quote($q['error'] === 'select_long_sleeve_product', 'legacy long sleeve requires the separate product');
check_quote(!sanro_studio_product_quote(8,design())['valid'], 'long sleeve product requires its own table');
$custom = $official;
$custom['S'][2] = 85750;
$GLOBALS['long_table'] = ['profile'=>'cotton_combed_24s','table'=>$custom];
$q = sanro_studio_product_quote(8,design());
check_quote($q['valid'] && $q['sleeve_label'] === 'long' && $q['total'] === 304500, 'separate price table without surcharge');
foreach ($categories as $category) {
    [$w,$h] = sanro_studio_print_categories()[$category];
    $d = ['front' => side($category, $w, $h), 'quantities' => ['S' => 1]];
    check_quote(sanro_studio_product_quote(7, $d)['valid'], 'exact category boundary');
    $d['front']['objects'][0]['width'] += 0.01;
    $result = sanro_studio_product_quote(7,$d);
    $next = $categories[array_search($category,$categories,true)+1] ?? null;
    check_quote($next === null ? !$result['valid'] : $result['categories']['front'] === $next, 'size beyond boundary advances tier or rejects');
}
$d = design(); $d['front']['objects'][] = ['type'=>'text','x'=>25,'y'=>0,'width'=>5,'height'=>5];
check_quote(sanro_studio_product_quote(7,$d)['categories']['front'] === 'A3+', 'combined image/text bounding box advances tier');
$d = design(); $d['back'] = ['category'=>'A3+', 'objects'=>[]];
check_quote(sanro_studio_product_quote(7, $d)['total'] === 252000, 'empty side not charged');
foreach ([[], ['S'=>0], ['S'=>-1], ['S'=>1.5], ['S'=>'1'], ['S'=>1000], ['XXL'=>1], null,
    ['S'=>999,'M'=>999,'L'=>999,'XL'=>999,'2XL'=>999,'3XL'=>999]] as $quantities) {
    $d = design(); $d['quantities'] = $quantities;
    check_quote(!sanro_studio_product_quote(7, $d)['valid'], 'invalid quantity rejected');
}
foreach (['unit_price'=>1, 'total'=>1, 'safe_areas'=>[], 'repeat_verified'=>true, 'pricing_profile'=>'hoodie'] as $key=>$value) {
    $d = design(); $d[$key] = $value;
    check_quote(!sanro_studio_product_quote(7, $d)['valid'], 'client authority rejected');
}
foreach ([null, 1, 'true'] as $value) {
    $d = design(); $d['long_sleeve'] = $value;
    check_quote(!sanro_studio_product_quote(7, $d)['valid'], 'strict sleeve boolean');
}
$d = design(); $d['front']['category'] = 'A2';
check_quote(sanro_studio_product_quote(7,$d)['categories']['front'] === 'A3','claimed category never changes price');
$d = design(); $d['front']['objects'][0]['width'] = INF;
check_quote(!sanro_studio_product_quote(7, $d)['valid'], 'non-finite geometry');
$GLOBALS['areas']['front']['width'] = 5;
check_quote(!sanro_studio_product_quote(7, design())['valid'], 'admin safe area enforced');
$GLOBALS['areas']['front']['width'] = 32;
foreach ([-7, '7', 0, [], 99] as $id) check_quote(!sanro_studio_product_quote($id, design())['valid'], 'product ID validation');
$GLOBALS['profile'] = 'hoodie';
check_quote(!sanro_studio_product_quote(7, design())['valid'], 'unknown profile fails closed');
$GLOBALS['profile'] = 'cotton_combed_24s';
$GLOBALS['published'] = false;
check_quote(!sanro_studio_product_quote(7, design())['valid'], 'unpublished product');
$GLOBALS['published'] = true; $GLOBALS['purchasable'] = false;
check_quote(!sanro_studio_product_quote(7, design())['valid'], 'unavailable product');
$GLOBALS['purchasable'] = true; $GLOBALS['currency'] = 'USD';
check_quote(!sanro_studio_product_quote(7, design())['valid'], 'no implicit currency conversion');
$GLOBALS['currency'] = 'IDR';
$line = sanro_studio_validate_priced_cart_line(7, design(), 'L', 1);
check_quote($line['valid'] && $line['unit_price'] === 103000 && $line['subtotal'] === 103000, 'recomputed Woo line');
$integrated = sanro_studio_quote_size_quantities(7, design(), design()['quantities']);
check_quote($integrated['valid'] && $integrated['currency'] === 'IDR'
    && $integrated['items']['L']['unit_price'] === 103000
    && $integrated['total_quantity'] === 3 && $integrated['total_price'] === 297000,
    'draft/cart quote delegates to authoritative product quote');
$GLOBALS['currency'] = 'USD';
check_quote(!sanro_studio_quote_size_quantities(7, design(), design()['quantities'])['valid'],
    'draft/cart quote rejects unsupported currency');
$GLOBALS['currency'] = 'IDR';
foreach ([['L',2],['S',1],['XL',1],['L','1'],['L',0],['L',1.5]] as $args) {
    check_quote(!sanro_studio_validate_priced_cart_line(7, design(), $args[0], $args[1])['valid'], 'cart mismatch rejection');
}
echo "PASS: {$checks} server pricing quote assertions\n";
