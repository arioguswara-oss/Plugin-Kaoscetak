<?php
defined('ABSPATH') || exit;

/** Default-off; never run alongside PR #50's signed QA cart price writer. */
function sanro_studio_pricing_wc_enabled() {
    return defined('SANRO_STUDIO_ENABLE_WC_PRICING') && SANRO_STUDIO_ENABLE_WC_PRICING === true
        && !(defined('SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK') && SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK === true);
}

function sanro_studio_pricing_is_line($line) {
    return is_array($line) && array_key_exists('sanro_studio_pricing_version', $line);
}

/** Native WC quantities can be numeric strings/floats; reject fractional/nonfinite values. */
function sanro_studio_pricing_quantity($quantity) {
    if ((!is_int($quantity) && !is_float($quantity) && !is_string($quantity))
        || !is_numeric($quantity) || !is_finite((float) $quantity)
        || (float) $quantity != floor((float) $quantity) || $quantity < 1 || $quantity > 999) return null;
    return (int) $quantity;
}

/** Only an integration-owned resolver may authorize a draft; no browser prices or tokens persisted. */
function sanro_studio_pricing_wc_quote($line, $quantity, $context) {
    if (!sanro_studio_pricing_wc_enabled()) throw new RuntimeException('SANRO pricing adapter disabled or competing QA adapter enabled.');
    $quantity = sanro_studio_pricing_quantity($quantity);
    $id = $line['sanro_studio_design_id'] ?? null;
    $size = $line['sanro_studio_size'] ?? null;
    $product_id = $line['product_id'] ?? null;
    if (($line['sanro_studio_pricing_version'] ?? null) !== 1 || !$quantity
        || !is_int($product_id) || $product_id < 1 || !is_string($size)
        || !is_string($id) || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $id)
        || !empty($line['variation_id'])) throw new RuntimeException('Invalid SANRO price identity or quantity.');
    $resolved = apply_filters('sanro_studio_pricing_authorized_design', null, $id, $product_id, $context);
    if (!is_array($resolved) || ($resolved['authorized'] ?? null) !== true
        || ($resolved['master_ready'] ?? null) !== true || ($resolved['design_id'] ?? null) !== $id
        || ($resolved['product_id'] ?? null) !== $product_id || !is_array($resolved['design'] ?? null)) {
        throw new RuntimeException('SANRO design authorization or master readiness unavailable.');
    }
    if (is_array($resolved['locked_quote']??null)) {
        $saved=$resolved['locked_quote']['items'][$size]??null;
        if (!is_array($saved) || ($saved['quantity']??null)!==$quantity) throw new RuntimeException('SANRO locked order quantity cannot change.');
        return $saved;
    }
    // Re-quote the native cart allocation without changing the saved artwork or draft.
    // Category/geometry/sleeve come only from the freshly authorized server design.
    $design = $resolved['design'];
    $design['quantities'] = [$size => $quantity];
    $quote = sanro_studio_product_quote($product_id, $design);
    if (empty($quote['valid']) || !isset($quote['lines'][$size])) throw new RuntimeException('SANRO server quote rejected.');
    if(function_exists('sanro_studio_repeat_adjust_quote')){
        $adjusted=sanro_studio_repeat_adjust_quote(['valid'=>true,'items'=>$quote['lines'],'total_price'=>$quote['total']],$id);
        if(empty($adjusted['valid']))throw new RuntimeException('SANRO repeat quote rejected.');
        return $adjusted['items'][$size];
    }
    return $quote['lines'][$size];
}

function sanro_studio_pricing_cart() {
    $wc = function_exists('WC') ? WC() : null;
    return is_object($wc) && isset($wc->cart) ? $wc->cart : null;
}

