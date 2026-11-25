<?php
defined('ABSPATH') || exit;

/**
 * Bind a staging cart line to a fresh server-authorized quote without
 * persisting the draft owner token. The cart may be reconstructed from a WC
 * session, so the price must be independently authenticated on each load.
 */
function sanro_studio_stage_cart_signature($line, $expires) {
    if (!is_array($line) || !is_int($expires) || $expires < 1
        || !function_exists('wp_salt')) return null;
    $keys = ['product_id', 'quantity', 'sanro_studio_design_id',
        'sanro_studio_size', 'sanro_studio_unit_price',
        'sanro_studio_line_subtotal', 'sanro_studio_quote_currency','sanro_studio_color','variation_id'];
    $values = ['sanro-cart-v1', $expires];
    foreach ($keys as $key) {
        $value=$key==='variation_id'?($line[$key]??0):($key==='sanro_studio_color'?($line[$key]??''):($line[$key]??null));
        if ((!is_int($value) && !is_string($value))) return null;
        $values[] = $value;
    }
    $variation=$line['variation']??[];if(!is_array($variation))return null;
    ksort($variation);$values[]=json_encode($variation,JSON_UNESCAPED_SLASHES);
    $secret = wp_salt('auth');
    if (!is_string($secret) || strlen($secret) < 16) return null;
    $payload = json_encode($values, JSON_UNESCAPED_SLASHES);
    return is_string($payload) ? hash_hmac('sha256', $payload, $secret) : null;
}

/**
 * Removal must succeed or abort the current Cart/Checkout request.
 * A false return or thrown hook error must never leave an unsafe Studio line
 * eligible for WooCommerce's regular catalog price.
 */
function sanro_studio_stage_remove_untrusted_cart_line($cart, $key) {
    try {
        if ($cart->remove_cart_item($key) === true) return;
    } catch (Throwable $ignored) {
        // Never downgrade an unsafe removal failure to a successful request.
    }
    throw new RuntimeException('SANRO Studio: unsafe cart item could not be removed.');
}

/**
 * WooCommerce reconstructs product objects from its session, often restoring
 * the ordinary catalog price. Reapply ONLY a server-signed Studio unit price.
 * Tampered or expired Studio lines are removed instead of being charged at
 * catalog prices. No order, payment, or owner token is involved.
 */
function sanro_studio_stage_reprice_signed_cart($cart) {
    if (!defined('SANRO_STUDIO_ENABLE_STAGING_CART')
        || SANRO_STUDIO_ENABLE_STAGING_CART !== true
        || !defined('SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT')
        || SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT !== true
        || !defined('SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK')
        || SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK !== true
        || !is_object($cart) || !method_exists($cart, 'get_cart')
        || !method_exists($cart, 'remove_cart_item')
        || !function_exists('get_woocommerce_currency')) return;
    try {
        $items = $cart->get_cart();
        $currency = get_woocommerce_currency();
    } catch (Throwable $error) {
        return;
    }
    if (!is_array($items)) return;
    // Never leave an existing signed Studio line at WooCommerce's catalog
    // price if a currency switcher changes the store currency. Remove only
    // Studio lines; ordinary WooCommerce products are not SANRO's concern.
    if ($currency !== 'IDR') {
        $has_studio_line = false;
        foreach ($items as $key => $line) {
            if (!is_array($line) || !array_key_exists('sanro_studio_design_id', $line)) continue;
            $has_studio_line = true;
            sanro_studio_stage_remove_untrusted_cart_line($cart, $key);
        }
        if ($has_studio_line && function_exists('wc_add_notice')) {
            wc_add_notice('SANRO Studio: harga desain hanya tersedia dalam IDR. Tambahkan ulang desain.', 'error');
        }
        return;
    }
    foreach ($items as $key => $line) {
        if (!is_array($line) || !array_key_exists('sanro_studio_design_id', $line)) continue;
        $expires = $line['_sanro_qa_expires'] ?? null;
        $signature = $line['_sanro_qa_signature'] ?? null;
        $expected = sanro_studio_stage_cart_signature($line, $expires);
        $valid = is_int($expires) && time() <= $expires
            && is_string($signature)
            && preg_match('/^[a-f0-9]{64}$/D', $signature)
            && is_string($expected) && hash_equals($expected, $signature)
            && isset($line['data']) && is_object($line['data'])
            && method_exists($line['data'], 'set_price')
            && isset($line['sanro_studio_unit_price'])
            && is_int($line['sanro_studio_unit_price'])
            && $line['sanro_studio_unit_price'] > 0
            && sanro_studio_stage_cart_variation_current($line);
        if (!$valid) {
            sanro_studio_stage_remove_untrusted_cart_line($cart, $key);
            if (function_exists('wc_add_notice')) {
                wc_add_notice('SANRO Studio: harga desain tidak dapat diverifikasi. Simpan dan tambahkan ulang desain.', 'error');
            }
            continue;
        }
        $line['data']->set_price((string) $line['sanro_studio_unit_price']);
    }
}

