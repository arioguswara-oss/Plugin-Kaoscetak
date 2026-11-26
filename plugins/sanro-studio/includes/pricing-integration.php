<?php
defined('ABSPATH') || exit;
require_once __DIR__.'/checkout-recovery.php';

/** Current opaque WC customer session, used only as a server-side draft binding. */
function sanro_studio_pricing_session_hash() {
    $wc = function_exists('WC') ? WC() : null;
    if (!is_object($wc) || !isset($wc->session) || !is_object($wc->session)
        || !method_exists($wc->session, 'get_customer_id') || !function_exists('wp_salt')) return null;
    $id = $wc->session->get_customer_id();
    $salt = wp_salt('auth');
    if(!is_string($salt)||strlen($salt)<16)return null;
    // Woo uses the authenticated WordPress user ID (often a short numeric string), not a guest secret.
    $user=function_exists('get_current_user_id')?get_current_user_id():0;
    if(is_int($user)&&$user>0)return (is_int($id)||is_string($id))&&(string)$id===(string)$user?hash_hmac('sha256','user:'.$user,$salt):null;
    // Preserve existing opaque guest bindings; never accept a short unauthenticated numeric ID.
    return is_string($id)&&strlen($id)>=16&&strlen($id)<=128?hash_hmac('sha256',$id,$salt):null;
}

/** Claim a draft for the cart session after its owner token has been checked. */
function sanro_studio_pricing_claim_draft_session($draft, $session_hash, $owner_token = null) {
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
    if(is_string($bound)){
        if(hash_equals($bound,$session_hash))return true;
        // Explicit owner-authorized reopen may migrate a still-unlocked design after guest login.
        if(!is_string($owner_token)||!preg_match('/^[a-f0-9]{64}$/D',$owner_token)||!hash_equals($before['token_hash'],hash('sha256',$owner_token)))return false;
    }
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
    $recovery=is_array($record)&&($record['status']??null)==='locked'?sanro_studio_pricing_recoverable_order($record):null;
    if (!is_string($session) || !is_array($record) || (($record['status'] ?? null) !== 'draft' && !$recovery)
        || ($record['product_id'] ?? null) !== $product_id
        || !is_int($record['expires_at'] ?? null) || $record['expires_at'] <= time()
        || !is_string($record['wc_session_hash'] ?? null)
        || !hash_equals($record['wc_session_hash'], $session)
        || !is_array($record['design'] ?? null)
        || empty(sanro_studio_validate_product_design($product_id,$record['design'],
            $recovery ? ($record['checkout_quote']['print_limits'] ?? sanro_studio_print_categories()) : null)['valid'])
        || !sanro_studio_draft_image_binding_allowed(
            $record['design'], is_array($record['master_refs'] ?? null) ? $record['master_refs'] : [], $design_id
        ) || !sanro_studio_pricing_masters_ready(
            $design_id, $record['design'], is_array($record['master_refs'] ?? null) ? $record['master_refs'] : []
        )) return null;
    return ['authorized'=>true, 'master_ready'=>true, 'design_id'=>$design_id,
        'product_id'=>$product_id, 'design'=>$record['design'],
        'locked_quote'=>$recovery?$record['checkout_quote']:null];
}

function sanro_studio_pricing_wc_cart() {
    $wc = function_exists('WC') ? WC() : null;
    return is_object($wc) && isset($wc->cart) ? $wc->cart : null;
}

/** Current revision-bound previews are required before a Studio cart commit. */
function sanro_studio_pricing_preview_bundle($draft, $token) {
    if (!defined('SANRO_STUDIO_ENABLE_PRIVATE_PREVIEWS') || SANRO_STUDIO_ENABLE_PRIVATE_PREVIEWS !== true) {
        return ['valid'=>true, 'refs'=>[]];
    }
    $record = get_option('sanro_studio_draft_' . $draft['design_id'], false);
    if (!is_array($record) || !is_array($record['preview_refs'] ?? null)
        || !is_string($record['preview_design_hash'] ?? null)
        || !hash_equals($draft['design_hash'], $record['preview_design_hash'])
        || !function_exists('sanro_studio_read_owned_draft_preview')) return ['valid'=>false];
    $refs = [];
    foreach (['front','back'] as $side) {
        $ref = $record['preview_refs'][$side] ?? null;
        if (!is_string($ref) || empty(sanro_studio_read_owned_draft_preview(
            $draft['design_id'], $token, $side, $ref
        )['valid'])) return ['valid'=>false];
        $refs[$side] = $ref;
    }
    return ['valid'=>true, 'refs'=>$refs];
}

