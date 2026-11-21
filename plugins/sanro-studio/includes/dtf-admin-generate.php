<?php
defined('ABSPATH') || exit;

/**
 * Sprint 143 — manually generate PRIVATE, UNAPPROVED DTF raster candidates.
 *
 * Feature-gated by a separate administrator opt-in, OFF by default.
 * No REST route, AJAX route, buyer action, cron, payment processing, Drive
 * upload, public file link, automatic production approval, or Woo order write.
 */
function sanro_studio_dtf_admin_generate_enabled() {
    return defined('SANRO_STUDIO_ENABLE_DTF_ADMIN_GENERATE')
        && SANRO_STUDIO_ENABLE_DTF_ADMIN_GENERATE === true
        && function_exists('sanro_studio_dtf_order_batch_enabled')
        && sanro_studio_dtf_order_batch_enabled();
}

/** Strictly verify ID string supplied by a WordPress admin POST. */
function sanro_studio_dtf_admin_positive_id($raw) {
    if (!is_string($raw)
        || !preg_match('/^[1-9][0-9]{0,8}$/D', $raw)) return false;
    $id = (int) $raw;
    return $id > 0 && (string) $id === $raw ? $id : false;
}

/**
 * GET-safe, read-only preflight. Enforces a paid or explicitly ready
 * WooCommerce order, immutable draft bound to the exact persisted order item,
 * and valid locked front/back geometry BEFORE a button may appear.
 */
function sanro_studio_dtf_admin_generate_preflight($order, $item_id) {
    if (!function_exists('current_user_can')
        || !current_user_can('manage_woocommerce')) {
        return ['valid' => false, 'error' => 'forbidden'];
    }
    if (!sanro_studio_dtf_admin_generate_enabled()) {
        return ['valid' => false, 'error' => 'admin_generation_disabled'];
    }
    if (!is_object($order) || !method_exists($order, 'get_id')
        || !method_exists($order, 'get_items')
        || !method_exists($order, 'get_edit_order_url')
        || !is_int($item_id) || $item_id < 1
        || !is_int($order->get_id()) || $order->get_id() < 1) {
        return ['valid' => false, 'error' => 'invalid_order_item'];
    }
    $items = $order->get_items('line_item');
    if (!is_array($items) || !isset($items[$item_id])
        || !is_object($items[$item_id])
        || !method_exists($items[$item_id], 'get_id')
        || !method_exists($items[$item_id], 'get_order_id')
        || (int) $items[$item_id]->get_id() !== $item_id
        || (int) $items[$item_id]->get_order_id() !== $order->get_id()) {
        return ['valid' => false, 'error' => 'invalid_order_item'];
    }
    if (!function_exists('sanro_studio_dtf_artifact_binding')
        || !function_exists('sanro_studio_dtf_artifact_directory')
        || !sanro_studio_dtf_artifact_directory()) {
        return ['valid' => false, 'error' => 'private_storage_unavailable'];
    }
    $printable = 0;
    foreach (['front', 'back'] as $side) {
        $binding = sanro_studio_dtf_artifact_binding($order, $item_id, $side);
        if (!is_array($binding) || empty($binding['valid'])) {
            return ['valid' => false, 'error' => 'order_design_not_eligible'];
        }
        if (empty($binding['skipped'])) $printable++;
    }
    if ($printable === 0) {
        return ['valid' => false, 'error' => 'design_has_no_printable_sides'];
    }
    return [
        'valid' => true,
        'order_id' => $order->get_id(),
        'item_id' => $item_id,
        'printable_sides' => $printable,
    ];
}

/** Only invoked from a nonce-checked admin POST, never on page load. */
function sanro_studio_dtf_admin_generate_candidates($order, $item_id) {
    $allowed = sanro_studio_dtf_admin_generate_preflight($order, $item_id);
    if (empty($allowed['valid'])) return $allowed;
    if (!function_exists('sanro_studio_dtf_prepare_order_item_candidates')
        || !function_exists('sanro_studio_dtf_order_item_private_status')) {
        return ['valid' => false, 'error' => 'dtf_worker_unavailable'];
    }
    // Internal worker checks binding and feature flags again on each side.
    $result = sanro_studio_dtf_prepare_order_item_candidates($order, $item_id);
    if (!is_array($result) || empty($result['valid'])
        || ($result['complete'] ?? null) !== true
        || ($result['production_ready'] ?? null) !== false
        || ($result['quality_approved'] ?? null) !== false
        || ($result['candidate_count'] ?? null) !== $allowed['printable_sides']) {
        return ['valid' => false, 'error' => 'candidate_generation_failed'];
    }
    // Independently rehash private PNGs and cross-check both sides: no paths,
    // raw bytes or production-approved state may leave the worker.
    $verified = sanro_studio_dtf_order_item_private_status($order, $item_id);
    if (!is_array($verified) || empty($verified['valid'])
        || ($verified['complete'] ?? null) !== true
        || ($verified['production_ready'] ?? null) !== false
        || ($verified['quality_approved'] ?? null) !== false
        || ($verified['candidate_count'] ?? null) !== $allowed['printable_sides']) {
        return ['valid' => false, 'error' => 'candidate_readback_failed'];
    }
    foreach (['front', 'back'] as $side) {
        $a = $result['sides'][$side] ?? null;
        $b = $verified['sides'][$side] ?? null;
        if (!is_array($a) || !is_array($b)
            || ($a['state'] ?? null) !== ($b['state'] ?? null)) {
            return ['valid' => false, 'error' => 'candidate_readback_failed'];
        }
        if (($a['state'] ?? null) === 'candidate'
            && (!is_string($a['sha256'] ?? null)
                || !is_string($b['sha256'] ?? null)
                || !hash_equals($a['sha256'], $b['sha256']))) {
            return ['valid' => false, 'error' => 'candidate_readback_failed'];
        }
    }
    return [
        'valid' => true, 'order_id' => $allowed['order_id'],
        'item_id' => $item_id, 'candidate_count' => $allowed['printable_sides'],
        'production_ready' => false, 'quality_approved' => false,
    ];
}

