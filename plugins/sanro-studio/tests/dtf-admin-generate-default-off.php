<?php
/** Sprint 143: new action MUST default to OFF even when batch exists. */
define('ABSPATH', __DIR__ . '/');
$GLOBALS['checks143off'] = 0;
function check143off($okay, $label) {
    if (!$okay) { fwrite(STDERR, "FAIL: default OFF: $label\n"); exit(1); }
    $GLOBALS['checks143off']++;
}
function current_user_can($cap) { return true; }
function sanro_studio_dtf_order_batch_enabled() { return true; }
function wp_die($message, $title = '', $args = []) {
    throw new RuntimeException('WP_DIE_' . ($args['response'] ?? 500));
}
require dirname(__DIR__) . '/includes/dtf-admin-generate.php';
check143off(!defined('SANRO_STUDIO_ENABLE_DTF_ADMIN_GENERATE'),
    'no automatic feature opt-in');
check143off(sanro_studio_dtf_admin_generate_enabled() === false,
    'batch alone never activates admin writer');
check143off(sanro_studio_dtf_admin_generate_preflight(null, 71)['error']
    === 'admin_generation_disabled', 'disabled flag blocks preflight');
ob_start();
sanro_studio_admin_item_generate_dtf_action(71, new stdClass(), null);
check143off(ob_get_clean() === '', 'no button appears when disabled');
ob_start();
sanro_studio_dtf_admin_generate_footer_forms();
check143off(ob_get_clean() === '', 'no hidden POST form appears when disabled');
$_SERVER['REQUEST_METHOD'] = 'POST';
try {
    sanro_studio_admin_generate_dtf_candidates_post();
    check143off(false, 'disabled action must reject POST');
} catch (RuntimeException $e) {
    check143off($e->getMessage() === 'WP_DIE_403',
        'disabled admin action fails before credentials or file IO');
}
echo 'PASS: ' . $GLOBALS['checks143off'] . " SANRO admin DTF generation default-OFF checks\n";
