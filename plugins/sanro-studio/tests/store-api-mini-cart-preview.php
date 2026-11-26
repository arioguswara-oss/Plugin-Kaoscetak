<?php
define('ABSPATH', __DIR__ . '/');
function require_once_stub() { }
function wp_salt($scheme) { return 'sanro-test-cart-signing-key-at-least-32-bytes'; }
function maybe_serialize($value) { return serialize($value); }
function get_option($key, $default = false) { return $GLOBALS['sanro_preview_records'][$key] ?? $default; }
function admin_url($path = '', $scheme = null) { return 'https://stage.example/wp-admin/' . $path; }
function add_query_arg($args, $url) { return $url . '?' . http_build_query($args); }
function wp_parse_url($url, $component) { return parse_url($url, $component); }
function sanro_studio_read_verified_preview_file($id, $side, $ref) { return ['valid'=>true,'mime'=>'image/png','bytes'=>'preview']; }
function add_action($hook, $callback, $priority = 10, $args = 1) { }
function add_filter($hook, $callback, $priority = 10, $args = 1) { }
class SanroStoreApiSession { function get_customer_id() { return $GLOBALS['sanro_customer_id']??'abcdefghijklmnop1234'; } }
class SanroStoreApiWoo { public $session; function __construct(){ $this->session=new SanroStoreApiSession(); } }
function WC() { return new SanroStoreApiWoo(); }
require __DIR__ . '/../includes/pricing-integration.php';
$id='4233e0e2-7c89-408f-b36b-fb76ce5adbc2';
$design=['front'=>['objects'=>[['type'=>'text']]],'back'=>['objects'=>[]]];
$GLOBALS['sanro_preview_records']=['sanro_studio_draft_'.$id=>['status'=>'draft','product_id'=>42,'expires_at'=>time()+600,'wc_session_hash'=>hash_hmac('sha256','abcdefghijklmnop1234',wp_salt('auth')),'design'=>$design,'preview_design_hash'=>hash('sha256',maybe_serialize($design)),'preview_refs'=>['front'=>'private-ref']]];
$line=['sanro_studio_pricing_version'=>1,'sanro_studio_design_id'=>$id,'product_id'=>42];
$images=[(object)['id'=>77,'src'=>'https://stage.example/catalog.jpg','thumbnail'=>'https://stage.example/catalog-thumb.jpg','alt'=>'Catalog']];
$result=sanro_studio_pricing_store_api_cart_images($images,$line,'key');
if(count($result)!==1||$result[0]->id!==77||$result[0]->alt!=='Preview desain'||strpos($result[0]->src,'action=sanro_studio_cart_preview')===false)throw new RuntimeException('Store API preview image not applied');
$GLOBALS['sanro_customer_id']='another-guest-session-123456';
if(sanro_studio_pricing_store_api_cart_images($images,$line,'key')!==$images)throw new RuntimeException('cross-session cart preview exposed');
$GLOBALS['sanro_customer_id']='abcdefghijklmnop1234';
$foreign=$line;$foreign['sanro_studio_design_id']='4233e0e2-7c89-408f-b36b-fb76ce5adbc3';
if(sanro_studio_pricing_store_api_cart_images($images,$foreign,'key')!==$images)throw new RuntimeException('non-session cart design changed');
echo "PASS: Store API Mini Cart receives only the current session-bound preview image\n";
