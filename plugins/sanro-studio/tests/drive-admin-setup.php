<?php
require __DIR__.'/drive-oauth-security.php';
mkdir($dir,0700);$GLOBALS['options']=[];$GLOBALS['allow']=true;$GLOBALS['nonce']=true;
function esc_html($s){return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}function esc_attr($s){return esc_html($s);}function esc_url($s){return esc_html($s);}
function wp_nonce_field($s){echo '<input type="hidden" name="_wpnonce" value="fixture">';}
function submit_button($label,$type='',$name='submit'){echo '<button name="'.esc_attr($name).'">'.esc_html($label).'</button>';}
function add_query_arg($key,$value,$url){return $url.'&'.$key.'='.$value;}
function wp_safe_redirect($url,$code){throw new RuntimeException('redirect:'.substr($url,strrpos($url,'=')+1));}
function wp_unslash($s){return $s;}
$baseRequests=$GLOBALS['requests'];$baseRoot=count($GLOBALS['root_requests']);
if(sanro_studio_drive_oauth_status()!=='NOT CONFIGURED'||sanro_studio_drive_oauth_test_config()!=='configure_client'||sanro_studio_drive_oauth_set_auto(true)!=='configure_client'||sanro_studio_drive_oauth_set_auto(false)!=='auto_off'||sanro_studio_drive_oauth_disconnect_config()!=='disconnected')throw new RuntimeException('unconfigured operation broken');
if($GLOBALS['requests']!==$baseRequests||count($GLOBALS['root_requests'])!==$baseRoot||$GLOBALS['options'])throw new RuntimeException('unconfigured setup made network request or wrote credentials');
ob_start();sanro_studio_drive_oauth_page();$page=ob_get_clean();foreach(['NOT CONFIGURED','Google OAuth Client ID','Google OAuth Client Secret','Redirect URI','Connect Google Drive','Disconnect Google Drive','Test Connection','Folder penyimpanan produksi','Aktifkan ekspor otomatis','Google Drive'] as $label)if(strpos($page,$label)===false)throw new RuntimeException('missing setup control: '.$label);
$input=['client_id'=>'123456-fixture.apps.googleusercontent.com','client_secret'=>'FIXTURE_ONLY_SECRET_123456789','parent_id'=>'existingFolder123456','picker_key'=>'FIXTURE_ONLY_PICKER_KEY_123456'];
if(sanro_studio_drive_oauth_apply_config($input)!=='saved'||sanro_studio_drive_oauth_status()!=='NOT CONNECTED')throw new RuntimeException('configuration save/status failed');
$saved=$GLOBALS['options'];foreach([['client_id'=>'invalid'],['parent_id'=>'https://wrong.example'],['client_secret'=>['not','string']],['picker_key'=>'invalid key']] as $change){if(sanro_studio_drive_oauth_apply_config(array_replace($input,$change))==='saved'||$GLOBALS['options']!==$saved)throw new RuntimeException('bad setup changed existing configuration');}
if(strpos(json_encode($saved),$input['client_secret'])!==false)throw new RuntimeException('plaintext client stored');
ob_start();sanro_studio_drive_oauth_page();$page=ob_get_clean();if(strpos($page,$input['client_secret'])!==false)throw new RuntimeException('secret redisplayed');
$input['client_secret']='';if(sanro_studio_drive_oauth_apply_config($input)!=='saved')throw new RuntimeException('same-client secret preservation failed');
$before=$GLOBALS['requests'];if(sanro_studio_drive_oauth_test_config()!=='consent_required'||$GLOBALS['requests']!==$before)throw new RuntimeException('disconnected Test Connection contacted Google');
if(sanro_studio_drive_oauth_set_auto(true)!=='test_required'||sanro_studio_drive_oauth_auto_allowed())throw new RuntimeException('unconnected auto export enabled');
$s=sanro_studio_drive_oauth_settings();$s['tokens']=sanro_studio_drive_oauth_seal(['access_token'=>str_repeat('t',48),'refresh_token'=>str_repeat('r',48),'expires_at'=>time()+3600,'scope'=>'https://www.googleapis.com/auth/drive.file'],'tokens');$GLOBALS['options']['sanro_studio_drive_oauth']=$s;
$GLOBALS['folder']=['id'=>'existingFolder123456','mimeType'=>'application/vnd.google-apps.folder','trashed'=>false,'capabilities'=>['canAddChildren'=>true]];$GLOBALS['permissions']=['permissions'=>[['type'=>'user','role'=>'owner']]];
if(sanro_studio_drive_oauth_test_config()!=='test_pass'||sanro_studio_drive_oauth_set_auto(true)!=='auto_on'||!sanro_studio_drive_oauth_auto_allowed()||sanro_studio_drive_oauth_set_auto(false)!=='auto_off'||sanro_studio_drive_oauth_auto_allowed())throw new RuntimeException('test/toggle lifecycle failed');
$oldRevision=sanro_studio_drive_oauth_settings()['revision'];if(sanro_studio_drive_oauth_disconnect_config()!=='disconnected')throw new RuntimeException('disconnect failed');$s=sanro_studio_drive_oauth_settings();if(isset($s['tokens'])||isset($s['parent_id'])||$s['auto_export']!==false||$s['revision']===$oldRevision||sanro_studio_drive_oauth_token()!=='')throw new RuntimeException('disconnect retained authorization');
foreach(['save','start','disconnect','test','auto'] as $action){$function='sanro_studio_drive_oauth_'.$action;$GLOBALS['allow']=false;try{$function();throw new LogicException('unauthorized action accepted');}catch(RuntimeException $e){if($e->getMessage()!=='permission rejected')throw $e;}$GLOBALS['allow']=true;$GLOBALS['nonce']=false;try{$function();throw new LogicException('CSRF action accepted');}catch(RuntimeException $e){if($e->getMessage()!=='nonce rejected')throw $e;}}
$GLOBALS['allow']=false;ob_start();sanro_studio_drive_oauth_page();if(ob_get_clean()!=='')throw new RuntimeException('nonadmin setup disclosure');
if(is_file($dir.'/.sanro-drive-oauth.lock'))unlink($dir.'/.sanro-drive-oauth.lock');rmdir($dir);
echo "PASS: complete admin UI, no-credential/no-network operation, validated encrypted save, private test doubles, auto ON/OFF integration, disconnect+revocation, permission/CSRF denial; LIVE OAUTH/UPLOAD DEFERRED BY OWNER\n";
