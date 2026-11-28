<?php
// Offline ownership/current-revision checks; no WP, network or filesystem writes.
define('ABSPATH', __DIR__);
define('SANRO_STUDIO_ENABLE_DRAFT_API', true);
define('SANRO_STUDIO_ENABLE_PRIVATE_PREVIEWS', true);
define('SANRO_STUDIO_ENABLE_PREVIEW_UPLOAD', true);
class WP_Error { public $code, $data; function __construct($code,$message,$data){$this->code=$code;$this->data=$data;} }
class PreviewResponse { public $data,$headers=[]; function __construct($data){$this->data=$data;} function header($key,$value){$this->headers[$key]=$value;} }
class PreviewRequest { public $side='front',$token='owner',$supplied='attacker.jpg'; function get_param($key){return ['id'=>'11111111-1111-4111-8111-111111111111','side'=>$this->side,'file_id'=>$this->supplied][$key]??null;} function get_header($key){return $this->token;} }
function sanro_studio_frontend_enabled(){return true;}
function register_rest_route($ns,$route,$args){$GLOBALS['routes'][$route]=$args;}
function rest_ensure_response($data){return new PreviewResponse($data);}
function nocache_headers(){}
function sanro_studio_read_draft($id,$token){return $token==='owner'?['valid'=>true,'design_hash'=>str_repeat('a',64)]:['valid'=>false,'error'=>$token==='expired'?'draft_expired':'not_found'];}
function get_option($key,$default=false){return $GLOBALS['record'];}
function sanro_studio_read_owned_draft_preview($id,$token,$side,$ref){$GLOBALS['reads'][]=$ref;return $GLOBALS['corrupt']?['valid'=>false]:['valid'=>true,'mime'=>'image/jpeg','bytes'=>'test-preview'];}
require __DIR__.'/../includes/draft-rest.php';
$ref='11111111-1111-4111-8111-111111111111-front-'.str_repeat('b',64).'.jpg';
$GLOBALS['record']=['preview_refs'=>['front'=>$ref],'preview_design_hash'=>str_repeat('a',64)];
$GLOBALS['reads']=[];$GLOBALS['corrupt']=false;$GLOBALS['routes']=[];$checks=0;
function preview_check($ok,$message){global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
sanro_studio_register_preview_upload_route();
$short='/drafts/(?P<id>[a-fA-F0-9-]{36})/preview-read/(?P<side>front|back)';
preview_check(($GLOBALS['routes'][$short]['methods']??'')==='GET','GET route registered');
preview_check($GLOBALS['routes'][$short]['permission_callback']==='sanro_studio_rest_draft_nonce','nonce gate retained');
$legacy=array_values(array_filter(array_keys($GLOBALS['routes']),fn($key)=>str_contains($key,'file_id')))[0];
preview_check(preg_match('#^'.$legacy.'$#','/drafts/11111111-1111-4111-8111-111111111111/previews/front/'.$ref)===1,'legacy regex matches literal dot filename');
preview_check(preg_match('#^'.$legacy.'$#','/drafts/11111111-1111-4111-8111-111111111111/previews/front/'.str_replace('.jpg','Xjpg',$ref))===0,'legacy regex rejects wildcard extension');
$request=new PreviewRequest();$response=sanro_studio_rest_read_current_preview($request);
preview_check($response instanceof PreviewResponse && $response->data['file_id']===$ref,'server selects bound reference');
preview_check($GLOBALS['reads']===[$ref],'injected filename never read');
preview_check($response->data['image_base64']===base64_encode('test-preview'),'bytes preserved');
preview_check($response->headers['Cache-Control']==='private, no-store, max-age=0','private no-store response');
foreach (['wrong'=>404,'expired'=>410] as $token=>$status){$request->token=$token;$before=count($GLOBALS['reads']);$result=sanro_studio_rest_read_current_preview($request);preview_check($result instanceof WP_Error&&$result->data['status']===$status&&count($GLOBALS['reads'])===$before,'unauthorized/expired denied before file I/O');}
$request->token='owner';$request->side='back';preview_check(sanro_studio_rest_read_current_preview($request) instanceof WP_Error,'unbound side denied');
$request->side='front';$GLOBALS['record']['preview_design_hash']=str_repeat('c',64);$before=count($GLOBALS['reads']);
preview_check(sanro_studio_rest_read_current_preview($request)->data['status']===409&&count($GLOBALS['reads'])===$before,'stale revision denied before file I/O');
$GLOBALS['record']['preview_design_hash']=str_repeat('a',64);$GLOBALS['corrupt']=true;
preview_check(sanro_studio_rest_read_current_preview($request) instanceof WP_Error,'corrupt/private file reader rejection preserved');
echo "PASS: $checks current preview route assertions\n";
