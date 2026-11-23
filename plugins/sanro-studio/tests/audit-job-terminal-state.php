<?php
require __DIR__.'/feature-checkout-flow.php';
function home_url($path='/'){return 'https://example.test'.$path;}
function wc_get_order($id){return $GLOBALS['fresh_visible']??$GLOBALS['audit_order'];}
function sanro_studio_auto_drive_enabled(){return true;}
function sanro_studio_drive_package_enabled(){return true;}
function sanro_studio_auto_drive_order_eligible($order){return true;}
function sanro_studio_production_policy_hash(){return str_repeat('a',64);}
function as_schedule_single_action($at,$hook,$args,$group,$unique){$GLOBALS['scheduled'][]=[$hook,$args];return count($GLOBALS['scheduled']);}
function sanro_studio_perform_drive_production($id,&$lease=null){
 if($GLOBALS['failure']==='rehydrate'){$GLOBALS['fresh_visible']=clone $GLOBALS['audit_order'];$GLOBALS['fresh_visible']->meta['_sanro_studio_checkout_quote_hash']=str_repeat('f',64);}
 if($GLOBALS['failure']==='snapshot')$GLOBALS['audit_order']->meta['_sanro_studio_checkout_quote_hash']=str_repeat('f',64);
 return ['valid'=>true];
}
require __DIR__.'/../includes/drive-jobs.php';
$checks=0;$GLOBALS['audit_order']=$order;$GLOBALS['scheduled']=[];$GLOBALS['failure']='snapshot';
$result=sanro_studio_run_drive_production(88);flow_check(empty($result['valid'])&&$result['job_state']==='blocked'&&$order->meta['_sanro_studio_drive_job']['code']==='production_snapshot_changed','post-upload integrity failure persisted blocked, not misleading running');
$hash=sanro_studio_frozen_quote_hash($GLOBALS['options'][$key]);$order->meta['_sanro_studio_checkout_quote_hash']=$hash;$GLOBALS['failure']='receipt';
$result=sanro_studio_run_drive_production(88);flow_check(empty($result['valid'])&&$result['job_state']==='blocked'&&$order->meta['_sanro_studio_drive_job']['code']==='production_order_incomplete','missing completion receipts persist permanent blocked state');
flow_check(count(array_filter($GLOBALS['scheduled'],fn($j)=>$j[0]==='sanro_studio_drive_provision_order'))===0,'permanent integrity failure does not queue transient upload retry');
$held=sanro_studio_lease_acquire(sanro_studio_drive_job_key(88));flow_check($held!==false&&(sanro_studio_run_drive_production('088')['error']??null)==='drive_job_locked','leading-zero order IDs share canonical root lease');sanro_studio_lease_release($held);
flow_check(sanro_studio_drive_job_key('88')===sanro_studio_drive_job_key(88),'integer and canonical string identities use one key');
flow_check(sanro_studio_drive_order_id('88.1')===null&&sanro_studio_drive_order_id('8.8e1')===null&&sanro_studio_drive_order_id('99999999999999999999')===null,'fractional scientific and overflowing IDs refused instead of truncating');
$GLOBALS['failure']='rehydrate';unset($GLOBALS['fresh_visible']);$result=sanro_studio_run_drive_production(88);flow_check(($result['error']??null)==='production_snapshot_changed'&&$GLOBALS['fresh_visible']->meta['_sanro_studio_drive_job']['state']==='blocked','worker completion reloads fresh Woo object before sealing receipts');
echo "PASS: $checks production terminal-state regressions (no HTTP calls)\n";
