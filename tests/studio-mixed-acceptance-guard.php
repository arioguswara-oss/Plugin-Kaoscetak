<?php
/** Offline security contract for the temporary HTTPS guard. No DB/network/order. */
define('ABSPATH', __DIR__.'/');
define('WP_PLUGIN_DIR', sys_get_temp_dir().'/sanro-mixed-guard-'.bin2hex(random_bytes(6)));
mkdir(WP_PLUGIN_DIR.'/sanro-studio',0700,true);
const SSWR_TARGET='https://staging.example.test';
const SSWR_SOURCE='fixture-source';
$GLOBALS['origin']=SSWR_TARGET; $GLOBALS['options']=[]; $GLOBALS['hooks']=[];
$GLOBALS['cart']=[]; $GLOBALS['can']=true; $GLOBALS['nonce']=true;
function home_url($p){return $GLOBALS['origin'];}
function site_url($p){return $GLOBALS['origin'];}
function add_action($hook,$callback,...$args){$GLOBALS['hooks'][$hook]=$callback;}
function current_user_can($cap){return $GLOBALS['can'];}
function wp_die(...$args){throw new RuntimeException('denied');}
function check_admin_referer($s){if(!$GLOBALS['nonce'])throw new RuntimeException('bad_nonce');}
function get_option($k,$d=false){return $GLOBALS['options'][$k]??$d;}
function update_option($k,$v,...$args){$GLOBALS['options'][$k]=$v;return true;}
function add_option($k,$v,...$args){if(isset($GLOBALS['options'][$k]))return false;return update_option($k,$v);}
function delete_option($k){unset($GLOBALS['options'][$k]);}
function wp_json_encode($v,...$args){return json_encode($v);}
function sanro_studio_pricing_is_line($v){return isset($v['sanro_studio_design_id']);}
function WC(){return (object)['cart'=>new class {function get_cart(){return $GLOBALS['cart'];} function get_total($s){return $GLOBALS['total'];}}];}
function sanro_studio_pricing_checkout_design($id,$token,$lines){return $token==='authorized_fixture_token'?($GLOBALS['fresh'][$id]??null):null;}
function sanro_studio_prepare_size_cart_lines($contract){return ['valid'=>true,'lines'=>$GLOBALS['server_lines'][$contract['design_id']]];}
function sanro_studio_pricing_quantity($q){return is_int($q)?$q:null;}
function sanro_studio_idr_charge($v){return is_numeric($v)?(int)$v:null;}
function sanro_studio_public_design_id($id){return $GLOBALS['public_ids'][$id];}
function wc_get_product($id){return new class($id){function __construct(private $id){} function get_type(){return $this->id===76?'variable':'simple';}};}