/** Validate one design allocation against its fresh server contract, including replays. */
function sanro_studio_pricing_verify_cart($cart, $contract, $check_price = true) {
    $expected = sanro_studio_prepare_size_cart_lines($contract);
    $items = $cart->get_cart();
    if (empty($expected['valid']) || !is_array($items)) return false;
    $items = array_filter($items, static function ($line) use ($contract) {
        return ($line['sanro_studio_design_id'] ?? null) === $contract['design_id'];
    });
    if (count($items) !== count($expected['lines'])) return false;
    $sizes = [];
    foreach ($expected['lines'] as $line) $sizes[$line['sanro_studio_size']] = $line;
    foreach ($items as $line) {
        $size = $line['sanro_studio_size'] ?? null;
        if (!empty($line['sanro_studio_replacement_pending']) || !sanro_studio_pricing_is_line($line) || !is_string($size) || !isset($sizes[$size])
            || ($line['product_id'] ?? null) !== $contract['product_id']
            || ($line['sanro_studio_design_id'] ?? null) !== $contract['design_id']
            || ($line['variation_id']??0)!==$sizes[$size]['variation_id']
            || ($line['variation']??[])!==$sizes[$size]['variation']
            || ($line['sanro_studio_color']??'')!==($contract['quote']['shirt_color']??'')
            || sanro_studio_pricing_quantity($line['quantity'] ?? null) !== $sizes[$size]['quantity']) return false;
        if ($check_price) {
            $product = $line['data'] ?? null;
            $price = is_object($product) && method_exists($product,'get_price') ? $product->get_price() : null;
            if (!is_numeric($price) || !is_finite((float)$price)
                || (float)$price !== (float)$sizes[$size]['sanro_studio_unit_price']) return false;
        }
        unset($sizes[$size]);
    }
    return !$sizes;
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
    if (!$product || !method_exists($product, 'is_type') || (!$product->is_type('simple')&&!$product->is_type('variable'))
        || !method_exists($product, 'is_purchasable') || !$product->is_purchasable()) {
        return new WP_Error('sanro_product_unavailable', 'The mapped product is unavailable.', ['status'=>409]);
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
    if (!is_array($existing)) return new WP_Error('sanro_cart_unavailable', 'Cart session unavailable.', ['status'=>503]);
    $contract = sanro_studio_prepare_cart_design_contract($id, $token, $draft['product_id']);
    if (empty($contract['valid']) || empty(sanro_studio_pricing_preview_bundle($draft, $token)['valid'])) {
        return new WP_Error('sanro_cart_not_ready', 'Save and verify both design previews before adding it.', ['status'=>409]);
    }
    if (!sanro_studio_pricing_claim_draft_session($draft, $session, $token)) {
        return new WP_Error('sanro_cart_not_ready', 'Save and verify the design before adding it.', ['status'=>409]);
    }
    $existing_designs = [];
    foreach ($existing as $line) {
        if (!sanro_studio_pricing_is_line($line) || !is_string($line['sanro_studio_design_id'] ?? null)) {
            return new WP_Error('sanro_cart_not_empty', 'Review your existing cart before adding this design.', ['status'=>409]);
        }
        $existing_designs[$line['sanro_studio_design_id']] = true;
    }
    $replacing = isset($existing_designs[$id]);
    if ($replacing && sanro_studio_pricing_verify_cart($cart,$contract,false)) {
        try {
            sanro_studio_pricing_cart_plan($cart);
            $cart->calculate_totals();
            if (!sanro_studio_pricing_verify_cart($cart,$contract)) throw new RuntimeException('cart_mismatch');
        } catch (Throwable $error) { return new WP_Error('sanro_cart_failed','Server price verification failed.',['status'=>409]); }
        return rest_ensure_response(['valid'=>true,'already_in_cart'=>true,'design_id'=>$id,
            'cart_url'=>function_exists('wc_get_cart_url')?wc_get_cart_url():'']);
    }
    if (!$replacing && count($existing_designs) >= 10) {
        return new WP_Error('sanro_cart_design_limit', 'Maksimal 10 desain dalam satu keranjang.', ['status'=>409]);
    }
    foreach ($existing_designs as $existing_id => $_) {
        $record = get_option('sanro_studio_draft_' . $existing_id, false);
        if (!is_array($record) || ($record['status'] ?? null) !== 'draft') {
            return new WP_Error('sanro_cart_order_pending', 'Selesaikan pesanan yang sedang diproses atau tinjau keranjang.', ['status'=>409]);
        }
    }
    $prepared = sanro_studio_prepare_size_cart_lines($contract);
    if (empty($prepared['valid'])) return new WP_Error('sanro_cart_quote', 'Server quote failed.', ['status'=>409]);
    $keys = [];
    $retained = $existing;
    if ($replacing) {
        if (!method_exists($cart,'set_cart_contents')) return new WP_Error('sanro_cart_unavailable','Cart replacement unavailable.',['status'=>503]);
        foreach ($retained as $key => $line) {
            if (($line['sanro_studio_design_id'] ?? null) === $id) unset($retained[$key]);
        }
    }
    try {
        if ($replacing) $cart->set_cart_contents($retained);
        foreach ($prepared['lines'] as $candidate) {
            $meta = ['sanro_studio_pricing_version'=>1,
                'sanro_studio_design_id'=>$id, 'sanro_studio_size'=>$candidate['sanro_studio_size'],
                'sanro_studio_color'=>$draft['design']['shirt_color']??''];
            $key = $cart->add_to_cart($draft['product_id'], $candidate['quantity'], $candidate['variation_id'], $candidate['variation'], $meta);
            if (!is_string($key) || $key === '') throw new RuntimeException('cart_add_failed');
            $keys[] = $key;
        }
        sanro_studio_pricing_cart_plan($cart);
        $cart->calculate_totals();
        $now = $cart->get_cart();
        if (!is_array($now) || count($now) !== count($retained) + count($keys)) throw new RuntimeException('cart_mismatch');
        foreach ($keys as $key) if (!isset($now[$key])) throw new RuntimeException('cart_mismatch');
        if (!sanro_studio_pricing_verify_cart($cart,$contract)) throw new RuntimeException('cart_quote_mismatch');
    } catch (Throwable $error) {
        if ($replacing) {
            try {
                // The draft may already contain the new revision. Preserve old rows
                // for recovery, but never treat them as an accepted checkout allocation.
                foreach ($existing as &$old_line) {
                    if (($old_line['sanro_studio_design_id'] ?? null) === $id) $old_line['sanro_studio_replacement_pending'] = true;
                }
                unset($old_line);
                $cart->set_cart_contents($existing);
                $cart->calculate_totals();
            } catch (Throwable $ignored) {
                return new WP_Error('sanro_cart_rollback_failed','Cart recovery required before checkout.',['status'=>503]);
            }
            return new WP_Error('sanro_cart_failed','Could not replace the verified design.',['status'=>409]);
        }
        foreach ($keys as $key) {
            try { if ($cart->remove_cart_item($key) !== true) throw new RuntimeException('rollback'); }
            catch (Throwable $ignored) { return new WP_Error('sanro_cart_rollback_failed','Cart recovery required before checkout.',['status'=>503]); }
        }
        return new WP_Error('sanro_cart_failed', 'Could not add the verified design to the cart.', ['status'=>409]);
    }
    if (function_exists('nocache_headers')) nocache_headers();
    return rest_ensure_response(['valid'=>true, 'design_id'=>$id,
        'cart_url'=>function_exists('wc_get_cart_url') ? wc_get_cart_url() : '']);
}

/** Hook registrations are gated by the independent adapter flag, default OFF. */
function sanro_studio_register_pricing_integration_hooks() {
    if (!sanro_studio_pricing_wc_enabled()) return;
    add_filter('woocommerce_cart_item_price','sanro_studio_pricing_display_line',20,3);
    add_filter('woocommerce_cart_item_subtotal','sanro_studio_pricing_display_line',20,3);
    add_filter('woocommerce_cart_subtotal','sanro_studio_pricing_display_total',20);
    add_filter('woocommerce_cart_totals_order_total_html','sanro_studio_pricing_display_total',20);
    add_filter('woocommerce_get_item_data','sanro_studio_pricing_cart_item_data',20,2);
    add_action('woocommerce_before_cart_table','sanro_studio_pricing_group_summary',10);
    add_action('woocommerce_after_cart_table','sanro_studio_pricing_continue_designing',10);
    add_action('woocommerce_checkout_before_order_review','sanro_studio_pricing_group_summary',10);
    add_filter('woocommerce_checkout_cart_item_quantity', 'sanro_studio_pricing_checkout_thumbnail', 20, 2);
    add_filter('woocommerce_cart_item_thumbnail', 'sanro_studio_pricing_cart_thumbnail', 20, 3);
    add_action('wp_ajax_sanro_studio_cart_preview', 'sanro_studio_pricing_deliver_cart_preview');
    add_action('wp_ajax_nopriv_sanro_studio_cart_preview', 'sanro_studio_pricing_deliver_cart_preview');
    add_filter('sanro_studio_pricing_authorized_design', 'sanro_studio_pricing_authorized_design', 10, 4);
    add_action('woocommerce_review_order_before_submit', 'sanro_studio_pricing_checkout_field', 5);
    add_action('wp_enqueue_scripts', 'sanro_studio_pricing_enqueue_checkout_auth', 30);
    add_action('woocommerce_checkout_process', 'sanro_studio_pricing_checkout_authorize', 5);
    add_action('woocommerce_checkout_create_order_line_item', 'sanro_studio_pricing_order_item', 10, 4);
    add_action('woocommerce_checkout_create_order', 'sanro_studio_pricing_validate_order', 10, 2);
    add_action('woocommerce_checkout_order_created', 'sanro_studio_pricing_lock_order', 10, 1);
    add_action('woocommerce_before_pay_action','sanro_studio_pricing_before_pay_order',10,1);
    add_action('woocommerce_pre_payment_complete','sanro_studio_pricing_before_payment_complete',5,1);
}

/** Only the current Woo session may retrieve a current cart design preview. */
function sanro_studio_pricing_cart_preview($line) {
    if (!is_array($line) || ($line['sanro_studio_pricing_version'] ?? null) !== 1) return null;
    $id = $line['sanro_studio_design_id'] ?? null;
    if (!is_string($id) || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $id)) return null;
    $session = sanro_studio_pricing_session_hash();
    $record = get_option('sanro_studio_draft_' . $id, false);
    if (!is_string($session) || !is_array($record)
        || ($record['status'] ?? null) !== 'draft'
        || ($record['product_id'] ?? null) !== ($line['product_id'] ?? null)
        || !is_int($record['expires_at'] ?? null) || $record['expires_at'] <= time()
        || !is_string($record['wc_session_hash'] ?? null)
        || !hash_equals($record['wc_session_hash'], $session)
        || !is_array($record['design'] ?? null)
        || !is_string($record['preview_design_hash'] ?? null)
        || !hash_equals($record['preview_design_hash'], hash('sha256', maybe_serialize($record['design'])))) return null;
    foreach (['front', 'back'] as $side) {
        if (empty($record['design'][$side]['objects'])) continue;
        $ref = $record['preview_refs'][$side] ?? null;
        if (!is_string($ref)) continue;
        $result = sanro_studio_read_verified_preview_file($id, $side, $ref);
        if (!empty($result['valid'])) return $result;
    }
    return null;
}

