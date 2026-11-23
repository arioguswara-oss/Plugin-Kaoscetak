<?php
// Offline bootstrap contract: no WordPress installation or database needed.
define('ABSPATH', __DIR__ . '/');
$GLOBALS['hooks'] = array();
function add_action($hook, $callback) { $GLOBALS['hooks'][$hook][] = $callback; }
$GLOBALS['allow_admin'] = true;
function current_user_can($cap) { return $cap === 'manage_woocommerce' && $GLOBALS['allow_admin']; }
function esc_html__($s, $domain) { return $s; }
function __($s, $domain) { return $s; }
function esc_html($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function add_submenu_page($parent, $title, $menu, $cap, $slug, $callback) {
    $GLOBALS['menu'] = compact('parent', 'title', 'menu', 'cap', 'slug', 'callback');
}
function wp_die($message) { throw new RuntimeException($message); }
require dirname(__DIR__) . '/plugins/sanro-studio/sanro-studio.php';
function check($condition, $label) {
    if (!$condition) { throw new RuntimeException('FAIL: ' . $label); }
}
check(in_array('sanro_studio_bootstrap_admin_menu', $GLOBALS['hooks']['admin_menu'] ?? array(), true), 'status admin hook');
check(in_array('sanro_studio_admin_menu', $GLOBALS['hooks']['admin_menu'] ?? array(), true), 'native settings admin hook');
sanro_studio_bootstrap_admin_menu();
check($GLOBALS['menu']['cap'] === 'manage_woocommerce', 'admin capability');
check($GLOBALS['menu']['parent'] === 'woocommerce', 'WooCommerce menu');
$status = sanro_studio_bootstrap_status();
check(count($status) === 5, 'five status fields');
foreach (array('editor', 'cart', 'checkout', 'production') as $key) {
    check($status[$key] === 'not_integrated', 'no premature feature activation: ' . $key);
}
ob_start();
sanro_studio_bootstrap_admin_page();
$html = ob_get_clean();
check(strpos($html, 'does not enable buyer editing') !== false, 'explicit inactive state');
check(strpos($html, 'not_integrated') !== false, 'read-only status rendered');
$GLOBALS['allow_admin'] = false;
$denied = false;
try {
    sanro_studio_bootstrap_admin_page();
} catch (RuntimeException $e) {
    $denied = strpos($e->getMessage(), 'Insufficient permissions') !== false;
}
check($denied, 'non-admin cannot access status page');
echo "PASS: SANRO Studio isolated admin bootstrap contract\n";
