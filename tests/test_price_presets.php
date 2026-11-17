<?php
// Isolated calculation tests. They do not replace WordPress+WooCommerce integration tests.
define('ABSPATH', __DIR__);
$GLOBALS['kld_test_options'] = [];
$GLOBALS['kld_test_meta'] = [];
$GLOBALS['kld_test_titles'] = [];
function add_action() {}
function add_filter() {}
function get_option($key, $default=[]) { return $GLOBALS['kld_test_options'][$key] ?? $default; }
function get_post_meta($id,$key,$single=true) { return $GLOBALS['kld_test_meta'][$id][$key]??null; }
function get_the_title($id) { return $GLOBALS['kld_test_titles'][$id]??''; }
function sanitize_text_field($value){return trim(strip_tags((string)$value));}
require __DIR__.'/../plugins/kaoscetak-lite-designer/includes/admin.php';
$count=0;
function check_price($v,$desc){global $count; ++$count; if(!$v)throw new Exception("FAIL: $desc");}
$defaults=kld_price_presets();
check_price(isset($defaults['reguler-24s'],$defaults['lengan-panjang-24s'],$defaults['tunik-oversize34'],$defaults['hoodie'],$defaults['sweatshirt']),'five independent categories');
check_price($defaults['hoodie']['matrix']===[],'hoodie not assigned regular shirt prices');
check_price($defaults['sweatshirt']['matrix']===[],'sweatshirt not assigned regular shirt prices');
// Select products' pricing independently.
$GLOBALS['kld_test_meta'][21]['_kld_price_preset']='reguler-24s';
$GLOBALS['kld_test_meta'][22]['_kld_price_preset']='lengan-panjang-24s';
$GLOBALS['kld_test_meta'][23]['_kld_price_preset']='tunik-oversize34';
$GLOBALS['kld_test_meta'][24]['_kld_price_preset']='hoodie';
$GLOBALS['kld_test_meta'][25]['_kld_price_preset']='sweatshirt';
foreach (['S'=>62000,'M'=>65000,'L'=>68000,'XL'=>72000,'2XL'=>77000,'3XL'=>80000,'4XL'=>83000,'5XL'=>85000] as $size=>$price){
  check_price(kld_unit_price(21,$size,'A5','none')===$price,"24s short $size A5");
  check_price(kld_unit_price(22,$size,'A5','none')===$price+10000,"24s long $size A5");
}
check_price(kld_unit_price(21,'L','A5','A3')===103000,'reguler L A5+A3');
check_price(kld_unit_price(21,'L','A3','A5')===103000,'front back symmetric');
check_price(kld_unit_price(23,'M','A4','none')===95000,'tunik M A4');
check_price(kld_unit_price(23,'2XL','A4','none')===null,'tunik unsupported size');
check_price(kld_unit_price(24,'L','A4','none')===null,'hoodie no guessed price');
check_price(kld_unit_price(25,'L','A4','none')===null,'sweatshirt no guessed price');
check_price(kld_unit_price(26,'L','A4','none')===null,'unknown product no guessed price');
// Custom pricing for just one WooCommerce product does not touch other templates.
$GLOBALS['kld_test_meta'][24]['_kld_price_custom']='yes';
$GLOBALS['kld_test_meta'][24]['_kld_matrix']=['L'=>['A4'=>145000]];
check_price(kld_unit_price(24,'L','A4','none')===145000,'hoodie custom price');
check_price(kld_unit_price(25,'L','A4','none')===null,'sweatshirt unaffected by hoodie custom');
check_price(kld_unit_price(21,'L','A4','none')===78000,'reguler unaffected by hoodie custom');
// Admin's template override persists without modifying per-product custom pricing.
$GLOBALS['kld_test_options']['kld_price_presets']=['reguler-24s'=>['L'=>['A4'=>80000]]];
check_price(kld_unit_price(21,'L','A4','none')===80000,'updated regular template retained');
check_price(kld_unit_price(22,'L','A4','none')===90000,'long sleeve follows template plus extra');
check_price(kld_unit_price(24,'L','A4','none')===145000,'hoodie custom unaffected by regular template edit');
check_price(kld_unit_price(23,'M','A4','none')===95000,'tunik template unaffected by regular template edit');
echo "PASS: $count independent pricing preset assertions\n";
// Real mockup normalizer: calibrated, small media on this site's own origin only.
function get_post_mime_type($id){return $GLOBALS['mockup_media'][$id]['mime']??'';}
function wp_get_attachment_metadata($id){return $GLOBALS['mockup_media'][$id]['meta']??[];}
function get_attached_file($id){return $GLOBALS['mockup_media'][$id]['file']??'';}
function wp_get_attachment_url($id){return $GLOBALS['mockup_media'][$id]['url']??'';}
function wp_parse_url($url){return parse_url($url);}function home_url($path='/'){return 'https://fixture.invalid'.$path;}
$small=tempnam(sys_get_temp_dir(),'kld-photo-');$large=tempnam(sys_get_temp_dir(),'kld-photo-');
try{
 file_put_contents($small,'synthetic-media-fixture');file_put_contents($large,str_repeat('x',204801));
 $base=['mime'=>'image/jpeg','meta'=>['width'=>600,'height'=>710],'file'=>$small,'url'=>'https://fixture.invalid/uploads/mockup.jpg'];
 $GLOBALS['mockup_media']=[1=>$base];
 check_price(kld_mockup_attachment_url(1)===$base['url'],'valid small calibrated photo accepted');
 foreach([['mime'=>'image/svg+xml'],['meta'=>['width'=>1200,'height'=>1420]],['file'=>$large],['url'=>'https://cdn.invalid/photo.jpg'],['url'=>'http://fixture.invalid/photo.jpg'],['url'=>'https://fixture.invalid:9999/photo.jpg'],['url'=>'https://user:password@fixture.invalid/photo.jpg']] as $bad){$GLOBALS['mockup_media'][2]=array_replace($base,$bad);check_price(kld_mockup_attachment_url(2)==='','unsafe, oversized or uncalibrated photo rejected');}
 foreach([[],new stdClass,1.5,-1,'1e2','1\n'] as $bad)check_price(kld_mockup_attachment_url($bad)==='','malformed attachment ID rejected');
 $map=kld_normalize_mockup_ids(21,['Hitam'=>['front'=>1,'back'=>2],'unknown'=>['front'=>1]]);
 check_price($map===['Hitam'=>['front'=>1]],'only allowed colour and validated photo survive');
 $GLOBALS['kld_test_meta'][21]['_kld_mockups']=$map;
 check_price(kld_product_mockups(21)===['Hitam'=>['front'=>$base['url']]],'frontend config contains photo URL only, no filesystem path');
 check_price(kld_normalize_mockup_ids(21,'malformed')===[],'malformed photo map fails closed');
}finally{@unlink($small);@unlink($large);}
echo "PASS: $count total pricing preset / mockup assertions\n";
