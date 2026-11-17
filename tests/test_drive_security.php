<?php
/** Real Drive helper functions with synthetic HTTP responses; no OAuth or network. */
define('ABSPATH',__DIR__);$count=0;$GLOBALS['requests']=[];$GLOBALS['response']=[];
function add_action(){}function get_transient($k){return $k==='kld_drive_access_token'?'synthetic-token':false;}
function get_option($key,$default=false){return $key==='kld_master_folder'?'fixture-master-folder':$default;}
function home_url($path){return 'https://fixture.invalid'.$path;}
function is_wp_error($v){return $v instanceof WP_Error;}
class WP_Error {public $code;function __construct($code,$msg='',$data=[]){$this->code=$code;}}
function wp_remote_request($url,$args){$GLOBALS['requests'][]=[$url,$args];return ['code'=>200,'body'=>json_encode($GLOBALS['response'])];}
function wp_remote_get($url,$args){$GLOBALS['requests'][]=[$url,$args];return ['code'=>206,'body'=>base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a9t8AAAAASUVORK5CYII=')];}
function wp_remote_retrieve_response_code($r){return $r['code'];}function wp_remote_retrieve_body($r){return $r['body'];}
$plugin=getenv('KLD_TEST_PLUGIN_DIR')?:__DIR__.'/../plugins/kaoscetak-lite-designer';require $plugin.'/includes/drive.php';
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
check(kld_drive_order_folder($order,'fixture-order-parent')==='recovered-folder-42','lost folder response recovered');
$url=$GLOBALS['requests'][count($GLOBALS['requests'])-1][0];parse_str(parse_url($url,PHP_URL_QUERY),$query);
check(strpos($query['q'],"'fixture-order-parent' in parents")!==false,'folder recovery bound to selected parent');
check(is_wp_error(kld_drive_order_folder($order,"bad'parent")),'invalid parent rejected before Drive query');
echo "PASS: $count Drive privacy/content/recovery assertions (mock HTTP)\n";
