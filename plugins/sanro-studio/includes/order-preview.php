<?php
defined('ABSPATH') || exit;

/**
 * Read-only order preview reference inventory. This never accepts buyer
 * input or creates order metadata, public links, or master file URLs.
 */
function sanro_studio_order_preview_inventory($order) {
    if (!is_object($order) || !method_exists($order, 'get_meta')) {
        return ['valid' => false, 'error' => 'invalid_order'];
    }
    $result = ['valid' => true, 'front' => null, 'back' => null];
    foreach (['front', 'back'] as $side) {
        $ref = $order->get_meta('_sanro_studio_preview_' . $side . '_ref', true);
        if (is_string($ref) && preg_match(
            '/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}-' .
            $side . '-[a-f0-9]{64}\\.(png|jpg|webp)$/iD', $ref
        )) {
            $result[$side] = $ref;
        }
    }
    return $result;
}

/** Display metadata availability only, never an unprotected file link. */
function sanro_studio_admin_order_preview_status($order) {
    if (!current_user_can('manage_woocommerce')) return;
    $inventory = sanro_studio_order_preview_inventory($order);
    if (empty($inventory['valid'])) return;
    echo '<div class="sanro-studio-order-preview"><h3>' . esc_html('SANRO Studio — Preview desain') . '</h3>';
    foreach (['front' => 'Depan', 'back' => 'Belakang'] as $side => $label) {
        $state = $inventory[$side] === null
            ? 'Belum tersedia'
            : 'Referensi privat tercatat; gunakan tautan admin untuk melihat preview';
        echo '<p>' . esc_html($label . ': ' . $state) . '</p>';
        if ($inventory[$side] !== null && method_exists($order, 'get_id')) {
            $order_id = absint($order->get_id());
            if ($order_id && function_exists('wp_nonce_url') && function_exists('admin_url')) {
                $url = add_query_arg([
                    'action' => 'sanro_studio_order_preview',
                    'order_id' => $order_id,
                    'side' => $side,
                ], admin_url('admin-post.php'));
                $url = wp_nonce_url($url, 'sanro_order_preview_' . $order_id . '_' . $side);
                echo '<a target="_blank" rel="noopener noreferrer" href="' . esc_url($url) . '">' .
                    esc_html('Lihat preview ' . $label) . '</a>';
            }
        }
    }
    echo '</div>';
}

/**
 * Internal-only order reference attachment. Caller must already possess an
 * authorized order object; never register this as a guest REST endpoint.
 * Does not store owner tokens, raw image bytes, or master file locations.
 */
function sanro_studio_attach_order_preview_refs($order, $design_id, $owner_token, $refs) {
    if (!current_user_can('manage_woocommerce')) {
        return ['valid' => false, 'error' => 'forbidden'];
    }
    if (!is_object($order) || !method_exists($order, 'update_meta_data')
        || !method_exists($order, 'save') || !method_exists($order, 'get_items')
        || !is_array($refs) || !$refs || count($refs) > 2
        || !function_exists('sanro_studio_read_draft')) {
        return ['valid' => false, 'error' => 'invalid_order_preview'];
    }
    $draft = sanro_studio_read_draft($design_id, $owner_token);
    if (empty($draft['valid'])) return $draft;
    if (($draft['status'] ?? null) !== 'draft') {
        return ['valid' => false, 'error' => 'draft_locked'];
    }
    $product_match = false;
    foreach ($order->get_items('line_item') as $item) {
        if (is_object($item) && method_exists($item, 'get_product_id')
            && (int) $item->get_product_id() === (int) $draft['product_id']) {
            $product_match = true;
            break;
        }
    }
    if (!$product_match) return ['valid' => false, 'error' => 'product_mismatch'];
    foreach ($refs as $side => $ref) {
        if (!in_array($side, ['front', 'back'], true)
            || !is_string($ref) || !is_string($design_id)
            || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $design_id)
            || !preg_match('/^' . preg_quote(strtolower($design_id), '/') . '-' . $side .
                '-[a-f0-9]{64}\\.(png|jpg|webp)$/D', $ref)) {
            return ['valid' => false, 'error' => 'invalid_preview_reference'];
        }
    }
    // Confirm each private file is readable and checksum-valid before linking.
    // This is intentionally fail-closed when private storage is not configured.
    if (!function_exists('sanro_studio_read_owned_draft_preview')) {
        return ['valid' => false, 'error' => 'preview_store_unavailable'];
    }
    foreach ($refs as $side => $ref) {
        $preview = sanro_studio_read_owned_draft_preview($design_id, $owner_token, $side, $ref);
        if (empty($preview['valid'])) {
            return ['valid' => false, 'error' => $preview['error'] ?? 'preview_not_found'];
        }
    }
    foreach ($refs as $side => $ref) {
        $order->update_meta_data('_sanro_studio_preview_' . $side . '_ref', $ref);
    }
    $order->save();
    return ['valid' => true, 'attached_sides' => array_keys($refs)];
}

