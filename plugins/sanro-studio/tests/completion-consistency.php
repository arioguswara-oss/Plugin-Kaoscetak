<?php
/** Integration of canonical order/payment checks, leased retries and sealed asset receipts. */
require __DIR__.'/production-package-flow.php';
function as_schedule_single_action($at,$hook,$args,$group,$unique){if(!empty($GLOBALS['fail_scheduler']))return 0;$GLOBALS['scheduled_jobs'][]=compact('at','hook','args','group','unique');return count($GLOBALS['scheduled_jobs']);}
function retry_jobs(){return array_values(array_filter($GLOBALS['scheduled_jobs'],fn($job)=>$job['hook']==='sanro_studio_drive_provision_order'));}
$checks=0;$production->status='processing';$production->paid=true;$production->meta=$order->meta;
flow_check(sanro_studio_saved_order_context($production,true)['valid'],'one saved order contract passes');
$size=$production->items[71]->meta['_sanro_studio_design_id'];$production->items[71]->meta['_sanro_studio_design_id']='';
flow_check(!sanro_studio_saved_order_context($production,true)['valid'],'orphaned Studio line cannot become ordinary checkout');$production->items[71]->meta['_sanro_studio_design_id']=$size;
$writes=$GLOBALS['network_calls'];sanro_studio_pricing_before_payment_complete(88);sanro_studio_pricing_before_payment_complete(88);flow_check($writes===$GLOBALS['network_calls'],'duplicate payment guard reads saved context without network or mutation');
$production->items[71]->total=1;try{sanro_studio_pricing_before_payment_complete(88);throw new LogicException('modified payment accepted');}catch(RuntimeException $e){$checks++;}
flow_check(!sanro_studio_production_item_manifest($production,72)['valid'],'another size tampering blocks the entire production order');$production->items[71]->total=186000;
$quote=$production->meta['_sanro_studio_checkout_quote_hash'];$production->meta['_sanro_studio_checkout_quote_hash']=str_repeat('f',64);flow_check(!sanro_studio_saved_order_context($production,true)['valid'],'pinned order quote mismatch denied');$production->meta['_sanro_studio_checkout_quote_hash']=$quote;
$lease=sanro_studio_lease_acquire('synthetic_lease');flow_check($lease!==false&&sanro_studio_lease_acquire('synthetic_lease')===false,'active lease never stolen');
$GLOBALS['options']['synthetic_lease']['expires']=time()-1;$second=sanro_studio_lease_acquire('synthetic_lease');
flow_check($second!==false&&!sanro_studio_lease_release($lease)&&!sanro_studio_lease_refresh($lease),'expired owner cannot release/renew a replacement');flow_check(sanro_studio_lease_refresh($second)&&sanro_studio_lease_release($second),'replacement owner renews/releases with exact CAS');
$GLOBALS['options']['legacy_lock']='old unversioned lock';flow_check(!sanro_studio_lease_acquire('legacy_lock'),'unversioned legacy lock requires review, never stolen');
$GLOBALS['remote']=[];$GLOBALS['uploads']=0;$GLOBALS['scheduled_jobs']=[];$GLOBALS['lost_upload_response']=true;
foreach($production->items as $item){foreach(array_keys($item->meta) as $m)if(str_starts_with($m,'_sanro_studio_drive_package_')||$m==='_sanro_studio_production_receipt')unset($item->meta[$m]);$item->meta['_sanro_studio_drive_folder_id']='private-folder-88-'.$item->id;}
$failed=sanro_studio_run_drive_production(88);flow_check(empty($failed['valid'])&&$failed['job_state']==='retry_queued'&&count($GLOBALS['remote'])===1,'lost upload response schedules bounded retry and preserves remote bytes');
flow_check(retry_jobs()[0]['args']===[88,1]&&retry_jobs()[0]['unique']&&retry_jobs()[0]['at']>=time()+29,'asynchronous backoff has exact order/attempt and uniqueness');
$retry=sanro_studio_run_drive_production(88,1);flow_check(!empty($retry['valid'])&&$retry['job_state']==='package_ready'&&$GLOBALS['uploads']===8&&count($GLOBALS['remote'])===8,'retry recovers uploaded asset and completes exactly eight files without duplicate');
$job=$production->meta['_sanro_studio_drive_job'];flow_check(count($job['items'])===2&&$job['print_approved']===false,'whole order ready requires both item receipts and no print approval');
foreach($job['items'] as $item_id=>$receipt){flow_check($receipt['fingerprint']===$job['fingerprint']&&count($receipt['assets'])===4,'item receipt uses same order snapshot with four file hashes');
 foreach($receipt['assets'] as $asset){$filemeta=$GLOBALS['remote'][$asset['file_id']];flow_check($filemeta['appProperties']['sanroSha256']===$asset['sha256']&&$filemeta['appProperties']['sanroItemId']===(string)$item_id,'each receipt SHA/file belongs to its item');}}
