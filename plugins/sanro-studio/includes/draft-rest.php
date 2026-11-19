<?php
defined('ABSPATH') || exit;

/**
 * Staging-only private draft metadata API. No master files, public shares,
 * checkout or order permissions are provided by these endpoints.
 */
function sanro_studio_draft_rest_enabled() {
    return function_exists('sanro_studio_frontend_enabled')
        && sanro_studio_frontend_enabled()
        && defined('SANRO_STUDIO_ENABLE_DRAFT_API')
        && SANRO_STUDIO_ENABLE_DRAFT_API === true;
}

function sanro_studio_register_draft_routes() {
    if (!sanro_studio_draft_rest_enabled()) return;
    if (defined('SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT')
        && SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT === true
        && defined('SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK')
        && SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK === true) {
        register_rest_route('sanro-studio/v1', '/drafts/(?P<id>[a-fA-F0-9-]{36})/cart', [
            'methods' => 'POST',
            'callback' => 'sanro_studio_rest_stage_draft_cart',
            'permission_callback' => 'sanro_studio_rest_draft_nonce',
        ]);
    }
    register_rest_route('sanro-studio/v1', '/drafts', [
        'methods' => 'POST',
        'callback' => 'sanro_studio_rest_create_draft',
        'permission_callback' => 'sanro_studio_rest_draft_nonce',
    ]);
    register_rest_route('sanro-studio/v1', '/drafts/(?P<id>[a-fA-F0-9-]{36})', [
        'methods' => 'GET',
        'callback' => 'sanro_studio_rest_read_draft',
        'permission_callback' => 'sanro_studio_rest_draft_nonce',
    ]);
    register_rest_route('sanro-studio/v1', '/drafts/(?P<id>[a-fA-F0-9-]{36})/quote', [
        'methods' => 'GET',
        'callback' => 'sanro_studio_rest_quote_draft',
        'permission_callback' => 'sanro_studio_rest_draft_nonce',
    ]);
    register_rest_route('sanro-studio/v1', '/drafts/(?P<id>[a-fA-F0-9-]{36})', [
        'methods' => 'POST',
        'callback' => 'sanro_studio_rest_update_draft',
        'permission_callback' => 'sanro_studio_rest_draft_nonce',
    ]);
}

/**
 * Private preview uploads require a separate explicit staging opt-in.
 * Owner secret is checked by the storage boundary, not by a guest nonce.
 */
function sanro_studio_preview_upload_enabled() {
    return sanro_studio_draft_rest_enabled()
        && defined('SANRO_STUDIO_ENABLE_PRIVATE_PREVIEWS')
        && SANRO_STUDIO_ENABLE_PRIVATE_PREVIEWS === true
        && defined('SANRO_STUDIO_ENABLE_PREVIEW_UPLOAD')
        && SANRO_STUDIO_ENABLE_PREVIEW_UPLOAD === true;
}

function sanro_studio_register_preview_upload_route() {
    if (!sanro_studio_preview_upload_enabled()) return;
    register_rest_route('sanro-studio/v1', '/drafts/(?P<id>[a-fA-F0-9-]{36})/previews/(?P<side>front|back)/(?P<file_id>[a-f0-9-]{36}-(?:front|back)-[a-f0-9]{64}\\.(?:png|jpg|webp))', [
        'methods' => 'GET',
        'callback' => 'sanro_studio_rest_read_preview',
        'permission_callback' => 'sanro_studio_rest_draft_nonce',
    ]);
    register_rest_route('sanro-studio/v1', '/drafts/(?P<id>[a-fA-F0-9-]{36})/previews/(?P<side>front|back)', [
        'methods' => 'POST',
        'callback' => 'sanro_studio_rest_upload_preview',
        'permission_callback' => 'sanro_studio_rest_draft_nonce',
    ]);
}

/**
 * Owner-authorized private preview readback for draft restoration.
 * File IDs are immutable content-addressed references, never public URLs.
 * This does not restore or persist original print-resolution masters.
 */
