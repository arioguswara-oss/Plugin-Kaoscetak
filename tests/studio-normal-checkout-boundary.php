<?php
const SSWR_TARGET='https://staging.example.test';const SSWR_SOURCE='fixture';
$GLOBALS['hooks']=[];$GLOBALS['allow']=true;$GLOBALS['origin']=SSWR_TARGET;$GLOBALS['cart']=[['product_id'=>24,'sanro_studio_pricing_version'=>1]];
function add_action($hook,$callback,$priority,$args){$GLOBALS['hooks'][$hook]=[$callback,$priority,$args];}function home_url($s){return $GLOBALS['origin'];}function current_user_can($s){return $GLOBALS['allow'];}function WC(){return (object)['cart'=>new class{function get_cart(){return $GLOBALS['cart'];}}];}function sanro_studio_pricing_is_line($s){return isset($s['sanro_studio_pricing_version']);}
function sswr_identity(){}function sswr_acceptance_record(){return ['order_id'=>56];}function wc_get_order($id){return $id;}function sswr_acceptance_order_summary($id){if($id!==56)throw new RuntimeException('bad order');return ['total'=>'240000'];}function sswr_private_root(){return '/fixture';}function wp_json_encode($a,$f=0){return json_encode($a,$f);}function sswr_write($p,$s){$GLOBALS['evidence']=json_decode($s,true);if(!empty($GLOBALS['storage_fail']))throw new RuntimeException('storage failure');}
require __DIR__.'/../tools/staging-web-release/checkout-acceptance.php';
$errors=new class{public $codes=['payment'];function add($k,$v){$this->codes[]=$k;}function get_error_codes(){return $this->codes;}};
$GLOBALS['sanro_studio_pricing_checkout']=['contract'=>['product_id'=>24,'quote'=>['valid'=>true,'total_price'=>240000]]];
sswr_normal_checkout_boundary([],$errors);if(!in_array('sanro_staging_one_order_boundary',$errors->codes,true)||$GLOBALS['evidence']['state']!=='STUDIO_VALIDATION_PASS_STOPPED_BEFORE_NEW_ORDER'||$GLOBALS['evidence']['order_creation_invoked'])throw new RuntimeException('unsafe normal checkout boundary');
$GLOBALS['storage_fail']=true;$errors->codes=[];sswr_normal_checkout_boundary([],$errors);if(!$errors->codes)throw new RuntimeException('storage failure allowed order');
$GLOBALS['origin']='https://production.example.test';$errors->codes=[];sswr_normal_checkout_boundary([],$errors);if($errors->codes)throw new RuntimeException('production altered');
$GLOBALS['origin']=SSWR_TARGET;$GLOBALS['allow']=false;sswr_normal_checkout_boundary([],$errors);if($errors->codes)throw new RuntimeException('nonadmin altered');
echo "PASS: actual Woo after-validation hook, default denial before writes, source-scoped evidence, storage-error denial, production/nonadmin isolation\n";
