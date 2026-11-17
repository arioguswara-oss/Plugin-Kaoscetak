<?php
$runtime_case = $argv[1] ?? 'external';
$argv[1] = $runtime_case === 'missing_woo' ? 'missing_woo' : ($runtime_case === 'external' ? 'normal' : 'missing_lumise');
require __DIR__ . '/sanro-studio-fixture.php';
require __DIR__ . '/../plugins/sanro-studio/sanro-studio.php';
$before = $GLOBALS['ss_options'];
if ($runtime_case === 'partial') define('LUMISE', '2.1.2');
if (in_array($runtime_case, ['external', 'missing_woo', 'partial'], true)) {
    $expected = ['external' => 'external_loaded', 'missing_woo' => 'woocommerce_missing', 'partial' => 'symbol_conflict'];
    $status = sanro_studio_boot_runtime();
    ss_assert($status['state'] === $expected[$runtime_case], 'safe bootstrap state: ' . $runtime_case);
    ss_assert(sanro_studio_boot_runtime() === $status, 'bootstrap idempotent');
    ss_assert($GLOBALS['ss_options'] === $before && !$GLOBALS['ss_writes'], 'bootstrap never rewrites options');
}
if ($runtime_case !== 'external') { ss_finish('Runtime ' . $runtime_case); exit; }
ss_assert($GLOBALS['ss_hooks']['plugins_loaded'][20][0][0] === 'sanro_studio_boot_runtime', 'bootstrap runs after all plugin files load');
$root = sys_get_temp_dir() . '/sanro-runtime-' . bin2hex(random_bytes(8));
mkdir($root, 0700);
$files = ['lumise.php', 'includes/class-lumise.php', 'woo_connector.php', 'core/includes/main.php',
    'core/includes/config.php', 'core/includes/tmpl.php'];
$source = ['version' => '2.1.2', 'entrypoint' => 'lumise.php', 'critical_files' => []];
try {
    ss_assert(sanro_studio_check_runtime($root . '/missing', $source) === 'package_missing', 'missing package rejected');
    foreach ($files as $name) {
        $path = $root . '/' . $name;
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
        file_put_contents($path, "<?php // synthetic source fixture, never executed\n");
        $source['critical_files'][$name] = hash_file('sha256', $path);
    }
    ss_assert(sanro_studio_check_runtime($root, $source) === 'valid', 'complete pinned source accepted');
    ss_assert(sanro_studio_check_runtime($root, null) === 'manifest_invalid', 'missing manifest rejected');
    $changed = $source; $changed['version'] = '2.2.0';
    ss_assert(sanro_studio_check_runtime($root, $changed) === 'manifest_invalid', 'unknown source version rejected');
    $changed = $source; unset($changed['critical_files']['woo_connector.php']);
    ss_assert(sanro_studio_check_runtime($root, $changed) === 'manifest_invalid', 'incomplete manifest rejected');
    $changed = $source; $changed['critical_files']['../outside.php'] = str_repeat('a', 64);
    ss_assert(sanro_studio_check_runtime($root, $changed) === 'manifest_invalid', 'manifest traversal rejected');
    $changed = $source; $changed['critical_files']['lumise.php'] = [];
    ss_assert(sanro_studio_check_runtime($root, $changed) === 'manifest_invalid', 'malformed digest rejected');
    $path = $root . '/lumise.php'; $original = file_get_contents($path);
    file_put_contents($path, '<?php throw new RuntimeException("must not execute");');
    ss_assert(sanro_studio_check_runtime($root, $source) === 'package_changed', 'changed entrypoint rejected without executing it');
    file_put_contents($path, $original);
    unlink($path);
    ss_assert(sanro_studio_check_runtime($root, $source) === 'package_incomplete', 'missing entrypoint rejected');
    $outside = $root . '-outside.php'; file_put_contents($outside, $original);
    symlink($outside, $path);
    ss_assert(sanro_studio_check_runtime($root, $source) === 'package_incomplete', 'file symlink escaping package rejected');
    unlink($path); unlink($outside); file_put_contents($path, $original);
    // A symlink to an otherwise valid file inside the package must also be rejected.
    $internal = $root . '/internal-copy.php';
    file_put_contents($internal, $original);
    unlink($path);
    symlink($internal, $path);
    ss_assert(sanro_studio_check_runtime($root, $source) === 'package_changed', 'internal symlink rejected despite matching digest');
    unlink($path); unlink($internal); file_put_contents($path, $original);
    ss_assert(sanro_studio_check_runtime($root, $source) === 'valid', 'original source can be restored');
    $_GET['core_path'] = '/tmp/customer-controlled'; $_POST['runtime_file'] = 'https://example.test/bad.php';
    ss_assert(sanro_studio_runtime_directory() === dirname(__DIR__) . '/plugins/sanro-studio/runtime/editor', 'request cannot select runtime source');
    ss_assert($GLOBALS['ss_options'] === $before && !$GLOBALS['ss_writes'], 'integrity checks never rewrite settings');
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) { $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname()); }
    rmdir($root);
}
ss_finish('Runtime integrity and external compatibility');
foreach (['missing_woo', 'partial'] as $case) {
    passthru(escapeshellarg(PHP_BINARY) . ' -n ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($case), $status);
    if ($status !== 0) exit($status);
}
