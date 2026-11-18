<?php
define('ABSPATH', __DIR__);
require dirname(__DIR__) . '/includes/core.php';
if (function_exists('sanro_studio_boot_runtime') || function_exists('sanro_studio_dependencies')) {
    fwrite(STDERR, "FAIL: native core unexpectedly loads legacy runtime\n");
    exit(1);
}
require dirname(__DIR__) . '/includes/runtime.php';
if (!function_exists('sanro_studio_boot_runtime')
    || !function_exists('sanro_studio_dependencies')
    || !function_exists('sanro_studio_filter_settings')) {
    fwrite(STDERR, "FAIL: opt-in legacy runtime symbols missing\n");
    exit(1);
}
$status = sanro_studio_native_core_status();
if (!$status['legacy_compat_enabled'] && $status['legacy_runtime_loaded']
    && !$status['runtime_dependencies_satisfied'] && !$status['verified_end_to_end']
    && !$status['checkout_enabled']) {
    echo "PASS: legacy module isolated and native release gates closed\n";
} else {
    fwrite(STDERR, "FAIL: incorrect native readiness gates\n");
    exit(1);
}