/** Collect independent admin form targets without nesting inside Woo forms. */
function sanro_studio_dtf_admin_generation_forms($pair = null) {
    static $pairs = [];
    if ($pair !== null && is_array($pair)
        && isset($pair['order_id'], $pair['item_id'])
        && is_int($pair['order_id']) && $pair['order_id'] > 0
        && is_int($pair['item_id']) && $pair['item_id'] > 0) {
        $key = $pair['order_id'] . '-' . $pair['item_id'];
        $pairs[$key] = $pair;
    }
    return $pairs;
}

/** Optional button OUTSIDE the status panel; does not expose private files. */
function sanro_studio_admin_item_generate_dtf_action($item_id, $item, $product) {
    if (!sanro_studio_dtf_admin_generate_enabled()
        || !is_int($item_id) || $item_id < 1
        || !is_object($item) || !method_exists($item, 'get_order_id')
        || !function_exists('wc_get_order')
        || !function_exists('esc_attr') || !function_exists('esc_html')) return;
    $order_id = (int) $item->get_order_id();
    if ($order_id < 1) return;
    $order = wc_get_order($order_id);
    $allowed = sanro_studio_dtf_admin_generate_preflight($order, $item_id);
    if (empty($allowed['valid'])) return;
    sanro_studio_dtf_admin_generation_forms($allowed);
    $key = $order_id . '-' . $item_id;
    echo '<div class="sanro-studio-admin-dtf-generate">';
    echo '<p>' . esc_html('Kandidat DTF privat hanya untuk pemeriksaan. Belum disetujui untuk cetak.') . '</p>';
    echo '<button type="submit" class="button" form="sanro-studio-dtf-generate-'
        . esc_attr($key) . '">' . esc_html('Buat / Verifikasi Kandidat DTF') . '</button>';
    echo '</div>';
}

/** Separate POST forms live in wp-admin footer, avoiding nested WC forms. */
function sanro_studio_dtf_admin_generate_footer_forms() {
    if (!sanro_studio_dtf_admin_generate_enabled()
        || !function_exists('current_user_can')
        || !current_user_can('manage_woocommerce')
        || !function_exists('wc_get_order')
        || !function_exists('admin_url')
        || !function_exists('esc_url')
        || !function_exists('esc_attr')
        || !function_exists('wp_nonce_field')) return;
    foreach (sanro_studio_dtf_admin_generation_forms() as $pair) {
        $order = wc_get_order($pair['order_id']);
        if (empty(sanro_studio_dtf_admin_generate_preflight(
            $order, $pair['item_id'])['valid'])) continue;
        $key = $pair['order_id'] . '-' . $pair['item_id'];
        echo '<form id="sanro-studio-dtf-generate-' . esc_attr($key)
            . '" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="sanro_studio_generate_dtf_candidates">';
        echo '<input type="hidden" name="order_id" value="' . esc_attr((string)$pair['order_id']) . '">';
        echo '<input type="hidden" name="item_id" value="' . esc_attr((string)$pair['item_id']) . '">';
        wp_nonce_field('sanro_studio_generate_dtf_' . $key);
        echo '</form>';
    }
}

/**
 * Admin-only POST handler. Fails closed on disabled flags, CSRF, wrong item,
 * unpaid/not-ready order, unlocked draft or unsafe private directory.
 * No payment status, WooCommerce data, or production approval is written.
 */
function sanro_studio_admin_generate_dtf_candidates_post() {
    if (!function_exists('current_user_can')
        || !current_user_can('manage_woocommerce')) {
        wp_die('Akses ditolak.', '', ['response' => 403]);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        wp_die('Gunakan formulir admin.', '', ['response' => 405]);
    }
    if (!sanro_studio_dtf_admin_generate_enabled()) {
        wp_die('Pembuatan kandidat DTF belum diaktifkan.', '', ['response' => 403]);
    }
    $order_id = sanro_studio_dtf_admin_positive_id($_POST['order_id'] ?? null);
    $item_id = sanro_studio_dtf_admin_positive_id($_POST['item_id'] ?? null);
    if (!$order_id || !$item_id) {
        wp_die('Nomor pesanan atau item tidak valid.', '', ['response' => 400]);
    }
    check_admin_referer('sanro_studio_generate_dtf_' . $order_id . '-' . $item_id);
    $order = function_exists('wc_get_order') ? wc_get_order($order_id) : false;
    $allowed = sanro_studio_dtf_admin_generate_preflight($order, $item_id);
    if (empty($allowed['valid'])) {
        wp_die('Pesanan atau desain tidak memenuhi syarat kandidat DTF.', '', ['response' => 409]);
    }
    if (!method_exists($order, 'get_edit_order_url')) {
        wp_die('Halaman pesanan tidak tersedia.', '', ['response' => 500]);
    }
    $generated = sanro_studio_dtf_admin_generate_candidates($order, $item_id);
    if (empty($generated['valid'])) {
        wp_die('Kandidat DTF belum dapat diverifikasi. Tidak ada file yang disetujui untuk cetak.', '',
            ['response' => 409]);
    }
    wp_safe_redirect($order->get_edit_order_url(), 303);
    exit;
}
