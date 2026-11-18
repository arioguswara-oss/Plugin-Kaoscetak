<?php
define('ABSPATH', __DIR__ . '/');
define('SANRO_STUDIO_ENABLE_DRAFT_API', true);
define('HOUR_IN_SECONDS', 3600);
class WP_Error {
    public $code; public $message; public $data;
    public function __construct($code, $message, $data) {
        $this->code = $code; $this->message = $message; $this->data = $data;
    }
}
class SanroTestRequest {
    private $params; private $headers;
    public function __construct($params = [], $headers = []) {
        $this->params = $params; $this->headers = $headers;
    }
    public function get_param($key) { return $this->params[$key] ?? null; }
    public function get_header($key) { return $this->headers[$key] ?? null; }
}
$GLOBALS['transients'] = [];
$GLOBALS['routes'] = [];
function sanro_studio_frontend_enabled() { return true; }
function register_rest_route($namespace, $route, $args) {
    $GLOBALS['routes'][$namespace . $route] = $args;
}
function wp_verify_nonce($nonce, $action) {
    return $nonce === 'good-nonce' && $action === 'sanro_studio_draft' ? 1 : false;
}
function wp_salt($scheme) { return 'synthetic-test-salt'; }
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient($key, $value, $ttl) {
    $GLOBALS['transients'][$key] = $value;
    return true;
}
function wp_json_encode($value) { return json_encode($value); }
function sanro_studio_public_editor_config($id) {
    return ['valid' => $id === 17];
}
function sanro_studio_validate_product_design($id, $design) {
    return isset($design['front']) && is_array($design['front'])
        ? ['valid' => true] : ['valid' => false, 'error' => 'invalid_design'];
}
function sanro_studio_create_draft($id, $design) {
    return ['valid' => true, 'design_id' => '12345678-1234-4234-8234-123456789abc', 'owner_token' => str_repeat('a', 64)];
}
function sanro_studio_update_draft($id, $token, $design) {
    return $token === str_repeat('a', 64)
        ? ['valid' => true, 'design_id' => $id]
        : ['valid' => false, 'error' => 'not_found'];
}
function rest_ensure_response($value) { return $value; }
require __DIR__ . '/../plugins/sanro-studio/includes/draft-rest.php';
$checks = 0;
function verify_rest($condition, $label) {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}
$_SERVER['REMOTE_ADDR'] = '192.0.2.20';
sanro_studio_register_draft_routes();
verify_rest(count($GLOBALS['routes']) === 2, 'staging routes registered');
verify_rest(sanro_studio_rest_draft_nonce(new SanroTestRequest([], ['X-Sanro-Draft-Nonce' => 'bad'])) instanceof WP_Error, 'invalid nonce rejected');
verify_rest(sanro_studio_rest_draft_nonce(new SanroTestRequest([], ['X-Sanro-Draft-Nonce' => 'good-nonce'])) === true, 'valid nonce accepted');
verify_rest(!sanro_studio_draft_payload_allowed(['image' => 'data:image/png;base64,AAAA']), 'embedded image rejected');
verify_rest(!sanro_studio_draft_payload_allowed(['text' => str_repeat('x', 17000)]), 'oversize rejected');
verify_rest(sanro_studio_draft_payload_allowed(['front' => ['text' => 'OK']]), 'small metadata accepted');
verify_rest(sanro_studio_rest_create_draft(new SanroTestRequest(['product_id' => 0, 'design' => []])) instanceof WP_Error, 'invalid product rejected');
verify_rest(sanro_studio_rest_create_draft(new SanroTestRequest(['product_id' => 17, 'design' => []])) instanceof WP_Error, 'invalid geometry rejected before rate limit');
verify_rest(count($GLOBALS['transients']) === 0, 'invalid geometry does not consume budget');
$request = new SanroTestRequest(['product_id' => 17, 'design' => ['front' => ['text' => 'OK']]]);
for ($i = 0; $i < 20; $i++) {
    verify_rest(sanro_studio_rest_create_draft($request)['valid'] === true, 'creation within budget ' . $i);
}
$limited = sanro_studio_rest_create_draft($request);
verify_rest($limited instanceof WP_Error && $limited->data['status'] === 429, 'guest creation budget enforced');
$bad = sanro_studio_rest_update_draft(new SanroTestRequest(['id' => 'draft', 'design' => []], ['X-Sanro-Owner-Token' => 'bad']));
verify_rest($bad instanceof WP_Error && $bad->data['status'] === 404, 'owner token required');
$good = sanro_studio_rest_update_draft(new SanroTestRequest(['id' => 'draft', 'design' => []], ['X-Sanro-Owner-Token' => str_repeat('a', 64)]));
verify_rest($good['valid'] === true, 'owner update accepted');
echo "SANRO draft REST: {$checks} assertions PASS, 0 FAIL\n";
