<?php
/** Focused ST-01/ST-R01 regressions. Synthetic data only; no mail/WP/WC writes. */
define('ABSPATH', __DIR__ . '/');
function wp_salt($scheme='auth'){return 'release-blocker-synthetic-salt';}
function is_email($value){return filter_var($value,FILTER_VALIDATE_EMAIL)!==false;}
function maybe_serialize($value){return serialize($value);}
function wp_cache_delete(...$args){}
function get_transient($key){return $GLOBALS['counter']??false;}
function set_transient($key,$value,$ttl){$GLOBALS['written'][]=$value;return true;}
function get_option($key,$default=false){return false;}
class GuardDB {public $options='synthetic_options';function prepare($sql,...$args){return $sql;}function query($sql){return 1;}}
$wpdb=new GuardDB();$_SERVER['REMOTE_ADDR']='192.0.2.10';
require dirname(__DIR__).'/includes/repeat-discount.php';
function sanro_studio_pricing_wc_enabled(){return false;}
function sanro_studio_pricing_is_line($line){return is_array($line)&&array_key_exists('sanro_studio_pricing_version',$line);}
function sanro_studio_pricing_wc_cart(){return $GLOBALS['cart']??null;}
function wc_add_notice($message,$type){$GLOBALS['notices'][]=[$message,$type];}
function add_action($hook,$callback,$priority=10,$args=1){$GLOBALS['hooks'][$hook]=$callback;}
function add_filter($hook,$callback,$priority=10,$args=1){$GLOBALS['filters'][$hook]=$callback;}
require dirname(__DIR__).'/includes/checkout-policy.php';
function guard_check($ok,$message){if(!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);}echo "PASS: $message\n";}
$GLOBALS['written']=[];$GLOBALS['counter']=1;
guard_check(sanro_studio_repeat_mail_budget('buyer@example.test')===true,'integer rate counter accepted');
$GLOBALS['counter']='1';$GLOBALS['written']=[];
guard_check(sanro_studio_repeat_mail_budget('buyer@example.test')===true&&$GLOBALS['written']===[2,2],'canonical decimal cache counter normalized to integer');
$GLOBALS['counter']='08';guard_check(sanro_studio_repeat_mail_budget('buyer@example.test')===false,'noncanonical rate counter rejected');
$GLOBALS['counter']=8;guard_check(sanro_studio_repeat_mail_budget('buyer@example.test')===false,'rate limit remains eight attempts');
sanro_studio_register_checkout_policy_hooks();
guard_check(isset($GLOBALS['hooks']['woocommerce_check_cart_items'],$GLOBALS['hooks']['woocommerce_checkout_process']),'fail-closed cart and checkout guard registered while adapter is disabled');
$GLOBALS['notices']=[];$GLOBALS['cart']=new class {function get_cart(){return [['product_id'=>10,'sanro_studio_design_id'=>'123e4567-e89b-42d3-a456-426614174000','quantity'=>1]];}function get_applied_coupons(){return [];}function get_fees(){return [];}function get_total_tax(){return 0;}function get_discount_total(){return 0;}};
sanro_studio_checkout_policy_check();guard_check(count($GLOBALS['notices'])===1&&str_contains($GLOBALS['notices'][0][0],'konfigurasi harga desain tidak aktif'),'Studio design line cannot proceed with catalog fallback price');
$api_errors=new class {public $errors=[];function add($code,$message){$this->errors[$code]=$message;}};
sanro_studio_store_api_checkout_policy_check($api_errors,$GLOBALS['cart']);
guard_check(isset($api_errors->errors['sanro_studio_pricing_unavailable']),'Store API cart/checkout is fail-closed for Studio lines');
$GLOBALS['notices']=[];$GLOBALS['cart']=new class {function get_cart(){return [['product_id'=>10,'quantity'=>1]];}};
sanro_studio_checkout_policy_check();guard_check($GLOBALS['notices']===[],'ordinary WooCommerce cart is unaffected');
echo "PASS: 7 focused release blocker guard assertions\n";
