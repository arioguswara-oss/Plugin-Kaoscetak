<?php
/** Isolated installer checks. No real WordPress files or hosting writes. */
$base = sys_get_temp_dir() . '/sanro-setup-test-' . bin2hex(random_bytes(7));
$web = $base . '/public_html';
if (!mkdir($base, 0700) || !mkdir($web, 0700)) exit(1);
define('ABSPATH', $web . '/');
define('SANRO_STUDIO_ENABLE_BUYER_EDITOR', true);
define('SANRO_STUDIO_ENABLE_DRAFT_API', true);
$_SERVER['DOCUMENT_ROOT'] = $web;
$GLOBALS['options'] = [];
function get_option($key, $default = '') { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['options'][$key] = $value; return true; }
function home_url($path='') { return 'https://staging.example.test' . $path; }
function wp_salt($name='') { return 'test-secret-salt-' . $name; }
require_once __DIR__ . '/../includes/private-master-setup.php';
$checks=0;
function check_it($condition,$message) {global $checks;if(!$condition){fwrite(STDERR,"FAIL: $message\n");exit(1);} $checks++;}
try {
    check_it(!sanro_studio_private_master_path_safe($web), 'Webroot is rejected');
    check_it(sanro_studio_provision_private_master_storage(), 'Private directory provisioned');
    $dir = get_option('sanro_studio_auto_private_master_dir');
    check_it(is_dir($dir) && dirname($dir) === $base, 'Sibling of webroot, not inside it');
    check_it((fileperms($dir) & 0077) === 0, 'Folder private permissions 0700');
    check_it(sanro_studio_private_master_path_safe($dir), 'Safe path recognized');
    check_it(sanro_studio_initialize_auto_private_master_storage(), 'Gated constants enabled');
    check_it(defined('SANRO_STUDIO_PRIVATE_MASTER_DIR') && SANRO_STUDIO_PRIVATE_MASTER_DIR === $dir,
       'Exact private path injected');
    check_it(SANRO_STUDIO_ENABLE_PRIVATE_MASTERS === true && SANRO_STUDIO_ENABLE_MASTER_UPLOAD === true,
       'Both upload gates ON when draft+buyer explicitly enabled');
    check_it(sanro_studio_provision_private_master_storage(), 'Repeated setup idempotent');
    $fake = $web . '/unsafe';
    mkdir($fake,0700);
    check_it(!sanro_studio_private_master_path_safe($fake), 'Unsafe nested webroot rejected');
    $link = $base . '/unsafe-link';
    symlink($fake,$link);
    check_it(!sanro_studio_private_master_path_safe($link), 'Symlink directory rejected');
    echo "PASS: $checks isolated SANRO auto-private installer checks\n";
} finally {
    if (is_link($base.'/unsafe-link')) unlink($base.'/unsafe-link');
    if (is_dir($web.'/unsafe')) rmdir($web.'/unsafe');
    if (isset($dir) && is_dir($dir)) rmdir($dir);
    rmdir($web);rmdir($base);
}
