<?php
defined('ABSPATH') || exit;

/**
 * Public, read-only editor configuration for published, explicitly opted-in
 * WooCommerce products. Never exposes tokens, drafts or private metadata.
 */
function sanro_studio_public_editor_config($product_id, $allow_inactive = false) {
    $product_id = absint($product_id);
    if (!$product_id || !function_exists('wc_get_product')) {
        return ['valid' => false, 'error' => 'product_unavailable'];
    }
    $product = wc_get_product($product_id);
    if (!$product || !is_object($product) || !method_exists($product, 'get_status')
        || $product->get_status() !== 'publish'
        || (method_exists($product,'is_type') && !$product->is_type('simple') && !$product->is_type('variable'))
        || !method_exists($product, 'is_purchasable') || !$product->is_purchasable()) {
        return ['valid' => false, 'error' => 'product_unavailable'];
    }
    // Products without an explicit lifecycle value are legacy listings and
    // stay available. New Studio mappings start disabled until configured.
    $catalog_status = function_exists('sanro_studio_catalog_status')
        ? sanro_studio_catalog_status($product_id) : get_post_meta($product_id, '_sanro_studio_catalog_status', true);
    if (!$allow_inactive && in_array($catalog_status, ['disabled', 'archived'], true)) {
        return ['valid' => false, 'error' => 'product_unavailable'];
    }
    $pricing=function_exists('sanro_studio_product_pricing_config')?sanro_studio_product_pricing_config($product_id):['valid'=>get_post_meta($product_id,'_sanro_studio_pricing_profile',true)==='cotton_combed_24s','profile'=>'cotton_combed_24s'];
    if (empty($pricing['valid'])) {
        return ['valid' => false, 'error' => 'product_unavailable'];
    }
    if(function_exists('sanro_studio_mockup_matrix_public') && sanro_studio_mockup_matrix_public($product_id)===false)return ['valid'=>false,'error'=>'mockup_mapping_invalid'];
    $variation_map=function_exists('sanro_studio_variation_map')?sanro_studio_variation_map($product_id):[];
    $is_variable=method_exists($product,'is_type')&&$product->is_type('variable');
    if($is_variable){
        $sizes=$pricing['sizes']??[];$palette=function_exists('sanro_studio_mockup_palette')?sanro_studio_mockup_palette($product_id):[];
        if(!is_array($variation_map)||!$variation_map||!is_array($sizes)||!$sizes||!is_array($palette)||!$palette)return ['valid'=>false,'error'=>'variation_mapping_invalid'];
        foreach(array_keys($palette) as $color)foreach($sizes as $size)if(!isset($variation_map[$color][$size]))return ['valid'=>false,'error'=>'variation_mapping_incomplete'];
    }
    $config = sanro_studio_editor_area_config($product_id);
    if (empty($config['valid'])) return ['valid' => false, 'error' => 'product_unavailable'];
    return [
        'valid' => true,
        'product_id' => $product_id,
        'unit' => $config['unit'],
        'safe_areas' => $config['safe_areas'],
        // Visual overlay placement only; centimeter limits remain server-owned.
        'print_zones' => function_exists('sanro_studio_product_print_zones')
            ? sanro_studio_product_print_zones($product_id) : [],
        'categories' => $config['categories'],
        'mockup_matrix' => function_exists('sanro_studio_mockup_matrix_public') ? sanro_studio_mockup_matrix_public($product_id) : [],
        'palette' => function_exists('sanro_studio_mockup_palette') ? sanro_studio_mockup_palette($product_id) : [],
        'variations' => is_array($variation_map)?$variation_map:[],
        'pricing_profile' => $pricing['profile'],
        'pricing' => isset($pricing['table'])?array_intersect_key($pricing,array_flip(['table','sizes','sleeve_extra','fixed_sleeve','revision'])):null,
        // Public, verified Media Library image URLs only. Never private DTF masters.
        'mockups' => function_exists('sanro_studio_product_mockup_urls')
            ? sanro_studio_product_mockup_urls($product_id) : [],
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

/** Product cards use configured one-side prices, never the placeholder Woo catalog price. */
function sanro_studio_minimum_configured_price($config) {
    if(!is_array($config)||empty($config['valid'])||!is_array($config['pricing']['table']??null))return null;
    $minimum=null;
    foreach($config['pricing']['table'] as $row){
        if(!is_array($row)||count($row)!==4)return null;
        foreach($row as $value){if(!is_int($value)||$value<1)return null;$minimum=$minimum===null?$value:min($minimum,$value);}
    }return $minimum;
}
