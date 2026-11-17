<?php
// Optional source-contract test against the owner's private vendor file; never bundle that file.
require __DIR__ . '/sanro-studio-fixture.php';
$file = getenv('SANRO_LUMISE_CONFIG_FILE');
if (!$file || !is_file($file)) { fwrite(STDERR, "Set SANRO_LUMISE_CONFIG_FILE to the private 2.1.2 config source\n"); exit(2); }
if (!is_file(dirname($file) . '/tmpl.php')) { fwrite(STDERR, "The vendor tmpl.php must be beside config.php\n"); exit(2); }
define('LUMISE_CORE_PATH', dirname($file));
define('DS', DIRECTORY_SEPARATOR);
require __DIR__ . '/../plugins/sanro-studio/sanro-studio.php';
require $file;
$GLOBALS['ss_options']['sanro_studio_options'] = ['admin_preview' => 'yes'];
$GLOBALS['lumise'] = new class {
    public function apply_filters($name, $value) { return apply_filters('lumise_' . $name, $value); }
};
// Skip constructor/database initialization; execute the actual vendor filtering method/defaults.
$config = (new ReflectionClass('lumise_cfg'))->newInstanceWithoutConstructor();
$original = $config->get('settings');
ss_assert(is_array($original) && in_array('templates', $original['components'], true), 'vendor defaults loaded');
$config->apply_filters($GLOBALS['lumise']);
$filtered = $config->get('settings');
ss_assert($filtered['components'] === ['shop', 'product', 'text', 'uploads', 'back'], 'actual vendor settings hook applies simple profile');
ss_assert(in_array('position', $filtered['toolbars'], true) && !in_array('filter', $filtered['toolbars'], true), 'actual toolbar contract');
foreach ($original as $key => $value) {
    if (!in_array($key, ['components', 'toolbars'], true)) ss_assert($filtered[$key] === $value, 'vendor field preserved: ' . $key);
}
ss_assert(!$GLOBALS['ss_writes'], 'vendor contract does not persist options');
$GLOBALS['ss_cap'] = false;
$config = (new ReflectionClass('lumise_cfg'))->newInstanceWithoutConstructor();
$config->apply_filters($GLOBALS['lumise']);
ss_assert($config->get('settings') === $original, 'customer receives unmodified vendor settings');
ss_finish('Lumise 2.1.2 private source contract (not full runtime)');
