<?php
// CLI-only integration checks for private preview filesystem boundaries.
// Run: php plugins/sanro-studio/tests/private-preview-files.php
$base = sys_get_temp_dir() . '/sanro-preview-test-' . bin2hex(random_bytes(6));
$web = $base . '/public';
$private = $base . '/private';
mkdir($base, 0700);
mkdir($web, 0700);
mkdir($private, 0700);
define('ABSPATH', $web . '/');
define('SANRO_STUDIO_ENABLE_PRIVATE_PREVIEWS', true);
define('SANRO_STUDIO_PRIVATE_PREVIEW_DIR', $private);
$_SERVER['DOCUMENT_ROOT'] = $web;
require dirname(__DIR__) . '/includes/storage-policy.php';
function verify($ok, $label) {
    if (!$ok) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    echo "PASS: $label\n";
}
$id = '123e4567-e89b-42d3-a456-123456789012';
if (!function_exists('imagecreatetruecolor')) {
    echo "SKIP: GD unavailable; image fixture not generated\n";
} else {
    $image = imagecreatetruecolor(32, 32);
    ob_start(); imagepng($image); $bytes = ob_get_clean();
    imagedestroy($image);
    $first = sanro_studio_store_private_preview($id, 'front', $bytes);
    verify(!empty($first['valid']), 'first private preview write');
    $again = sanro_studio_store_private_preview($id, 'front', $bytes);
    verify(!empty($again['valid']) && $again['file_id'] === $first['file_id'], 'idempotent repeated upload');
    function sanro_studio_read_draft($design_id, $owner_token) {
        return $owner_token === 'valid-token'
            ? ['valid' => true, 'status' => 'draft']
            : ['valid' => false, 'error' => 'not_found'];
    }
    $owned = sanro_studio_read_owned_draft_preview($id, 'valid-token', 'front', $first['file_id']);
    verify(!empty($owned['valid']) && hash_equals($bytes, $owned['bytes']), 'authorized preview read verifies content');
    $denied = sanro_studio_read_owned_draft_preview($id, 'wrong-token', 'front', $first['file_id']);
    verify(empty($denied['valid']), 'unauthorized preview read denied');
    $path = $private . '/' . $first['file_id'];
    file_put_contents($path, str_repeat('z', strlen($bytes)));
    $tampered = sanro_studio_store_private_preview($id, 'front', $bytes);
    verify(empty($tampered['valid']), 'reject tampered existing preview');
    unlink($path);
}
rmdir($private); rmdir($web); rmdir($base);
