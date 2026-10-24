<?php
/** Standalone security tests for editable forks; WordPress integration remains a separate gate. */
define('ABSPATH', __DIR__);
$GLOBALS['meta'] = [42 => [
 '_kld_owner_email'=>'owner@example.org', '_kld_product'=>901,
 '_kld_sides'=>['front'=>['tier'=>'A5','image'=>['x'=>1,'y'=>2,'w'=>10,'h'=>12]],'back'=>['tier'=>'none']],
 '_kld_color'=>'Hitam','_kld_artwork_front'=>base64_encode('RIFF1234WEBPdemo'),
 '_kld_file_front'=>'master-google-drive-42', '_kld_preview_front'=>'smallmockup',
 '_kld_share_seed'=>str_repeat('a',40),
]];
$GLOBALS['types']=[42=>'kld_design'];$GLOBALS['nextid']=100;
function add_action(){} function get_post_type($id){return $GLOBALS['types'][$id]??false;}
function get_post_meta($id,$key,$single=true){return $GLOBALS['meta'][$id][$key]??null;}
function update_post_meta($id,$key,$value){$GLOBALS['meta'][$id][$key]=$value;}
function delete_post_meta($id,$key){unset($GLOBALS['meta'][$id][$key]);}
function absint($v){return abs((int)$v);} function wp_salt($v){return 'test-secret-'.$v;}
function wp_insert_post($data,$error=true){$id=++$GLOBALS['nextid'];$GLOBALS['types'][$id]=$data['post_type'];return $id;}
function is_wp_error($v){return $v instanceof WP_Error;}
class WP_Error{private $code;function __construct($code,$message='',$data=[]){$this->code=$code;}function get_error_code(){return $this->code;}}
function wc_get_product($id){return $id===901?(object)['id'=>901]:false;}
function rest_ensure_response($x){return $x;}
class WP_REST_Request {
 private $data=[],$headers=[];
 function __construct($method,$route){}
 function set_param($k,$v){$this->data[$k]=$v;}
 function get_param($k){return $this->data[$k]??null;}
 function set_header($k,$v){$this->headers[strtolower($k)]=$v;}
 function get_header($k){return $this->headers[strtolower($k)]??'';}
}
require __DIR__.'/../plugins/kaoscetak-lite-designer/includes/sharing.php';
require __DIR__.'/../plugins/kaoscetak-lite-designer/includes/api.php';
function check($truth,$message){global $count;$count++;if(!$truth)throw new Exception('FAIL: '.$message);}
$count=0;
check(kld_fork_has_artwork(42),'original with image and artwork can be forked');
$clone=kld_clone_shared_design(42,'Hitam');
check(is_array($clone),'fork generated');
[$new,$token]=$clone;
check($new!==42 && strlen($token)===64,'fork has independent ID and edit token');
check(get_post_meta($new,'_kld_file_front',true)==='master-google-drive-42','fork keeps private master reference');
check(get_post_meta($new,'_kld_artwork_front',true)===get_post_meta(42,'_kld_artwork_front',true),'artwork preview copied');
check(!get_post_meta($new,'_kld_owner_email',true),'owner is not inherited');
check(!get_post_meta($new,'_kld_share_seed',true),'fork starts private');
$request=new WP_REST_Request('POST','/kld/v1/load');
$request->set_param('design_id',$new);$request->set_header('X-KLD-Token',$token);
$loaded=kld_api_load($request);
check(!is_wp_error($loaded),'holder can restore fork');
check($loaded['product_id']===901,'product restored');
check($loaded['sides']['front']['tier']==='A5','dimensions restored');
check(strpos($loaded['layers']['front'],'data:image/webp;base64,')===0,'webp artwork returned privately');
check(!isset($loaded['files'])&&!isset($loaded['owner_email'])&&!isset($loaded['edit_hash']),'never leak master URL or owner data');
$request->set_header('X-KLD-Token',str_repeat('0',64));
$blocked=kld_api_load($request);check(is_wp_error($blocked)&&$blocked->get_error_code()==='no_access','wrong token rejected');
update_post_meta(42,'_kld_artwork_front','replaced');
update_post_meta(42,'_kld_deleted','yes');
$request->set_header('X-KLD-Token',$token);
$restored=kld_api_load($request);
check(!is_wp_error($restored)&&$restored['layers']['front']===$loaded['layers']['front'],'fork unchanged after source mutation/revocation');
$GLOBALS['meta'][55]=['_kld_sides'=>['front'=>['tier'=>'A5','image'=>['x'=>1,'y'=>1,'w'=>3,'h'=>3]]]];$GLOBALS['types'][55]='kld_design';
check(!kld_fork_has_artwork(55),'older design without editable art cannot claim working editor');
$GLOBALS['meta'][56]=['_kld_sides'=>['front'=>['tier'=>'A5','text'=>['value'=>'ABC']]]];$GLOBALS['types'][56]='kld_design';
check(kld_fork_has_artwork(56),'text-only design remains editable');
echo "PASS: $count editable fork security/restore assertions\n";