<?php
/** Targeted config safety: token-aware declaration matching and conflict-safe original restoration. */
require __DIR__.'/../tools/staging-web-release/transaction.php';
require __DIR__.'/../tools/staging-web-release/config-lease.php';
const SSWR_DESIGN='4233e0e2-7c89-408f-b36b-fb76ce5adbc2';const SSWR_SOURCE='79e0a93f77bd03be0760ca179383375426284b8f';
$base=sys_get_temp_dir().'/sswr-config-'.bin2hex(random_bytes(8));mkdir($base,0700);mkdir($base.'/mu',0700);mkdir($base.'/private',0700);define('WPMU_PLUGIN_DIR',$base.'/mu');
function sswr_identity(){}function sswr_private_root(){return $GLOBALS['base'].'/private';}function wp_json_encode($v,$flags=0){return json_encode($v,$flags);}
$code="<?php\n// define('SANRO_STUDIO_ENABLE_DTF_ADMIN_GENERATE', false);\n\$sample=\"define('SANRO_STUDIO_ENABLE_DTF_ADMIN_GENERATE', false)\";\ndefine('SANRO_STUDIO_ENABLE_DTF_ADMIN_GENERATE', false);\ndefine('SANRO_STUDIO_ENABLE_STORAGE_GC', false);\n";
$matches=sswr_false_flag_offsets($code);if(count($matches)!==1||substr($code,$matches[0]['offset'],$matches[0]['bytes'])!=='false')throw new RuntimeException('unsafe_parser_match');
$mapped='<?php $flags=["DTF_ADMIN_GENERATE"=>false,"STORAGE_GC"=>false,"DRIVE_MASTER_EXPORT"=>false];';$mm=sswr_false_flag_offsets($mapped);if(count($mm)!==1||$mm[0]['flags']!==['DTF_ADMIN_GENERATE']||substr($mapped,$mm[0]['offset'],$mm[0]['bytes'])!=='false')throw new RuntimeException('map_allowlist_failed');
$dynamic="<?php $"."flags=['DTF_ADMIN_GENERATE','STORAGE_GC'];foreach($"."flags as $"."key)define('SANRO_STUDIO_ENABLE_'.$"."key,false);";
$dm=sswr_false_flag_offsets($dynamic);if(count($dm)!==1||$dm[0]['flags']!==['DTF_ADMIN_GENERATE']||$dm[0]['name_expression']!=="'SANRO_STUDIO_ENABLE_'.".'$key')throw new RuntimeException('dynamic_declaration_invalid');
$bounded="(in_array((".$dm[0]['name_expression']."),['SANRO_STUDIO_ENABLE_DTF_ADMIN_GENERATE'],true) && (\$_SERVER['REQUEST_METHOD']??'')==='POST')";
$patched=substr_replace($dynamic,$bounded,$dm[0]['offset'],$dm[0]['bytes']);token_get_all($patched,TOKEN_PARSE);
foreach(['GET','POST'] as $method){$test=$base.'/dynamic-'.$method.'.php';file_put_contents($test,'<?php $_SERVER["REQUEST_METHOD"]='.var_export($method,true).';'.substr($patched,5).';echo json_encode([SANRO_STUDIO_ENABLE_DTF_ADMIN_GENERATE,SANRO_STUDIO_ENABLE_STORAGE_GC]);');exec(escapeshellarg(PHP_BINARY).' -n '.escapeshellarg($test),$output,$exit);$actual=json_decode(end($output),true);if($exit!==0||$actual!==[$method==='POST',false])throw new RuntimeException('dynamic_allowlist_or_default_failed');}
$path=$base.'/mu/sanro.php';$before=hash('sha256',$code);$changed=substr_replace($code,'true',$matches[0]['offset'],$matches[0]['bytes']);file_put_contents($path,$changed);$backup='config-backup-'.$before.'.php';sswr_write(sswr_private_root().'/'.$backup,$code);
$record=['schema'=>1,'design_id'=>SSWR_DESIGN,'source'=>SSWR_SOURCE,'order_id'=>56,'expires'=>time()+60,'state'=>'armed','flags'=>['DTF_ADMIN_GENERATE'],'files'=>[['path'=>$path,'backup'=>$backup,'before'=>$before,'after'=>hash('sha256',$changed),'mode'=>0600]]];sswr_config_lease($record);
sswr_restore_dtf_config();if(file_get_contents($path)!==$code)throw new RuntimeException('original_not_restored');sswr_restore_dtf_config();
sswr_config_lease($record);file_put_contents($path,$changed.'// Owner concurrent edit');
try{sswr_restore_dtf_config();throw new RuntimeException('concurrent_edit_overwritten');}catch(RuntimeException $e){if($e->getMessage()!=='configuration_conflict_original_retained')throw $e;}
if(!str_contains(file_get_contents($path),'Owner concurrent edit')||hash_file('sha256',sswr_private_root().'/'.$backup)!==$before)throw new RuntimeException('configuration_data_lost');
$record['expires']=time()-1;sswr_config_lease($record);if(sswr_config_lease_live()!==false)throw new RuntimeException('expired_lease_enabled');
$historical=$record;$historical['source']=str_repeat('a',40);$historical['state']='restored';file_put_contents($path,$code);sswr_config_lease($historical);
if(sswr_config_lease()['state']!=='restored'||sswr_config_lease_live()!==false)throw new RuntimeException('historical restoration unreadable or enabled');
$historical['state']='armed';sswr_config_lease($historical);try{sswr_config_lease();throw new RuntimeException('foreign lease inherited');}catch(RuntimeException $e){if($e->getMessage()!=='foreign_config_lease_active')throw $e;}
$historical['state']='restored';sswr_config_lease($historical);file_put_contents($path,$changed);try{sswr_config_lease();throw new RuntimeException('unrestored historical accepted');}catch(RuntimeException $e){if($e->getMessage()!=='foreign_config_restoration_unverified')throw $e;}
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $p){if($p->isDir())rmdir($p->getPathname());else unlink($p->getPathname());}rmdir($base);
echo "PASS: comments/strings/GC untouched, restore exact and idempotent, concurrent edit retained, expiry fail-closed\n";
