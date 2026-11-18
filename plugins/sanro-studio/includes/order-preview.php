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
            : 'Referensi privat tercatat; tampilan gambar menunggu endpoint terotorisasi';
        echo '<p>' . esc_html($label . ': ' . $state) . '</p>';
    }
    echo '</div>';
}
