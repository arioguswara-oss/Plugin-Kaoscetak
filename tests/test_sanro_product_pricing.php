<?php
define('ABSPATH', __DIR__ . '/');
$GLOBALS['can_edit'] = false;
$GLOBALS['meta'] = [];
function get_the_ID() { return 42; }
function get_post_meta($id, $key, $single) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function current_user_can($capability, $id) { return $GLOBALS['can_edit'] && $capability === 'edit_post' && $id === 42; }
function wp_unslash($value) { return $value; }
function woocommerce_wp_select($args) { $GLOBALS['rendered'] = $args; }
require __DIR__ . '/../plugins/sanro-studio/includes/product-pricing.php';
class SanroPricingProductFixture {
    public $changes = [];
    public function get_id() { return 42; }
    public function update_meta_data($key, $value) { $this->changes[$key] = $value; }
}
function verify_profile($expected, $actual, $label) {
    global $checks;
    if ($expected !== $actual) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}
$checks = 0;
$p = new SanroPricingProductFixture();
sanro_studio_product_pricing_fields();
verify_profile('', $GLOBALS['rendered']['value'], 'default disabled');
verify_profile(2, count($GLOBALS['rendered']['options']), 'only approved profile');
$_POST['_sanro_studio_pricing_profile'] = 'cotton_combed_24s';
sanro_studio_save_product_pricing($p);
verify_profile([], $p->changes, 'unauthorized denied');
$GLOBALS['can_edit'] = true;
sanro_studio_save_product_pricing($p);
verify_profile('cotton_combed_24s', $p->changes['_sanro_studio_pricing_profile'], 'opt in');
$_POST['_sanro_studio_pricing_profile'] = 'custom_unverified';
$p->changes = [];
sanro_studio_save_product_pricing($p);
verify_profile([], $p->changes, 'unknown profile denied');
$_POST['_sanro_studio_pricing_profile'] = '';
sanro_studio_save_product_pricing($p);
verify_profile('', $p->changes['_sanro_studio_pricing_profile'], 'explicit opt out');
unset($_POST['_sanro_studio_pricing_profile']);
$p->changes = [];
sanro_studio_save_product_pricing($p);
verify_profile([], $p->changes, 'missing form field no mutation');
echo "SANRO product pricing profile: {$checks} assertions PASS, 0 FAIL\n";
