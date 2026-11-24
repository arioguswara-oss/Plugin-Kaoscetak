<?php
/** Explicit staging configuration opt-in, never PHP constant redefinition. Default and every GET stay OFF. */
function sswr_dtf_config_flags(){return ['DTF_ADMIN_GENERATE','DTF_ORDER_BATCH','DTF_IMAGE_RASTER','DTF_LAYER_RASTER','DTF_PRIVATE_ARTIFACTS'];}
function sswr_false_flag_offsets($source) {
    $tokens=[];$offset=0;
    foreach(token_get_all($source,TOKEN_PARSE) as $t){$text=is_array($t)?$t[1]:$t;$kind=is_array($t)?$t[0]:null;if(!in_array($kind,[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true))$tokens[]=[$kind,$text,$offset];$offset+=strlen($text);}
    $known=[];foreach($tokens as $t)if($t[0]===T_CONSTANT_ENCAPSED_STRING){$name=substr($t[1],1,-1);foreach(sswr_dtf_config_flags() as $flag)if($name===$flag||$name==='SANRO_STUDIO_ENABLE_'.$flag)$known[$flag]=true;}
    $matches=[];
    for($i=0;$i+5<count($tokens);$i++){
        if($tokens[$i][0]!==T_STRING||strtolower($tokens[$i][1])!=='define'||$tokens[$i+1][1]!=='(')continue;
        $j=$i+2;$argument='';$parts=[];
        while($j<count($tokens)&&$j<$i+16&&$tokens[$j][1]!==','){
            if(!in_array($tokens[$j][0],[T_VARIABLE,T_CONSTANT_ENCAPSED_STRING],true)&&$tokens[$j][1]!=='.')break;
            $parts[]=$tokens[$j];$argument.=$tokens[$j][1];$j++;
        }
        if(!$parts||($tokens[$j][1]??null)!==','||($tokens[$j+1][0]??null)!==T_STRING||strtolower($tokens[$j+1][1])!=='false'||($tokens[$j+2][1]??null)!==')')continue;
        $flags=[];
        if(count($parts)===1&&$parts[0][0]===T_CONSTANT_ENCAPSED_STRING){$name=substr($parts[0][1],1,-1);foreach(sswr_dtf_config_flags() as $flag)if($name==='SANRO_STUDIO_ENABLE_'.$flag)$flags[]=$flag;}
        elseif(array_filter($parts,static function($t){return $t[0]===T_VARIABLE;}))$flags=array_keys($known);
        if($flags)$matches[]=['flags'=>$flags,'name_expression'=>$argument,'offset'=>$tokens[$j+1][2],'bytes'=>strlen($tokens[$j+1][1])];
    }return $matches;
}
function sswr_config_lease($record=null) {
    $path=sswr_private_root().'/dtf-config-lease.json';
    if($record!==null){sswr_write($path,wp_json_encode($record,JSON_UNESCAPED_SLASHES));return $record;}
    if(!file_exists($path))return null;
    if(is_link($path)||!is_file($path)||(fileperms($path)&0077)||filesize($path)>32768)throw new RuntimeException('unsafe_config_lease');
    $r=json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR);
    if(($r['schema']??null)!==1||($r['order_id']??null)!==56||($r['design_id']??null)!==SSWR_DESIGN||($r['source']??null)!==SSWR_SOURCE||!in_array($r['state']??null,['prepared','armed','restored'],true)||!is_int($r['expires']??null)||!is_array($r['files']??null)||count($r['files'])>10)throw new RuntimeException('invalid_config_lease');
    foreach($r['files'] as $f){
        if(!is_string($f['path']??null)||is_link($f['path'])||realpath($f['path'])!==$f['path']||strpos($f['path'],realpath(WPMU_PLUGIN_DIR).'/')!==0||!preg_match('/^config-backup-[a-f0-9]{64}\.php$/D',$f['backup']??''))throw new RuntimeException('invalid_config_path');
        foreach(['before','after'] as $key)if(!preg_match('/^[a-f0-9]{64}$/D',$f[$key]??''))throw new RuntimeException('invalid_config_hash');
    }return $r;
}
function sswr_config_lease_live() {
    $r=sswr_config_lease();
    if(!$r||$r['state']!=='armed'||$r['expires']<time()||($_SERVER['REQUEST_METHOD']??'')!=='POST'||($_POST['acceptance']??'')!=='dtf'||($_GET['page']??'')!=='sanro-staging-release')return false;
    foreach($r['files'] as $f)if(!hash_equals($f['after'],hash_file('sha256',$f['path'])))throw new RuntimeException('armed_configuration_changed');
    return true;
}
function sswr_arm_dtf_config() {
    sswr_preflight();$accept=sswr_acceptance_record();if(($accept['order_id']??null)!==56)throw new RuntimeException('exact_synthetic_order_required');
    $order=wc_get_order(56);sswr_acceptance_order_summary($order);if(!sanro_studio_auto_drive_order_eligible($order))throw new RuntimeException('production_approval_required');
    $old=sswr_config_lease();if($old&&$old['state']!=='restored')throw new RuntimeException('restore_previous_config_lease_first');
    $expires=time()+300;$files=[];$seen=[];
    // Included SANRO MU files only. Never read credentials, wp-config or files from other domains.
    foreach(get_included_files() as $path){$actual=realpath($path);if(!$actual||is_link($path)||strpos($actual,realpath(WPMU_PLUGIN_DIR).'/')!==0||stripos(basename($actual),'sanro')===false)continue;
        if(!is_file($actual)||filesize($actual)>131072||!is_writable($actual))throw new RuntimeException('managed_config_unavailable');
        $before=file_get_contents($actual);$matches=sswr_false_flag_offsets($before);if(!$matches)continue;
        $after=$before;usort($matches,static function($a,$b){return $b['offset']<=>$a['offset'];});
        $expression="(time() < ".$expires." && (\$_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (\$_SERVER['SCRIPT_NAME'] ?? '') === '/wp-admin/tools.php' && (\$_GET['page'] ?? '') === 'sanro-staging-release' && (\$_POST['acceptance'] ?? '') === 'dtf')";
        foreach($matches as $match){foreach($match['flags'] as $flag)$seen[$flag]=true;
            $allow=var_export(array_map(static function($flag){return 'SANRO_STUDIO_ENABLE_'.$flag;},sswr_dtf_config_flags()),true);
            $bounded='(in_array(('.$match['name_expression'].'),'.$allow.',true) && '.$expression.')';
            $after=substr_replace($after,$bounded,$match['offset'],$match['bytes']);
        }
        token_get_all($after,TOKEN_PARSE);$hash=hash('sha256',$before);$backup='config-backup-'.$hash.'.php';$backupPath=sswr_private_root().'/'.$backup;
        if(is_file($backupPath)){if(!hash_equals($hash,hash_file('sha256',$backupPath)))throw new RuntimeException('config_backup_changed');}else sswr_write($backupPath,$before);
        $files[]=['path'=>$actual,'backup'=>$backup,'before'=>$hash,'after'=>hash('sha256',$after),'mode'=>fileperms($actual)&0777,'new_bytes'=>$after];
    }
    foreach(sswr_dtf_config_flags() as $flag)if(defined('SANRO_STUDIO_ENABLE_'.$flag)&&!isset($seen[$flag]))throw new RuntimeException('managed_literal_config_not_found_'.$flag);
    if(!$files)throw new RuntimeException('no_managed_literal_configuration');
    $r=['schema'=>1,'order_id'=>56,'design_id'=>SSWR_DESIGN,'source'=>SSWR_SOURCE,'expires'=>$expires,'state'=>'prepared','flags'=>array_keys($seen),'files'=>array_map(static function($f){unset($f['new_bytes']);return $f;},$files)];sswr_config_lease($r);
    try{foreach($files as $f){if(!hash_equals($f['before'],hash_file('sha256',$f['path'])))throw new RuntimeException('configuration_changed_before_opt_in');sswr_write($f['path'],$f['new_bytes'],$f['mode']);if(function_exists('opcache_invalidate'))opcache_invalidate($f['path'],true);}$r['state']='armed';sswr_config_lease($r);}
    catch(Throwable $e){sswr_restore_dtf_config();throw $e;}
    return ['state'=>'armed','expires'=>$expires,'default_and_get_flags'=>'OFF','order_id'=>56,'flags'=>array_keys($seen),'files'=>count($files)];
}
function sswr_restore_dtf_config() {
    sswr_identity();$r=sswr_config_lease();if(!$r)return ['state'=>'not_armed'];
    foreach($r['files'] as $f){$current=hash_file('sha256',$f['path']);if(hash_equals($f['before'],$current))continue;
        $backup=sswr_private_root().'/'.$f['backup'];
        if(!hash_equals($f['after'],$current)||is_link($backup)||!is_file($backup)||!hash_equals($f['before'],hash_file('sha256',$backup)))throw new RuntimeException('configuration_conflict_original_retained');
        sswr_write($f['path'],file_get_contents($backup),$f['mode']);if(function_exists('opcache_invalidate'))opcache_invalidate($f['path'],true);
    }$r['state']='restored';sswr_config_lease($r);return ['state'=>'restored','original_hashes_verified'=>true];
}