function sanro_studio_rest_read_preview($request) {
    if (!sanro_studio_preview_upload_enabled()) {
        return new WP_Error('sanro_preview_disabled', 'Preview unavailable.', ['status' => 403]);
    }
    $result = sanro_studio_read_owned_draft_preview(
        $request->get_param('id'), $request->get_header('X-Sanro-Owner-Token'),
        $request->get_param('side'), $request->get_param('file_id')
    );
    if (empty($result['valid'])) {
        $error = $result['error'] ?? 'preview_not_found';
        $status = $error === 'draft_expired' ? 410
            : (in_array($error, ['invalid_credentials', 'not_found', 'preview_not_found'], true) ? 404 : 400);
        return new WP_Error('sanro_' . $error, 'Preview unavailable.', ['status' => $status]);
    }
    if (function_exists('nocache_headers')) nocache_headers();
    $response = rest_ensure_response([
        'valid' => true,
        'mime' => $result['mime'],
        'image_base64' => base64_encode($result['bytes']),
    ]);
    $response->header('Cache-Control', 'private, no-store, max-age=0');
    $response->header('X-Content-Type-Options', 'nosniff');
    return $response;
}

function sanro_studio_rest_upload_preview($request) {
    if (!sanro_studio_preview_upload_enabled()) {
        return new WP_Error('sanro_preview_disabled', 'Preview uploads unavailable.', ['status' => 403]);
    }
    $encoded = $request->get_param('image_base64');
    if (!is_string($encoded) || strlen($encoded) > 70000
        || !preg_match('/^[A-Za-z0-9+\\/]+={0,2}$/D', $encoded)) {
        return new WP_Error('sanro_invalid_preview', 'Invalid preview payload.', ['status' => 400]);
    }
    $bytes = base64_decode($encoded, true);
    if (!is_string($bytes) || strlen($bytes) > 51200) {
        return new WP_Error('sanro_invalid_preview', 'Invalid preview payload.', ['status' => 400]);
    }
    $result = sanro_studio_store_owned_draft_preview(
        $request->get_param('id'), $request->get_header('X-Sanro-Owner-Token'),
        $request->get_param('side'), $bytes
    );
    if (empty($result['valid'])) {
        $error = $result['error'] ?? 'invalid_preview';
        $status = $error === 'draft_expired' ? 410
            : (in_array($error, ['invalid_credentials', 'not_found'], true) ? 404 : 400);
        return new WP_Error('sanro_' . $error, 'Preview upload rejected.', ['status' => $status]);
    }
    if (function_exists('nocache_headers')) nocache_headers();
    return rest_ensure_response([
        'valid' => true, 'file_id' => $result['file_id'],
        'mime' => $result['mime'], 'bytes' => $result['bytes'],
    ]);
}

/**
 * Master upload remains staging-only and opt-in. Nonce is CSRF protection;
 * the separate draft owner token authorizes access to a specific draft.
 */
/** Owner-only private master readback for staging verification; never cache. */
function sanro_studio_rest_read_master($request) {
    if (!defined('SANRO_STUDIO_ENABLE_MASTER_UPLOAD')
        || SANRO_STUDIO_ENABLE_MASTER_UPLOAD !== true
        || !defined('SANRO_STUDIO_ENABLE_PRIVATE_MASTERS')
        || SANRO_STUDIO_ENABLE_PRIVATE_MASTERS !== true) {
        return new WP_Error('sanro_master_disabled', 'Master readback unavailable.', ['status' => 403]);
    }
    $id = $request->get_param('id');
    $side = $request->get_param('side');
    $file_id = $request->get_param('file_id');
    $token = $request->get_header('X-Sanro-Owner-Token');
    $draft = sanro_studio_read_draft($id, $token);
    if (empty($draft['valid'])) return sanro_studio_rest_draft_response($draft);
    // Only expose the currently bound reference, not arbitrary historical files.
    if (!isset($draft['master_refs'][$side])
        || !is_string($draft['master_refs'][$side])
        || !hash_equals($draft['master_refs'][$side], $file_id)) {
        return new WP_Error('sanro_master_not_bound', 'Master unavailable.', ['status' => 404]);
    }
    $read = sanro_studio_read_owned_draft_master($id, $token, $side, $file_id);
    if (empty($read['valid'])) return sanro_studio_rest_draft_response($read);
    if (function_exists('nocache_headers')) nocache_headers();
    $response = rest_ensure_response([
        'valid' => true, 'file_id' => $file_id, 'side' => $side,
        'mime' => $read['mime'], 'sha256' => $read['sha256'],
        'image_base64' => base64_encode($read['bytes']),
    ]);
    $response->header('Cache-Control', 'private, no-store, max-age=0');
    return $response;
}

