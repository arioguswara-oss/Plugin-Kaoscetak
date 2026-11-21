<?php
defined('ABSPATH') || exit;

/**
 * Sprint 138: admin-only READ-ONLY status of private order-item DTF candidates.
 * This is NOT a file download or preview route. Lightweight buyer previews
 * remain served by the existing nonce-protected order-preview.php handler.
 * There is no hook which creates, renders, alters or uploads production files.
 */
function sanro_studio_dtf_admin_status_enabled() {
    // Read-only ADMIN UI is an independent opt-in. Merely displaying
    // "candidate not available" must never activate raster, private writes,
    // Google Drive or order creation on staging.
    return defined('SANRO_STUDIO_ENABLE_DTF_ADMIN_STATUS')
        && SANRO_STUDIO_ENABLE_DTF_ADMIN_STATUS === true;
}

/**
 * Permission and order ownership are checked before accessing private files.
 * Return only allowlisted status fields, not a path, URL or actual PNG bytes.
 */
function sanro_studio_dtf_admin_item_status($order, $item_id) {
    if (!function_exists('current_user_can')
        || !current_user_can('manage_woocommerce')) {
        return ['valid' => false, 'error' => 'forbidden'];
    }
    if (!sanro_studio_dtf_admin_status_enabled()
        || !function_exists('sanro_studio_dtf_artifacts_enabled')
        || !sanro_studio_dtf_artifacts_enabled()
        || !function_exists('sanro_studio_dtf_order_item_private_status')) {
        return ['valid' => false, 'error' => 'admin_status_disabled'];
    }
    if (!is_object($order) || !method_exists($order, 'get_id')
        || !method_exists($order, 'get_items')
        || !is_int($item_id) || $item_id < 1
        || !is_int($order->get_id()) || $order->get_id() < 1) {
        return ['valid' => false, 'error' => 'invalid_order_item'];
    }
    $order_id = $order->get_id();
    $items = $order->get_items('line_item');
    if (!is_array($items) || !isset($items[$item_id])
        || !is_object($items[$item_id])) {
        return ['valid' => false, 'error' => 'invalid_order_item'];
    }
    $item = $items[$item_id];
    if (!method_exists($item, 'get_id') || (int) $item->get_id() !== $item_id
        || !method_exists($item, 'get_order_id')
        || (int) $item->get_order_id() !== $order_id
        || !method_exists($item, 'get_meta')) {
        return ['valid' => false, 'error' => 'invalid_order_item'];
    }
    $design_id = $item->get_meta('_sanro_studio_design_id', true);
    if (!is_string($design_id)
        || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $design_id)) {
        return ['valid' => false, 'error' => 'design_not_bound'];
    }
    $design_id = strtolower($design_id);

    // This existing internal API re-validates paid/ready order, locked design,
    // item binding, private folder mode, both PNG checksums and manifests.
    $result = sanro_studio_dtf_order_item_private_status($order, $item_id);
    if (!is_array($result) || empty($result['valid'])
        || ($result['complete'] ?? null) !== true
        || ($result['production_ready'] ?? null) !== false
        || ($result['quality_approved'] ?? null) !== false
        || !is_array($result['sides'] ?? null)
        || !is_int($result['candidate_count'] ?? null)
        || $result['candidate_count'] < 1 || $result['candidate_count'] > 2) {
        return ['valid' => false, 'error' => 'artifact_status_unavailable'];
    }

    $sides = []; $count = 0;
    foreach (['front', 'back'] as $side) {
        $value = $result['sides'][$side] ?? null;
        if (!is_array($value)) {
            return ['valid' => false, 'error' => 'artifact_status_unavailable'];
        }
        if (($value['state'] ?? null) === 'blank') {
            $sides[$side] = ['state' => 'blank'];
            continue;
        }
        if (($value['state'] ?? null) !== 'candidate'
            || ($value['private'] ?? null) !== true
            || ($value['order_id'] ?? null) !== $order_id
            || ($value['item_id'] ?? null) !== $item_id
            || ($value['design_id'] ?? null) !== $design_id
            || ($value['side'] ?? null) !== $side
            || ($value['quality_approved'] ?? null) !== false
            || ($value['font_parity_approved'] ?? null) !== false
            || ($value['dpi'] ?? null) !== 300
            || !is_string($value['sha256'] ?? null)
            || !preg_match('/^[a-f0-9]{64}$/D', $value['sha256'])
            || !is_int($value['width_px'] ?? null)
            || !is_int($value['height_px'] ?? null)
            || $value['width_px'] < 1 || $value['height_px'] < 1
            || $value['width_px'] > 4096 || $value['height_px'] > 4096
            || !is_numeric($value['width_cm'] ?? null)
            || !is_numeric($value['height_cm'] ?? null)
            || !is_finite((float) $value['width_cm'])
            || !is_finite((float) $value['height_cm'])
            || (float) $value['width_cm'] <= 0
            || (float) $value['height_cm'] <= 0) {
            return ['valid' => false, 'error' => 'artifact_status_unavailable'];
        }
        $count++;
        $sides[$side] = [
            'state' => 'candidate', 'width_px' => $value['width_px'],
            'height_px' => $value['height_px'],
            'width_cm' => (float) $value['width_cm'],
            'height_cm' => (float) $value['height_cm'],
            'dpi' => 300,
            'sha256_prefix' => substr($value['sha256'], 0, 12),
        ];
    }
    if ($count !== $result['candidate_count']) {
        return ['valid' => false, 'error' => 'artifact_status_unavailable'];
    }

    return [
        'valid' => true, 'order_id' => $order_id, 'item_id' => $item_id,
        'candidate_count' => $count, 'sides' => $sides,
        'production_ready' => false, 'font_parity_approved' => false,
    ];
}

