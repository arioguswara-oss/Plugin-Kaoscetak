<?php
defined('ABSPATH') || exit;

/**
 * Staging integration boundary for an order item created by WooCommerce.
 * The caller must provide a freshly authorized draft contract and the cart
 * item from the same checkout. This is not a public endpoint or checkout hook.
 * Never save owner tokens, artwork, or client-provided prices.
 */
function sanro_studio_prepare_checkout_order_item($contract, $cart_item, $order_item) {
    if (!function_exists('sanro_studio_validate_cart_design_identity')
        || !function_exists('sanro_studio_validate_order_item_quote_amount')) {
        return ['valid' => false, 'error' => 'checkout_bridge_unavailable'];
    }
    $identity = sanro_studio_validate_cart_design_identity($contract, $cart_item);
    if (empty($identity['valid'])) return $identity;
    if (!is_object($order_item)
        || !method_exists($order_item, 'get_product_id')
        || !method_exists($order_item, 'get_quantity')
        || !method_exists($order_item, 'get_subtotal')
        || !method_exists($order_item, 'get_meta')
        || !method_exists($order_item, 'update_meta_data')) {
        return ['valid' => false, 'error' => 'invalid_order_item'];
    }
    if ($order_item->get_product_id() !== $identity['product_id']
        || $order_item->get_quantity() !== $identity['quantity']) {
        return ['valid' => false, 'error' => 'order_identity_mismatch'];
    }
    $amount = sanro_studio_validate_order_item_quote_amount($order_item, $contract['quote']);
    if (empty($amount['valid'])) return $amount;
    $existing = $order_item->get_meta('_sanro_studio_design_id', true);
    if ($existing !== '' && $existing !== null) {
        return ['valid' => false, 'error' => 'order_design_already_bound'];
    }
    $order_item->update_meta_data('_sanro_studio_design_id', $identity['design_id']);
    return [
        'valid' => true,
        'design_id' => $identity['design_id'],
        'requires_item_save' => true,
        'checkout_enabled' => false,
    ];
}

/**
 * Verify a WooCommerce order line against one server-priced size candidate.
 * Only after all lines are verified may the caller bind the design metadata.
 * No hook or order write is performed by this helper.
 */
function sanro_studio_validate_size_order_item($candidate, $order_item) {
    if (!is_array($candidate) || !is_object($order_item)
        || !method_exists($order_item, 'get_product_id')
        || !method_exists($order_item, 'get_quantity')
        || !method_exists($order_item, 'get_subtotal')
        || !isset($candidate['product_id'], $candidate['quantity'],
            $candidate['sanro_studio_line_subtotal'], $candidate['sanro_studio_size'])
        || !is_int($candidate['product_id']) || !is_int($candidate['quantity'])
        || !is_int($candidate['sanro_studio_line_subtotal'])
        || $candidate['quantity'] < 1
        || !in_array($candidate['sanro_studio_size'], ['S','M','L','XL','2XL','3XL','4XL','5XL'], true)
        || $order_item->get_product_id() !== $candidate['product_id']
        || $order_item->get_quantity() !== $candidate['quantity']) {
        return ['valid' => false, 'error' => 'size_order_identity_mismatch'];
    }
    $subtotal = $order_item->get_subtotal();
    if ((!is_string($subtotal) && !is_int($subtotal) && !is_float($subtotal))
        || !preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/D', (string) $subtotal)) {
        return ['valid' => false, 'error' => 'size_order_amount_invalid'];
    }
    $parts = explode('.', (string) $subtotal, 2);
    if ($parts[0] !== (string) $candidate['sanro_studio_line_subtotal']
        || str_pad($parts[1] ?? '', 2, '0') !== '00') {
        return ['valid' => false, 'error' => 'size_order_amount_mismatch'];
    }
    return ['valid' => true, 'size' => $candidate['sanro_studio_size'],
        'design_id' => $candidate['sanro_studio_design_id'] ?? null];
}

/**
 * Bind a verified size-line design to an unsaved WooCommerce order item.
 * Staging boundary only; the caller is responsible for atomic checkout flow.
 * Never store owner tokens, artwork bytes, private file paths or price claims.
 */
