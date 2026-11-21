<?php
defined('ABSPATH') || exit;

/**
 * Sprint 146 — explicit opt-in, READ-ONLY WooCommerce per-item DTF readiness.
 *
 * This checks buyer/order binding BEFORE enabling private DTF generation. It
 * does not render PNG, read private masters, write order meta, update a draft,
 * create an output directory, approve print quality or call Google Drive.
 * All returned fields use a short public allowlist, never owner tokens, the
 * draft record, design UUID, product/customer identifiers or filesystem paths.
 */
function sanro_studio_dtf_admin_readiness_enabled() {
    return defined('SANRO_STUDIO_ENABLE_DTF_ADMIN_READINESS')
        && SANRO_STUDIO_ENABLE_DTF_ADMIN_READINESS === true
        && function_exists('sanro_studio_dtf_admin_status_enabled')
        && sanro_studio_dtf_admin_status_enabled();
}

/** Returns safe milestone codes only. Never says an output is print-ready. */
function sanro_studio_dtf_admin_readiness_report($order, $item_id) {
    if (!function_exists('current_user_can')
        || !current_user_can('manage_woocommerce')) {
        return ['valid' => false, 'stage' => 'access', 'code' => 'forbidden'];
    }
    if (!sanro_studio_dtf_admin_readiness_enabled()) {
        return ['valid' => false, 'stage' => 'configuration', 'code' => 'disabled'];
    }
    if (!is_object($order) || !method_exists($order, 'get_id')
        || !method_exists($order, 'get_items')
        || !is_int($order->get_id()) || $order->get_id() < 1
        || !is_int($item_id) || $item_id < 1) {
        return ['valid' => false, 'stage' => 'identity', 'code' => 'invalid_order_item'];
    }
    $items = $order->get_items('line_item');
    if (!is_array($items) || !isset($items[$item_id])
        || !is_object($items[$item_id])
        || !method_exists($items[$item_id], 'get_id')
        || !method_exists($items[$item_id], 'get_order_id')
        || !method_exists($items[$item_id], 'get_meta')
        || !method_exists($items[$item_id], 'get_product_id')
        || (int) $items[$item_id]->get_id() !== $item_id
        || (int) $items[$item_id]->get_order_id() !== $order->get_id()) {
        return ['valid' => false, 'stage' => 'identity', 'code' => 'invalid_order_item'];
    }
    $item = $items[$item_id];
    $design = $item->get_meta('_sanro_studio_design_id', true);
    $product = $item->get_product_id();
    if (!is_string($design)
        || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $design)
        || !is_int($product) || $product < 1) {
        return ['valid' => false, 'stage' => 'identity', 'code' => 'product_design_missing'];
    }

    // Payment/ready check is an existing order-only policy and does NOT
    // activate the Google Drive provisioning feature.
    if (!function_exists('sanro_studio_auto_drive_order_eligible')
        || !sanro_studio_auto_drive_order_eligible($order)) {
        return ['valid' => false, 'stage' => 'order', 'code' => 'order_not_paid_or_ready'];
    }
    if (!function_exists('get_option')
        || !function_exists('sanro_studio_locked_record_has_item')
        || !function_exists('sanro_studio_validate_product_design')) {
        return ['valid' => false, 'stage' => 'draft', 'code' => 'draft_check_unavailable'];
    }
    $record = get_option('sanro_studio_draft_' . $design, false);
    if (!is_array($record) || ($record['status'] ?? null) !== 'locked'
        || ($record['order_id'] ?? null) !== $order->get_id()
        || ($record['product_id'] ?? null) !== $product
        || !sanro_studio_locked_record_has_item($record, $item_id)
        || !is_array($record['design'] ?? null)) {
        return ['valid' => false, 'stage' => 'draft', 'code' => 'draft_not_locked_or_bound'];
    }
    if (empty(sanro_studio_validate_product_design(
        $product, $record['design'])['valid'])) {
        return ['valid' => false, 'stage' => 'design', 'code' => 'design_geometry_invalid'];
    }
    $sides = [];
    $printable = 0;
    foreach (['front', 'back'] as $side) {
        $objects = $record['design'][$side]['objects'] ?? null;
        if (!is_array($objects) || count($objects) > 2) {
            return ['valid' => false, 'stage' => 'design', 'code' => 'design_geometry_invalid'];
        }
        $sides[$side] = $objects ? 'artwork' : 'blank';
        if ($objects) $printable++;
    }
    if ($printable === 0) {
        return ['valid' => false, 'stage' => 'design', 'code' => 'no_printable_sides'];
    }
    // Never report a press-ready file: this is only an order/draft preflight.
    $generation = function_exists('sanro_studio_dtf_admin_generate_enabled')
        && sanro_studio_dtf_admin_generate_enabled();
    return [
        'valid' => true, 'stage' => 'preflight',
        'code' => $generation ? 'binding_prerequisites_pass' : 'generator_disabled',
        'printable_sides' => $printable, 'sides' => $sides,
        'production_ready' => false, 'quality_approved' => false,
    ];
}