/** Signed session replay must still resolve to the current mapped, purchasable child. */
function sanro_studio_stage_cart_variation_current($line){
    if(!is_array($line)||!function_exists('wc_get_product'))return false;
    $parent=wc_get_product((int)($line['product_id']??0));$variation_id=(int)($line['variation_id']??0);
    if(!$parent||!$parent->is_purchasable())return false;
    if($variation_id===0)return $parent->is_type('simple');
    if(!$parent->is_type('variable')||!is_string($line['sanro_studio_color']??null)||!is_string($line['sanro_studio_size']??null))return false;
    $map=sanro_studio_variation_map((int)$line['product_id']);$entry=$map[$line['sanro_studio_color']][$line['sanro_studio_size']]??null;
    return is_array($entry)&&($entry['variation_id']??null)===$variation_id&&!empty($entry['available'])
        &&($entry['attributes']??null)===($line['variation']??[]);
}

/**
 * Render safe, source-authenticated Studio details in WooCommerce Cart and
 * Classic Checkout. Displaying a price is WooCommerce's job; never accept
 * arbitrary cart metadata or reveal draft owner credentials to the buyer.
 */
function sanro_studio_stage_cart_item_display($item_data, $cart_item) {
    if (!defined('SANRO_STUDIO_ENABLE_STAGING_CART')
        || SANRO_STUDIO_ENABLE_STAGING_CART !== true
        || !defined('SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT')
        || SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT !== true
        || !defined('SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK')
        || SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK !== true
        || !is_array($item_data) || !is_array($cart_item)) return $item_data;
    $id = $cart_item['sanro_studio_design_id'] ?? null;
    $size = $cart_item['sanro_studio_size'] ?? null;
    $expires = $cart_item['_sanro_qa_expires'] ?? null;
    $signature = $cart_item['_sanro_qa_signature'] ?? null;
    if (!is_string($id)
        || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $id)
        || !is_string($size)
        || !in_array($size, ['S','M','L','XL','2XL','3XL','4XL','5XL'], true)
        || !is_int($expires) || time() > $expires
        || !is_string($signature) || !preg_match('/^[a-f0-9]{64}$/D', $signature)) {
        return $item_data;
    }
    $expected = sanro_studio_stage_cart_signature($cart_item, $expires);
    if (!is_string($expected) || !hash_equals($expected, $signature)) {
        return $item_data;
    }
    $item_data[] = ['key' => 'Ukuran kaos', 'value' => $size];
    if(is_string($cart_item['sanro_studio_color']??null)&&$cart_item['sanro_studio_color']!=='')$item_data[]=['key'=>'Warna kaos','value'=>$cart_item['sanro_studio_color']];
    $item_data[] = ['key' => 'Kode desain', 'value' => substr($id, 0, 8)];
    return $item_data;
}

/**
 * Explicit staging-only WooCommerce cart adapter. Never registers a public
 * endpoint or hook. The owner-authorized contract is supplied transiently by
 * the caller; owner credentials must not enter WC session/cart metadata.
 */