function sanro_studio_register_master_upload_route() {
    if (!defined('SANRO_STUDIO_ENABLE_MASTER_UPLOAD')
        || SANRO_STUDIO_ENABLE_MASTER_UPLOAD !== true
        || !defined('SANRO_STUDIO_ENABLE_PRIVATE_MASTERS')
        || SANRO_STUDIO_ENABLE_PRIVATE_MASTERS !== true
        || !sanro_studio_draft_rest_enabled()) return;
    register_rest_route('sanro-studio/v1',
        '/drafts/(?P<id>[a-fA-F0-9-]{36})/masters/(?P<side>front|back)/(?P<file_id>[a-zA-Z0-9._-]+)', [
            'methods' => 'GET',
            'callback' => 'sanro_studio_rest_read_master',
            'permission_callback' => 'sanro_studio_rest_draft_nonce',
        ]);
    register_rest_route('sanro-studio/v1',
        '/drafts/(?P<id>[a-fA-F0-9-]{36})/masters/(?P<side>front|back)', [
            'methods' => 'POST',
            'callback' => 'sanro_studio_rest_upload_master',
            'permission_callback' => 'sanro_studio_rest_draft_nonce',
        ]);
}

function sanro_studio_rest_upload_master($request) {
    if (!defined('SANRO_STUDIO_ENABLE_MASTER_UPLOAD')
        || SANRO_STUDIO_ENABLE_MASTER_UPLOAD !== true
        || !defined('SANRO_STUDIO_ENABLE_PRIVATE_MASTERS')
        || SANRO_STUDIO_ENABLE_PRIVATE_MASTERS !== true) {
        return new WP_Error('sanro_master_disabled', 'Master upload unavailable.', ['status' => 403]);
    }
    $id = $request->get_param('id');
    $side = $request->get_param('side');
    $token = $request->get_header('X-Sanro-Owner-Token');
    $draft = sanro_studio_read_draft($id, $token);
    if (empty($draft['valid'])) return sanro_studio_rest_draft_response($draft);
    if (($draft['status'] ?? null) !== 'draft') {
        return new WP_Error('sanro_draft_locked', 'Draft is not editable.', ['status' => 409]);
    }
    // Required before decoding or reserving upload quota. Never rely on
    // an undefined revision variable (PHP notices must not bypass CAS).
    $expected_hash = $request->get_param('design_hash');
    if (!is_string($expected_hash) || !preg_match('/^[a-f0-9]{64}$/D', $expected_hash)) {
        return new WP_Error('sanro_revision_required', 'Draft revision required.', ['status' => 409]);
    }
    $encoded = $request->get_param('image_base64');
    if (!is_string($encoded) || strlen($encoded) < 128
        || strlen($encoded) > 13981016
        || !preg_match('/^[A-Za-z0-9+\\/]+={0,2}$/D', $encoded)) {
        return new WP_Error('sanro_invalid_master', 'Invalid master image.', ['status' => 400]);
    }
    $bytes = base64_decode($encoded, true);
    if (!is_string($bytes) || strlen($bytes) > 10485760) {
        return new WP_Error('sanro_invalid_master', 'Invalid master image.', ['status' => 400]);
    }
    $checked = sanro_studio_validate_master_bytes($bytes);
    if (empty($checked['valid'])) {
        return new WP_Error('sanro_invalid_master', 'Invalid master image.', ['status' => 400]);
    }
    if (!hash_equals($draft['design_hash'] ?? '', $expected_hash)) {
        return new WP_Error('sanro_draft_conflict', 'Draft changed. Reload before upload.', ['status' => 409]);
    }
    // Serialize quota reservation and file binding across concurrent requests.
    // MySQL advisory locks are connection-scoped, so release in finally.
    global $wpdb;
    if (!isset($wpdb) || !is_object($wpdb) || !method_exists($wpdb, 'get_var')
        || !method_exists($wpdb, 'prepare') || !function_exists('get_transient')
        || !function_exists('set_transient')) {
        return new WP_Error('sanro_master_unavailable', 'Upload unavailable.', ['status' => 503]);
    }
    $lock_name = 'sanro_master_' . substr(hash('sha256', strtolower($id)), 0, 40);
    $acquired = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 2)', $lock_name));
    if ((string) $acquired !== '1') {
        return new WP_Error('sanro_master_busy', 'Upload busy. Retry later.', ['status' => 503]);
    }
    try {
        // Recheck owner and lock status inside the serialized critical section.
        $current = sanro_studio_read_draft($id, $token);
        if (empty($current['valid'])) return sanro_studio_rest_draft_response($current);
        if (($current['status'] ?? null) !== 'draft') {
            return new WP_Error('sanro_draft_locked', 'Draft is not editable.', ['status' => 409]);
        }
        if (!isset($current['design_hash'])
            || !hash_equals($current['design_hash'], $expected_hash)) {
            return new WP_Error('sanro_draft_conflict', 'Draft changed. Reload before upload.', ['status' => 409]);
        }
        $budget_key = 'sanro_master_budget_' . hash('sha256', strtolower($id));
        $count = get_transient($budget_key);
        if ($count !== false && (!is_int($count) || $count >= 10)) {
            return new WP_Error('sanro_master_rate_limited', 'Upload limit reached.', ['status' => 429]);
        }
        // Reserve before writing, fail closed if quota storage fails.
        if (!set_transient($budget_key, $count === false ? 1 : $count + 1, HOUR_IN_SECONDS)) {
            return new WP_Error('sanro_master_unavailable', 'Upload unavailable.', ['status' => 503]);
        }
        $stored = sanro_studio_store_owned_draft_master($id, $token, $side, $bytes);
        if (empty($stored['valid'])) return sanro_studio_rest_draft_response($stored);
        $bound = sanro_studio_bind_owned_draft_master($id, $token, $side, $stored['file_id'], $expected_hash);
        if (empty($bound['valid'])) return sanro_studio_rest_draft_response($bound);
    } finally {
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
    }
    if (function_exists('nocache_headers')) nocache_headers();
    $response = rest_ensure_response([
        'valid' => true, 'design_id' => $id, 'side' => $side,
        'file_id' => $stored['file_id'], 'mime' => $stored['mime'],
        'bytes' => $stored['bytes'], 'sha256' => $stored['sha256'],
    ]);
    $response->header('Cache-Control', 'private, no-store, max-age=0');
    return $response;
}

