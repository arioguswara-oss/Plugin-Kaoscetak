<?php
defined('ABSPATH') || exit;

function sanro_studio_job_schedule($at,$hook,$args) {
    if(function_exists('as_schedule_single_action')){
        $id=as_schedule_single_action($at,$hook,$args,'sanro-studio',true);
        return (bool)$id||(function_exists('as_has_scheduled_action')&&as_has_scheduled_action($hook,$args,'sanro-studio'));
    }
    return function_exists('wp_schedule_single_event')&&function_exists('wp_next_scheduled')
        &&(wp_next_scheduled($hook,$args)||wp_schedule_single_event($at,$hook,$args));
}
function sanro_studio_drive_order_id($raw) {
    if(is_int($raw))return $raw>0?$raw:null;
    if(!is_string($raw)||strlen($raw)>20||!preg_match('/^[0-9]+$/D',$raw))return null;
    $normal=ltrim($raw,'0');$id=(int)$normal;
    return $id>0&&(string)$id===$normal?$id:null;
}
function sanro_studio_drive_job_key($order_id){$id=sanro_studio_drive_order_id($order_id);return $id===null?null:'sanro_drive_job_'.md5(home_url('/').':'.$id);}

/** Read persisted running state; never steals an active lease or changes payment state. */
function sanro_studio_drive_job_watchdog($order_id,$run_id,$attempt,$scheduled_at=0) {
    if(!sanro_studio_auto_drive_enabled()||!sanro_studio_drive_package_enabled())return ['valid'=>false,'error'=>'drive_auto_disabled'];
    if(!is_int($order_id)||$order_id<1||!is_string($run_id)||!preg_match('/^[a-f0-9]{64}$/D',$run_id)||!is_int($attempt)||$attempt<0||$attempt>5)return ['valid'=>false,'error'=>'watchdog_invalid'];
    $order=wc_get_order($order_id);if(!sanro_studio_auto_drive_order_eligible($order))return ['valid'=>false,'error'=>'production_not_ready'];
    $state=$order->get_meta('_sanro_studio_drive_job',true);
    if(!is_array($state)||($state['state']??null)!=='running'||($state['run_id']??null)!==$run_id)return ['valid'=>true,'state'=>'superseded'];
    $key=sanro_studio_drive_job_key($order_id);sanro_studio_lease_cache($key);$lease=get_option($key,false);
    if(is_array($lease)&&is_string($lease['owner']??null)){
        if(hash('sha256',$lease['owner'])!==$run_id)return ['valid'=>true,'state'=>'new_run_present'];
        if(($lease['expires']??0)>time())return ['valid'=>sanro_studio_job_schedule($lease['expires']+30,'sanro_studio_drive_watchdog',[$order_id,$run_id,$attempt,$lease['expires']+30]),'state'=>'active'];
    }
    if($attempt>=5){$held=sanro_studio_lease_acquire($key);if(!$held)return ['valid'=>false,'error'=>'drive_job_locked'];
        try{$order=wc_get_order($order_id);$current=$order->get_meta('_sanro_studio_drive_job',true);if(!is_array($current)||($current['state']??null)!=='running'||($current['run_id']??null)!==$run_id)return ['valid'=>true,'state'=>'superseded'];$state=$current;$state['state']='blocked';$state['code']='drive_interrupted_retry_limit';$state['next_at']=null;$order->update_meta_data('_sanro_studio_drive_job',$state);return ['valid'=>$order->save()!==false,'state'=>'blocked'];}
        finally{sanro_studio_lease_release($held);}
    }
    return ['valid'=>sanro_studio_job_schedule(time()+5,'sanro_studio_drive_provision_order',[$order_id,$attempt+1]),'state'=>'recovery_queued'];
}

/** Explicit admin retry only after policy/source repair; no generator/Drive gate bypass. */
function sanro_studio_request_production_retry($order) {
    if(!current_user_can('manage_woocommerce')||!sanro_studio_drive_package_enabled())return ['valid'=>false,'error'=>'retry_not_allowed'];
    $context=sanro_studio_saved_order_context($order,true);
    if(empty($context['valid'])||empty($context['studio'])||!sanro_studio_auto_drive_order_eligible($order))return ['valid'=>false,'error'=>'production_not_ready'];
    $key=sanro_studio_drive_job_key($order->get_id());sanro_studio_lease_cache($key);$lease=get_option($key,false);
    if(is_array($lease)&&($lease['expires']??0)>time())return ['valid'=>false,'error'=>'drive_job_running'];
    return ['valid'=>sanro_studio_job_schedule(time()+1,'sanro_studio_drive_provision_order',[$order->get_id(),0])];
}
