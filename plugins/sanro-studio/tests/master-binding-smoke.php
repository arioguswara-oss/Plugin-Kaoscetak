<?php
/**
 * Isolated regression for atomic master-to-draft binding.
 * No WordPress boot or production database writes.
 */
$dir = sys_get_temp_dir() . '/sanro-bind-' . bin2hex(random_bytes(8));
if (!mkdir($dir, 0700)) exit(1);
define('ABSPATH', dirname(__DIR__, 3) . '/');
define('SANRO_STUDIO_ENABLE_PRIVATE_MASTERS', true);
define('SANRO_STUDIO_PRIVATE_MASTER_DIR', $dir);
$_SERVER['DOCUMENT_ROOT'] = ABSPATH;
require_once __DIR__ . '/../includes/master-storage.php';
$id = '00000000-0000-4000-8000-000000000000';
$token = str_repeat('a', 64);
$record = ['product_id' => 1, 'design' => [], 'status' => 'draft',
    'expires_at' => time() + 3600, 'created_at' => time(),
    'token_hash' => hash('sha256', $token)];
function get_option($key, $fallback = false) { global $record; return $record; }
function maybe_serialize($value) { return serialize($value); }
function wp_cache_delete($key, $group) { return true; }
function sanro_studio_read_draft($id, $owner) {
    global $token, $record;
    if (!hash_equals($token, $owner)) return ['valid' => false, 'error' => 'not_found'];
    return ['valid' => true, 'status' => $record['status']];
}
class MasterTestDb {
    public $options = 'wp_options';
    public $mode = 'success';
    public function prepare($sql, ...$args) { return ['sql' => $sql, 'args' => $args]; }
    public function query($prepared) {
        global $record;
        if ($this->mode === 'conflict') return 0;
        if ($this->mode === 'failure') return false;
        $args = $prepared['args'];
        if ($args[1] !== 'sanro_studio_draft_00000000-0000-4000-8000-000000000000'
            || $args[2] !== serialize($record)) return 0;
        $record = unserialize($args[0]);
        return 1;
    }
}
$wpdb = new MasterTestDb();
function bind_check($ok, $label) {
    if (!$ok) { fwrite(STDERR, "FAIL: {$label}\n"); exit(1); }
}
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAIAAADZF8uwAAAATUlEQVR4nGNkYGBQFTbFjxj3PKkhrKjixA/CikzWlBBW9GHCB8KK1pTkEFaUEfGCsCIVmxTCih4oPCCsaA5LDGFFES9uEFYkciaEoCIAPtNKeUEU/EcAAAAASUVORK5CYII=', true);
try {
    $saved = sanro_studio_store_owned_draft_master($id, $token, 'front', $png);
    bind_check(!empty($saved['valid']), 'private master stored');
    $file = $saved['file_id'];
    $denied = sanro_studio_bind_owned_draft_master($id, str_repeat('b', 64), 'front', $file);
    bind_check(empty($denied['valid']), 'wrong owner denied');
    $denied = sanro_studio_bind_owned_draft_master($id, $token, 'back', $file);
    bind_check(empty($denied['valid']), 'wrong side denied');
    $wpdb->mode = 'conflict';
    $conflict = sanro_studio_bind_owned_draft_master($id, $token, 'front', $file);
    bind_check(($conflict['error'] ?? '') === 'draft_conflict', 'concurrent update rejected');
    bind_check(!isset($record['master_refs']), 'conflict did not mutate draft');
    $wpdb->mode = 'success';
    $hash = hash('sha256', serialize($record['design']));
    $stale = sanro_studio_bind_owned_draft_master($id, $token, 'front', $file, str_repeat('0', 64));
    bind_check(($stale['error'] ?? '') === 'draft_conflict', 'stale design fingerprint rejected');
    bind_check(!isset($record['master_refs']), 'stale fingerprint did not mutate draft');
    $bound = sanro_studio_bind_owned_draft_master($id, $token, 'front', $file, $hash);
    bind_check(!empty($bound['valid']) && ($record['master_refs']['front'] ?? '') === $file, 'atomic binding persisted');
    $record['status'] = 'locked';
    $locked = sanro_studio_bind_owned_draft_master($id, $token, 'front', $file);
    bind_check(($locked['error'] ?? '') === 'draft_conflict', 'locked draft rejected');
    echo "PASS: 8 private-master binding regression checks\n";
} finally {
    foreach (glob($dir . '/*') ?: [] as $p) if (is_file($p) && !is_link($p)) unlink($p);
    rmdir($dir);
}
