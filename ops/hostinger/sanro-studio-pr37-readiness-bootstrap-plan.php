<?php
/**
 * Sprint 147: additive, PLAN-ONLY bootstrap preview for read-only readiness.
 * No apply path or file modifications inside WordPress; temp destination only.
 */
if (PHP_SAPI !== 'cli' || count($argv) !== 3) {
    fwrite(STDERR, "BLOCKED: private source and temp preview required\n");
    exit(2);
}
$source = $argv[1];
$output = $argv[2];
$old = is_string($source) && is_file($source) && !is_link($source)
    ? file_get_contents($source) : false;
if (!is_string($old) || strpos($old, 'Plugin Name: SANRO Studio') === false
    || strpos($old, "defined('ABSPATH') || exit;") === false) {
    fwrite(STDERR, "BLOCKED: unknown SANRO Studio bootstrap\n");
    exit(2);
}
$anchors = [
    "require_once __DIR__ . '/includes/dtf-admin-generate.php';",
    "require_once __DIR__ . '/includes/dtf-admin-order-status.php';",
    "add_action('woocommerce_after_order_itemmeta', 'sanro_studio_admin_item_dtf_status', 20, 3);",
    "add_action('woocommerce_after_order_itemmeta', 'sanro_studio_admin_item_generate_dtf_action', 25, 3);",
    "add_action('admin_post_sanro_studio_generate_dtf_candidates', 'sanro_studio_admin_generate_dtf_candidates_post');",
];
foreach ($anchors as $anchor) {
    if (substr_count($old, $anchor) !== 1) {
        fwrite(STDERR, "BLOCKED: PR35 installed bootstrap anchors missing or duplicated\n");
        exit(2);
    }
}
if (strpos($old, 'dtf-admin-readiness.php') !== false
    || strpos($old, 'sanro_studio_admin_item_dtf_readiness') !== false
    || strpos($old, 'SANRO_STUDIO_ENABLE_DTF_ADMIN_READINESS') !== false) {
    fwrite(STDERR, "BLOCKED: diagnostic module or activation already customized\n");
    exit(2);
}
$include = "require_once __DIR__ . '/includes/dtf-admin-generate.php';";
$next = str_replace($include, $include . "\n"
    . "require_once __DIR__ . '/includes/dtf-admin-readiness.php';", $old, $count);
if ($count !== 1) {
    fwrite(STDERR, "BLOCKED: ambiguous diagnostic module include\n");
    exit(2);
}
$hook = "add_action('woocommerce_after_order_itemmeta', 'sanro_studio_admin_item_dtf_status', 20, 3);";
$next = str_replace($hook, $hook . "\n"
    . "// Read-only diagnostic OFF unless SANRO_STUDIO_ENABLE_DTF_ADMIN_READINESS is true.\n"
    . "add_action('woocommerce_after_order_itemmeta', 'sanro_studio_admin_item_dtf_readiness', 23, 3);",
    $next, $count);
if ($count !== 1 || substr_count($next, 'dtf-admin-readiness.php') !== 1
    || substr_count($next, "sanro_studio_admin_item_dtf_readiness', 23, 3") !== 1) {
    fwrite(STDERR, "BLOCKED: ambiguous read-only hook patch\n");
    exit(2);
}
if (file_put_contents($output, $next, LOCK_EX) !== strlen($next)) {
    fwrite(STDERR, "BLOCKED: unable to write private preview\n");
    exit(2);
}
@chmod($output, 0600);
echo "PR37_STAGING_PATCH: PREPARED\n";
echo "BASE_SHA256: " . hash('sha256', $old) . "\n";
echo "CANDIDATE_SHA256: " . hash('sha256', $next) . "\n";
echo "STAGING_WORDPRESS_WRITE: NO\n";
echo "DTF_GENERATOR_FLAG: UNCHANGED_OFF\n";
echo "DTF_READINESS_FLAG: NOT_ENABLED_BY_PLAN\n";
