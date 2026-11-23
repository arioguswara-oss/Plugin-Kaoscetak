<?php
defined('ABSPATH') || exit;

/** Current opaque WC customer session, used only as a server-side draft binding. */
function sanro_studio_pricing_session_hash() {
    $wc = function_exists('WC') ? WC() : null;
    if (!is_object($wc) || !isset($wc->session) || !is_object($wc->session)
        || !method_exists($wc->session, 'get_customer_id') || !function_exists('wp_salt')) return null;
    $id = $wc->session->get_customer_id();
    $salt = wp_salt('auth');
    return is_string($id) && strlen($id) >= 16 && is_string($salt) && strlen($salt) >= 16
        ? hash_hmac('sha256', $id, $salt) : null;
}

/** Claim a draft for the cart session after its owner token has been checked. */
function sanro_studio_pricing_claim_draft_session($draft, $session_hash) {
    if (!is_array($draft) || empty($draft['valid']) || ($draft['status'] ?? null) !== 'draft'
        || !is_string($session_hash) || !preg_match('/^[a-f0-9]{64}$/D', $session_hash)
        || !function_exists('get_option') || !function_exists('maybe_serialize')
        || !function_exists('wp_cache_delete')) return false;
    global $wpdb;
    $id = $draft['design_id'] ?? null;
    if (!is_string($id) || !is_object($wpdb) || !isset($wpdb->options)
        || !preg_match('/^[A-Za-z0-9_]+$/D', $wpdb->options)
        || !method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'query')) return false;
    $key = 'sanro_studio_draft_' . $id;
    $before = get_option($key, false);
    if (!is_array($before) || ($before['status'] ?? null) !== 'draft'
        || ($before['product_id'] ?? null) !== ($draft['product_id'] ?? null)
        || !is_string($before['token_hash'] ?? null)
        || !hash_equals($draft['design_hash'], hash('sha256', maybe_serialize($before['design'] ?? null)))) return false;
    $bound = $before['wc_session_hash'] ?? null;
    if (is_string($bound)) return hash_equals($bound, $session_hash);
    $after = $before;
    $after['wc_session_hash'] = $session_hash;
    $after['wc_session_bound_at'] = time();
    $changed = $wpdb->query($wpdb->prepare(
        "UPDATE `{$wpdb->options}` SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s",
        maybe_serialize($after), $key, maybe_serialize($before)
    ));
    wp_cache_delete($key, 'options');
    return $changed === 1;
}

/** Ensure every image object still has its exact verified private master. */
function sanro_studio_pricing_masters_ready($design_id, $design, $refs) {
    foreach (['front','back'] as $side) {
        $has_image = false;
        $objects = $design[$side]['objects'] ?? [];
        if (!is_array($objects)) return false;
        foreach ($objects as $object) {
            if (is_array($object) && ($object['type'] ?? null) === 'image') { $has_image = true; break; }
        }
        if (!$has_image) continue;
        $ref = $refs[$side] ?? null;
        $prefix = strtolower($design_id) . '-' . $side . '-';
        if (!defined('SANRO_STUDIO_ENABLE_PRIVATE_MASTERS') || SANRO_STUDIO_ENABLE_PRIVATE_MASTERS !== true
            || !defined('SANRO_STUDIO_PRIVATE_MASTER_DIR') || !is_string(SANRO_STUDIO_PRIVATE_MASTER_DIR)
            || !is_string($ref) || strpos($ref, $prefix) !== 0
            || !preg_match('/^[a-f0-9]{64}\.(?:png|jpg)$/D', substr($ref, strlen($prefix)))
            || !function_exists('sanro_studio_validate_master_bytes')) return false;
        $dir=realpath(SANRO_STUDIO_PRIVATE_MASTER_DIR); $root=defined('ABSPATH')?realpath(ABSPATH):false;
        $web=isset($_SERVER['DOCUMENT_ROOT'])&&is_string($_SERVER['DOCUMENT_ROOT'])?realpath($_SERVER['DOCUMENT_ROOT']):false;
        if (!$dir || !$root || !$web || is_link(SANRO_STUDIO_PRIVATE_MASTER_DIR)
            || $dir===$root || $dir===$web || strpos($dir,$root.DIRECTORY_SEPARATOR)===0
            || strpos($dir,$web.DIRECTORY_SEPARATOR)===0) return false;
        $path=$dir.DIRECTORY_SEPARATOR.$ref;
        if (is_link($path) || !is_file($path) || !is_readable($path)) return false;
        $size=@filesize($path); if (!is_int($size) || $size<100 || $size>10485760) return false;
        $bytes=@file_get_contents($path); if (!is_string($bytes) || strlen($bytes)!==$size) return false;
        $checked=sanro_studio_validate_master_bytes($bytes);
        $expected_ext=($checked['mime']??null)==='image/png'?'png':(($checked['mime']??null)==='image/jpeg'?'jpg':null);
        if (empty($checked['valid']) || $expected_ext===null
            || !hash_equals(substr($ref,strlen($prefix),64),$checked['sha256'])
            || substr($ref,-strlen($expected_ext)-1)!=='.'.$expected_ext) return false;
    }
    return true;
}

