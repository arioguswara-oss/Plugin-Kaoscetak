<?php
/** ST-04 bounded buyer catalog pagination regression. */
define('ABSPATH', __DIR__ . '/');
$GLOBALS['catalog_args']=null;
function wc_get_products($args){$GLOBALS['catalog_args']=$args;return (object)['products'=>[901],'max_num_pages'=>3];}
class CatalogFixture {function get_name(){return 'Eligible after page one';}function get_image_id(){return 1;}function get_image(...$args){return '<img alt="fixture">';}}
function wc_get_product($id){return $id===901?new CatalogFixture():null;}
function sanro_studio_public_editor_config($id){return ['valid'=>$id===901];}
function sanro_studio_ui_header($label){echo '<header>'.htmlspecialchars($label).'</header>';}
function sanro_studio_ui_icon($name){return '';}
function get_option($key,$default=[]){return $default;}
function absint($value){return abs((int)$value);}
function wp_unslash($value){return $value;}
function sanitize_text_field($value){return trim((string)$value);}
function esc_attr($value){return htmlspecialchars((string)$value,ENT_QUOTES);}
function esc_html($value){return htmlspecialchars((string)$value,ENT_QUOTES);}
function esc_url($value){return $value;}
function add_query_arg($args,$url='https://example.test/studio'){return $url.'?'.http_build_query(is_array($args)?$args:[]);}
function wc_price($price){return (string)$price;}
function wp_kses_post($value){return $value;}
function plugin_dir_url($path){return 'https://example.test/plugins/studio/';}
function sanro_studio_minimum_configured_price($config){return 1000;}
require dirname(__DIR__).'/includes/buyer.php';
$_GET=['studio_catalog_page'=>'2','studio_search'=>'shirt'];
ob_start();sanro_studio_render_product_picker('https://example.test/studio');$html=ob_get_clean();
$args=$GLOBALS['catalog_args'];
if(($args['limit']??null)!==48||($args['page']??null)!==2||($args['paginate']??null)!==true||($args['s']??null)!=='shirt'
    ||strpos($html,'Eligible after page one')===false||strpos($html,'aria-label="Halaman produk"')===false){fwrite(STDERR,"FAIL buyer catalog page query/render\n");exit(1);}
echo "PASS: buyer query is bounded/paginated and later eligible page renders with server-side search\n";
