<?php
// The quote suite supplies synthetic product metadata and real quote/price functions.
require __DIR__ . '/studio-pricing-quote-offline.php';
define('SANRO_STUDIO_ENABLE_WC_PRICING', true);
$GLOBALS['resolver_enabled'] = true;
$GLOBALS['master_ready'] = true;
$GLOBALS['resolved_design'] = design();
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
$before = $checks;
sanro_studio_register_pricing_wc_hooks();
check_quote(count($GLOBALS['hooks']) === 5, 'register native WC lifecycle hooks');
check_quote($GLOBALS['hooks']['woocommerce_add_to_cart_validation'][2] === 6, 'receive native cart data');
$cart = $GLOBALS['cart'] = new PricingCart();
$cart->cart_contents = ['L'=>pricing_line(), 'ordinary'=>['product_id'=>9,'quantity'=>1,'data'=>new PricingProduct()]];
$cart->cart_contents['ordinary']['data'] = $cart->cart_contents['L']['data'];
$quote = sanro_studio_pricing_wc_quote(pricing_line(), 1, 'editor');
check_quote($quote['unit_price'] === 103000, 'editor price comes from the selected product');
check_quote(sanro_studio_pricing_add_validation(true,7,2,0,[],pricing_line()), 'native add validation');
sanro_studio_pricing_totals($cart);
check_quote($cart->cart_contents['L']['data']->get_price() === '103000', 'totals ignores client unit price');
check_quote($cart->cart_contents['ordinary']['data']->get_price() === '1', 'shared ordinary object remains unchanged');
sanro_studio_pricing_totals($cart);
check_quote($cart->cart_contents['L']['data']->get_price() === '103000', 'repeated totals keep the same unit price');
check_quote(sanro_studio_pricing_update_validation(true,'L',$cart->cart_contents['L'],'3'), 'quantity update permitted');
$cart->cart_contents['L']['quantity'] = 3;
$plan = sanro_studio_pricing_cart_plan($cart);
check_quote($plan['L']['subtotal'] === 309000, 'changed quantity uses fresh unit price');
sanro_studio_pricing_checkout();
check_quote(!$GLOBALS['notices'], 'checkout fresh authorization passes');
check_quote(sanro_studio_pricing_update_validation(true,'L',$cart->cart_contents['L'],0), 'native remove permitted');
foreach ([1.5,-1,1000,'bad',true] as $quantity) {
    check_quote(!sanro_studio_pricing_update_validation(true,'L',$cart->cart_contents['L'],$quantity), 'invalid update rejected');
}
check_quote(!sanro_studio_pricing_add_validation(false,7,1,0,[],pricing_line()), 'earlier stock validation failure preserved');
check_quote(!sanro_studio_pricing_add_validation(true,7,1,4,[],pricing_line()), 'unsupported variation fails closed');
$cart->cart_contents['L']['quantity'] = 999;
check_quote(!sanro_studio_pricing_add_validation(true,7,1,0,[],pricing_line()), 'merged size maximum enforced');
$cart->cart_contents['L']['quantity'] = 3;
$GLOBALS['resolver_enabled'] = false;
check_quote(!sanro_studio_pricing_add_validation(true,7,1,0,[],pricing_line()), 'missing owner resolver rejects add');
$original = $cart->cart_contents['L']['data']->get_price();
try { sanro_studio_pricing_totals($cart); check_quote(false, 'totals must abort'); }
catch (RuntimeException $e) { check_quote($cart->cart_contents['L']['data']->get_price() === $original, 'invalid plan leaves cart intact'); }
$GLOBALS['notices'] = [];
sanro_studio_pricing_checkout();
check_quote(count($GLOBALS['notices']) === 1, 'expired authorization blocks checkout');
$GLOBALS['resolver_enabled'] = true; $GLOBALS['master_ready'] = false;
check_quote(!sanro_studio_pricing_add_validation(true,7,1,0,[],pricing_line()), 'unready master blocks add');
$GLOBALS['master_ready'] = true;
$GLOBALS['currency'] = 'USD';
check_quote(!sanro_studio_pricing_add_validation(true,7,1,0,[],pricing_line()), 'currency switch blocks add');
$GLOBALS['currency'] = 'IDR';
$GLOBALS['areas']['front']['width'] = 5;
check_quote(!sanro_studio_pricing_add_validation(true,7,1,0,[],pricing_line()), 'fresh safe area change blocks stale draft');
$GLOBALS['areas']['front']['width'] = 32;
$GLOBALS['resolved_design']['front'] = side('A4');
sanro_studio_pricing_totals($cart);
check_quote($cart->cart_contents['L']['data']->get_price() === '93000', 'current draft category recalculated at totals');
$cart->cart_contents = [];
sanro_studio_pricing_totals($cart);
check_quote(sanro_studio_pricing_cart_plan($cart) === [], 'empty cart');
$cart->cart_contents['ordinary'] = ['product_id'=>9,'quantity'=>1,'data'=>new PricingProduct()];
sanro_studio_pricing_totals($cart);
check_quote($cart->cart_contents['ordinary']['data']->get_price() === '1', 'ordinary carts untouched');
// Full allocation cap across sizes, not just individual requested lines.
foreach (['S','M','L','XL','2XL','3XL'] as $size) $cart->cart_contents[$size] = pricing_line($size,999);
try { sanro_studio_pricing_cart_plan($cart); check_quote(false,'aggregate must fail'); }
catch (RuntimeException $e) { check_quote(true, 'aggregate allocation blocked'); }
check_quote(sanro_studio_pricing_quantity('2') === 2 && sanro_studio_pricing_quantity(2.0) === 2, 'native integral quantity normalization');
// Conflicting QA writer disables this registrar without editing the restore branch.
define('SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK', true);
check_quote(!sanro_studio_pricing_wc_enabled(), 'competing QA adapter rejected');
echo 'PASS: ' . ($checks-$before) . " WooCommerce pricing adapter assertions\n";