function sswr_identity(){if(empty($GLOBALS['identity']))throw new RuntimeException('source_identity_not_available_in_offline_fixture');}
function is_ssl(){return true;}
function sswr_flags(){}
require __DIR__.'/../tools/staging-web-release/mixed-checkout-acceptance.php';
$count=0;
function qa_check($ok,$name){global $count;$count++;if(!$ok)throw new RuntimeException($name);}
function qa_denied($value,$name){$denied=false;try{sswr_mixed_expectation($value);}catch(RuntimeException $e){$denied=true;}qa_check($denied,$name);}
$a='12345678-1234-4123-8123-123456789012'; $b='12345678-1234-4123-8123-123456789013';
$expected=['designs'=>[
 ['design_id'=>$a,'public_id'=>'ID-00107','product_id'=>76,'type'=>'variable','lines'=>[['size'=>'M','qty'=>1,'unit_idr'=>85000,'variation_id'=>77,'color'=>'#ffffff']]],
 ['design_id'=>$b,'public_id'=>'ID-00108','product_id'=>53,'type'=>'simple','lines'=>[['size'=>'S','qty'=>1,'unit_idr'=>72000,'variation_id'=>0,'color'=>'#ffffff']]],
], 'total_idr'=>157000];
qa_check(sswr_mixed_expectation($expected)['total_idr']===157000,'valid simple/variable expectation');
foreach(['qty'=>0,'unit_idr'=>0,'variation_id'=>0,'color'=>'javascript:bad'] as $key=>$value){$bad=$expected;$bad['designs'][0]['lines'][0][$key]=$value;qa_denied($bad,'reject invalid '.$key);}
$bad=$expected;$bad['designs'][1]['design_id']=$a;qa_denied($bad,'duplicate design denied');
$bad=$expected;$bad['designs'][1]['product_id']=76;qa_denied($bad,'same product denied');
$bad=$expected;$bad['designs'][1]['public_id']='ID-00107';qa_denied($bad,'same public identity denied');
$bad=$expected;$bad['total_idr']=1;qa_denied($bad,'browser total denied');
$bad=$expected;$bad['designs'][0]['token']='secret';qa_denied($bad,'unexpected private field denied');
$bad=$expected;$bad['designs'][1]['type']='variable';qa_denied($bad,'two simple or variable cases cannot masquerade as mixed types');
$errors=new class{public $codes=[];function add($k,$m){$this->codes[]=$k;}function get_error_codes(){return $this->codes;}};
qa_check($GLOBALS['hooks']['woocommerce_after_checkout_validation']==='sswr_mixed_checkout_boundary','actual Woo boundary registered');
$GLOBALS['cart']=[['sanro_studio_design_id'=>$a,'product_id'=>76],['sanro_studio_design_id'=>$b,'product_id'=>53]];
sswr_mixed_checkout_boundary([],$errors);
qa_check(in_array('sanro_staging_mixed_boundary',$errors->codes,true),'guest unarmed mixed cart blocked before identity/evidence failure');
qa_check($GLOBALS['options']['sswr_mixed_evidence']['state']==='MIXED_VALIDATION_FAILED_CLOSED','identity failure never gives PASS');
$GLOBALS['origin']='https://production.example.test';$errors->codes=[];sswr_mixed_checkout_boundary([],$errors);
qa_check(!$errors->codes,'production checkout untouched');
$GLOBALS['origin']=SSWR_TARGET;$GLOBALS['cart']=[['product_id'=>5]];sswr_mixed_checkout_boundary([],$errors);
qa_check(!$errors->codes,'ordinary Woo cart untouched');
$GLOBALS['can']=false;$denied=false;try{sswr_mixed_page();}catch(RuntimeException $e){$denied=true;}
qa_check($denied,'nonadmin HTTPS operation denied');
qa_check(!isset($GLOBALS['options']['sswr_mixed_arm']),'unauthorized request cannot arm');
// Exercise the complete guard success/duplicate/negative flow with native authorization doubles.
$GLOBALS['identity']=true;
file_put_contents(WP_PLUGIN_DIR.'/sanro-studio/SANRO-STUDIO-BUILD.json',json_encode(['source_commit'=>SSWR_SOURCE]));
$GLOBALS['can']=true;$GLOBALS['cart']=[];$GLOBALS['total']=157000;
$GLOBALS['fresh']=[];$GLOBALS['server_lines']=[];$GLOBALS['public_ids']=[];
foreach($expected['designs'] as $d){
    $id=$d['design_id'];$l=$d['lines'][0];
    $GLOBALS['public_ids'][$id]=$d['public_id'];
    $v=['id'=>$id,'token'=>'authorized_fixture_token','contract'=>['design_id'=>$id,'product_id'=>$d['product_id'],'quote'=>['total_price'=>$l['unit_idr']]]];
    $GLOBALS['fresh'][$id]=$v;
    $GLOBALS['server_lines'][$id]=[['sanro_studio_size'=>$l['size'],'quantity'=>$l['qty'],'sanro_studio_unit_price'=>$l['unit_idr'],'sanro_studio_color'=>$l['color'],'variation_id'=>$l['variation_id'],'variation'=>[]]];
    $GLOBALS['cart'][]=['sanro_studio_pricing_version'=>1,'sanro_studio_design_id'=>$id,'sanro_studio_size'=>$l['size'],'product_id'=>$d['product_id'],'quantity'=>$l['qty'],'variation_id'=>$l['variation_id'],'variation'=>[],'sanro_studio_color'=>$l['color'],'line_subtotal'=>$l['unit_idr'],'line_total'=>$l['unit_idr'],'data'=>new class($l['unit_idr']){function __construct(private $price){}function get_price(){return $this->price;}}];
}
$GLOBALS['sanro_studio_pricing_checkout']=['designs'=>$GLOBALS['fresh']];
$GLOBALS['options']['sswr_mixed_arm']=['source'=>SSWR_SOURCE,'expires'=>time()+900,'expected'=>sswr_mixed_expectation($expected)];
sswr_mixed_checkout_boundary([],$errors);
qa_check($GLOBALS['options']['sswr_mixed_evidence']['state']==='MIXED_AUTHORIZATION_PASS_STOPPED_BEFORE_ORDER','valid native-authorized mixed summary accepted only at stop boundary');
qa_check(!str_contains(json_encode($GLOBALS['options']['sswr_mixed_evidence']),'authorized_fixture_token'),'evidence never includes owner token');
sswr_mixed_checkout_boundary([],$errors);
qa_check($GLOBALS['options']['sswr_mixed_evidence']['state']==='DUPLICATE_SUBMISSION_BLOCKED','repeated submit blocked');
foreach(['variation_id'=>999,'quantity'=>2,'product_id'=>999,'line_total'=>1,'sanro_studio_design_id'=>$b] as $key=>$bad){
    $old=$GLOBALS['cart'][0][$key];$GLOBALS['cart'][0][$key]=$bad;
    sswr_mixed_checkout_boundary([],$errors);
    qa_check($GLOBALS['options']['sswr_mixed_evidence']['state']==='MIXED_VALIDATION_FAILED_CLOSED','cross-design/product/quantity/variation/price mismatch denied: '.$key);
    $GLOBALS['cart'][0][$key]=$old;
}
$GLOBALS['sanro_studio_pricing_checkout']['designs'][$a]['token']='foreign_token';
sswr_mixed_checkout_boundary([],$errors);
qa_check($GLOBALS['options']['sswr_mixed_evidence']['state']==='MIXED_VALIDATION_FAILED_CLOSED','foreign ownership denied');
$GLOBALS['sanro_studio_pricing_checkout']['designs']=$GLOBALS['fresh'];
$GLOBALS['options']['sswr_mixed_arm']['expires']=time()-1;
sswr_mixed_checkout_boundary([],$errors);
qa_check($GLOBALS['options']['sswr_mixed_evidence']['state']==='MIXED_VALIDATION_FAILED_CLOSED','expired arm remains blocked');
$GLOBALS['options']['sswr_mixed_arm']['expires']=time()+900;
$GLOBALS['nonce']=false;$_SERVER['REQUEST_METHOD']='POST';$_POST=['mixed_action'=>'arm'];
ob_start();sswr_mixed_page();ob_end_clean();
qa_check($GLOBALS['options']['sswr_mixed_arm']['expected']===sswr_mixed_expectation($expected),'invalid nonce cannot change existing arm');
unlink(WP_PLUGIN_DIR.'/sanro-studio/SANRO-STUDIO-BUILD.json');rmdir(WP_PLUGIN_DIR.'/sanro-studio');rmdir(WP_PLUGIN_DIR);
echo "PASS: $count mixed HTTPS acceptance security assertions (OFFLINE)\n";