/** Fresh server resolver required by the PR #51 WooCommerce pricing adapter. */
function sanro_studio_pricing_authorized_design($default, $design_id, $product_id, $context) {
    if (!function_exists('get_option') || !is_int($product_id) || $product_id < 1
        || !is_string($design_id) || !in_array($context, ['add_to_cart', 'cart'], true)) return null;
    $session = sanro_studio_pricing_session_hash();
    $record = get_option('sanro_studio_draft_' . $design_id, false);
    if (!is_string($session) || !is_array($record) || ($record['status'] ?? null) !== 'draft'
        || ($record['product_id'] ?? null) !== $product_id
        || !is_int($record['expires_at'] ?? null) || $record['expires_at'] <= time()
        || !is_string($record['wc_session_hash'] ?? null)
        || !hash_equals($record['wc_session_hash'], $session)
        || !is_array($record['design'] ?? null)
        || empty(sanro_studio_validate_product_design($product_id, $record['design'])['valid'])
        || !sanro_studio_draft_image_binding_allowed(
            $record['design'], is_array($record['master_refs'] ?? null) ? $record['master_refs'] : [], $design_id
        ) || !sanro_studio_pricing_masters_ready(
            $design_id, $record['design'], is_array($record['master_refs'] ?? null) ? $record['master_refs'] : []
        )) return null;
    return ['authorized'=>true, 'master_ready'=>true, 'design_id'=>$design_id,
        'product_id'=>$product_id, 'design'=>$record['design']];
}

function sanro_studio_pricing_wc_cart() {
    $wc = function_exists('WC') ? WC() : null;
    return is_object($wc) && isset($wc->cart) ? $wc->cart : null;
}

