<?php
/** No WordPress runtime, no Google Drive, no feature flags. */
define('ABSPATH', __DIR__ . '/');
require dirname(__DIR__) . '/includes/drive-master-export.php';
if (sanro_studio_drive_master_export_enabled()) {
    fwrite(STDERR, "FAIL: private master export enabled by default\n");
    exit(1);
}
$result = sanro_studio_drive_upload_locked_item_master(null, 0, '', 'front');
if (!is_array($result) || ($result['error'] ?? '') !== 'drive_master_export_disabled') {
    fwrite(STDERR, "FAIL: private master worker does not fail closed\n");
    exit(1);
}
echo "PASS: SANRO private master Drive export defaults disabled\n";
