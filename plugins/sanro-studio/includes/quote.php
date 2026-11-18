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
    // Fail closed: other products have independent admin price schedules.
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
