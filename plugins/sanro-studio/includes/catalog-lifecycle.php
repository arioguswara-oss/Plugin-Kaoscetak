<?php
defined('ABSPATH') || exit;

/** Independent SANRO listing state stored on the mapped WooCommerce product. */
function sanro_studio_catalog_status($product_id) {
    $status = get_post_meta(absint($product_id), '_sanro_studio_catalog_status', true);
    return in_array($status, ['active', 'disabled', 'archived'], true) ? $status : 'active';
}

function sanro_studio_catalog_is_buyer_visible($product_id) {
    return sanro_studio_catalog_status($product_id) === 'active';
}

/**
 * Explicit lifecycle metadata marks every Studio listing. Older installations
 * predate that field, so recognize their persisted Studio configuration too.
 * This is deliberately based on product configuration metadata, never on
 * designs/orders, and does not change the legacy buyer-visible default.
 */
function sanro_studio_catalog_is_mapped($product_id) {
    $product_id = absint($product_id);
    if (!$product_id) return false;
    if (metadata_exists('post', $product_id, '_sanro_studio_catalog_status')) return true;
    foreach ([
        '_sanro_studio_pricing_profile',
        '_sanro_studio_price_table',
        '_sanro_studio_safe_areas',
        '_sanro_studio_print_categories',
        '_sanro_studio_print_zones',
        '_sanro_studio_mockups',
        '_sanro_studio_mockup_matrix',
    ] as $key) {
        if (!metadata_exists('post', $product_id, $key)) continue;
        $value = get_post_meta($product_id, $key, true);
        if ($value !== '' && $value !== null && $value !== []) return true;
    }
    return false;
}
