<?php
/** Targeted fault injection for the new PHP transport; no WordPress, network or database. */
require __DIR__.'/../tools/staging-web-release/transaction.php';
function check($yes,$name){if(!$yes)throw new RuntimeException($name);}
$base=sys_get_temp_dir().'/sswr-test-'.bin2hex(random_bytes(8));mkdir($base,0700);
$manifest=['source_commit'=>str_repeat('a',40),'archive_sha256'=>str_repeat('b',64),'plugin_digest'=>str_repeat('c',64)];
$checks=0;
foreach(['normal','prepared','old_renamed','new_renamed'] as $fault) {
    $dir=$base.'/'.$fault;mkdir($dir,0700);$target=$dir.'/sanro-studio';$root=$dir.'/private';$incoming=$dir.'/incoming';
    foreach([$target,$root,$incoming] as $p)mkdir($p,0700);
    file_put_contents($target.'/sanro-studio.php','old');file_put_contents($incoming.'/sanro-studio.php','new');$before=sswr_tree($target);
    try { $r=sswr_replace($target,$root,$incoming,$manifest,$before,static function($event)use($fault){if($event===$fault)throw new RuntimeException('injected');}); }
    catch(RuntimeException $e){check($e->getMessage()==='injected','unexpected failure');$r=sswr_journal($root);sswr_recover($target,$root,$r);}
    if($fault==='normal'){check(file_get_contents($target.'/sanro-studio.php')==='new','new tree installed');check(file_get_contents($root.'/'.$r['backup'].'/sanro-studio.php')==='old','old retained');check(sswr_recover($target,$root,$r)==='committed','committed idempotent');}
    else {check(sswr_tree($target)===$before,'old restored after '.$fault);check(sswr_recover($target,$root,sswr_journal($root))==='rolled_back','recovery idempotent');}
    $checks++;
}
// True process death after either rename, before journal phase update.
foreach(['old_renamed','new_renamed'] as $event) {
    $dir=$base.'/crash-'.$event;mkdir($dir,0700);foreach(['sanro-studio','private','incoming'] as $p)mkdir($dir.'/'.$p,0700);
    file_put_contents($dir.'/sanro-studio/sanro-studio.php','old');file_put_contents($dir.'/incoming/sanro-studio.php','new');$before=sswr_tree($dir.'/sanro-studio');
    $script=$dir.'/crash.php';
    file_put_contents($script,'<?php require '.var_export(realpath(__DIR__.'/../tools/staging-web-release/transaction.php'),true).';sswr_replace('.var_export($dir.'/sanro-studio',true).','.var_export($dir.'/private',true).','.var_export($dir.'/incoming',true).','.var_export($manifest,true).','.var_export($before,true).',static function($event){if($event==='.var_export($event,true).')exit(86);});');
    exec(escapeshellarg(PHP_BINARY).' -n '.escapeshellarg($script),$out,$exit);check($exit===86,'crash injected');
    $r=sswr_journal($dir.'/private');check(sswr_recover($dir.'/sanro-studio',$dir.'/private',$r)==='rolled_back','crash recovered');
    check(sswr_tree($dir.'/sanro-studio')===$before,'crash preserves original');check(sswr_recover($dir.'/sanro-studio',$dir.'/private',sswr_journal($dir.'/private'))==='rolled_back','crash recovery idempotent');$checks++;
}
// External proof is required and the prior rollback must remain intact.
$dir=$base.'/external';mkdir($dir,0700);
$target=$dir.'/sanro-studio';$root=$dir.'/private';$incoming=$dir.'/incoming';
foreach ([$target,$root,$incoming] as $path) mkdir($path,0700);
file_put_contents($target.'/sanro-studio.php','original');
file_put_contents($incoming.'/sanro-studio.php','first web candidate');
$old=sswr_replace($target,$root,$incoming,$manifest,sswr_tree($target));
$oldBackup=$root.'/'.$old['backup'].'/sanro-studio.php';
file_put_contents($target.'/sanro-studio.php','externally committed candidate');
$before=sswr_tree($target);
mkdir($incoming,0700);
file_put_contents($incoming.'/sanro-studio.php','next web candidate');
foreach ([false,true] as $externalProof) {
    if ($externalProof) file_put_contents($oldBackup,'corrupt');
    $error=null;
    try { sswr_replace($target,$root,$incoming,$manifest,$before,null,$externalProof); }
    catch (RuntimeException $e) { $error=$e->getMessage(); }
    check($error===($externalProof?'superseded_journal_not_verified':'committed_tree_changed'),'stale journal fails closed');
    check(sswr_tree($target)===$before && sswr_journal($root)===$old,'failure preserves live tree and journal');
    $checks++;
}
file_put_contents($oldBackup,'original');
$next=sswr_replace($target,$root,$incoming,$manifest,$before,null,true);
check($next['phase']==='committed','externally committed predecessor superseded');
check(sswr_tree($root.'/'.$next['backup'])===$before,'latest predecessor retained for rollback');
check(json_decode(file_get_contents($root.'/transaction-'.$old['transaction'].'.json'),true)===$old,'prior web journal archived');
check(sswr_tree($root.'/'.$old['backup'])===$old['before_digest'],'original rollback retained');
$checks++;
$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
foreach($iterator as $p){if($p->isDir())rmdir($p->getPathname());else unlink($p->getPathname());}rmdir($base);
echo "PASS: $checks install/recovery scenarios, including process death at both rename boundaries\n";
