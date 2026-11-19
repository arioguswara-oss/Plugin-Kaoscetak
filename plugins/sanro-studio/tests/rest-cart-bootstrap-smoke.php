<?php
/** Isolated staging REST cart-bootstrap regression; no WordPress, WC or live writes. */
define('ABSPATH', dirname(__DIR__, 3) . '/');
// Keep headers unsent until the simulated guest session cookie is set.
ob_start();
require_once __DIR__ . '/../includes/draft-rest.php';
define('SANRO_STUDIO_ENABLE_DRAFT_API', true);
define('SANRO_STUDIO_ENABLE_STAGING_CART', true);
define('SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT', true);
define('SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK', true);
class QaSession {
    public $active = false;
    public $cookieCalls = 0;
    public $fail = false;
    public $noActivate = false;
    function has_session() { return $this->active; }
    function set_customer_session_cookie($create) {
        $this->cookieCalls++;
        if ($this->fail) throw new RuntimeException('synthetic cookie error');
        if (!$this->noActivate && $create) $this->active = true;
    }
}
class QaCart {
    public $lines = [];
    public $failRead = false;
    function get_cart() {
        if ($this->failRead) throw new RuntimeException('synthetic cart read failed');
        return $this->lines;
    }
}
$GLOBALS['qa_cart'] = (object) ['cart'=>null, 'session'=>new QaSession()];
$GLOBALS['qa_reauth_valid'] = true;
$GLOBALS['qa_reauth_price'] = 90000;
$GLOBALS['qa_reauth_calls'] = 0;
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
    $GLOBALS['qa_cart']->cart = new QaCart();
}
function sanro_studio_frontend_enabled() { return true; }
function sanro_studio_read_draft($id, $token) {
    return $GLOBALS['qa_draft_valid'] && $id === 'test-draft' && $token === 'test-owner'
        ? ['valid'=>true, 'status'=>'draft', 'product_id'=>42]
        : ['valid'=>false];
}
function sanro_studio_prepare_cart_design_contract($id, $token, $product) {
    return ['valid'=>$GLOBALS['qa_contract_valid'], 'product_id'=>$product,
        'quote'=>['valid'=>true,'currency'=>'IDR','total_price'=>90000]];
}
function sanro_studio_stage_commit_cart_lines($contract, $cart) {
    $GLOBALS['qa_commits']++;
    $cart->lines=['line-0'=>['sanro_studio_design_id'=>'test-draft']];
    return ['valid'=>true, 'total_price'=>90000];
}
function sanro_studio_stage_reauthorize_cart($id, $token, $product, $cart) {
    $GLOBALS['qa_reauth_calls']++;
    return ['valid'=>$GLOBALS['qa_reauth_valid'] && $id==='test-draft'
        && $token==='test-owner' && $product===42,
        'total_price'=>$GLOBALS['qa_reauth_price']];
}
function rest_ensure_response($value) { return $value; }
function wc_get_cart_url() { return '/cart/'; }
$GLOBALS['qa_checkout_mode'] = 'classic';
function wc_get_page_id($kind) { return $kind === 'checkout' ? 17 : -1; }
function get_post($id) {
    if ($id !== 17 || $GLOBALS['qa_checkout_mode'] === 'missing') return null;
    return (object)[
        'post_type' => 'page',
        'post_status' => $GLOBALS['qa_checkout_mode'] === 'unpublished' ? 'draft' : 'publish',
        'post_content' => $GLOBALS['qa_checkout_mode'] === 'plain'
            ? '<p>Not configured</p>' : '[woocommerce_checkout]',
    ];
}
function has_block($name, $page) {
    return $name === 'woocommerce/checkout' && $GLOBALS['qa_checkout_mode'] === 'block';
}
function has_shortcode($content, $name) {
    return $name === 'woocommerce_checkout'
        && strpos($content, '[woocommerce_checkout]') !== false;
}
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
qa_assert(sanro_studio_stage_classic_checkout_ready() === true,
    'published classic checkout is compatible');
$GLOBALS['qa_checkout_mode'] = 'block';
$beforeBlocked = $GLOBALS['qa_loader_calls'];
$blocked = sanro_studio_rest_stage_draft_cart($request);
qa_assert($blocked instanceof WP_Error && $blocked->code === 'sanro_checkout_page_incompatible'
    && ($blocked->data['status'] ?? null) === 503,
    'Checkout Block refused by REST staging cart');
qa_assert($GLOBALS['qa_loader_calls'] === $beforeBlocked
    && $GLOBALS['qa_commits'] === 0,
    'block checkout does not initialize cart or mutate session');
