<?php
/** Isolated authenticated REST master-read tests; no WP runtime or live files. */
define('ABSPATH', __DIR__ . '/');
define('SANRO_STUDIO_ENABLE_DRAFT_API', true);
define('SANRO_STUDIO_ENABLE_PRIVATE_MASTERS', true);
define('SANRO_STUDIO_ENABLE_MASTER_UPLOAD', true);
class WP_Error {
    public $code;
    public $message;
    public $data;
    public function __construct($code,$message,$data) {
        $this->code=$code;$this->message=$message;$this->data=$data;
    }
}
class SanroReadRequest {
    private $params;
    private $headers;
    function __construct($params,$headers=[]) {$this->params=$params;$this->headers=$headers;}
    function get_param($key) {return $this->params[$key]??null;}
    function get_header($key) {return $this->headers[$key]??null;}
}
class SanroReadResponse {
    public $data;
    public $headers=[];
    function __construct($data) {$this->data=$data;}
    function header($key,$value) {$this->headers[$key]=$value;}
}
$GLOBALS['routes']=[];
$GLOBALS['read_calls']=[];
$GLOBALS['no_cache_calls']=0;
$id='11111111-1111-4111-8111-111111111111';
$token=str_repeat('a',64);
$bytes='synthetic-master-byte-fixture-no-personal-data';
$hash=hash('sha256',$bytes);
$file_id=strtolower($id).'-front-'.$hash.'.png';
function sanro_studio_frontend_enabled() {return true;}
function register_rest_route($ns,$route,$args) {$GLOBALS['routes'][$ns.$route]=$args;}
function wp_verify_nonce($nonce,$action) {return $nonce==='valid-nonce' && $action==='sanro_studio_draft';}
function nocache_headers() {$GLOBALS['no_cache_calls']++;}
function rest_ensure_response($data) {return new SanroReadResponse($data);}
function sanro_studio_read_draft($id,$token) {
    global $file_id;
    if($token!==str_repeat('a',64))return ['valid'=>false,'error'=>'not_found'];
    return ['valid'=>true,'master_refs'=>['front'=>$file_id],'status'=>'draft'];
}
function sanro_studio_read_owned_draft_master($id,$token,$side,$file_id) {
    global $bytes,$hash;
    $GLOBALS['read_calls'][]=[$side,$file_id];
    return ['valid'=>true,'mime'=>'image/png','sha256'=>$hash,'bytes'=>$bytes];
}
require_once __DIR__.'/../includes/draft-rest.php';
$count=0;
function expect_it($condition,$label) {
    global $count;
    if(!$condition)throw new RuntimeException('FAIL: '.$label);
    $count++;
}
sanro_studio_register_master_upload_route();
$new_path='sanro-studio/v1/drafts/(?P<id>[a-fA-F0-9-]{36})/master-read/(?P<side>front|back)';
$old_path='sanro-studio/v1/drafts/(?P<id>[a-fA-F0-9-]{36})/masters/(?P<side>front|back)/(?P<file_id>[a-zA-Z0-9._-]+)';
expect_it(isset($GLOBALS['routes'][$new_path]),'Short read route registered');
expect_it(isset($GLOBALS['routes'][$old_path]),'Legacy read route retained');
expect_it($GLOBALS['routes'][$new_path]['methods']==='GET','Read is GET');
expect_it($GLOBALS['routes'][$new_path]['callback']==='sanro_studio_rest_read_master','Short route binds owner callback');
expect_it($GLOBALS['routes'][$new_path]['permission_callback']==='sanro_studio_rest_draft_nonce','Nonce gate');
$headers=['X-Sanro-Draft-Nonce'=>'valid-nonce','X-Sanro-Owner-Token'=>$token];
$request=new SanroReadRequest(['id'=>$id,'side'=>'front'],$headers);
expect_it(sanro_studio_rest_draft_nonce(new SanroReadRequest([],[])) instanceof WP_Error,
    'Missing nonce rejected');
expect_it(sanro_studio_rest_draft_nonce($request)===true,'Valid nonce authorized');
$response=sanro_studio_rest_read_master($request);
expect_it($response instanceof SanroReadResponse,'Response object');
expect_it(($response->data['valid']??false)===true,'Private master fetched by bound reference');
expect_it($response->data['file_id']===$file_id,'Server supplies verified file ID');
expect_it($response->data['image_base64']===base64_encode($bytes),'Owner image bytes intact');
expect_it($response->data['sha256']===$hash,'Content digest intact');
expect_it($response->headers['Cache-Control']==='private, no-store, max-age=0',
    'Private response not cacheable');
expect_it(count($GLOBALS['read_calls'])===1,'One authenticated private read');
$wrong=new SanroReadRequest(['id'=>$id,'side'=>'front'],
    ['X-Sanro-Owner-Token'=>str_repeat('b',64)]);
expect_it(sanro_studio_rest_read_master($wrong) instanceof WP_Error,'Wrong owner denied');
$back=new SanroReadRequest(['id'=>$id,'side'=>'back'],$headers);
$denied=sanro_studio_rest_read_master($back);
expect_it($denied instanceof WP_Error && $denied->code==='sanro_master_not_bound',
    'Unbound side denied');
$legacy=new SanroReadRequest(['id'=>$id,'side'=>'front','file_id'=>$file_id],$headers);
expect_it(sanro_studio_rest_read_master($legacy) instanceof SanroReadResponse,
    'Matching legacy file reference allowed');
$stale=new SanroReadRequest(['id'=>$id,'side'=>'front','file_id'=>'different.png'],$headers);
$staleResult=sanro_studio_rest_read_master($stale);
expect_it($staleResult instanceof WP_Error && $staleResult->code==='sanro_master_not_bound',
    'Unbound historical filename rejected');
expect_it(count($GLOBALS['read_calls'])===2,'No storage reads for unauthorized requests');
expect_it($GLOBALS['no_cache_calls']===2,'No-cache enforced on valid reads');
echo "PASS: $count short-route private DTF read security checks\n";