function sanro_studio_rest_draft_nonce($request) {
    $nonce = $request->get_header('X-Sanro-Draft-Nonce');
    return is_string($nonce) && wp_verify_nonce($nonce, 'sanro_studio_draft')
        ? true : new WP_Error('sanro_nonce_required', 'Draft request not authorized.', ['status' => 403]);
}

function sanro_studio_rest_draft_response($result) {
    if (!empty($result['valid'])) {
        return rest_ensure_response($result);
    }
    $error = isset($result['error']) && is_string($result['error']) ? $result['error'] : 'invalid_draft';
    $status = in_array($error, ['invalid_product', 'invalid_design'], true) ? 400 : 409;
    if (in_array($error, ['invalid_credentials', 'not_found'], true)) $status = 404;
    if ($error === 'draft_expired') $status = 410;
    return new WP_Error('sanro_' . $error, 'Draft operation rejected.', ['status' => $status]);
}

/**
 * Guest nonce is CSRF friction, not authentication. Bound unauthenticated
 * creation to a small hourly budget without persisting a raw IP address.
 */
function sanro_studio_draft_creation_budget() {
    if (!function_exists('get_transient') || !function_exists('set_transient')
        || !function_exists('wp_salt')) return false;
    $ip = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])
        ? $_SERVER['REMOTE_ADDR'] : '';
    if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
    $key = 'sanro_draft_limit_' . substr(hash_hmac('sha256', $ip, wp_salt('auth')), 0, 32);
    $count = get_transient($key);
    if ($count !== false && (!is_int($count) || $count >= 20)) return false;
    return set_transient($key, $count === false ? 1 : $count + 1, HOUR_IN_SECONDS);
}

