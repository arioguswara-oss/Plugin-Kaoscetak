<?php
/** No WordPress, database or network: SANRO editor mobile viewport smoke. */
define('ABSPATH', dirname(__DIR__, 3) . '/');
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
echo "PASS: 5 SANRO buyer viewport checks\n";
