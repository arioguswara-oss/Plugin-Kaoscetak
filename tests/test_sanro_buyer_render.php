<?php
/** Positive-path harness: the editor intentionally calls exit after rendering. */
define('ABSPATH', __DIR__ . '/');
define('SANRO_STUDIO_ENABLE_BUYER_EDITOR', true);
define('SANRO_STUDIO_VERSION', 'test');
$GLOBALS['enqueued'] = [];
$GLOBALS['headers'] = 0;
$GLOBALS['footers'] = 0;
$GLOBALS['config'] = ['valid'=>true,'product_id'=>17,'unit'=>'cm',
    'safe_areas'=>['front'=>['width'=>32.0,'height'=>48.0]],
    'label'=>'</script><script>alert(1)</script>',
    'categories'=>['A5'=>[14.8,21.0],'A4'=>[21.0,29.7],'A3'=>[29.7,42.0],'A3+'=>[32.0,48.0]]];
function absint($v) { return abs((int)$v); }
function get_option($key, $default=false) { return $default; }
function esc_html($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function esc_url_raw($value) { return filter_var((string)$value, FILTER_SANITIZE_URL); }
function has_action($hook, $callback) { return false; }
function add_action($hook, $callback, $priority = 10) { $GLOBALS['actions'][] = [$hook, $callback, $priority]; }
function is_product() { return true; }
function get_queried_object_id() { return 17; }
function sanro_studio_public_editor_config($id) { return $GLOBALS['config']; }
function sanro_studio_draft_rest_enabled() { return true; }
function rest_url($path) { return 'https://staging.example.test/wp-json/'.$path; }
function wp_create_nonce($action) { return 'test-nonce'; }
function plugin_dir_url($file) {
    if (basename($file) !== 'sanro-studio.php') throw new RuntimeException('Invalid plugin entrypoint');
    return 'https://staging.example.test/wp-content/plugins/sanro-studio/';
}
function wp_enqueue_style(...$args) { $GLOBALS['enqueued'][]=['css',$args]; }
function wp_enqueue_script(...$args) { $GLOBALS['enqueued'][]=['js',$args]; }
function wp_add_inline_script(...$args) { $GLOBALS['enqueued'][]=['inline',$args]; }
function wp_json_encode($v, $flags = 0) { return json_encode($v, $flags); }
function get_header() { $GLOBALS['headers']++; echo '<header>HEADER</header>'; }
function get_footer() { $GLOBALS['footers']++; echo '<footer>FOOTER</footer>'; }
require __DIR__.'/../plugins/sanro-studio/includes/buyer.php';
register_shutdown_function(function () {
    $html=ob_get_contents();
    $buyer_js=file_get_contents(__DIR__.'/../plugins/sanro-studio/assets/buyer.js');
    $assets=$GLOBALS['enqueued'];
    $assertions=[
        'header and footer'=>($GLOBALS['headers']===1 && $GLOBALS['footers']===1),
        'editor markup'=>strpos($html,'id="sanro-studio-buyer"')!==false,
        'front/back navigation'=>strpos($html,'data-side="front"')!==false && strpos($html,'data-side="back"')!==false,
        'explicit reopen control'=>strpos($html,'id="sanro-reopen-draft"')!==false && strpos($html,'id="sanro-reopen-status"')!==false,
        'reopen control calls owner-bound draft resolver'=>is_string($buyer_js) && strpos($buyer_js,"reopenButton.addEventListener('click'")!==false && strpos($buyer_js,'await reopenDraft()')!==false,
        'checkout disabled'=>strpos($html,'Simpan dan checkout — belum aktif')!==false,
        'five assets'=>count($assets)===5,
        'CSS path'=>isset($assets[0]) && strpos($assets[0][1][1],'/sanro-studio/assets/buyer.css')!==false,
        'estimate JS path'=>isset($assets[1]) && strpos($assets[1][1][1],'/sanro-studio/assets/estimate.js')!==false,
        'cart redirect JS path'=>isset($assets[2]) && strpos($assets[2][1][1],'/sanro-studio/assets/cart-redirect.js')!==false,
        'buyer JS path'=>isset($assets[3]) && strpos($assets[3][1][1],'/sanro-studio/assets/buyer.js')!==false,
        'public config'=>isset($assets[4]) && strpos($assets[4][1][1],'"product_id":17')!==false,
        'no owner token'=>isset($assets[4]) && strpos($assets[4][1][1],'owner_token')===false,
        'inline script escaped'=>isset($assets[4]) && strpos($assets[4][1][1],'</script>')===false && strpos($assets[4][1][1],'\\u003C')!==false,
    ];
    ob_end_clean();
    foreach ($assertions as $label=>$ok) {
        if (!$ok) { fwrite(STDERR,"FAIL: {$label}\n"); exit(1); }
    }
    echo 'SANRO buyer render: '.count($assertions)." assertions PASS, 0 FAIL\n";
});
$_GET=['sanro_studio_editor'=>'1','product_id'=>'17'];
ob_start();
sanro_studio_render_buyer_editor();
throw new RuntimeException('Editor did not terminate its dedicated view');
