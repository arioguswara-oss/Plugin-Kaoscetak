<?php
defined('ABSPATH') || exit;

/**
 * Pure server-side print bounds validator. Coordinates and sizes are in cm.
 * Does not trust the browser's claimed category or pricing.
 */
function sanro_studio_print_categories() {
    return [
        'A5' => [14.8, 21.0],
        'A4' => [21.0, 29.7],
        'A3' => [29.7, 42.0],
        'A3+' => [32.0, 48.0],
    ];
}

/**
 * $objects: up to one image and one text block per side.
 * Each object has type, x, y, width, height in centimeters.
 * $safe_area: width and height in centimeters, origin at top-left.
 * Returns an explicit validation result; never writes state.
 */
function sanro_studio_validate_print_side($objects, $category, $safe_area) {
    $categories = sanro_studio_print_categories();
    if (!is_string($category) || !isset($categories[$category])) {
        return ['valid' => false, 'error' => 'invalid_category'];
    }
    if (!is_array($objects) || !is_array($safe_area)
        || !isset($safe_area['width'], $safe_area['height'])
        || !is_numeric($safe_area['width']) || !is_numeric($safe_area['height'])
        || !is_finite((float) $safe_area['width']) || !is_finite((float) $safe_area['height'])
        || (float) $safe_area['width'] <= 0 || (float) $safe_area['height'] <= 0) {
        return ['valid' => false, 'error' => 'invalid_safe_area'];
    }
    if (count($objects) > 2) return ['valid' => false, 'error' => 'too_many_objects'];
    $seen = [];
    $min_x = $min_y = INF;
    $max_x = $max_y = -INF;
    foreach ($objects as $object) {
        if (!is_array($object) || !isset($object['type'])
            || !in_array($object['type'], ['image', 'text'], true)
            || isset($seen[$object['type']])) {
            return ['valid' => false, 'error' => 'invalid_object_type'];
        }
        $seen[$object['type']] = true;
        foreach (['x', 'y', 'width', 'height'] as $field) {
            if (!isset($object[$field]) || !is_numeric($object[$field])
                || !is_finite((float) $object[$field])) {
                return ['valid' => false, 'error' => 'invalid_geometry'];
            }
        }
        $x = (float) $object['x']; $y = (float) $object['y'];
        $w = (float) $object['width']; $h = (float) $object['height'];
        if ($w <= 0 || $h <= 0 || $x < 0 || $y < 0
            || $x + $w > (float) $safe_area['width'] + 0.000001
            || $y + $h > (float) $safe_area['height'] + 0.000001) {
            return ['valid' => false, 'error' => 'outside_safe_area'];
        }
        $min_x = min($min_x, $x); $min_y = min($min_y, $y);
        $max_x = max($max_x, $x + $w); $max_y = max($max_y, $y + $h);
    }
    if (!$objects) return ['valid' => true, 'empty' => true, 'span_cm' => [0, 0]];
    $span = [$max_x - $min_x, $max_y - $min_y];
    [$limit_w, $limit_h] = $categories[$category];
    if ($span[0] > $limit_w + 0.000001 || $span[1] > $limit_h + 0.000001) {
        return ['valid' => false, 'error' => 'category_exceeded', 'span_cm' => $span];
    }
    return ['valid' => true, 'empty' => false, 'span_cm' => $span];
}
