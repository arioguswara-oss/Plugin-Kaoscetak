<?php
defined('ABSPATH') || exit;

/** Source rule: verified design-owner email == checkout email, prior paid order, 5% per piece. */
function sanro_studio_repeat_email($value) {
    if(!is_string($value)||strlen($value)>254)return '';
    $value=strtolower(trim($value));
    return function_exists('is_email')&&is_email($value)?$value:'';
}
function sanro_studio_repeat_email_hash($email) {
    $salt=function_exists('wp_salt')?wp_salt('auth'):null;
    return $email!==''&&is_string($salt)&&strlen($salt)>=16?hash_hmac('sha256',$email,$salt):null;
}
function sanro_studio_repeat_billing_context($raw) {
    if(is_string($raw)){parse_str($raw,$fields);$GLOBALS['sanro_studio_repeat_billing']=sanro_studio_repeat_email($fields['billing_email']??null);}
}
function sanro_studio_repeat_current_email() {
    if(isset($_POST['billing_email']))return sanro_studio_repeat_email(is_string($_POST['billing_email'])?wp_unslash($_POST['billing_email']):null);
    if(array_key_exists('sanro_studio_repeat_billing',$GLOBALS))return $GLOBALS['sanro_studio_repeat_billing'];
    $wc=function_exists('WC')?WC():null;
    return is_object($wc)&&isset($wc->customer)&&is_object($wc->customer)&&method_exists($wc->customer,'get_billing_email')
        ?sanro_studio_repeat_email($wc->customer->get_billing_email()):'';
}
/** Only the real order record can establish payment history; no ID/client status claims. */
function sanro_studio_repeat_paid_order_allowed($order,$email,$exclude=0) {
    if(!is_object($order)||!function_exists('wc_get_is_paid_statuses'))return false;
    foreach(['get_id','get_billing_email','is_paid','get_status','get_total_refunded'] as $method)if(!method_exists($order,$method))return false;
    $statuses=wc_get_is_paid_statuses();$refunded=$order->get_total_refunded();$id=(int)$order->get_id();
    return is_array($statuses)&&$id>0&&$id!==$exclude&&$order->is_paid()===true&&in_array($order->get_status(),$statuses,true)
        &&!in_array($order->get_status(),['pending','failed','cancelled','refunded','trash'],true)
        &&is_numeric($refunded)&&(float)$refunded===0.0&&sanro_studio_repeat_email($order->get_billing_email())===$email;
}
/** HPOS-compatible query, then validate actual objects, not a client order ID or query status alone. */
function sanro_studio_repeat_paid_history($email,$exclude=0) {
    if($email===''||!function_exists('wc_get_orders')||!function_exists('wc_get_is_paid_statuses'))return false;
    $statuses=wc_get_is_paid_statuses();if(!is_array($statuses)||!$statuses)return false;
    // Page through history; no business expiry or latest-N eligibility rule. Exhausted technical
    // budget is unavailable (false), not proof of ineligibility (null) or permission to overcharge.
    $deadline=microtime(true)+3.0;$limit=20;
    for($page=1;$page<=100&&microtime(true)<$deadline;$page++){
        try{$orders=wc_get_orders(['billing_email'=>$email,'status'=>$statuses,'exclude'=>$exclude?[$exclude]:[],'limit'=>$limit,'page'=>$page,'return'=>'objects','orderby'=>'date','order'=>'DESC']);}
        catch(Throwable $error){return false;}
        if(!is_array($orders))return false;
        foreach($orders as $order)if(sanro_studio_repeat_paid_order_allowed($order,$email,$exclude))return (int)$order->get_id();
        if(count($orders)<$limit)return null;
    }return false;
}