/** Authenticated editor-to-native-cart transaction for a simple product. */
function sanro_studio_rest_pricing_draft_cart($request) {
    if (!sanro_studio_pricing_wc_enabled() || !sanro_studio_draft_rest_enabled()
        || !function_exists('wc_get_product') || !function_exists('WC')) {
        return new WP_Error('sanro_cart_disabled', 'Studio cart is unavailable.', ['status'=>403]);
    }
    if (!sanro_studio_stage_classic_checkout_ready()) {
        return new WP_Error('sanro_checkout_unavailable', 'Classic checkout is unavailable.', ['status'=>503]);
    }
    $id = $request->get_param('id');
    $token = $request->get_header('X-Sanro-Owner-Token');
    $draft = sanro_studio_read_draft($id, $token);
    if (empty($draft['valid']) || ($draft['status'] ?? null) !== 'draft') {
        return new WP_Error('sanro_cart_unauthorized', 'Draft authorization failed.', ['status'=>403]);
    }
    $product = wc_get_product($draft['product_id']);
    if (!$product || !method_exists($product, 'is_type') || !$product->is_type('simple')
        || !method_exists($product, 'is_purchasable') || !$product->is_purchasable()) {
        return new WP_Error('sanro_product_unavailable', 'Only purchasable simple products are supported.', ['status'=>409]);
    }
    $cart = sanro_studio_stage_rest_cart();
    $session = sanro_studio_pricing_session_hash();
    if (!is_object($cart) || !method_exists($cart, 'get_cart') || !method_exists($cart, 'add_to_cart')
        || !method_exists($cart, 'calculate_totals') || !is_string($session)
        || !function_exists('sanro_studio_stage_rest_session_ready')
        || !sanro_studio_stage_rest_session_ready()) {
        return new WP_Error('sanro_cart_unavailable', 'Cart session unavailable.', ['status'=>503]);
    }
    $existing = $cart->get_cart();
    if (!is_array($existing) || $existing) return new WP_Error('sanro_cart_not_empty', 'Empty the cart before adding this design.', ['status'=>409]);
    $contract = sanro_studio_prepare_cart_design_contract($id, $token, $draft['product_id']);
    if (empty($contract['valid']) || !sanro_studio_pricing_claim_draft_session($draft, $session)) {
        return new WP_Error('sanro_cart_not_ready', 'Save and verify the design before adding it.', ['status'=>409]);
    }
    $prepared = sanro_studio_prepare_size_cart_lines($contract);
    if (empty($prepared['valid'])) return new WP_Error('sanro_cart_quote', 'Server quote failed.', ['status'=>409]);
    $keys = [];
    try {
        foreach ($prepared['lines'] as $candidate) {
            $meta = ['sanro_studio_pricing_version'=>1,
                'sanro_studio_design_id'=>$id, 'sanro_studio_size'=>$candidate['sanro_studio_size']];
            $key = $cart->add_to_cart($draft['product_id'], $candidate['quantity'], 0, [], $meta);
            if (!is_string($key) || $key === '') throw new RuntimeException('cart_add_failed');
            $keys[] = $key;
        }
        $cart->calculate_totals();
        $now = $cart->get_cart();
        if (!is_array($now) || count($now) !== count($keys)) throw new RuntimeException('cart_mismatch');
        foreach ($keys as $key) if (!isset($now[$key])) throw new RuntimeException('cart_mismatch');
    } catch (Throwable $error) {
        foreach ($keys as $key) { try { $cart->remove_cart_item($key); } catch (Throwable $ignored) {} }
        return new WP_Error('sanro_cart_failed', 'Could not add the verified design to the cart.', ['status'=>409]);
    }
    if (function_exists('nocache_headers')) nocache_headers();
    return rest_ensure_response(['valid'=>true, 'design_id'=>$id,
        'cart_url'=>function_exists('wc_get_cart_url') ? wc_get_cart_url() : '']);
}

/** Hook registrations are gated by the independent adapter flag, default OFF. */
function sanro_studio_register_pricing_integration_hooks() {
    if (!sanro_studio_pricing_wc_enabled()) return;
    add_filter('sanro_studio_pricing_authorized_design', 'sanro_studio_pricing_authorized_design', 10, 4);
    add_action('woocommerce_review_order_before_submit', 'sanro_studio_pricing_checkout_field', 5);
    add_action('wp_enqueue_scripts', 'sanro_studio_pricing_enqueue_checkout_auth', 30);
    add_action('woocommerce_checkout_process', 'sanro_studio_pricing_checkout_authorize', 5);
    add_action('woocommerce_checkout_create_order_line_item', 'sanro_studio_pricing_order_item', 10, 4);
    add_action('woocommerce_checkout_create_order', 'sanro_studio_pricing_validate_order', 10, 2);
    add_action('woocommerce_checkout_order_created', 'sanro_studio_pricing_lock_order', 10, 1);
}

function sanro_studio_pricing_checkout_field() {
    $items = (array) (sanro_studio_pricing_wc_cart() ? sanro_studio_pricing_wc_cart()->get_cart() : []);
    foreach ($items as $line) if (sanro_studio_pricing_is_line($line)) {
        $id = $line['sanro_studio_design_id'] ?? '';
        $product = $line['product_id'] ?? 0;
        echo '<input type="hidden" id="sanro_studio_checkout_token" name="sanro_studio_checkout_token" value="" data-design-id="' . esc_attr($id) . '" data-product-id="' . esc_attr((string)$product) . '">';
        wp_nonce_field('sanro_studio_checkout_auth', 'sanro_studio_checkout_nonce');
        return;
    }
}

function sanro_studio_pricing_enqueue_checkout_auth() {
    if (function_exists('is_checkout') && is_checkout() && !(function_exists('is_order_received_page') && is_order_received_page())) {
        wp_enqueue_script('sanro-studio-checkout-auth', plugin_dir_url(__DIR__ . '/../sanro-studio.php') . 'assets/checkout-auth.js', ['jquery','wc-checkout'], SANRO_STUDIO_VERSION, true);
    }
}

