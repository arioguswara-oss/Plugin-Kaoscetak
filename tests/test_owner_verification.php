<?php
/** Guest email ownership flows; isolated from WP transport, not a live SMTP test. */
define('ABSPATH', __DIR__);define('HOUR_IN_SECONDS',3600);
$GLOBALS['meta']=[42=>['_kld_edit_hash'=>hash('sha256',str_repeat('c',64))]];
$GLOBALS['types']=[42=>'kld_design'];$GLOBALS['mail']=[];$GLOBALS['transient']=[];
function add_action(){} function get_post_type($id){return $GLOBALS['types'][$id]??false;}
function get_post_meta($id,$k,$single=true){return $GLOBALS['meta'][$id][$k]??'';}
function update_post_meta($id,$k,$v){$GLOBALS['meta'][$id][$k]=$v;}
function delete_post_meta($id,$k){unset($GLOBALS['meta'][$id][$k]);}
function absint($n){return abs((int)$n);} function sanitize_email($s){return filter_var($s,FILTER_VALIDATE_EMAIL)?$s:'';}
function is_email($s){return !!filter_var($s,FILTER_VALIDATE_EMAIL);} function wp_salt($s){return 'unit-test-secret-'.$s;}
function get_transient($k){return $GLOBALS['transient'][$k]??false;}function set_transient($k,$v,$ttl){$GLOBALS['transient'][$k]=$v;}
function home_url($s){return 'https://kaoscetak.example'.$s;}function add_query_arg($a,$url){return $url.'?'.http_build_query($a);}
function wp_mail($email,$subject,$body){$GLOBALS['mail'][]=compact('email','subject','body');return true;}
function rest_ensure_response($val){return $val;}function is_wp_error($err){return $err instanceof WP_Error;}
class WP_Error{private $code;private $msg;private $extra;function __construct($code,$msg,$extra=[]){$this->code=$code;$this->msg=$msg;$this->extra=$extra;}function get_error_code(){return $this->code;}function get_error_message(){return $this->msg;}}
class WP_REST_Request implements ArrayAccess{
 private $data=[];private $headers=[];function __construct($data,$headers){$this->data=$data;$this->headers=$headers;}
 function offsetExists($o):bool{return isset($this->data[$o]);}function offsetGet($o):mixed{return $this->data[$o]??null;}
 function offsetSet($o,$v):void{$this->data[$o]=$v;}function offsetUnset($o):void{unset($this->data[$o]);}
 function get_param($k){return $this->data[$k]??null;}function get_header($k){return $this->headers[$k]??'';}
}
require __DIR__.'/../plugins/kaoscetak-lite-designer/includes/api.php';
require __DIR__.'/../plugins/kaoscetak-lite-designer/includes/sharing.php';
function check($ok,$msg){if(!$ok)throw new RuntimeException('FAIL '.$msg);}
$edit=str_repeat('c',64);$a=new WP_REST_Request(['design_id'=>42,'email'=>'owner@example.com'],['X-KLD-Token'=>$edit]);
$result=kld_api_request_verify($a);check(!is_wp_error($result)&&($result['sent']??false),'owner verification mail sent');
check(count($GLOBALS['mail'])===1,'one email sent');check(strpos($GLOBALS['mail'][0]['body'],'JANGAN BAGIKAN')!==false,'private token warning');
preg_match('/key=([a-f0-9]{64})/',$GLOBALS['mail'][0]['body'],$m);
check(!empty($m[1]),'email contains management URL');
check(kld_verify_owner_token(42,$m[1]),'visiting magic link establishes ownership');
$hijack=new WP_REST_Request(['design_id'=>42,'email'=>'attacker@example.com'],['X-KLD-Token'=>$edit]);
$r=kld_api_request_verify($hijack);check(is_wp_error($r)&&$r->get_error_code()==='owner_email','reassignment to attacker rejected');
check(count($GLOBALS['mail'])===1,'no email sent to attacker');
$r=kld_api_request_verify($a);check(!is_wp_error($r),'verified owner can recover management link');
check(count($GLOBALS['mail'])===2,'owner gets recovery email');
check($GLOBALS['mail'][1]['email']==='owner@example.com','recovery only to owner');
check(get_post_meta(42,'_kld_owner_email',true)==='owner@example.com','owner unchanged');
echo "PASS: 11 email verification / ownership assertions\n";