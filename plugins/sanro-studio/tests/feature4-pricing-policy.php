<?php
/** Actual profile/quote/admin boundaries, synthetic catalog only. No WP/site writes. */
define('ABSPATH',__DIR__.'/');
$GLOBALS['meta']=[7=>['_sanro_studio_pricing_profile'=>'tunik_oversize_34','_sanro_studio_safe_areas'=>['front'=>['width'=>32,'height'=>48],'back'=>['width'=>32,'height'=>48]]]];
$GLOBALS['can_edit']=true;$GLOBALS['simple']=true;
function get_post_meta($id,$key,$single=true){return $GLOBALS['meta'][$id][$key]??'';}
function get_woocommerce_currency(){return 'IDR';}function absint($v){return abs((int)$v);}
function current_user_can($cap,...$args){return $GLOBALS['can_edit'];}function wp_unslash($s){return $s;}
class Product4 {function get_id(){return 7;}function update_meta_data($k,$v){$GLOBALS['meta'][7][$k]=$v;}function get_status(){return 'publish';}function is_purchasable(){return true;}function is_type($t){return $t==='simple'&&$GLOBALS['simple'];}}
function wc_get_product($id){return $id===7?new Product4():false;}
foreach(['print-area','product-area','pricing-profiles','editor-config','pricing-quote','quote','product-pricing','pricing-woocommerce','checkout-charges','checkout-policy','runtime-readiness'] as $f)require __DIR__.'/../includes/'.$f.'.php';
$n=0;function ok4($v,$label){global $n;$n++;if(!$v)throw new RuntimeException('FAIL '.$label);}
function side4($cat){[$w,$h]=sanro_studio_print_categories()[$cat];return ['objects'=>[['type'=>'image','x'=>0,'y'=>0,'width'=>$w,'height'=>$h]]];}
$prices=['M'=>[85000,95000,105000,110000],'L'=>[90000,100000,110000,115000],'XL'=>[95000,105000,115000,120000]];$cats=['A5','A4','A3','A3+'];$extras=[15000,25000,35000,40000];
foreach($prices as $size=>$row)foreach($cats as $i=>$front)foreach([null,'A5','A4','A3','A3+'] as $back){
 $design=['front'=>side4($front),'quantities'=>[$size=>2]];if($back)$design['back']=side4($back);
 $q=sanro_studio_product_quote(7,$design);$j=$back===null?$i:array_search($back,$cats,true);$expected=2*($row[max($i,$j)]+($back===null?0:$extras[min($i,$j)]));
 ok4(!empty($q['valid'])&&$q['total']===$expected&&$q['sleeve_label']==='3/4','approved Tunic full category matrix');
}
$d=['front'=>side4('A5'),'quantities'=>['M'=>1]];ok4(!sanro_studio_product_quote(7,$d+['long_sleeve'=>true])['valid'],'fixed sleeve rejects long override');
$bad=$d;$bad['quantities']=['S'=>1];ok4(!sanro_studio_product_quote(7,$bad)['valid'],'Tunic S unavailable');
$old=sanro_studio_product_quote(7,$d);$custom=$prices;$custom['M'][0]=89000;
$_POST=['_sanro_studio_pricing_profile'=>'tunik_oversize_34','_sanro_studio_price_table_json'=>json_encode($custom)];sanro_studio_save_product_pricing(new Product4());
$new=sanro_studio_product_quote(7,$d);ok4($new['valid']&&$new['total']===89000&&$new['pricing_revision']!==$old['pricing_revision'],'authorized product override changes authoritative quote/revision');
ok4(sanro_studio_pricing_presets()['tunik_oversize_34']['table']['M'][0]===85000,'preset remains immutable');
$before=$GLOBALS['meta'];$_POST['_sanro_studio_price_table_json']='{"M":[1,2,3,4]}';sanro_studio_save_product_pricing(new Product4());ok4($GLOBALS['meta']===$before,'partial price table cannot silently replace valid prices');
$_POST['_sanro_studio_price_table_json']=json_encode($prices);$GLOBALS['can_edit']=false;sanro_studio_save_product_pricing(new Product4());ok4($GLOBALS['meta']===$before,'unauthorized price update denied');$GLOBALS['can_edit']=true;
$_POST=['_sanro_studio_pricing_profile'=>'cotton_combed_24s'];sanro_studio_save_product_pricing(new Product4());ok4(!sanro_studio_product_pricing_config(7)['valid'],'stale override/profile mismatch fails closed');
$_POST['_sanro_studio_price_table_json']='';sanro_studio_save_product_pricing(new Product4());ok4(sanro_studio_product_quote(7,$d)['total']===65000,'explicit blank table restores official cotton preset');
$GLOBALS['simple']=false;ok4(!sanro_studio_public_editor_config(7)['valid'],'native variable products rejected before buyer quote');$GLOBALS['simple']=true;
class Cart4 {public $studio=true,$coupons=[],$fees=[],$tax=0,$discount=0;function get_cart(){return $this->studio?[['sanro_studio_pricing_version'=>1]]:[['product_id'=>9]];}function get_applied_coupons(){return $this->coupons;}function get_fees(){return $this->fees;}function get_total_tax(){return $this->tax;}function get_discount_total(){return $this->discount;}}
$c=new Cart4();ok4(sanro_studio_cart_policy_issues($c)===[],'unmodified Studio price accepted');$c->coupons=['FREE0'];ok4(in_array('coupon_policy_open',sanro_studio_cart_policy_issues($c),true),'zero value coupon still rejected');$c->fees=[(object)['total'=>0]];ok4(in_array('fee_policy_open',sanro_studio_cart_policy_issues($c),true),'zero fee line rejected');$c->tax='1.00';$c->discount=5;ok4(count(sanro_studio_cart_policy_issues($c))===4,'all unapproved adjustments identified');$c->studio=false;ok4(sanro_studio_cart_policy_issues($c)===[],'ordinary WooCommerce cart untouched');
sanro_studio_register_checkout_policy_hooks();ok4(!sanro_studio_pricing_wc_enabled(),'policy hooks remain default OFF');
$GLOBALS['can_edit']=false;$r=new class {function get_header($n){return 'bad';}};ok4(!sanro_studio_readiness_permission($r),'private diagnostic refuses non-admin');
function wp_verify_nonce($n,$action){return $n==='nonce'&&$action==='wp_rest';}$GLOBALS['can_edit']=true;ok4(!sanro_studio_readiness_permission($r),'admin still requires REST nonce');
ok4(sanro_studio_build_identity()['source_commit']==='unpackaged','unpackaged checkout cannot impersonate a build SHA');
$diag=sanro_studio_runtime_readiness(7);ok4(!$diag['flags']['DTF_ADMIN_GENERATE']&&!$diag['flags']['DRIVE_AUTO_PROVISION']&&!$diag['verified_end_to_end'],'diagnostic records OFF gates and no E2E approval');
$response=new class {public $headers=[];function header($k,$v){$this->headers[$k]=$v;}};$req=new class {function get_route(){return '/sanro-studio/v1/drafts/test';}};sanro_studio_rest_source_header($response,null,$req);ok4($response->headers['X-Sanro-Source']==='unpackaged','Studio REST responses carry honest source identity');
echo "PASS: $n profile/quote/policy/admin checks\n";
$price_config=sanro_studio_public_editor_config(7);ok4(sanro_studio_minimum_configured_price($price_config)===62000,'native card minimum uses official configured matrix, not placeholder SKU price');
$price_config['pricing']['table']['S'][0]=1.5;ok4(sanro_studio_minimum_configured_price($price_config)===null,'invalid display matrix cannot publish an invented price');
echo "PASS: product-card server-price audit regressions\n";
