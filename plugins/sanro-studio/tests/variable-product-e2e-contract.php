<?php
/** Synthetic variable-product contract: no WC database/cart/order/payment writes. */
define('ABSPATH', __DIR__ . '/');
$GLOBALS['vp_variations'] = [
    301 => ['parent'=>30,'attributes'=>['pa_warna'=>'navy','pa_ukuran'=>'s'],'stock'=>true],
    302 => ['parent'=>30,'attributes'=>['pa_warna'=>'navy','pa_ukuran'=>'m'],'stock'=>true],
    303 => ['parent'=>30,'attributes'=>['pa_warna'=>'red','pa_ukuran'=>'s'],'stock'=>false],
    401 => ['parent'=>40,'attributes'=>['pa_warna'=>'navy','pa_ukuran'=>'s'],'stock'=>true],
];
$GLOBALS['vp_rows'] = [
    ['color'=>'#112233','label'=>'Navy','size'=>'S','front'=>1,'back'=>2,'variation_id'=>301],
    ['color'=>'#112233','label'=>'Navy','size'=>'M','front'=>1,'back'=>2,'variation_id'=>302],
    ['color'=>'#ee0000','label'=>'Red','size'=>'S','front'=>1,'back'=>2,'variation_id'=>303],
];
class VpProduct {
    private int $id; private string $type;
    function __construct($id,$type){$this->id=$id;$this->type=$type;}
    function is_type($type){return $this->type===$type;}
    function get_parent_id(){return $GLOBALS['vp_variations'][$this->id]['parent']??0;}
    function get_attributes(){return $GLOBALS['vp_variations'][$this->id]['attributes']??[];}
    function get_status(){return 'publish';}
    function is_purchasable(){return $GLOBALS['vp_variations'][$this->id]['stock']??true;}
    function is_in_stock(){return $GLOBALS['vp_variations'][$this->id]['stock']??true;}
}
function wc_get_product($id){if((int)$id===30)return new VpProduct(30,'variable');if((int)$id===40)return new VpProduct(40,'variable');return isset($GLOBALS['vp_variations'][(int)$id])?new VpProduct((int)$id,'variation'):null;}
function wc_get_product_variation_attributes($id){$attrs=wc_get_product($id)->get_attributes();$out=[];foreach($attrs as $key=>$value)$out['attribute_'.$key]=$value;return $out;}
function get_post_meta($id,$key,$single=true){return $key==='_sanro_studio_mockup_matrix'?$GLOBALS['vp_rows']:'';}
function sanro_studio_valid_mockup_attachment($id){return is_int($id)&&$id>0?$id:0;}
function sanro_studio_product_pricing_config($id){return ['valid'=>true,'profile'=>'cotton_combed_24s','sizes'=>['S','M']];}
function sanro_studio_profile_unit_price($profile,$size,$front,$back,$sleeve=false){return ['valid'=>true,'unit_price'=>$size==='S'?50000:60000,'currency'=>'IDR'];}
function get_woocommerce_currency(){return 'IDR';}
function sanro_studio_public_editor_config($id,$allow=false){return ['valid'=>true,'pricing_profile'=>'cotton_combed_24s','categories'=>['A4'=>[21,29.7]],'safe_areas'=>['front'=>['width'=>30,'height'=>40],'back'=>['width'=>30,'height'=>40]],'palette'=>['#112233'=>'Navy'],'print_zones'=>[]];}
function sanro_studio_validate_product_design($id,$design){return ['valid'=>true,'sides'=>['front'=>['empty'=>false,'category'=>'A4'],'back'=>['empty'=>true,'category'=>null]]];}
function sanitize_title($value){return strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',(string)$value),'-'));}
function sanitize_text_field($value){return trim((string)$value);}
function sanro_studio_read_draft($id,$token){return $token==='owner'?['valid'=>true,'status'=>'draft','design_id'=>$id,'product_id'=>30,'design'=>$GLOBALS['vp_design'],'master_refs'=>[]]:['valid'=>false,'error'=>'not_found'];}
function sanro_studio_draft_image_binding_allowed($design,$refs,$id){return true;}
function vp_check($condition,$name){if(!$condition){fwrite(STDERR,"FAIL: $name\n");exit(1);}echo "PASS: $name\n";}
class VpOrderItem {
    public $variation;public $meta=[];
    function __construct($variation){$this->variation=$variation;}
    function get_product_id(){return 30;}function get_variation_id(){return $this->variation;}
    function get_quantity(){return 2;}function get_subtotal(){return '100000';}function get_total(){return '100000';}
    function get_meta($key,$single=true){return $this->meta[$key]??'';}function update_meta_data($key,$value){$this->meta[$key]=$value;}
}
require dirname(__DIR__).'/includes/mockup-mapping.php';
require dirname(__DIR__).'/includes/pricing-quote.php';
require dirname(__DIR__).'/includes/quote.php';
require dirname(__DIR__).'/includes/cart-contract.php';
require dirname(__DIR__).'/includes/checkout-bridge.php';
$map=sanro_studio_variation_map(30);
vp_check(is_array($map)&&($map['#112233']['S']['variation_id']??null)===301&&($map['#112233']['M']['variation_id']??null)===302,'admin map resolves pa_warna/pa_ukuran parent variations by explicit variation ID');
vp_check(($map['#ee0000']['S']['available']??true)===false,'out-of-stock mapped option remains visible as unavailable');
vp_check(sanro_studio_variation_for_mapping(30,401,'Navy','S')===false,'variation from another parent rejected');
$GLOBALS['vp_design']=['shirt_color'=>'#112233','quantities'=>['S'=>2,'M'=>1],'long_sleeve'=>false,'front'=>['category'=>'A4'],'back'=>['category'=>'A5']];
$quote=sanro_studio_product_quote(30,$GLOBALS['vp_design']);
vp_check(!empty($quote['valid'])&&$quote['lines']['S']['variation_id']===301&&$quote['lines']['M']['variation_id']===302,'server quote binds each multi-size line to mapped variation');
$draft_id='00000000-0000-4000-8000-000000000030';
$contract=sanro_studio_prepare_cart_design_contract($draft_id,'owner',30);
$lines=sanro_studio_prepare_size_cart_lines($contract);
vp_check(!empty($lines['valid'])&&count($lines['lines'])===2&&$lines['lines'][0]['variation_id']===301,'cart candidates preserve variation IDs and size allocation');
$tampered=$lines['lines'];$tampered[0]['variation_id']=401;
vp_check(sanro_studio_validate_size_cart_lines($contract,$tampered)['valid']===false,'wrong-parent variation substitution rejected in cart');
$tampered=$lines['lines'];$tampered[0]['variation']['attribute_pa_size']='m';
vp_check(sanro_studio_validate_size_cart_lines($contract,$tampered)['valid']===false,'variation attribute mismatch rejected in cart');
$orderItem=new VpOrderItem(301);$bound=sanro_studio_prepare_size_checkout_order_item($lines['lines'][0],$orderItem);
vp_check(!empty($bound['valid'])&&$orderItem->meta['_sanro_studio_variation_id']===301&&$orderItem->meta['_sanro_studio_color']==='#112233','order line records server-authorized variation and color metadata');
$wrongOrderItem=new VpOrderItem(401);
vp_check(sanro_studio_validate_size_order_item($lines['lines'][0],$wrongOrderItem)['valid']===false,'wrong-parent variation rejected on order line');
$GLOBALS['vp_design']['shirt_color']='#ee0000';$GLOBALS['vp_design']['quantities']=['S'=>1,'M'=>0];
vp_check(sanro_studio_product_quote(30,$GLOBALS['vp_design'])['error']==='variation_unavailable','out-of-stock variation rejected by quote');
echo "PASS: 10 variable-product contract checks\n";