function sanro_studio_stage_add_cart_lines($contract, $cart) {
    if (!defined('SANRO_STUDIO_ENABLE_STAGING_CART')
        || SANRO_STUDIO_ENABLE_STAGING_CART !== true
        || !function_exists('sanro_studio_prepare_size_cart_lines')
        || !function_exists('sanro_studio_validate_size_cart_lines')
        || !is_object($cart) || !method_exists($cart, 'add_to_cart')
        || !method_exists($cart, 'remove_cart_item')
        || !method_exists($cart, 'get_cart')
        || !function_exists('get_woocommerce_currency')
        || get_woocommerce_currency() !== 'IDR') {
        return ['valid' => false, 'error' => 'staging_cart_disabled'];
    }
    $prepared = sanro_studio_prepare_size_cart_lines($contract);
    if (empty($prepared['valid'])) return $prepared;
    $verified = sanro_studio_validate_size_cart_lines($contract, $prepared['lines']);
    if (empty($verified['valid'])) return $verified;
    // Resolve the parent and every variation from the server quote. Never
    // accept a variation identity posted by the buyer.
    if (!function_exists('wc_get_product')) {
        return ['valid' => false, 'error' => 'product_unavailable'];
    }
    $product = wc_get_product($contract['product_id']);
    if (!$product || !method_exists($product, 'is_type')
        || (!$product->is_type('simple')&&!$product->is_type('variable'))
        || !method_exists($product, 'is_purchasable')
        || !$product->is_purchasable()) {
        return ['valid' => false, 'error' => 'unsupported_product_type'];
    }
    // A WooCommerce extension can throw or return malformed session data.
    // Fail closed before adding or repricing anything in that situation.
    try {
        $existing_cart = $cart->get_cart();
    } catch (Throwable $error) {
        return ['valid' => false, 'error' => 'cart_unavailable'];
    }
    if (!is_array($existing_cart)) {
        return ['valid' => false, 'error' => 'cart_unavailable'];
    }
    // Do not touch an existing cart: only an empty cart is accepted.
    if ($existing_cart !== []) {
        return ['valid' => false, 'error' => 'cart_must_be_empty'];
    }
    // Read-only candidate output for the future transactional adapter.
    // Calling add_to_cart here before server-owned unit-price hooks exist
    // would silently charge the product's ordinary catalog price.
    return ['valid' => true, 'lines' => $prepared['lines'],
        'requires_price_hook' => true, 'cart_mutation_enabled' => false];
}

/**
 * Apply a server-authorized per-size unit price to a WooCommerce cart product
 * object. Caller must reauthorize the draft and pass its fresh quote; no
 * client-provided amount is trusted. No global price hook is registered.
 */
function sanro_studio_stage_price_cart_line($contract, $line, $product) {
    if (!defined('SANRO_STUDIO_ENABLE_STAGING_CART')
        || SANRO_STUDIO_ENABLE_STAGING_CART !== true
        || !is_object($product) || !method_exists($product, 'set_price')
        || !function_exists('get_woocommerce_currency')
        || get_woocommerce_currency() !== 'IDR') {
        return ['valid' => false, 'error' => 'staging_pricing_disabled'];
    }
    $expected = sanro_studio_prepare_size_cart_lines($contract);
    if (empty($expected['valid'])) return $expected;
    if (!is_array($line) || !isset($line['sanro_studio_size'])
        || !is_string($line['sanro_studio_size'])) {
        return ['valid' => false, 'error' => 'invalid_cart_line'];
    }
    $matched = null;
    foreach ($expected['lines'] as $candidate) {
        if ($candidate['sanro_studio_size'] === $line['sanro_studio_size']) {
            $matched = $candidate;
            break;
        }
    }
    if ($matched === null) return ['valid' => false, 'error' => 'unknown_cart_size'];
    foreach (['product_id','quantity', 'sanro_studio_design_id',
        'sanro_studio_size', 'sanro_studio_unit_price',
        'sanro_studio_line_subtotal', 'sanro_studio_quote_currency'] as $key) {
        if (!array_key_exists($key, $line) || $line[$key] !== $matched[$key]) {
            return ['valid' => false, 'error' => 'cart_line_mismatch'];
        }
    }
    if(!empty($matched['variation_id'])){
        if(($line['variation_id']??null)!==$matched['variation_id']||($line['variation']??null)!==$matched['variation']
            ||($line['sanro_studio_color']??null)!==$matched['sanro_studio_color'])return ['valid'=>false,'error'=>'cart_variation_mismatch'];
    }elseif((isset($line['variation_id'])&&$line['variation_id']!==0)||(isset($line['variation'])&&$line['variation']!==[])
        ||(isset($line['sanro_studio_color'])&&$line['sanro_studio_color']!==$matched['sanro_studio_color']))return ['valid'=>false,'error'=>'cart_line_mismatch'];
    $product->set_price((string) $matched['sanro_studio_unit_price']);
    return ['valid' => true, 'unit_price' => $matched['sanro_studio_unit_price'],
        'currency' => 'IDR', 'requires_totals_recalculation' => true];
}

/**
 * Price every line only after the complete cart matches a fresh server quote.
 * This method is for an isolated staging transaction; no WooCommerce hook is
 * attached automatically. Caller must recalculate WC totals afterwards.
 */