/** Use a same-origin, session-authorized, non-cacheable image response. */
function sanro_studio_pricing_deliver_cart_preview() {
    $id = $_GET['design'] ?? null;
    // admin-ajax may not initialize the frontend WooCommerce session/cart.
    if (function_exists('wc_load_cart') && !sanro_studio_pricing_wc_cart()) wc_load_cart();
    $cart = sanro_studio_pricing_wc_cart();
    nocache_headers();
    header('Cache-Control: private, no-store, max-age=0', true);
    header('X-Content-Type-Options: nosniff');
    if (is_string($id) && is_object($cart) && method_exists($cart, 'get_cart')) {
        foreach ($cart->get_cart() as $line) {
            if (($line['sanro_studio_design_id'] ?? null) !== $id) continue;
            $preview = sanro_studio_pricing_cart_preview($line);
            if ($preview && in_array($preview['mime'], ['image/png','image/jpeg','image/webp'], true)) {
                header('Content-Type: ' . $preview['mime']);
                echo $preview['bytes'];
                exit;
            }
        }
    }
    status_header(404);
    exit;
}

function sanro_studio_pricing_preview_markup($line, $width) {
    $preview = sanro_studio_pricing_cart_preview($line);
    if (!$preview) return '';
    $url = add_query_arg(['action'=>'sanro_studio_cart_preview','design'=>$line['sanro_studio_design_id']], admin_url('admin-ajax.php', 'relative'));
    return '<img class="sanro-studio-cart-preview" src="' . esc_url($url) . '" alt="' . esc_attr('Preview desain') . '" width="' . (int)$width . '" height="' . (int)$width . '" style="width:' . (int)$width . 'px;max-width:100%;height:auto;object-fit:contain;vertical-align:middle" />';
}

