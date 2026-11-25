<?php
define('ABSPATH', __DIR__ . '/');
$GLOBALS['meta'] = [];
$GLOBALS['product_status'] = 'publish';
$GLOBALS['product_type'] = 'simple';
$GLOBALS['catalog_ready'] = true;
$GLOBALS['can_edit'] = true;
$GLOBALS['checks'] = 0;
function absint($v) { return abs((int) $v); }
function get_post_meta($id, $key, $single = true) { return $GLOBALS['meta'][$id][$key] ?? ''; }
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
    public function get_status() { return $GLOBALS['product_status']; }
    public function is_type($type) { return $GLOBALS['product_type'] === $type; }
}
function wc_get_product($id) { return $id === 53 ? new CatalogTestProduct() : false; }
require dirname(__DIR__) . '/plugins/sanro-studio/includes/catalog-lifecycle.php';
require dirname(__DIR__) . '/plugins/sanro-studio/includes/studio-ui.php';
function catalog_check($ok, $name) { $GLOBALS['checks']++; if (!$ok) throw new RuntimeException('FAIL ' . $name); }
function catalog_action($id, $action) { $GLOBALS['nonce'] = 'sanro_catalog_' . $id; return sanro_studio_catalog_write($id, $action); }
$catalog_before = ['woo_status' => $GLOBALS['product_status'], 'product_type' => $GLOBALS['product_type']];
catalog_check(catalog_action(53, 'add') === 53, 'add returns mapped product ID');
catalog_check(sanro_studio_catalog_status(53) === 'disabled', 'new catalog mapping starts disabled');
try { catalog_action(53, 'add'); throw new RuntimeException('FAIL duplicate mapping accepted'); }
catch (RuntimeException $error) { catalog_check(strpos($error->getMessage(), 'sudah ada') !== false, 'duplicate mapping rejected'); }
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
$GLOBALS['product_type'] = 'variable';
try { catalog_action(53, 'add'); throw new RuntimeException('FAIL variable product accepted'); }
catch (RuntimeException $error) { catalog_check(strpos($error->getMessage(), 'simple') !== false, 'variable product explicitly rejected'); }
$GLOBALS['product_type'] = 'simple';
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
echo 'SANRO catalog lifecycle: ' . $GLOBALS['checks'] . " assertions PASS, 0 FAIL\n";
