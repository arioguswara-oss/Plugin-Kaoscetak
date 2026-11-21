<?php
defined('ABSPATH') || exit;

/**
 * Verify a SANRO Studio production folder against live Google Drive metadata.
 * This does NOT create a folder, authorize OAuth, or write WooCommerce data.
 * A trusted, server-side OAuth provider may supply a scoped token through
 * sanro_studio_drive_access_token. Default is deny, never use buyer input.
 */
function sanro_studio_verify_drive_item_folder($order, $item_id, $folder_id) {
    if (!defined('SANRO_STUDIO_ENABLE_VERIFIED_DRIVE_ATTACHMENT')
        || SANRO_STUDIO_ENABLE_VERIFIED_DRIVE_ATTACHMENT !== true
        || !defined('SANRO_STUDIO_DRIVE_PARENT_FOLDER_ID')) {
        return ['valid' => false, 'error' => 'drive_verification_required'];
    }
    if (!is_object($order) || !method_exists($order, 'get_id')
        || (int) $order->get_id() < 1
        || !is_int($item_id) || $item_id < 1
        || !is_string($folder_id)
        || !preg_match('/^[A-Za-z0-9_-]{10,128}$/D', $folder_id)) {
        return ['valid' => false, 'error' => 'invalid_folder_target'];
    }
    $parent = SANRO_STUDIO_DRIVE_PARENT_FOLDER_ID;
    if (!is_string($parent) || !preg_match('/^[A-Za-z0-9_-]{10,128}$/D', $parent)
        || $parent === $folder_id) {
        return ['valid' => false, 'error' => 'drive_parent_not_configured'];
    }
    if (!function_exists('home_url') || !function_exists('apply_filters')
        || !function_exists('wp_remote_get')
        || !function_exists('wp_remote_retrieve_response_code')
        || !function_exists('wp_remote_retrieve_body')
        || !function_exists('is_wp_error')) {
        return ['valid' => false, 'error' => 'drive_runtime_unavailable'];
    }
    $token = apply_filters('sanro_studio_drive_access_token', '');
    if (!is_string($token) || strlen($token) < 12 || strlen($token) > 4096
        || !preg_match('/^[A-Za-z0-9._~+\/-]+$/D', $token)) {
        return ['valid' => false, 'error' => 'drive_not_connected'];
    }
    $url = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($folder_id)
        . '?fields=' . rawurlencode('id,name,mimeType,parents,trashed,permissions(type),appProperties');
    $response = wp_remote_get($url, [
        'timeout' => 15,
        'redirection' => 0,
        'limit_response_size' => 8192,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ],
    ]);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return ['valid' => false, 'error' => 'drive_remote_unavailable'];
    }
    $body = wp_remote_retrieve_body($response);
    if (!is_string($body) || strlen($body) > 8192) {
        return ['valid' => false, 'error' => 'drive_invalid_response'];
    }
    $remote = json_decode($body, true);
    if (!is_array($remote) || ($remote['id'] ?? null) !== $folder_id
        || ($remote['mimeType'] ?? null) !== 'application/vnd.google-apps.folder'
        || ($remote['trashed'] ?? true) !== false
        || !is_array($remote['parents'] ?? null)
        || count($remote['parents']) !== 1 || $remote['parents'][0] !== $parent) {
        return ['valid' => false, 'error' => 'drive_folder_mismatch'];
    }
    // Fail closed if Google did not return permission evidence or if folder is
    // shared to the public, a domain or a group. Owner/user-only is required.
    $permissions = $remote['permissions'] ?? null;
    if (!is_array($permissions) || !$permissions) {
        return ['valid' => false, 'error' => 'drive_permissions_unverified'];
    }
    foreach ($permissions as $permission) {
        if (!is_array($permission) || ($permission['type'] ?? null) !== 'user') {
            return ['valid' => false, 'error' => 'drive_folder_not_private'];
        }
    }
    $properties = $remote['appProperties'] ?? null;
    $site = hash('sha256', rtrim(home_url('/'), '/'));
    if (!is_array($properties)
        || ($properties['sanroSite'] ?? null) !== $site
        || ($properties['sanroOrderId'] ?? null) !== (string) $order->get_id()
        || ($properties['sanroItemId'] ?? null) !== (string) $item_id) {
        return ['valid' => false, 'error' => 'drive_folder_binding_mismatch'];
    }
    // The private folder has been verified live. Never persist the access
    // token, returned metadata, permission list or generated public share URL.
    return ['valid' => true, 'folder_id' => $folder_id];
}

/** Admin-only URL, re-verifies remote state on every read; no stale links. */
function sanro_studio_verified_order_item_drive_url($order, $item_id) {
    if (!function_exists('current_user_can') || !current_user_can('manage_woocommerce')
        || !is_object($order) || !method_exists($order, 'get_items')
        || !is_int($item_id) || $item_id < 1) {
        return null;
    }
    $items = $order->get_items('line_item');
    if (!is_array($items) || !isset($items[$item_id])
        || !is_object($items[$item_id]) || !method_exists($items[$item_id], 'get_meta')) {
        return null;
    }
    $id = $items[$item_id]->get_meta('_sanro_studio_drive_folder_id', true);
    if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{10,128}$/D', $id)) {
        return null;
    }
    // Don't expose a link for an order that has lost production eligibility.
    if (!function_exists('sanro_studio_order_item_production_manifest')) return null;
    $manifest = sanro_studio_order_item_production_manifest($order, $item_id);
    if (empty($manifest['valid']) || empty($manifest['ready_for_drive_integration'])) {
        return null;
    }
    $verified = sanro_studio_verify_drive_item_folder($order, $item_id, $id);
    return !empty($verified['valid'])
        ? 'https://drive.google.com/drive/folders/' . rawurlencode($id)
        : null;
}
