<?php
define('ABSPATH', __DIR__ . '/');
$GLOBALS['product_id'] = 17;
$GLOBALS['editor_valid'] = true;
$GLOBALS['is_product'] = true;
function absint($value) { return abs((int) $value); }
function is_product() { return $GLOBALS['is_product']; }
function get_queried_object_id() { return $GLOBALS['product_id']; }
function sanro_studio_public_editor_config($id) {
    return ['valid' => $GLOBALS['editor_valid'] && $id === 17];
}
function add_query_arg($args, $url) { return $url . '?' . http_build_query($args); }
function get_permalink($id) { return 'https://staging.example.test/product/' . $id; }
function esc_url($url) { return htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); }
class SanroBuyerFixture {
    function get_id() { return 17; }
}
$product = new SanroBuyerFixture();
require __DIR__ . '/../plugins/sanro-studio/includes/buyer.php';
$checks = 0;
function verify_buyer($actual, $expected, $label) {
    global $checks;
    if ($actual !== $expected) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}
verify_buyer(sanro_studio_frontend_enabled(), false, 'default disabled');
ob_start(); sanro_studio_product_editor_link(); $html = ob_get_clean();
verify_buyer($html, '', 'no link by default');
verify_buyer(function_exists('sanro_studio_render_buyer_editor'), true, 'frontend handler registered');
echo "SANRO buyer gate: {$checks} assertions PASS, 0 FAIL\n";
