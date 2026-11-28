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
 * Axis-aligned bounds of a rectangle rotated about its center in physical cm.
 * The caller must validate the source rectangle and angle first.
 */
function sanro_studio_print_object_bounds($object) {
    $x = (float) $object['x'];
    $y = (float) $object['y'];
    $width = (float) $object['width'];
    $height = (float) $object['height'];
    $radians = deg2rad((float) ($object['rotation'] ?? 0));
    if ($radians == 0) return [$x,$y,$x+$width,$y+$height];
    $span_x = $width * abs(cos($radians)) + $height * abs(sin($radians));
    $span_y = $width * abs(sin($radians)) + $height * abs(cos($radians));
    return [$x + ($width - $span_x) / 2, $y + ($height - $span_y) / 2,
        $x + ($width + $span_x) / 2, $y + ($height + $span_y) / 2];
}

/** Validate supported types and compute the smallest configured tier for one side. */
function sanro_studio_validate_print_side($objects, $safe_area, $categories = null) {
    $categories = $categories ?? sanro_studio_print_categories();
    if (!is_array($objects) || !is_array($safe_area)
        || !isset($safe_area['width'], $safe_area['height'])
        || !is_numeric($safe_area['width']) || !is_numeric($safe_area['height'])
        || !is_finite((float) $safe_area['width']) || !is_finite((float) $safe_area['height'])
        || (float) $safe_area['width'] <= 0 || (float) $safe_area['height'] <= 0) {
        return ['valid' => false, 'error' => 'invalid_safe_area'];
    }
    if (count($objects) > 2) return ['valid' => false, 'error' => 'too_many_objects'];
    // A JSON array of objects is required; associative keys must not bypass object limits.
    if (array_keys($objects) !== range(0, count($objects) - 1) && $objects !== []) {
        return ['valid' => false, 'error' => 'invalid_objects'];
    }
    $seen = [];
    $min_x = $min_y = INF;
    $max_x = $max_y = -INF;
    foreach ($objects as $object) {
        if (!is_array($object) || array_diff(array_keys($object), ['type', 'x', 'y', 'width', 'height', 'rotation']) || !isset($object['type'])
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
        $rotation = array_key_exists('rotation',$object) ? $object['rotation'] : 0;
        if ((!is_int($rotation) && !is_float($rotation))
            || !is_finite((float) $rotation) || abs($rotation) > 180
            || $object['width'] <= 0 || $object['height'] <= 0) {
            return ['valid' => false, 'error' => 'invalid_geometry'];
        }
        [$left,$top,$right,$bottom] = sanro_studio_print_object_bounds($object);
        if ($left < -0.000001 || $top < -0.000001
            || $right > (float) $safe_area['width'] + 0.000001
            || $bottom > (float) $safe_area['height'] + 0.000001) {
            return ['valid' => false, 'error' => 'outside_safe_area'];
        }
        $min_x = min($min_x,$left);
        $min_y = min($min_y,$top);
        $max_x = max($max_x,$right);
        $max_y = max($max_y,$bottom);
    }
    if (!$objects) return ['valid' => true, 'empty' => true, 'category' => null, 'span_cm' => [0, 0]];
    $span = [$max_x - $min_x, $max_y - $min_y];
    foreach ($categories as $category => [$width,$height]) {
        if ($span[0] <= $width + 0.000001 && $span[1] <= $height + 0.000001) {
            return ['valid' => true, 'empty' => false, 'category' => $category, 'span_cm' => $span];
        }
    }
    return ['valid' => false, 'error' => 'category_exceeded', 'span_cm' => $span];
}

/**
 * Validate a two-sided design as a single server-side contract.
 * Each side supplies objects; the product supplies safe areas and limits.
 * A category in a legacy draft is accepted as inert metadata.
 * Reject unknown keys, malformed payloads and designs with no printable content.
 * Does not compute price or persist customer data.
 */
function sanro_studio_validate_print_design($design, $safe_areas, $categories = null) {
    if (!is_array($design) || !is_array($safe_areas)
        || array_diff(array_keys($design), ['front', 'back'])
        || array_diff(array_keys($safe_areas), ['front', 'back'])) {
        return ['valid' => false, 'error' => 'invalid_design'];
    }
    $results = [];
    $nonempty = 0;
    foreach (['front', 'back'] as $side) {
        if (!array_key_exists($side, $design)) continue;
        $entry = $design[$side];
        if (!is_array($entry) || !array_key_exists('objects', $entry)
            || array_diff(array_keys($entry), ['objects', 'category'])
            || !array_key_exists($side, $safe_areas)) {
            return ['valid' => false, 'error' => 'invalid_design', 'side' => $side];
        }
        $result = sanro_studio_validate_print_side(
            $entry['objects'], $safe_areas[$side], $categories
        );
        if (!$result['valid']) {
            return ['valid' => false, 'error' => $result['error'], 'side' => $side];
        }
        $results[$side] = $result;
        if (!$result['empty']) $nonempty++;
    }
    if (!$nonempty) return ['valid' => false, 'error' => 'empty_design'];
    return ['valid' => true, 'sides' => $results];
}