/** Only compact geometry/text metadata; reject embedded images or oversized JSON. */
/**
 * A saved image geometry is trustworthy only when its side is bound to a
 * private master in the same owner-authorized draft. Never accept a file URL,
 * client-selected file ID, or image bytes in design metadata.
 */
function sanro_studio_draft_image_binding_allowed($design, $master_refs, $design_id = null) {
    if (!is_array($design) || !is_array($master_refs)) return false;
    // Bindings must be unambiguous and limited to the two supported sides.
    if (array_diff(array_keys($master_refs), ['front', 'back'])) return false;
    foreach (['front', 'back'] as $side) {
        $entry = $design[$side] ?? null;
        if (!is_array($entry)) continue;
        $objects = $entry['objects'] ?? [];
        if (!is_array($objects)) return false;
        foreach ($objects as $object) {
            if (!is_array($object) || ($object['type'] ?? null) !== 'image') continue;
            $ref = $master_refs[$side] ?? null;
            if (!is_string($design_id)
                || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $design_id)
                || !is_string($ref)
                || !preg_match('/^[a-f0-9-]{36}-(front|back)-[a-f0-9]{64}\\.(png|jpg)$/D', $ref)
                || strpos($ref, strtolower($design_id) . '-' . $side . '-') !== 0) return false;
        }
    }
    return true;
}

function sanro_studio_draft_payload_allowed($design, $allow_image_geometry = false) {
    if (!is_array($design) || !function_exists('wp_json_encode')) return false;
    $json = wp_json_encode($design);
    if (!is_string($json) || strlen($json) > 16384
        || preg_match('~data:image/|base64,~i', $json)) return false;
    // Until the private Drive master pipeline exists, accept text-only
    // drafts. Image geometry alone must never imply a saved master.
    foreach (['front', 'back'] as $side) {
        if (!isset($design[$side]) || !is_array($design[$side])) continue;
        foreach (($design[$side]['objects'] ?? []) as $object) {
            if (is_array($object) && ($object['type'] ?? null) === 'image' && !$allow_image_geometry) return false;
        }
    }
    return true;
}

/** An image-only draft starts without printable objects, never as a quote. */
function sanro_studio_image_bootstrap_allowed($design) {
    if (!defined('SANRO_STUDIO_ENABLE_MASTER_UPLOAD')
        || SANRO_STUDIO_ENABLE_MASTER_UPLOAD !== true
        || !defined('SANRO_STUDIO_ENABLE_PRIVATE_MASTERS')
        || SANRO_STUDIO_ENABLE_PRIVATE_MASTERS !== true
        || !is_array($design)
        || array_diff(array_keys($design), ['shirt_color', 'quantities', 'long_sleeve'])) {
        return false;
    }
    return isset($design['shirt_color'], $design['quantities'], $design['long_sleeve'])
        && is_string($design['shirt_color'])
        && in_array($design['shirt_color'], ['#ffffff','#171717','#b91c1c','#1d4ed8','#166534'], true)
        && is_bool($design['long_sleeve'])
        && is_array($design['quantities'])
        && !array_diff(array_keys($design['quantities']), ['S','M','L','XL','2XL','3XL','4XL','5XL'])
        && array_reduce($design['quantities'], static function ($valid, $count) {
            return $valid && is_int($count) && $count >= 0 && $count <= 999;
        }, true)
        && array_sum($design['quantities']) <= 5000;
}

function sanro_studio_rest_create_draft($request) {
    $product_id = $request->get_param('product_id');
    $design = $request->get_param('design');
    if (!is_int($product_id) || !sanro_studio_draft_payload_allowed($design)
        || empty(sanro_studio_public_editor_config($product_id)['valid'])) {
        return new WP_Error('sanro_invalid_draft', 'Invalid product or design.', ['status' => 400]);
    }
    // Validate before consuming the guest creation budget. Invalid geometry
    // must not create records or consume a legitimate customer's allowance.
    if (!sanro_studio_image_bootstrap_allowed($design)) {
        $validation = sanro_studio_validate_product_design($product_id, $design);
        if (empty($validation['valid'])) {
            return sanro_studio_rest_draft_response($validation);
        }
    }
    if (!sanro_studio_draft_creation_budget()) {
        return new WP_Error('sanro_rate_limited', 'Draft creation temporarily unavailable.', ['status' => 429]);
    }
    return sanro_studio_rest_draft_response(sanro_studio_create_draft($product_id, $design));
}

