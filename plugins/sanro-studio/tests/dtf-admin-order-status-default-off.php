<?php
/** Default OFF and no WooCommerce/WP object access when feature is disabled. */
define('ABSPATH', __DIR__ . '/');
function current_user_can($capability) { return $capability === 'manage_woocommerce'; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
require dirname(__DIR__) . '/includes/dtf-admin-order-status.php';
if (sanro_studio_dtf_admin_status_enabled()
    || (sanro_studio_dtf_admin_item_status(null, 0)['error'] ?? null)
        !== 'admin_status_disabled') {
    fwrite(STDERR, "FAIL: DTF Woo admin candidate status enabled by default\n");
    exit(1);
}
ob_start();
sanro_studio_admin_item_dtf_status(71, null, null);
$html = ob_get_clean();
if ($html !== '') {
    fwrite(STDERR, "FAIL: disabled DTF candidate panel emitted output\n");
    exit(1);
}
echo "PASS: SANRO WooCommerce admin DTF candidate status defaults disabled\n";
