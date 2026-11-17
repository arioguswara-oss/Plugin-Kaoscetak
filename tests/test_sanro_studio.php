<?php
require __DIR__ . '/sanro-studio-fixture.php';
$before = $GLOBALS['ss_options'];
require __DIR__ . '/../plugins/sanro-studio/sanro-studio.php';
$settings = [
    'components' => ['product', 'templates', 'uploads', 'text', 'cliparts', 'shop', 'layers', 'back', 'shapes', 'drawing'],
    'toolbars' => ['position', 'crop', 'transform', 'filter', 'font-size', 'text-effect'],
    'custom_js' => 'synthetic-existing-code', 'max_upload' => '10',
    'stages' => '4', 'editor_page' => 52, 'price' => ['total' => 113000],
];
ss_assert($GLOBALS['ss_options'] === $before && !$GLOBALS['ss_writes'], 'bootstrap preserves all options');
ss_assert(count($GLOBALS['ss_hooks']) === 4, 'only expected profile, runtime and admin hooks registered');
$GLOBALS['ss_options']['sanro_studio_options'] = ['admin_preview' => 'yes'];
if ($scenario !== 'normal') {
    ss_assert(apply_filters('lumise_settings', $settings) === $settings, 'unavailable/unknown/vendor-admin context remains unchanged');
    ob_start(); sanro_studio_render_settings(); $html = ob_get_clean();
    ss_assert(strpos($html, '<script>') === false, 'version output escaped');
    ss_assert(!$GLOBALS['ss_writes'], 'dependency diagnostics make no option writes');
    ss_finish($scenario); exit;
}
unset($GLOBALS['ss_options']['sanro_studio_options']);
ss_assert(apply_filters('lumise_settings', $settings) === $settings, 'default profile off');
$GLOBALS['ss_options']['sanro_studio_options'] = ['admin_preview' => 'yes'];
$GLOBALS['ss_cap'] = false;
ss_assert(apply_filters('lumise_settings', $settings) === $settings, 'guest/customer profile unchanged');
$GLOBALS['ss_cap'] = true; $GLOBALS['ss_admin'] = true;
ss_assert(apply_filters('lumise_settings', $settings) === $settings, 'WordPress admin settings untouched');
$GLOBALS['ss_admin'] = false;
$profile = apply_filters('lumise_settings', $settings);
ss_assert($profile['components'] === ['product', 'uploads', 'text', 'shop', 'back'], 'simple editor components retain order');
ss_assert($profile['toolbars'] === ['position', 'transform', 'font-size'], 'position/size controls retained, effects removed');
foreach (['custom_js', 'max_upload', 'stages', 'editor_page', 'price'] as $key) {
    ss_assert($profile[$key] === $settings[$key], 'unrelated setting retained: ' . $key);
}
ss_assert(apply_filters('lumise_settings', $profile) === $profile, 'repeat filter application is stable');
ss_assert(apply_filters('lumise_settings', ['components' => ['product']]) === ['components' => ['product']], 'disabled components never enabled');
ss_assert(apply_filters('lumise_settings', ['components' => null]) === ['components' => null], 'malformed component settings pass through');
ss_assert(apply_filters('lumise_settings', null) === null, 'malformed settings pass through');
ss_assert(apply_filters('lumise_settings', ['components' => ['product', ['uploads'], 1]]) === ['components' => ['product']], 'unexpected component entries safely excluded');
sanro_studio_admin_menu();
ss_assert($GLOBALS['ss_menu'][3] === 'manage_options', 'settings restricted to administrator');
ob_start(); sanro_studio_render_settings(); $html = ob_get_clean();
ss_assert(strpos($html, '<h1>SANRO Studio</h1>') !== false, 'own settings branding');
ss_assert(stripos($html, 'Lumise') === false, 'own settings UI uses SANRO branding');
ss_assert(strpos($html, 'https://example.test/custom/') !== false, 'configured editor link reused');
ss_assert(strpos($html, 'name="_wpnonce"') !== false, 'settings form includes nonce');
$_POST = ['admin_preview' => 'yes', 'kld_client_secret' => 'attempted-change', 'lumise_editor_page' => '99'];
$_SERVER['REQUEST_METHOD'] = 'POST';
foreach (['capability', 'nonce', 'method'] as $failure) {
    $GLOBALS['ss_cap'] = $failure !== 'capability';
    $GLOBALS['ss_nonce'] = $failure !== 'nonce';
    $_SERVER['REQUEST_METHOD'] = $failure === 'method' ? 'GET' : 'POST';
    try { sanro_studio_save_settings(); throw new RuntimeException('Save should be rejected'); }
    catch (SS_Denied $e) { ss_assert($e->getCode() === ($failure === 'method' ? 405 : 403), 'reject invalid ' . $failure); }
    ss_assert(!$GLOBALS['ss_writes'], 'rejected request makes no writes');
}
$GLOBALS['ss_cap'] = true; $GLOBALS['ss_nonce'] = true; $_SERVER['REQUEST_METHOD'] = 'POST';
try { sanro_studio_save_settings(); }
catch (SS_Redirect $e) { ss_assert($e->getMessage() === 'https://example.test/wp-admin/admin.php?page=sanro-studio&saved=1', 'fixed safe redirect'); }
ss_assert(count($GLOBALS['ss_writes']) === 1, 'valid save writes only dedicated option');
ss_assert(sanro_studio_options()['admin_preview'] === true, 'valid preview save');
foreach ($before as $key => $value) ss_assert($GLOBALS['ss_options'][$key] === $value, 'vendor/KLD option preserved: ' . $key);
$_POST = ['admin_preview' => ['yes']];
try { sanro_studio_save_settings(); } catch (SS_Redirect $e) {}
ss_assert(sanro_studio_options()['admin_preview'] === false, 'malformed checkbox cannot enable profile');
$GLOBALS['ss_cap'] = false;
try { sanro_studio_render_settings(); throw new RuntimeException('Page should be denied'); }
catch (SS_Denied $e) { ss_assert($e->getCode() === 403, 'settings page denies unauthorized user'); }
$GLOBALS['ss_cap'] = true;
ss_finish('SANRO Studio foundation');
foreach (['missing_lumise', 'missing_woo', 'unsupported', 'vendor_admin'] as $case) {
    $command = escapeshellarg(PHP_BINARY) . ' -n ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($case);
    passthru($command, $status);
    if ($status !== 0) exit($status);
}
