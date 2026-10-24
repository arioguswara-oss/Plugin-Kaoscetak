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
require __DIR__.'/../plugins/kaoscetak-lite-designer/includes/admin.php';
$count=0;
function check_price($v,$desc){global $count; ++$count; if(!$v)throw new Exception("FAIL: $desc");}
$defaults=kld_price_templates();
check_price(isset($defaults['cotton-24s-short'],$defaults['cotton-24s-long'],$defaults['tunik-oversize34'],$defaults['hoodie'],$defaults['sweatshirt']),'five independent categories');
check_price($defaults['hoodie']['matrix']===[],'hoodie not assigned regular shirt prices');
check_price($defaults['sweatshirt']['matrix']===[],'sweatshirt not assigned regular shirt prices');
// Select products' pricing independently.
$GLOBALS['kld_test_meta'][21]['_kld_price_preset']='cotton-24s-short';
$GLOBALS['kld_test_meta'][22]['_kld_price_preset']='cotton-24s-long';
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
$GLOBALS['kld_test_options']['kld_price_templates']=['cotton-24s-short'=>['L'=>['A4'=>80000]]];
check_price(kld_unit_price(21,'L','A4','none')===80000,'updated regular template retained');
check_price(kld_unit_price(22,'L','A4','none')===90000,'long sleeve follows template plus extra');
check_price(kld_unit_price(24,'L','A4','none')===145000,'hoodie custom unaffected by regular template edit');
check_price(kld_unit_price(23,'M','A4','none')===95000,'tunik template unaffected by regular template edit');
echo "PASS: $count independent pricing preset assertions\n";