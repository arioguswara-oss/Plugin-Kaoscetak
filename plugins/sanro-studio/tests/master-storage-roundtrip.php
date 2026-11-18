<?php
/**
 * Isolated private-master write/read regression; no WordPress or live storage.
 * Run: php plugins/sanro-studio/tests/master-storage-roundtrip.php
 */
$directory = sys_get_temp_dir() . '/sanro-master-test-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700)) { fwrite(STDERR, "FAIL: temp directory\n"); exit(1); }
define('ABSPATH', dirname(__DIR__, 3) . '/');
define('SANRO_STUDIO_ENABLE_PRIVATE_MASTERS', true);
define('SANRO_STUDIO_PRIVATE_MASTER_DIR', $directory);
$_SERVER['DOCUMENT_ROOT'] = ABSPATH;
require_once __DIR__ . '/../includes/master-storage.php';
function sanro_studio_read_draft($id, $token) {
    if ($token !== str_repeat('a', 64)) return ['valid' => false, 'error' => 'not_found'];
    return ['valid' => true, 'status' => 'draft'];
}
function master_assert($condition, $label) {
    if (!$condition) { fwrite(STDERR, "FAIL: {$label}\n"); exit(1); }
}
$id = '00000000-0000-4000-8000-000000000000';
$token = str_repeat('a', 64);
$bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAIAAADZF8uwAAAATUlEQVR4nGNkYGBQFTbFjxj3PKkhrKjixA/CikzWlBBW9GHCB8KK1pTkEFaUEfGCsCIVmxTCih4oPCCsaA5LDGFFES9uEFYkAAAAASUVORK5CYII=', true);
// Fixture intentionally uses a fixed PNG; check validity before testing writes.
try {
    $validated = sanro_studio_validate_master_bytes($bytes);
    master_assert(!empty($validated['valid']), 'PNG fixture valid');
    $denied = sanro_studio_store_owned_draft_master($id, str_repeat('b', 64), 'front', $bytes);
    master_assert(empty($denied['valid']) && ($denied['error'] ?? '') === 'not_found', 'unauthorized write rejected');
    $saved = sanro_studio_store_owned_draft_master($id, $token, 'front', $bytes);
    master_assert(!empty($saved['valid']), 'authorized write');
    master_assert(count(glob($directory . '/*')) === 1, 'one private file');
    master_assert((fileperms($directory . '/' . $saved['file_id']) & 0777) === 0600, 'file mode 0600');
    $again = sanro_studio_store_owned_draft_master($id, $token, 'front', $bytes);
    master_assert(!empty($again['valid']) && $again['file_id'] === $saved['file_id'], 'idempotent write');
    $read = sanro_studio_read_owned_draft_master($id, $token, 'front', $saved['file_id']);
    master_assert(!empty($read['valid']) && hash_equals($bytes, $read['bytes']), 'authorized roundtrip');
    $other = sanro_studio_read_owned_draft_master($id, $token, 'back', $saved['file_id']);
    master_assert(empty($other['valid']), 'cross-side read rejected');
    $other = sanro_studio_read_owned_draft_master($id, str_repeat('b', 64), 'front', $saved['file_id']);
    master_assert(empty($other['valid']), 'unauthorized read rejected');
    file_put_contents($directory . '/' . $saved['file_id'], 'tampered');
    $tampered = sanro_studio_read_owned_draft_master($id, $token, 'front', $saved['file_id']);
    master_assert(empty($tampered['valid']), 'tampered file rejected');
    echo "PASS: 9 isolated private-master roundtrip checks\n";
} finally {
    foreach (glob($directory . '/*') ?: [] as $file) { if (is_file($file) && !is_link($file)) unlink($file); }
    rmdir($directory);
}
