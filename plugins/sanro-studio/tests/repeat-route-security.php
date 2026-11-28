<?php
/** New endpoint boundary only; no mail, database, site or old suite calls. */
define('ABSPATH',__DIR__);
function sanro_studio_frontend_enabled(){return true;}
function register_rest_route($namespace,$route,$options){$GLOBALS['routes'][$route]=$options;}
function wp_verify_nonce($nonce,$action){return $nonce==='fixture'&&$action==='sanro_studio_draft';}
function is_email($value){return filter_var($value,FILTER_VALIDATE_EMAIL)!==false;}
function wp_mail(...$args){throw new RuntimeException('unauthorized mail');}
class WP_Error {public $code,$message,$data;function __construct($c,$m,$d){$this->code=$c;$this->message=$m;$this->data=$d;}}
require __DIR__.'/../includes/draft.php';require __DIR__.'/../includes/draft-rest.php';require __DIR__.'/../includes/pricing-woocommerce.php';require __DIR__.'/../includes/repeat-discount.php';
$checks=0;function route_check($v,$label){global $checks;$checks++;if(!$v)throw new RuntimeException('FAIL '.$label);}
$GLOBALS['routes']=[];sanro_studio_repeat_register_routes();route_check($GLOBALS['routes']===[],'no email API at default OFF');
define('SANRO_STUDIO_ENABLE_DRAFT_API',true);sanro_studio_repeat_register_routes();route_check(count($GLOBALS['routes'])===2,'verified collection identity available with draft opt-in without WC cart writer');
define('SANRO_STUDIO_ENABLE_WC_PRICING',true);sanro_studio_repeat_register_routes();route_check(count($GLOBALS['routes'])===2,'two explicitly gated POST routes');
$request=new class {public $nonce='bad';function get_header($name){return $name==='X-Sanro-Draft-Nonce'?$this->nonce:'forged';}function get_param($name){return ['id'=>'123e4567-e89b-42d3-a456-426614174000','email'=>'repeat@example.test','code'=>str_repeat('a',64)][$name]??null;}};
foreach($GLOBALS['routes'] as $route){route_check($route['methods']==='POST'&&$route['permission_callback']==='sanro_studio_rest_draft_nonce','not a public or GET verification route');$request->nonce='bad';route_check(call_user_func($route['permission_callback'],$request) instanceof WP_Error,'bad nonce denied');$request->nonce='fixture';route_check(call_user_func($route['permission_callback'],$request)===true,'valid nonce is only CSRF permission');route_check(call_user_func($route['callback'],$request) instanceof WP_Error,'valid nonce still cannot bypass independent owner token');}
echo "PASS: $checks verification route/nonce/default-OFF checks\n";
