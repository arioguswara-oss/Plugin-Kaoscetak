<?php
defined('ABSPATH') || exit;

/**
 * Authoritative product quote; no persistence, discount, cart or payment effects.
 * Callers must authorize the draft separately. Never accept a client price,
 * profile, safe area or repeat-order eligibility as input to this boundary.
 */
function sanro_studio_product_quote($product_id, $design) {
    if (!is_int($product_id) || $product_id < 1) {
        return ['valid' => false, 'error' => 'product_unavailable'];
    }
    if (!function_exists('get_woocommerce_currency') || get_woocommerce_currency() !== 'IDR') {
        return ['valid' => false, 'error' => 'unsupported_currency'];
    }
    $config = sanro_studio_public_editor_config($product_id);
    if (empty($config['valid']) || !in_array($config['pricing_profile'],['cotton_combed_24s','tunik_oversize_34'],true)) {
        return ['valid' => false, 'error' => 'product_unavailable'];
    }
    if (!is_array($design) || (array_key_exists('long_sleeve', $design) && !is_bool($design['long_sleeve']))) {
        return ['valid' => false, 'error' => 'invalid_variant'];
    }
    if (!empty($design['long_sleeve']) && ($config['pricing']['sleeve_label'] ?? null) !== 'long') {
        return ['valid'=>false,'error'=>'select_long_sleeve_product'];
    }
    $profile = function_exists('sanro_studio_product_pricing_config') ? sanro_studio_product_pricing_config($product_id) : null;
    $validated = sanro_studio_validate_product_design($product_id, $design);
    if (empty($validated['valid'])) return $validated;
    $quantities = $design['quantities'] ?? null;
    if (!is_array($quantities) || !$quantities) {
        return ['valid' => false, 'error' => 'invalid_quantities'];
    }
    $wc_product=function_exists('wc_get_product')?wc_get_product($product_id):null;
    $is_variable=$wc_product&&method_exists($wc_product,'is_type')&&$wc_product->is_type('variable');
    $variation_map=$is_variable?sanro_studio_variation_map($product_id):[];
    if($is_variable&&(!is_array($variation_map)||!isset($design['shirt_color'],$variation_map[$design['shirt_color']])))return ['valid'=>false,'error'=>'variation_unavailable'];
    $categories = ['front' => null, 'back' => null];
    foreach ($validated['sides'] as $side => $result) {
        // An empty side is not charged, regardless of its selected category.
        if (!$result['empty']) $categories[$side] = $design[$side]['category'];
    }
    $lines = [];
    $pieces = $total = 0;
    foreach ($quantities as $size => $quantity) {
        if ($quantity === 0) continue;
        $unit=$profile!==null?sanro_studio_profile_unit_price($profile,$size,$categories['front'],$categories['back'])
            :sanro_studio_24s_unit_price($size,$categories['front'],$categories['back']);
        if (!$unit['valid']) return $unit;
        $subtotal = $unit['unit_price'] * $quantity;
        $mapped=$is_variable?($variation_map[$design['shirt_color']][$size]??null):null;
        if($is_variable&&(!is_array($mapped)||empty($mapped['available'])||!isset($mapped['variation_id'],$mapped['attributes'])||!sanro_studio_variation_for_mapping($product_id,$mapped['variation_id'],sanro_studio_mockup_palette($product_id)[$design['shirt_color']]??'',$size)))return ['valid'=>false,'error'=>'variation_unavailable'];
        $lines[$size] = ['quantity' => $quantity, 'unit_price' => $unit['unit_price'], 'subtotal' => $subtotal,
            'variation_id'=>$is_variable?$mapped['variation_id']:0,'variation_attributes'=>$is_variable?$mapped['attributes']:[]];
        $pieces += $quantity;
        $total += $subtotal;
    }
    if (!$pieces) return ['valid' => false, 'error' => 'invalid_quantities'];
    return [
        'valid' => true, 'contract_version' => 1, 'product_id' => $product_id,
        'shirt_color' => isset($design['shirt_color'])&&is_string($design['shirt_color'])?$design['shirt_color']:'',
        'pricing_profile' => $config['pricing_profile'], 'currency' => 'IDR',
        'pricing_revision'=>$profile['revision']??null,
        'sleeve_label'=>$profile['sleeve_label']??'short',
        'categories' => $categories, 'sides' => $validated['sides'],
        'lines' => $lines, 'pieces' => $pieces, 'total' => $total,
    ];
}

/**
 * WooCommerce adapter contract: recalculate one size line before insertion and
 * again during totals/checkout. $design must be reloaded from an authorized
 * server-side draft. No hooks are activated until the cart bridge is integrated.
 * Native WC still owns stock, attributes, taxes, coupons and order validation.
 */
function sanro_studio_validate_priced_cart_line($product_id, $design, $size, $quantity) {
    if (!is_string($size) || !is_int($quantity) || $quantity < 1 || $quantity > 999) {
        return ['valid' => false, 'error' => 'invalid_cart_line'];
    }
    $quote = sanro_studio_product_quote($product_id, $design);
    if (!$quote['valid']) return $quote;
    if (!isset($quote['lines'][$size]) || $quote['lines'][$size]['quantity'] !== $quantity) {
        return ['valid' => false, 'error' => 'cart_quantity_mismatch'];
    }
    return ['valid' => true, 'currency' => $quote['currency'], 'product_id' => $product_id,
        'size' => $size] + $quote['lines'][$size];
}
