<?php
define('ABSPATH', __DIR__ . '/');
$GLOBALS['can_edit'] = false;
$GLOBALS['meta'] = [];
function get_the_ID() { return 42; }
function get_post_meta($id, $key, $single) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function current_user_can($capability, $id) { return $GLOBALS['can_edit'] && $capability === 'edit_post' && $id === 42; }
function wp_unslash($value) { return $value; }
function woocommerce_wp_select($args) { $GLOBALS['rendered'][$args['id']] = $args; }
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
verify_profile('', $GLOBALS['rendered']['_sanro_studio_pricing_profile']['value'], 'default disabled');
verify_profile(2, count($GLOBALS['rendered']['_sanro_studio_pricing_profile']['options']), 'only approved profile');
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
verify_profile(['short'=>'Lengan pendek','long'=>'Lengan panjang'], $GLOBALS['rendered']['_sanro_studio_product_sleeve']['options'], 'sleeve is a product field');
$_POST = ['_sanro_studio_pricing_profile'=>'cotton_combed_24s','_sanro_studio_product_sleeve'=>'long'];
sanro_studio_save_product_pricing($p);
verify_profile([], $p->changes, 'a separate long sleeve price table is required');
$_POST = ['_sanro_studio_pricing_profile'=>'cotton_combed_24s','_sanro_extra_side_0'=>'0',
    '_sanro_extra_side_1'=>'21000','_sanro_extra_side_2'=>'33000','_sanro_extra_side_3'=>'45000'];
sanro_studio_save_product_pricing($p);
verify_profile([0,21000,33000,45000],$p->changes['_sanro_studio_second_side_prices'],'authorized second-side rates saved by tier');
$p->changes = [];
unset($_POST['_sanro_extra_side_3']);
sanro_studio_save_product_pricing($p);
verify_profile([],$p->changes,'partial tariff change rejected');
echo "SANRO product pricing profile: {$checks} assertions PASS, 0 FAIL\n";