/** Proof is outside buyer-supplied artwork JSON and survives safe draft edits, never an unverified copy. */
function sanro_studio_repeat_verified_email($record) {
    $proof=is_array($record)?($record['verified_owner']??null):null;
    $email=is_array($proof)?sanro_studio_repeat_email($proof['email']??null):'';
    return $email!==''&&($proof['version']??null)===1&&is_int($proof['verified_at']??null)&&$proof['verified_at']<=time()
        &&is_string($proof['token_binding']??null)&&hash_equals((string)($record['token_hash']??''),$proof['token_binding'])?$email:'';
}
function sanro_studio_repeat_adjust_quote($quote,$id,$billing=null) {
    if(empty($quote['valid'])||!function_exists('sanro_studio_pricing_wc_enabled')||!sanro_studio_pricing_wc_enabled())return $quote;
    $record=get_option('sanro_studio_draft_'.$id,false);$owner=sanro_studio_repeat_verified_email($record);
    $billing=$billing===null?sanro_studio_repeat_current_email():sanro_studio_repeat_email($billing);
    if(!is_array($record)||($record['status']??null)!=='draft'||!is_int($record['expires_at']??null)||$record['expires_at']<=time()||$owner===''||$billing!==$owner)return $quote;
    $customer=sanro_studio_repeat_email_hash($owner);$history=sanro_studio_repeat_paid_history($owner);
    if($history===false)return ['valid'=>false,'error'=>'repeat_history_unavailable'];
    if(!$customer||!$history)return $quote;
    $total=$base_total=$discount=0;$items=$quote['items']??null;
    if(!is_array($items)||!$items)return ['valid'=>false,'error'=>'repeat_quote_invalid'];
    foreach($items as $size=>$line){
        $base=$line['unit_price']??null;$quantity=$line['quantity']??null;
        if(!is_int($base)||$base<1||$base>2000000||!is_int($quantity)||$quantity<1||$quantity>999||isset($line['base_unit_price'])||$base*$quantity!==($line['subtotal']??null))return ['valid'=>false,'error'=>'repeat_quote_invalid'];
        // Existing source uses PHP_ROUND_HALF_UP; exact positive integer arithmetic reproduces it.
        $unit_discount=intdiv($base*5+50,100);$unit=$base-$unit_discount;
        $items[$size]=['quantity'=>$quantity,'unit_price'=>$unit,'subtotal'=>$unit*$quantity,'base_unit_price'=>$base,'unit_discount'=>$unit_discount];
        $total+=$unit*$quantity;$base_total+=$base*$quantity;$discount+=$unit_discount*$quantity;
    }
    $quote['items']=$items;$quote['total_price']=$total;
    $quote['repeat_discount']=['version'=>1,'rate'=>5,'rounding'=>'half_up_per_piece','email_hash'=>$customer,'history_order_id'=>$history,'base_total'=>$base_total,'discount_total'=>$discount];
    return $quote;
}

/** CAS metadata changes cannot overwrite a concurrent artwork save, session bind or order lock. */
function sanro_studio_repeat_update($id,$token,$change) {
    $draft=sanro_studio_read_draft($id,$token);if(empty($draft['valid'])||($draft['status']??null)!=='draft')return ['valid'=>false,'error'=>'verification_unavailable'];
    global $wpdb;$key='sanro_studio_draft_'.$id;$before=get_option($key,false);
    if(!is_object($wpdb)||!is_string($wpdb->options??null)||!preg_match('/^[A-Za-z0-9_]+$/D',$wpdb->options)||!method_exists($wpdb,'prepare')||!method_exists($wpdb,'query')
        ||!is_array($before)||($before['status']??null)!=='draft'||!hash_equals((string)($before['token_hash']??''),hash('sha256',$token)))return ['valid'=>false,'error'=>'verification_unavailable'];
    $after=$change($before);if(!is_array($after)||empty($after['valid']))return is_array($after)?$after:['valid'=>false,'error'=>'verification_unavailable'];
    $record=$after['record'];$n=$wpdb->query($wpdb->prepare("UPDATE `{$wpdb->options}` SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s",maybe_serialize($record),$key,maybe_serialize($before)));
    wp_cache_delete($key,'options');return $n===1?['valid'=>true]:['valid'=>false,'error'=>'verification_conflict'];
}
function sanro_studio_repeat_mail_budget() {
    if(!function_exists('get_transient')||!function_exists('set_transient')||!function_exists('wp_salt'))return false;
    $ip=$_SERVER['REMOTE_ADDR']??null;if(!is_string($ip)||!filter_var($ip,FILTER_VALIDATE_IP))return false;
    $key='sanro_repeat_mail_'.substr(hash_hmac('sha256',$ip,wp_salt('auth')),0,32);$count=get_transient($key);
    if($count!==false&&(!is_int($count)||$count>=8))return false;
    return set_transient($key,$count===false?1:$count+1,3600);
}
function sanro_studio_repeat_request_verification($id,$owner_token,$email) {
    $email=sanro_studio_repeat_email($email);if($email===''||!function_exists('wp_mail'))return ['valid'=>false,'error'=>'verification_unavailable'];
    $draft=sanro_studio_read_draft($id,$owner_token);if(empty($draft['valid'])||($draft['status']??null)!=='draft')return ['valid'=>false,'error'=>'verification_unavailable'];
    if(!sanro_studio_repeat_mail_budget())return ['valid'=>false,'error'=>'verification_rate_limit'];
    $code=bin2hex(random_bytes(32));
    $result=sanro_studio_repeat_update($id,$owner_token,static function($record)use($email,$code){
        $verified=sanro_studio_repeat_verified_email($record);if($verified!==''&&$verified!==$email)return ['valid'=>false,'error'=>'verification_unavailable'];
        $record['pending_owner_verification']=['email'=>$email,'code_hash'=>hash('sha256',$code),'expires'=>time()+1800];return ['valid'=>true,'record'=>$record];
    });
    if(empty($result['valid']))return $result;
    // Only the email channel receives the code; no URL/query secret or REST token response.
    $sent=wp_mail($email,'Verifikasi email desain SANRO Studio',"Jangan bagikan kode ini. Masukkan kode di editor pada browser pemilik desain. Berlaku 30 menit.\n\n".$code);
    return $sent?['valid'=>true,'sent'=>true]:['valid'=>false,'error'=>'verification_mail_unavailable'];
}
function sanro_studio_repeat_complete_verification($id,$owner_token,$code) {
    if(!is_string($code)||!preg_match('/^[a-f0-9]{64}$/D',$code))return ['valid'=>false,'error'=>'verification_unavailable'];
    return sanro_studio_repeat_update($id,$owner_token,static function($record)use($code){
        $pending=$record['pending_owner_verification']??null;$email=is_array($pending)?sanro_studio_repeat_email($pending['email']??null):'';
        if($email===''||!is_int($pending['expires']??null)||$pending['expires']<=time()||!is_string($pending['code_hash']??null)||!hash_equals($pending['code_hash'],hash('sha256',$code)))return ['valid'=>false,'error'=>'verification_unavailable'];
        $old=sanro_studio_repeat_verified_email($record);if($old!==''&&$old!==$email)return ['valid'=>false,'error'=>'verification_unavailable'];
        $record['verified_owner']=['version'=>1,'email'=>$email,'verified_at'=>time(),'token_binding'=>$record['token_hash']];unset($record['pending_owner_verification']);return ['valid'=>true,'record'=>$record];
    });
}
function sanro_studio_repeat_register_routes() {
    if(!sanro_studio_draft_rest_enabled()||!sanro_studio_pricing_wc_enabled())return;
    foreach(['request','complete'] as $operation)register_rest_route('sanro-studio/v1','/drafts/(?P<id>[a-fA-F0-9-]{36})/owner-email/'.$operation,[
        'methods'=>'POST','permission_callback'=>'sanro_studio_rest_draft_nonce','callback'=>static function($request)use($operation){
            $id=$request->get_param('id');$token=$request->get_header('X-Sanro-Owner-Token');
            $result=$operation==='request'?sanro_studio_repeat_request_verification($id,$token,$request->get_param('email')):sanro_studio_repeat_complete_verification($id,$token,$request->get_param('code'));
            if(empty($result['valid']))return new WP_Error('sanro_owner_verification','Verifikasi belum dapat diselesaikan. Periksa kode, kepemilikan draft atau coba nanti.',['status'=>($result['error']??'')==='verification_rate_limit'?429:409]);
            $response=rest_ensure_response($result);$response->header('Cache-Control','private, no-store, max-age=0');return $response;
        }]);
}

