<?php
/**
 * Sprint 144: construct a PRIVATE, LINTABLE staging patch preview.
 * The deployed WordPress plugin is READ ONLY. No apply mode exists.
 */
if (PHP_SAPI !== 'cli' || count($argv) !== 3) {
    fwrite(STDERR, "BLOCKED: expected source bootstrap and private output\n");
    exit(2);
}
$source = $argv[1];
$output = $argv[2];
$old = is_file($source) && !is_link($source) ? file_get_contents($source) : false;
if (!is_string($old)
    || strpos($old, 'Plugin Name: SANRO Studio') === false
    || strpos($old, "defined('ABSPATH') || exit;") === false) {
    fwrite(STDERR, "BLOCKED: unknown WordPress SANRO bootstrap\n");
    exit(2);
}
$anchors = [
    "require_once __DIR__ . '/includes/dtf-admin-order-status.php';",
    "add_action('woocommerce_after_order_itemmeta', 'sanro_studio_admin_item_dtf_status', 20, 3);",
    "/* SANRO_STAGING_DTF_ADMIN_MODULES_BEGIN",
    "/* SANRO_STAGING_DTF_ADMIN_MODULES_END */",
    "/* SANRO_STAGING_DTF_ADMIN_HOOK_BEGIN",
    "/* SANRO_STAGING_DTF_ADMIN_HOOK_END */",
];
foreach ($anchors as $anchor) {
    if (substr_count($old, $anchor) !== 1) {
        fwrite(STDERR, "BLOCKED: staging-only PR31 bootstrap anchor mismatch\n");
        exit(2);
    }
}
if (strpos($old, 'dtf-admin-generate.php') !== false
    || strpos($old, 'sanro_studio_admin_generate_dtf_candidates_post') !== false
    || strpos($old, 'sanro_studio_admin_item_generate_dtf_action') !== false) {
    fwrite(STDERR, "BLOCKED: DTF generation already present or customized\n");
    exit(2);
}
$included = "require_once __DIR__ . '/includes/dtf-admin-order-status.php';";
$addInclude = $included . "\nrequire_once __DIR__ . '/includes/dtf-admin-generate.php';";
$new = str_replace($included, $addInclude, $old, $count);
if ($count !== 1) {
    fwrite(STDERR, "BLOCKED: module include replacement ambiguous\n");
    exit(2);
}
$marker = '/* SANRO_STAGING_DTF_ADMIN_HOOK_END */';
$hooks = <<<'HOOKS'
/* SANRO_STAGING_DTF_GENERATE_HOOK_BEGIN: OFF unless separately opted in */
add_action('woocommerce_after_order_itemmeta', 'sanro_studio_admin_item_generate_dtf_action', 25, 3);
add_action('admin_footer', 'sanro_studio_dtf_admin_generate_footer_forms');
add_action('admin_post_sanro_studio_generate_dtf_candidates', 'sanro_studio_admin_generate_dtf_candidates_post');
/* SANRO_STAGING_DTF_GENERATE_HOOK_END */
HOOKS;
$new = str_replace($marker, $marker . "\n" . $hooks, $new, $count);
if ($count !== 1
    || substr_count($new, "require_once __DIR__ . '/includes/dtf-admin-generate.php';") !== 1
    || substr_count($new, 'SANRO_STAGING_DTF_GENERATE_HOOK_BEGIN') !== 1) {
    fwrite(STDERR, "BLOCKED: ambiguous feature-gated admin hook patch\n");
    exit(2);
}
if (file_put_contents($output, $new, LOCK_EX) !== strlen($new)) {
    fwrite(STDERR, "BLOCKED: private patch output failed\n");
    exit(2);
}
@chmod($output, 0600);
echo "STAGING_PATCH_PREVIEW: PREPARED\n";
echo "ORIGINAL_SHA256: " . hash('sha256', $old) . "\n";
echo "CANDIDATE_SHA256: " . hash('sha256', $new) . "\n";
echo "LIVE_WORDPRESS_WRITE: NO\n";
