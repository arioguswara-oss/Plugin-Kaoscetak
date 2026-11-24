<?php
defined('ABSPATH') || exit;
/** Original SANRO interface primitives; no vendor assets or business rules. */
function sanro_studio_ui_icon($name){
    $paths=['cart'=>'M3 3h2l3 12h10l3-9H6M9 21h.01M18 21h.01','image'=>'M3 3h18v18H3zM3 16l6-6 4 4 3-3 5 5M16 7h.01','text'=>'M4 4h16M12 4v16M8 20h8','shirt'=>'M8 3l4 2 4-2 6 5-4 4-2-2v11H8V10l-2 2-4-4z','folder'=>'M3 6h7l2 2h9v13H3z','arrow'=>'M19 12H5m6-6-6 6 6 6','center'=>'M12 2v4m0 12v4M2 12h4m12 0h4M6 6h12v12H6z','trash'=>'M4 6h16M9 6V3h6v3M6 6l1 15h10l1-15M10 10v7m4-7v7','resize'=>'M4 10V4h6m4 0h6v6M4 14v6h6m4 0h6v-6M4 4l5 5m11-5-5 5M4 20l5-5m11 5-5-5','lock'=>'M6 10h12v11H6zM8 10V7a4 4 0 018 0v3','mountain'=>'M2 20L9 5l6 10 3-5 4 10M7 9l3 3 2-2','home'=>'M3 11l9-8 9 8M5 10v11h5v-7h4v7h5V10','settings'=>'M4 7h16M4 17h16M8 4v6m8 4v6','price'=>'M3 3h10l8 8-10 10-8-8zM8 8h.01','cloud'=>'M6 19a5 5 0 010-10 6 6 0 0112-1 5 5 0 010 11z','orders'=>'M5 4h14v18H5zM9 2h6v4H9zM8 10h8m-8 4h8m-8 4h5'];
    return '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'.($paths[$name]??$paths['settings']).'"/></svg>';
}
function sanro_studio_ui_header($label,$back=''){
    $cart=function_exists('wc_get_cart_url')?wc_get_cart_url():'#';$count=function_exists('WC')&&is_object(WC()->cart)&&method_exists(WC()->cart,'get_cart_contents_count')?WC()->cart->get_cart_contents_count():0;
    echo '<header class="sanro-appbar">'.($back?'<a class="sanro-back" aria-label="Kembali pilih produk" href="'.esc_url($back).'">'.sanro_studio_ui_icon('arrow').'</a>':'').'<h1>'.esc_html($label).'</h1><a class="sanro-cart-link" aria-label="Keranjang" href="'.esc_url($cart).'">'.sanro_studio_ui_icon('cart').($count?'<span>'.(int)$count.'</span>':'').'</a></header>';
}
function sanro_studio_ui_theme_scope($classes){$classes[]='sanro-studio-page';return $classes;}
function sanro_studio_ui_admin_links(){return ['summary'=>['Ringkasan','sanro-studio','home'],'settings'=>['Pengaturan','sanro-studio-settings','settings'],'products'=>['Produk & Mockup','sanro-studio-products','shirt'],'prices'=>['Harga','sanro-studio-prices','price'],'storage'=>['Penyimpanan','sanro-studio-storage','cloud'],'orders'=>['Pesanan','sanro-studio-orders','orders']];}
function sanro_studio_ui_admin_begin($active){
    echo '<div class="sanro-admin-shell"><aside class="sanro-admin-nav"><a class="sanro-admin-brand" href="'.esc_url(admin_url('admin.php?page=sanro-studio')).'">'.sanro_studio_ui_icon('mountain').'<strong>SANRO<small>Studio</small></strong></a><nav aria-label="Navigasi SANRO Studio">';
    foreach(sanro_studio_ui_admin_links() as $key=>$entry)echo '<a '.($key===$active?'aria-current="page"':'').' href="'.esc_url(admin_url('admin.php?page='.$entry[1])).'">'.sanro_studio_ui_icon($entry[2]).'<span>'.esc_html($entry[0]).'</span></a>';
    echo '</nav><a class="sanro-admin-exit" href="'.esc_url(admin_url()).'">← WordPress</a></aside><main class="sanro-admin-main">';
}
function sanro_studio_ui_admin_end(){echo '</main></div>';}
function sanro_studio_ui_summary(){
    if(!current_user_can('manage_woocommerce')&&!current_user_can('manage_options'))wp_die('Akses ditolak.','',['response'=>403]);
    sanro_studio_ui_admin_begin('summary');echo '<p class="sanro-eyebrow">RUANG KERJA ANDA</p><h1>Ringkasan SANRO Studio</h1><p class="sanro-lead">Kelola produk, desain dan persiapan produksi dalam satu tempat.</p><div class="sanro-admin-cards">';
    foreach(['products'=>['Siapkan produk','Atur mockup, warna, ukuran dan area cetak.'],'prices'=>['Kelola harga','Gunakan tabel harga per produk yang telah disetujui.'],'orders'=>['Lihat pesanan','Periksa desain depan/belakang dan kesiapan produksi.']] as $key=>$copy){$entry=sanro_studio_ui_admin_links()[$key];echo '<a class="sanro-admin-card" href="'.esc_url(admin_url('admin.php?page='.$entry[1])).'">'.sanro_studio_ui_icon($entry[2]).'<h2>'.esc_html($copy[0]).'</h2><p>'.esc_html($copy[1]).'</p><span>Buka →</span></a>';}
    echo '</div><section class="sanro-admin-card"><h2>Penyimpanan produksi</h2><p><span class="sanro-status-pill">'.esc_html(sanro_studio_drive_oauth_status()).'</span></p><p>Preview privat dan produksi lokal tetap tersedia. Google Drive dapat dikonfigurasi saat diperlukan.</p><a class="button" href="'.esc_url(admin_url('admin.php?page=sanro-studio-drive')).'">Pengaturan Google Drive</a></section>';sanro_studio_ui_admin_end();
}
function sanro_studio_ui_settings(){if(!current_user_can('manage_options'))wp_die('Akses ditolak.','',['response'=>403]);sanro_studio_ui_admin_begin('settings');sanro_studio_render_settings();sanro_studio_ui_admin_end();}
function sanro_studio_ui_products(){sanro_studio_ui_product_page(false);}
function sanro_studio_ui_prices(){sanro_studio_ui_product_page(true);}
function sanro_studio_ui_product_page($prices=false){
    if(!current_user_can('edit_products'))wp_die('Akses ditolak.','',['response'=>403]);
    sanro_studio_ui_admin_begin($prices?'prices':'products');echo '<p class="sanro-eyebrow">KATALOG ANDA</p><h1>'.($prices?'Harga Produk':'Produk & Mockup').'</h1><p class="sanro-lead">Pengaturan visual memakai konfigurasi produk native yang sama.</p>';
    $products=wc_get_products(['status'=>'publish','limit'=>100,'orderby'=>'title','order'=>'ASC']);$id=isset($_GET['product_id'])?absint($_GET['product_id']):0;
    echo '<form method="get" class="sanro-product-selector"><input type="hidden" name="page" value="'.($prices?'sanro-studio-prices':'sanro-studio-products').'"><label>Pilih produk <select name="product_id"><option value="">— Pilih produk —</option>';
    foreach($products as $product)if(current_user_can('edit_post',$product->get_id()))echo '<option value="'.(int)$product->get_id().'"'.($id===$product->get_id()?' selected':'').'>'.esc_html($product->get_name()).'</option>';
    echo '</select></label><button class="button" type="submit">Buka Produk</button></form>';
    if(!$id){echo '<div class="sanro-admin-cards">';foreach($products as $product){$config=sanro_studio_public_editor_config($product->get_id());if(empty($config['valid']))continue;echo '<a class="sanro-admin-card sanro-product-mini" href="'.esc_url(admin_url('admin.php?page='.($prices?'sanro-studio-prices':'sanro-studio-products').'&product_id='.$product->get_id())).'">'.$product->get_image('woocommerce_thumbnail').'<h2>'.esc_html($product->get_name()).'</h2><span>Kelola '.($prices?'harga':'produk').' →</span></a>';}echo '</div>';sanro_studio_ui_admin_end();return;}
    $product=wc_get_product($id);if(!$product||!current_user_can('edit_post',$id)){sanro_studio_ui_admin_end();return;}
    echo '<section class="sanro-admin-card"><h2>'.esc_html($product->get_name()).'</h2><nav class="sanro-admin-tabs" aria-label="Pengaturan produk">';foreach(['mockup'=>'Mockup','area'=>'Area Cetak','variants'=>'Warna & Size','prices'=>'Harga'] as $key=>$title)echo '<button type="button" data-sanro-admin-tab="'.$key.'" aria-selected="'.(($prices?$key==='prices':$key==='area')?'true':'false').'">'.esc_html($title).'</button>';echo '</nav><form method="post" class="sanro-product-form" action="'.esc_url(admin_url('admin-post.php')).'" data-start-tab="'.($prices?'prices':'area').'">';
    echo '<input type="hidden" name="action" value="sanro_studio_ui_save_product"><input type="hidden" name="product_id" value="'.$id.'">';wp_nonce_field('sanro_ui_product_'.$id);
    $old=$GLOBALS['post']??null;$GLOBALS['post']=get_post($id);
    try{
        echo '<div data-sanro-admin-panel="mockup">';sanro_studio_product_mockup_fields();echo '</div><div data-sanro-admin-panel="area">';sanro_studio_product_print_zone_fields();sanro_studio_product_area_fields();echo '<p class="sanro-help">Geser area atau tarik sudutnya. Posisi visual (%) terpisah dari batas fisik (cm).</p></div><div data-sanro-admin-panel="variants">';sanro_studio_mockup_matrix_fields();echo '</div><div data-sanro-admin-panel="prices">';sanro_studio_product_pricing_fields();$config=sanro_studio_public_editor_config($id);if(!empty($config['pricing']['table']))echo '<script type="application/json" class="sanro-ui-price-config">'.wp_json_encode($config['pricing']['table'],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script>';echo '</div>';
    }finally{$GLOBALS['post']=$old;}
    submit_button('Simpan Produk','primary','sanro_ui_save_product');echo '</form></section>';sanro_studio_ui_admin_end();
}
function sanro_studio_ui_save_product(){
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST')wp_die('Gunakan formulir produk.','',['response'=>405]);$id=absint($_POST['product_id']??0);
    if(!$id||!current_user_can('edit_products')||!current_user_can('edit_post',$id))wp_die('Akses ditolak.','',['response'=>403]);check_admin_referer('sanro_ui_product_'.$id);
    $product=wc_get_product($id);if(!$product)wp_die('Produk tidak tersedia.','',['response'=>404]);
    sanro_studio_save_product_areas($product);sanro_studio_save_product_print_zones($product);sanro_studio_save_product_pricing($product);sanro_studio_save_product_mockups($product);sanro_studio_mockup_matrix_save($product);$product->save();
    wp_safe_redirect(admin_url('admin.php?page=sanro-studio-products&product_id='.$id.'&updated=1'),303);exit;
}
function sanro_studio_ui_orders(){
    if(!current_user_can('manage_woocommerce'))wp_die('Akses ditolak.','',['response'=>403]);sanro_studio_ui_admin_begin('orders');echo '<p class="sanro-eyebrow">PROSES PRODUKSI</p><h1>Pesanan & Desain</h1><p class="sanro-lead">Preview dan file produksi mengikuti status pesanan yang sebenarnya.</p><div class="sanro-order-list">';
    $found=0;foreach(wc_get_orders(['limit'=>30,'orderby'=>'date','order'=>'DESC']) as $order){$items=$order->get_items();$studio=false;foreach($items as $item)if($item->get_meta('_sanro_studio_design_id',true)!=='')$studio=true;if(!$studio)continue;$found++;echo '<a class="sanro-admin-card" href="'.esc_url($order->get_edit_order_url()).'"><h2>Pesanan #'.esc_html($order->get_order_number()).'</h2><span class="sanro-status-pill">'.esc_html(wc_get_order_status_name($order->get_status())).'</span><p>'.wp_kses_post($order->get_formatted_order_total()).'</p><span>Lihat desain dan produksi →</span></a>';}
    if(!$found)echo '<div class="sanro-empty">Belum ada pesanan Studio pada daftar terbaru.</div>';echo '</div>';sanro_studio_ui_admin_end();
}
function sanro_studio_ui_storage(){if(!current_user_can('manage_woocommerce'))wp_die('Akses ditolak.','',['response'=>403]);sanro_studio_ui_admin_begin('storage');sanro_studio_storage_admin();sanro_studio_ui_admin_end();}
function sanro_studio_ui_admin_assets($hook){
    $page=isset($_GET['page'])&&is_string($_GET['page'])?$_GET['page']:'';$screen=function_exists('get_current_screen')?get_current_screen():null;
    if(strpos($page,'sanro-studio')!==0&&(!is_object($screen)||!in_array($screen->id??'', ['product','shop_order','woocommerce_page_wc-orders'],true)))return;
    $asset=plugin_dir_url(__DIR__.'/../sanro-studio.php').'assets/';wp_enqueue_style('sanro-studio-admin-ui',$asset.'studio-admin.css',[],SANRO_STUDIO_VERSION);wp_enqueue_script('sanro-studio-admin-ui',$asset.'studio-admin.js',[],SANRO_STUDIO_VERSION,true);
    if(in_array($page,['sanro-studio-products','sanro-studio-prices'],true)&&current_user_can('edit_products')){
        wp_enqueue_media();foreach(['admin-mockups','admin-mockup-mapping','admin-print-zones'] as $name)wp_enqueue_script('sanro-ui-'.$name,$asset.$name.'.js',['jquery'],SANRO_STUDIO_VERSION,true);
    }
}
function sanro_studio_ui_download_guide(){
    if(!current_user_can('manage_woocommerce'))wp_die('Akses ditolak.','',['response'=>403]);$id=absint($_GET['order_id']??0);$item=absint($_GET['item_id']??0);check_admin_referer('sanro_guide_'.$id.'_'.$item);$order=wc_get_order($id);
    if(!$order||!sanro_studio_auto_drive_order_eligible($order)||empty(sanro_studio_saved_order_context($order,true)['valid']))wp_die('Pesanan belum siap produksi.','',['response'=>409]);
    $package=sanro_studio_prepare_production_package($order,$item);if(!empty($package['valid']))foreach($package['assets'] as $asset)if(($asset['kind']??null)==='guide'&&($asset['mime']??null)==='application/pdf'){nocache_headers();header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="sanro-order-'.$id.'-item-'.$item.'-guide.pdf"');header('X-Content-Type-Options: nosniff');echo $asset['bytes'];exit;}
    wp_die('Panduan belum tersedia.','',['response'=>409]);
}
add_action('admin_enqueue_scripts','sanro_studio_ui_admin_assets');
add_action('admin_post_sanro_studio_ui_save_product','sanro_studio_ui_save_product');
add_action('admin_post_sanro_studio_ui_guide','sanro_studio_ui_download_guide');

function sanro_studio_ui_drive(){if(!current_user_can('manage_options'))return;sanro_studio_ui_admin_begin('settings');sanro_studio_drive_oauth_page();sanro_studio_ui_admin_end();}