function sanro_studio_prepare_size_checkout_order_item($candidate, $order_item) {
    $verified = sanro_studio_validate_size_order_item($candidate, $order_item);
    if (empty($verified['valid'])) return $verified;
    if (!is_string($verified['design_id'])
        || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $verified['design_id'])
        || !method_exists($order_item, 'get_meta')
        || !method_exists($order_item, 'update_meta_data')
        || !isset($candidate['sanro_studio_unit_price'])
        || !is_int($candidate['sanro_studio_unit_price'])
        || $candidate['sanro_studio_unit_price'] < 1
        || $candidate['sanro_studio_line_subtotal'] !== $candidate['quantity'] * $candidate['sanro_studio_unit_price']
        || ($candidate['sanro_studio_quote_currency'] ?? null) !== 'IDR') {
        return ['valid' => false, 'error' => 'invalid_size_checkout_contract'];
    }
    foreach (['_sanro_studio_design_id', '_sanro_studio_size'] as $key) {
        $existing = $order_item->get_meta($key, true);
        if ($existing !== '' && $existing !== null) {
            return ['valid' => false, 'error' => 'size_order_already_bound'];
        }
    }
    $order_item->update_meta_data('_sanro_studio_design_id', $verified['design_id']);
    $order_item->update_meta_data('_sanro_studio_size', $verified['size']);
    return ['valid' => true, 'design_id' => $verified['design_id'],
        'size' => $verified['size'], 'requires_item_save' => true,
        'checkout_enabled' => false];
}

/**
 * Validate a complete set of unsaved WooCommerce order items before binding
 * any design metadata. Never partially bind a multi-size order.
 */
function sanro_studio_validate_size_checkout_batch($contract, $order_items) {
    if (!function_exists('sanro_studio_prepare_size_cart_lines')) {
        return ['valid' => false, 'error' => 'cart_contract_unavailable'];
    }
    $expected = sanro_studio_prepare_size_cart_lines($contract);
    if (empty($expected['valid'])) return $expected;
    if (!is_array($order_items) || count($order_items) !== count($expected['lines'])) {
        return ['valid' => false, 'error' => 'order_line_count_mismatch'];
    }
    // Explicit size keys are necessary: product ID alone cannot distinguish
    // two different size lines for the same shirt design.
    $remaining = [];
    foreach ($expected['lines'] as $line) $remaining[$line['sanro_studio_size']] = $line;
    foreach ($order_items as $size => $item) {
        if (!is_string($size) || !isset($remaining[$size])) {
            return ['valid' => false, 'error' => 'order_size_mismatch'];
        }
        $result = sanro_studio_validate_size_order_item($remaining[$size], $item);
        if (empty($result['valid'])) return $result;
        unset($remaining[$size]);
    }
    if ($remaining) return ['valid' => false, 'error' => 'order_missing_size'];
    return ['valid' => true, 'total_price' => $expected['total_price'],
        'total_quantity' => $expected['total_quantity'], 'checkout_enabled' => false];
}

/**
 * Fail-closed staging checkout gate. Requires fresh owner authorization on
 * every invocation and checks the entire WC cart against the live quote.
 * This does not register a hook or create an order.
 */
