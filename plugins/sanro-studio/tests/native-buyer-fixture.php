<?php
/** Development-only HTML fixture; never packaged, never connects to WordPress or sends mail. */
ob_start();require __DIR__.'/native-feature-gap-flow.php';ob_end_clean();
define('SANRO_STUDIO_ENABLE_BUYER_EDITOR',true);define('SANRO_STUDIO_ENABLE_DRAFT_API',true);define('SANRO_STUDIO_ENABLE_PREVIEW_UPLOAD',true);define('SANRO_STUDIO_VERSION','test');
function is_product(){return true;}function get_queried_object_id(){return 7;}function plugin_dir_url($v){return '/assets/';}function wp_enqueue_style(...$v){}function wp_enqueue_script(...$v){}function has_action(...$v){return true;}
function esc_attr($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}function esc_html($v){return esc_attr($v);}function esc_url($v){return esc_attr($v);}function wp_json_encode($v,$flags=0){return json_encode($v,$flags);}function rest_url($v){return 'http://127.0.0.1:18752/wp-json/'.$v;}function wp_create_nonce($v){return 'native-fixture';}
function wp_add_inline_script($handle,$code,$position){$GLOBALS['inline']=$code;}
function get_header(){
 global $fork,$bytes,$transparent;
 $draft=sanro_studio_read_draft($fork['design_id'],$fork['owner_token']);$removed=sanro_studio_remove_background_bytes($bytes);$processed=$removed['bytes'];
 $fixture=['draft'=>$draft,'token'=>$fork['owner_token'],'original'=>base64_encode($bytes),'processed'=>base64_encode($processed),'processedRef'=>$fork['design_id'].'-front-'.hash('sha256',$processed).'.png'];
 echo '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/buyer.css"></head><body><script>'.$GLOBALS['inline'].'window.NATIVE_FIXTURE='.json_encode($fixture).';sessionStorage.setItem("sanro-studio-draft:7",JSON.stringify({id:window.NATIVE_FIXTURE.draft.design_id,token:window.NATIVE_FIXTURE.token}));</script>';
}
function get_footer(){foreach(['estimate.js','transport.js','cart-redirect.js','buyer.js','collection.js'] as $file)echo '<script src="/assets/'.$file.'"></script>';echo '</body></html>';}
$_GET=['sanro_studio_editor'=>'1','product_id'=>'7'];require __DIR__.'/../includes/buyer.php';sanro_studio_render_buyer_editor();
