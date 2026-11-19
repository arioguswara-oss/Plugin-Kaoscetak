<?php
/**
 * SANRO Studio staging cart must remain unavailable by default.
 * Isolated PHP test; never boots WordPress or writes to a database.
 */
define('ABSPATH', __DIR__ . '/');
define('SANRO_STUDIO_ENABLE_DRAFT_API', true);
$GLOBALS['registered_routes'] = [];
$GLOBALS['cart_wc_calls'] = 0;
$GLOBALS['cart_draft_reads'] = 0;

function sanro_studio_frontend_enabled() { return true; }
function register_rest_route($namespace, $route, $args) {
    $GLOBALS['registered_routes'][] = [
        'namespace' => $namespace, 'route' => $route, 'args' => $args,
    ];
}
function WC() { $GLOBALS['cart_wc_calls']++; return null; }
function sanro_studio_read_draft($id, $token) {
    $GLOBALS['cart_draft_reads']++;
    throw new RuntimeException('Draft must not be accessed with staging cart disabled');
}
class WP_Error {
    public $code;
    public $data;
    function __construct($code, $message, $data) {
        $this->code = $code;
        $this->data = $data;
    }
}
class SanroDisabledCartRequest {
    function get_param($name) { return $name === 'id' ? 'synthetic-draft' : null; }
    function get_header($name) { return null; }
}
require __DIR__ . '/../plugins/sanro-studio/includes/draft-rest.php';
$checks = 0;
function sanro_cart_gate_check($condition, $message) {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
}
sanro_studio_register_draft_routes();
$routes = $GLOBALS['registered_routes'];
$paths = array_column($routes, 'route');

sanro_cart_gate_check(count($routes) === 5, 'only five draft REST route registrations');
sanro_cart_gate_check(in_array('/drafts', $paths, true), 'draft creation route exists');
sanro_cart_gate_check((bool) array_filter($paths, function($path) {
    return strpos($path, '/quote') !== false;
}), 'read-only server quote route exists');
sanro_cart_gate_check(!array_filter($paths, function($path) {
    return strpos($path, '/cart') !== false;
}), 'cart POST route stays unregistered by default');
$creation = null;
foreach ($routes as $route) {
    if ($route['route'] === '/drafts') $creation = $route['args'];
}
sanro_cart_gate_check(
    is_array($creation) && ($creation['permission_callback'] ?? null) === 'sanro_studio_rest_draft_nonce',
    'draft POST retains nonce permission gate'
);
$nonceRejected = sanro_studio_rest_draft_nonce(new SanroDisabledCartRequest());
sanro_cart_gate_check($nonceRejected instanceof WP_Error
    && $nonceRejected->data['status'] === 403, 'missing draft nonce rejected');
$cartRejected = sanro_studio_rest_stage_draft_cart(new SanroDisabledCartRequest());
sanro_cart_gate_check($cartRejected instanceof WP_Error
    && $cartRejected->code === 'sanro_cart_disabled', 'direct cart handler fails closed');
sanro_cart_gate_check($cartRejected->data['status'] === 403, 'disabled cart returns HTTP 403');
sanro_cart_gate_check($GLOBALS['cart_wc_calls'] === 0
    && $GLOBALS['cart_draft_reads'] === 0, 'disabled cart never accesses WC or draft');
sanro_cart_gate_check(!isset($cartRejected->data['owner_token']),
    'disabled cart response never contains owner token');
echo "SANRO staging cart default gate: {$checks} assertions PASS, 0 FAIL\n";
