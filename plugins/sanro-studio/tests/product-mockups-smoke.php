<?php
/** SANRO native product mockup tests; no WP installation/DB required. */
define('ABSPATH', __DIR__ . '/');
define('SANRO_STUDIO_VERSION', '0.1.1-dev');
$GLOBALS['mockup_meta'] = ['front' => 41, 'back' => 42, 'extra' => 99];
$GLOBALS['can_edit'] = true;
$GLOBALS['screen'] = 'product';
$GLOBALS['media_calls'] = 0;
$GLOBALS['scripts'] = [];
$GLOBALS['input_fields'] = [];
function absint($id) { return abs((int) $id); }
function get_post_meta($id,$key,$single=true) {
    if ($key === '_sanro_studio_mockups') return $GLOBALS['mockup_meta'];
    return null;
}
function wp_attachment_is_image($id) { return in_array($id, [41,42,44], true); }
function get_post_status($id) { return $id === 44 ? 'private' : 'inherit'; }
function wp_get_attachment_image_url($id,$size) {
    return $size === 'large' ? 'https://cdn.example.test/mockup-' . $id . '.jpg' : false;
}
function esc_url_raw($url) { return $url; }
function get_the_ID() { return 17; }
function current_user_can($cap,$id=0) { return $GLOBALS['can_edit']; }
function woocommerce_wp_text_input($args) { $GLOBALS['input_fields'][] = $args; }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function wp_unslash($value) { return $value; }
function get_current_screen() { return (object) ['id' => $GLOBALS['screen']]; }
function wp_enqueue_media() { $GLOBALS['media_calls']++; }
function plugin_dir_url($path) { return 'https://staging.example.test/wp-content/plugins/sanro-studio/'; }
function wp_enqueue_script($name,$src,$deps,$version,$footer) {
    $GLOBALS['scripts'][] = compact('name','src','deps','version','footer');
}
class SanroMockProduct {
    public $updated = null;
    function get_id() { return 17; }
    function update_meta_data($key,$value) { $this->updated = [$key,$value]; }
}
require_once __DIR__ . '/../includes/product-mockups.php';
$checks=0;
function verify_mockup($expected,$actual,$name) {
    global $checks;
    if ($expected !== $actual) throw new RuntimeException('FAIL: ' . $name);
    $checks++;
}
verify_mockup(41,sanro_studio_valid_mockup_attachment('41'),'valid numeric media ID');
verify_mockup(42,sanro_studio_valid_mockup_attachment(42),'valid numeric media int');
verify_mockup(0,sanro_studio_valid_mockup_attachment('0'),'zero rejected');
verify_mockup(0,sanro_studio_valid_mockup_attachment('-41'),'negative rejected');
verify_mockup(0,sanro_studio_valid_mockup_attachment('41abc'),'invalid ID rejected');
verify_mockup(0,sanro_studio_valid_mockup_attachment(43),'nonimage attachment rejected');
verify_mockup(0,sanro_studio_valid_mockup_attachment(44),'nonpublic attachment rejected');
verify_mockup(['front'=>41,'back'=>42],sanro_studio_product_mockup_attachments(17),'only two valid sides returned');
$urls = sanro_studio_product_mockup_urls(17);
verify_mockup('https://cdn.example.test/mockup-41.jpg',$urls['front'] ?? null,'front verified CDN URL');
verify_mockup('https://cdn.example.test/mockup-42.jpg',$urls['back'] ?? null,'back verified CDN URL');
verify_mockup(2,count($urls),'never expose arbitrary private metadata');
verify_mockup([],sanro_studio_product_mockup_attachments(0),'invalid product ID rejected');
$product = new SanroMockProduct();
$_POST = [];
sanro_studio_save_product_mockups($product);
verify_mockup(null,$product->updated,'missing fields cannot overwrite config');
$_POST = ['_sanro_studio_mockup_front'=>'41','_sanro_studio_mockup_back'=>'42'];
sanro_studio_save_product_mockups($product);
verify_mockup(['_sanro_studio_mockups',['front'=>41,'back'=>42]],$product->updated,'valid pair saved');
$product->updated=null;
$_POST['_sanro_studio_mockup_back']='44';
sanro_studio_save_product_mockups($product);
verify_mockup(null,$product->updated,'private attachment cannot overwrite config');
$_POST['_sanro_studio_mockup_back']='42';
$GLOBALS['can_edit']=false;
sanro_studio_save_product_mockups($product);
verify_mockup(null,$product->updated,'unauthorized editor cannot write');
$GLOBALS['can_edit']=true;
$_POST['_sanro_studio_mockup_front']='';
sanro_studio_save_product_mockups($product);
verify_mockup(['_sanro_studio_mockups',['back'=>42]],$product->updated,'empty front intentionally clears one side');
ob_start();
sanro_studio_product_mockup_fields();
$html=ob_get_clean();
verify_mockup(true,strpos($html,'data-sanro-side="front"')!==false,'front media chooser in admin');
verify_mockup(true,strpos($html,'data-sanro-side="back"')!==false,'back media chooser in admin');
verify_mockup(2,count($GLOBALS['input_fields']),'both WooCommerce metadata fields rendered');
sanro_studio_enqueue_product_mockup_admin('index.php');
verify_mockup(0,$GLOBALS['media_calls'],'media not loaded on unrelated admin screen');
sanro_studio_enqueue_product_mockup_admin('post.php');
verify_mockup(1,$GLOBALS['media_calls'],'media loaded on product screen');
verify_mockup(1,count($GLOBALS['scripts']),'admin mockup JS loaded only on product screen');
$GLOBALS['screen']='post';
sanro_studio_enqueue_product_mockup_admin('post.php');
verify_mockup(1,$GLOBALS['media_calls'],'admin post editor cannot load media picker');
echo "PASS: SANRO product mockup metadata and admin controls ({$checks} assertions)\n";
