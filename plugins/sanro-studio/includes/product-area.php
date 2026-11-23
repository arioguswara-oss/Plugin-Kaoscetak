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
    // Order preferences are draft metadata only, never trusted for checkout pricing.
    if (isset($design['shirt_color']) && (!is_string($design['shirt_color'])
        || !in_array($design['shirt_color'], ['#ffffff', '#171717', '#b91c1c', '#1d4ed8', '#166534'], true))) {
        return ['valid' => false, 'error' => 'invalid_shirt_color'];
    }
    if (isset($design['long_sleeve']) && !is_bool($design['long_sleeve'])) {
        return ['valid' => false, 'error' => 'invalid_variant'];
    }
    if (isset($design['quantities'])) {
        if (!is_array($design['quantities'])
            || array_diff(array_keys($design['quantities']), ['S','M','L','XL','2XL','3XL','4XL','5XL'])) {
            return ['valid' => false, 'error' => 'invalid_quantities'];
        }
        $total = 0;
        foreach ($design['quantities'] as $quantity) {
            if (!is_int($quantity) || $quantity < 0 || $quantity > 999) {
                return ['valid' => false, 'error' => 'invalid_quantities'];
            }
            $total += $quantity;
        }
        if ($total > 5000) return ['valid' => false, 'error' => 'invalid_quantities'];
    }
    $geometry = $design;
    unset($geometry['shirt_color'], $geometry['quantities'], $geometry['long_sleeve']);
    foreach (['front', 'back'] as $side) {
        if (!isset($geometry[$side])) continue;
        if (!is_array($geometry[$side])) return ['valid' => false, 'error' => 'invalid_design'];
        if (array_key_exists('text', $geometry[$side])) {
            $value = $geometry[$side]['text'];
            if (!is_string($value) || strlen($value) > 1200
                || preg_match('~[\x00-\x08\x0B\x0C\x0E-\x1F]~', $value)) {
                return ['valid' => false, 'error' => 'invalid_text'];
            }
            $has_text = false;
            foreach (($geometry[$side]['objects'] ?? []) as $object) {
                if (is_array($object) && ($object['type'] ?? null) === 'text') $has_text = true;
            }
            if (!$has_text || trim($value) === '') return ['valid' => false, 'error' => 'invalid_text'];
            if (array_key_exists('font_size', $geometry[$side])) {
                $font = $geometry[$side]['font_size'];
                if (!is_int($font) || $font < 10 || $font > 48) {
                    return ['valid' => false, 'error' => 'invalid_font_size'];
                }
                unset($geometry[$side]['font_size']);
            }
            if (array_key_exists('text_color', $geometry[$side])) {
                $color = $geometry[$side]['text_color'];
                if (!is_string($color) || !preg_match('/^#[0-9a-fA-F]{6}$/D', $color)) {
                    return ['valid' => false, 'error' => 'invalid_text_color'];
                }
                unset($geometry[$side]['text_color']);
            }
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

/**
 * Admin-facing product readiness diagnostic. Reports configuration gaps without
 * exposing private metadata to buyers or changing any product settings.
 */
function sanro_studio_product_readiness($product_id) {
    $product_id = absint($product_id);
    if (!$product_id || !function_exists('wc_get_product')) {
        return ['ready' => false, 'issues' => ['product_unavailable']];
    }
    $product = wc_get_product($product_id);
    if (!is_object($product) || !method_exists($product, 'get_status')
        || !method_exists($product, 'is_purchasable')) {
        return ['ready' => false, 'issues' => ['product_unavailable']];
    }
    $issues = [];
    if ($product->get_status() !== 'publish') $issues[] = 'product_not_published';
    if (!$product->is_purchasable()) $issues[] = 'product_not_purchasable';
    if (!sanro_studio_product_safe_areas($product_id)) $issues[] = 'safe_area_missing';
    $pricing=function_exists('sanro_studio_product_pricing_config')?sanro_studio_product_pricing_config($product_id):['valid'=>get_post_meta($product_id,'_sanro_studio_pricing_profile',true)==='cotton_combed_24s'];
    if (empty($pricing['valid'])) {
        $issues[] = 'pricing_profile_missing';
    }
    if(method_exists($product,'is_type')&&!$product->is_type('simple'))$issues[]='native_variations_not_configured';
    return ['ready' => !$issues, 'issues' => $issues];
}

/** Read-only diagnostic shown only in WooCommerce's product editor. */
function sanro_studio_product_readiness_notice() {
    if (!function_exists('get_the_ID') || !function_exists('current_user_can')) return;
    $id = absint(get_the_ID());
    if (!$id || !current_user_can('edit_post', $id)) return;
    $status = sanro_studio_product_readiness($id);
    $labels = [
        'product_unavailable' => 'Produk tidak tersedia',
        'product_not_published' => 'Produk belum dipublikasikan',
        'product_not_purchasable' => 'Produk belum dapat dibeli',
        'safe_area_missing' => 'Area cetak depan/belakang belum dikonfigurasi',
        'pricing_profile_missing' => 'Profil atau tabel harga SANRO Studio belum valid',
        'native_variations_not_configured'=>'V1 menggunakan size/lengan pada desain. Native variasi Woo belum dikonfigurasi untuk editor.',
    ];
    echo '<div class="options_group"><p><strong>SANRO Studio — Kesiapan Produk</strong></p>';
    if ($status['ready']) {
        echo '<p>Konfigurasi produk siap untuk editor (belum termasuk QA checkout).</p>';
    } else {
        foreach ($status['issues'] as $issue) {
            echo '<p>' . esc_html($labels[$issue] ?? 'Konfigurasi belum lengkap') . '</p>';
        }
    }
    echo '</div>';
}
