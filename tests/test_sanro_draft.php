<?php
define('ABSPATH', __DIR__ . '/');
$GLOBALS['options'] = [];
function absint($v) { return abs((int) $v); }
function wp_generate_uuid4() { return '12345678-1234-4234-8234-123456789abc'; }
function add_option($key, $value, $deprecated, $autoload) {
    if (isset($GLOBALS['options'][$key])) return false;
    $GLOBALS['options'][$key] = $value;
    return true;
}
function get_option($key, $fallback) {
    if (!empty($GLOBALS['draft_lock_on_second_read']) && ++$GLOBALS['draft_read_count'] === 2) {
        $GLOBALS['options'][$key]['status'] = 'locked';
    }
    if (!empty($GLOBALS['draft_rebind_on_second_read']) && ++$GLOBALS['draft_read_count'] === 2) {
        $GLOBALS['options'][$key]['product_id'] = 18;
    }
    if (!empty($GLOBALS['draft_corrupt_hash_on_second_read']) && ++$GLOBALS['draft_read_count'] === 2) {
        $GLOBALS['options'][$key]['token_hash'] = ['invalid'];
    }
    return $GLOBALS['options'][$key] ?? $fallback;
}
function update_option($key, $value, $autoload = null) {
    if (!isset($GLOBALS['options'][$key])) return false;
    // Match WordPress behavior: an unchanged option is not reported as updated.
    if ($GLOBALS['options'][$key] === $value) return false;
    $GLOBALS['options'][$key] = $value;
    return true;
}
function maybe_serialize($value) { return serialize($value); }
function wp_cache_delete($key, $group) { return true; }
$wpdb = new class {
    public $options = 'wp_options';
    public function prepare($sql, ...$args) {
        if (strpos($sql, 'BINARY option_value = BINARY %s') === false) return false;
        return ['sql' => $sql, 'args' => $args];
    }
    public function query($statement) {
        if (!is_array($statement) || count($statement['args']) !== 3) return false;
        [$new, $key, $expected] = $statement['args'];
        if (!isset($GLOBALS['options'][$key])) return 0;
        if (!empty($GLOBALS['draft_cas_conflict'])) {
            $GLOBALS['options'][$key]['status'] = 'locked';
        }
        if (!empty($GLOBALS['draft_cas_case_conflict'])) {
            // A case-insensitive SQL collation would consider these values equal.
            $GLOBALS['options'][$key]['status'] = 'DRAFT';
        }
        // Simulate byte-exact SQL BINARY compare, not a case-insensitive collation.
        if (serialize($GLOBALS['options'][$key]) !== $expected) return 0;
        $GLOBALS['options'][$key] = unserialize($new, ['allowed_classes' => false]);
        return 1;
    }
};
function sanro_studio_validate_product_design($product_id, $design) {
    return in_array($design, [['front' => 'valid'], ['front' => 'changed']], true) ? ['valid' => true] : ['valid' => false, 'error' => 'invalid_design'];
}
require __DIR__ . '/../plugins/sanro-studio/includes/draft.php';
$checks = 0;
function check_draft($expected, $actual, $label) {
    global $checks;
    if ($expected !== $actual) throw new RuntimeException('FAIL ' . $label);
    $checks++;
}
check_draft('invalid_product', sanro_studio_create_draft(0, ['front' => 'valid'])['error'], 'invalid product');
check_draft('invalid_design', sanro_studio_create_draft(17, [])['error'], 'invalid geometry');
$created = sanro_studio_create_draft(17, ['front' => 'valid']);
check_draft(true, $created['valid'], 'create');
check_draft(64, strlen($created['owner_token']), 'token entropy length');
check_draft(false, isset($GLOBALS['options']['sanro_studio_draft_' . $created['design_id']]['owner_token']), 'raw token never stored');
check_draft(true, sanro_studio_read_draft($created['design_id'], $created['owner_token'])['valid'], 'authorized read');
check_draft('not_found', sanro_studio_read_draft($created['design_id'], str_repeat('a', 64))['error'], 'wrong owner token');
$key = 'sanro_studio_draft_' . $created['design_id'];
$saved_record = $GLOBALS['options'][$key];
$GLOBALS['options'][$key]['design'] = 'corrupted';
check_draft('not_found', sanro_studio_read_draft($created['design_id'], $created['owner_token'])['error'], 'malformed design metadata rejected');
$GLOBALS['options'][$key] = $saved_record;
$GLOBALS['options'][$key]['status'] = ['corrupted'];
check_draft('not_found', sanro_studio_read_draft($created['design_id'], $created['owner_token'])['error'], 'malformed status metadata rejected');
$GLOBALS['options'][$key] = $saved_record;
check_draft('invalid_credentials', sanro_studio_read_draft('../wp-config.php', $created['owner_token'])['error'], 'path rejected');
check_draft('invalid_credentials', sanro_studio_read_draft(str_repeat('-', 36), $created['owner_token'])['error'], 'malformed UUID rejected');
check_draft('invalid_credentials', sanro_studio_read_draft('12345678123442348234123456789abc----', $created['owner_token'])['error'], 'invalid UUID separators rejected');
check_draft('not_found', sanro_studio_update_draft($created['design_id'], str_repeat('a', 64), ['front' => 'valid'])['error'], 'unauthorized update');
check_draft('invalid_design', sanro_studio_update_draft($created['design_id'], $created['owner_token'], [])['error'], 'invalid replacement rejected');
check_draft(true, sanro_studio_update_draft($created['design_id'], $created['owner_token'], ['front' => 'valid'])['valid'], 'authorized update');
$first_update_at = $GLOBALS['options'][$key]['updated_at'] ?? null;
$repeat = sanro_studio_update_draft($created['design_id'], $created['owner_token'], ['front' => 'valid']);
check_draft(true, $repeat['valid'], 'immediate repeated save');
check_draft(true, !empty($repeat['unchanged']), 'identical design is a no-op');
check_draft($first_update_at, $GLOBALS['options'][$key]['updated_at'] ?? null, 'no-op preserves timestamp');
check_draft(true, sanro_studio_read_draft($created['design_id'], $created['owner_token'])['valid'], 'read after update');
$GLOBALS['draft_cas_conflict'] = true;
check_draft('draft_conflict', sanro_studio_update_draft($created['design_id'], $created['owner_token'], ['front' => 'changed'])['error'], 'CAS detects concurrent lock before write');
$GLOBALS['draft_cas_conflict'] = false;
$GLOBALS['options']['sanro_studio_draft_' . $created['design_id']]['status'] = 'draft';
$GLOBALS['draft_cas_case_conflict'] = true;
check_draft('draft_conflict', sanro_studio_update_draft($created['design_id'], $created['owner_token'], ['front' => 'changed'])['error'], 'CAS rejects case-only concurrent change');
$GLOBALS['draft_cas_case_conflict'] = false;
$GLOBALS['options']['sanro_studio_draft_' . $created['design_id']]['status'] = 'draft';
// Simulate status changing between the first and second get_option reads.
$GLOBALS['draft_read_count'] = 0;
$GLOBALS['draft_lock_on_second_read'] = true;
check_draft('draft_locked', sanro_studio_update_draft($created['design_id'], $created['owner_token'], ['front' => 'changed'])['error'], 'interleaved lock rejected');
$GLOBALS['draft_lock_on_second_read'] = false;
$GLOBALS['options']['sanro_studio_draft_' . $created['design_id']]['status'] = 'draft';
// Reject product rebinding between the initial authorization read and final write.
$GLOBALS['draft_read_count'] = 0;
$GLOBALS['draft_rebind_on_second_read'] = true;
check_draft('draft_locked', sanro_studio_update_draft($created['design_id'], $created['owner_token'], ['front' => 'changed'])['error'], 'interleaved product rebinding rejected');
$GLOBALS['draft_rebind_on_second_read'] = false;
$GLOBALS['options']['sanro_studio_draft_' . $created['design_id']]['product_id'] = 17;
// Simulate corrupted option metadata after the first authorized read.
$GLOBALS['draft_read_count'] = 0;
$GLOBALS['draft_corrupt_hash_on_second_read'] = true;
check_draft('not_found', sanro_studio_update_draft($created['design_id'], $created['owner_token'], ['front' => 'changed'])['error'], 'malformed token hash fails closed');
$GLOBALS['draft_corrupt_hash_on_second_read'] = false;
$GLOBALS['options']['sanro_studio_draft_' . $created['design_id']]['token_hash'] = hash('sha256', $created['owner_token']);
$GLOBALS['options']['sanro_studio_draft_' . $created['design_id']]['status'] = 'locked';
check_draft('draft_locked', sanro_studio_update_draft($created['design_id'], $created['owner_token'], ['front' => 'valid'])['error'], 'locked draft denied');
check_draft('storage_failed', sanro_studio_create_draft(17, ['front' => 'valid'])['error'], 'collision fails closed');
echo "SANRO draft ID: {$checks} assertions PASS, 0 FAIL\n";
