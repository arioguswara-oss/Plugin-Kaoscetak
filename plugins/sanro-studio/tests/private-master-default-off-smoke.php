<?php
/** Default-deny test without WordPress or any explicit buyer/draft flags. */
$base = sys_get_temp_dir() . '/sanro-setup-off-' . bin2hex(random_bytes(6));
$web = $base . '/public_html';
if (!mkdir($base, 0700) || !mkdir($web, 0700)) exit(1);
define('ABSPATH', $web . '/');
$_SERVER['DOCUMENT_ROOT'] = $web;
$GLOBALS['options'] = [];
function get_option($key, $default = '') { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['options'][$key] = $value; return true; }
function home_url($path='') { return 'https://inactive.example.test' . $path; }
function wp_salt($name='') { return 'test-secret-salt-' . $name; }
require_once __DIR__ . '/../includes/private-master-setup.php';
try {
    if (sanro_studio_initialize_auto_private_master_storage() !== false) throw new Exception('Unopted buyer enabled');
    if (defined('SANRO_STUDIO_ENABLE_MASTER_UPLOAD') || defined('SANRO_STUDIO_ENABLE_PRIVATE_MASTERS')) {
        throw new Exception('Upload gates enabled by default');
    }
    if (get_option('sanro_studio_auto_private_master_dir') !== '') throw new Exception('Storage created by public default');
    echo "PASS: 3 default-disabled SANRO private-master gate checks\n";
} finally { rmdir($web); rmdir($base); }
