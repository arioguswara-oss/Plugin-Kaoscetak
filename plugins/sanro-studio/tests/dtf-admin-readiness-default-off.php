<?php
/** Sprint 146 default-off safety: no DB, no WordPress and no output. */
define('ABSPATH', __DIR__ . '/');
$GLOBALS['calls146off'] = 0;
function current_user_can($cap) { return $cap === 'manage_woocommerce'; }
function sanro_studio_dtf_admin_status_enabled() { return true; }
function wc_get_order($id) { $GLOBALS['calls146off']++; return false; }
function get_option($key, $fallback=false) {
    $GLOBALS['calls146off']++; return $fallback;
}
function esc_html($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
require dirname(__DIR__) . '/includes/dtf-admin-readiness.php';

$count146off = 0;
function off146($condition, $label) {
    global $count146off;
    if (!$condition) { fwrite(STDERR, "FAIL: DTF readiness default-OFF: $label\n"); exit(1); }
    $count146off++;
}
class Item146Off {
    function get_id() { return 71; }
    function get_order_id() { return 52; }
    function get_meta($key, $single = true) {
        return $key === '_sanro_studio_design_id'
            ? '123e4567-e89b-42d3-a456-123456789012' : '';
    }
}
off146(!defined('SANRO_STUDIO_ENABLE_DTF_ADMIN_READINESS'),
    'feature flag must not default to true');
off146(!sanro_studio_dtf_admin_readiness_enabled(),
    'status panel enabled alone must not activate readiness');
off146(sanro_studio_dtf_admin_readiness_report(null,71)['code'] === 'disabled',
    'no draft probing when readiness disabled');
ob_start(); sanro_studio_admin_item_dtf_readiness(71,new Item146Off(),null);
off146(ob_get_clean() === '', 'no additional admin panel by default');
off146($GLOBALS['calls146off'] === 0,
    'default-OFF does not look up WooCommerce orders or drafts');
echo 'PASS: ' . $count146off . " SANRO DTF readiness default-OFF checks\n";
