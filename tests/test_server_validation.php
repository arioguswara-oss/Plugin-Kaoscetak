<?php
/** Synthetic WordPress/Woo doubles calling real KLD validation, price and hook functions. */
define('ABSPATH',__DIR__);define('HOUR_IN_SECONDS',3600);
$GLOBALS['meta']=[];$GLOBALS['products']=[];$GLOBALS['hooks']=[];$GLOBALS['filters']=[];$GLOBALS['notices']=[];$GLOBALS['statuses']=[42=>'private'];$count=0;
function add_action($name,$fn,$priority=10,$args=1){$GLOBALS['hooks'][$name][]=$fn;}
function add_filter($name,$fn,$priority=10,$args=1){$GLOBALS['filters'][$name][]=$fn;}
function absint($v){return abs((int)$v);}function get_option($k,$d=false){return $d;}
function get_post_meta($id,$key,$one=true){return $GLOBALS['meta'][$id][$key]??'';}
function get_post_type($id){return $id===42?'kld_design':'post';}
function get_post_status($id){return $GLOBALS['statuses'][$id]??false;}
function wc_get_product($id){return $GLOBALS['products'][$id]??false;}
function sanitize_title($v){return strtolower(str_replace(' ','-',(string)$v));}
function sanitize_text_field($v){return trim(strip_tags($v));}function sanitize_textarea_field($v){return trim(strip_tags($v));}
function sanitize_hex_color($v){return preg_match('/^#[0-9a-f]{6}$/i',$v)?$v:null;}
function sanitize_email($v){return filter_var($v,FILTER_VALIDATE_EMAIL)?$v:'';}function is_email($v){return (bool)filter_var($v,FILTER_VALIDATE_EMAIL);}
function is_admin(){return false;}function wp_doing_ajax(){return false;}function esc_html($v){return htmlspecialchars($v,ENT_QUOTES);}
function kld_drive_connected(){return true;}
function kld_drive_verify(...$args){return !empty($GLOBALS['drive_failure'])?new WP_Error('drive','Fixture unavailable'):['id'=>$args[0]];}
function wc_add_notice($m,$kind){$GLOBALS['notices'][]=$m;}
function wc_get_orders($a){return $a['billing_email']==='repeat@example.test'?[71]:[];}function wc_get_is_paid_statuses(){return ['processing','completed'];}
function is_wp_error($v){return $v instanceof WP_Error;}
class WP_Error {public $errors=[];function __construct($c='',$m='',$d=[]){if($c)$this->add($c,$m);}function add($c,$m){$this->errors[$c]=$m;}function get_error_code(){return array_key_first($this->errors);}function get_error_message(){return reset($this->errors);}}
class WP_REST_Request {private $id,$token;function __construct($id,$token){$this->id=$id;$this->token=$token;}function get_param($k){return $k==='design_id'?$this->id:null;}function get_header($k){return $this->token;}}
class TestAttribute {function is_taxonomy(){return false;}function get_options(){return ['S','L','Hitam','Putih'];}}
class TestProduct {public $id,$parent,$type,$price=1,$attrs=[];function __construct($id,$type='simple',$parent=0){$this->id=$id;$this->type=$type;$this->parent=$parent;}function get_id(){return $this->id;}function get_parent_id(){return $this->parent;}function is_purchasable(){return true;}function is_type($types){return in_array($this->type,(array)$types,true);}function get_attributes(){return ['size'=>new TestAttribute,'color'=>new TestAttribute];}function get_variation_attributes(){return $this->attrs;}function set_price($p){$this->price=$p;}function get_price(){return $this->price;}}
class TestCart {public $items=[];function get_cart(){return $this->items;}}
class TestCustomer {public $email='';function get_billing_email(){return $this->email;}}
$GLOBALS['wc']=(object)['cart'=>new TestCart,'customer'=>new TestCustomer];function WC(){return $GLOBALS['wc'];}
$plugin=getenv('KLD_TEST_PLUGIN_DIR')?:__DIR__.'/../plugins/kaoscetak-lite-designer';
require $plugin.'/includes/admin.php';require $plugin.'/includes/repeat.php';require $plugin.'/includes/api.php';require $plugin.'/includes/sharing.php';require $plugin.'/includes/store.php';
function check($ok,$label){global $count;$count++;if(!$ok)throw new RuntimeException('FAIL '.$label);}
$edit=str_repeat('c',64);
$GLOBALS['meta'][901]=['_kld_enabled'=>'yes','_kld_price_preset'=>'reguler-24s','_kld_colors'=>'Hitam|Putih'];
$GLOBALS['meta'][902]=$GLOBALS['meta'][901];
$GLOBALS['meta'][42]=['_kld_edit_hash'=>hash('sha256',$edit),'_kld_status'=>'cart','_kld_product'=>901,'_kld_sides'=>['front'=>['tier'=>'A5','text'=>['value'=>'TEST','x'=>0,'y'=>0,'w'=>5,'h'=>5]],'back'=>['tier'=>'none']]];
$GLOBALS['products'][901]=new TestProduct(901);$GLOBALS['products'][902]=new TestProduct(902,'variable');
$line=['kld_design_id'=>42,'product_id'=>901,'variation_id'=>0,'kld_size'=>'L','kld_color'=>'Hitam','quantity'=>1,'kld_unit_price'=>68000,'data'=>clone $GLOBALS['products'][901]];
$cart=WC()->cart;$cart->items=[$line];
check(kld_validate_cart_designs($cart)===[],'valid simple cart');
foreach([0,-42,42.5,true,[], '42junk','4.2e1',((string)PHP_INT_MAX).'0'] as $id){
 check(is_wp_error(kld_api_auth(new WP_REST_Request($id,$edit))),'malformed REST design ID rejected');
 $cart->items=[array_replace($line,['kld_design_id'=>$id])];check((bool)kld_validate_cart_designs($cart),'malformed cart design ID rejected');
}
check(kld_api_auth(new WP_REST_Request('42',$edit))===42,'canonical string ID accepted');
check(is_wp_error(kld_api_auth(new WP_REST_Request(43,$edit))),'other Design ID rejected');
check(is_wp_error(kld_api_auth(new WP_REST_Request(42,str_repeat('f',64)))),'wrong edit token rejected');
$GLOBALS['statuses'][42]='trash';check(is_wp_error(kld_api_auth(new WP_REST_Request(42,$edit))),'trashed REST design rejected');check(!kld_design_exists(42),'trashed owner/share design rejected');$GLOBALS['statuses'][42]='private';
foreach([0,-1,101,1.5,1.0,true,[], '1e2','01','1.5'] as $qty){$cart->items=[array_replace($line,['quantity'=>$qty])];check((bool)kld_validate_cart_designs($cart),'malformed cart quantity rejected');}
foreach(['kld_size','kld_color'] as $field){$cart->items=[array_replace($line,[$field=>[]])];check((bool)kld_validate_cart_designs($cart),'nested cart field rejected without fatal');}
$cart->items=[array_replace($line,['data'=>new TestProduct(999)])];check((bool)kld_validate_cart_designs($cart),'actual simple product cannot differ from design');
$cart->items=[array_replace($line,['product_id'=>902])];check((bool)kld_validate_cart_designs($cart),'parent product tampering rejected');
$cart->items=[array_replace($line,['quantity'=>100]),array_replace($line,['quantity'=>100]),array_replace($line,['quantity'=>1])];check((bool)kld_validate_cart_designs($cart),'combined quantity above 200 rejected');
$cart->items=[array_replace($line,['quantity'=>'100']),array_replace($line,['quantity'=>100])];check(kld_validate_cart_designs($cart)===[],'combined 200 accepted');
$variation=new TestProduct(1001,'variation',902);$variation->attrs=['attribute_size'=>'L','attribute_color'=>'Hitam'];
$vline=array_replace($line,['product_id'=>902,'variation_id'=>1001,'variation'=>$variation->attrs,'data'=>$variation]);$GLOBALS['meta'][42]['_kld_product']=902;
$cart->items=[$vline];check(kld_validate_cart_designs($cart)===[],'valid actual custom variation');
foreach([['variation_id'=>1002],['variation'=>['attribute_size'=>'S','attribute_color'=>'Hitam']],['variation'=>['attribute_size'=>'L']],['data'=>new TestProduct(1001,'variation',999)]] as $bad){$cart->items=[array_replace($vline,$bad)];check((bool)kld_validate_cart_designs($cart),'variation identity/attributes/parent tampering rejected');}
$GLOBALS['meta'][42]['_kld_product']=901;$cart->items=[$line];
$price=$GLOBALS['hooks']['woocommerce_before_calculate_totals'][0];$price($cart);check($cart->items[0]['data']->get_price()===68000,'real server price overrides product price');
$GLOBALS['meta'][42]['_kld_owner_email']='repeat@example.test';WC()->customer->email='repeat@example.test';$price($cart);check($cart->items[0]['data']->get_price()===64600,'verified repeat uses actual server base');
$checkout=$GLOBALS['hooks']['woocommerce_after_checkout_validation'][0];$errors=new WP_Error;$checkout(['billing_email'=>'different@example.test'],$errors);check(isset($errors->errors['kld_price_mismatch']),'checkout email/price mismatch rejected');
$errors=new WP_Error;$checkout(['billing_email'=>'repeat@example.test'],$errors);check(!$errors->errors,'verified price accepted');
$cart->items[0]['data']->set_price(1);$errors=new WP_Error;$checkout(['billing_email'=>'repeat@example.test'],$errors);check(isset($errors->errors['kld_price_mismatch']),'one-rupiah checkout tampering rejected');
$GLOBALS['meta'][42]['_kld_sides']['front']=['tier'=>'A5','image'=>['x'=>0,'y'=>0,'w'=>5,'h'=>5]];
check(is_wp_error(kld_design_ready(42,true)),'unconfirmed master blocks checkout');
$GLOBALS['meta'][42]['_kld_file_front']='fixture-master-42';$GLOBALS['meta'][42]['_kld_file_details_front']=['name'=>'master.png','mime'=>'image/png','size'=>68];
check(kld_design_ready(42,true)===true,'confirmed master accepted by fixture transport');
$GLOBALS['meta'][42]['_kld_pending_front']=['at'=>time()];check(is_wp_error(kld_design_ready(42,true)),'pending replacement blocks checkout');unset($GLOBALS['meta'][42]['_kld_pending_front']);
$GLOBALS['drive_failure']=true;check(is_wp_error(kld_design_ready(42,true)),'remote unavailable master blocks checkout');
$blocks=$GLOBALS['filters']['woocommerce_store_api_cart_errors'][0];$errors=new WP_Error;$blocks($errors,$cart);check(isset($errors->errors['kld_classic_required']),'Store API explicitly rejects KLD cart');
$cart->items=[['product_id'=>901,'quantity'=>1]];$errors=new WP_Error;$blocks($errors,$cart);check(!$errors->errors,'ordinary Store API cart unaffected');
echo "PASS: $count server validation/security assertions (synthetic doubles, not Woo runtime)\n";
