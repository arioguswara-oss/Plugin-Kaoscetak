<?php
/** Real pricing/cart/checkout/lock modules; in-memory WP/WC and preview I/O boundaries. */
define('ABSPATH',__DIR__);
$private=sys_get_temp_dir().'/sanro-feature-'.bin2hex(random_bytes(8));mkdir($private,0700);
define('SANRO_STUDIO_PRIVATE_PREVIEW_DIR',$private);$_SERVER['DOCUMENT_ROOT']=ABSPATH;
register_shutdown_function(function()use($private){foreach(glob($private.'/*') as $file)unlink($file);rmdir($private);});
$GLOBALS['jpeg']=base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAAQABADASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD3+iiigD//2Q==');define('SANRO_STUDIO_ENABLE_WC_PRICING',true);define('SANRO_STUDIO_ENABLE_PRIVATE_PREVIEWS',true);
foreach(['print-area','product-area','editor-config','pricing','pricing-quote','quote','draft','cart-contract','checkout-bridge','checkout-batch-lock','pricing-woocommerce','pricing-integration','storage-policy','order-preview'] as $module)require __DIR__.'/../includes/'.$module.'.php';
function absint($v){return abs((int)$v);}function maybe_serialize($v){return serialize($v);}function wp_cache_delete(...$args){}
function get_woocommerce_currency(){return 'IDR';}function wp_salt($v){return 'fixture-salt-0123456789';}
function get_post_meta($id,$key,$single=true){return $key==='_sanro_studio_pricing_profile'?'cotton_combed_24s':($key==='_sanro_studio_safe_areas'?['front'=>['width'=>32,'height'=>48],'back'=>['width'=>32,'height'=>48]]:null);}
function get_option($key,$default=false){return $GLOBALS['options'][$key]??$default;}
function sanro_studio_draft_image_binding_allowed($design,$refs,$id){return $refs===[];}
function sanro_studio_draft_rest_enabled(){return true;}function sanro_studio_stage_classic_checkout_ready(){return true;}
function sanro_studio_stage_rest_session_ready(){return true;}function sanro_studio_stage_rest_cart(){return WC()->cart;}
function WC(){return $GLOBALS['wc'];}function wc_get_product($id){return $id===7?new FlowProduct():false;}
function apply_filters($tag,$default,...$args){if($tag==='sanro_studio_pricing_authorized_design')return sanro_studio_pricing_authorized_design($default,...$args);if($tag==='sanro_studio_drive_access_token')return 'synthetic-token-0123456789';return $default;}
function wc_add_notice($message,$type){$GLOBALS['notices'][]=$message;}
function wp_verify_nonce($nonce,$action){return $nonce==='fixture-nonce'&&$action==='sanro_studio_checkout_auth';}
function rest_ensure_response($data){return $data;}function wc_get_cart_url(){return 'https://example.test/cart/';}function nocache_headers(){}
class WP_Error {public function __construct(public $code,public $message,public $data){}}
class FlowProduct {public $price='1';function get_status(){return 'publish';}function is_purchasable(){return true;}function is_type($type){return $type==='simple';}function get_price(){return $this->price;}function set_price($price){$this->price=$price;}}
class FlowSession {function get_customer_id(){return 'fixture-wc-session-123456';}}
class FlowDB {public $options='wp_options';function prepare($sql,...$args){return $args;}function query($args){[$after,$key,$before]=$args;if(serialize($GLOBALS['options'][$key])!==$before)return 0;$GLOBALS['options'][$key]=unserialize($after);return 1;}}
class FlowCart {
 public $cart_contents=[],$adds=0;function get_cart(){return $this->cart_contents;}
 function add_to_cart($id,$qty,$variation,$vars,$meta){if(!sanro_studio_pricing_add_validation(true,$id,$qty,$variation,$vars,$meta))return false;$key='line-'.++$this->adds;$this->cart_contents[$key]=$meta+['product_id'=>$id,'quantity'=>$qty,'variation_id'=>0,'data'=>wc_get_product($id)];return $key;}
 function calculate_totals(){sanro_studio_pricing_totals($this);}function remove_cart_item($key){unset($this->cart_contents[$key]);return true;}
}
class FlowRequest {public $token;function get_param($key){return $GLOBALS['design_id'];}function get_header($key){return $this->token;}}
class FlowItem {function save(){return $this->id;}public $meta=[],$total;function __construct(public $id,public $qty,public $unit){$this->total=$qty*$unit;}function get_id(){return $this->id;}function get_product_id(){return 7;}function get_quantity(){return $this->qty;}function get_subtotal(){return $this->qty*$this->unit;}function get_total(){return $this->total;}function get_meta($key,$single=true){return $this->meta[$key]??'';}function update_meta_data($key,$value){$this->meta[$key]=$value;}}
class FlowOrder {public $items=[];function get_id(){return 88;}function get_items($type='line_item'){return $this->items;}function get_currency(){return 'IDR';}function get_total(){return array_sum(array_map(fn($i)=>$i->total,$this->items));}}
$checks=0;function flow_check($ok,$message){global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
$GLOBALS['wpdb']=new FlowDB();$GLOBALS['wc']=(object)['session'=>new FlowSession(),'cart'=>new FlowCart()];$GLOBALS['notices']=[];
$id=$GLOBALS['design_id']='11111111-1111-4111-8111-111111111111';$token=str_repeat('a',64);
$design=['front'=>['category'=>'A5','objects'=>[['type'=>'text','x'=>1,'y'=>1,'width'=>5,'height'=>4]],'text'=>'SANRO','font_size'=>24,'text_color'=>'#171717'],'back'=>['category'=>'A5','objects'=>[]],'quantities'=>['S'=>2,'L'=>1],'shirt_color'=>'#ffffff','long_sleeve'=>false];
foreach(['front','back'] as $side){$GLOBALS['refs'][$side]=$id.'-'.$side.'-'.hash('sha256',$GLOBALS['jpeg']).'.jpg';file_put_contents($private.'/'.$GLOBALS['refs'][$side],$GLOBALS['jpeg']);}
$key='sanro_studio_draft_'.$id;$GLOBALS['options'][$key]=['token_hash'=>hash('sha256',$token),'product_id'=>7,'design'=>$design,'status'=>'draft','expires_at'=>time()+1000,'preview_refs'=>$GLOBALS['refs'],'preview_design_hash'=>hash('sha256',serialize($design))];
$request=new FlowRequest();$request->token=$token;$cart=WC()->cart;
$result=sanro_studio_rest_pricing_draft_cart($request);flow_check(is_array($result)&&$result['valid'],'real REST/cart/quote add: '.json_encode($result));
flow_check(count($cart->get_cart())===2&&$cart->adds===2,'two size lines only');
$total=0;foreach($cart->get_cart() as $line)$total+=$line['quantity']*$line['data']->get_price();flow_check($total===192000,'official A5 S2 + L1 = IDR192000');
$result=sanro_studio_rest_pricing_draft_cart($request);flow_check(!empty($result['already_in_cart'])&&$cart->adds===2,'replay does not duplicate');
$request->token=str_repeat('b',64);flow_check(sanro_studio_rest_pricing_draft_cart($request) instanceof WP_Error&&$cart->adds===2,'foreign owner denied');$request->token=$token;
file_put_contents($private.'/'.$GLOBALS['refs']['front'],'corrupted');flow_check(sanro_studio_rest_pricing_draft_cart($request) instanceof WP_Error,'unreadable preview denies cart replay');file_put_contents($private.'/'.$GLOBALS['refs']['front'],$GLOBALS['jpeg']);
$cart->cart_contents['line-1']['quantity']='3';$cart->calculate_totals();
$_POST=['sanro_studio_checkout_nonce'=>'fixture-nonce','sanro_studio_checkout_token'=>$token];sanro_studio_pricing_checkout_authorize();
flow_check(isset($GLOBALS['sanro_studio_pricing_checkout'])&&$GLOBALS['notices']===[],'checkout authorizes edited numeric-string quantities');
$order=new FlowOrder();foreach($cart->get_cart() as $cartkey=>$line){$item=new FlowItem(count($order->items)+71,(int)$line['quantity'],(int)$line['data']->get_price());sanro_studio_pricing_order_item($item,$cartkey,$line,$order);$order->items[$item->id]=$item;}
sanro_studio_pricing_validate_order($order,[]);flow_check($order->get_total()===254000,'checkout official S3 + L1 = IDR254000');
$order->items[71]->total=1;try{sanro_studio_pricing_validate_order($order,[]);throw new LogicException('undercharge accepted');}catch(RuntimeException $error){$checks++;}$order->items[71]->total=186000;
sanro_studio_pricing_validate_order($order,[]);sanro_studio_pricing_lock_order($order);$locked=$GLOBALS['options'][$key];
flow_check($locked['status']==='locked'&&$locked['order_id']===88&&$locked['order_item_ids']===[71,72],'exact persisted order allocation locked');
flow_check($locked['design']['quantities']===['S'=>3,'L'=>1],'production snapshot uses checkout quantities');
flow_check($order->items[71]->get_meta('_sanro_studio_preview_front_ref')===$GLOBALS['refs']['front']&&$order->items[72]->get_meta('_sanro_studio_preview_back_ref')===$GLOBALS['refs']['back']&&$locked['preview_design_hash']===hash('sha256',serialize($locked['design'])),'preview binding gets updated quantity-only revision');
foreach($order->items as $item){$snapshot=$item->meta['_sanro_studio_design_snapshot'];flow_check($snapshot['design_hash']===$locked['preview_design_hash'],'every line snapshot matches locked design');flow_check(!str_contains(serialize($item->meta),$token)&&!isset($snapshot['preview_refs']),'no owner token or private refs persisted in design snapshot');}
flow_check(sanro_studio_pricing_authorized_design(null,$id,7,'cart')===null,'locked order cannot be re-priced from live catalog');
echo "PASS: $checks integrated pricing/cart/multi-size/order-lock checks (WP/WC simulated; real private preview files and order binding)\n";
