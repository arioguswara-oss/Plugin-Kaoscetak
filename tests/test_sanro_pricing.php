<?php
define('ABSPATH', __DIR__ . '/');
require __DIR__ . '/../plugins/sanro-studio/includes/pricing.php';
$checks = 0;
function ss_price_expect($expected, $actual, $label) {
    global $checks;
    if ($expected !== $actual) throw new RuntimeException('FAIL ' . $label . ': expected ' . var_export($expected, true) . ' got ' . var_export($actual, true));
    $checks++;
}
$table = sanro_studio_24s_price_table();
$cats = ['A5', 'A4', 'A3', 'A3+'];
foreach ($table as $size => $prices) {
    foreach ($cats as $i => $cat) {
        ss_price_expect($prices[$i], sanro_studio_24s_unit_price($size, $cat, null)['unit_price'], 'front ' . $size . ' ' . $cat);
        ss_price_expect($prices[$i], sanro_studio_24s_unit_price($size, null, $cat)['unit_price'], 'back ' . $size . ' ' . $cat);
        ss_price_expect($prices[$i] + 10000, sanro_studio_24s_unit_price($size, $cat, null, true)['unit_price'], 'long sleeve ' . $size . ' ' . $cat);
    }
}
$extras = [15000, 25000, 35000, 40000];
foreach ($table as $size => $prices) {
    foreach ($cats as $i => $front) {
        foreach ($cats as $j => $back) {
            $expected = $prices[max($i, $j)] + $extras[min($i, $j)];
            ss_price_expect($expected, sanro_studio_24s_unit_price($size, $front, $back)['unit_price'], 'two sides ' . $size . ' ' . $front . '/' . $back);
            ss_price_expect($expected, sanro_studio_24s_unit_price($size, $back, $front)['unit_price'], 'reverse order ' . $size . ' ' . $back . '/' . $front);
        }
    }
}
ss_price_expect('empty_design', sanro_studio_24s_unit_price('M', null, null)['error'], 'empty');
ss_price_expect('invalid_variant', sanro_studio_24s_unit_price('6XL', 'A4', null)['error'], 'unknown size');
ss_price_expect('invalid_variant', sanro_studio_24s_unit_price('M', 'A4', null, 'yes')['error'], 'invalid sleeve flag');
ss_price_expect('invalid_category', sanro_studio_24s_unit_price('M', 'A2', null)['error'], 'invalid category');
echo "SANRO 24s price matrix: {$checks} assertions PASS, 0 FAIL\n";
