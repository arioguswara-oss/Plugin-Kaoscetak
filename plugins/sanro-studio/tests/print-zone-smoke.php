<?php
/** SANRO mockup print overlay tests. No WordPress installation or DB writes. */
define('ABSPATH', __DIR__ . '/');
$GLOBALS['zones'] = [];
$GLOBALS['can_edit_zone'] = true;
$GLOBALS['fields'] = [];
function absint($value) { return abs((int)$value); }
function get_post_meta($id, $key, $single=true) {
    return $key === '_sanro_studio_print_zones' ? $GLOBALS['zones'] : null;
}
function get_the_ID() { return 17; }
function current_user_can($cap,$id=0) { return $GLOBALS['can_edit_zone']; }
function wp_unslash($value) { return $value; }
function woocommerce_wp_text_input($data) { $GLOBALS['fields'][]=$data; }
class SanroPrintZoneTestProduct {
    public $updated=null;
    public function get_id() { return 17; }
    public function update_meta_data($key,$value) { $this->updated=[$key,$value]; }
}
require_once __DIR__ . '/../includes/product-print-zones.php';
$checks=0;
function check_zone($expected,$actual,$label) {
    global $checks;
    if($expected!==$actual)throw new RuntimeException('FAIL: '.$label);
    $checks++;
}
$base=['x'=>27.0,'y'=>22.0,'width'=>46.0,'height'=>54.0];
$front=['x'=>14.0,'y'=>18.0,'width'=>44.0,'height'=>60.0];
$back=['x'=>24.0,'y'=>22.0,'width'=>39.0,'height'=>51.0];
check_zone($base,sanro_studio_default_print_zone(),'legacy mockup default preserved');
check_zone(['front'=>$base,'back'=>$base],sanro_studio_product_print_zones(17),'missing settings use both defaults');
check_zone(false,sanro_studio_valid_print_zone(['x'=>80,'y'=>20,'width'=>25,'height'=>50]),'horizontal overflow rejected');
check_zone(false,sanro_studio_valid_print_zone(['x'=>10,'y'=>90,'width'=>20,'height'=>15]),'vertical overflow rejected');
check_zone(false,sanro_studio_valid_print_zone(['x'=>0,'y'=>0,'width'=>0,'height'=>70]),'zero width rejected');
check_zone(false,sanro_studio_valid_print_zone(['x'=>0,'y'=>-1,'width'=>50,'height'=>70]),'negative offset rejected');
check_zone(false,sanro_studio_valid_print_zone(['x'=>0,'y'=>0,'width'=>'1e309','height'=>50]),'infinite numeric rejected');
check_zone(false,sanro_studio_valid_print_zone(['x'=>10,'y'=>10,'width'=>30]),'partial zone rejected');
check_zone(false,sanro_studio_valid_print_zone(['x'=>5,'y'=>10,'width'=>20,'height'=>20,'private_path'=>'foo']),'unexpected private data rejected');
check_zone(['x'=>0.0,'y'=>0.0,'width'=>100.0,'height'=>100.0],
    sanro_studio_valid_print_zone(['x'=>'0','y'=>'0','width'=>'100','height'=>'100']),
    'exact full mockup valid');
$GLOBALS['zones']=['front'=>$front,'back'=>$back];
check_zone(['front'=>$front,'back'=>$back],sanro_studio_product_print_zones(17),'both sides independently configured');
$GLOBALS['zones']=['front'=>$front,'back'=>['x'=>80,'y'=>20,'width'=>30,'height'=>60]];
check_zone($base,sanro_studio_product_print_zones(17)['back'],'invalid stored back zone fail-safe fallback');
$GLOBALS['zones']=['front'=>$front,'back'=>$back];
ob_start();sanro_studio_product_print_zone_fields();ob_end_clean();
check_zone(8,count($GLOBALS['fields']),'8 WooCommerce product field controls rendered');
check_zone('_sanro_studio_zone_front_x',$GLOBALS['fields'][0]['id'],'front X field present');
check_zone('_sanro_studio_zone_back_height',$GLOBALS['fields'][7]['id'],'back height field present');
$product=new SanroPrintZoneTestProduct();
$_POST=[];
sanro_studio_save_product_print_zones($product);
check_zone(null,$product->updated,'missing form fields cannot erase zones');
$input=[];
foreach (['front'=>$front,'back'=>$back] as $side=>$zone) {
    foreach ($zone as $key=>$value) $input['_sanro_studio_zone_'.$side.'_'.$key]=(string)$value;
}
$_POST=$input;
sanro_studio_save_product_print_zones($product);
check_zone(['_sanro_studio_print_zones',['front'=>$front,'back'=>$back]],
    $product->updated,'valid two-sided zone saved');
$product->updated=null;
$_POST['_sanro_studio_zone_back_width']='80';
sanro_studio_save_product_print_zones($product);
check_zone(null,$product->updated,'invalid back cannot overwrite valid front');
$_POST=$input;
unset($_POST['_sanro_studio_zone_front_height']);
sanro_studio_save_product_print_zones($product);
check_zone(null,$product->updated,'partial form cannot reset zones');
$_POST=$input;
$GLOBALS['can_edit_zone']=false;
sanro_studio_save_product_print_zones($product);
check_zone(null,$product->updated,'unauthorized product edit denied');
$GLOBALS['fields']=[];
ob_start();sanro_studio_product_print_zone_fields();ob_end_clean();
check_zone(0,count($GLOBALS['fields']),'unauthorized fields hidden');
echo "PASS: SANRO per-product print overlay, security and admin controls ($checks checks)\n";
