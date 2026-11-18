<?php
defined('ABSPATH') || exit;

/**
 * Per-product print safety dimensions. Never accept safe areas from a buyer payload.
 * No defaults: a product must be explicitly configured before accepting a design.
 */
function sanro_studio_product_safe_areas($product_id) {
    $product_id = absint($product_id);
    if (!$product_id) return [];
    $stored = get_post_meta($product_id, '_sanro_studio_safe_areas', true);
    if (!is_array($stored)) return [];
    $areas = [];
    foreach (['front', 'back'] as $side) {
        $entry = $stored[$side] ?? null;
        if (!is_array($entry) || !isset($entry['width'], $entry['height'])) continue;
        $width = $entry['width'];
        $height = $entry['height'];
        if (!is_numeric($width) || !is_numeric($height)
            || !is_finite((float) $width) || !is_finite((float) $height)
            || (float) $width <= 0 || (float) $height <= 0
            || (float) $width > 100 || (float) $height > 100) continue;
        $areas[$side] = ['width' => (float) $width, 'height' => (float) $height];
    }
    return $areas;
}

function sanro_studio_validate_product_design($product_id, $design) {
    $areas = sanro_studio_product_safe_areas($product_id);
    if (!$areas) return ['valid' => false, 'error' => 'product_area_unconfigured'];
    // Draft text is printable metadata; geometry validation still owns all
    // category and safe-area checks. Master images remain outside WordPress.
    if (!is_array($design)) return ['valid' => false, 'error' => 'invalid_design'];
    $geometry = $design;
    foreach (['front', 'back'] as $side) {
        if (!isset($geometry[$side])) continue;
        if (!is_array($geometry[$side])) return ['valid' => false, 'error' => 'invalid_design'];
        if (array_key_exists('text', $geometry[$side])) {
            $value = $geometry[$side]['text'];
            if (!is_string($value) || strlen($value) > 1200
                || preg_match('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F]/', $value)) {
                return ['valid' => false, 'error' => 'invalid_text'];
            }
            $has_text = false;
            foreach (($geometry[$side]['objects'] ?? []) as $object) {
                if (is_array($object) && ($object['type'] ?? null) === 'text') $has_text = true;
            }
            if (!$has_text || trim($value) === '') return ['valid' => false, 'error' => 'invalid_text'];
            unset($geometry[$side]['text']);
        }
    }
    return sanro_studio_validate_print_design($geometry, $areas);
}

/** Product edit screen only; never shown to shoppers. */
function sanro_studio_product_area_fields() {
    if (!function_exists('woocommerce_wp_text_input')) return;
    $id = get_the_ID();
    $areas = sanro_studio_product_safe_areas($id);
    echo '<div class="options_group"><p><strong>Area aman SANRO Studio (cm)</strong></p>';
    foreach (['front' => 'Depan', 'back' => 'Belakang'] as $side => $label) {
        foreach (['width' => 'Lebar', 'height' => 'Tinggi'] as $axis => $axis_label) {
            woocommerce_wp_text_input([
                'id' => '_sanro_studio_' . $side . '_' . $axis,
                'label' => $label . ' — ' . $axis_label . ' (cm)',
                'type' => 'number',
                'custom_attributes' => ['step' => '0.1', 'min' => '0.1', 'max' => '100'],
                'value' => isset($areas[$side][$axis]) ? $areas[$side][$axis] : '',
                'description' => 'Batas fisik maksimum, bukan ukuran desain wajib.',
            ]);
        }
    }
    echo '</div>';
}

/** Called by WooCommerce product-admin save hook after its nonce and capability checks. */
function sanro_studio_save_product_areas($product) {
    if (!is_object($product) || !method_exists($product, 'get_id')
        || !method_exists($product, 'update_meta_data')
        || !current_user_can('edit_post', $product->get_id())) return;
    $areas = [];
    foreach (['front', 'back'] as $side) {
        $values = [];
        foreach (['width', 'height'] as $axis) {
            $key = '_sanro_studio_' . $side . '_' . $axis;
            if (!isset($_POST[$key]) || !is_scalar($_POST[$key])) return;
            $value = wp_unslash($_POST[$key]);
            if ($value === '') continue;
            if (!is_numeric($value) || !is_finite((float) $value)
                || (float) $value <= 0 || (float) $value > 100) return;
            $values[$axis] = (float) $value;
        }
        if (count($values) === 1) return; // Never persist a partial rectangle.
        if (count($values) === 2) $areas[$side] = $values;
    }
    // Empty settings intentionally disable public design acceptance for this product.
    $product->update_meta_data('_sanro_studio_safe_areas', $areas);
}

/**
 * Read-only editor configuration. Admin metadata is the sole source of cm bounds.
 * Do not expose private product metadata or accept dimensions from the browser.
 */
function sanro_studio_editor_area_config($product_id) {
    $areas = sanro_studio_product_safe_areas($product_id);
    if (!$areas) return ['valid' => false, 'error' => 'product_area_unconfigured'];
    return [
        'valid' => true,
        'unit' => 'cm',
        'safe_areas' => $areas,
        'categories' => sanro_studio_print_categories(),
    ];
}
