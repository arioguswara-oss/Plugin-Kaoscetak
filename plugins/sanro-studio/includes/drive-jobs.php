<?php
defined('ABSPATH') || exit;
require_once __DIR__.'/worker-lease.php';
require_once __DIR__.'/drive-watchdog.php';

/** Bounded asynchronous retry; each attempt recovers by exact Drive lookup, never blind POST retry. */
function sanro_studio_run_drive_production($order_id,$attempt=0) {
    $order_id=sanro_studio_drive_order_id($order_id);
    if(!sanro_studio_auto_drive_enabled()||$order_id===null)return ['valid'=>false,'error'=>'drive_auto_disabled'];
    if(!is_int($attempt)||$attempt<0||$attempt>5)return ['valid'=>false,'error'=>'drive_retry_limit'];
    $order=wc_get_order((int)$order_id);if(!sanro_studio_auto_drive_order_eligible($order))return ['valid'=>false,'error'=>'production_not_ready'];
    $lease=sanro_studio_lease_acquire(sanro_studio_drive_job_key($order_id));
    if(!$lease)return ['valid'=>false,'error'=>'drive_job_locked'];
    try{
        if(function_exists('sanro_studio_drive_package_enabled')&&sanro_studio_drive_package_enabled()){
            $run_id=hash('sha256',$lease['value']['owner']);$running=['version'=>1,'state'=>'running','run_id'=>$run_id,'attempt'=>$attempt,'code'=>'in_progress','next_at'=>$lease['value']['expires']+30];
            $order->update_meta_data('_sanro_studio_drive_job',$running);if($order->save()===false)return ['valid'=>false,'error'=>'drive_job_save_failed'];
            if(!sanro_studio_job_schedule($lease['value']['expires']+30,'sanro_studio_drive_watchdog',[(int)$order_id,$run_id,$attempt,$lease['value']['expires']+30])){
                $running['state']='blocked';$running['code']='drive_scheduler_unavailable';$running['next_at']=null;$order->update_meta_data('_sanro_studio_drive_job',$running);$order->save();return ['valid'=>false,'error'=>'drive_scheduler_unavailable'];
            }
        }
        try{$result=sanro_studio_perform_drive_production((int)$order_id,$lease);}catch(Throwable $error){$result=['valid'=>false,'error'=>'drive_worker_exception'];}
        // Rehydrate after a worker that uses separate Woo CRUD objects; do not seal cached items.
        $fresh=wc_get_order((int)$order_id);
        if(!is_object($fresh)||!sanro_studio_auto_drive_order_eligible($fresh))$result=['valid'=>false,'error'=>'production_not_ready'];
        else $order=$fresh;
        $state=['version'=>1,'attempt'=>$attempt,'state'=>empty($result['valid'])?'blocked':'folders_ready','code'=>$result['error']??'ok','next_at'=>null];
        if(!empty($result['valid'])&&function_exists('sanro_studio_drive_package_enabled')&&sanro_studio_drive_package_enabled()){
            $context=sanro_studio_saved_order_context($order,true);$all=[];
            if(empty($context['valid'])||empty($context['studio']))$result=['valid'=>false,'error'=>'production_snapshot_changed'];
            if(!empty($result['valid']))foreach($context['item_ids'] as $id){$receipt=$order->get_items('line_item')[$id]->get_meta('_sanro_studio_production_receipt',true);
                if(!is_array($receipt)||empty($receipt['complete'])||($receipt['fingerprint']??null)!==$context['fingerprint']||($receipt['policy_hash']??null)!==sanro_studio_production_policy_hash()){$result=['valid'=>false,'error'=>'production_order_incomplete'];break;}$all[$id]=$receipt;
            }
            if(!empty($result['valid'])){
                $state['state']='package_ready';$state['fingerprint']=$context['fingerprint'];$state['policy_hash']=sanro_studio_production_policy_hash();$state['items']=$all;$state['print_approved']=false;
            }else{$state['state']='blocked';$state['code']=$result['error'];}
        }
        $retryable=['drive_master_search_failed','drive_search_failed','drive_master_upload_failed','drive_create_failed','drive_master_save_failed','drive_layout_save_failed','folder_save_failed','drive_package_save_failed','drive_lease_lost','drive_worker_exception','drive_uploaded_master_unverified'];
        if(empty($result['valid'])&&$attempt<5&&in_array($state['code'],$retryable,true)){
            $at=time()+min(900,30*(2**$attempt));$args=[(int)$order_id,$attempt+1];$queued=false;
            $queued=sanro_studio_job_schedule($at,'sanro_studio_drive_provision_order',$args);
            if($queued){$state['state']='retry_queued';$state['next_at']=$at;}
        }
        if(!sanro_studio_lease_refresh($lease))return ['valid'=>false,'error'=>'drive_lease_lost'];
        if(method_exists($order,'update_meta_data')&&method_exists($order,'save')){$order->update_meta_data('_sanro_studio_drive_job',$state);if($order->save()===false)return empty($result['valid'])?$result+['job_persistence_failed'=>true]:['valid'=>false,'error'=>'drive_job_save_failed'];}
        return $result+['job_state'=>$state['state'],'retry_at'=>$state['next_at'],'attempt'=>$attempt];
    }finally{sanro_studio_lease_release($lease);}
}
