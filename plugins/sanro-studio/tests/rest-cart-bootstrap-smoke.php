<?php
/** Isolated staging REST cart-bootstrap regression; no WordPress, WC or live writes. */
define('ABSPATH', dirname(__DIR__, 3) . '/');
require_once __DIR__ . '/../includes/draft-rest.php';
define('SANRO_STUDIO_ENABLE_DRAFT_API', true);
define('SANRO_STUDIO_ENABLE_STAGING_CART', true);
define('SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT', true);
define('SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK', true);
$GLOBALS['qa_cart'] = (object) ['cart'=>null];
$GLOBALS['qa_loader_calls'] = 0;
$GLOBALS['qa_init'] = true;
$GLOBALS['qa_load_mode'] = 'ok';
$GLOBALS['qa_draft_valid'] = true;
$GLOBALS['qa_contract_valid'] = true;
$GLOBALS['qa_commits'] = 0;
function WC() { return $GLOBALS['qa_cart']; }
function did_action($hook) { return $hook === 'woocommerce_init' && $GLOBALS['qa_init'] ? 1 : 0; }
function wc_load_cart() {
    $GLOBALS['qa_loader_calls']++;
    if ($GLOBALS['qa_load_mode'] === 'throw') throw new RuntimeException('Synthetic loader exception');
    if ($GLOBALS['qa_load_mode'] === 'missing') return;
    $GLOBALS['qa_cart']->cart = new stdClass();
}
function sanro_studio_frontend_enabled() { return true; }
function sanro_studio_read_draft($id, $token) {
    return $GLOBALS['qa_draft_valid'] && $id === 'test-draft' && $token === 'test-owner'
        ? ['valid'=>true, 'status'=>'draft', 'product_id'=>42]
        : ['valid'=>false];
}
function sanro_studio_prepare_cart_design_contract($id, $token, $product) {
    return ['valid'=>$GLOBALS['qa_contract_valid'], 'product_id'=>$product];
}
function sanro_studio_stage_commit_cart_lines($contract, $cart) {
    $GLOBALS['qa_commits']++;
    return ['valid'=>true, 'total_price'=>90000];
}
function rest_ensure_response($value) { return $value; }
function wc_get_cart_url() { return '/cart/'; }
class WP_Error {
    public $code;
    public $data;
    function __construct($code, $message, $data) { $this->code=$code; $this->data=$data; }
}
class QaRequest {
    function get_param($name) { return $name === 'id' ? 'test-draft' : null; }
    function get_header($name) { return $name === 'X-Sanro-Owner-Token' ? 'test-owner' : null; }
}
function qa_assert($condition, $description) {
    if (!$condition) { fwrite(STDERR, "FAIL: $description\n"); exit(1); }
    echo "PASS: $description\n";
}
$request = new QaRequest();
$GLOBALS['qa_cart']->cart = new stdClass();
qa_assert(sanro_studio_stage_rest_cart() === $GLOBALS['qa_cart']->cart, 'preloaded cart reused');
qa_assert($GLOBALS['qa_loader_calls'] === 0, 'preloaded cart does not bootstrap');
$GLOBALS['qa_cart']->cart = null;
qa_assert(is_object(sanro_studio_stage_rest_cart()), 'missing REST cart bootstrapped');
qa_assert($GLOBALS['qa_loader_calls'] === 1, 'WC cart loader called once');
$GLOBALS['qa_cart']->cart = null;
$GLOBALS['qa_init'] = false;
qa_assert(sanro_studio_stage_rest_cart() === null, 'before woocommerce_init fails closed');
qa_assert($GLOBALS['qa_loader_calls'] === 1, 'premature bootstrap avoided');
$GLOBALS['qa_init'] = true;
$GLOBALS['qa_load_mode'] = 'throw';
qa_assert(sanro_studio_stage_rest_cart() === null, 'WC loader exception fails closed');
$GLOBALS['qa_load_mode'] = 'missing';
qa_assert(sanro_studio_stage_rest_cart() === null, 'WC loader without cart fails closed');
$GLOBALS['qa_load_mode'] = 'ok';
$GLOBALS['qa_draft_valid'] = false;
$before = $GLOBALS['qa_loader_calls'];
$rejected = sanro_studio_rest_stage_draft_cart($request);
qa_assert($rejected instanceof WP_Error && $rejected->code === 'sanro_cart_unauthorized', 'invalid owner rejected');
qa_assert($GLOBALS['qa_loader_calls'] === $before, 'unauthorized draft does not create WC session');
$GLOBALS['qa_draft_valid'] = true;
$GLOBALS['qa_contract_valid'] = false;
$rejected = sanro_studio_rest_stage_draft_cart($request);
qa_assert($rejected instanceof WP_Error && $rejected->code === 'sanro_cart_contract', 'invalid quote rejected');
qa_assert($GLOBALS['qa_loader_calls'] === $before, 'invalid quote does not create WC session');
$GLOBALS['qa_contract_valid'] = true;
$accepted = sanro_studio_rest_stage_draft_cart($request);
qa_assert(is_array($accepted) && $accepted['valid'] === true && $accepted['cart_url'] === '/cart/', 'authorized REST cart returns cart URL');
qa_assert($GLOBALS['qa_commits'] === 1 && $GLOBALS['qa_loader_calls'] === $before + 1, 'authorized cart bootstraps then commits once');
qa_assert(!array_key_exists('owner_token', $accepted) && !array_key_exists('checkout_enabled', $accepted), 'response never returns credential or checkout permission');
echo "PASS: 15 staging REST cart-bootstrap checks\n";
