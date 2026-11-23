<?php
/** Isolated real WordPress+WooCommerce activation checks; not a live checkout test. */
function kld_ci_assert($condition,$message){
 if(!$condition)throw new RuntimeException('KLD WP smoke FAIL: '.$message);
}
kld_ci_assert(class_exists('WooCommerce'),'WooCommerce loaded');
kld_ci_assert(is_plugin_active('kaoscetak-lite-designer/kaoscetak-lite-designer.php'),'Kaoscetak plugin active');
kld_ci_assert(post_type_exists('kld_design'),'private kld_design post type registered');
kld_ci_assert(function_exists('kld_unit_price'),'pricing function loaded');
kld_ci_assert(function_exists('kld_drive_connected'),'Drive integration function loaded');
kld_ci_assert(has_action('woocommerce_after_add_to_cart_form')!==false,'product editor hook registered');
kld_ci_assert(get_option('kld_public_enabled','no')!=='yes','public designer remains disabled by default');
$product=new WC_Product_Simple();
$product->set_name('Cotton Combed 24s CI Test');
$product->set_regular_price(62000);
$product->set_status('publish');
$product->save();
$id=$product->get_id();
kld_ci_assert($id>0,'WooCommerce test product created');
update_post_meta($id,'_kld_enabled','yes');
update_post_meta($id,'_kld_price_preset','reguler-24s');
kld_ci_assert(kld_unit_price($id,'S','A5','none')===62000,'official S A5 price');
kld_ci_assert(kld_unit_price($id,'L','A5','A3')===103000,'front A5 back A3');
kld_ci_assert(kld_unit_price($id,'L','A3','A5')===103000,'front/back symmetric pricing');
wp_set_current_user(1);
$GLOBALS['product']=wc_get_product($id);
ob_start();
do_action('woocommerce_after_add_to_cart_form');
$button=ob_get_clean();
kld_ci_assert(strpos($button,'id="kld-open-designer"')!==false,'actual Custom Desain button renders on product hook in admin preview');
echo "PASS: plugin ZIP installed & activated under WordPress+WooCommerce, private CPT, price, admin product button; no real checkout/Drive\n";
