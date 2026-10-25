<?php
/**
 * Plugin Name: Kaoscetak Lite Designer
 * Description: Editor sablon mobile-first dengan harga size/area, preview ringan, dan pengaturan Google Drive.
 * Version: 0.2.0-dev
 * Author: Kaoscetak
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * Text Domain: kaoscetak-lite-designer
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */
if (!defined('ABSPATH')) exit;
define('KLD_VERSION','0.2.0-dev');
define('KLD_DIR', plugin_dir_path(__FILE__));
define('KLD_URL', plugin_dir_url(__FILE__));
require_once KLD_DIR . 'includes/admin.php';
require_once KLD_DIR . 'includes/drive.php';
require_once KLD_DIR . 'includes/api.php';
require_once KLD_DIR . 'includes/sharing.php';
require_once KLD_DIR . 'includes/repeat.php';
require_once KLD_DIR . 'includes/store.php';

add_action('init', function(){
 register_post_type('kld_design', [
  'label'=>'Kaoscetak Desain', 'public'=>false, 'show_ui'=>false,
  'supports'=>['title'], 'capability_type'=>'post', 'map_meta_cap'=>true,
 ]);
});
add_action('plugins_loaded', function(){ if (!class_exists('WooCommerce')) add_action('admin_notices', function(){echo '<div class="notice notice-error"><p>Kaoscetak Lite Designer membutuhkan WooCommerce aktif.</p></div>';}); });
add_action('wp_enqueue_scripts', function(){
 if (!function_exists('is_product') || !is_product()) return;
 $product=wc_get_product(get_queried_object_id());
 if (!$product || get_post_meta($product->get_id(),'_kld_enabled',true)!=='yes') return;
 $preview = current_user_can('manage_woocommerce');
 $connected=kld_drive_connected();
 $publicReady=$connected && get_option('kld_public_enabled','no')==='yes';
 if (!$publicReady && !$preview) return; // Do not expose an unfinished checkout flow to buyers.
 wp_enqueue_style('kld-editor', KLD_URL.'assets/editor.css', [], KLD_VERSION);
 wp_enqueue_script('kld-editor', KLD_URL.'assets/editor.js', [], KLD_VERSION, true);
 $matrix=kld_product_prices($product->get_id());
 $colors=explode('|',(string)get_post_meta($product->get_id(),'_kld_colors',true));
 $colors=array_values(array_filter(array_map('trim',$colors)));
 wp_localize_script('kld-editor','KLD_CONFIG', [
  'rest'=>esc_url_raw(rest_url('kld/v1/')),
  'nonce'=>wp_create_nonce('wp_rest'),
  'productId'=>$product->get_id(),
  'productName'=>$product->get_name(),
  'matrix'=>$matrix,
  'surcharges'=>kld_surcharges(),
  'tiers'=>kld_tiers(),
  'sizes'=>array_keys($matrix),
  'colors'=>$colors?:['Hitam','Putih','Army Green','Navy'],
  'previewOnly'=>!$connected,
  'cartUrl'=>wc_get_cart_url(),
  'maxBytes'=>10485760,
  'maxPreviewBytes'=>61440,
 ]);
});
add_action('woocommerce_after_add_to_cart_form', function(){
 global $product;
 if (!$product || get_post_meta($product->get_id(),'_kld_enabled',true)!=='yes') return;
 $isPreview=current_user_can('manage_woocommerce');
 if (!(kld_drive_connected() && get_option('kld_public_enabled','no')==='yes') && !$isPreview) return;
 echo '<button type="button" class="button alt kld-open" id="kld-open-designer">Custom Desain Kaos</button><div id="kld-editor-root"></div>';
});

register_activation_hook(__FILE__, function(){
 if(!wp_next_scheduled('kld_cleanup_drafts'))wp_schedule_event(time()+3600,'daily','kld_cleanup_drafts');
});
register_deactivation_hook(__FILE__,function(){wp_clear_scheduled_hook('kld_cleanup_drafts');});
add_action('kld_cleanup_drafts',function(){
 // Only unused, unverified drafts are eligible for cleanup; orders and verified designs are retained.
 $ids=get_posts(['post_type'=>'kld_design','post_status'=>'private','numberposts'=>80,
  'date_query'=>[['before'=>'48 hours ago']], 'fields'=>'ids','orderby'=>'date','order'=>'ASC']);
 foreach($ids as $id){
  $status=get_post_meta($id,'_kld_status',true);
  if(!in_array($status,['','saved_pending_verify'],true))continue;
  // Master Drive files may be shared by forked designs and immutable paid orders.
  // NEVER delete a Drive master during timed draft cleanup: no reference counting
  // or order-retention policy has been approved. Only the draft metadata is removed.
  // Orphan master cleanup requires a separately audited, reference-safe process.
  wp_delete_post($id,true);
 }
});