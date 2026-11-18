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
