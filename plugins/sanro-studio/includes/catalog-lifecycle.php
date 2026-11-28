<?php
defined('ABSPATH') || exit;

/** Studio membership is independent of WooCommerce publication. */
function sanro_studio_catalog_is_mapped($product_id) {
    $product_id = absint($product_id);
    if (!$product_id) return false;
    $status = get_post_meta($product_id, '_sanro_studio_catalog_status', true);
    if (in_array($status, ['active', 'disabled', 'archived'], true)) return true;
    // New products receive an explicit unmapped marker. Unknown markers fail closed.
    if ($status !== '' && $status !== null) return false;
    // Native Woo forms also save default zones/categories on non-Studio products.
    // Only a complete, validated legacy configuration proves previous opt-in.
    return function_exists('sanro_studio_public_editor_config')
        && !empty(sanro_studio_public_editor_config($product_id, true)['valid']);
}

function sanro_studio_catalog_status($product_id) {
    $status = get_post_meta(absint($product_id), '_sanro_studio_catalog_status', true);
    if (in_array($status, ['active', 'disabled', 'archived'], true)) return $status;
    return sanro_studio_catalog_is_mapped($product_id) ? 'active' : 'unmapped';
}

function sanro_studio_catalog_is_buyer_visible($product_id) {
    return sanro_studio_catalog_status($product_id) === 'active';
}

/** Woo fires this only for creation, after assigning the persisted product ID. */
function sanro_studio_catalog_new_product($product_id) {
    $product_id = absint($product_id);
    // Also clear inherited membership on a newly duplicated/imported product.
    if ($product_id) update_post_meta($product_id, '_sanro_studio_catalog_status', 'unmapped');
}

/** Native post-new.php creates an auto-draft before Woo's product object save. */
function sanro_studio_catalog_new_product_post($post_id, $post, $update) {
    if (!$update && is_object($post) && ($post->post_type ?? null) === 'product') {
        sanro_studio_catalog_new_product($post_id);
    }
}