/** Immutable saved evidence, actual per-piece math and billing identity; no catalog repricing. */
function sanro_studio_repeat_saved_order_allowed($order,$record) {
    $quote=$record['checkout_quote']??[];$proof=$quote['repeat_discount']??null;
    if($proof===null)return true;
    $owner=sanro_studio_repeat_verified_email($record);$email=method_exists($order,'get_billing_email')?sanro_studio_repeat_email($order->get_billing_email()):'';
    $hash=sanro_studio_repeat_email_hash($email);
    if(!is_array($proof)||$owner===''||$owner!==$email||!$hash||($proof['version']??null)!==1||($proof['rate']??null)!==5||($proof['rounding']??null)!=='half_up_per_piece'||($proof['email_hash']??null)!==$hash||!is_int($proof['history_order_id']??null)||$proof['history_order_id']<1||$proof['history_order_id']===$order->get_id())return false;
    $base=$discount=$net=0;
    foreach($quote['items']??[] as $line){$b=$line['base_unit_price']??null;$q=$line['quantity']??null;$d=$line['unit_discount']??null;
        if(!is_int($b)||$b<1||$b>2000000||!is_int($q)||$q<1||$q>999||!is_int($d)||$d!==intdiv($b*5+50,100)||($line['unit_price']??null)!==$b-$d||($line['subtotal']??null)!==($b-$d)*$q)return false;
        $base+=$b*$q;$discount+=$d*$q;$net+=($b-$d)*$q;
    }
    if(!$base||$base!==($proof['base_total']??null)||$discount!==($proof['discount_total']??null)||$net!==($quote['total_price']??null))return false;
    // A settled order retains its pinned commercial snapshot; do not retroactively reprice production.
    if(method_exists($order,'is_paid')&&$order->is_paid()===true)return true;
    try{$history=function_exists('wc_get_order')?wc_get_order($proof['history_order_id']):null;}catch(Throwable $error){return false;}
    return sanro_studio_repeat_paid_order_allowed($history,$email,(int)$order->get_id());
}

/** Owner-authenticated browser needs totals, not private email fingerprints/history identifiers. */
function sanro_studio_repeat_public_quote($quote) {
    if(isset($quote['repeat_discount']))$quote['repeat_discount']=array_intersect_key($quote['repeat_discount'],array_flip(['version','rate','rounding','base_total','discount_total']));
    return $quote;
}

function sanro_studio_repeat_item_evidence($quote,$size) {
    if(!isset($quote['repeat_discount']))return null;
    $line=$quote['items'][$size]??[];
    return ['proof'=>$quote['repeat_discount'],'base_unit_price'=>$line['base_unit_price']??null,'unit_discount'=>$line['unit_discount']??null];
}
