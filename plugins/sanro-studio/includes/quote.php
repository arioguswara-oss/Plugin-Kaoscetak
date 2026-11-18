<?php
defined('ABSPATH') || exit;

/**
 * Read-only server quotation for an explicitly configured Cotton Combed 24s product.
 * Do not call from checkout until order ownership, master uploads and quantity
 * have been verified separately. Client-supplied price is never accepted.
 */
function sanro_studio_quote_product_design($product_id, $design, $size, $long_sleeve = false) {
    $product_id = absint($product_id);
    if (!$product_id) return ['valid' => false, 'error' => 'invalid_product'];
    // Quotes are only available for published, purchasable products.
    // This is a read-only gate, not checkout authorization.
    if (!function_exists('wc_get_product')) return ['valid' => false, 'error' => 'product_unavailable'];
    $product = wc_get_product($product_id);
    if (!$product || !method_exists($product, 'get_status') || $product->get_status() !== 'publish'
        || !method_exists($product, 'is_purchasable') || !$product->is_purchasable()) {
        return ['valid' => false, 'error' => 'product_unavailable'];
    }
    // Fail closed: other products have independent admin price schedules.
    if (!function_exists('wc_get_product')) return ['valid' => false, 'error' => 'product_unavailable'];
    $product = wc_get_product($product_id);
    if (!$product || !method_exists($product, 'get_status') || $product->get_status() !== 'publish'
        || !method_exists($product, 'is_purchasable') || !$product->is_purchasable()) {
        return ['valid' => false, 'error' => 'product_unavailable'];
    }
    if (get_post_meta($product_id, '_sanro_studio_pricing_profile', true) !== 'cotton_combed_24s') {
        return ['valid' => false, 'error' => 'pricing_profile_unconfigured'];
    }
    $validated = sanro_studio_validate_product_design($product_id, $design);
    if (empty($validated['valid'])) return $validated;
    $front = null;
    $back = null;
    foreach (['front', 'back'] as $side) {
        if (!empty($validated['sides'][$side]) && empty($validated['sides'][$side]['empty'])) {
            if ($side === 'front') $front = $design[$side]['category'];
            else $back = $design[$side]['category'];
        }
    }
    $quote = sanro_studio_24s_unit_price($size, $front, $back, $long_sleeve);
    if (empty($quote['valid'])) return $quote;
    return [
        'valid' => true,
        'product_id' => $product_id,
        'unit_price' => $quote['unit_price'],
        'currency' => $quote['currency'],
        'categories' => ['front' => $front, 'back' => $back],
    ];
}

/**
 * Read-only quotation for one design with quantities by size.
 * Prices and subtotal are derived exclusively on the server.
 * Max 999 pieces per size and 5000 pieces per quote; actual checkout limits
 * may be stricter and must be independently validated.
 */
function sanro_studio_quote_size_quantities($product_id, $design, $quantities, $long_sleeve = false) {
    if (!is_array($quantities) || !$quantities || count($quantities) > 8
        || !is_bool($long_sleeve)) {
        return ['valid' => false, 'error' => 'invalid_quantities'];
    }
    $allowed = sanro_studio_24s_price_table();
    // Validate the design and product price profile once, even when a quote
    // contains multiple sizes. Never accept a browser-provided unit price.
    $product_id = absint($product_id);
    if (!$product_id) return ['valid' => false, 'error' => 'invalid_product'];
    if (get_post_meta($product_id, '_sanro_studio_pricing_profile', true) !== 'cotton_combed_24s') {
        return ['valid' => false, 'error' => 'pricing_profile_unconfigured'];
    }
    $validated = sanro_studio_validate_product_design($product_id, $design);
    if (empty($validated['valid'])) return $validated;
    $front = !empty($validated['sides']['front']) && empty($validated['sides']['front']['empty']) ? $design['front']['category'] : null;
    $back = !empty($validated['sides']['back']) && empty($validated['sides']['back']['empty']) ? $design['back']['category'] : null;
    $rows = [];
    $total_quantity = 0;
    $total_price = 0;
    foreach ($quantities as $size => $quantity) {
        if (!is_string($size) || !isset($allowed[$size])
            || !is_int($quantity) || $quantity < 0 || $quantity > 999) {
            return ['valid' => false, 'error' => 'invalid_quantities'];
        }
        if ($quantity === 0) continue;
        $total_quantity += $quantity;
        if ($total_quantity > 5000) {
            return ['valid' => false, 'error' => 'invalid_quantities'];
        }
        $quote = sanro_studio_24s_unit_price($size, $front, $back, $long_sleeve);
        if (empty($quote['valid'])) return $quote;
        $subtotal = $quote['unit_price'] * $quantity;
        $total_price += $subtotal;
        $rows[$size] = [
            'quantity' => $quantity,
            'unit_price' => $quote['unit_price'],
            'subtotal' => $subtotal,
        ];
    }
    if ($total_quantity === 0) return ['valid' => false, 'error' => 'invalid_quantities'];
    return [
        'valid' => true,
        'currency' => 'IDR',
        'items' => $rows,
        'total_quantity' => $total_quantity,
        'total_price' => $total_price,
    ];
}
