<?php
/**
 * Sprint 141 — prepare a minimal SANRO Studio staging-only bootstrap patch.
 *
 * No writing into WordPress takes place here. A new PHP file is prepared in
 * an isolated private staging QA folder, then linted by the calling shell.
 * This program NEVER rewrites the complete plugin from a GitHub branch.
 */
if (PHP_SAPI !== 'cli' || count($argv) !== 3) {
    fwrite(STDERR, "BLOCKED: expected current plugin main path and private output path\n");
    exit(2);
}
$current = $argv[1];
$output = $argv[2];
$original = is_file($current) && !is_link($current) ? file_get_contents($current) : false;
if (!is_string($original)
    || strpos($original, 'Plugin Name: SANRO Studio') === false
    || strpos($original, "defined('ABSPATH') || exit;") === false
    || strpos($original, "require_once __DIR__ . '/includes/order-preview.php';") === false) {
    fwrite(STDERR, "BLOCKED: unrecognized SANRO Studio live bootstrap\n");
    exit(2);
}
if (strpos($original, 'sanro_studio_admin_item_dtf_status') !== false
    || strpos($original, "dtf-image-render.php") !== false
    || strpos($original, "dtf-layer-render.php") !== false
    || strpos($original, "dtf-private-artifacts.php") !== false
    || strpos($original, "dtf-order-workflow.php") !== false) {
    fwrite(STDERR, "BLOCKED: DTF bootstrap already customized; no blind overwrite\n");
    exit(2);
}
$includeAnchor = "require_once __DIR__ . '/includes/order-preview.php';";
$includes = <<<'SOURCE'

/* SANRO_STAGING_DTF_ADMIN_MODULES_BEGIN: read-only, feature-gated */
require_once __DIR__ . '/includes/dtf-image-render.php';
require_once __DIR__ . '/includes/dtf-layer-render.php';
require_once __DIR__ . '/includes/dtf-private-artifacts.php';
require_once __DIR__ . '/includes/dtf-order-workflow.php';
require_once __DIR__ . '/includes/dtf-admin-order-status.php';
/* SANRO_STAGING_DTF_ADMIN_MODULES_END */
SOURCE;
$patch = str_replace($includeAnchor, $includeAnchor . "\n" . $includes, $original, $count);
if ($count !== 1) {
    fwrite(STDERR, "BLOCKED: preview module anchor must be unique\n");
    exit(2);
}
$pattern = "/add_action\\s*\\(\\s*'woocommerce_after_order_itemmeta'\\s*,"
    . "\\s*'sanro_studio_admin_item_preview_status'\\s*,\\s*10\\s*,\\s*3\\s*\\)\\s*;/";
$hook = <<<'SOURCE'

/* SANRO_STAGING_DTF_ADMIN_HOOK_BEGIN: admin metadata, no file delivery */
add_action('woocommerce_after_order_itemmeta', 'sanro_studio_admin_item_dtf_status', 20, 3);
/* SANRO_STAGING_DTF_ADMIN_HOOK_END */
SOURCE;
$count = 0;
$patch = preg_replace_callback($pattern, static function ($match) use ($hook) {
    return $match[0] . "\n" . $hook;
}, $patch, 1, $count);
if (!is_string($patch) || $count !== 1) {
    fwrite(STDERR, "BLOCKED: expected unique legacy WooCommerce preview hook not found\n");
    exit(2);
}
if (substr_count($patch, 'SANRO_STAGING_DTF_ADMIN_MODULES_BEGIN') !== 1
    || substr_count($patch, 'SANRO_STAGING_DTF_ADMIN_HOOK_BEGIN') !== 1
    || strpos($patch, "require_once __DIR__ . '/includes/dtf-admin-order-status.php';") === false) {
    fwrite(STDERR, "BLOCKED: inconsistent staging bootstrap patch\n");
    exit(2);
}
if (file_put_contents($output, $patch, LOCK_EX) !== strlen($patch)) {
    fwrite(STDERR, "BLOCKED: cannot write isolated staging preview candidate\n");
    exit(2);
}
@chmod($output, 0600);
echo "BOOTSTRAP_PATCH: PREPARED\n";
echo "BOOTSTRAP_ORIGINAL_SHA256: " . hash('sha256', $original) . "\n";
echo "BOOTSTRAP_CANDIDATE_SHA256: " . hash('sha256', $patch) . "\n";
