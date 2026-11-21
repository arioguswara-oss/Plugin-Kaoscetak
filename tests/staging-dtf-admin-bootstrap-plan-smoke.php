<?php
/** Sprint 144 isolated additive bootstrap policy fixture; no WordPress. */
$dir = sys_get_temp_dir() . '/sanro-pr34-' . bin2hex(random_bytes(8));
if (!mkdir($dir, 0700)) exit(2);
$source = $dir . '/live.php';
$output = $dir . '/preview.php';
$original = <<<'WP'
<?php
/** Plugin Name: SANRO Studio */
defined('ABSPATH') || exit;
require_once __DIR__ . '/includes/order-preview.php';
/* SANRO_STAGING_DTF_ADMIN_MODULES_BEGIN: read-only, feature-gated */
require_once __DIR__ . '/includes/dtf-image-render.php';
require_once __DIR__ . '/includes/dtf-layer-render.php';
require_once __DIR__ . '/includes/dtf-private-artifacts.php';
require_once __DIR__ . '/includes/dtf-order-workflow.php';
require_once __DIR__ . '/includes/dtf-admin-order-status.php';
/* SANRO_STAGING_DTF_ADMIN_MODULES_END */
add_action('woocommerce_after_order_itemmeta', 'sanro_studio_admin_item_preview_status', 10, 3);
/* SANRO_STAGING_DTF_ADMIN_HOOK_BEGIN: admin metadata, no file delivery */
add_action('woocommerce_after_order_itemmeta', 'sanro_studio_admin_item_dtf_status', 20, 3);
/* SANRO_STAGING_DTF_ADMIN_HOOK_END */
// preserve third-party custom functionality
WP;
file_put_contents($source, $original);
$count144 = 0;
function check144($ok, $message) {
    global $count144;
    if (!$ok) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
    $count144++;
}
try {
    $outputText = (static function ($in, $out) {
        $argv = [__DIR__ . '/../ops/hostinger/sanro-studio-pr34-bootstrap-plan.php', $in, $out];
        ob_start();
        try {
            require __DIR__ . '/../ops/hostinger/sanro-studio-pr34-bootstrap-plan.php';
            return (string) ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    })($source, $output);
    $patch = file_get_contents($output);
    check144(strpos($outputText, 'STAGING_PATCH_PREVIEW: PREPARED') !== false,
        'patch prepared');
    check144(file_get_contents($source) === $original, 'live bootstrap untouched');
    check144(strpos($patch, "require_once __DIR__ . '/includes/dtf-admin-generate.php';") !== false,
        'new module included');
    check144(substr_count($patch, "require_once __DIR__ . '/includes/dtf-admin-generate.php';") === 1,
        'module included once');
    check144(substr_count($patch, 'sanro_studio_admin_item_generate_dtf_action') === 1,
        'generation button hook once');
    check144(substr_count($patch, 'sanro_studio_dtf_admin_generate_footer_forms') === 1,
        'separate footer form hook once');
    check144(substr_count($patch, 'sanro_studio_admin_generate_dtf_candidates_post') === 1,
        'nonce guarded POST hook once');
    check144(strpos($patch, "sanro_studio_admin_item_preview_status', 10, 3") !== false,
        'legacy preview preserved');
    check144(strpos($patch, "sanro_studio_admin_item_dtf_status', 20, 3") !== false,
        'existing status panel preserved');
    check144(strpos($patch, '// preserve third-party custom functionality') !== false,
        'unrelated plugin code preserved');
    check144(strpos($patch, "define('SANRO_STUDIO_ENABLE_DTF_ADMIN_GENERATE'") === false,
        'patch never enables generation flag');
    check144(strpos($outputText, hash('sha256', $original)) !== false
        && strpos($outputText, hash('sha256', $patch)) !== false,
        'source and candidate SHA256 identified');
    check144((fileperms($output) & 0077) === 0, 'private candidate output');
    echo 'PASS: ' . $count144 . " SANRO safe admin DTF staging preview assertions\n";
} finally {
    foreach ([$source, $output] as $file) {
        if (is_file($file)) unlink($file);
    }
    rmdir($dir);
}
