<?php
/** ST-03 frozen physical configuration regression using synthetic product metadata. */
define('ABSPATH', __DIR__ . '/');
$GLOBALS['meta']=['_sanro_studio_safe_areas'=>['front'=>['width'=>10,'height'=>10],'back'=>['width'=>10,'height'=>10]]];
function absint($value){return abs((int)$value);}
function get_post_meta($id,$key,$single=true){return $GLOBALS['meta'][$key]??'';}
function sanro_studio_mockup_palette($id){return ['#ffffff'=>'White'];}
require dirname(__DIR__).'/includes/print-area.php';
require dirname(__DIR__).'/includes/product-area.php';
function frozen_check($ok,$label){if(!$ok){fwrite(STDERR,"FAIL: $label\n");exit(1);}echo "PASS: $label\n";}
$design=['shirt_color'=>'#112233','front'=>['objects'=>[['type'=>'image','x'=>0,'y'=>0,'width'=>20,'height'=>20,'rotation'=>0]]]];
$snapshot=['version'=>1,'safe_areas'=>['front'=>['width'=>30,'height'=>40],'back'=>['width'=>30,'height'=>40]],
    'palette'=>['#112233'=>'Navy'],'print_limits'=>sanro_studio_print_categories(),'print_zones'=>[]];
frozen_check(!empty(sanro_studio_validate_product_design(12,$design,$snapshot['print_limits'],$snapshot)['valid']),'locked design validates against frozen safe area and palette');
frozen_check(sanro_studio_validate_product_design(12,$design,$snapshot['print_limits'])['valid']===false,'same geometry fails against changed current catalog');
$snapshot['safe_areas']['front']['width']='invalid';
frozen_check(sanro_studio_validate_product_design(12,$design,null,$snapshot)['error']==='frozen_product_config_invalid','malformed frozen configuration rejected');
echo "PASS: 3 frozen configuration assertions\n";
