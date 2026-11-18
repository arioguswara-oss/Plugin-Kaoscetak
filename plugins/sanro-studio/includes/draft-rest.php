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
    register_rest_route('sanro-studio/v1', '/drafts/(?P<id>[a-fA-F0-9-]{36})', [
        'methods' => 'POST',
        'callback' => 'sanro_studio_rest_update_draft',
        'permission_callback' => 'sanro_studio_rest_draft_nonce',
    ]);
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
