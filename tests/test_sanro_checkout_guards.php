<?php
// Sprint 21: isolated fail-closed classic checkout guards. No WordPress/database writes.
define('ABSPATH', __DIR__ . '/');
define('SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK', true);
require __DIR__ . '/../plugins/sanro-studio/includes/checkout-bridge.php';
$checks = 0;
function s21_check($condition, $label) {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}
function wp_verify_nonce($nonce, $action) {
    return $nonce === 'validnonce' && $action === 'sanro_studio_checkout_auth';
}
function WC() { return $GLOBALS['s21_wc'] ?? null; }
function s21_throws($callback, $label) {
    try { $callback(); } catch (RuntimeException $e) { return true; }
    return false;
}
s21_check(sanro_studio_stage_checkout_post_token(null, 'id', 17) === null, 'missing credentials rejected');
$_POST = ['sanro_studio_checkout_token' => str_repeat('a', 64), 'sanro_studio_checkout_nonce' => 'bad'];
s21_check(sanro_studio_stage_checkout_post_token(null, 'id', 17) === null, 'invalid nonce rejected');
$_POST['sanro_studio_checkout_nonce'] = 'validnonce';
$_POST['sanro_studio_checkout_token'] = 'not-a-token';
s21_check(sanro_studio_stage_checkout_post_token(null, 'id', 17) === null, 'malformed token rejected');
$_POST['sanro_studio_checkout_token'] = str_repeat('a', 64);
s21_check(sanro_studio_stage_checkout_post_token(null, 'id', 17) === str_repeat('a', 64), 'valid request-scoped credential accepted');
unset($GLOBALS['sanro_studio_checkout_verified']);
$GLOBALS['s21_wc'] = (object) ['cart' => new class {
    function get_cart() { return [['sanro_studio_design_id' => 'draft-id']]; }
}];
s21_check(s21_throws(function () {
    sanro_studio_stage_checkout_verify_order_batch(new stdClass(), []);
}, 'missing contract'), 'studio cart without authorization rejected before save');
$GLOBALS['s21_wc'] = (object) ['cart' => new class {
    function get_cart() { return [['product_id' => 17]]; }
}];
s21_check(!s21_throws(function () {
    sanro_studio_stage_checkout_verify_order_batch(new stdClass(), []);
}, 'ordinary cart'), 'ordinary cart unaffected');
echo "SANRO Sprint 21 checkout guards: {$checks} assertions PASS, 0 FAIL\n";
