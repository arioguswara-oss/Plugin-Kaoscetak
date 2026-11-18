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
function get_option($key, $fallback) { return $GLOBALS['options'][$key] ?? $fallback; }
function update_option($key, $value, $autoload = null) {
    if (!isset($GLOBALS['options'][$key])) return false;
    $GLOBALS['options'][$key] = $value;
    return true;
}
function sanro_studio_validate_product_design($product_id, $design) {
    return $design === ['front' => 'valid'] ? ['valid' => true] : ['valid' => false, 'error' => 'invalid_design'];
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
check_draft('invalid_credentials', sanro_studio_read_draft('../wp-config.php', $created['owner_token'])['error'], 'path rejected');
check_draft('not_found', sanro_studio_update_draft($created['design_id'], str_repeat('a', 64), ['front' => 'valid'])['error'], 'unauthorized update');
check_draft('invalid_design', sanro_studio_update_draft($created['design_id'], $created['owner_token'], [])['error'], 'invalid replacement rejected');
check_draft(true, sanro_studio_update_draft($created['design_id'], $created['owner_token'], ['front' => 'valid'])['valid'], 'authorized update');
check_draft(true, sanro_studio_read_draft($created['design_id'], $created['owner_token'])['valid'], 'read after update');
$GLOBALS['options']['sanro_studio_draft_' . $created['design_id']]['status'] = 'locked';
check_draft('draft_locked', sanro_studio_update_draft($created['design_id'], $created['owner_token'], ['front' => 'valid'])['error'], 'locked draft denied');
check_draft('storage_failed', sanro_studio_create_draft(17, ['front' => 'valid'])['error'], 'collision fails closed');
echo "SANRO draft ID: {$checks} assertions PASS, 0 FAIL\n";
