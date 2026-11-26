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
function esc_url($value){return esc_html($value);}
function wc_get_product($id){return false;}
function add_filter(...$args){}
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
require __DIR__.'/../plugins/sanro-studio/includes/studio-ui.php';
require __DIR__.'/../plugins/sanro-studio/includes/buyer.php';
register_shutdown_function(function () {
    $html=ob_get_contents();
    $buyer_js=file_get_contents(__DIR__.'/../plugins/sanro-studio/assets/buyer.js');
    $buyer_css=file_get_contents(__DIR__.'/../plugins/sanro-studio/assets/buyer.css');
    $collection_js=file_get_contents(__DIR__.'/../plugins/sanro-studio/assets/collection.js');
    $assets=$GLOBALS['enqueued'];
    $by_handle=[];foreach($assets as $asset)if($asset[0]!=='inline')$by_handle[$asset[1][0]]=$asset[1];
    $inline=end($assets)[1][1];
    $assertions=[
        'header and footer'=>($GLOBALS['headers']===1 && $GLOBALS['footers']===1),
        'editor markup'=>strpos($html,'id="sanro-studio-buyer"')!==false,
        'front/back navigation'=>strpos($html,'data-side="front"')!==false && strpos($html,'data-side="back"')!==false,
        'explicit reopen control'=>strpos($html,'id="sanro-reopen-draft"')!==false && strpos($html,'id="sanro-reopen-status"')!==false,
        'reopen control calls owner-bound draft resolver'=>is_string($buyer_js) && strpos($buyer_js,"reopenButton.addEventListener('click'")!==false && strpos($buyer_js,'await reopenDraft()')!==false,
        'checkout disabled'=>strpos($html,'Simpan dan checkout — belum aktif')!==false,
        'visual workspace loaded'=>isset($by_handle['sanro-studio-ui']),
        'native runtime retained'=>isset($by_handle['sanro-studio-buyer'],$by_handle['sanro-studio-collection'],$by_handle['sanro-studio-transport']),
        'CSS path'=>strpos($by_handle['sanro-studio-buyer'][1],'/sanro-studio/assets/buyer.js')!==false,
        'public config'=>strpos($inline,'"product_id":17')!==false,
        'no owner token'=>strpos($inline,'owner_token')===false,
        'inline script escaped'=>strpos($inline,'</script>')===false && strpos($inline,'\\u003C')!==false,
        'concept appbar'=>strpos($html,'sanro-appbar')!==false,
        'dedicated editor layout scope'=>strpos($html,'sanro-buyer-editor')!==false,
        'collection save and email access separated'=>strpos($html,'sanro-collection-save')!==false&&strpos($html,'sanro-collection-access')!==false,
        'collection empty/locked states explicit'=>strpos($collection_js,'Koleksi privat. Verifikasi email')!==false&&strpos($collection_js,'Belum ada desain tersimpan')!==false,
        'saved image draft exposes upload next action'=>strpos($buyer_js,'Lanjut unggah gambar')!==false&&strpos($buyer_js,"sanro:show-panel")!==false,
        'background control refreshed after local file cleanup'=>strpos($buyer_js,'for(const name of completed)sides[name].file=null;')<strpos($buyer_js,'await refreshBackgroundControl();'),
        'automatic print sizing controls'=>strpos($buyer_js,'Pindahkan atau kecilkan objek.')!==false && strpos($html,'sanro-category')===false && strpos($html,'sanro-image-rotation')!==false,
        'theme chrome suppression is limited to opted-in Studio body'=>strpos($buyer_css,'body.sanro-studio-page :is(#masthead,#colophon')!==false,
        'no size preview or sleeve selector'=>strpos($html,'sanro-mockup-size')===false&&strpos($html,'sanro-sleeve')===false,
        'size order quantities remain'=>strpos($html,'data-size="S"')!==false&&strpos($html,'data-size="L"')!==false,

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
