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
function sanro_studio_admin_order_preview_bytes($order, $side, $item_id = null) {
    if (!function_exists('current_user_can') || !current_user_can('manage_woocommerce')) {
        return ['valid' => false, 'error' => 'forbidden'];
    }
    if (!in_array($side, ['front', 'back'], true)) {
        return ['valid' => false, 'error' => 'invalid_side'];
    }
    $inventory = $item_id === null
        ? sanro_studio_order_preview_inventory($order)
        : sanro_studio_order_item_preview_inventory($order, $item_id);
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
    $item_id = isset($_GET['item_id']) ? absint($_GET['item_id']) : null;
    $nonce_action = 'sanro_order_preview_' . $order_id . '_' . $side
        . ($item_id === null ? '' : '_item_' . $item_id);
    if (!$order_id || !in_array($side, ['front', 'back'], true)
        || ($item_id !== null && $item_id < 1)
        || !wp_verify_nonce($nonce, $nonce_action)) {
        wp_die('Forbidden', '', ['response' => 403]);
    }
    $order = function_exists('wc_get_order') ? wc_get_order($order_id) : false;
    $result = sanro_studio_admin_order_preview_bytes($order, $side, $item_id);
    if (empty($result['valid'])) wp_die('Preview unavailable', '', ['response' => 404]);
    if (function_exists('nocache_headers')) nocache_headers();
    header('Content-Type: ' . $result['mime']);
    header('Content-Disposition: inline; filename="sanro-preview-' . $side . '.img"');
    header('X-Content-Type-Options: nosniff');
    header('Content-Security-Policy: sandbox');
    echo $result['bytes'];
    exit;
}

/**
 * Order-item-scoped inventory. Keeps designs for separate line items isolated.
 * Legacy order-level metadata is intentionally not used as a fallback.
 */
function sanro_studio_order_item_preview_inventory($order, $item_id) {
    if (!is_object($order) || !method_exists($order, 'get_items')
        || !is_int($item_id) || $item_id < 1) {
        return ['valid' => false, 'error' => 'invalid_order_item'];
    }
    $items = $order->get_items('line_item');
    if (!is_array($items) || !isset($items[$item_id])
        || !is_object($items[$item_id]) || !method_exists($items[$item_id], 'get_meta')) {
        return ['valid' => false, 'error' => 'invalid_order_item'];
    }
    return sanro_studio_order_preview_inventory($items[$item_id]);
}

/**
 * Internal-only attachment for one exact order line. No guest route.
 * The owner token is used transiently for validation, never saved to order metadata.
 */
function sanro_studio_attach_item_preview_refs($order, $item_id, $design_id, $owner_token, $refs) {
    if (!function_exists('current_user_can') || !current_user_can('manage_woocommerce')) {
        return ['valid' => false, 'error' => 'forbidden'];
    }
    $inventory = sanro_studio_order_item_preview_inventory($order, $item_id);
    if (empty($inventory['valid']) || !is_array($refs) || !$refs || count($refs) > 2
        || !function_exists('sanro_studio_read_draft')
        || !function_exists('sanro_studio_read_owned_draft_preview')) {
        return ['valid' => false, 'error' => 'invalid_order_item'];
    }
    $items = $order->get_items('line_item');
    $item = $items[$item_id];
    if (!method_exists($item, 'get_product_id') || !method_exists($item, 'update_meta_data')
        || !method_exists($item, 'save')) {
        return ['valid' => false, 'error' => 'invalid_order_item'];
    }
    $draft = sanro_studio_read_draft($design_id, $owner_token);
    if (empty($draft['valid'])) return $draft;
    if (($draft['status'] ?? null) !== 'draft') {
        return ['valid' => false, 'error' => 'draft_locked'];
    }
    if ((int) $draft['product_id'] !== (int) $item->get_product_id()) {
        return ['valid' => false, 'error' => 'product_mismatch'];
    }
    foreach ($refs as $side => $ref) {
        if (!in_array($side, ['front', 'back'], true)
            || !is_string($design_id) || !is_string($ref)
            || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $design_id)
            || !preg_match('/^' . preg_quote(strtolower($design_id), '/') . '-' . $side .
                '-[a-f0-9]{64}\\.(png|jpg|webp)$/D', $ref)) {
            return ['valid' => false, 'error' => 'invalid_preview_reference'];
        }
        $preview = sanro_studio_read_owned_draft_preview($design_id, $owner_token, $side, $ref);
        if (empty($preview['valid'])) {
            return ['valid' => false, 'error' => $preview['error'] ?? 'preview_not_found'];
        }
    }
    // Bind the line item to one design, and never silently replace a preview.
    // Reattaching the same references is idempotent; conflicts fail closed.
    // Reject a second design before touching metadata; require a complete
    // pair of references when an item is first bound to its design.
    $bound_design = $item->get_meta('_sanro_studio_design_id', true);
    if ($bound_design !== '' && $bound_design !== $design_id) {
        return ['valid' => false, 'error' => 'design_conflict'];
    }
    foreach ($refs as $side => $ref) {
        $existing = $item->get_meta('_sanro_studio_preview_' . $side . '_ref', true);
        if ($existing !== '' && $existing !== $ref) {
            return ['valid' => false, 'error' => 'preview_conflict'];
        }
    }
    $item->update_meta_data('_sanro_studio_design_id', $design_id);
    foreach ($refs as $side => $ref) {
        $item->update_meta_data('_sanro_studio_preview_' . $side . '_ref', $ref);
    }
    $saved = $item->save();
    if ($saved === false) {
        return ['valid' => false, 'error' => 'preview_save_failed'];
    }
    return ['valid' => true, 'item_id' => $item_id, 'attached_sides' => array_keys($refs)];
}

/** Render independent preview links for each WooCommerce line item. */
function sanro_studio_admin_item_preview_status($item_id, $item, $product) {
    if (!current_user_can('manage_woocommerce') || !is_object($item)
        || !method_exists($item, 'get_order_id')) return;
    $order_id = absint($item->get_order_id());
    $item_id = absint($item_id);
    if (!$order_id || !$item_id) return;
    $inventory = sanro_studio_order_preview_inventory($item);
    if (empty($inventory['valid'])) return;
    echo '<div class="sanro-studio-item-preview"><strong>' .
        esc_html('SANRO Studio — Preview item') . '</strong>';
    foreach (['front' => 'Depan', 'back' => 'Belakang'] as $side => $label) {
        if (empty($inventory[$side])) {
            echo '<p>' . esc_html($label . ': Belum tersedia') . '</p>';
            continue;
        }
        $url = add_query_arg([
            'action' => 'sanro_studio_order_preview',
            'order_id' => $order_id,
            'item_id' => $item_id,
            'side' => $side,
        ], admin_url('admin-post.php'));
        $url = wp_nonce_url($url, 'sanro_order_preview_' . $order_id . '_' . $side . '_item_' . $item_id);
        echo '<p><a target="_blank" rel="noopener noreferrer" href="' . esc_url($url) . '">' .
            esc_html('Lihat preview ' . $label) . '</a></p>';
    }
    echo '</div>';
}