function sanro_studio_rest_update_draft($request) {
    $token = $request->get_header('X-Sanro-Owner-Token');
    $design = $request->get_param('design');
    // Image geometry is allowed only on existing drafts; the update layer
    // checks the bound master for each side against its CAS snapshot.
    if (!sanro_studio_draft_payload_allowed($design, true)) {
        return new WP_Error('sanro_invalid_design', 'Invalid design.', ['status' => 400]);
    }
    $expected_hash = $request->get_param('expected_design_hash');
    // Public draft updates must carry a server-issued revision. Internal
    // callers retain the optional parameter for compatibility.
    if (!is_string($expected_hash) || !preg_match('/^[a-f0-9]{64}$/D', $expected_hash)) {
        return new WP_Error('sanro_revision_required', 'Draft revision required.', ['status' => 409]);
    }
    return sanro_studio_rest_draft_response(
        sanro_studio_update_draft($request->get_param('id'), $token, $design, $expected_hash)
    );
}

/** Read requires BOTH the owner secret and the staging nonce; never cache. */
function sanro_studio_rest_read_draft($request) {
    if (function_exists('nocache_headers')) nocache_headers();
    return sanro_studio_rest_draft_response(sanro_studio_read_draft(
        $request->get_param('id'), $request->get_header('X-Sanro-Owner-Token')
    ));
}

/**
 * Read-only, owner-authorized price quote for a saved text-only draft.
 * Rechecks the product and geometry on each request; never trusts a client
 * price, quantity, product ID, sleeve variant, or checkout instruction.
 */
function sanro_studio_rest_quote_draft($request) {
    if (function_exists('nocache_headers')) nocache_headers();
    $draft = sanro_studio_read_draft(
        $request->get_param('id'), $request->get_header('X-Sanro-Owner-Token')
    );
    if (empty($draft['valid'])) return sanro_studio_rest_draft_response($draft);
    if ($draft['status'] !== 'draft') {
        return new WP_Error('sanro_draft_locked', 'Draft is not editable.', ['status' => 409]);
    }
    if (empty(sanro_studio_public_editor_config($draft['product_id'])['valid'])) {
        return new WP_Error('sanro_product_unavailable', 'Product unavailable.', ['status' => 404]);
    }
    $design = $draft['design'];
    // An empty bootstrap is not printable, and image geometry without the
    // currently owner-bound master must never receive a price quote.
    if (!function_exists('sanro_studio_validate_product_design')
        || empty(sanro_studio_validate_product_design($draft['product_id'], $design)['valid'])
        || !sanro_studio_draft_image_binding_allowed(
            $design, $draft['master_refs'] ?? [], $draft['design_id']
        )) {
        return new WP_Error('sanro_draft_not_print_ready', 'Draft not print ready.', ['status' => 409]);
    }
    $quantities = $design['quantities'] ?? null;
    $long_sleeve = $design['long_sleeve'] ?? false;
    $quote = sanro_studio_quote_size_quantities(
        $draft['product_id'], $design, $quantities, $long_sleeve
    );
    if (empty($quote['valid'])) return sanro_studio_rest_draft_response($quote);
    return rest_ensure_response([
        'valid' => true,
        'design_id' => $draft['design_id'],
        'quote' => $quote,
        'checkout_enabled' => false,
    ]);
}

/**
 * Resolve a WooCommerce cart in an authenticated REST request. WooCommerce
 * does not always initialize frontend cart/session objects for REST routes.
 * Loading must happen only AFTER the draft owner and quote are authorized.
 * Never attempt bootstrap before woocommerce_init or mask load failures.
 */
function sanro_studio_stage_rest_cart() {
    if (!function_exists('WC')) return null;
    $wc = WC();
    if (!is_object($wc)) return null;
    if (!isset($wc->cart) || !is_object($wc->cart)) {
        if (!function_exists('wc_load_cart')
            || !function_exists('did_action')
            || !did_action('woocommerce_init')) return null;
        try {
            wc_load_cart();
        } catch (Throwable $error) {
            return null;
        }
        $wc = WC();
    }
    return is_object($wc) && isset($wc->cart) && is_object($wc->cart)
        ? $wc->cart : null;
}