function sanro_studio_pricing_cart_thumbnail($thumbnail, $line, $key = '') {
    $width = function_exists('is_cart') && is_cart() ? 96 : 64;
    $preview = sanro_studio_pricing_preview_markup($line, $width);
    return $preview !== '' ? $preview : $thumbnail;
}

function sanro_studio_pricing_checkout_thumbnail($quantity_html, $line) {
    if (($line['sanro_studio_pricing_version'] ?? null) !== 1 || !(($line['data'] ?? null) instanceof WC_Product)) return $quantity_html;
    $thumbnail = sanro_studio_pricing_preview_markup($line, 112);
    if ($thumbnail === '') $thumbnail = $line['data']->get_image('woocommerce_thumbnail', [
        'width'=>112, 'height'=>112, 'style'=>'width:112px;max-width:100%;height:auto;vertical-align:middle',
    ]);
    return $quantity_html . '<span style="display:block;margin-top:0.5em">' . wp_kses_post($thumbnail) . '</span>';
}


/** Return to the configured Studio catalog, never a hard-coded production URL. */
function sanro_studio_pricing_continue_designing() {
    $cart = sanro_studio_pricing_wc_cart();
    if (!is_object($cart) || !method_exists($cart,'get_cart')) return;
    $has_studio = false;
    foreach ($cart->get_cart() as $line) {
        if (sanro_studio_pricing_is_line($line)) { $has_studio = true; break; }
    }
    if (!$has_studio || !function_exists('sanro_studio_frontend_enabled') || !sanro_studio_frontend_enabled()) return;
    $page = (int) get_option('sanro_studio_editor_page_id',0);
    if (!$page || get_post_type($page) !== 'page' || get_post_status($page) !== 'publish') return;
    $url = get_permalink($page);
    if (!$url) return;
    echo '<p class="sanro-studio-continue"><a class="button" href="' . esc_url($url) . '">Lanjut Belanja</a></p>';
}

/** Read-only grouping: native WooCommerce rows, quantities and totals remain authoritative. */
function sanro_studio_pricing_summary_groups($lines) {
    $groups = [];
    foreach ($lines as $line) {
        if (!is_array($line) || ($line['sanro_studio_pricing_version'] ?? null) !== 1) continue;
        $id = $line['sanro_studio_design_id'] ?? '';
        $color = $line['sanro_studio_color'] ?? '';
        $product = $line['product_id'] ?? 0;
        $size = $line['sanro_studio_size'] ?? '';
        $qty = $line['quantity'] ?? 0;
        if (!is_string($id) || !preg_match('/^[a-f0-9-]{36}$/iD',$id)
            || !is_string($color) || !preg_match('/^#[a-f0-9]{6}$/D',$color)
            || !is_int($product) || $product < 1 || !is_string($size)
            || !in_array($size,['S','M','L','XL','2XL','3XL','4XL','5XL'],true)
            || !is_numeric($qty) || $qty < 1
            || !(($line['data'] ?? null) instanceof WC_Product)) continue;
        $key = $product . ':' . $id . ':' . $color;
        if (!isset($groups[$key])) $groups[$key] = ['line'=>$line,'sizes'=>[]];
        $groups[$key]['sizes'][] = ['size'=>$size,'qty'=>$qty,'product'=>$line['data'],'line'=>$line];
    }
    return $groups;
}

/** Compact grouped reference, without hiding native cart controls or financial totals. */
function sanro_studio_pricing_group_summary() {
    $cart = sanro_studio_pricing_wc_cart();
    if (!is_object($cart) || !method_exists($cart,'get_cart')
        || !method_exists($cart,'get_product_price') || !method_exists($cart,'get_product_subtotal')) return;
    $groups = sanro_studio_pricing_summary_groups($cart->get_cart());
    if (!$groups) return;
    $on_cart = function_exists('is_cart') && is_cart();
    echo '<details class="sanro-studio-group-summary" aria-label="Ringkasan desain"' . ($on_cart ? ' open' : '') . '><summary style="cursor:pointer;font-weight:600">Ringkasan desain (' . count($groups) . ')</summary>';
    foreach ($groups as $group) {
        $line = $group['line'];
        $palette = sanro_studio_mockup_palette($line['product_id']);
        $color = $palette[$line['sanro_studio_color']] ?? $line['sanro_studio_color'];
        echo '<div style="display:flex;align-items:flex-start;gap:12px;padding:12px 0;border-bottom:1px solid #ddd">';
        $image = sanro_studio_pricing_preview_markup($line,96);
        if ($image !== '') echo wp_kses_post($image);
        echo '<div style="min-width:0;flex:1"><strong>' . esc_html($line['data']->get_name()) . '</strong>';
        echo '<div style="overflow-wrap:anywhere">Design ID: ' . esc_html($line['sanro_studio_design_id']) . '</div>';
        echo '<div>Warna: ' . esc_html($color) . '</div>';
        if (function_exists('is_cart') && is_cart() && function_exists('sanro_studio_frontend_enabled')
            && sanro_studio_frontend_enabled() && sanro_studio_pricing_cart_preview($line)) {
            $page = (int) get_option('sanro_studio_editor_page_id',0);
            if ($page && get_post_type($page) === 'page' && get_post_status($page) === 'publish' && get_permalink($page)) {
                $url = add_query_arg(['sanro_studio_editor'=>'1','product_id'=>$line['product_id'],
                    'sanro_design'=>$line['sanro_studio_design_id']],get_permalink($page));
                echo '<p><a class="button sanro-studio-edit-design" href="' . esc_url($url) . '">Edit Desain</a></p>';
            }
        }
        foreach ($group['sizes'] as $row) {
            $unit = sanro_studio_pricing_display_line($cart->get_product_price($row['product']),$row['line']);
            $subtotal = sanro_studio_pricing_display_line($cart->get_product_subtotal($row['product'],$row['qty']),$row['line']);
            echo '<div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:4px 12px"><span>'
                . esc_html($row['size'] . ' × ' . $row['qty']) . ' @ ' . wp_kses_post($unit)
                . '</span><span>' . wp_kses_post($subtotal) . '</span></div>';
        }
        echo '</div></div>';
    }
    echo '</details>';
}