$manifest=sanro_studio_prepare_production_package($production,71)['manifest'];flow_check(count($manifest['assets'])===3&&count(array_filter($manifest['assets'],fn($a)=>$a['kind']==='guide'))===1,'JSON manifest indexes both previews and PDF, self hash recorded externally');
$before=$GLOBALS['uploads'];sanro_studio_run_drive_production(88,1);flow_check($before===$GLOBALS['uploads'],'completed job replay performs no new uploads');
# Simulate a new-process reload after a remote upload succeeds but item CRUD fails.
$asset=sanro_studio_prepare_production_package($production,71)['assets'][0];$asset['bytes'].='synthetic-new-version';$asset['sha256']=hash('sha256',$asset['bytes']);$production->items[71]->fail_saves=1;
$failed=sanro_studio_drive_upload_item_source($production,71,'private-folder-88-71','front',$asset,'preview');flow_check(($failed['error']??null)==='drive_master_save_failed','upload save failure is explicit');
$meta='_sanro_studio_drive_package_preview_front_id_'.$asset['sha256'];unset($production->items[71]->meta[$meta]);$before=$GLOBALS['uploads'];$recovered=sanro_studio_drive_upload_item_source($production,71,'private-folder-88-71','front',$asset,'preview');flow_check(!empty($recovered['valid'])&&!$recovered['created']&&$GLOBALS['uploads']===$before,'remote upload survives failed local save and is recovered without duplication');
flow_check(sanro_studio_run_drive_production(88,6)['error']==='drive_retry_limit','retry attempt is bounded');
$recorded=sanro_studio_order_package_receipt($production);flow_check($recorded['valid']&&$recorded['artifact_count']===8,'admin receipt validates all eight artifacts across sizes');
$oldReceipt=$production->items[71]->meta['_sanro_studio_production_receipt'];$production->items[71]->meta['_sanro_studio_production_receipt']['fingerprint']=str_repeat('f',64);flow_check(sanro_studio_order_package_receipt($production)['state']==='stale','partial receipt update never appears whole-order ready');$production->items[71]->meta['_sanro_studio_production_receipt']=$oldReceipt;
require_once __DIR__.'/../includes/production-memory.php';$oldLimit=ini_get('memory_limit');ini_set('memory_limit','64M');flow_check(!sanro_studio_memory_bytes_available(70000000),'large multipart buffers fail before allocation');ini_set('memory_limit',$oldLimit);
$before=count(retry_jobs());$production->items[71]->total=1;$blocked=sanro_studio_run_drive_production(88,2);flow_check(empty($blocked['valid'])&&$blocked['job_state']==='blocked'&&count(retry_jobs())===$before,'permanent integrity failure is not endlessly retried');$production->items[71]->total=186000;
foreach($production->items as $item)foreach(array_keys($item->meta) as $m)if(str_starts_with($m,'_sanro_studio_drive_package_'))unset($item->meta[$m]);$GLOBALS['remote']=[];$GLOBALS['lost_upload_response']=true;
$last=sanro_studio_run_drive_production(88,5);flow_check(empty($last['valid'])&&$last['job_state']==='blocked'&&$last['retry_at']===null&&count(retry_jobs())===$before,'final transient attempt stops without scheduling another job');
echo "PASS: $checks order/payment/Drive recovery and artifact consistency checks\n";
