<?php
// Sprint 16 isolated checkout bridge security smoke tests; no WordPress writes.
define('ABSPATH', __DIR__ . '/');
require __DIR__ . '/../plugins/sanro-studio/includes/checkout-bridge.php';
$checks = 0;
function check_bridge($condition, $label) {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL ' . $label);
    $checks++;
}
$disabled = sanro_studio_stage_authorize_checkout('invalid', 'invalid', 17, new stdClass());
check_bridge(($disabled['valid'] ?? null) === false && ($disabled['error'] ?? null) === 'checkout_gate_disabled', 'staging checkout disabled by default');
$binding = sanro_studio_stage_bind_size_order_batch([], []);
check_bridge(($binding['valid'] ?? null) === false && ($binding['error'] ?? null) === 'order_binding_disabled', 'order binding disabled by default');
$invalid = sanro_studio_validate_size_order_item([], new stdClass());
check_bridge(($invalid['valid'] ?? null) === false && ($invalid['error'] ?? null) === 'size_order_identity_mismatch', 'invalid order item rejected');
$missing = sanro_studio_prepare_checkout_order_item([], [], new stdClass());
check_bridge(($missing['valid'] ?? null) === false && ($missing['error'] ?? null) === 'checkout_bridge_unavailable', 'missing identity validators fail closed');
echo "SANRO checkout bridge: {$checks} assertions PASS, 0 FAIL\n";
