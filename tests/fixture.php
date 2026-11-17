<?php
if(!defined('KLD_LOCAL_FIXTURE')||!KLD_LOCAL_FIXTURE||wp_parse_url(home_url(),PHP_URL_HOST)!=='127.0.0.1')throw new RuntimeException('Local fixture only');
foreach(['Reguler 24s'=>'reguler-24s','Lengan Panjang 24s'=>'lengan-panjang-24s','Tunik'=>'tunik-oversize34','Mug 11 oz'=>'mug-11oz'] as $name=>$preset){
 $id=wc_get_product_id_by_sku('KLD-'.$preset);$p=$id?wc_get_product($id):new WC_Product_Simple();
 $p->set_name('Fixture '.$name);$p->set_sku('KLD-'.$preset);$p->set_status('publish');$p->set_regular_price($preset==='mug-11oz'?25000:62000);$p->save();
 update_post_meta($p->get_id(),'_kld_enabled','yes');update_post_meta($p->get_id(),'_kld_price_preset',$preset);
}
$existing=get_page_by_path('cart');$cart=$existing?$existing->ID:wp_insert_post(['post_title'=>'Fixture Cart','post_name'=>'cart','post_status'=>'publish','post_type'=>'page','post_content'=>'[woocommerce_cart]']);update_option('woocommerce_cart_page_id',$cart);
$existing=get_page_by_path('checkout');$checkout=$existing?$existing->ID:wp_insert_post(['post_title'=>'Fixture Checkout','post_name'=>'checkout','post_status'=>'publish','post_type'=>'page','post_content'=>'[woocommerce_checkout]']);update_option('woocommerce_checkout_page_id',$checkout);
update_option('kld_refresh_token',kld_encrypt_secret('fixture-not-a-real-google-token'));update_option('kld_public_enabled','yes');
update_option('kld_master_folder','fixture-master-folder');update_option('kld_orders_folder','fixture-orders-folder');
if(!is_dir(WPMU_PLUGIN_DIR))mkdir(WPMU_PLUGIN_DIR,0755,true);copy('/fixtures/tests/drive-fixture.php',WPMU_PLUGIN_DIR.'/kld-drive-fixture.php');
echo "PASS: synthetic catalogue and loopback-only fake Drive transport installed\n";
// Shipping/payment configuration is local synthetic fixture data only.
$zone=new WC_Shipping_Zone(0);$methods=$zone->get_shipping_methods();if(!$methods){$instance=$zone->add_shipping_method('flat_rate');update_option('woocommerce_flat_rate_'.$instance.'_settings',['enabled'=>'yes','title'=>'Synthetic shipping','cost'=>'10000']);}
update_option('woocommerce_cod_settings',['enabled'=>'yes','title'=>'Synthetic COD — no real payment','description'=>'Local fixture only','enable_for_virtual'=>'yes']);

$terms=get_page_by_path('fixture-terms');$terms=$terms?$terms->ID:wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'Synthetic Terms','post_name'=>'fixture-terms','post_content'=>'Synthetic fixture terms only.']);update_option('woocommerce_terms_page_id',$terms);

update_option('woocommerce_coming_soon','no');update_option('woocommerce_store_pages_only','no');
