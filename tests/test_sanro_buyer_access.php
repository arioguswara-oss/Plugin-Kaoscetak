<?php
define('ABSPATH', __DIR__ . '/');
define('SANRO_STUDIO_ENABLE_BUYER_EDITOR', true);
define('SANRO_STUDIO_VERSION', 'test');
$GLOBALS['is_product'] = true;
$GLOBALS['queried_id'] = 17;
$GLOBALS['valid'] = true;
$GLOBALS['enqueued'] = [];
function absint($v) { return abs((int)$v); }
function get_option($key, $default=false) { return $default; }
function is_product() { return $GLOBALS['is_product']; }
function get_queried_object_id() { return $GLOBALS['queried_id']; }
function sanro_studio_public_editor_config($id) { return ['valid'=>$GLOBALS['valid'] && $id===17,'product_id'=>$id,'safe_areas'=>['front'=>['width'=>20,'height'=>30]]]; }
function add_query_arg($args,$url) { return $url.'?'.http_build_query($args); }
function get_permalink($id) { return 'https://staging.example.test/product/'.$id; }
function esc_url($url) { return htmlspecialchars($url,ENT_QUOTES,'UTF-8'); }
function plugin_dir_url($file) { return 'https://staging.example.test/wp-content/plugins/sanro-studio/'; }
function wp_enqueue_style(...$args) { $GLOBALS['enqueued'][]=['css',$args]; }
function wp_enqueue_script(...$args) { $GLOBALS['enqueued'][]=['js',$args]; }
function wp_add_inline_script(...$args) { $GLOBALS['enqueued'][]=['inline',$args]; }
function wp_json_encode($v) { return json_encode($v); }
function get_header() { echo '<header>HEADER</header>'; }
function get_footer() { echo '<footer>FOOTER</footer>'; }
class SanroBuyerProduct { function get_id() { return 17; } }
$product = new SanroBuyerProduct();
require __DIR__.'/../plugins/sanro-studio/includes/buyer.php';
$checks=0;
function verify_buyer_case($condition,$label) {
 global $checks;
 if (!$condition) throw new RuntimeException('FAIL '.$label);
 $checks++;
}
verify_buyer_case(sanro_studio_frontend_enabled(),'opt-in enabled');
ob_start();sanro_studio_product_editor_link();$link=ob_get_clean();
verify_buyer_case(strpos($link,'product_id=17')!==false,'valid link');
verify_buyer_case(strpos($link,'sanro_studio_editor=1')!==false,'editor link flag');
$GLOBALS['valid']=false;
ob_start();sanro_studio_product_editor_link();$link=ob_get_clean();
verify_buyer_case($link==='','unconfigured product link hidden');
$GLOBALS['valid']=true;$GLOBALS['is_product']=false;
ob_start();sanro_studio_product_editor_link();$link=ob_get_clean();
verify_buyer_case($link==='','non-product link hidden');
$GLOBALS['is_product']=true;
$_GET=['sanro_studio_editor'=>'1','product_id'=>'18'];
sanro_studio_render_buyer_editor();
verify_buyer_case(count($GLOBALS['enqueued'])===0,'mismatched product id denied');
$_GET=['sanro_studio_editor'=>['1'],'product_id'=>'17'];
sanro_studio_render_buyer_editor();
verify_buyer_case(count($GLOBALS['enqueued'])===0,'array editor flag denied');
$_GET=['sanro_studio_editor'=>'1','product_id'=>'17'];
$GLOBALS['valid']=false;
sanro_studio_render_buyer_editor();
verify_buyer_case(count($GLOBALS['enqueued'])===0,'unavailable product denied');
$GLOBALS['valid']=true;$GLOBALS['is_product']=false;
sanro_studio_render_buyer_editor();
verify_buyer_case(count($GLOBALS['enqueued'])===0,'non-product editor denied');
echo "SANRO buyer access: {$checks} assertions PASS, 0 FAIL\n";
