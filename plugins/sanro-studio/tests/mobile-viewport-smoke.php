<?php
/** No WordPress, database or network: SANRO editor mobile viewport smoke. */
define('ABSPATH', dirname(__DIR__, 3) . '/');
define('SANRO_STUDIO_VERSION', '0.1.1-dev');
$GLOBALS['sanro_qa_actions'] = [];
function has_action($tag, $callback) {
    return $GLOBALS['sanro_qa_actions'][$tag][$callback] ?? false;
}
function add_action($tag, $callback, $priority = 10) {
    $GLOBALS['sanro_qa_actions'][$tag][$callback] = $priority;
    return true;
}
require_once __DIR__ . '/../includes/buyer.php';
function sanro_check_viewport($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . "\n");
        exit(1);
    }
}
sanro_check_viewport(!has_action('wp_head', 'sanro_studio_print_buyer_viewport_meta'),
    'viewport hook is not globally registered before a buyer route');
sanro_studio_register_buyer_viewport_meta();
sanro_check_viewport(has_action('wp_head', 'sanro_studio_print_buyer_viewport_meta') === 1,
    'buyer route registers early wp_head viewport meta');
sanro_studio_register_buyer_viewport_meta();
sanro_check_viewport(count($GLOBALS['sanro_qa_actions']['wp_head']) === 1,
    'repeated viewport registration is idempotent');
ob_start();
sanro_studio_print_buyer_viewport_meta();
$meta = ob_get_clean();
sanro_check_viewport(strpos($meta, 'name="viewport"') !== false
    && strpos($meta, 'width=device-width') !== false
    && strpos($meta, 'initial-scale=1') !== false,
    'rendered buyer meta uses device width');
$source = file_get_contents(__DIR__ . '/../includes/buyer.php');
sanro_check_viewport(is_string($source)
    && substr_count($source, 'sanro_studio_register_buyer_viewport_meta();') === 2,
    'product picker and editor both register viewport only on their scoped routes');
$cssPath = __DIR__ . '/../assets/buyer.css';
$cssSource = file_get_contents($cssPath);
sanro_check_viewport(is_string($cssSource) && strlen($cssSource) > 0,
    'buyer CSS source is readable');
sanro_check_viewport(sanro_studio_buyer_style_version() === SANRO_STUDIO_VERSION
    . '-' . substr(hash_file('sha256', $cssPath), 0, 12),
    'buyer CSS URL uses its actual content fingerprint');
sanro_check_viewport(strpos($cssSource,
    '.sanro-buyer,.sanro-buyer *{box-sizing:border-box}') !== false,
    'SANRO root and descendants use border-box to prevent padding overflow');
sanro_check_viewport(strpos($cssSource,
    '#sanro-studio-buyer button{min-height:44px!important}') !== false
    && strpos($cssSource,
    '#sanro-studio-buyer .sanro-preview nav button[data-side]{min-height:48px!important}') !== false,
    'touch sizing overrides theme only inside SANRO Studio');
sanro_check_viewport(substr_count($source,
    "wp_enqueue_style('sanro-studio-buyer', \$asset . 'buyer.css', [], sanro_studio_buyer_style_version());") === 2,
    'both product picker and editor enqueue fingerprinted CSS');
echo "PASS: 10 SANRO buyer viewport/CSS checks\n";
