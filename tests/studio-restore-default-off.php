<?php
// Offline integration guard; no WordPress installation or database is needed.
define('ABSPATH', __DIR__ . '/');
$GLOBALS['studio_hooks'] = array();
function add_action($name, $callback, $priority = 10, $accepted_args = 1) {
    $GLOBALS['studio_hooks'][$name][] = $callback;
}
function add_filter($name, $callback, $priority = 10, $accepted_args = 1) {
    $GLOBALS['studio_hooks'][$name][] = $callback;
}
require dirname(__DIR__) . '/plugins/sanro-studio/sanro-studio.php';
function studio_restore_check($condition, $label) {
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
}
foreach (get_defined_constants() as $name => $value) {
    studio_restore_check(strpos($name, 'SANRO_STUDIO_ENABLE_') !== 0, 'feature flags remain undefined');
}
foreach (array(
    'woocommerce_single_product_summary',
    'template_redirect',
    'woocommerce_before_calculate_totals',
    'woocommerce_get_item_data',
    'woocommerce_checkout_process',
    'woocommerce_checkout_create_order_line_item',
    'woocommerce_checkout_create_order',
    'woocommerce_checkout_order_created',
) as $name) {
    studio_restore_check(empty($GLOBALS['studio_hooks'][$name]), $name . ' remains inactive');
}
studio_restore_check(in_array('sanro_studio_bootstrap_admin_menu',
    $GLOBALS['studio_hooks']['admin_menu'] ?? array(), true), 'integration admin status retained');
studio_restore_check(in_array('sanro_studio_save_product_pricing',
    $GLOBALS['studio_hooks']['woocommerce_admin_process_product_object'] ?? array(), true),
    'pricing profile admin save hook registered');
echo "PASS: SANRO Studio restore defaults and hooks\n";
