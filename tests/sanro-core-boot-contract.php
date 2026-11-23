<?php
// Explicit private source test. Exercises vendor plugin bootstrap, NOT WordPress init/editor.
$boot_case = $argv[1] ?? 'normal';
$argv[1] = 'missing_lumise';
require __DIR__ . '/sanro-studio-fixture.php';
$GLOBALS['wp_version'] = '6.9';
define('WP_CONTENT_DIR', sys_get_temp_dir() . '/sanro-boot-fixture');
define('WP_DEBUG', false);
function site_url($path = '') { return 'https://example.test' . $path; }
function plugin_basename($file) { return 'sanro-studio/runtime/editor/' . basename($file); }
function plugins_url($path = '', $file = '') { return 'https://example.test/wp-content/plugins/sanro-studio/runtime/editor/' . ltrim($path, '/'); }
function wp_upload_dir(...$args) { return ['basedir' => WP_CONTENT_DIR . '/uploads', 'baseurl' => 'https://example.test/wp-content/uploads']; }
function untrailingslashit($value) { return rtrim($value, '/\\'); }
function plugin_dir_path($file) { return dirname($file) . '/'; }
function register_activation_hook($file, $callback) { $GLOBALS['ss_activation_hooks'][] = [$file, $callback]; }
function is_multisite() { return false; }
function wp_doing_ajax() { return false; }
function __($text, $domain = '') { return $text; }
$GLOBALS['wpdb'] = new class {
    public $reads = 0;
    public function get_var($query) {
        if ($query !== "SHOW TABLES LIKE 'lumise_settings'") throw new RuntimeException('Unexpected query');
        $this->reads++; return ($GLOBALS['boot_case'] ?? '') === 'missing_schema' ? null : 'lumise_settings';
    }
    public function query(...$args) { throw new RuntimeException('Database writes forbidden'); }
};
require __DIR__ . '/../plugins/sanro-studio/sanro-studio.php';
if (!is_dir(sanro_studio_runtime_directory())) { fwrite(STDERR, "Prepare the owner's private runtime first\n"); exit(2); }
$before = $GLOBALS['ss_options'];
$state = sanro_studio_boot_runtime();
if ($boot_case === 'missing_schema') {
    ss_assert($state['state'] === 'schema_missing', 'missing schema blocks core bootstrap');
    ss_assert(!class_exists('LumiseWoo', false), 'no core loaded and no automatic sample data import');
    ss_assert($GLOBALS['ss_options'] === $before && !$GLOBALS['ss_writes'], 'missing schema leaves all options unchanged');
    ss_assert($GLOBALS['wpdb']->reads === 1, 'schema check uses one read');
    ss_finish('Private core missing schema'); exit;
}
ss_assert($state === ['state' => 'internal_loaded', 'mode' => 'internal'], 'actual vendor bootstrap loaded internally');
ss_assert(!function_exists('kld_unit_price'), 'KLD plugin not loaded');
ss_assert(defined('LUMISE_FILE') && strpos(LUMISE_FILE, '/sanro-studio/runtime/editor/') !== false, 'core entry belongs to SANRO internal runtime');
ss_assert(isset($GLOBALS['ss_hooks']['init'][4]), 'actual editor init registered');
foreach (['activated_plugin', 'deactivated_plugin'] as $hook) {
    $owned_callbacks = 0;
    foreach ($GLOBALS['ss_hooks'][$hook] ?? [] as $entries) foreach ($entries as $entry) {
        if (is_array($entry[0]) && $entry[0][0] === $GLOBALS['lumise_woo']) $owned_callbacks++;
    }
    ss_assert($owned_callbacks === 0, 'vendor lifecycle callbacks detached: ' . $hook);
}
$instance = $GLOBALS['lumise_woo'];
ss_assert(sanro_studio_boot_runtime() === $state && $GLOBALS['lumise_woo'] === $instance, 'repeat bootstrap does not replace instance');
ss_assert($GLOBALS['ss_options'] === $before && !$GLOBALS['ss_writes'], 'all options unchanged during bootstrap');
ss_assert($GLOBALS['wpdb']->reads === 1, 'single schema read, no query writes');
ss_finish('Private core bootstrap with both old plugins absent (not full runtime)');