/** Validate entire allocation before any product price is mutated. */
function sanro_studio_pricing_cart_plan($cart, $replacement_key = null, $replacement_quantity = null, $addition = null) {
    if (!is_object($cart) || !method_exists($cart, 'get_cart')) throw new RuntimeException('SANRO cart unavailable.');
    $items = $cart->get_cart();
    if (!is_array($items)) throw new RuntimeException('Invalid WooCommerce cart.');
    if ($addition !== null) $items['__sanro_pending_addition'] = $addition;
    $plan = $pieces = $sizes = [];
    foreach ($items as $key => $line) {
        if (!sanro_studio_pricing_is_line($line)) continue;
        $quantity = $key === $replacement_key ? $replacement_quantity : ($line['quantity'] ?? null);
        // WC removes zero-quantity lines; no quote needed for the deleted line.
        if ($key === $replacement_key && is_numeric($quantity) && (float) $quantity === 0.0) continue;
        $quote = sanro_studio_pricing_wc_quote($line, $quantity, 'cart');
        $id = $line['sanro_studio_design_id'];
        $pieces[$id] = ($pieces[$id] ?? 0) + $quote['quantity'];
        $size_key = $id . ':' . $line['sanro_studio_size'];
        $sizes[$size_key] = ($sizes[$size_key] ?? 0) + $quote['quantity'];
        if ($sizes[$size_key] > 999) throw new RuntimeException('SANRO size exceeds 999 pieces.');
        if ($pieces[$id] > 5000) throw new RuntimeException('SANRO design exceeds 5000 pieces.');
        $plan[$key] = $quote;
    }
    return $plan;
}

function sanro_studio_pricing_notice($error) {
    // Do not expose resolver exceptions, tokens or private paths in customer notices.
    wc_add_notice('SANRO Studio: please reopen your design to verify its price and quantity.', 'error');
}

function sanro_studio_pricing_add_validation($passed, $product_id, $quantity, $variation_id = 0, $variation = [], $data = []) {
    if (!$passed || !sanro_studio_pricing_is_line($data)) return $passed;
    try {
        $line = array_merge($data, ['product_id' => (int) $product_id, 'variation_id' => (int) $variation_id, 'quantity' => $quantity]);
        sanro_studio_pricing_wc_quote($line, $quantity, 'add_to_cart');
        sanro_studio_pricing_cart_plan(sanro_studio_pricing_cart(), null, null, $line);
        return true;
    } catch (Throwable $error) { sanro_studio_pricing_notice($error); return false; }
}

function sanro_studio_pricing_update_validation($passed, $key, $values, $quantity) {
    if (!$passed || !sanro_studio_pricing_is_line($values)) return $passed;
    try {
        sanro_studio_pricing_cart_plan(sanro_studio_pricing_cart(), $key, $quantity);
        return true;
    } catch (Throwable $error) { sanro_studio_pricing_notice($error); return false; }
}

function sanro_studio_pricing_totals($cart) {
    if (!sanro_studio_pricing_wc_enabled()) return;
    $plan = sanro_studio_pricing_cart_plan($cart);
    $products = [];
    // Clone per-line product objects so ordinary lines/sizes sharing an object are untouched.
    foreach ($plan as $key => $quote) {
        $product = $cart->cart_contents[$key]['data'] ?? null;
        if (!is_object($product) || !method_exists($product, 'set_price')) throw new RuntimeException('SANRO product pricing unavailable.');
        $products[$key] = clone $product;
        $products[$key]->set_price((string) $quote['unit_price']);
    }
    foreach ($products as $key => $product) $cart->cart_contents[$key]['data'] = $product;
}

function sanro_studio_pricing_checkout() {
    try { sanro_studio_pricing_totals(sanro_studio_pricing_cart()); }
    catch (Throwable $error) { sanro_studio_pricing_notice($error); }
}

function sanro_studio_register_pricing_wc_hooks() {
    if (!sanro_studio_pricing_wc_enabled()) return;
    add_filter('woocommerce_add_to_cart_validation', 'sanro_studio_pricing_add_validation', 20, 6);
    add_filter('woocommerce_update_cart_validation', 'sanro_studio_pricing_update_validation', 20, 4);
    add_action('woocommerce_before_calculate_totals', 'sanro_studio_pricing_totals', 1000, 1);
    add_action('woocommerce_check_cart_items', 'sanro_studio_pricing_checkout', 20);
    add_action('woocommerce_checkout_process', 'sanro_studio_pricing_checkout', 20);
}
