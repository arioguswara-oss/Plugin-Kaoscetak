<?php
// The quote suite supplies synthetic product metadata and real quote/price functions.
require __DIR__ . '/studio-pricing-quote-offline.php';
define('SANRO_STUDIO_ENABLE_WC_PRICING', true);
$GLOBALS['resolver_enabled'] = true;
$GLOBALS['master_ready'] = true;
$GLOBALS['resolved_design'] = design();
$GLOBALS['resolved_design']['long_sleeve'] = true;
$GLOBALS['notices'] = [];
$GLOBALS['hooks'] = [];
function apply_filters($hook, $default, $id, $product_id, $context) {
    if (!$GLOBALS['resolver_enabled']) return null;
    return ['authorized'=>true,'master_ready'=>$GLOBALS['master_ready'],'design_id'=>$id,
        'product_id'=>$product_id,'design'=>$GLOBALS['resolved_design']];
}
function add_filter($hook, $callback, $priority = 10, $args = 1) { $GLOBALS['hooks'][$hook] = [$callback,$priority,$args]; }
function add_action($hook, $callback, $priority = 10, $args = 1) { add_filter($hook,$callback,$priority,$args); }
function WC() { return (object) ['cart'=>$GLOBALS['cart']]; }
function wc_add_notice($message, $type) { $GLOBALS['notices'][] = [$message,$type]; }
class PricingProduct {
    public $price = '1';
    public function set_price($price) { $this->price = $price; }
    public function get_price() { return $this->price; }
}
class PricingCart {
    public $cart_contents = [];
    public function get_cart() { return $this->cart_contents; }
}
require dirname(__DIR__) . '/plugins/sanro-studio/includes/pricing-woocommerce.php';
function pricing_line($size = 'L', $quantity = 1) {
    return ['product_id'=>7,'variation_id'=>0,'quantity'=>$quantity,'sanro_studio_pricing_version'=>1,
        'sanro_studio_design_id'=>'12345678-1234-4123-8123-123456789012','sanro_studio_size'=>$size,
        'sanro_studio_unit_price'=>1,'sanro_studio_line_subtotal'=>1,'data'=>new PricingProduct()];
}

sanro_studio_register_pricing_wc_hooks();
function esc_html($s){return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
$callback=$GLOBALS['hooks']['woocommerce_before_calculate_totals'][0];
if($callback!=='sanro_studio_pricing_calculate_totals')throw new RuntimeException('unsafe throwing totals hook remains');
$cart=new PricingCart();$product=new PricingProduct();$product->price='62000';$line=pricing_line('S',1);$line['data']=$product;$cart->cart_contents=['stale'=>$line];$GLOBALS['cart']=$cart;
$GLOBALS['resolver_enabled']=false;$GLOBALS['notices']=[];
$callback($cart);
if(count($GLOBALS['notices'])!==1||$GLOBALS['notices'][0][1]!=='error'||$cart->cart_contents['stale']['data']!==$product||$product->price!=='62000')throw new RuntimeException('stale cart failed to render safely or price mutated');
if(sanro_studio_pricing_display_line('Rp65.000',$line)!=='Desain perlu diverifikasi'||strpos(sanro_studio_pricing_display_total('Rp195.000'),'Rp')!==false||sanro_studio_pricing_display_line('Rp65.000',['product_id'=>9])!=='Rp65.000')throw new RuntimeException('rejected cart misleading price display');
$GLOBALS['notices']=[];sanro_studio_pricing_checkout();if(count($GLOBALS['notices'])!==1)throw new RuntimeException('stale cart checkout allowed');
$GLOBALS['resolver_enabled']=true;$GLOBALS['master_ready']=true;$GLOBALS['notices']=[];$callback($cart);if($GLOBALS['notices']||$cart->cart_contents['stale']['data']===$product)throw new RuntimeException('valid cart repricing broken');
echo "PASS: actual totals hook handles stale resolver without HTTP500; product untouched; independent checkout still blocked; valid repricing retained\n";