/**
 * Ensure a guest cart from an authorized REST request can survive navigation
 * to the WooCommerce cart page. REST requests do not reliably inherit
 * WooCommerce's frontend session-cookie bootstrap.
 *
 * Call only AFTER checking the draft owner and the server-side quote, and
 * BEFORE adding any cart items. A session failure must not leave a mutated
 * cart behind or expose owner credentials in a response.
 */
function sanro_studio_stage_rest_session_ready() {
    if (!function_exists('WC')) return false;
    $wc = WC();
    if (!is_object($wc) || !isset($wc->session)
        || !is_object($wc->session)
        || !method_exists($wc->session, 'has_session')
        || !method_exists($wc->session, 'set_customer_session_cookie')) return false;
    try {
        if (!$wc->session->has_session()) {
            if (headers_sent()) return false;
            $wc->session->set_customer_session_cookie(true);
        }
        return $wc->session->has_session() === true;
    } catch (Throwable $error) {
        return false;
    }
}

/**
 * Staging-only buyer-to-cart boundary. The owner token is supplied in an
 * authenticated draft request header, never stored in WC cart metadata.
 */
function sanro_studio_rest_stage_draft_cart($request) {
    if (!sanro_studio_draft_rest_enabled()
        || !defined('SANRO_STUDIO_ENABLE_STAGING_CART')
        || SANRO_STUDIO_ENABLE_STAGING_CART !== true
        || !defined('SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT')
        || SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT !== true
        || !defined('SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK')
        || SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK !== true
        || !function_exists('WC')) {
        return new WP_Error('sanro_cart_disabled', 'Staging cart disabled.', ['status'=>403]);
    }
    $id = $request->get_param('id');
    $token = $request->get_header('X-Sanro-Owner-Token');
    $draft = sanro_studio_read_draft($id, $token);
    if (empty($draft['valid']) || ($draft['status'] ?? null) !== 'draft') {
        return new WP_Error('sanro_cart_unauthorized', 'Draft authorization failed.', ['status'=>403]);
    }
    $product_id = $draft['product_id'];
    $contract = sanro_studio_prepare_cart_design_contract($id, $token, $product_id);
    if (empty($contract['valid'])) {
        return new WP_Error('sanro_cart_contract', 'Draft not ready for cart.', ['status'=>409]);
    }
    $cart = sanro_studio_stage_rest_cart();
    if (!is_object($cart) || !sanro_studio_stage_rest_session_ready()) {
        return new WP_Error('sanro_cart_unavailable', 'Cart session unavailable.', ['status'=>503]);
    }
    // A client may retry after its first POST committed but the response was
    // lost. Do not duplicate the order lines: permit reuse only after fresh
    // owner authorization, full cart validation, and an identical server quote.
    try {
        $existing = method_exists($cart, 'get_cart') ? $cart->get_cart() : null;
    } catch (Throwable $error) {
        $existing = null;
    }
    if (!is_array($existing)) {
        return new WP_Error('sanro_cart_unavailable', 'Cart cannot be inspected.', ['status'=>503]);
    }
    if ($existing) {
        $result = function_exists('sanro_studio_stage_reauthorize_cart')
            ? sanro_studio_stage_reauthorize_cart($id, $token, $product_id, $cart)
            : ['valid'=>false];
        $quoted_total = $contract['quote']['total_price'] ?? null;
        if (empty($result['valid']) || !is_int($quoted_total)
            || ($result['total_price'] ?? null) !== $quoted_total) {
            return new WP_Error('sanro_cart_conflict', 'Cart has another design or stale pricing.', ['status'=>409]);
        }
    } else {
        $result = sanro_studio_stage_commit_cart_lines($contract, $cart);
        if (empty($result['valid'])) {
            return new WP_Error('sanro_cart_failed', 'Could not add verified design to cart.', ['status'=>409]);
        }
    }
    if (function_exists('nocache_headers')) nocache_headers();
    return rest_ensure_response([
        'valid'=>true, 'design_id'=>$id, 'total_price'=>$result['total_price'],
        'cart_url'=>function_exists('wc_get_cart_url') ? wc_get_cart_url() : '',
        'requires_checkout_reauthorization'=>true,
    ]);
}
