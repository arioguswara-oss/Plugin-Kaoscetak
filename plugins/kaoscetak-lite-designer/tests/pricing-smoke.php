<?php
/** Source-level pricing regression: php tests/pricing-smoke.php (no WordPress required). */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
$GLOBALS['kld_test_options'] = [];
function get_option($key, $default = false) { return $GLOBALS['kld_test_options'][$key] ?? $default; }
function get_post_meta($id, $key, $single = false) { return ''; }
function add_action(...$args) {}
function add_filter(...$args) {}
function kld_is_mug($id) { return false; }
function kld_product_preset_key($id) {
    return [1 => 'reguler-24s', 2 => 'lengan-panjang-24s', 3 => 'tunik-oversize34'][$id] ?? 'hoodie';
}
require dirname(__DIR__) . '/includes/admin.php';
$assertions = 0;
function expect_price($product, $size, $front, $back, $expected) {
    global $assertions;
    $actual = kld_unit_price($product, $size, $front, $back);
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL product=$product size=$size front=$front back=$back expected=" . var_export($expected, true) . " actual=" . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
    $assertions++;
}
$matrix = kld_price_presets_default()['reguler-24s']['matrix'];
$surcharges = ['A5'=>15000,'A4'=>25000,'A3'=>35000,'A3+'=>40000];
$tiers = array_keys($surcharges);
foreach ($matrix as $size => $prices) {
    foreach ($tiers as $tier) {
        expect_price(1, $size, $tier, 'none', $prices[$tier]);
        expect_price(1, $size, 'none', $tier, $prices[$tier]);
        expect_price(2, $size, $tier, 'none', $prices[$tier] + 10000);
        foreach ($tiers as $other) {
            $large = array_search($tier, $tiers, true) >= array_search($other, $tiers, true) ? $tier : $other;
            $small = $large === $tier ? $other : $tier;
            $expected = $prices[$large] + $surcharges[$small];
            expect_price(1, $size, $tier, $other, $expected);
            expect_price(1, $size, $other, $tier, $expected);
        }
    }
}
expect_price(1, 'L', 'A3+', 'A4', 118000);
expect_price(1, 'L', 'A4', 'A3+', 118000);
expect_price(1, 'L', 'none', 'none', null);
expect_price(1, 'INVALID', 'A5', 'none', null);
expect_price(1, 'L', 'UNKNOWN', 'none', null);
foreach (kld_price_presets_default()['tunik-oversize34']['matrix'] as $size => $prices) {
    foreach ($tiers as $tier) expect_price(3, $size, $tier, 'none', $prices[$tier]);
}
echo "PASS: $assertions source pricing assertions" . PHP_EOL;
