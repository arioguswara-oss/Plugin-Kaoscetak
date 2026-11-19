<?php
define('ABSPATH', __DIR__ . '/');
$GLOBALS['meta'] = [];
$GLOBALS['writes'] = [];
function absint($value) { return abs((int) $value); }
function get_post_meta($id, $key, $single) { return $GLOBALS['meta'][$id][$key] ?? null; }
function current_user_can($capability, $id) { return ($GLOBALS['can_edit'] ?? false) && $capability === 'edit_post'; }
function wp_unslash($value) { return $value; }
require __DIR__ . '/../plugins/sanro-studio/includes/print-area.php';
require __DIR__ . '/../plugins/sanro-studio/includes/product-area.php';
class SanroProductFixture {
    public function get_id() { return 77; }
    public function update_meta_data($key, $value) { $GLOBALS['writes'][] = [77, $key, $value]; $GLOBALS['meta'][77][$key] = $value; }
}
function expect_sanro($condition, $name) {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $name);
    $checks++;
}
$checks = 0;
$product = new SanroProductFixture();
expect_sanro(sanro_studio_product_safe_areas(77) === [], 'unconfigured areas empty');
expect_sanro(sanro_studio_validate_product_design(77, [])['error'] === 'product_area_unconfigured', 'unconfigured product rejects');
$_POST = ['_sanro_studio_front_width' => '20', '_sanro_studio_front_height' => '30',
    '_sanro_studio_back_width' => '', '_sanro_studio_back_height' => ''];
sanro_studio_save_product_areas($product);
expect_sanro(count($GLOBALS['writes']) === 0, 'unauthorized write rejected');
$GLOBALS['can_edit'] = true;
sanro_studio_save_product_areas($product);
expect_sanro(count($GLOBALS['writes']) === 1, 'authorized save');
expect_sanro(sanro_studio_product_safe_areas(77)['front'] === ['width' => 20.0, 'height' => 30.0], 'front dimensions saved');
$img = ['type' => 'image', 'x' => 1, 'y' => 1, 'width' => 10, 'height' => 10];
expect_sanro(sanro_studio_validate_product_design(77, ['front' => ['category' => 'A5', 'objects' => [$img]]])['valid'], 'front valid');
expect_sanro(sanro_studio_validate_product_design(77, ['back' => ['category' => 'A5', 'objects' => [$img]]])['error'] === 'invalid_design', 'unconfigured back rejected');
$_POST['_sanro_studio_front_width'] = 'NaN';
sanro_studio_save_product_areas($product);
expect_sanro(count($GLOBALS['writes']) === 1, 'invalid input not persisted');
$_POST['_sanro_studio_front_width'] = '20';
$_POST['_sanro_studio_front_height'] = '';
sanro_studio_save_product_areas($product);
expect_sanro(count($GLOBALS['writes']) === 1, 'partial rectangle not persisted');
expect_sanro($GLOBALS['writes'][0][1] === '_sanro_studio_safe_areas', 'Woo CRUD metadata key');
expect_sanro($GLOBALS['writes'][0][2]['front']['width'] === 20.0, 'Woo CRUD metadata value');
expect_sanro(sanro_studio_editor_area_config(77)['valid'] === true, 'editor config enabled');
expect_sanro(sanro_studio_editor_area_config(77)['unit'] === 'cm', 'editor config units cm');
expect_sanro(sanro_studio_editor_area_config(77)['safe_areas']['front']['width'] === 20.0, 'editor config admin source');
expect_sanro(sanro_studio_editor_area_config(77)['categories']['A3+'] === [32.0, 48.0], 'editor config official limits');
expect_sanro(sanro_studio_editor_area_config(78)['error'] === 'product_area_unconfigured', 'unconfigured editor denied');
echo "SANRO product areas: {$checks} assertions PASS, 0 FAIL\n";
