<?php
/** Real Drive helper functions with synthetic HTTP responses; no OAuth or network. */
define('ABSPATH',__DIR__);$count=0;$GLOBALS['requests']=[];$GLOBALS['response']=[];$GLOBALS['opts']=[];
function add_action(){}function add_filter($name,$callback,...$args){$GLOBALS['filters'][$name]=$callback;}function get_transient($k){return $k==='kld_drive_access_token'?'synthetic-token':false;}
function get_option($key,$default=false){return $key==='kld_master_folder'?'fixture-master-folder':($GLOBALS['opts'][$key]??$default);}
function add_option($key,$value,...$args){if(!empty($GLOBALS['fail_save']))return false;if(!empty($GLOBALS['race_save'])){$GLOBALS['opts'][$key]='concurrent-winner-id';$GLOBALS['race_save']=false;return false;}if(isset($GLOBALS['opts'][$key]))return false;$GLOBALS['opts'][$key]=$value;return true;}
function update_option($key,$value,...$args){$GLOBALS['opts'][$key]=$value;return true;}function delete_option($key){unset($GLOBALS['opts'][$key]);}
function wp_parse_url($url,$component=-1){return parse_url($url,$component);}
function wp_cache_delete(...$args){}
function wp_json_encode($v){return json_encode($v);}function wp_strip_all_tags($v){return strip_tags($v);}
function home_url($path){return 'https://fixture.invalid'.$path;}
function is_wp_error($v){return $v instanceof WP_Error;}
class WP_Error {public $code,$message,$data;function __construct($code,$msg='',$data=[]){$this->code=$code;$this->message=$msg;$this->data=$data;}function get_error_data(){return $this->data;}function get_error_message(){return $this->message;}}
function wp_remote_request($url,$args){$GLOBALS['requests'][]=[$url,$args];if(isset($GLOBALS['http_handler']))return ($GLOBALS['http_handler'])($url,$args);return ['code'=>200,'body'=>json_encode($GLOBALS['response'])];}
function wp_remote_post($url,$args){$args['method']='POST';return wp_remote_request($url,$args);}
function wp_remote_get($url,$args){$GLOBALS['requests'][]=[$url,$args];return ['code'=>206,'body'=>base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a9t8AAAAASUVORK5CYII=')];}
function wp_remote_retrieve_response_code($r){return $r['code'];}function wp_remote_retrieve_body($r){return $r['body'];}
$plugin=getenv('KLD_TEST_PLUGIN_DIR')?:__DIR__.'/../plugins/kaoscetak-lite-designer';require $plugin.'/includes/drive.php';require $plugin.'/includes/store.php';
function check($ok,$label){global $count;$count++;if(!$ok)throw new RuntimeException('FAIL '.$label);}
$base=['id'=>'fixture-file-42','name'=>'master.png','size'=>68,'mimeType'=>'image/png','parents'=>['fixture-master-folder'],'trashed'=>false,'permissions'=>[['type'=>'user']]];
$GLOBALS['response']=$base;
check(!is_wp_error(kld_drive_verify('fixture-file-42','image/png',68,'master.png')),'private PNG with real parsed header accepted');
$GLOBALS['response']=$base;unset($GLOBALS['response']['permissions']);
check(is_wp_error(kld_drive_verify('fixture-file-42','image/png',68,'master.png')),'missing permission evidence fails closed');
foreach(['anyone','domain'] as $type){$GLOBALS['response']=array_replace($base,['permissions'=>[['type'=>$type]]]);check(is_wp_error(kld_drive_verify('fixture-file-42','image/png',68,'master.png')),'public or domain master rejected');}
foreach([['trashed'=>true],['size'=>69],['mimeType'=>'text/html'],['parents'=>['different-folder']],['name'=>'other.png']] as $change){$GLOBALS['response']=array_replace($base,$change);check(is_wp_error(kld_drive_verify('fixture-file-42','image/png',68,'master.png')),'mismatched master metadata rejected');}
$GLOBALS['response']=$base;check(is_wp_error(kld_drive_verify("fixture-file-42\n",'image/png',68,'master.png')),'trailing newline in file ID rejected');
check(is_wp_error(kld_drive_verify([],'image/png',68,'master.png')),'non-scalar file ID rejected');
foreach([['text/html',68],['image/png',0],['image/png',10485761],['image/png',1.5]] as [$mime,$size])check(is_wp_error(kld_drive_verify('fixture-file-42',$mime,$size,'master.png')),'unsupported MIME or byte count rejected');
$GLOBALS['response']=$base;check(is_wp_error(kld_drive_verify('fixture-file-42','image/jpeg',68,'master.png')),'PNG bytes cannot masquerade as JPEG');
$GLOBALS['response']=['files'=>[['id'=>'recovered-folder-42']]];
$order=new class {function get_id(){return 42;}function get_order_number(){return '42';}};
check(kld_drive_find_artifact('fixture-order-parent',hash('sha256','fixture-order'),'application/vnd.google-apps.folder','kldOrder')==='recovered-folder-42','legacy folder response recovered');
$url=$GLOBALS['requests'][count($GLOBALS['requests'])-1][0];parse_str(parse_url($url,PHP_URL_QUERY),$query);
check(strpos($query['q'],"'fixture-order-parent' in parents")!==false,'folder recovery bound to selected parent');
check(is_wp_error(kld_drive_order_folder($order,"bad'parent")),'invalid parent rejected before Drive query');
$artifactKey=str_repeat('a',64);
$GLOBALS['response']=['files'=>[['id'=>'existing-artifact-42']]];
check(kld_drive_find_artifact('fixture-order-parent',$artifactKey,'application/pdf')==='existing-artifact-42','lost PDF response recovered');
$GLOBALS['response']=['files'=>[['id'=>'first'],['id'=>'second']]];
check(is_wp_error(kld_drive_find_artifact('fixture-order-parent',$artifactKey,'application/pdf')),'duplicate PDF blocks unsafe retry');
$GLOBALS['response']=['files'=>[]];
check(kld_drive_find_artifact('fixture-order-parent',$artifactKey,'application/pdf')==='','missing artifact allows creation');
check(is_wp_error(kld_drive_find_artifact("bad'parent",$artifactKey,'application/pdf')),'invalid artifact parent rejected');
$GLOBALS['response']=['files'=>[['id'=>'existing-shortcut-42']]];
$GLOBALS['http_handler']=function($url,$args)use($artifactKey){return ['code'=>200,'body'=>json_encode(strpos($url,'/files?')!==false?['files'=>[['id'=>'existing-shortcut-42']]]:['id'=>'existing-shortcut-42','mimeType'=>'application/vnd.google-apps.shortcut','parents'=>['fixture-order-parent'],'trashed'=>false,'appProperties'=>['kldArtifact'=>$artifactKey],'shortcutDetails'=>['targetId'=>'master-file-42']])];};
$before=count($GLOBALS['requests']);
$shortcut=kld_drive_shortcut('master-file-42','fixture-order-parent','DESAIN-42-FRONT',$artifactKey);
check(($shortcut['id']??'')==='existing-shortcut-42','existing shortcut recovered without recreation');
check(count($GLOBALS['requests'])===$before+2,'shortcut recovery performs list and exact confirmation without creation');
echo "PASS: $count Drive privacy/content/recovery assertions (mock HTTP)\n";

// Stateful provider: creates commit before the response is lost. Listing is deliberately stale.
// Drive rejects a second create with the same ID with HTTP 409, even while GET returns 404.
$GLOBALS['files']=[];$GLOBALS['create_attempts']=[];$GLOBALS['generated_types']=[];$GLOBALS['drop']=true;$GLOBALS['hide_get']=true;
$GLOBALS['http_handler']=function($url,$args){
 $reply=function($code,$body){return ['code'=>$code,'body'=>json_encode($body)];};
 if(strpos($url,'/files/generateIds?')!==false){parse_str(parse_url($url,PHP_URL_QUERY),$q);$GLOBALS['generated_types'][]=$q['type']??'';return $reply(200,['ids'=>['reserved-file-'.count($GLOBALS['generated_types'])]]);}
 if(($args['method']??'GET')==='GET'){
  if(strpos($url,'/files?')!==false)return $reply(200,['files'=>[]]);
  $id=basename(parse_url($url,PHP_URL_PATH));return !$GLOBALS['hide_get']&&isset($GLOBALS['files'][$id])?$reply(200,$GLOBALS['files'][$id]):$reply(404,[]);
 }
 if(strpos($url,'uploadType=multipart')!==false){preg_match('/\r\n\r\n(\{[^\r\n]+\})\r\n/',$args['body'],$m);$meta=json_decode($m[1]??'',true);}else $meta=json_decode($args['body']??'',true);
 if(!is_array($meta))throw new RuntimeException('Invalid create request');
 $id=$meta['id']??('provider-created-'.count($GLOBALS['create_attempts']));$GLOBALS['create_attempts'][]=$id;
 if(isset($GLOBALS['files'][$id]))return $reply(409,[]);
 $meta['id']=$id;$meta['trashed']=false;$GLOBALS['files'][$id]=$meta;
 if($GLOBALS['drop'])return new WP_Error('timeout','Synthetic response lost after server commit');
 return $reply(200,['id'=>$id]);
};
foreach(['shortcut','pdf','folder'] as $kind){
 $GLOBALS['files']=[];$GLOBALS['create_attempts']=[];$GLOBALS['drop']=true;$GLOBALS['hide_get']=true;
 $key=hash('sha256','lost-'.$kind);
 $run=function()use($kind,$key,$order){
  if($kind==='shortcut')return kld_drive_shortcut('master-file-42','fixture-order-parent','DESAIN-42-FRONT',$key);
  if($kind==='folder')return kld_drive_order_folder($order,'fixture-order-parent');
  return kld_drive_upload_small('guide.pdf','application/pdf','%PDF-synthetic','fixture-order-parent',$key);
 };
 check(is_wp_error($run()),$kind.' lost create response is not marked complete');
 check(is_wp_error($run()),$kind.' hidden exact GET and conflict stay retryable');
 check(count($GLOBALS['files'])===1,$kind.' stale listing cannot cause duplicate creation');
 check(count(array_unique($GLOBALS['create_attempts']))===1,$kind.' all create retries use one persisted ID');
 $GLOBALS['hide_get']=false;
 $r=$run();check(!is_wp_error($r),$kind.' retry recovers exact existing resource');
 $before=count($GLOBALS['create_attempts']);$again=$run();
 check(!is_wp_error($again)&&count($GLOBALS['create_attempts'])===$before,$kind.' completion retry does not POST');
 $id=array_key_first($GLOBALS['files']);$GLOBALS['files'][$id]['parents']=['wrong-parent'];
 check(is_wp_error($run())&&count($GLOBALS['create_attempts'])===$before,$kind.' mismatched existing resource fails closed');
}
check(in_array('shortcuts',$GLOBALS['generated_types'],true),'shortcut IDs generated with provider shortcut type');
$GLOBALS['fail_save']=true;$before=count($GLOBALS['create_attempts']);
check(is_wp_error(kld_drive_shortcut('master-file-42','fixture-order-parent','no-save',hash('sha256','cannot-save'))),'failed durable reservation blocks create');
check(count($GLOBALS['create_attempts'])===$before,'reservation failure never POSTs');
$GLOBALS['fail_save']=false;
$GLOBALS['hide_get']=true;$GLOBALS['drop']=false;$GLOBALS['race_save']=true;
$plan=kld_drive_artifact_plan('fixture-order-parent',hash('sha256','race'),'application/pdf');
check(!is_wp_error($plan)&&$plan['id']==='concurrent-winner-id','concurrent reservation uses durable winner');
$before=count($GLOBALS['generated_types']);
check(kld_drive_artifact_plan('fixture-order-parent',hash('sha256','race'),'application/pdf')===$plan&&count($GLOBALS['generated_types'])===$before,'crash before create retains reservation without regenerating ID');
$provider=$GLOBALS['http_handler'];
$GLOBALS['http_handler']=function($url,$args){return ['code'=>503,'body'=>'{}'];};$before=count($GLOBALS['create_attempts']);
check(is_wp_error(kld_drive_upload_small('guide.pdf','application/pdf','%PDF','fixture-order-parent',hash('sha256','race')))&&count($GLOBALS['create_attempts'])===$before,'503 on exact lookup never permits create');
foreach([['files'=>[],'incompleteSearch'=>true],['files'=>[],'nextPageToken'=>'more'],['files'=>[['id'=>'bad']]],['files'=>[[]]]] as $response){
 $GLOBALS['http_handler']=function()use($response){return ['code'=>200,'body'=>json_encode($response)];};
 check(is_wp_error(kld_drive_find_artifact('fixture-order-parent',hash('sha256','lookup'),'application/pdf')),'incomplete/ambiguous/malformed listing blocks creation');
}
$GLOBALS['http_handler']=function(){return ['code'=>200,'body'=>'not-json'];};
check(is_wp_error(kld_drive_request('GET','files?fields=id')),'malformed successful JSON fails closed');
foreach([['parents','wrong-type'],['appProperties','wrong-type'],['trashed',true],['mimeType','text/plain'],['appProperties',['kldArtifact'=>'wrong-key']]] as [$field,$bad]){
 $data=$plan;$data['trashed']=false;$data[$field]=$bad;
 $GLOBALS['http_handler']=function()use($data){return ['code'=>200,'body'=>json_encode($data)];};
 check(is_wp_error(kld_drive_artifact_probe($plan)),'invalid resource metadata blocks completion');
}
$shortcutPlan=$plan;$shortcutPlan['mimeType']='application/vnd.google-apps.shortcut';$shortcutPlan['shortcutDetails']=['targetId'=>'master-file-42'];
$data=$shortcutPlan;$data['trashed']=false;$data['shortcutDetails']['targetId']='different-master-42';
$GLOBALS['http_handler']=function()use($data){return ['code'=>200,'body'=>json_encode($data)];};
check(is_wp_error(kld_drive_artifact_probe($shortcutPlan)),'wrong shortcut target cannot be adopted');
// Exercise the SAME synthetic transport adapter used by the WordPress staging fixture.
define('KLD_LOCAL_FIXTURE',true);$GLOBALS['opts']['home']='http://127.0.0.1:8893';
require __DIR__.'/drive-fixture.php';
$GLOBALS['http_handler']=function($url,$args){$r=($GLOBALS['filters']['pre_http_request'])(false,$args,$url);if(is_wp_error($r))return $r;return ['code'=>$r['response']['code'],'body'=>$r['body']];};
update_option('kld_fixture_hide_artifact_lists',true);
foreach(['shortcut','pdf','folder'] as $kind){
 $key=hash('sha256','staging-'.$kind);$before=(int)get_option('kld_fixture_http_creates');update_option('kld_fixture_drop_artifact',true);
 $run=function()use($kind,$key){
  if($kind==='shortcut')return kld_drive_shortcut('master-file-42','fixture-order-parent','fixture',$key);
  if($kind==='folder')return kld_drive_order_folder(new class{function get_id(){return 456;}function get_order_number(){return '456';}},'fixture-order-parent');
  return kld_drive_upload_small('guide.pdf','application/pdf','%PDF','fixture-order-parent',$key);
 };
 check(is_wp_error($run()),$kind.' staging adapter simulates lost response');
 check(!is_wp_error($run()),$kind.' staging adapter exact-ID recovery succeeds');
 check((int)get_option('kld_fixture_http_creates')===$before+1,$kind.' staging adapter commits one artifact');
}
echo "PASS: $count total Drive assertions including lost responses, stale search and repeated retries\n";
