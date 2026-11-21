<?php
/** SANRO Drive must have no network access or link before explicit opt-in. */
define('ABSPATH', __DIR__ . '/');
require dirname(__DIR__) . '/includes/drive-production.php';
class SanroDriveDefaultOrder {
    public function get_id() { return 100; }
    public function get_items($type = 'line_item') { return []; }
}
$verified = sanro_studio_verify_drive_item_folder(
    new SanroDriveDefaultOrder(), 1, 'sanroPrivateItemFolder_100_1'
);
if (!is_array($verified) || !empty($verified['valid'])
    || ($verified['error'] ?? '') !== 'drive_verification_required') {
    fwrite(STDERR, "FAIL: SANRO Drive must be disabled by default\n");
    exit(1);
}
echo "PASS: SANRO Drive remote verification defaults disabled\n";
