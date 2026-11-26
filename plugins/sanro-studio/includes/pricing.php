<?php
defined('ABSPATH') || exit;

/**
 * Cotton Combed 24s pricing in IDR, one printed side included.
 * Only this explicitly approved product profile is defined here.
 * Other products must provide their own server-owned price table.
 */
function sanro_studio_24s_price_table() {
    return [
        'S' => [62000, 72000, 82000, 87000],
        'M' => [65000, 75000, 85000, 90000],
        'L' => [68000, 78000, 88000, 93000],
        'XL' => [72000, 82000, 92000, 97000],
        '2XL' => [77000, 87000, 97000, 102000],
        '3XL' => [80000, 90000, 100000, 105000],
        '4XL' => [83000, 93000, 103000, 108000],
        '5XL' => [85000, 95000, 105000, 110000],
    ];
}

/**
 * Calculate one item from validated, nonempty print sides.
 * The price table and extras are server-controlled, never supplied by buyers.
 */
function sanro_studio_24s_unit_price($size, $front_category, $back_category, $long_sleeve = false) {
    $categories = ['A5', 'A4', 'A3', 'A3+'];
    $extras = [15000, 25000, 35000, 40000];
    $table = sanro_studio_24s_price_table();
    if (!is_string($size) || !isset($table[$size]) || !is_bool($long_sleeve)) {
        return ['valid' => false, 'error' => 'invalid_variant'];
    }
    $active = [];
    foreach ([$front_category, $back_category] as $category) {
        if ($category === null) continue;
        if (!is_string($category)) return ['valid' => false, 'error' => 'invalid_category'];
        $index = array_search($category, $categories, true);
        if ($index === false) return ['valid' => false, 'error' => 'invalid_category'];
        $active[] = $index;
    }
    if (!$active) return ['valid' => false, 'error' => 'empty_design'];
    rsort($active, SORT_NUMERIC);
    $price = $table[$size][$active[0]];
    if (count($active) === 2) $price += $extras[$active[1]];
    return ['valid' => true, 'unit_price' => $price, 'currency' => 'IDR'];
}
