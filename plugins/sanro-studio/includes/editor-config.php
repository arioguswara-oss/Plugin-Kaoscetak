<?php
defined('ABSPATH') || exit;

/**
 * Public, read-only editor configuration for published, explicitly opted-in
 * WooCommerce products. Never exposes tokens, drafts or private metadata.
 */
function sanro_studio_public_editor_config($product_id) {
    $product_id = absint($product_id);
    if (!$product_id || !function_exists('wc_get_product')) {
        return ['valid' => false, 'error' => 'product_unavailable'];
    }
    $product = wc_get_product($product_id);
    if (!$product || !is_object($product) || !method_exists($product, 'get_status')
        || $product->get_status() !== 'publish'
        || !method_exists($product, 'is_purchasable') || !$product->is_purchasable()) {
        return ['valid' => false, 'error' => 'product_unavailable'];
    }
    if (get_post_meta($product_id, '_sanro_studio_pricing_profile', true) !== 'cotton_combed_24s') {
        return ['valid' => false, 'error' => 'product_unavailable'];
    }
    $config = sanro_studio_editor_area_config($product_id);
    if (empty($config['valid'])) return ['valid' => false, 'error' => 'product_unavailable'];
    return [
        'valid' => true,
        'product_id' => $product_id,
        'unit' => $config['unit'],
        'safe_areas' => $config['safe_areas'],
        'categories' => $config['categories'],
        'pricing_profile' => 'cotton_combed_24s',
    ];
}

function sanro_studio_register_editor_config_route() {
    register_rest_route('sanro-studio/v1', '/products/(?P<id>[1-9][0-9]*)/editor-config', [
        'methods' => 'GET',
        'callback' => 'sanro_studio_rest_editor_config',
        'permission_callback' => '__return_true',
        'args' => ['id' => [
            'required' => true,
            'validate_callback' => static function ($value, $request, $param) {
                return is_scalar($value) && ctype_digit((string) $value) && (int) $value > 0;
            },
        ]],
    ]);
}

function sanro_studio_rest_editor_config($request) {
    $config = sanro_studio_public_editor_config($request->get_param('id'));
    if (empty($config['valid'])) {
        return new WP_Error('sanro_editor_unavailable', 'Editor unavailable for this product.', ['status' => 404]);
    }
    return rest_ensure_response($config);
}
