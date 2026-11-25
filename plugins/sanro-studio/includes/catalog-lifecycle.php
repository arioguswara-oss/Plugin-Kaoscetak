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
