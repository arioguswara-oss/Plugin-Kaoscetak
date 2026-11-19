<?php
// Sprint 22: checkout-process fail-closed regression, isolated from WordPress.
define('ABSPATH', __DIR__ . '/');
define('SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK', true);
require __DIR__ . '/../plugins/sanro-studio/includes/checkout-bridge.php';
$checks = 0;
$notices = [];
function s22_assert($ok, $label) {
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    ++$checks;
}
function WC() { return $GLOBALS['s22_wc'] ?? null; }
function wc_add_notice($message, $type) { $GLOBALS['notices'][] = [$message, $type]; }
function apply_filters($tag, $default, ...$args) { return $default; }
function s22_cart($items) {
    $GLOBALS['s22_wc'] = (object)['cart' => new class($items) {
        private $items;
        function __construct($items) { $this->items = $items; }
        function get_cart() { return $this->items; }
    }];
    $GLOBALS['notices'] = [];
    $GLOBALS['sanro_studio_checkout_verified'] = ['stale' => true];
    sanro_studio_stage_checkout_process();
    return $GLOBALS['notices'];
}
s22_assert(s22_cart([['product_id' => 17]]) === [], 'ordinary cart unaffected');
s22_assert(!isset($GLOBALS['sanro_studio_checkout_verified']), 'stale authorization cleared');
s22_assert(s22_cart([['sanro_studio_design_id' => 'draft-id', 'product_id' => 17]]) !== [], 'missing owner credential rejected');
s22_assert(!isset($GLOBALS['sanro_studio_checkout_verified']), 'unauthorized studio cart never verified');
s22_assert(s22_cart([
    ['sanro_studio_design_id' => 'draft-id', 'product_id' => 17],
    ['product_id' => 18],
]) !== [], 'mixed studio and ordinary cart rejected');
s22_assert(!isset($GLOBALS['sanro_studio_checkout_verified']), 'mixed cart never verified');
s22_assert(s22_cart([
    ['sanro_studio_design_id' => 'draft-id', 'product_id' => '17'],
]) !== [], 'invalid product identity rejected');
s22_assert(s22_cart([
    ['sanro_studio_design_id' => 'draft-id', 'product_id' => 17],
    ['sanro_studio_design_id' => 'draft-id', 'product_id' => 17],
]) !== [], 'missing request-scoped resolver rejected for multi-line studio cart');
echo "SANRO Sprint 22 checkout process: {$checks} assertions PASS, 0 FAIL\n";
