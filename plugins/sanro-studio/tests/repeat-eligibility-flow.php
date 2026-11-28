<?php
/** Real email-proof/CAS/pricing/cart/order/package code, only WP/Woo/mail/HTTP mocked. */
require __DIR__.'/feature-checkout-flow.php';
require __DIR__.'/../includes/repeat-discount.php';
function is_email($email){return filter_var($email,FILTER_VALIDATE_EMAIL)!==false;}
function wp_unslash($v){return $v;}
function get_transient($key){return $GLOBALS['transients'][$key]??false;}
function set_transient($key,$value,$ttl){$GLOBALS['transients'][$key]=$value;return true;}
function wp_mail($to,$subject,$body){$GLOBALS['mail_count']=($GLOBALS['mail_count']??0)+1;preg_match('/\b[a-f0-9]{64}\b/',$body,$m);$GLOBALS['verification_code']=$m[0]??null;return !empty($GLOBALS['mail_ok']);}
function wc_get_is_paid_statuses(){return ['processing','completed'];}
function wc_get_orders($args){$GLOBALS['queries'][]=$args;if(!empty($GLOBALS['history_error']))throw new RuntimeException('synthetic lookup failure');return isset($GLOBALS['history_pages'])?($GLOBALS['history_pages'][$args['page']]??[]):($GLOBALS['history_results']??[]);}
function wc_get_order($id){return $id===42?($GLOBALS['history']??null):($GLOBALS['repeat_order']??null);}
class RepeatHistory {public $email='repeat@example.test',$status='processing',$paid=true,$refund=0;function get_id(){return 42;}function get_billing_email(){return $this->email;}function get_status(){return $this->status;}function is_paid(){return $this->paid;}function get_total_refunded(){return $this->refund;}}
class RepeatCustomer {public $email='repeat@example.test';function get_billing_email(){return $this->email;}}
class RepeatItem extends FlowItem {function get_order_id(){return 88;}}
class RepeatOrder extends FlowOrder {public $email='repeat@example.test',$paid=false;function get_billing_email(){return $this->email;}function is_paid(){return $this->paid;}function get_status(){return $this->paid?'processing':'pending';}function get_order_number(){return 'REPEAT-88';}}
$checks=0;$_SERVER['REMOTE_ADDR']='192.0.2.10';$GLOBALS['transients']=[];$GLOBALS['mail_ok']=true;
$record=$GLOBALS['options'][$key];$record['status']='draft';foreach(['checkout_quote','order_id','order_item_ids','shipping_total'] as $field)unset($record[$field]);$record['design']['quantities']=['S'=>1,'L'=>2];$record['preview_design_hash']=hash('sha256',serialize($record['design']));$GLOBALS['options'][$key]=$record;
$sent=sanro_studio_repeat_request_verification($id,$token,'REPEAT@example.test');$code=$GLOBALS['verification_code'];
flow_check(!empty($sent['valid'])&&!isset($sent['code'])&&!str_contains(serialize($GLOBALS['options'][$key]),$code),'mail-only hashed challenge, no API/storage plaintext code');
flow_check(!sanro_studio_repeat_complete_verification($id,str_repeat('b',64),$code)['valid'],'email code alone cannot replace draft ownership');
flow_check(!sanro_studio_repeat_complete_verification($id,$token,str_repeat('a',64))['valid'],'wrong verification code rejected');
$GLOBALS['options'][$key]['pending_owner_verification']['expires']=time()-1;flow_check(!sanro_studio_repeat_complete_verification($id,$token,$code)['valid'],'expired challenge refused');$GLOBALS['options'][$key]['pending_owner_verification']['expires']=time()+60;
flow_check(sanro_studio_repeat_complete_verification($id,$token,$code)['valid'],'owner+emailed challenge confirms email');
flow_check(!sanro_studio_repeat_complete_verification($id,$token,$code)['valid'],'challenge single-use replay refused');
flow_check(sanro_studio_repeat_verified_email($GLOBALS['options'][$key])==='repeat@example.test','verified identity stored outside artwork');
flow_check(!sanro_studio_repeat_request_verification($id,$token,'intruder@example.test')['valid'],'edit token cannot change verified email');
$copy=$GLOBALS['options'][$key];$copy['token_hash']=hash('sha256',str_repeat('c',64));flow_check(sanro_studio_repeat_verified_email($copy)==='','copied proof cannot authorize another owner token');
$GLOBALS['history']=new RepeatHistory();$GLOBALS['history_results']=[$GLOBALS['history']];WC()->customer=new RepeatCustomer();unset($_POST['billing_email']);
$raw=sanro_studio_quote_size_quantities(7,$record['design'],$record['design']['quantities'],false);
$discounted=sanro_studio_repeat_adjust_quote($raw,$id);flow_check($discounted['total_price']===188100&&$discounted['items']['S']['unit_price']===58900&&$discounted['items']['L']['unit_price']===64600,'5% per piece including single size S1 and L2');
$single=$raw;$single['items']=['S'=>['quantity'=>1,'unit_price'=>65001,'subtotal'=>65001]];$single['total_quantity']=1;$single['total_price']=65001;
flow_check(sanro_studio_repeat_adjust_quote($single,$id)['items']['S']['unit_price']===61751,'legacy-source half-up rupiah rounding reproduced exactly');
$public=sanro_studio_repeat_public_quote($discounted);flow_check(!isset($public['repeat_discount']['email_hash'],$public['repeat_discount']['history_order_id']),'browser totals omit private history fingerprint');
WC()->customer->email='other@example.test';flow_check(sanro_studio_repeat_adjust_quote($raw,$id)['total_price']===$raw['total_price'],'different checkout email gets full price');WC()->customer->email='repeat@example.test';
foreach([['paid',false],['status','failed'],['status','refunded'],['refund',100],['email','other@example.test']] as [$field,$bad]){$old=$GLOBALS['history']->$field;$GLOBALS['history']->$field=$bad;flow_check(!isset(sanro_studio_repeat_adjust_quote($raw,$id)['repeat_discount']),'actual invalid/refunded/foreign history cannot qualify');$GLOBALS['history']->$field=$old;}
$GLOBALS['history_results']=[42];flow_check(!isset(sanro_studio_repeat_adjust_quote($raw,$id)['repeat_discount']),'unverified order IDs do not establish paid history');$GLOBALS['history_results']=[$GLOBALS['history']];
$refunded=clone $GLOBALS['history'];$refunded->refund=1;$GLOBALS['history_pages']=[1=>array_fill(0,20,$refunded),2=>[$GLOBALS['history']]];
flow_check(isset(sanro_studio_repeat_adjust_quote($raw,$id)['repeat_discount']),'older valid history remains eligible beyond first page of refunded orders');unset($GLOBALS['history_pages']);
$GLOBALS['history_error']=true;flow_check(!sanro_studio_repeat_adjust_quote($raw,$id)['valid'],'history failure blocks quote instead of silently dropping earned discount');$GLOBALS['history_error']=false;
WC()->cart=new FlowCart();$added=sanro_studio_rest_pricing_draft_cart($request);flow_check(!empty($added['valid']),'verified owner draft added through native adapter');
$total=0;foreach(WC()->cart->get_cart() as $line)$total+=$line['quantity']*$line['data']->get_price();flow_check($total===188100,'native cart uses discounted server unit prices');
sanro_studio_repeat_billing_context('billing_email=other%40example.test');WC()->cart->calculate_totals();$fresh=0;foreach(WC()->cart->get_cart() as $line)$fresh+=$line['quantity']*$line['data']->get_price();flow_check($fresh===198000,'native checkout email change restores official base before order creation');
sanro_studio_repeat_billing_context('billing_email=repeat%40example.test');WC()->cart->calculate_totals();$fresh=0;foreach(WC()->cart->get_cart() as $line)$fresh+=$line['quantity']*$line['data']->get_price();flow_check($fresh===188100,'repeated recalculation reapplies 5% once, never compounds');
$_POST=['sanro_studio_checkout_nonce'=>'fixture-nonce','sanro_studio_checkout_token'=>$token,'billing_email'=>'repeat@example.test'];
sanro_studio_pricing_checkout_authorize();$repeat=new RepeatOrder();$GLOBALS['repeat_order']=$repeat;
foreach(WC()->cart->get_cart() as $cartkey=>$line){$item=new RepeatItem(count($repeat->items)+71,(int)$line['quantity'],(int)$line['data']->get_price());sanro_studio_pricing_order_item($item,$cartkey,$line,$repeat);$repeat->items[$item->id]=$item;}
$repeat->email='other@example.test';try{sanro_studio_pricing_validate_order($repeat,[]);throw new LogicException('changed billing accepted');}catch(RuntimeException $e){$checks++;}$repeat->email='repeat@example.test';
sanro_studio_pricing_validate_order($repeat,[]);sanro_studio_pricing_lock_order($repeat);
flow_check($repeat->get_total()===188100&&$GLOBALS['options'][$key]['checkout_quote']['repeat_discount']['discount_total']===9900,'discounted total and exact evidence pinned in locked record');
flow_check(sanro_studio_saved_order_context($repeat,true)['valid'],'saved payment/production contract accepts verified discount');
$proof=$repeat->items[71]->meta['_sanro_studio_repeat_discount'];$repeat->items[71]->meta['_sanro_studio_repeat_discount']['unit_discount']=1;flow_check(!sanro_studio_saved_order_context($repeat,true)['valid'],'tampered per-item discount evidence refused');$repeat->items[71]->meta['_sanro_studio_repeat_discount']=$proof;
$GLOBALS['history']->refund=100;flow_check(!sanro_studio_saved_order_context($repeat,true)['valid'],'prior history refunded before payment blocks pending discounted order');$GLOBALS['history']->refund=0;
$repeat->paid=true;$GLOBALS['history']->refund=100;flow_check(sanro_studio_saved_order_context($repeat,true)['valid'],'already-paid order retains frozen discount without retrospective repricing');
require __DIR__.'/../includes/order-master-source.php';require __DIR__.'/../includes/drive-auto-provision.php';require __DIR__.'/../includes/production-package.php';
$manifest=sanro_studio_production_item_manifest($repeat,71);flow_check(!empty($manifest['valid'])&&$manifest['manifest']['repeat_discount']['base_unit_price']===62000&&$manifest['manifest']['unit_price']===58900,'production manifest reconciles base/discount/net on same locked order');
flow_check(!str_contains(json_encode($manifest['manifest']),'repeat@example.test')&&!isset($manifest['manifest']['repeat_discount']['email_hash']),'Drive/guide manifest omits billing proof and email');
$package=sanro_studio_prepare_production_package($repeat,71);flow_check(!empty($package['valid'])&&str_starts_with($package['assets'][2]['bytes']??'','%PDF-'),'real Unicode production PDF built with repeat net metadata while writers OFF');
flow_check(!sanro_studio_auto_drive_enabled()&&!sanro_studio_drive_package_enabled(),'production functions do not activate Drive runtime');
$queries=$GLOBALS['queries'];flow_check($queries[0]['return']==='objects'&&$queries[0]['billing_email']==='repeat@example.test'&&$queries[0]['status']===['processing','completed'],'paid-history lookup uses native HPOS-compatible query');
// Critical negative paths on an independent unlocked draft; never mutate the locked production record.
$copy_id='123e4567-e89b-42d3-a456-426614174099';$copy_key='sanro_studio_draft_'.$copy_id;$unverified=$record;unset($unverified['verified_owner'],$unverified['pending_owner_verification']);$unverified['design']['verified_owner']=['email'=>'repeat@example.test'];$GLOBALS['options'][$copy_key]=$unverified;
flow_check(!isset(sanro_studio_repeat_adjust_quote($raw,$copy_id,'repeat@example.test')['repeat_discount']),'buyer artwork cannot assert email verification');
$before_mail=$GLOBALS['mail_count'];flow_check(!sanro_studio_repeat_request_verification($copy_id,str_repeat('b',64),'repeat@example.test')['valid']&&$GLOBALS['mail_count']===$before_mail,'foreign owner cannot trigger verification email');
$real_db=$GLOBALS['wpdb'];$GLOBALS['wpdb']=new class extends FlowDB {function query($args){return count($args)===3&&isset($args[1])&&str_starts_with($args[1],'sanro_studio_draft_')?0:parent::query($args);}};$GLOBALS['transients']=[];
flow_check(!sanro_studio_repeat_request_verification($copy_id,$token,'repeat@example.test')['valid']&&$GLOBALS['mail_count']===$before_mail,'CAS conflict sends no email and does not lose concurrent record');$GLOBALS['wpdb']=$real_db;
$GLOBALS['mail_ok']=false;$GLOBALS['transients']=[];$mailfail=sanro_studio_repeat_request_verification($copy_id,$token,'repeat@example.test');flow_check(!$mailfail['valid']&&sanro_studio_repeat_verified_email($GLOBALS['options'][$copy_key])==='','SMTP failure cannot grant verified ownership');$GLOBALS['mail_ok']=true;
$before_mail=$GLOBALS['mail_count'];foreach($GLOBALS['transients'] as $budget=>$count)$GLOBALS['transients'][$budget]=8;
flow_check((sanro_studio_repeat_request_verification($copy_id,$token,'repeat@example.test')['error']??null)==='verification_rate_limit'&&$GLOBALS['mail_count']===$before_mail,'mail budget blocks flood without sending');
function current_user_can($cap){return $cap==='manage_woocommerce';}function esc_html($s){return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}function esc_attr($s){return esc_html($s);}function esc_url($s){return esc_html($s);}
function admin_url($path){return 'https://example.test/wp-admin/'.$path;}function add_query_arg($args,$url){return $url.'?'.http_build_query($args);}function wp_nonce_url($url,$action){return $url.'&_wpnonce=fixture';}
ob_start();sanro_studio_admin_item_preview_status(71,$repeat->items[71],null);$admin=ob_get_clean();flow_check(str_contains($admin,'Repeat 5% tersimpan')&&substr_count($admin,'<img ')===2&&!str_contains($admin,'repeat@example.test')&&!str_contains($admin,$token),'admin shows repeat base/net and both private previews without proof secrets');
$sealed=sanro_studio_package_finalize_manifest($package);$json_asset=end($sealed['assets']);$sealed_manifest=json_decode($json_asset['bytes'],true);
flow_check(count($sealed['assets'])===4&&$sealed_manifest['repeat_discount']===$package['manifest']['repeat_discount'],'sealed Drive manifest carries exact same repeat base/discount/net contract');
flow_check($json_asset['sha256']===hash('sha256',$json_asset['bytes'])&&$sealed_manifest['assets'][2]['sha256']===hash('sha256',$package['assets'][2]['bytes']),'manifest/PDF hashes reconcile without running uploader');
echo "PASS: $checks repeat proof/checkout/production checks (mail and WP/WC simulated)\n";