/** Render a safe human-readable diagnostic, not a generator or download UI. */
function sanro_studio_admin_item_dtf_readiness($item_id, $item, $product) {
    if (!sanro_studio_dtf_admin_readiness_enabled()
        || !function_exists('current_user_can')
        || !current_user_can('manage_woocommerce')
        || !is_int($item_id) || $item_id < 1
        || !is_object($item) || !method_exists($item, 'get_order_id')
        || !method_exists($item, 'get_id')
        || !method_exists($item, 'get_meta')
        || !function_exists('wc_get_order')
        || !function_exists('esc_html')
        || (int) $item->get_id() !== $item_id) return;
    $design = $item->get_meta('_sanro_studio_design_id', true);
    // Never display anything on unrelated WooCommerce order items.
    if (!is_string($design)
        || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $design)) return;
    $order_id = (int) $item->get_order_id();
    if ($order_id < 1) return;
    $report = sanro_studio_dtf_admin_readiness_report(
        wc_get_order($order_id), $item_id);
    $messages = [
        'disabled' => 'Pemeriksaan kesiapan belum diaktifkan.',
        'invalid_order_item' => 'Item pesanan tidak cocok.',
        'product_design_missing' => 'Produk atau identitas desain belum sesuai.',
        'order_not_paid_or_ready' => 'Pesanan belum dibayar atau belum dinyatakan siap produksi.',
        'draft_check_unavailable' => 'Pemeriksaan draft tidak tersedia.',
        'draft_not_locked_or_bound' => 'Desain belum terkunci atau belum terkait ke item pesanan.',
        'design_geometry_invalid' => 'Ukuran atau posisi desain belum valid.',
        'no_printable_sides' => 'Desain depan dan belakang kosong.',
        'generator_disabled' => 'Identitas pesanan dan draft sesuai; generator DTF masih OFF.',
        'binding_prerequisites_pass' => 'Prasyarat pesanan dan draft sesuai; rendering dan kualitas cetak masih perlu diverifikasi.',
    ];
    $code = is_array($report) ? ($report['code'] ?? '') : '';
    $message = isset($messages[$code])
        ? $messages[$code] : 'Kesiapan kandidat DTF belum dapat diverifikasi.';
    echo '<div class="sanro-studio-admin-dtf-readiness">';
    echo '<strong>' . esc_html('SANRO Studio — Diagnostik prasyarat DTF') . '</strong>';
    echo '<p>' . esc_html($message) . '</p>';
    if (!empty($report['valid']) && is_array($report['sides'] ?? null)) {
        foreach (['front' => 'Depan', 'back' => 'Belakang'] as $side => $label) {
            $display = ($report['sides'][$side] ?? null) === 'artwork'
                ? 'Desain terikat' : 'Tanpa desain';
            echo '<p>' . esc_html($label . ': ' . $display) . '</p>';
        }
    }
    echo '<p><em>' . esc_html('Hanya pemeriksaan baca-saja. Tidak menghasilkan atau menyetujui file cetak.') . '</em></p>';
    echo '</div>';
}