function sanro_studio_stage_authorize_checkout($design_id, $owner_token, $product_id, $cart) {
    if (!defined('SANRO_STUDIO_ENABLE_STAGING_CART')
        || SANRO_STUDIO_ENABLE_STAGING_CART !== true
        || !defined('SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT')
        || SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT !== true
        || !is_object($cart) || !method_exists($cart, 'get_cart')
        || !is_int($product_id) || $product_id < 1
        || !function_exists('sanro_studio_prepare_cart_design_contract')
        || !function_exists('sanro_studio_validate_size_cart_lines')
        || !function_exists('get_woocommerce_currency')
        || get_woocommerce_currency() !== 'IDR') {
        return ['valid' => false, 'error' => 'checkout_gate_disabled'];
    }
    $contract = sanro_studio_prepare_cart_design_contract($design_id, $owner_token, $product_id);
    if (empty($contract['valid'])) return $contract;
    $cart_lines = $cart->get_cart();
    if (!is_array($cart_lines) || !$cart_lines) {
        return ['valid' => false, 'error' => 'checkout_cart_empty'];
    }
    $snapshots = [];
    foreach ($cart_lines as $line) {
        if (!is_array($line) || !isset($line['data']) || !is_object($line['data'])
            || !method_exists($line['data'], 'get_price')) {
            return ['valid' => false, 'error' => 'checkout_product_invalid'];
        }
        $snapshot = $line;
        unset($snapshot['data']);
        $snapshots[] = $snapshot;
    }
    $verified = sanro_studio_validate_size_cart_lines($contract, $snapshots);
    if (empty($verified['valid'])) return $verified;
    $expected = sanro_studio_prepare_size_cart_lines($contract);
    $prices = [];
    foreach ($expected['lines'] as $line) {
        $prices[$line['sanro_studio_size']] = $line['sanro_studio_unit_price'];
    }
    foreach ($cart_lines as $line) {
        $size = $line['sanro_studio_size'];
        $actual = $line['data']->get_price();
        if ((!is_string($actual) && !is_int($actual) && !is_float($actual))
            || !preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/D', (string) $actual)
            || (float) $actual !== (float) $prices[$size]) {
            return ['valid' => false, 'error' => 'checkout_price_mismatch'];
        }
    }
    return ['valid' => true, 'design_id' => $contract['design_id'],
        'total_quantity' => $verified['total_quantity'],
        'total_price' => $verified['total_price'],
        'requires_order_item_validation' => true];
}

/**
 * Prepare metadata for every unsaved order item after verifying the complete
 * size batch. A duplicate binding or missing metadata API rejects the entire
 * batch before any item is mutated. Caller still owns persistence/rollback.
 */
function sanro_studio_stage_bind_size_order_batch($contract, $order_items) {
    if (!defined('SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT')
        || SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT !== true) {
        return ['valid' => false, 'error' => 'order_binding_disabled'];
    }
    $verified = sanro_studio_validate_size_checkout_batch($contract, $order_items);
    if (empty($verified['valid'])) return $verified;
    $prepared = sanro_studio_prepare_size_cart_lines($contract);
    $candidates = [];
    foreach ($prepared['lines'] as $line) {
        $candidates[$line['sanro_studio_size']] = $line;
    }
    foreach ($order_items as $size => $item) {
        if (!is_object($item) || !method_exists($item, 'get_meta')
            || !method_exists($item, 'update_meta_data')
            || !isset($candidates[$size])
            || !is_string($candidates[$size]['sanro_studio_design_id'])) {
            return ['valid' => false, 'error' => 'order_metadata_unavailable'];
        }
        foreach (['_sanro_studio_design_id', '_sanro_studio_size'] as $key) {
            $existing = $item->get_meta($key, true);
            if ($existing !== '' && $existing !== null) {
                return ['valid' => false, 'error' => 'order_design_already_bound'];
            }
        }
    }
    foreach ($order_items as $size => $item) {
        $item->update_meta_data('_sanro_studio_design_id', $candidates[$size]['sanro_studio_design_id']);
        $item->update_meta_data('_sanro_studio_size', $size);
    }
    return ['valid' => true, 'bound_items' => count($order_items),
        'total_price' => $verified['total_price'],
        'requires_order_save' => true, 'checkout_enabled' => false];
}

/**
 * Optional WooCommerce checkout validation hook for staging only.
 * A transient credential resolver must be supplied by a trusted integration.
 * Never read an owner token from cart item metadata or persist it in WC.
 * Unrelated ordinary carts remain untouched.
 */
