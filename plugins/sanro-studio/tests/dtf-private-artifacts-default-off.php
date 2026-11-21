<?php
/** Private DTF artifacts must be disabled even when WordPress is absent. */
define('ABSPATH', __DIR__ . '/');
require dirname(__DIR__) . '/includes/dtf-private-artifacts.php';
if (sanro_studio_dtf_artifacts_enabled()
    || (sanro_studio_dtf_persist_order_item_side(null, 0, 'front')['error'] ?? null)
        !== 'artifact_storage_disabled'
    || (sanro_studio_dtf_verify_private_artifact(null, 0, 'front')['error'] ?? null)
        !== 'artifact_storage_disabled') {
    fwrite(STDERR, "FAIL: private DTF store is not default OFF\n");
    exit(1);
}
echo "PASS: SANRO DTF private artifact storage defaults disabled\n";
