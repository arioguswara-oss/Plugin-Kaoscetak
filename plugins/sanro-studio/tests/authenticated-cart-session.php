<?php
require __DIR__.'/feature-checkout-flow.php';
function get_current_user_id(){return $GLOBALS['user_id']??0;}
class AuthSession extends FlowSession {public $identity;function __construct($id){$this->identity=$id;}function get_customer_id(){return $this->identity;}}
$checks=0;$guest=WC()->session;$old=sanro_studio_pricing_session_hash();
flow_check($old===hash_hmac('sha256',$guest->get_customer_id(),wp_salt('auth')),'existing guest session hashes are unchanged');
WC()->session=new AuthSession('23');$GLOBALS['user_id']=0;flow_check(sanro_studio_pricing_session_hash()===null,'short numeric guest session is not authorization');
$GLOBALS['user_id']=24;flow_check(sanro_studio_pricing_session_hash()===null,'session ID cannot claim a different authenticated account');
$GLOBALS['user_id']=23;$auth=sanro_studio_pricing_session_hash();flow_check(is_string($auth)&&$auth!==$old,'short native Woo account ID accepted only for authenticated same user');WC()->session=new AuthSession(23);flow_check(sanro_studio_pricing_session_hash()===$auth,'integer/string Woo user IDs yield same server binding');
$record=$GLOBALS['options'][$key];$record['status']='draft';foreach(['checkout_quote','order_id','order_item_ids'] as $field)unset($record[$field]);$GLOBALS['options'][$key]=$record;$draft=sanro_studio_read_draft($id,$token);
flow_check(!sanro_studio_pricing_claim_draft_session($draft,$auth),'migration requires independent owner proof');
flow_check(!sanro_studio_pricing_claim_draft_session($draft,$auth,str_repeat('b',64)),'foreign owner proof cannot migrate another draft');
flow_check(sanro_studio_pricing_claim_draft_session($draft,$auth,$token)&&$GLOBALS['options'][$key]['wc_session_hash']===$auth,'owner-authorized guest-to-login migration uses CAS');
WC()->cart=new FlowCart();$result=sanro_studio_rest_pricing_draft_cart($request);flow_check(!empty($result['valid'])&&count(WC()->cart->get_cart())===2,'authenticated buyer can add multi-size design through native REST/cart');
$GLOBALS['options'][$key]['status']='locked';flow_check(!sanro_studio_pricing_claim_draft_session($draft,$old,$token),'owner token cannot migrate locked order');
echo "PASS: $checks authenticated/guest cart-session checks\n";