function sanro_studio_stage_checkout_process() {
    unset($GLOBALS['sanro_studio_checkout_verified']);
    if (!defined('SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK')
        || SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK !== true
        || !function_exists('WC') || !function_exists('wc_add_notice')) return;
    $wc = WC();
    if (!is_object($wc) || !isset($wc->cart) || !is_object($wc->cart)
        || !method_exists($wc->cart, 'get_cart')) return;
    $items = $wc->cart->get_cart();
    if (!is_array($items)) return;
    $studio = [];
    foreach ($items as $line) {
        if (!is_array($line)) continue;
        foreach ($line as $key => $value) {
            if (is_string($key) && strpos($key, 'sanro_studio_') === 0) {
                $studio[] = $line;
                break;
            }
        }
    }
    if (!$studio) return;
    if (count($studio) !== count($items)) {
        wc_add_notice('SANRO Studio: mixed carts are not supported in staging.', 'error');
        return;
    }
    $first = $studio[0];
    $id = $first['sanro_studio_design_id'] ?? null;
    $product_id = $first['product_id'] ?? null;
    if (!is_string($id) || !is_int($product_id) || $product_id < 1
        || !function_exists('apply_filters')) {
        wc_add_notice('SANRO Studio: checkout authorization unavailable.', 'error');
        return;
    }
    // No default credential: without a trusted, request-scoped resolver,
    // checkout must fail closed instead of trusting stale cart data.
    $token = apply_filters('sanro_studio_staging_checkout_owner_token', null, $id, $product_id);
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) {
        wc_add_notice('SANRO Studio: reauthorize your design before checkout.', 'error');
        return;
    }
    $result = sanro_studio_stage_authorize_checkout($id, $token, $product_id, $wc->cart);
    if (empty($result['valid'])) {
        wc_add_notice('SANRO Studio: design or pricing validation failed.', 'error');
        return;
    }
    // Authorization is request-scoped: never put the owner token in cart,
    // order metadata, or a WooCommerce session.
    $contract = sanro_studio_prepare_cart_design_contract($id, $token, $product_id);
    if (empty($contract['valid'])) {
        wc_add_notice('SANRO Studio: design authorization expired.', 'error');
        return;
    }
    $GLOBALS['sanro_studio_checkout_verified'] = [
        'design_id' => $id, 'product_id' => $product_id,
        'contract' => $contract, 'bound_sizes' => [],
    ];
}

/**
 * WooCommerce creates each order line after the checkout_process gate.
 * Never trust a cart item's claimed design or price without the live quote.
 * A failed item aborts checkout rather than persisting an unbound design.
 */
function sanro_studio_stage_checkout_create_order_line_item($item, $cart_item_key, $values, $order) {
    if (!defined('SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK')
        || SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK !== true
        || !is_array($values)) return;
    $is_studio = false;
    foreach (array_keys($values) as $key) {
        if (is_string($key) && strpos($key, 'sanro_studio_') === 0) {
            $is_studio = true;
            break;
        }
    }
    if (!$is_studio) return;
    $verified = $GLOBALS['sanro_studio_checkout_verified'] ?? null;
    if (!is_array($verified) || !isset($verified['contract'])
        || empty($verified['contract']['valid'])) {
        throw new RuntimeException('SANRO Studio: checkout authorization missing.');
    }
    $contract = $verified['contract'];
    $size = $values['sanro_studio_size'] ?? null;
    if (!is_string($size) || !isset($verified['bound_sizes'])
        || !is_array($verified['bound_sizes'])
        || isset($verified['bound_sizes'][$size])) {
        throw new RuntimeException('SANRO Studio: duplicate or invalid order size.');
    }
    $candidates = sanro_studio_prepare_size_cart_lines($contract);
    if (empty($candidates['valid'])) {
        throw new RuntimeException('SANRO Studio: invalid size quote.');
    }
    $candidate = null;
    foreach ($candidates['lines'] as $line) {
        if ($line['sanro_studio_size'] === ($values['sanro_studio_size'] ?? null)) {
            $candidate = $line;
            break;
        }
    }
    if (!is_array($candidate)) {
        throw new RuntimeException('SANRO Studio: size not authorized.');
    }
    // Full-batch validation happens in checkout_process. Here compare the
    // exact current line with the quote before binding metadata.
    foreach (['product_id', 'quantity', 'sanro_studio_design_id',
        'sanro_studio_size', 'sanro_studio_unit_price',
        'sanro_studio_line_subtotal', 'sanro_studio_quote_currency'] as $field) {
        if (!array_key_exists($field, $values)
            || $values[$field] !== $candidate[$field]) {
            throw new RuntimeException('SANRO Studio: cart item changed during checkout.');
        }
    }
    $bound = sanro_studio_prepare_size_checkout_order_item($candidate, $item);
    if (empty($bound['valid'])) {
        throw new RuntimeException('SANRO Studio: order item validation failed.');
    }
    $GLOBALS['sanro_studio_checkout_verified']['bound_sizes'][$size] = true;
}