function sanro_studio_stage_price_cart_batch($contract, $cart_lines) {
    if (!defined('SANRO_STUDIO_ENABLE_STAGING_CART')
        || SANRO_STUDIO_ENABLE_STAGING_CART !== true
        || !is_array($cart_lines)) {
        return ['valid' => false, 'error' => 'staging_pricing_disabled'];
    }
    $snapshots = [];
    foreach ($cart_lines as $line) {
        if (!is_array($line) || !isset($line['data'])
            || !is_object($line['data']) || !method_exists($line['data'], 'set_price')) {
            return ['valid' => false, 'error' => 'invalid_cart_product'];
        }
        $snapshot = $line;
        unset($snapshot['data']);
        $snapshots[] = $snapshot;
    }
    $verified = sanro_studio_validate_size_cart_lines($contract, $snapshots);
    if (empty($verified['valid'])) return $verified;
    // No product price is mutated until every line has passed validation.
    foreach ($cart_lines as $line) {
        $priced = sanro_studio_stage_price_cart_line($contract, $line, $line['data']);
        if (empty($priced['valid'])) return $priced;
    }
    return ['valid' => true, 'total_quantity' => $verified['total_quantity'],
        'total_price' => $verified['total_price'], 'requires_totals_recalculation' => true];
}

/**
 * After WooCommerce calculate_totals(), ensure plugins/hooks did not change
 * sizes, quantity, quote metadata or product unit prices. Staging-only.
 * This does not authorize checkout and does not trust a client price.
 */
function sanro_studio_stage_verify_cart_prices($contract, $cart) {
    if (!is_object($cart) || !method_exists($cart, 'get_cart')) {
        return ['valid' => false, 'error' => 'cart_unavailable'];
    }
    $expected = sanro_studio_prepare_size_cart_lines($contract);
    if (empty($expected['valid'])) return $expected;
    $actual = $cart->get_cart();
    if (!is_array($actual) || count($actual) !== count($expected['lines'])) {
        return ['valid' => false, 'error' => 'cart_line_count_mismatch'];
    }
    $snapshots = [];
    $prices = [];
    foreach ($expected['lines'] as $line) {
        $prices[$line['sanro_studio_size']] = $line['sanro_studio_unit_price'];
    }
    foreach ($actual as $line) {
        if (!is_array($line) || !isset($line['data'])
            || !is_object($line['data']) || !method_exists($line['data'], 'get_price')
            || !isset($line['sanro_studio_size'])
            || !is_string($line['sanro_studio_size'])
            || !isset($prices[$line['sanro_studio_size']])) {
            return ['valid' => false, 'error' => 'cart_price_unavailable'];
        }
        $actual_price = $line['data']->get_price();
        if ((!is_string($actual_price) && !is_int($actual_price) && !is_float($actual_price))
            || !preg_match('/^(?:0|[1-9][0-9]*)(?:\.0{1,2})?$/D', (string) $actual_price)
            || (float) $actual_price !== (float) $prices[$line['sanro_studio_size']]) {
            return ['valid' => false, 'error' => 'cart_price_mismatch'];
        }
        unset($line['data']);
        $snapshots[] = $line;
    }
    return sanro_studio_validate_size_cart_lines($contract, $snapshots);
}

/**
 * Opt-in staging transaction: add a complete, empty-cart design batch, apply
 * verified per-size prices and recalculate totals. No endpoint/hook is exposed.
 * A separate checkout/session gate is still required before buyer use.
 */
