<?php
/**
 * Standalone master-storage regression smoke test.
 * Run: php plugins/sanro-studio/tests/master-storage-smoke.php
 * No WordPress boot, database, uploads, or production writes.
 */
define('ABSPATH', __DIR__ . '/../../../');
require_once __DIR__ . '/../includes/master-storage.php';

$checks = 0;
function check_master($condition, $message) {
    global $checks;
    $checks++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
$invalid = sanro_studio_validate_master_bytes('');
check_master(($invalid['error'] ?? '') === 'master_size', 'empty file rejected');
check_master((sanro_studio_validate_master_bytes(str_repeat('a', 10485761))['error'] ?? '') === 'master_size', 'over 10MB rejected');
check_master((sanro_studio_validate_master_bytes(str_repeat('a', 200))['error'] ?? '') === 'master_image_invalid', 'fake image rejected');
$disabled = sanro_studio_store_private_master('00000000-0000-4000-8000-000000000000', 'front', str_repeat('a', 200));
check_master(($disabled['error'] ?? '') === 'master_storage_disabled', 'master write disabled by default');
$read = sanro_studio_read_owned_draft_master('00000000-0000-4000-8000-000000000000', str_repeat('0', 64), 'front', 'not-a-file');
check_master(($read['error'] ?? '') === 'master_storage_disabled', 'master read disabled by default');
$owner = sanro_studio_store_owned_draft_master('00000000-0000-4000-8000-000000000000', str_repeat('0', 64), 'front', str_repeat('a', 200));
check_master(($owner['error'] ?? '') === 'draft_store_unavailable', 'owner writer fails closed without draft store');
echo "PASS: {$checks} master-storage regression checks\n";
