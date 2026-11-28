<?php
define('ABSPATH', __DIR__ . '/');
$GLOBALS['meta'] = [];
$GLOBALS['product_status'] = 'publish';
$GLOBALS['product_type'] = 'simple';
$GLOBALS['catalog_ready'] = false;
$GLOBALS['can_edit'] = true;
$GLOBALS['checks'] = 0;
function absint($v) { return abs((int) $v); }
function get_post_meta($id, $key, $single = true) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function add_post_meta($id, $key, $value, $unique = false) { if ($unique && metadata_exists('post',$id,$key)) return false; return update_post_meta($id,$key,$value); }
function update_post_meta($id, $key, $value) { $GLOBALS['meta'][$id][$key] = $value; return true; }
function delete_post_meta($id, $key) { unset($GLOBALS['meta'][$id][$key]); return true; }
function metadata_exists($type, $id, $key) { return array_key_exists($key, $GLOBALS['meta'][$id] ?? []); }
function current_user_can($cap, $id = null) { return $GLOBALS['can_edit']; }
function check_admin_referer($action) { if (($GLOBALS['nonce'] ?? '') !== $action) throw new RuntimeException('nonce mismatch'); return true; }
function wp_die($message, $title = '', $args = []) { throw new RuntimeException('wp_die: ' . $message); }
function add_action(...$args) {}
function add_filter(...$args) {}
function sanro_studio_public_editor_config($id, $allow_inactive = false) { return ['valid' => $GLOBALS['catalog_ready']]; }
class CatalogTestProduct {
    public function get_id() { return 53; }
    public function update_meta_data($key,$value) { update_post_meta(53,$key,$value); }
    public function get_status() { return $GLOBALS['product_status']; }
    public function is_type($type) { return $GLOBALS['product_type'] === $type; }
}
function wc_get_product($id) { return $id === 53 ? new CatalogTestProduct() : false; }
function wp_unslash($value) { return $value; }
require dirname(__DIR__) . '/plugins/sanro-studio/includes/product-print-zones.php';
require dirname(__DIR__) . '/plugins/sanro-studio/includes/catalog-lifecycle.php';
require dirname(__DIR__) . '/plugins/sanro-studio/includes/studio-ui.php';
function catalog_check($ok, $name) { $GLOBALS['checks']++; if (!$ok) throw new RuntimeException('FAIL ' . $name); }
function catalog_action($id, $action) { $GLOBALS['nonce'] = 'sanro_catalog_' . $id; return sanro_studio_catalog_write($id, $action); }
$catalog_before = ['woo_status' => $GLOBALS['product_status'], 'product_type' => $GLOBALS['product_type']];
catalog_check(catalog_action(53, 'add') === 53, 'add returns mapped product ID');
catalog_check(sanro_studio_catalog_status(53) === 'disabled', 'new catalog mapping starts disabled');
try { catalog_action(53, 'add'); throw new RuntimeException('FAIL duplicate mapping accepted'); }
catch (RuntimeException $error) { catalog_check(strpos($error->getMessage(), 'sudah ada') !== false, 'duplicate mapping rejected'); }
$GLOBALS['catalog_ready'] = true;
catalog_action(53, 'activate');
catalog_check(sanro_studio_catalog_is_buyer_visible(53), 'configured product activates');
catalog_action(53, 'disable');
catalog_check(!sanro_studio_catalog_is_buyer_visible(53), 'disable hides listing');
catalog_action(53, 'activate');
catalog_action(53, 'archive');
catalog_check(sanro_studio_catalog_status(53) === 'archived', 'archive is independent state');
catalog_action(53, 'archive');
catalog_check(get_post_meta(53, '_sanro_studio_catalog_restore_status') === 'active', 'archive is idempotent');
catalog_action(53, 'restore');
catalog_check(sanro_studio_catalog_status(53) === 'active', 'restore returns prior active state');
catalog_action(53, 'disable');
catalog_action(53, 'archive');
catalog_action(53, 'restore');
catalog_check(sanro_studio_catalog_status(53) === 'disabled', 'restore preserves prior disabled state');
$GLOBALS['meta'][53] = ['_sanro_studio_pricing_profile' => 'cotton_combed_24s'];
catalog_check(sanro_studio_catalog_is_mapped(53), 'legacy Studio config is recognized without lifecycle status metadata');
catalog_check(sanro_studio_catalog_status(53) === 'active' && sanro_studio_catalog_is_buyer_visible(53), 'legacy listing keeps its buyer-visible status');
try { catalog_action(53, 'add'); throw new RuntimeException('FAIL legacy duplicate mapping accepted'); }
catch (RuntimeException $error) { catalog_check(strpos($error->getMessage(), 'sudah ada') !== false, 'legacy mapped product cannot be added twice'); }
catalog_action(53, 'disable');
catalog_check(sanro_studio_catalog_status(53) === 'disabled' && !sanro_studio_catalog_is_buyer_visible(53), 'legacy mapping can be deactivated');
catalog_action(53, 'archive');
catalog_action(53, 'restore');
catalog_check(sanro_studio_catalog_status(53) === 'disabled', 'legacy mapping can be archived and restored without enabling it');
$GLOBALS['meta'][53] = [];
$GLOBALS['catalog_ready'] = false;
try { catalog_action(53, 'disable'); throw new RuntimeException('FAIL unmanaged Woo product accepted for catalog lifecycle'); }
catch (RuntimeException $error) { catalog_check(strpos($error->getMessage(), 'Petakan produk') !== false, 'unmapped product lifecycle mutation rejected'); }
$GLOBALS['product_type'] = 'variable';
$GLOBALS['catalog_ready'] = false;
$GLOBALS['meta'][53] = [];
catalog_check(catalog_action(53, 'add') === 53 && sanro_studio_catalog_status(53) === 'disabled', 'variable product can be mapped into Studio lifecycle');
$GLOBALS['catalog_ready'] = true;
catalog_action(53, 'activate');
catalog_check(sanro_studio_catalog_is_buyer_visible(53), 'configured variable product activates');
catalog_action(53, 'disable');
catalog_check(!sanro_studio_catalog_is_buyer_visible(53), 'variable listing deactivates independently');
catalog_action(53, 'archive');
catalog_check(sanro_studio_catalog_status(53) === 'archived', 'variable product archives without deleting WooCommerce product');
catalog_action(53, 'restore');
catalog_check(sanro_studio_catalog_status(53) === 'disabled', 'variable archived mapping restores its prior inactive state');
$GLOBALS['meta'][53] = [];
$GLOBALS['product_type'] = 'grouped';
try { catalog_action(53, 'add'); throw new RuntimeException('FAIL unsupported product type accepted'); }
catch (RuntimeException $error) { catalog_check(strpos($error->getMessage(), 'simple atau variable') !== false, 'unsupported product type rejected'); }
$GLOBALS['product_type'] = 'simple';
$GLOBALS['meta'][53] = ['_sanro_studio_catalog_status' => 'disabled'];
$GLOBALS['catalog_ready'] = false;
try { catalog_action(53, 'activate'); throw new RuntimeException('FAIL incomplete config activated'); }
catch (RuntimeException $error) { catalog_check(strpos($error->getMessage(), 'belum siap') !== false, 'incomplete configuration cannot activate'); }
$GLOBALS['catalog_ready'] = true;
$GLOBALS['nonce'] = 'wrong';
try { sanro_studio_catalog_write(53, 'disable'); throw new RuntimeException('FAIL invalid nonce accepted'); }
catch (RuntimeException $error) { catalog_check(strpos($error->getMessage(), 'nonce mismatch') !== false, 'invalid nonce rejected'); }
$GLOBALS['can_edit'] = false;
try { catalog_action(53, 'disable'); throw new RuntimeException('FAIL unauthorized write accepted'); }
catch (RuntimeException $error) { catalog_check(strpos($error->getMessage(), 'Akses ditolak') !== false, 'capability check enforced'); }
$GLOBALS['can_edit'] = true;
catalog_check($catalog_before === ['woo_status' => $GLOBALS['product_status'], 'product_type' => 'simple'], 'Woo product status/type unchanged');
// Reproduce the native default-metadata shape; these values are not Studio opt-in.
$GLOBALS['meta'][53] = [];
$_POST = [];
foreach (['front','back'] as $side) foreach (sanro_studio_default_print_zone() as $axis=>$value) $_POST['_sanro_studio_zone_'.$side.'_'.$axis] = (string)$value;
sanro_studio_save_product_print_zones(new CatalogTestProduct());
catalog_check(!empty(get_post_meta(53,'_sanro_studio_print_zones')), 'real native save persists default zones that triggered previous metadata heuristic');
$GLOBALS['catalog_ready'] = false;
catalog_check(!sanro_studio_catalog_is_mapped(53), 'native saved defaults do not map product');
catalog_check(sanro_studio_catalog_status(53) === 'unmapped', 'incomplete native config is not active');
foreach (['simple','variable'] as $type) {
    $GLOBALS['product_type'] = $type;
    $GLOBALS['meta'][53] = [];
    sanro_studio_catalog_new_product(53);
    $GLOBALS['catalog_ready'] = true;
    catalog_check(!sanro_studio_catalog_is_mapped(53), 'new '.$type.' remains unmapped even with ready config');
    catalog_check(!sanro_studio_catalog_is_buyer_visible(53), 'new '.$type.' hidden until explicit mapping');
    catalog_action(53, 'add');
    catalog_check(sanro_studio_catalog_status(53) === 'disabled', 'explicit '.$type.' mapping starts disabled');
    catalog_action(53, 'activate');
    catalog_check(sanro_studio_catalog_is_buyer_visible(53), 'explicit '.$type.' activation succeeds');
    sanro_studio_catalog_new_product(53);
    catalog_check(sanro_studio_catalog_status(53) === 'unmapped', 'new duplicate cannot inherit active Studio membership');
}
$GLOBALS['meta'][53] = ['_sanro_studio_pricing_profile'=>'cotton_combed_24s'];
$GLOBALS['catalog_ready'] = false;
catalog_check(!sanro_studio_catalog_is_mapped(53), 'incomplete legacy pricing metadata is not valid mapping');
$GLOBALS['catalog_ready'] = true;
catalog_check(sanro_studio_catalog_is_mapped(53), 'validated legacy configuration remains mapped');
$GLOBALS['meta'][53] = [];
sanro_studio_catalog_new_product_post(53, (object)['post_type'=>'product','post_status'=>'auto-draft'], false);
catalog_check(get_post_meta(53,'_sanro_studio_catalog_status') === 'unmapped', 'native auto-draft creation receives explicit opt-in boundary');
$GLOBALS['meta'][53]['_sanro_studio_catalog_status'] = 'active';
sanro_studio_catalog_new_product_post(53, (object)['post_type'=>'product'], true);
catalog_check(sanro_studio_catalog_status(53) === 'active', 'ordinary existing product update preserves Studio membership');
$GLOBALS['meta'][53] = [];
sanro_studio_catalog_new_product_post(53, (object)['post_type'=>'page'], false);
catalog_check(!metadata_exists('post',53,'_sanro_studio_catalog_status'), 'unrelated post types untouched');
echo 'SANRO catalog lifecycle: ' . $GLOBALS['checks'] . " assertions PASS, 0 FAIL\n";