/**
 * Staging-only classic checkout credential entry. A draft owner token is
 * submitted with the checkout POST, not saved to WC cart/session/order.
 * This is an integration QA path, not the final buyer experience.
 */
function sanro_studio_stage_checkout_credential_field() {
    if (!defined('SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK')
        || SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK !== true
        || !function_exists('WC') || !function_exists('wp_nonce_field')) return;
    $wc = WC();
    if (!is_object($wc) || !isset($wc->cart)
        || !is_object($wc->cart) || !method_exists($wc->cart, 'get_cart')) return;
    foreach ((array) $wc->cart->get_cart() as $line) {
        if (!is_array($line)) continue;
        foreach (array_keys($line) as $key) {
            if (is_string($key) && strpos($key, 'sanro_studio_') === 0) {
                echo '<div class="sanro-studio-staging-authorization">';
                echo '<label for="sanro_studio_checkout_token">SANRO Studio draft owner token (staging only)</label>';
                echo '<input type="password" id="sanro_studio_checkout_token" name="sanro_studio_checkout_token" autocomplete="off" maxlength="64" required>';
                wp_nonce_field('sanro_studio_checkout_auth', 'sanro_studio_checkout_nonce');
                echo '</div>';
                return;
            }
        }
    }
}

/** Resolve the secret only from the current verified checkout request. */
function sanro_studio_stage_checkout_post_token($default, $design_id, $product_id) {
    if (!defined('SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK')
        || SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK !== true
        || !isset($_POST['sanro_studio_checkout_token'], $_POST['sanro_studio_checkout_nonce'])
        || !is_string($_POST['sanro_studio_checkout_token'])
        || !is_string($_POST['sanro_studio_checkout_nonce'])
        || !function_exists('wp_verify_nonce')) return null;
    $nonce = $_POST['sanro_studio_checkout_nonce'];
    if (!preg_match('/^[a-zA-Z0-9]+$/D', $nonce)
        || !wp_verify_nonce($nonce, 'sanro_studio_checkout_auth')) return null;
    $token = $_POST['sanro_studio_checkout_token'];
    return preg_match('/^[a-f0-9]{64}$/D', $token) ? $token : null;
}

/**
 * Final pre-save guard: all expected sizes must have been bound exactly once.
 * Called by classic WooCommerce checkout_create_order before order persistence.
 */
function sanro_studio_stage_checkout_verify_order_batch($order, $data) {
    if (!defined('SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK')
        || SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK !== true) return;
    $verified = $GLOBALS['sanro_studio_checkout_verified'] ?? null;
    if (!is_array($verified) || empty($verified['contract']['valid'])) return;
    if (!is_object($order) || !method_exists($order, 'get_items')) {
        throw new RuntimeException('SANRO Studio: order inspection unavailable.');
    }
    $expected = sanro_studio_prepare_size_cart_lines($verified['contract']);
    if (empty($expected['valid']) || !isset($verified['bound_sizes'])
        || !is_array($verified['bound_sizes'])) {
        throw new RuntimeException('SANRO Studio: incomplete checkout contract.');
    }
    $sizes = [];
    foreach ($expected['lines'] as $line) $sizes[$line['sanro_studio_size']] = true;
    if (count($sizes) !== count($verified['bound_sizes'])
        || array_diff_key($sizes, $verified['bound_sizes'])) {
        throw new RuntimeException('SANRO Studio: incomplete order size batch.');
    }
    $items = $order->get_items('line_item');
    if (!is_array($items) || count($items) !== count($sizes)) {
        throw new RuntimeException('SANRO Studio: unexpected order line count.');
    }
    $seen = [];
    foreach ($items as $item) {
        if (!is_object($item) || !method_exists($item, 'get_meta')) {
            throw new RuntimeException('SANRO Studio: invalid order item.');
        }
        $size = $item->get_meta('_sanro_studio_size', true);
        $design = $item->get_meta('_sanro_studio_design_id', true);
        if (!is_string($size) || !isset($sizes[$size]) || isset($seen[$size])
            || $design !== $verified['contract']['design_id']) {
            throw new RuntimeException('SANRO Studio: order metadata mismatch.');
        }
        $seen[$size] = true;
    }
}
