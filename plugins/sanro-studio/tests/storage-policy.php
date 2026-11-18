<?php
// Run: php plugins/sanro-studio/tests/storage-policy.php
define('ABSPATH', __DIR__);
require dirname(__DIR__) . '/includes/storage-policy.php';
function verify($condition, $label) {
    if (!$condition) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    echo "PASS: $label\n";
}
verify(!sanro_studio_production_folder_eligible(false, false), 'checkout alone cannot create folder');
verify(sanro_studio_production_folder_eligible(true, false), 'confirmed payment allows folder');
verify(sanro_studio_production_folder_eligible(false, true), 'production-ready allows folder');
verify(!sanro_studio_validate_preview_bytes('not an image')['valid'], 'reject fake image');
verify(!sanro_studio_validate_preview_bytes(str_repeat('x', 51201))['valid'], 'reject oversized preview');
verify(!sanro_studio_validate_preview_bytes([])['valid'], 'reject non-string bytes');

$fakeId = '123e4567-e89b-42d3-a456-123456789012';
verify(sanro_studio_store_private_preview($fakeId, 'front', 'bytes')['error'] === 'preview_storage_disabled', 'private storage disabled by default');
verify(sanro_studio_store_private_preview($fakeId, '../back', 'bytes')['error'] === 'preview_storage_disabled', 'disabled gate precedes any target processing');

function sanro_studio_read_draft($id, $token) {
    if ($token !== 'owner-secret') return ['valid' => false, 'error' => 'not_found'];
    return ['valid' => true, 'status' => $id === 'locked' ? 'locked' : 'draft'];
}
verify(sanro_studio_store_owned_draft_preview($fakeId, 'wrong', 'front', 'bytes')['error'] === 'not_found', 'invalid owner cannot write preview');
verify(sanro_studio_store_owned_draft_preview('locked', 'owner-secret', 'front', 'bytes')['error'] === 'draft_locked', 'locked draft cannot write preview');
verify(sanro_studio_store_owned_draft_preview($fakeId, 'owner-secret', 'other', 'bytes')['error'] === 'invalid_preview_target', 'reject unknown side');
verify(sanro_studio_store_owned_draft_preview($fakeId, 'owner-secret', 'front', 'bytes')['error'] === 'preview_storage_disabled', 'authorized write still requires storage opt-in');