/** Friendly cart/checkout labels; never display tokens, private refs or browser prices. */
function sanro_studio_pricing_cart_item_data($data,$line){
    if(!is_array($data)||!is_array($line))return $data;
    $id=$line['sanro_studio_design_id']??null;$size=$line['sanro_studio_size']??null;
    if(!is_array($data)||!is_array($line)||($line['sanro_studio_pricing_version']??null)!==1
        ||!is_string($id)||!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD',$id)
        ||!is_string($size)||!in_array($size,['S','M','L','XL','2XL','3XL','4XL','5XL'],true))return $data;
    $data[]=['key'=>'Ukuran','value'=>$size,'display'=>esc_html($size)];
    $data[]=['key'=>'Design ID','value'=>$id,'display'=>esc_html($id)];
    $color = $line['sanro_studio_color'] ?? '';
    if (is_string($color) && preg_match('/^#[a-f0-9]{6}$/D',$color)) {
        $palette = sanro_studio_mockup_palette((int) ($line['product_id'] ?? 0));
        $label = $palette[$color] ?? $color;
        $data[]=['key'=>'Warna','value'=>$label,'display'=>esc_html($label)];
    }
    return $data;
}

function sanro_studio_pricing_checkout_field() {
    $items = (array) (sanro_studio_pricing_wc_cart() ? sanro_studio_pricing_wc_cart()->get_cart() : []);
    $rendered = [];
    foreach ($items as $line) if (sanro_studio_pricing_is_line($line)) {
        $id = $line['sanro_studio_design_id'] ?? '';
        $product = $line['product_id'] ?? 0;
        if (isset($rendered[$id])) continue;
        $rendered[$id] = true;
        echo '<input type="hidden" name="sanro_studio_checkout_token[' . esc_attr($id) . ']" value="" data-sanro-checkout-token data-design-id="' . esc_attr($id) . '" data-product-id="' . esc_attr((string)$product) . '">';
    }
    if ($rendered) wp_nonce_field('sanro_studio_checkout_auth', 'sanro_studio_checkout_nonce');
}

function sanro_studio_pricing_enqueue_checkout_auth() {
    if (function_exists('is_checkout') && is_checkout() && !(function_exists('is_order_received_page') && is_order_received_page())) {
        wp_enqueue_script('sanro-studio-checkout-auth', plugin_dir_url(__DIR__ . '/../sanro-studio.php') . 'assets/checkout-auth.js', ['jquery','wc-checkout'], SANRO_STUDIO_VERSION . '-' . substr(hash_file('sha256', dirname(__DIR__) . '/assets/checkout-auth.js'), 0, 12), true);
    }
}

function sanro_studio_pricing_checkout_authorize() {
    unset($GLOBALS['sanro_studio_pricing_checkout']);
    $cart = sanro_studio_pricing_wc_cart();
    if (!is_object($cart) || !method_exists($cart, 'get_cart')) return;
    if(function_exists('sanro_studio_cart_policy_issues')&&sanro_studio_cart_policy_issues($cart))return;
    $lines = array_values(array_filter((array)$cart->get_cart(), 'sanro_studio_pricing_is_line'));
    if (!$lines) return;
    if (count($lines) !== count((array)$cart->get_cart()) || !function_exists('wp_verify_nonce')
        || !isset($_POST['sanro_studio_checkout_nonce'], $_POST['sanro_studio_checkout_token'])
        || !is_string($_POST['sanro_studio_checkout_nonce'])
        || !wp_verify_nonce($_POST['sanro_studio_checkout_nonce'], 'sanro_studio_checkout_auth')) {
        wc_add_notice('SANRO Studio: reopen your design session before checkout.', 'error'); return;
    }
    $groups = [];
    foreach ($lines as $line) {
        $id = $line['sanro_studio_design_id'] ?? null;
        if (!is_string($id)) { wc_add_notice('SANRO Studio: invalid design.', 'error'); return; }
        $groups[$id][] = $line;
    }
    $tokens = $_POST['sanro_studio_checkout_token'];
    // An open checkout tab from before this update may still send one token.
    if (is_string($tokens) && count($groups) === 1) $tokens = [array_key_first($groups) => $tokens];
    if (!is_array($tokens) || count($tokens) !== count($groups) || count($groups) > 10) {
        wc_add_notice('SANRO Studio: design authorization failed.', 'error'); return;
    }
    $verified = [];
    $resume_id = null;
    foreach ($groups as $id => $group) {
        $current = sanro_studio_pricing_checkout_design($id, $tokens[$id] ?? null, $group);
        if ($current === null) return;
        $existing_order_id = $current['existing_order_id'] ?? 0;
        if ($resume_id !== null && $resume_id !== $existing_order_id) {
            wc_add_notice('SANRO Studio: resume the original order or use a new cart.', 'error'); return;
        }
        $resume_id = $existing_order_id;
        $verified[$id] = $current;
    }
    if ($resume_id) {
        $original_ids = [];
        foreach (reset($verified)['existing_order']->get_items('line_item') as $item) {
            $original_ids[$item->get_meta('_sanro_studio_design_id',true)] = true;
        }
        if (count($original_ids) !== count($verified) || array_diff_key($original_ids,$verified)) {
            wc_add_notice('SANRO Studio: resume checkout with every original design.', 'error'); return;
        }
    }
    $GLOBALS['sanro_studio_pricing_checkout'] = count($verified) === 1 ? reset($verified) : ['designs'=>$verified];
}