function sanro_studio_stage_commit_cart_lines($contract, $cart) {
    if (!defined('SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT')
        || SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT !== true
        || !defined('SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK')
        || SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK !== true
        || !is_object($cart) || !method_exists($cart, 'calculate_totals')) {
        return ['valid' => false, 'error' => 'cart_commit_disabled'];
    }
    $preflight = sanro_studio_stage_add_cart_lines($contract, $cart);
    if (empty($preflight['valid'])) return $preflight;
    $keys = [];
    // WooCommerce hooks and pricing extensions can throw or change a line
    // during insertion/totals. Keep the batch all-or-nothing.
    try {
        foreach ($preflight['lines'] as $line) {
            $expires = time() + 3600;
            $signature = sanro_studio_stage_cart_signature($line, $expires);
            if (!is_string($signature)) {
                throw new RuntimeException('cart_signature_unavailable');
            }
            $meta = [
                '_sanro_qa_expires' => $expires,
                '_sanro_qa_signature' => $signature,
                'sanro_studio_design_id' => $line['sanro_studio_design_id'],
                'sanro_studio_size' => $line['sanro_studio_size'],
                'sanro_studio_unit_price' => $line['sanro_studio_unit_price'],
                'sanro_studio_line_subtotal' => $line['sanro_studio_line_subtotal'],
                'sanro_studio_quote_currency' => 'IDR',
                'sanro_studio_color' => $line['sanro_studio_color'],
            ];
            $key = $cart->add_to_cart($line['product_id'], $line['quantity'], $line['variation_id'], $line['variation'], $meta);
            if (!is_string($key) || $key === '' || in_array($key, $keys, true)) {
                throw new RuntimeException('cart_add_failed');
            }
            $keys[] = $key;
        }
        $actual = $cart->get_cart();
        $selected = [];
        foreach ($keys as $key) {
            if (!isset($actual[$key]) || !is_array($actual[$key])) {
                throw new RuntimeException('cart_line_missing');
            }
            $selected[] = $actual[$key];
        }
        $priced = sanro_studio_stage_price_cart_batch($contract, $selected);
        if (empty($priced['valid'])) {
            throw new RuntimeException('cart_pricing_failed');
        }
        $cart->calculate_totals();
        $checked = sanro_studio_stage_verify_cart_prices($contract, $cart);
        if (empty($checked['valid'])) {
            throw new RuntimeException('cart_totals_mismatch');
        }
        return ['valid' => true, 'cart_keys' => $keys,
            'total_price' => $priced['total_price'],
            'requires_session_checkout_guard' => true];
    } catch (Throwable $error) {
        // The cart was empty at preflight. A WooCommerce hook may insert a
        // line and then throw before add_to_cart() returns its key. Remove
        // both recorded keys and any untracked line from this failed batch.
        // Never expose exception details or draft credentials in REST.
        $rollback_keys = $keys;
        try {
            $pending = $cart->get_cart();
            if (is_array($pending)) {
                foreach (array_keys($pending) as $pending_key) {
                    if (is_string($pending_key)
                        && !in_array($pending_key, $rollback_keys, true)) {
                        $rollback_keys[] = $pending_key;
                    }
                }
            }
        } catch (Throwable $ignored) {}
        foreach ($rollback_keys as $added) {
            try { $cart->remove_cart_item($added); } catch (Throwable $ignored) {}
        }
        // If the cart could not be rolled back, explicitly fail closed.
        try {
            $remaining = $cart->get_cart();
            if (!is_array($remaining) || $remaining) {
                return ['valid' => false, 'error' => 'cart_rollback_incomplete'];
            }
        } catch (Throwable $ignored) {
            return ['valid' => false, 'error' => 'cart_rollback_incomplete'];
        }
        return ['valid' => false, 'error' => 'cart_transaction_failed'];
    }
}

/**
 * Restore and reprice a cart after session reload ONLY when the caller
 * presents the draft owner token again. Never persist that token in WC.
 * A missing, expired, modified or revoked draft fails closed.
 */
function sanro_studio_stage_reauthorize_cart($design_id, $owner_token, $product_id, $cart) {
    if (!defined('SANRO_STUDIO_ENABLE_STAGING_CART')
        || SANRO_STUDIO_ENABLE_STAGING_CART !== true
        || !is_object($cart) || !method_exists($cart, 'get_cart')
        || !method_exists($cart, 'calculate_totals')
        || !is_int($product_id) || $product_id < 1
        || !function_exists('sanro_studio_prepare_cart_design_contract')) {
        return ['valid' => false, 'error' => 'cart_reauthorization_disabled'];
    }
    $contract = sanro_studio_prepare_cart_design_contract($design_id, $owner_token, $product_id);
    if (empty($contract['valid'])) return $contract;
    $actual = $cart->get_cart();
    if (!is_array($actual) || !$actual) {
        return ['valid' => false, 'error' => 'cart_empty'];
    }
    try {
        $priced = sanro_studio_stage_price_cart_batch($contract, array_values($actual));
        if (empty($priced['valid'])) return $priced;
        $cart->calculate_totals();
        $checked = sanro_studio_stage_verify_cart_prices($contract, $cart);
        if (empty($checked['valid'])) return $checked;
    } catch (Throwable $error) {
        // Session is untrusted on reload; never return a successful price
        // authorization when a WooCommerce hook or pricing extension fails.
        return ['valid' => false, 'error' => 'cart_reprice_failed'];
    }
    return ['valid' => true, 'design_id' => $contract['design_id'],
        'total_price' => $priced['total_price'],
        'requires_checkout_reauthorization' => true];
}
