<?php
/** No network/account: exercise actual credential encryption, refresh/cache, origin and request boundaries. */
define('ABSPATH',__DIR__);$dir=sys_get_temp_dir().'/sanro-oauth-test-'.bin2hex(random_bytes(8));mkdir($dir,0700);define('SANRO_STUDIO_PRIVATE_MASTER_DIR',$dir);
$GLOBALS['origin']='https://staging.example.test/';$GLOBALS['salt']=str_repeat('fixture-only-',8);$GLOBALS['options']=[];$GLOBALS['requests']=0;$GLOBALS['allow']=true;$GLOBALS['nonce']=true;
function home_url($v){return $GLOBALS['origin'];}function wp_salt($v){return $GLOBALS['salt'];}function wp_json_encode($v){return json_encode($v);}function get_option($k,$default=null){return $GLOBALS['options'][$k]??$default;}function update_option($k,$v,$autoload=false){$GLOBALS['options'][$k]=$v;return true;}
function is_wp_error($v){return !is_array($v);}function wp_remote_retrieve_response_code($v){return $v['code']??0;}function wp_remote_retrieve_body($v){return $v['body']??'';}function sanro_studio_storage_health($v){return ['safe'=>$v===SANRO_STUDIO_PRIVATE_MASTER_DIR];}
function wp_remote_post($url,$args){$GLOBALS['requests']++;if($url==='https://oauth2.googleapis.com/revoke'){if($args['redirection']!==0||$args['sslverify']!==true||!isset($args['body']['token']))throw new RuntimeException('unsafe revocation');return ['code'=>200,'body'=>''];}if($url!=='https://oauth2.googleapis.com/token'||$args['redirection']!==0||$args['sslverify']!==true||$args['body']['grant_type']!=='refresh_token')throw new RuntimeException('unsafe token endpoint');return $GLOBALS['response'];}
function current_user_can($v){return $GLOBALS['allow'];}function is_ssl(){return true;}function admin_url($v){return home_url('/').'wp-admin/'.$v;}function check_admin_referer($v){if(!$GLOBALS['nonce'])throw new RuntimeException('nonce rejected');}function wp_die(...$args){throw new RuntimeException('permission rejected');}
require __DIR__.'/../includes/drive-oauth.php';
$secret='TEST-ONLY-CLIENT-SECRET-123456789';$refresh='1//TEST_ONLY_REFRESH_'.str_repeat('a',40);$access=str_repeat('b',48);
$sealed=sanro_studio_drive_oauth_seal(['secret'=>$secret],'client');if(!$sealed||strpos($sealed,$secret)!==false||sanro_studio_drive_oauth_open($sealed,'client')['secret']!==$secret||sanro_studio_drive_oauth_open($sealed,'tokens')!==false)throw new RuntimeException('credential encryption or purpose binding failed');
$bad=$sealed;$bad[20]=$bad[20]==='A'?'B':'A';if(sanro_studio_drive_oauth_open($bad,'client')!==false)throw new RuntimeException('tampered ciphertext accepted');
$savedSalt=$GLOBALS['salt'];$GLOBALS['salt']='other installation secret';if(sanro_studio_drive_oauth_open($sealed,'client')!==false)throw new RuntimeException('foreign installation decrypted');$GLOBALS['salt']=$savedSalt;
$settings=['schema'=>1,'origin'=>sanro_studio_drive_oauth_origin(),'client_id'=>'123456-test.apps.googleusercontent.com','secret'=>$sealed,'revision'=>str_repeat('a',32),'parent_id'=>'folder123456789','tokens'=>sanro_studio_drive_oauth_seal(['access_token'=>str_repeat('c',48),'refresh_token'=>$refresh,'expires_at'=>time()-10,'scope'=>'https://www.googleapis.com/auth/drive.file'],'tokens')];$GLOBALS['options']['sanro_studio_drive_oauth']=$settings;
$GLOBALS['response']=['code'=>200,'body'=>json_encode(['access_token'=>$access,'token_type'=>'Bearer','expires_in'=>3600,'scope'=>'https://www.googleapis.com/auth/drive.file'])];
if(sanro_studio_drive_oauth_token()!==$access||$GLOBALS['requests']!==1||sanro_studio_drive_oauth_token()!==$access||$GLOBALS['requests']!==1)throw new RuntimeException('refresh/cache failed');
$stored=json_encode($GLOBALS['options']);foreach([$secret,$refresh,$access] as $s)if(strpos($stored,$s)!==false)throw new RuntimeException('plaintext credential stored');
$GLOBALS['origin']='https://production.example.test/';if(sanro_studio_drive_oauth_token()!==''||$GLOBALS['requests']!==1)throw new RuntimeException('origin isolation failed');$GLOBALS['origin']='https://staging.example.test/';
foreach([['access_token'=>"bad\r\nAuthorization: forged"],['scope'=>'https://www.googleapis.com/auth/drive'],['expires_in'=>9999999],['token_type'=>'unexpected']] as $change){$base=['access_token'=>$access,'refresh_token'=>$refresh,'token_type'=>'Bearer','expires_in'=>3600,'scope'=>'https://www.googleapis.com/auth/drive.file'];if(sanro_studio_drive_oauth_token_result(['code'=>200,'body'=>json_encode(array_replace($base,$change))],'',true)!==false)throw new RuntimeException('unsafe grant accepted');}
$_SERVER['REQUEST_METHOD']='POST';$GLOBALS['allow']=false;try{sanro_studio_drive_oauth_guard('save');throw new RuntimeException('permission missing');}catch(RuntimeException $e){if($e->getMessage()!=='permission rejected')throw $e;}
$GLOBALS['allow']=true;$GLOBALS['nonce']=false;try{sanro_studio_drive_oauth_guard('save');throw new RuntimeException('nonce missing');}catch(RuntimeException $e){if($e->getMessage()!=='nonce rejected')throw $e;}
unlink($dir.'/.sanro-drive-oauth.lock');rmdir($dir);
echo "PASS: encrypted secrets, authenticated tamper/purpose/origin isolation, one refresh+cache, grant allowlist, admin/nonce gates; NO real OAuth/API request\n";
// Existing-root privacy checks: no root creation, no public/group/domain/incomplete permissions.
function wp_remote_request($url,$args){
    if($args['method']!=='GET'||strpos($url,'https://www.googleapis.com/drive/v3/files/')!==0)throw new RuntimeException('root attempted write or foreign endpoint');
    $GLOBALS['root_requests'][]=$url;
    return ['code'=>200,'body'=>json_encode(strpos($url,'/permissions?')!==false?$GLOBALS['permissions']:$GLOBALS['folder'])];
}
$GLOBALS['folder']=['id'=>'folder123456789','mimeType'=>'application/vnd.google-apps.folder','trashed'=>false,'capabilities'=>['canAddChildren'=>true]];
$GLOBALS['permissions']=['permissions'=>[['type'=>'user','role'=>'owner']]];
$GLOBALS['root_requests']=[];
if(sanro_studio_drive_oauth_private_root($access)!=='folder123456789'||count($GLOBALS['root_requests'])!==2)throw new RuntimeException('existing private root failed');
foreach(['anyone','group','domain'] as $type){$GLOBALS['permissions']=['permissions'=>[['type'=>'user','role'=>'owner'],['type'=>$type,'role'=>'reader']]];if(sanro_studio_drive_oauth_private_root($access)!==false)throw new RuntimeException('shared root accepted');}
$GLOBALS['permissions']=['permissions'=>[['type'=>'user','role'=>'owner']],'nextPageToken'=>'more'];if(sanro_studio_drive_oauth_private_root($access)!==false)throw new RuntimeException('partial permissions accepted');
$GLOBALS['permissions']=['permissions'=>[['type'=>'user','role'=>'owner']]];
$GLOBALS['folder']['capabilities']['canAddChildren']=false;if(sanro_studio_drive_oauth_private_root($access)!==false)throw new RuntimeException('read-only root accepted');
$GLOBALS['options']['sanro_studio_drive_oauth']['requested_parent_id']='not/a/folder';$before=count($GLOBALS['root_requests']);if(sanro_studio_drive_oauth_private_root($access)!==false||count($GLOBALS['root_requests'])!==$before)throw new RuntimeException('invalid root made request');
echo "PASS: exact existing-root reuse; no creation; unsafe/shared/partial/read-only roots rejected\n";
