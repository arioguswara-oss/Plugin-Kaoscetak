<?php
/** Sprint 147 source-only staging PLAN fixture. Never touches WordPress. */
$workspace = sys_get_temp_dir() . '/sanro-pr37-preview-' . bin2hex(random_bytes(7));
if (!mkdir($workspace, 0700)) exit(2);
$input = $workspace . '/bootstrap-old.php';
$output = $workspace . '/bootstrap-preview.php';
$old = <<<'PHP'
<?php
/** Plugin Name: SANRO Studio */
defined('ABSPATH') || exit;
require_once __DIR__ . '/includes/order-preview.php';
require_once __DIR__ . '/includes/dtf-admin-order-status.php';
require_once __DIR__ . '/includes/dtf-admin-generate.php';
add_action('woocommerce_after_order_itemmeta', 'sanro_studio_admin_item_preview_status', 10, 3);
add_action('woocommerce_after_order_itemmeta', 'sanro_studio_admin_item_dtf_status', 20, 3);
add_action('woocommerce_after_order_itemmeta', 'sanro_studio_admin_item_generate_dtf_action', 25, 3);
add_action('admin_footer', 'sanro_studio_dtf_admin_generate_footer_forms');
add_action('admin_post_sanro_studio_generate_dtf_candidates', 'sanro_studio_admin_generate_dtf_candidates_post');
// Preserve unrelated legacy controls.
PHP;
file_put_contents($input, $old);
$checks = 0;
function pr37_assert($ok, $message) {
    global $checks;
    if (!$ok) {
        fwrite(STDERR, "FAIL: staging readiness plan: $message\n");
        exit(1);
    }
    $checks++;
}
try {
    $diagnostic = (static function ($in, $out) {
        $argv = [__DIR__ . '/../ops/hostinger/sanro-studio-pr37-readiness-bootstrap-plan.php', $in, $out];
        ob_start();
        try {
            require __DIR__ . '/../ops/hostinger/sanro-studio-pr37-readiness-bootstrap-plan.php';
            return (string) ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    })($input, $output);
    $proposed = file_get_contents($output);
    pr37_assert(strpos($diagnostic, 'PR37_STAGING_PATCH: PREPARED') !== false,
        'private patch preview generated');
    pr37_assert(file_get_contents($input) === $old, 'original bootstrap unchanged');
    pr37_assert(substr_count($proposed, "require_once __DIR__ . '/includes/dtf-admin-readiness.php';") === 1,
        'one new readiness module include');
    pr37_assert(substr_count($proposed, "sanro_studio_admin_item_dtf_readiness', 23, 3") === 1,
        'exactly one priority-23 diagnostic hook');
    pr37_assert(strpos($proposed, "sanro_studio_admin_item_preview_status', 10, 3") !== false,
        'existing buyer preview remains');
    pr37_assert(strpos($proposed, "sanro_studio_admin_item_dtf_status', 20, 3") !== false,
        'existing DTF read-only status remains');
    pr37_assert(strpos($proposed, "sanro_studio_admin_item_generate_dtf_action', 25, 3") !== false,
        'separate DTF generator action preserved');
    pr37_assert(strpos($proposed, 'sanro_studio_admin_generate_dtf_candidates_post') !== false,
        'existing nonce-protected admin action preserved');
    pr37_assert(strpos($proposed, '// Preserve unrelated legacy controls.') !== false,
        'unrelated bootstrap contents untouched');
    pr37_assert(strpos($proposed, "define('SANRO_STUDIO_ENABLE_DTF_ADMIN_READINESS'") === false,
        'no readiness flag enabled');
    pr37_assert(strpos($proposed, "define('SANRO_STUDIO_ENABLE_DTF_ADMIN_GENERATE'") === false,
        'no generator flag enabled');
    pr37_assert(strpos($diagnostic, hash('sha256', $old)) !== false
        && strpos($diagnostic, hash('sha256', $proposed)) !== false,
        'original/proposed SHA256 explicitly reported');
    pr37_assert((fileperms($output) & 0077) === 0, 'temp preview only owner readable');
    echo 'PASS: ' . $checks . " SANRO read-only staging readiness preview assertions\n";
} finally {
    foreach ([$input, $output] as $file) {
        if (is_file($file)) unlink($file);
    }
    @rmdir($workspace);
}
