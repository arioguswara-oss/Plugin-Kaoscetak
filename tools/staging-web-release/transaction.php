<?php
/** Staging transport for the existing locked, retained-backup install contract. No shell/eval. */
function sswr_tree($root) {
    if (is_link($root) || !is_dir($root) || realpath($root) !== $root) throw new RuntimeException('unsafe_tree');
    $files=[]; $bytes=0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isLink() || !$file->isFile()) throw new RuntimeException('unsupported_tree_entry');
        $bytes += $file->getSize();
        if (count($files)>255 || $bytes>25000000) throw new RuntimeException('tree_limit');
        $files[substr($file->getPathname(),strlen($root)+1)] = hash_file('sha256',$file->getPathname());
    }
    ksort($files,SORT_STRING); $pairs=[]; foreach($files as $path=>$hash) $pairs[]=[$path,$hash];
    return hash('sha256',json_encode($pairs,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
}
function sswr_sync_dir($path) {
    $stream=@fopen($path,'r');
    if (!$stream || !function_exists('fsync') || !@fsync($stream)) throw new RuntimeException('directory_fsync_unavailable');
    fclose($stream);
}
function sswr_write($path,$bytes,$mode=0600) {
    if (is_link($path) || (file_exists($path)&&!is_file($path))) throw new RuntimeException('unsafe_write_target');
    $temp=$path.'.'.bin2hex(random_bytes(8)).'.tmp'; $f=fopen($temp,'x');
    if (!$f || !chmod($temp,$mode)) throw new RuntimeException('private_write_failed');
    try {
        if (fwrite($f,$bytes)!==strlen($bytes) || !fflush($f) || !fsync($f)) throw new RuntimeException('durable_write_failed');
    } finally { fclose($f); }
    if (!rename($temp,$path)) throw new RuntimeException('atomic_write_failed');
    sswr_sync_dir(dirname($path));
}
function sswr_journal($root,$record=null) {
    $path=$root.'/.sanro-studio-transaction.json';
    if ($record!==null) { sswr_write($path,json_encode($record,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)); return $record; }
    if (!file_exists($path)&&!is_link($path)) return null;
    if (is_link($path)||!is_file($path)||filesize($path)>8192||(fileperms($path)&0077)) throw new RuntimeException('unsafe_journal');
    $r=json_decode(file_get_contents($path),true,16,JSON_THROW_ON_ERROR);
    if (($r['schema']??null)!==1 || !in_array($r['phase']??'', ['prepared','old_moved','new_moved','committed','rolled_back'],true)) throw new RuntimeException('invalid_journal');
    foreach(['transaction'=>32,'before_digest'=>64,'candidate_digest'=>64,'source_commit'=>40,'archive_sha256'=>64,'plugin_digest'=>64] as $key=>$length)
        if (!is_string($r[$key]??null)||!preg_match('/^[a-f0-9]{'.$length.'}$/D',$r[$key])) throw new RuntimeException('invalid_journal_binding');
    if (($r['backup']??null)!=='sanro-studio-previous-'.$r['transaction'] || ($r['quarantine']??null)!=='sanro-studio-interrupted-'.$r['transaction']) throw new RuntimeException('invalid_journal_paths');
    return $r;
}
function sswr_recover($target,$root,$r) {
    if (($r['target']??null)!==$target) throw new RuntimeException('journal_target_mismatch');
    $current=is_dir($target)?sswr_tree($target):null; $backup=$root.'/'.$r['backup'];
    if ($r['phase']==='committed') {
        if ($current!==$r['candidate_digest'] || sswr_tree($backup)!==$r['before_digest']) throw new RuntimeException('committed_tree_changed');
        return 'committed';
    }
    if ($current===$r['before_digest']) {
        if (file_exists($backup)) throw new RuntimeException('ambiguous_backup');
    } else {
        if (sswr_tree($backup)!==$r['before_digest']) throw new RuntimeException('previous_tree_unavailable');
        if ($current!==null) {
            $quarantine=$root.'/'.$r['quarantine'];
            if ($current!==$r['candidate_digest']||file_exists($quarantine)||!rename($target,$quarantine)) throw new RuntimeException('unknown_interrupted_tree');
            sswr_sync_dir(dirname($target)); sswr_sync_dir($root);
        }
        if (!rename($backup,$target)) throw new RuntimeException('restore_failed');
        sswr_sync_dir(dirname($target)); sswr_sync_dir($root);
        if (sswr_tree($target)!==$r['before_digest']) throw new RuntimeException('restore_digest_mismatch');
    }
    $r['phase']='rolled_back'; sswr_journal($root,$r); return 'rolled_back';
}
function sswr_replace($target,$root,$incoming,$manifest,$before,$checkpoint=null) {
    if (sswr_tree($target)!==$before) throw new RuntimeException('existing_tree_changed');
    $old=sswr_journal($root);
    if ($old) {
        sswr_recover($target,$root,$old);
        sswr_write($root.'/transaction-'.$old['transaction'].'.json',json_encode($old,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    }
    $id=bin2hex(random_bytes(16));
    $r=['schema'=>1,'transaction'=>$id,'phase'=>'prepared','target'=>$target,'before_digest'=>$before,'candidate_digest'=>sswr_tree($incoming),'backup'=>'sanro-studio-previous-'.$id,'quarantine'=>'sanro-studio-interrupted-'.$id];
    foreach(['source_commit','archive_sha256','plugin_digest'] as $key) $r[$key]=$manifest[$key];
    sswr_journal($root,$r);
    if ($checkpoint) $checkpoint('prepared');
    try {
        if (!rename($target,$root.'/'.$r['backup'])) throw new RuntimeException('old_rename_failed');
        sswr_sync_dir(dirname($target)); sswr_sync_dir($root);
        if ($checkpoint) $checkpoint('old_renamed');
        $r['phase']='old_moved'; sswr_journal($root,$r);
        if (!rename($incoming,$target)) throw new RuntimeException('new_rename_failed');
        sswr_sync_dir(dirname($target)); sswr_sync_dir(dirname($incoming));
        if ($checkpoint) $checkpoint('new_renamed');
        $r['phase']='new_moved'; sswr_journal($root,$r);
        if (sswr_tree($target)!==$r['candidate_digest']) throw new RuntimeException('installed_digest_mismatch');
        $r['phase']='committed'; sswr_journal($root,$r);
        if (function_exists('opcache_invalidate')) foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target,FilesystemIterator::SKIP_DOTS)) as $f) if($f->isFile()&&substr($f->getFilename(),-4)==='.php') opcache_invalidate($f->getPathname(),true);
        return $r;
    } catch (Throwable $e) { sswr_recover($target,$root,$r); throw $e; }
}
