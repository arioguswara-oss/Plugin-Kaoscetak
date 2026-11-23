<?php
defined('ABSPATH') || exit;
require_once __DIR__.'/worker-lease.php';

/** Bounded asynchronous retry; each attempt recovers by exact Drive lookup, never blind POST retry. */
function sanro_studio_run_drive_production($order_id,$attempt=0) {
    if(!sanro_studio_auto_drive_enabled()||!is_numeric($order_id)||(int)$order_id<1)return ['valid'=>false,'error'=>'drive_auto_disabled'];
    if(!is_int($attempt)||$attempt<0||$attempt>5)return ['valid'=>false,'error'=>'drive_retry_limit'];
    $order=wc_get_order((int)$order_id);if(!sanro_studio_auto_drive_order_eligible($order))return ['valid'=>false,'error'=>'production_not_ready'];
    $lease=sanro_studio_lease_acquire('sanro_drive_job_'.md5(home_url('/').':'.$order_id));
    if(!$lease)return ['valid'=>false,'error'=>'drive_job_locked'];
    try{
        try{$result=sanro_studio_perform_drive_production((int)$order_id);}catch(Throwable $error){$result=['valid'=>false,'error'=>'drive_worker_exception'];}
        $state=['version'=>1,'attempt'=>$attempt,'state'=>empty($result['valid'])?'blocked':'folders_ready','code'=>$result['error']??'ok','next_at'=>null];
        if(!empty($result['valid'])&&function_exists('sanro_studio_drive_package_enabled')&&sanro_studio_drive_package_enabled()){
            $context=sanro_studio_saved_order_context($order,true);$all=[];
            if(empty($context['valid'])||empty($context['studio']))return ['valid'=>false,'error'=>'production_snapshot_changed'];
            foreach($context['item_ids'] as $id){$receipt=$order->get_items('line_item')[$id]->get_meta('_sanro_studio_production_receipt',true);
                if(!is_array($receipt)||empty($receipt['complete'])||($receipt['fingerprint']??null)!==$context['fingerprint']||($receipt['policy_hash']??null)!==sanro_studio_production_policy_hash())return ['valid'=>false,'error'=>'production_order_incomplete'];$all[$id]=$receipt;
            }
            $state['state']='package_ready';$state['fingerprint']=$context['fingerprint'];$state['policy_hash']=sanro_studio_production_policy_hash();$state['items']=$all;$state['print_approved']=false;
        }
        $retryable=['drive_master_search_failed','drive_search_failed','drive_master_upload_failed','drive_create_failed','drive_master_save_failed','drive_layout_save_failed','folder_save_failed','drive_package_save_failed','drive_lease_lost','drive_worker_exception','drive_uploaded_master_unverified'];
        if(empty($result['valid'])&&$attempt<5&&in_array($state['code'],$retryable,true)){
            $at=time()+min(900,30*(2**$attempt));$args=[(int)$order_id,$attempt+1];$queued=false;
            if(function_exists('as_schedule_single_action')){$queued=(bool)as_schedule_single_action($at,'sanro_studio_drive_provision_order',$args,'sanro-studio',true);if(!$queued&&function_exists('as_has_scheduled_action'))$queued=as_has_scheduled_action('sanro_studio_drive_provision_order',$args,'sanro-studio');}
            elseif(function_exists('wp_schedule_single_event')&&function_exists('wp_next_scheduled'))$queued=wp_next_scheduled('sanro_studio_drive_provision_order',$args)||wp_schedule_single_event($at,'sanro_studio_drive_provision_order',$args);
            if($queued){$state['state']='retry_queued';$state['next_at']=$at;}
        }
        if(!sanro_studio_lease_refresh($lease))return ['valid'=>false,'error'=>'drive_lease_lost'];
        if(method_exists($order,'update_meta_data')&&method_exists($order,'save')){$order->update_meta_data('_sanro_studio_drive_job',$state);if($order->save()===false)return empty($result['valid'])?$result+['job_persistence_failed'=>true]:['valid'=>false,'error'=>'drive_job_save_failed'];}
        return $result+['job_state'=>$state['state'],'retry_at'=>$state['next_at'],'attempt'=>$attempt];
    }finally{sanro_studio_lease_release($lease);}
}