function sanro_studio_pricing_checkout_authorize() {
    unset($GLOBALS['sanro_studio_pricing_checkout']);
    $cart = sanro_studio_pricing_wc_cart();
    if (!is_object($cart) || !method_exists($cart, 'get_cart')) return;
    $lines = array_values(array_filter((array)$cart->get_cart(), 'sanro_studio_pricing_is_line'));
    if (!$lines) return;
    if (count($lines) !== count((array)$cart->get_cart()) || !function_exists('wp_verify_nonce')
        || !isset($_POST['sanro_studio_checkout_nonce'], $_POST['sanro_studio_checkout_token'])
        || !is_string($_POST['sanro_studio_checkout_nonce']) || !is_string($_POST['sanro_studio_checkout_token'])
        || !wp_verify_nonce($_POST['sanro_studio_checkout_nonce'], 'sanro_studio_checkout_auth')) {
        wc_add_notice('SANRO Studio: reopen your design session before checkout.', 'error'); return;
    }
    $id = $lines[0]['sanro_studio_design_id'] ?? null;
    $product = $lines[0]['product_id'] ?? null;
    $token = $_POST['sanro_studio_checkout_token'];
    if (!is_string($id) || !is_int($product) || !preg_match('/^[a-f0-9]{64}$/D', $token)
        || !sanro_studio_read_draft($id, $token)['valid']) {
        wc_add_notice('SANRO Studio: design authorization failed.', 'error'); return;
    }
    foreach ($lines as $line) if (($line['sanro_studio_design_id'] ?? null) !== $id || ($line['product_id'] ?? null) !== $product) {
        wc_add_notice('SANRO Studio: mixed designs are not supported.', 'error'); return;
    }
    $draft = sanro_studio_read_draft($id, $token);
    $design = $draft['design']; $design['quantities'] = [];
    foreach ($lines as $line) $design['quantities'][$line['sanro_studio_size']] = (int)$line['quantity'];
    $contract = sanro_studio_prepare_cart_design_contract($id, $token, $product);
    if (empty($contract['valid'])) { wc_add_notice('SANRO Studio: draft is no longer ready.', 'error'); return; }
    $contract['design'] = $design;
    $contract['quote'] = sanro_studio_quote_size_quantities($product, $design, $design['quantities'], $design['long_sleeve'] ?? false);
    if (empty($contract['quote']['valid'])) { wc_add_notice('SANRO Studio: fresh quote failed.', 'error'); return; }
    if (defined('SANRO_STUDIO_ENABLE_PRIVATE_PREVIEWS') && SANRO_STUDIO_ENABLE_PRIVATE_PREVIEWS === true) {
        $record = get_option('sanro_studio_draft_' . $id, false);
        $refs = is_array($record) ? ($record['preview_refs'] ?? null) : null;
        $preview_hash = is_array($record) ? ($record['preview_design_hash'] ?? null) : null;
        if (!is_array($refs) || !is_string($preview_hash) || !hash_equals($draft['design_hash'], $preview_hash)
            || !function_exists('sanro_studio_read_owned_draft_preview')) {
            wc_add_notice('SANRO Studio: refresh both private previews before checkout.', 'error'); return;
        }
        foreach (['front','back'] as $side) {
            $ref = $refs[$side] ?? null;
            if (!is_string($ref) || empty(sanro_studio_read_owned_draft_preview($id,$token,$side,$ref)['valid'])) {
                wc_add_notice('SANRO Studio: private preview authorization failed.', 'error'); return;
            }
        }
    }
    $GLOBALS['sanro_studio_pricing_checkout'] = ['id'=>$id,'token'=>$token,'draft'=>$draft,'contract'=>$contract,'sizes'=>[]];
}

