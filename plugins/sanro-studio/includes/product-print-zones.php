<?php
defined('ABSPATH') || exit;

/**
 * Visual placement of the print-safe overlay on the selected product mockup.
 * These percentages are display-only and NEVER replace the authoritative
 * centimeter limits in sanro_studio_product_safe_areas().
 */
function sanro_studio_default_print_zone() {
    return ['x' => 27.0, 'y' => 22.0, 'width' => 46.0, 'height' => 54.0];
}
function sanro_studio_valid_print_zone($zone) {
    if (!is_array($zone) || count($zone) !== 4) return false;
    $result = [];
    foreach (['x','y','width','height'] as $key) {
        if (!array_key_exists($key, $zone) || !is_scalar($zone[$key])
            || !is_numeric($zone[$key]) || !is_finite((float)$zone[$key])) return false;
        $result[$key] = (float)$zone[$key];
    }
    return $result['x'] >= 0 && $result['y'] >= 0
        && $result['width'] > 0 && $result['height'] > 0
        && $result['x'] + $result['width'] <= 100.0
        && $result['y'] + $result['height'] <= 100.0
        ? $result : false;
}
function sanro_studio_product_print_zones($product_id) {
    $product_id = absint($product_id);
    $stored = $product_id ? get_post_meta($product_id, '_sanro_studio_print_zones', true) : null;
    $result = [];
    foreach (['front','back'] as $side) {
        $validated = is_array($stored) && isset($stored[$side])
            ? sanro_studio_valid_print_zone($stored[$side]) : false;
        $result[$side] = $validated !== false ? $validated : sanro_studio_default_print_zone();
    }
    return $result;
}
function sanro_studio_product_print_zone_fields() {
    if (!function_exists('woocommerce_wp_text_input') || !function_exists('get_the_ID')) return;
    $product_id = absint(get_the_ID());
    if (!$product_id || !current_user_can('edit_post',$product_id)) return;
    $zones = sanro_studio_product_print_zones($product_id);
    echo '<div class="options_group"><p><strong>SANRO Studio — posisi area cetak pada mockup (%)</strong></p>';
    echo '<p class="form-field">Mengatur letak visual di pratinjau, bukan ukuran cetak dalam cm.</p>';
    foreach (['front'=>'Depan','back'=>'Belakang'] as $side=>$label) {
        foreach (['x'=>'Jarak kiri','y'=>'Jarak atas','width'=>'Lebar visual','height'=>'Tinggi visual'] as $key=>$caption) {
            woocommerce_wp_text_input([
                'id'=>'_sanro_studio_zone_'.$side.'_'.$key,
                'label'=>$label.' — '.$caption.' (%)',
                'type'=>'number',
                'custom_attributes'=>['min'=>'0','max'=>'100','step'=>'0.1'],
                'value'=>$zones[$side][$key],
                'description'=>'Total posisi dan ukuran tidak boleh melewati 100%.',
            ]);
        }
    }
    echo '</div>';
}
/** Invoked only after WooCommerce has verified its product-edit form. */
function sanro_studio_save_product_print_zones($product) {
    if (!is_object($product) || !method_exists($product, 'get_id')
        || !method_exists($product, 'update_meta_data')) return;
    $product_id = absint($product->get_id());
    if (!$product_id || !current_user_can('edit_post',$product_id)) return;
    $zones=[];
    foreach (['front','back'] as $side) {
        $candidate=[];
        foreach (['x','y','width','height'] as $key) {
            $name='_sanro_studio_zone_'.$side.'_'.$key;
            if (!isset($_POST[$name]) || !is_string($_POST[$name])) return;
            $candidate[$key]=trim(wp_unslash($_POST[$name]));
        }
        $zone=sanro_studio_valid_print_zone($candidate);
        if ($zone===false) return; // Fail closed; keep existing valid settings.
        $zones[$side]=$zone;
    }
    $product->update_meta_data('_sanro_studio_print_zones',$zones);
}
