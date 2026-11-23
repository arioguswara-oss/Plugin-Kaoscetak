<?php
/** Default-deny: no feature flags, OAuth, WordPress or WooCommerce loaded. */
define('ABSPATH', __DIR__ . '/');
require dirname(__DIR__) . '/includes/drive-auto-provision.php';
if (sanro_studio_auto_drive_enabled()) {
    fwrite(STDERR, "FAIL: auto-provision must be disabled by default\n"); exit(1);
}
if (sanro_studio_run_drive_production(100)['error'] !== 'drive_auto_disabled') {
    fwrite(STDERR, "FAIL: worker did not fail closed\n"); exit(1);
}
sanro_studio_schedule_drive_production(100);
echo "PASS: SANRO Drive automatic provisioning defaults disabled\n";
