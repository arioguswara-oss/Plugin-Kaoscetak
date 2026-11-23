<?php
defined('ABSPATH') || exit;

/**
 * Product-level opt-in for the approved 24s price schedule.
 * Other products remain unconfigured until their own tables are implemented.
 */
function sanro_studio_product_pricing_fields() {
    if (!function_exists('woocommerce_wp_select')) return;
    $product_id = get_the_ID();
    $current = get_post_meta($product_id, '_sanro_studio_pricing_profile', true);
    woocommerce_wp_select([
        'id' => '_sanro_studio_pricing_profile',
        'label' => 'SANRO Studio — Profil harga',
        'value' => $current === 'cotton_combed_24s' ? $current : '',
        'options' => [
            '' => 'Nonaktif (belum dikonfigurasi)',
            'cotton_combed_24s' => 'Cotton Combed 24s — tabel harga resmi',
        ],
        'description' => 'Harga dari server. Produk lain membutuhkan tabel harga tersendiri.',
        'desc_tip' => true,
    ]);
}

/**
 * WooCommerce has already verified the product-editor nonce.
 * Fail closed on missing/tampered values, without silently changing pricing.
 */
function sanro_studio_save_product_pricing($product) {
    if (!is_object($product) || !method_exists($product, 'get_id')
        || !method_exists($product, 'update_meta_data')
        || !current_user_can('edit_post', $product->get_id())
        || !isset($_POST['_sanro_studio_pricing_profile'])
        || !is_string($_POST['_sanro_studio_pricing_profile'])) return;
    $profile = wp_unslash($_POST['_sanro_studio_pricing_profile']);
    if (!in_array($profile, ['', 'cotton_combed_24s'], true)) return;
    $product->update_meta_data('_sanro_studio_pricing_profile', $profile);
}