function sanro_studio_pricing_checkout_design($id, $token, $lines) {
    $product = $lines[0]['product_id'] ?? null;
    if (!is_string($id) || !is_int($product) || !is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) {
        wc_add_notice('SANRO Studio: design authorization failed.', 'error'); return null;
    }
    foreach ($lines as $line) {
        if (!empty($line['sanro_studio_replacement_pending'])) {
            wc_add_notice('SANRO Studio: simpan ulang desain dan perbarui keranjang sebelum checkout.', 'error'); return null;
        }
    }
    foreach ($lines as $line) if (($line['product_id'] ?? null) !== $product) {
        wc_add_notice('SANRO Studio: inconsistent design product.', 'error'); return null;
    }
    $draft = sanro_studio_read_draft($id, $token);
    if (empty($draft['valid']) || ($draft['product_id'] ?? null) !== $product) {
        wc_add_notice('SANRO Studio: design authorization failed.', 'error'); return null;
    }
    $design = $draft['design']; $design['quantities'] = [];
    foreach ($lines as $line) {
        $size = $line['sanro_studio_size'] ?? null;
        $quantity = sanro_studio_pricing_quantity($line['quantity'] ?? null);
        if (!is_string($size) || isset($design['quantities'][$size]) || $quantity === null) {
            wc_add_notice('SANRO Studio: invalid size allocation.', 'error'); return null;
        }
        $design['quantities'][$size] = $quantity;
    }
    $record=get_option('sanro_studio_draft_'.$id,false);
    if (is_array($record)&&($record['status']??null)==='locked') {
        $record['design_id']=$id;$resume=sanro_studio_pricing_recoverable_order($record);
        $saved=$record['design']['quantities']??[];$current=$design['quantities'];ksort($saved);ksort($current);
        if (!$resume || $saved!==$current || empty(sanro_studio_pricing_preview_bundle($draft,$token)['valid'])) {
            wc_add_notice('SANRO Studio: resume the original unpaid order or create a new design.', 'error');return null;
        }
        $contract=['valid'=>true,'design_id'=>$id,'product_id'=>$product,'design'=>$record['design'],'quote'=>$record['checkout_quote'],'shipping_total'=>$record['shipping_total']??0];
        return ['id'=>$id,'token'=>$token,'draft'=>$draft,'contract'=>$contract,'sizes'=>[],'existing_order_id'=>$resume->get_id(),'existing_order'=>$resume];
    }
    $contract = sanro_studio_prepare_cart_design_contract($id, $token, $product);
    if (empty($contract['valid'])) { wc_add_notice('SANRO Studio: draft is no longer ready.', 'error'); return null; }
    $contract['design'] = $design;
    $contract['quote'] = sanro_studio_quote_size_quantities($product, $design, $design['quantities'], $design['long_sleeve'] ?? false);
    if(function_exists('sanro_studio_repeat_adjust_quote'))$contract['quote']=sanro_studio_repeat_adjust_quote($contract['quote'],$id);
    if (empty($contract['quote']['valid'])) { wc_add_notice('SANRO Studio: fresh quote failed.', 'error'); return null; }
    if (empty(sanro_studio_pricing_preview_bundle($draft,$token)['valid'])) {
        wc_add_notice('SANRO Studio: refresh both private previews before checkout.', 'error'); return null;
    }
    return ['id'=>$id,'token'=>$token,'draft'=>$draft,'contract'=>$contract,'sizes'=>[]];
}

/** Whitelist immutable design metadata; never copy owner credentials/file refs. */
function sanro_studio_pricing_design_snapshot($verified) {
    $design = $verified['contract']['design'] ?? null;
    if (!is_array($design)) throw new RuntimeException('SANRO Studio: saved design missing.');
    $sides = [];
    foreach (['front','back'] as $side) {
        $entry = $design[$side] ?? [];
        $objects = [];
        foreach (($entry['objects'] ?? []) as $object) {
            $objects[] = array_intersect_key($object,array_flip(['type','x','y','width','height','rotation']));
        }
        $quoted_categories = $verified['contract']['quote']['categories'] ?? null;
        $category = is_array($quoted_categories) ? ($quoted_categories[$side] ?? null) : ($entry['category'] ?? null);
        $sides[$side] = ['category'=>$objects ? $category : null,
            'objects'=>$objects, 'text'=>$entry['text'] ?? '',
            'font_size'=>$entry['font_size'] ?? null,'text_color'=>$entry['text_color'] ?? null];
    }
    return ['version'=>1,'design_id'=>$verified['id'],
        'design_hash'=>hash('sha256',maybe_serialize($design)),
        'shirt_color'=>$design['shirt_color'] ?? '#ffffff',
        'long_sleeve'=>$design['long_sleeve'] ?? false,'sides'=>$sides];
}

function sanro_studio_pricing_order_item($item, $cart_item_key, $values, $order) {
    if (!sanro_studio_pricing_is_line($values)) return;
    $checkout = $GLOBALS['sanro_studio_pricing_checkout'] ?? null;
    $id = $values['sanro_studio_design_id'] ?? '';
    $multi = isset($checkout['designs']);
    $verified = $multi ? ($checkout['designs'][$id] ?? null) : $checkout;
    if (!is_array($verified) || empty($verified['contract']['quote']['valid']) || !method_exists($item,'update_meta_data')) {
        throw new RuntimeException('SANRO Studio: checkout authorization missing.');
    }
    $size = $values['sanro_studio_size'] ?? null;
    $candidate = $verified['contract']['quote']['items'][$size] ?? null;
    if (isset($verified['sizes'][$size]) || !is_array($candidate) || ($verified['id'] ?? null) !== ($values['sanro_studio_design_id'] ?? null)
        || ($candidate['quantity'] ?? null) !== sanro_studio_pricing_quantity($values['quantity'] ?? null)
        || ($values['variation_id']??0)!==($candidate['variation_id']??0)
        || ($values['variation']??[])!==($candidate['variation_attributes']??[])
        || (!empty($candidate['variation_id'])&&($values['sanro_studio_color']??null)!==($verified['contract']['quote']['shirt_color']??null))
        || (isset($values['sanro_studio_color'])&&$values['sanro_studio_color']!==($verified['contract']['quote']['shirt_color']??''))) throw new RuntimeException('SANRO Studio: order line mismatch.');
    $snapshot = sanro_studio_pricing_design_snapshot($verified);
    $snapshot['sleeve_label']=$verified['contract']['quote']['sleeve_label']??(!empty($snapshot['long_sleeve'])?'long':'short');
    foreach (['_sanro_studio_design_id'=>$verified['id'], '_sanro_studio_size'=>$size,
        '_sanro_studio_unit_price'=>$candidate['unit_price'], '_sanro_studio_quote_currency'=>'IDR',
        '_sanro_studio_variation_id'=>$candidate['variation_id']??0,
        '_sanro_studio_color'=>$verified['contract']['quote']['shirt_color']??'',
        '_sanro_studio_design_snapshot'=>$snapshot] as $key=>$value) $item->update_meta_data($key,$value);
    if(isset($verified['contract']['quote']['repeat_discount']))$item->update_meta_data('_sanro_studio_repeat_discount',['proof'=>$verified['contract']['quote']['repeat_discount'],'base_unit_price'=>$candidate['base_unit_price'],'unit_discount'=>$candidate['unit_discount']]);
    $verified['sizes'][$size] = true;
    if ($multi) $checkout['designs'][$id] = $verified;
    else $checkout = $verified;
    $GLOBALS['sanro_studio_pricing_checkout'] = $checkout;
}