function sanro_studio_pricing_order_item($item, $cart_item_key, $values, $order) {
    $verified = $GLOBALS['sanro_studio_pricing_checkout'] ?? null;
    if (!sanro_studio_pricing_is_line($values)) return;
    if (!is_array($verified) || empty($verified['contract']['quote']['valid']) || !method_exists($item,'update_meta_data')) {
        throw new RuntimeException('SANRO Studio: checkout authorization missing.');
    }
    $size = $values['sanro_studio_size'] ?? null;
    $candidate = $verified['contract']['quote']['items'][$size] ?? null;
    if (!is_array($candidate) || ($verified['id'] ?? null) !== ($values['sanro_studio_design_id'] ?? null)
        || ($candidate['quantity'] ?? null) !== ($values['quantity'] ?? null)) throw new RuntimeException('SANRO Studio: order line mismatch.');
    foreach (['_sanro_studio_design_id'=>$verified['id'], '_sanro_studio_size'=>$size,
        '_sanro_studio_unit_price'=>$candidate['unit_price'], '_sanro_studio_quote_currency'=>'IDR'] as $key=>$value) $item->update_meta_data($key,$value);
    $verified['sizes'][$size] = true; $GLOBALS['sanro_studio_pricing_checkout'] = $verified;
}

function sanro_studio_pricing_validate_order($order, $data) {
    $verified = $GLOBALS['sanro_studio_pricing_checkout'] ?? null;
    if (!is_array($verified)) {
        foreach ((array)$order->get_items('line_item') as $item) if (method_exists($item,'get_meta') && $item->get_meta('_sanro_studio_design_id',true) !== '') throw new RuntimeException('SANRO Studio: verified checkout missing.');
        return;
    }
    if (!is_object($order) || !method_exists($order,'get_currency') || $order->get_currency() !== 'IDR'
        || !method_exists($order,'get_items') || count((array)$order->get_items('line_item')) !== count($verified['sizes'])) {
        throw new RuntimeException('SANRO Studio: invalid order allocation.');
    }
    $verified['order_id'] = 0; $GLOBALS['sanro_studio_pricing_checkout'] = $verified;
}

function sanro_studio_pricing_lock_order($order) {
    $verified = $GLOBALS['sanro_studio_pricing_checkout'] ?? null;
    if (!is_array($verified) || !is_object($order) || !method_exists($order,'get_id') || !method_exists($order,'get_items')) return;
    if (!function_exists('sanro_studio_checkout_batch_lock_preflight')
        || empty(sanro_studio_checkout_batch_lock_preflight($order, $verified['contract'])['valid'])) {
        throw new RuntimeException('SANRO Studio: persisted order quote could not be verified.');
    }
    $ids = array_map('intval', array_keys((array)$order->get_items('line_item'))); sort($ids, SORT_NUMERIC);
    $key = 'sanro_studio_draft_' . $verified['id']; $record = get_option($key, false);
    $session = sanro_studio_pricing_session_hash();
    if (!is_array($record) || ($record['status'] ?? null) !== 'draft' || ($record['wc_session_hash'] ?? null) !== $session
        || ($record['product_id'] ?? null) !== ($verified['contract']['product_id'] ?? null)
        || !is_int($record['expires_at'] ?? null) || $record['expires_at'] <= time()
        || !is_string($record['token_hash'] ?? null)
        || !hash_equals($record['token_hash'], hash('sha256', $verified['token']))
        || !hash_equals($verified['draft']['design_hash'], hash('sha256', maybe_serialize($record['design'] ?? null)))) {
        throw new RuntimeException('SANRO Studio: order draft lock failed.');
    }
    global $wpdb; $after=$record; $after['status']='locked'; $after['order_id']=(int)$order->get_id(); $after['order_item_ids']=$ids; $after['locked_at']=time();
    $changed=$wpdb->query($wpdb->prepare("UPDATE `{$wpdb->options}` SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s", maybe_serialize($after),$key,maybe_serialize($record)));
    wp_cache_delete($key,'options');
    if ($changed !== 1) throw new RuntimeException('SANRO Studio: draft could not be locked to order.');
    if (defined('SANRO_STUDIO_ENABLE_PRIVATE_PREVIEWS') && SANRO_STUDIO_ENABLE_PRIVATE_PREVIEWS === true) {
        $bound = sanro_studio_checkout_bind_existing_item_previews($order,$verified['id'],$verified['token'],$ids);
        if (empty($bound['valid'])) throw new RuntimeException('SANRO Studio: private previews were not bound.');
    }
    unset($GLOBALS['sanro_studio_pricing_checkout']);
}
