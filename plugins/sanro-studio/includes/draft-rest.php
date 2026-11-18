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
function sanro_studio_register_master_upload_route() {
    if (!defined('SANRO_STUDIO_ENABLE_MASTER_UPLOAD')
        || SANRO_STUDIO_ENABLE_MASTER_UPLOAD !== true
        || !defined('SANRO_STUDIO_ENABLE_PRIVATE_MASTERS')
        || SANRO_STUDIO_ENABLE_PRIVATE_MASTERS !== true
        || !sanro_studio_draft_rest_enabled()) return;
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
    // Best-effort abuse throttle. This is not an atomic quota; a stronger
    // per-draft atomic quota is required before public buyer enablement.
    $budget_key = 'sanro_master_budget_' . hash('sha256', strtolower($id));
    $count = get_transient($budget_key);
    if ($count !== false && (!is_int($count) || $count >= 10)) {
        return new WP_Error('sanro_master_rate_limited', 'Upload limit reached.', ['status' => 429]);
    }
    if (!set_transient($budget_key, $count === false ? 1 : $count + 1, HOUR_IN_SECONDS)) {
        return new WP_Error('sanro_master_rate_limited', 'Upload unavailable.', ['status' => 503]);
    }
    $stored = sanro_studio_store_owned_draft_master($id, $token, $side, $bytes);
    if (empty($stored['valid'])) return sanro_studio_rest_draft_response($stored);
    $bound = sanro_studio_bind_owned_draft_master($id, $token, $side, $stored['file_id']);
    if (empty($bound['valid'])) return sanro_studio_rest_draft_response($bound);
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
function sanro_studio_draft_payload_allowed($design) {
    if (!is_array($design) || !function_exists('wp_json_encode')) return false;
    $json = wp_json_encode($design);
    if (!is_string($json) || strlen($json) > 16384
        || preg_match('~data:image/|base64,~i', $json)) return false;
    // Until the private Drive master pipeline exists, accept text-only
    // drafts. Image geometry alone must never imply a saved master.
    foreach (['front', 'back'] as $side) {
        if (!isset($design[$side]) || !is_array($design[$side])) continue;
        foreach (($design[$side]['objects'] ?? []) as $object) {
            if (is_array($object) && ($object['type'] ?? null) === 'image') return false;
        }
    }
    return true;
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
    $validation = sanro_studio_validate_product_design($product_id, $design);
    if (empty($validation['valid'])) {
        return sanro_studio_rest_draft_response($validation);
    }
    if (!sanro_studio_draft_creation_budget()) {
        return new WP_Error('sanro_rate_limited', 'Draft creation temporarily unavailable.', ['status' => 429]);
    }
    return sanro_studio_rest_draft_response(sanro_studio_create_draft($product_id, $design));
}

function sanro_studio_rest_update_draft($request) {
    $token = $request->get_header('X-Sanro-Owner-Token');
    $design = $request->get_param('design');
    if (!sanro_studio_draft_payload_allowed($design)) {
        return new WP_Error('sanro_invalid_design', 'Invalid design.', ['status' => 400]);
    }
    return sanro_studio_rest_draft_response(
        sanro_studio_update_draft($request->get_param('id'), $token, $design)
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
