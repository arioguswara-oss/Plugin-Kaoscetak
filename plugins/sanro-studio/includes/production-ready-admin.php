<?php
defined('ABSPATH') || exit;

/**
 * Determine whether a WooCommerce administrator may explicitly mark an
 * unpaid SANRO design order production-ready. Read-only; no payment changes,
 * Drive folder creation, or browser-facing draft credentials.
 */
function sanro_studio_production_ready_admin_preflight($order) {
    if (!is_object($order) || !method_exists($order, 'get_id')
        || !method_exists($order, 'get_status')
        || !method_exists($order, 'is_paid')
        || !method_exists($order, 'get_items')
        || !method_exists($order, 'get_meta')
        || (int) $order->get_id() < 1) {
        return ['valid' => false, 'error' => 'invalid_order'];
    }
    if ($order->is_paid() === true) {
        return ['valid' => false, 'error' => 'already_paid'];
    }
    // Never reopen cancelled/refunded/failed/completed orders through the
    // production-ready shortcut. WooCommerce supports pending and on-hold.
    if (!in_array($order->get_status(), ['pending', 'on-hold'], true)) {
        return ['valid' => false, 'error' => 'order_status_not_allowed'];
    }
    if ($order->get_meta('_sanro_studio_production_ready', true) === 'yes') {
        return ['valid' => false, 'error' => 'already_ready'];
    }
    if (!function_exists('sanro_studio_order_item_production_manifest')) {
        return ['valid' => false, 'error' => 'production_manifest_unavailable'];
    }
    $items = $order->get_items('line_item');
    if (!is_array($items) || !$items) {
        return ['valid' => false, 'error' => 'missing_design_preview'];
    }
    $studio_count = 0;
    foreach ($items as $item_id => $item) {
        if (!is_int($item_id) || $item_id < 1 || !is_object($item)
            || !method_exists($item, 'get_meta')) {
            return ['valid' => false, 'error' => 'invalid_order_item'];
        }
        $design_id = $item->get_meta('_sanro_studio_design_id', true);
        $front_ref = $item->get_meta('_sanro_studio_preview_front_ref', true);
        $back_ref = $item->get_meta('_sanro_studio_preview_back_ref', true);
        // Ordinary WooCommerce items without SANRO metadata are unrelated.
        // A SANRO order is ready only if EVERY affected design line is valid.
        if ($design_id === '' && $front_ref === '' && $back_ref === '') continue;
        $studio_count++;
        $manifest = sanro_studio_order_item_production_manifest($order, $item_id);
        if (empty($manifest['valid'])
            || (empty($manifest['preview_front_available'])
                && empty($manifest['preview_back_available']))) {
            return ['valid' => false, 'error' => 'missing_design_preview'];
        }
    }
    return $studio_count > 0
        ? ['valid' => true, 'order_id' => (int) $order->get_id()]
        : ['valid' => false, 'error' => 'missing_design_preview'];
}

/**
 * Render a real, explicit, nonce-protected WooCommerce admin action.
 * Nothing is written until the administrator submits this POST form.
 */
function sanro_studio_production_ready_form_order_id($set = null) {
    static $order_id = 0;
    if ($set !== null) $order_id = is_int($set) && $set > 0 ? $set : 0;
    return $order_id;
}

function sanro_studio_admin_production_ready_action($order) {
    if (!function_exists('current_user_can') || !current_user_can('manage_woocommerce')) return;
    $allowed = sanro_studio_production_ready_admin_preflight($order);
    if (empty($allowed['valid'])) return;
    $order_id = $allowed['order_id'];
    // WooCommerce order edit is itself inside a WordPress form. Nesting forms
    // would cause the browser to submit the wrong endpoint. The button below
    // explicitly targets a separate form rendered later in admin_footer.
    sanro_studio_production_ready_form_order_id($order_id);
    echo '<div class="sanro-studio-production-ready-admin">';
    echo '<h3>' . esc_html('SANRO Studio — Persetujuan produksi') . '</h3>';
    echo '<p>' . esc_html('Tindakan ini menandai pesanan Siap Produksi tanpa mengubah status pembayaran atau membuat folder Google Drive.') . '</p>';
    echo '<button type="submit" form="sanro-studio-production-ready-form-' . esc_attr((string) $order_id)
        . '" class="button button-primary">' . esc_html('Tandai Siap Produksi') . '</button>';
    echo '</div>';
}

/** Separate footer form avoids invalid nested forms on the WooCommerce order page. */
function sanro_studio_admin_production_ready_footer_form() {
    if (!function_exists('current_user_can') || !current_user_can('manage_woocommerce')
        || !function_exists('admin_url') || !function_exists('wp_nonce_field')) return;
    $order_id = sanro_studio_production_ready_form_order_id();
    if ($order_id < 1 || !function_exists('wc_get_order')) return;
    $order = wc_get_order($order_id);
    $allowed = sanro_studio_production_ready_admin_preflight($order);
    if (empty($allowed['valid'])) return;
    echo '<form id="sanro-studio-production-ready-form-' . esc_attr((string) $order_id)
        . '" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="sanro_studio_mark_production_ready">';
    echo '<input type="hidden" name="order_id" value="' . esc_attr((string) $order_id) . '">';
    wp_nonce_field('sanro_studio_mark_production_ready_' . $order_id);
    echo '</form>';
}

/**
 * Admin-only POST boundary. Refreshes the WooCommerce order and checks nonce,
 * capability, status and bound SANRO preview again before saving readiness.
 * HPOS-compatible: use WooCommerce CRUD, never update_post_meta().
 */
function sanro_studio_admin_mark_production_ready() {
    if (!function_exists('current_user_can') || !current_user_can('manage_woocommerce')) {
        wp_die('Akses ditolak.', '', ['response' => 403]);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        wp_die('Gunakan formulir admin.', '', ['response' => 405]);
    }
    $raw_id = $_POST['order_id'] ?? null;
    if (!is_string($raw_id) || !ctype_digit($raw_id)
        || (int) $raw_id < 1 || (string) ((int) $raw_id) !== $raw_id) {
        wp_die('Nomor pesanan tidak valid.', '', ['response' => 400]);
    }
    $order_id = (int) $raw_id;
    check_admin_referer('sanro_studio_mark_production_ready_' . $order_id);
    $order = function_exists('wc_get_order') ? wc_get_order($order_id) : false;
    $allowed = sanro_studio_production_ready_admin_preflight($order);
    if (empty($allowed['valid'])) {
        wp_die('Pesanan tidak memenuhi syarat persetujuan produksi.', '', ['response' => 409]);
    }
    if (!function_exists('sanro_studio_mark_order_production_ready')) {
        wp_die('Modul produksi belum tersedia.', '', ['response' => 500]);
    }
    if (!method_exists($order, 'get_edit_order_url')) {
        wp_die('Halaman admin pesanan tidak tersedia.', '', ['response' => 500]);
    }
    $marked = sanro_studio_mark_order_production_ready($order);
    if (empty($marked['valid'])) {
        wp_die('Status siap produksi gagal disimpan.', '', ['response' => 500]);
    }
    wp_safe_redirect($order->get_edit_order_url(), 303);
    exit;
}