function sanro_studio_pricing_validate_order($order, $data) {
    $verified = $GLOBALS['sanro_studio_pricing_checkout'] ?? null;
    if (!is_array($verified)) {
        foreach ((array)$order->get_items('line_item') as $item) if (method_exists($item,'get_meta') && $item->get_meta('_sanro_studio_design_id',true) !== '') throw new RuntimeException('SANRO Studio: verified checkout missing.');
        return;
    }
    $designs = $verified['designs'] ?? [$verified['id']=>$verified];
    if (!is_object($order) || !method_exists($order,'get_currency') || $order->get_currency() !== 'IDR'
        || !method_exists($order,'get_items')) {
        throw new RuntimeException('SANRO Studio: invalid order allocation.');
    }
    $expected = [];
    $subtotal = 0;
    $shipping = null;
    foreach ($designs as $id => $design) {
        $contract = $design['contract'];
        $prepared = sanro_studio_prepare_size_cart_lines($contract);
        if (empty($prepared['valid'])) throw new RuntimeException('SANRO Studio: order quote missing.');
        foreach ($prepared['lines'] as $line) $expected[$id][$line['sanro_studio_size']] = $line;
        $subtotal += $contract['quote']['total_price'];
        if (isset($design['existing_order_id'])) {
            if ($order->get_id() !== $design['existing_order_id']
                || ($shipping !== null && $shipping !== ($contract['shipping_total'] ?? 0))) {
                throw new RuntimeException('SANRO Studio: resume the original order.');
            }
            $shipping = $contract['shipping_total'] ?? 0;
        }
        if (function_exists('sanro_studio_repeat_saved_order_allowed')) {
            $record = get_option('sanro_studio_draft_' . $id, false);
            if (!is_array($record)) throw new RuntimeException('SANRO Studio: repeat draft unavailable.');
            $record['checkout_quote'] = $contract['quote'];
            if (!sanro_studio_repeat_saved_order_allowed($order,$record)) throw new RuntimeException('SANRO Studio: repeat eligibility or checkout email changed.');
        }
    }
    foreach ($order->get_items('line_item') as $item) {
        $size = is_object($item) && method_exists($item,'get_meta') ? $item->get_meta('_sanro_studio_size',true) : null;
        $id = is_string($size) ? $item->get_meta('_sanro_studio_design_id',true) : null;
        $line = is_string($id) && is_string($size) ? ($expected[$id][$size] ?? null) : null;
        if (!is_array($line)
            || $item->get_meta('_sanro_studio_quote_currency',true) !== 'IDR'
            || (string)$item->get_meta('_sanro_studio_unit_price',true) !== (string)$line['sanro_studio_unit_price']
            || ($item->get_meta('_sanro_studio_variation_id',true)!==''&&(int)$item->get_meta('_sanro_studio_variation_id',true) !== $line['variation_id'])
            || (!empty($line['variation_id'])&&(int)$item->get_meta('_sanro_studio_variation_id',true)!==$line['variation_id'])
            || ($item->get_meta('_sanro_studio_color',true)!==''&&(string)$item->get_meta('_sanro_studio_color',true) !== (string)$line['sanro_studio_color'])
            || empty(sanro_studio_validate_size_order_item($line,$item)['valid'])) {
            throw new RuntimeException('SANRO Studio: order metadata or totals mismatch.');
        }
        if (function_exists('sanro_studio_repeat_item_evidence')) {
            $repeat_expected=sanro_studio_repeat_item_evidence($designs[$id]['contract']['quote'],$size);
            $repeat_actual=$item->get_meta('_sanro_studio_repeat_discount',true);
            if($repeat_expected!==null?$repeat_actual!==$repeat_expected:($repeat_actual!==''&&$repeat_actual!==[]))throw new RuntimeException('SANRO Studio: repeat metadata mismatch.');
        }
        unset($expected[$id][$size]);
    }
    foreach ($expected as $sizes) if ($sizes) throw new RuntimeException('SANRO Studio: incomplete order sizes.');
    if ($shipping === null) {
        $charges = sanro_studio_capture_native_shipping($order,['quote'=>['total_price'=>$subtotal]]);
        if (empty($charges['valid'])) throw new RuntimeException('SANRO Studio: native shipping or order charges do not match.');
        $shipping = $charges['shipping_total'];
    }
    if (!sanro_studio_order_shipping_matches($order,$shipping)) throw new RuntimeException('SANRO Studio: saved shipping changed.');
    foreach ($designs as &$design) $design['contract']['shipping_total'] = $shipping;
    unset($design);
    $GLOBALS['sanro_studio_pricing_checkout'] = isset($verified['designs']) ? ['designs'=>$designs] : reset($designs);
}