/**
 * Resolve order-linked preview for an authorized WooCommerce administrator.
 * No owner token is needed after a reference has been attached to the order.
 * No public URL or filesystem path is returned.
 */
function sanro_studio_admin_order_preview_bytes($order, $side) {
    if (!function_exists('current_user_can') || !current_user_can('manage_woocommerce')) {
        return ['valid' => false, 'error' => 'forbidden'];
    }
    if (!in_array($side, ['front', 'back'], true)) {
        return ['valid' => false, 'error' => 'invalid_side'];
    }
    $inventory = sanro_studio_order_preview_inventory($order);
    if (empty($inventory['valid']) || empty($inventory[$side])) {
        return ['valid' => false, 'error' => 'preview_not_found'];
    }
    $ref = $inventory[$side];
    if (!defined('SANRO_STUDIO_ENABLE_PRIVATE_PREVIEWS')
        || SANRO_STUDIO_ENABLE_PRIVATE_PREVIEWS !== true
        || !defined('SANRO_STUDIO_PRIVATE_PREVIEW_DIR')
        || !is_string(SANRO_STUDIO_PRIVATE_PREVIEW_DIR)) {
        return ['valid' => false, 'error' => 'preview_storage_disabled'];
    }
    $directory = realpath(SANRO_STUDIO_PRIVATE_PREVIEW_DIR);
    $root = defined('ABSPATH') ? realpath(ABSPATH) : false;
    $webroot = isset($_SERVER['DOCUMENT_ROOT']) && is_string($_SERVER['DOCUMENT_ROOT'])
        ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    if (!$directory || !is_dir($directory) || is_link(SANRO_STUDIO_PRIVATE_PREVIEW_DIR)
        || !$root || !$webroot || $directory === $root || $directory === $webroot
        || strpos($directory, $root . DIRECTORY_SEPARATOR) === 0
        || strpos($directory, $webroot . DIRECTORY_SEPARATOR) === 0) {
        return ['valid' => false, 'error' => 'unsafe_preview_directory'];
    }
    $path = $directory . DIRECTORY_SEPARATOR . $ref;
    if (is_link($path) || !is_file($path) || !is_readable($path)
        || filesize($path) > 51200) {
        return ['valid' => false, 'error' => 'preview_not_found'];
    }
    $bytes = @file_get_contents($path);
    $checked = sanro_studio_validate_preview_bytes($bytes);
    $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    $expected = substr($ref, 0, 36) . '-' . $side . '-' . ($checked['sha256'] ?? '') .
        '.' . ($extensions[$checked['mime'] ?? ''] ?? '');
    if (empty($checked['valid']) || !hash_equals($expected, $ref)) {
        return ['valid' => false, 'error' => 'preview_integrity_failed'];
    }
    return ['valid' => true, 'bytes' => $bytes, 'mime' => $checked['mime']];
}

/** Explicitly admin-only delivery, protected by order-scoped nonce. */
function sanro_studio_admin_deliver_order_preview() {
    if (!current_user_can('manage_woocommerce')) wp_die('Forbidden', '', ['response' => 403]);
    $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
    $side = isset($_GET['side']) && is_string($_GET['side']) ? $_GET['side'] : '';
    $nonce = isset($_GET['_wpnonce']) && is_string($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';
    if (!$order_id || !in_array($side, ['front', 'back'], true)
        || !wp_verify_nonce($nonce, 'sanro_order_preview_' . $order_id . '_' . $side)) {
        wp_die('Forbidden', '', ['response' => 403]);
    }
    $order = function_exists('wc_get_order') ? wc_get_order($order_id) : false;
    $result = sanro_studio_admin_order_preview_bytes($order, $side);
    if (empty($result['valid'])) wp_die('Preview unavailable', '', ['response' => 404]);
    if (function_exists('nocache_headers')) nocache_headers();
    header('Content-Type: ' . $result['mime']);
    header('Content-Disposition: inline; filename="sanro-preview-' . $side . '.img"');
    header('X-Content-Type-Options: nosniff');
    header('Content-Security-Policy: sandbox');
    echo $result['bytes'];
    exit;
}
