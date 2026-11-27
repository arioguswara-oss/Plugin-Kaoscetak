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

/** Product-specific size limits, ordered from the smallest to the largest tier. */
function sanro_studio_validate_print_categories($limits) {
    $defaults = sanro_studio_print_categories();
    if (!is_array($limits) || count($limits) !== count($defaults)) return false;
    $normalized = [];
    $previous = [0,0];
    foreach ($defaults as $category => $_) {
        $pair = $limits[$category] ?? null;
        if (!is_array($pair) || array_keys($pair) !== [0,1]) return false;
        foreach ($pair as $axis => $value) {
            if (!is_numeric($value) || !is_finite((float)$value)
                || (float)$value < 0.1 || (float)$value > 100 || (float)$value < $previous[$axis]) {
                return false;
            }
        }
        $pair = array_map('floatval',$pair);
        if ($pair === $previous) return false;
        $normalized[$category] = $previous = $pair;
    }
    return $normalized;
}

function sanro_studio_product_print_categories($product_id) {
    $stored = get_post_meta($product_id,'_sanro_studio_print_categories',true);
    $limits = sanro_studio_validate_print_categories($stored);
    return $limits === false ? sanro_studio_print_categories() : $limits;
}

function sanro_studio_validate_product_design($product_id, $design, $frozen_categories = null, $frozen_config = null) {
    $areas = $frozen_config === null ? sanro_studio_product_safe_areas($product_id) : [];
    $palette = $frozen_config === null
        ? (function_exists('sanro_studio_mockup_palette') ? array_keys(sanro_studio_mockup_palette($product_id)) : ['#ffffff', '#171717', '#b91c1c', '#1d4ed8', '#166534'])
        : [];
    if ($frozen_config !== null) {
        if (!is_array($frozen_config) || ($frozen_config['version'] ?? null) !== 1
            || !is_array($frozen_config['safe_areas'] ?? null) || !is_array($frozen_config['palette'] ?? null)
            || !is_array($frozen_config['print_limits'] ?? null)) return ['valid'=>false,'error'=>'frozen_product_config_invalid'];
        $frozen_areas=[];
        foreach(['front','back'] as $side){$area=$frozen_config['safe_areas'][$side]??null;
            if(!is_array($area)||!is_numeric($area['width']??null)||!is_numeric($area['height']??null)
                ||!is_finite((float)$area['width'])||!is_finite((float)$area['height'])
                ||(float)$area['width']<=0||(float)$area['width']>100||(float)$area['height']<=0||(float)$area['height']>100)
                return ['valid'=>false,'error'=>'frozen_product_config_invalid'];
            $frozen_areas[$side]=['width'=>(float)$area['width'],'height'=>(float)$area['height']];
        }
        $frozen_palette=[];foreach($frozen_config['palette'] as $color=>$label){
            if(!is_string($color)||!preg_match('/^#[a-f0-9]{6}$/D',$color)||!is_string($label)||strlen($label)>80)return ['valid'=>false,'error'=>'frozen_product_config_invalid'];
            $frozen_palette[]=$color;
        }
        $validated_limits=sanro_studio_validate_print_categories($frozen_config['print_limits']);
        if(!$frozen_palette||$validated_limits===false)return ['valid'=>false,'error'=>'frozen_product_config_invalid'];
        $areas=$frozen_areas;$palette=$frozen_palette;$frozen_categories=$validated_limits;
    }
    if (!$areas) return ['valid' => false, 'error' => 'product_area_unconfigured'];
    // Draft text is printable metadata; geometry validation still owns all
    // category and safe-area checks. Master images remain outside WordPress.
    if (!is_array($design)) return ['valid' => false, 'error' => 'invalid_design'];
    // Order preferences are draft metadata only, never trusted for checkout pricing.
    if (isset($design['shirt_color']) && (!is_string($design['shirt_color'])
        || !in_array($design['shirt_color'], $palette, true))) {
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
    return sanro_studio_validate_print_design($geometry, $areas, $frozen_categories ?? sanro_studio_product_print_categories($product_id));
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
    echo '<p><strong>Batas kategori cetak otomatis (cm)</strong></p>';
    foreach (sanro_studio_product_print_categories($id) as $category => $pair) {
        foreach (['w'=>'Lebar','h'=>'Tinggi'] as $axis => $label) {
            woocommerce_wp_text_input([
                'id'=>'_sanro_print_'.str_replace('+','_plus',$category).'_'.$axis, 'label'=>$category.' — '.$label.' (cm)',
                'type'=>'number', 'value'=>$pair[$axis === 'w' ? 0 : 1],
                'custom_attributes'=>['step'=>'0.1','min'=>'0.1','max'=>'100'],
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
    $limits = [];
    foreach (sanro_studio_print_categories() as $category => $_) {
        $width = $_POST['_sanro_print_'.str_replace('+','_plus',$category).'_w'] ?? null;
        $height = $_POST['_sanro_print_'.str_replace('+','_plus',$category).'_h'] ?? null;
        if ($width !== null || $height !== null) {
            $limits[$category] = [is_scalar($width) ? wp_unslash($width) : null, is_scalar($height) ? wp_unslash($height) : null];
        }
    }
    if ($limits) {
        $normalized = sanro_studio_validate_print_categories($limits);
        if ($normalized === false) {
            if (class_exists('WC_Admin_Meta_Boxes')) WC_Admin_Meta_Boxes::add_error('Batas kategori harus bertambah dari A5 sampai A3+.');
            return;
        }
        $product->update_meta_data('_sanro_studio_print_categories',$normalized);
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
        'categories' => sanro_studio_product_print_categories($product_id),
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
    if(method_exists($product,'is_type')&&!$product->is_type('simple')&&!$product->is_type('variable'))$issues[]='unsupported_product_type';
    if(method_exists($product,'is_type')&&$product->is_type('variable')&&(!function_exists('sanro_studio_variation_map')||!is_array($variation_map=sanro_studio_variation_map($product_id))||!$variation_map))$issues[]='native_variations_not_configured';
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
        'native_variations_not_configured'=>'Petakan variation ID untuk setiap warna dan ukuran.',
        'unsupported_product_type'=>'Tipe produk WooCommerce tidak didukung.',
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