$GLOBALS['qa_checkout_mode'] = 'missing';
qa_assert(sanro_studio_stage_classic_checkout_ready() === false,
    'missing checkout page fails closed');
$GLOBALS['qa_checkout_mode'] = 'unpublished';
qa_assert(sanro_studio_stage_classic_checkout_ready() === false,
    'unpublished checkout page fails closed');
$GLOBALS['qa_checkout_mode'] = 'plain';
qa_assert(sanro_studio_stage_classic_checkout_ready() === false,
    'published page without classic shortcode fails closed');
$GLOBALS['qa_checkout_mode'] = 'classic';
$GLOBALS['qa_cart']->cart = new QaCart();
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
$GLOBALS['qa_cart']->session->fail = true;
$failure = sanro_studio_rest_stage_draft_cart($request);
qa_assert($failure instanceof WP_Error && $failure->code === 'sanro_cart_unavailable', 'cookie exception fails closed');
qa_assert($GLOBALS['qa_commits'] === 0, 'cookie exception never mutates WC cart');
$GLOBALS['qa_cart']->session->fail = false;
$GLOBALS['qa_cart']->session->noActivate = true;
$failure = sanro_studio_rest_stage_draft_cart($request);
qa_assert($failure instanceof WP_Error && $failure->code === 'sanro_cart_unavailable', 'cookie not persisted fails closed');
qa_assert($GLOBALS['qa_commits'] === 0, 'missing session never mutates WC cart');
$GLOBALS['qa_cart']->session->noActivate = false;
$accepted = sanro_studio_rest_stage_draft_cart($request);
qa_assert(is_array($accepted) && $accepted['valid'] === true && $accepted['cart_url'] === '/cart/', 'authorized REST cart returns cart URL');
qa_assert($GLOBALS['qa_commits'] === 1 && $GLOBALS['qa_loader_calls'] === $before + 1, 'authorized cart bootstraps then commits once');
qa_assert($GLOBALS['qa_cart']->session->has_session() === true, 'guest session active before cart response');
$cookieCalls = $GLOBALS['qa_cart']->session->cookieCalls;
qa_assert(sanro_studio_stage_rest_session_ready() === true, 'existing guest session accepted');
qa_assert($GLOBALS['qa_cart']->session->cookieCalls === $cookieCalls
    && $GLOBALS['qa_commits'] === 1,
    'existing session does not reset cookie or repeat cart commit');
qa_assert(!array_key_exists('owner_token', $accepted) && !array_key_exists('checkout_enabled', $accepted), 'response never returns credential or checkout permission');
// Repeating a successful POST after the response was lost must never append
// duplicate lines or bypass the fresh owner and price validation.
$retry = sanro_studio_rest_stage_draft_cart($request);
qa_assert(is_array($retry) && $retry['valid'] === true
    && $retry['total_price'] === 90000, 'same draft retry returns verified cart');
qa_assert($GLOBALS['qa_commits'] === 1
    && count($GLOBALS['qa_cart']->cart->lines) === 1, 'same draft retry does not duplicate cart lines');
qa_assert($GLOBALS['qa_reauth_calls'] === 1, 'retry reauthorizes owner and quote');
$GLOBALS['qa_reauth_valid'] = false;
$conflict = sanro_studio_rest_stage_draft_cart($request);
qa_assert($conflict instanceof WP_Error && $conflict->code === 'sanro_cart_conflict',
    'existing foreign or unauthorized cart rejected');
qa_assert($GLOBALS['qa_commits'] === 1, 'conflict does not insert another cart line');
$GLOBALS['qa_reauth_valid'] = true;
$GLOBALS['qa_reauth_price'] = 1;
$stale = sanro_studio_rest_stage_draft_cart($request);
qa_assert($stale instanceof WP_Error && $stale->code === 'sanro_cart_conflict',
    'stale quote cannot reuse existing cart');
$GLOBALS['qa_reauth_price'] = 90000;
$GLOBALS['qa_cart']->cart->failRead = true;
$unreadable = sanro_studio_rest_stage_draft_cart($request);
qa_assert($unreadable instanceof WP_Error && $unreadable->code === 'sanro_cart_unavailable',
    'cart read failure returns temporary unavailable without mutation');
qa_assert($GLOBALS['qa_commits'] === 1, 'cart inspection failure never inserts lines');
echo "PASS: 36 staging REST cart-bootstrap checks\n";
ob_end_flush();
