<?php
// Offline contract for the product pricing-profile opt-in. No WordPress or database needed.
define('ABSPATH', __DIR__ . '/');
$GLOBALS['hooks'] = array();
$GLOBALS['meta'] = array();
$GLOBALS['can_edit'] = true;
function add_action($hook, $callback, $priority = 10) { $GLOBALS['hooks'][$hook][] = $callback; }
function add_submenu_page() {}
function __($s, $d = null) { return $s; }
function esc_html__($s, $d = null) { return $s; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function current_user_can($cap, $id = null) { return $GLOBALS['can_edit']; }
function wp_unslash($v) { return $v; }
function get_the_ID() { return 7; }
function get_post_meta($id, $key, $single) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function woocommerce_wp_select($args) { $GLOBALS['select'] = $args; }
class FakeProduct {
    public $saved = array();
    public function get_id() { return 7; }
    public function update_meta_data($key, $value) { $this->saved[$key] = $value; }
}
require dirname(__DIR__) . '/plugins/sanro-studio/sanro-studio.php';
function check($condition, $label) {
    if (!$condition) { throw new RuntimeException('FAIL: ' . $label); }
}
$product_hook = 'woocommerce_product_options_general_product_data';
check(in_array('sanro_studio_product_pricing_fields', $GLOBALS['hooks'][$product_hook] ?? array(), true), 'pricing field hook registered');
check(in_array('sanro_studio_save_product_pricing', $GLOBALS['hooks']['woocommerce_admin_process_product_object'] ?? array(), true), 'pricing save hook registered');
sanro_studio_product_pricing_fields();
check(array_keys($GLOBALS['select']['options']) === array('', 'cotton_combed_24s'), 'only approved profiles offered');
$_POST['_sanro_studio_pricing_profile'] = 'cotton_combed_24s';
$p = new FakeProduct();
sanro_studio_save_product_pricing($p);
check(($p->saved['_sanro_studio_pricing_profile'] ?? null) === 'cotton_combed_24s', 'approved profile saved');
$_POST['_sanro_studio_pricing_profile'] = 'attacker_profile';
$p = new FakeProduct();
sanro_studio_save_product_pricing($p);
check($p->saved === array(), 'unknown profile rejected');
$_POST['_sanro_studio_pricing_profile'] = array('cotton_combed_24s');
$p = new FakeProduct();
sanro_studio_save_product_pricing($p);
check($p->saved === array(), 'non-string value rejected');
$_POST['_sanro_studio_pricing_profile'] = 'cotton_combed_24s';
$GLOBALS['can_edit'] = false;
$p = new FakeProduct();
sanro_studio_save_product_pricing($p);
check($p->saved === array(), 'user without edit capability cannot save');
echo "PASS: SANRO Studio product pricing-profile contract\n";