function sanro_studio_pricing_lock_order($order) {
    $verified = $GLOBALS['sanro_studio_pricing_checkout'] ?? null;
    if (!is_array($verified) || !is_object($order) || !method_exists($order,'get_id') || !method_exists($order,'get_items')) return;
    $designs = $verified['designs'] ?? [$verified['id']=>$verified];
    $contracts = array_column($designs, 'contract');
    $preflight = count($designs) === 1 ? $contracts[0] : [
        'valid'=>true, 'designs'=>$contracts, 'shipping_total'=>$contracts[0]['shipping_total']??0,
    ];
    if (!function_exists('sanro_studio_checkout_batch_lock_preflight')
        || empty(sanro_studio_checkout_batch_lock_preflight($order,$preflight)['valid'])) {
        throw new RuntimeException('SANRO Studio: persisted order quote could not be verified.');
    }
    $items_by_design = [];
    foreach ($order->get_items('line_item') as $item_id => $item) {
        $items_by_design[$item->get_meta('_sanro_studio_design_id',true)][] = $item_id;
    }
    $updates = [];
    foreach ($designs as $id => $design) {
        $ids = $items_by_design[$id];
        sort($ids, SORT_NUMERIC);
        $updates[$id] = sanro_studio_pricing_order_draft_update($order,$design,$ids);
    }
    sanro_studio_pricing_commit_order_drafts($updates);
    $records = array_map(static function ($update) { return $update['after']; }, $updates);
    if (count($designs) === 1) sanro_studio_pricing_finish_order_binding($order,reset($designs),reset($records));
    else sanro_studio_pricing_finish_multi_order_binding($order,$designs,$records);
}

function sanro_studio_pricing_order_draft_update($order,$verified,$ids) {
    $key = 'sanro_studio_draft_' . $verified['id']; $record = get_option($key, false);
    $session = sanro_studio_pricing_session_hash();
    if (is_array($record) && ($record['status']??null)==='locked'
        && ($record['order_id']??null)===$order->get_id()
        && ($record['wc_session_hash']??null)===$session
        && ($record['shipping_total']??0)===($verified['contract']['shipping_total']??0)
        && ($record['checkout_quote']??null) === $verified['contract']['quote']
        && is_string($record['token_hash']??null)
        && hash_equals($record['token_hash'],hash('sha256',$verified['token']))
        && hash('sha256',maybe_serialize($record['design']))===hash('sha256',maybe_serialize($verified['contract']['design']))) {
        if (($record['order_item_ids']??null) !== $ids
            && ($verified['existing_order_id']??null) !== $order->get_id()) throw new RuntimeException('SANRO Studio: order allocation changed.');
        $after = $record;
        $after['order_item_ids'] = $ids;
        return ['key'=>$key,'before'=>$record,'after'=>$after];
    }
    if (!is_array($record) || ($record['status'] ?? null) !== 'draft' || ($record['wc_session_hash'] ?? null) !== $session
        || ($record['product_id'] ?? null) !== ($verified['contract']['product_id'] ?? null)
        || !is_int($record['expires_at'] ?? null) || $record['expires_at'] <= time()
        || !is_string($record['token_hash'] ?? null)
        || !hash_equals($record['token_hash'], hash('sha256', $verified['token']))
        || !hash_equals($verified['draft']['design_hash'], hash('sha256', maybe_serialize($record['design'] ?? null)))) {
        throw new RuntimeException('SANRO Studio: order draft lock failed.');
    }
    $production_design = $verified['contract']['design'] ?? null;
    $before_artwork = $record['design']; $after_artwork = $production_design;
    if (!is_array($after_artwork)) throw new RuntimeException('SANRO Studio: production design missing.');
    unset($before_artwork['quantities'],$after_artwork['quantities']);
    if (maybe_serialize($before_artwork) !== maybe_serialize($after_artwork)
        || empty(sanro_studio_pricing_preview_bundle($verified['draft'],$verified['token'])['valid'])) {
        throw new RuntimeException('SANRO Studio: production artwork changed.');
    }
    if(function_exists('sanro_studio_repeat_saved_order_allowed')){$repeat_record=$record;$repeat_record['checkout_quote']=$verified['contract']['quote'];if(!sanro_studio_repeat_saved_order_allowed($order,$repeat_record))throw new RuntimeException('SANRO Studio: repeat eligibility changed before lock.');}
    $after=$record; $after['design']=$production_design;
    if (isset($after['preview_design_hash'])) $after['preview_design_hash']=hash('sha256',maybe_serialize($production_design));
    $after['design_id']=$verified['id'];$after['checkout_quote']=$verified['contract']['quote'];$after['shipping_total']=$verified['contract']['shipping_total']??0;
    $after['status']='locked'; $after['order_id']=(int)$order->get_id(); $after['order_item_ids']=$ids; $after['locked_at']=time();
    return ['key'=>$key,'before'=>$record,'after'=>$after];
}

/** One compare-and-swap statement for all affected draft rows. */
function sanro_studio_pricing_commit_order_drafts($updates) {
    global $wpdb;
    $tables = $assignments = $values = $conditions = $expected = $keys = [];
    $changed_count = 0;
    foreach (array_values($updates) as $index => $update) {
        $alias = 'draft_' . $index;
        $tables[] = "`{$wpdb->options}` AS " . $alias;
        $assignments[] = "$alias.option_value = %s";
        $values[] = maybe_serialize($update['after']);
        $conditions[] = "$alias.option_name = %s AND BINARY $alias.option_value = BINARY %s";
        $keys[] = $update['key'];
        $expected[] = $update['key'];
        $expected[] = maybe_serialize($update['before']);
        if ($update['before'] !== $update['after']) $changed_count++;
    }
    if (!$changed_count) return;
    $sql = 'UPDATE ' . implode(' CROSS JOIN ', $tables) . ' SET ' . implode(', ', $assignments)
        . ' WHERE ' . implode(' AND ', $conditions);
    $changed = $wpdb->query($wpdb->prepare($sql,...array_merge($values,$expected)));
    foreach ($keys as $key) wp_cache_delete($key,'options');
    if ($changed !== $changed_count) throw new RuntimeException('SANRO Studio: draft allocation changed before locking the order.');
}
