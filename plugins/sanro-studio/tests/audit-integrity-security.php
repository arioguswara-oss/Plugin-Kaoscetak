<?php
/** Audit regressions: tamper trusted labels without changing copied ID/hash, and quota contention. */
require __DIR__.'/repeat-eligibility-flow.php';
$checks=0;$snapshot=$repeat->items[71]->meta['_sanro_studio_design_snapshot'];
$mutants=[];$m=$snapshot;$m['shirt_color']='#b91c1c';$mutants[]=$m;$m=$snapshot;$m['long_sleeve']=!$m['long_sleeve'];$mutants[]=$m;
$m=$snapshot;$m['sides']['front']['objects'][0]['x']+=1;$mutants[]=$m;$m=$snapshot;$m['sides']['front']['text']='tampered audit text';$mutants[]=$m;
$m=$snapshot;$m['sleeve_label']='tampered sleeve';$mutants[]=$m;$m=$snapshot;$m['unexpected']='tampered extra';$mutants[]=$m;
foreach($mutants as $mutant){$repeat->items[71]->meta['_sanro_studio_design_snapshot']=$mutant;$checked=sanro_studio_saved_order_context($repeat,true);flow_check(empty($checked['valid'])&&$checked['error']==='order_design_snapshot_mismatch','whole canonical payload compared, copied ID/hash is not authority');}
ob_start();sanro_studio_admin_item_preview_status(71,$repeat->items[71],null);$html=ob_get_clean();flow_check(!str_contains($html,'<img ')&&!str_contains($html,'tampered')&&str_contains($html,'integritas'),'tampered metadata cannot appear as production-ready admin preview');
$repeat->items[71]->meta['_sanro_studio_design_snapshot']=$snapshot;flow_check(sanro_studio_saved_order_context($repeat,true)['valid'],'original snapshot remains valid');
$GLOBALS['transients']=[];$_SERVER['REMOTE_ADDR']='192.0.2.41';$ipkey='sanro_repeat_mail_'.substr(hash_hmac('sha256',$_SERVER['REMOTE_ADDR'],wp_salt('auth')),0,32);
$held=sanro_studio_lease_acquire($ipkey.'_lock',15);flow_check($held!==false&&!sanro_studio_repeat_mail_budget('recipient@example.test')&&$GLOBALS['transients']===[],'contending IP quota cannot pass read/set or increment counts');sanro_studio_lease_release($held);
$receiver='sanro_repeat_recipient_'.substr(hash_hmac('sha256','recipient@example.test',wp_salt('auth')),0,32);$held=sanro_studio_lease_acquire($receiver.'_lock',15);
flow_check(!sanro_studio_repeat_mail_budget('recipient@example.test')&&get_option($ipkey.'_lock',false)===false,'recipient contention fails closed and releases first lease');sanro_studio_lease_release($held);
for($n=1;$n<=8;$n++){$_SERVER['REMOTE_ADDR']='192.0.2.'.$n;flow_check(sanro_studio_repeat_mail_budget('recipient@example.test'),'bounded recipient quota accepts first eight requests');}
$_SERVER['REMOTE_ADDR']='192.0.2.99';flow_check(!sanro_studio_repeat_mail_budget('recipient@example.test'),'new IP cannot bypass recipient mail flood quota');
flow_check(!sanro_studio_repeat_mail_budget('invalid')&&!sanro_studio_repeat_mail_budget([]),'malformed recipient cannot reserve quota');
echo "PASS: $checks audit snapshot/admin/quota security regressions\n";