/**
 * Show beside the EXISTING per-item lightweight-preview links, not instead
 * of them. WooCommerce order CRUD ensures compatibility with HPOS.
 */
function sanro_studio_admin_item_dtf_status($item_id, $item, $product) {
    if (!function_exists('current_user_can')
        || !current_user_can('manage_woocommerce')
        || !sanro_studio_dtf_admin_status_enabled()
        || !is_object($item) || !method_exists($item, 'get_order_id')
        || !method_exists($item, 'get_id')
        || !method_exists($item, 'get_meta')
        || !function_exists('wc_get_order')
        || !function_exists('esc_html')) return;

    // Only SANRO Studio line items, never unrelated ordinary WooCommerce sales.
    $design = $item->get_meta('_sanro_studio_design_id', true);
    if (!is_string($design)
        || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $design)) return;
    $id = (int) $item_id;
    $order_id = (int) $item->get_order_id();
    if ($id < 1 || $order_id < 1 || (int) $item->get_id() !== $id) return;
    $order = wc_get_order($order_id);
    $status = sanro_studio_dtf_admin_item_status($order, $id);
    // No raw errors/paths are shown in wp-admin. Missing, invalid and corrupt
    // candidates all display as unavailable, never as partially "ready".
    echo '<div class="sanro-studio-admin-dtf-status">';
    echo '<strong>' . esc_html('SANRO Studio — Kandidat DTF privat') . '</strong>';
    if (empty($status['valid'])) {
        echo '<p>' . esc_html('File DTF belum tersedia atau perlu pemeriksaan.') . '</p>';
    } else {
        foreach (['front' => 'Depan', 'back' => 'Belakang'] as $side => $label) {
            $meta = $status['sides'][$side];
            if ($meta['state'] === 'blank') {
                echo '<p>' . esc_html($label . ': Tanpa desain') . '</p>';
                continue;
            }
            $line = $label . ': Kandidat tersimpan privat — '
                . $meta['width_px'] . ' × ' . $meta['height_px'] . ' px, '
                . $meta['dpi'] . ' DPI, SHA-256 ' . $meta['sha256_prefix'] . '…';
            echo '<p>' . esc_html($line) . '</p>';
        }
    }
    echo '<p><em>' . esc_html('Belum disetujui untuk cetak. Gunakan tautan Preview item yang tersedia di atas untuk melihat gambar ringan.') . '</em></p>';
    echo '</div>';
}
