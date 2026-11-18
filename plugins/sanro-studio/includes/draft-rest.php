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

function sanro_studio_rest_create_draft($request) {
    $product_id = $request->get_param('product_id');
    $design = $request->get_param('design');
    if (!is_int($product_id) || !is_array($design)
        || empty(sanro_studio_public_editor_config($product_id)['valid'])) {
        return new WP_Error('sanro_invalid_draft', 'Invalid product or design.', ['status' => 400]);
    }
    return sanro_studio_rest_draft_response(sanro_studio_create_draft($product_id, $design));
}

function sanro_studio_rest_update_draft($request) {
    $token = $request->get_header('X-Sanro-Owner-Token');
    $design = $request->get_param('design');
    if (!is_array($design)) {
        return new WP_Error('sanro_invalid_design', 'Invalid design.', ['status' => 400]);
    }
    return sanro_studio_rest_draft_response(
        sanro_studio_update_draft($request->get_param('id'), $token, $design)
    );
}
