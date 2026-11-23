<?php
define('ABSPATH', __DIR__);
$GLOBALS['record'] = [];
class StudioPricingSession { public $id='wc-session-test-0000000001'; function get_customer_id(){return $this->id;} }
function WC(){return (object)['session'=>$GLOBALS['wc_session']];}
function wp_salt($scheme='auth'){return 'offline-test-salt-0123456789';}
function sanro_studio_pricing_wc_enabled(){return true;}
function get_option($key,$default=false){return $key==='sanro_studio_draft_design'?$GLOBALS['record']:$default;}
function sanro_studio_validate_product_design($product,$design){return ['valid'=>$product===12 && is_array($design)];}
function sanro_studio_draft_image_binding_allowed($design,$refs,$id){return is_array($refs) && $refs===[] && $id==='design';}
require dirname(__DIR__).'/includes/pricing-integration.php';
function check_integration($ok,$label){if(!$ok){fwrite(STDERR,"FAIL: $label\n");exit(1);}echo "PASS: $label\n";}
$GLOBALS['wc_session']=new StudioPricingSession();
$session=hash_hmac('sha256',$GLOBALS['wc_session']->id,wp_salt('auth'));
$GLOBALS['record']=['status'=>'draft','product_id'=>12,'expires_at'=>time()+300,'wc_session_hash'=>$session,
    'design'=>['front'=>['objects'=>[]]],'master_refs'=>[]];
$resolved=sanro_studio_pricing_authorized_design(null,'design',12,'cart');
check_integration(is_array($resolved)&&$resolved['authorized']===true&&$resolved['master_ready']===true,'session-bound draft resolves for cart');
check_integration(sanro_studio_pricing_authorized_design(null,'other',12,'cart')===null,'unknown design denied');
check_integration(sanro_studio_pricing_authorized_design(null,'design',13,'cart')===null,'product substitution denied');
check_integration(sanro_studio_pricing_authorized_design(null,'design',12,'editor')===null,'untrusted context denied');
$GLOBALS['wc_session']->id='different-session-id-0000000001';
check_integration(sanro_studio_pricing_authorized_design(null,'design',12,'cart')===null,'foreign WC session denied');
$GLOBALS['wc_session']->id='wc-session-test-0000000001';
$GLOBALS['record']['status']='locked';
check_integration(sanro_studio_pricing_authorized_design(null,'design',12,'cart')===null,'locked draft cannot be repriced');
$GLOBALS['record']['status']='draft';$GLOBALS['record']['expires_at']=time()-1;
check_integration(sanro_studio_pricing_authorized_design(null,'design',12,'cart')===null,'expired draft denied');
$GLOBALS['record']['expires_at']=time()+300;$GLOBALS['record']['design']['front']['objects']=[['type'=>'image']];
check_integration(sanro_studio_pricing_authorized_design(null,'design',12,'cart')===null,'missing private master denies image quote');
echo "PASS: 8 SANRO session-authorized pricing resolver assertions\n";
