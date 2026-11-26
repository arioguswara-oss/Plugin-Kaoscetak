<?php
defined('ABSPATH') || exit;
/** Original SANRO interface primitives; no vendor assets or business rules. */
function sanro_studio_ui_icon($name){
    $paths=['cart'=>'M3 3h2l3 12h10l3-9H6M9 21h.01M18 21h.01','image'=>'M3 3h18v18H3zM3 16l6-6 4 4 3-3 5 5M16 7h.01','text'=>'M4 4h16M12 4v16M8 20h8','shirt'=>'M8 3l4 2 4-2 6 5-4 4-2-2v11H8V10l-2 2-4-4z','folder'=>'M3 6h7l2 2h9v13H3z','arrow'=>'M19 12H5m6-6-6 6 6 6','center'=>'M12 2v4m0 12v4M2 12h4m12 0h4M6 6h12v12H6z','trash'=>'M4 6h16M9 6V3h6v3M6 6l1 15h10l1-15M10 10v7m4-7v7','resize'=>'M4 10V4h6m4 0h6v6M4 14v6h6m4 0h6v-6M4 4l5 5m11-5-5 5M4 20l5-5m11 5-5-5','lock'=>'M6 10h12v11H6zM8 10V7a4 4 0 018 0v3','mountain'=>'M2 20L9 5l6 10 3-5 4 10M7 9l3 3 2-2','home'=>'M3 11l9-8 9 8M5 10v11h5v-7h4v7h5V10','settings'=>'M4 7h16M4 17h16M8 4v6m8 4v6','price'=>'M3 3h10l8 8-10 10-8-8zM8 8h.01','cloud'=>'M6 19a5 5 0 010-10 6 6 0 0112-1 5 5 0 010 11z','orders'=>'M5 4h14v18H5zM9 2h6v4H9zM8 10h8m-8 4h8m-8 4h5'];
    return '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'.($paths[$name]??$paths['settings']).'"/></svg>';
}
/** Stay on the storefront so the Woo session and checkout authorization still apply. */
function sanro_studio_ui_cart_url() {
    if (!function_exists('wc_get_cart_url') || !function_exists('sanro_studio_classic_wc_page_id')) return '';
    $page_id = sanro_studio_classic_wc_page_id('cart');
    if (!$page_id) return '';

    $url = wc_get_cart_url();
    if (!is_string($url) || $url === '') return '';
    $target = wp_parse_url($url);
    $home = wp_parse_url(home_url('/'));
    if (empty($target['host']) || empty($home['host']) || strcasecmp($home['host'],$target['host']) !== 0
        || !in_array($target['scheme'] ?? '', ['http','https'], true)
        || (($home['scheme'] ?? '') === 'https' && $target['scheme'] !== 'https')
        || ($target['port'] ?? null) !== ($home['port'] ?? null)
        || isset($target['user']) || isset($target['pass'])) return '';

    // WooCommerce or a URL filter may return the site root instead of the cart.
    // Keep plain WordPress permalinks such as /?page_id=16 working.
    if (rtrim($target['path'] ?? '', '/') === rtrim($home['path'] ?? '', '/')) {
        wp_parse_str($target['query'] ?? '',$query);
        if (($query['page_id'] ?? '') !== (string)$page_id) return '';
    }
    return $url;
}

function sanro_studio_ui_header($label,$back=''){
    $cart_url = esc_url(sanro_studio_ui_cart_url());
    $wc = function_exists('WC') ? WC() : null;
    $cart = $wc->cart ?? null;
    $count = is_object($cart) && method_exists($cart,'get_cart_contents_count') ? $cart->get_cart_contents_count() : 0;
    $icon = sanro_studio_ui_icon('cart') . ($count ? '<span>' . (int)$count . '</span>' : '');
    $mini_cart = '';
    if ($cart_url !== '' && function_exists('woocommerce_mini_cart')) {
        ob_start();
        woocommerce_mini_cart();
        $mini_cart = ob_get_clean();
    }
    $cart_control = $cart_url !== ''
        ? '<div class="sanro-mini-cart-wrap"><a class="sanro-cart-link" aria-label="Keranjang WooCommerce" aria-haspopup="true" aria-expanded="false" aria-controls="sanro-mini-cart-panel" data-sanro-mini-cart-toggle href="' . $cart_url . '">' . $icon . '</a><div id="sanro-mini-cart-panel" class="sanro-mini-cart-panel" data-sanro-mini-cart role="region" aria-label="Mini Cart" hidden>' . $mini_cart . '<a class="sanro-mini-cart-view" href="' . $cart_url . '">Lihat keranjang</a></div></div>'
        : '<span class="sanro-cart-link" role="img" aria-label="Keranjang belum tersedia" title="Keranjang belum tersedia">' . $icon . '</span>';
    echo '<header class="sanro-appbar">'
        . ($back ? '<a class="sanro-back" aria-label="Kembali pilih produk" href="' . esc_url($back) . '">' . sanro_studio_ui_icon('arrow') . '</a>' : '')
        . '<h1>' . esc_html($label) . '</h1>' . $cart_control . '</header>';
}
function sanro_studio_ui_theme_scope($classes){$classes[]='sanro-studio-page';return $classes;}
function sanro_studio_ui_admin_body_scope($classes){$page=isset($_GET['page'])&&is_string($_GET['page'])?sanitize_key(wp_unslash($_GET['page'])):'';if(strpos($page,'sanro-studio')===0)$classes.=' sanro-studio-admin';return $classes;}
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
function sanro_studio_catalog_write($product_id,$action){
    if((!is_int($product_id)&&!is_string($product_id))||!preg_match('/^[1-9][0-9]{0,18}$/D',(string)$product_id)||(string)(int)$product_id!==(string)$product_id)wp_die('ID produk tidak valid.','',['response'=>400]);
    $product_id=(int)$product_id;
    if(!current_user_can('edit_products')||!current_user_can('edit_post',$product_id))wp_die('Akses ditolak.','',['response'=>403]);
    check_admin_referer('sanro_catalog_'.$product_id);
    $product=wc_get_product($product_id);if(!$product||$product->get_status()!=='publish')wp_die('Pilih produk WooCommerce yang terbit.','',['response'=>400]);
    if($action==='add'){
        if(!$product->is_type('simple')&&!$product->is_type('variable'))wp_die('SANRO Studio hanya mendukung produk simple atau variable WooCommerce.','',['response'=>409]);
        if(metadata_exists('post',$product_id,'_sanro_studio_catalog_status')&&get_post_meta($product_id,'_sanro_studio_catalog_status',true)!=='archived')wp_die('Produk sudah ada di katalog Studio.','',['response'=>409]);
        update_post_meta($product_id,'_sanro_studio_catalog_status','disabled');
        delete_post_meta($product_id,'_sanro_studio_catalog_restore_status');
    }elseif($action==='activate'){
        if(!$product->is_type('simple')&&!$product->is_type('variable'))wp_die('Tipe produk tidak didukung SANRO Studio.','',['response'=>409]);
        $config=sanro_studio_public_editor_config($product_id,true);
        if(empty($config['valid']))wp_die('Produk belum siap: lengkapi mockup, area cetak, ukuran dan harga sebelum mengaktifkan.','',['response'=>409]);
        update_post_meta($product_id,'_sanro_studio_catalog_status','active');
    }elseif($action==='disable'){
        update_post_meta($product_id,'_sanro_studio_catalog_status','disabled');
    }elseif($action==='archive'){
        $status=sanro_studio_catalog_status($product_id);
        if($status!=='archived'){
            update_post_meta($product_id,'_sanro_studio_catalog_restore_status',$status==='active'?'active':'disabled');
            update_post_meta($product_id,'_sanro_studio_catalog_status','archived');
        }
    }elseif($action==='restore'){
        if(sanro_studio_catalog_status($product_id)==='archived'){
            $restore=get_post_meta($product_id,'_sanro_studio_catalog_restore_status',true);
            $config=$restore==='active'?sanro_studio_public_editor_config($product_id,true):['valid'=>false];
            update_post_meta($product_id,'_sanro_studio_catalog_status',$restore==='active'&&!empty($config['valid'])?'active':'disabled');
            delete_post_meta($product_id,'_sanro_studio_catalog_restore_status');
        }
    }else wp_die('Aksi katalog tidak valid.','',['response'=>400]);
    return $product_id;
}
function sanro_studio_catalog_dispatch($action){$id=sanro_studio_catalog_write($_POST['product_id']??null,$action);wp_safe_redirect(admin_url('admin.php?page=sanro-studio-products&product_id='.$id.'&catalog_updated=1'),303);exit;}
function sanro_studio_catalog_add(){sanro_studio_catalog_dispatch('add');}
function sanro_studio_catalog_activate(){sanro_studio_catalog_dispatch('activate');}
function sanro_studio_catalog_disable(){sanro_studio_catalog_dispatch('disable');}
function sanro_studio_catalog_archive(){sanro_studio_catalog_dispatch('archive');}
function sanro_studio_catalog_restore(){sanro_studio_catalog_dispatch('restore');}
function sanro_studio_ui_product_page($prices=false){
    if(!current_user_can('edit_products'))wp_die('Akses ditolak.','',['response'=>403]);
    sanro_studio_ui_admin_begin($prices?'prices':'products');echo '<p class="sanro-eyebrow">KATALOG ANDA</p><h1>'.($prices?'Harga Produk':'Produk & Mockup').'</h1><p class="sanro-lead">Pengaturan visual memakai konfigurasi produk native yang sama.</p>';
    if(!$prices&&isset($_GET['mockup_error'])&&$_GET['mockup_error']==='1')echo '<div class="notice notice-error"><p>Mockup tidak disimpan. Pilih gambar publik dari Media Library; file pribadi, draft, atau terhapus ditolak. Konfigurasi mockup sebelumnya tetap dipertahankan.</p></div>';
    elseif(!$prices&&isset($_GET['updated'])&&$_GET['updated']==='1')echo '<div class="notice notice-success"><p>Konfigurasi produk SANRO Studio tersimpan.</p></div>';
    $id=isset($_GET['product_id'])?absint($_GET['product_id']):0;$search=isset($_GET['q'])&&is_string($_GET['q'])?sanitize_text_field(wp_unslash($_GET['q'])):'';$filter=isset($_GET['status'])&&is_string($_GET['status'])?sanitize_key($_GET['status']):'all';
    $page_num=max(1,absint($_GET['catalog_page']??1));$query=wc_get_products(['status'=>'publish','limit'=>100,'page'=>$page_num,'paginate'=>true,'orderby'=>'title','order'=>'ASC','s'=>$search]);$products=is_object($query)&&isset($query->products)&&is_array($query->products)?$query->products:(is_array($query)?$query:[]);$max_pages=is_object($query)?max(1,(int)($query->max_num_pages??1)):1;
    if(!$prices){
        echo '<section class="sanro-admin-card"><h2>Tambah produk ke katalog</h2><p>Petakan produk WooCommerce yang sudah ada. Studio tidak membuat duplikat dan tidak mengubah status WooCommerce.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="sanro-product-selector"><input type="hidden" name="action" value="sanro_studio_catalog_add">';
        echo '<input type="hidden" name="_wpnonce" value=""><label>Produk WooCommerce <select name="product_id" required><option value="">— Pilih produk simple atau variable —</option>';foreach($products as $candidate)if(current_user_can('edit_post',$candidate->get_id())&&!metadata_exists('post',$candidate->get_id(),'_sanro_studio_catalog_status'))echo '<option value="'.(int)$candidate->get_id().'" data-nonce="'.esc_attr(wp_create_nonce('sanro_catalog_'.$candidate->get_id())).'">'.esc_html($candidate->get_name()).' (#'.(int)$candidate->get_id().')</option>';echo '</select></label><button class="button button-primary">Tambah & atur</button></form></section>';
        echo '<form method="get" class="sanro-product-selector"><input type="hidden" name="page" value="sanro-studio-products"><label>Cari produk <input type="search" name="q" value="'.esc_attr($search).'" placeholder="Nama produk"></label><label>Status <select name="status">';foreach(['all'=>'Semua','active'=>'Aktif','disabled'=>'Nonaktif','archived'=>'Arsip'] as $key=>$label)echo '<option value="'.$key.'"'.selected($filter,$key,false).'>'.$label.'</option>';echo '</select></label><button class="button">Filter</button></form>';
        echo '<div class="sanro-admin-cards">';foreach($products as $product){if(!current_user_can('edit_post',$product->get_id()))continue;$pid=$product->get_id();$has=metadata_exists('post',$pid,'_sanro_studio_catalog_status');$status=$has?sanro_studio_catalog_status($pid):'unmapped';if($filter!=='all'&&$status!==$filter)continue;$config=sanro_studio_public_editor_config($pid,true);$ready=!empty($config['valid']);$edit=admin_url('admin.php?page=sanro-studio-products&product_id='.$pid);echo '<article class="sanro-admin-card sanro-product-mini">'.$product->get_image('woocommerce_thumbnail').'<h2>'.esc_html($product->get_name()).'</h2><p>WooCommerce #'.(int)$pid.' · '.esc_html($product->get_type()).'</p><span class="sanro-status-pill">'.esc_html($status==='unmapped'?'Belum dipetakan':($status==='active'?'Aktif':($status==='disabled'?'Nonaktif':'Arsip'))).'</span><p>'.($ready?'Konfigurasi siap':'Konfigurasi belum lengkap').'</p><a class="button" href="'.esc_url($edit).'">Edit konfigurasi</a>';
            if($has){$action=$status==='archived'?'restore':($status==='active'?'disable':'activate');$label=$status==='archived'?'Pulihkan':($status==='active'?'Nonaktifkan':'Aktifkan');echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="sanro_studio_catalog_'.$action.'"><input type="hidden" name="product_id" value="'.$pid.'">';wp_nonce_field('sanro_catalog_'.$pid);echo '<button class="button">'.$label.'</button></form>';if($status!=='archived'){echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" onsubmit="return confirm(\'Arsipkan listing Studio? Produk WooCommerce dan riwayat tetap ada.\')"><input type="hidden" name="action" value="sanro_studio_catalog_archive"><input type="hidden" name="product_id" value="'.$pid.'">';wp_nonce_field('sanro_catalog_'.$pid);echo '<button class="button">Arsipkan dari Studio</button></form>';}}
            echo '</article>'; }echo '</div>';if($max_pages>1){echo '<nav class="sanro-catalog-pages" aria-label="Halaman katalog">';for($p=1;$p<=$max_pages;$p++){if($p===$page_num)echo '<strong aria-current="page">'.$p.'</strong> ';else echo '<a class="button" href="'.esc_url(add_query_arg(['page'=>'sanro-studio-products','catalog_page'=>$p,'q'=>$search,'status'=>$filter],admin_url('admin.php'))).'">'.$p.'</a> ';}echo '</nav>';}
        // Product-bound add nonce is emitted alongside each selectable mapping to prevent unbound writes.
    }
    if(!$id){if($prices){echo '<form method="get" class="sanro-product-selector"><input type="hidden" name="page" value="sanro-studio-prices"><label>Pilih produk <select name="product_id"><option value="">— Pilih produk —</option>';foreach($products as $product)if(current_user_can('edit_post',$product->get_id()))echo '<option value="'.(int)$product->get_id().'">'.esc_html($product->get_name()).'</option>';echo '</select></label><button class="button">Buka Harga</button></form><div class="sanro-admin-cards">';foreach($products as $product){if(!current_user_can('edit_post',$product->get_id()))continue;$config=sanro_studio_public_editor_config($product->get_id(),true);if(empty($config['valid']))continue;echo '<a class="sanro-admin-card sanro-product-mini" href="'.esc_url(admin_url('admin.php?page=sanro-studio-prices&product_id='.$product->get_id())).'">'.$product->get_image('woocommerce_thumbnail').'<h2>'.esc_html($product->get_name()).'</h2><span>Kelola harga →</span></a>';}echo '</div>'; }sanro_studio_ui_admin_end();return;}
    $product=wc_get_product($id);if(!$product||!current_user_can('edit_post',$id)){sanro_studio_ui_admin_end();return;}
    if(!method_exists($product,'is_type')||(!$product->is_type('simple')&&!$product->is_type('variable'))){echo '<div class="notice notice-warning"><p>Tipe produk tidak didukung SANRO Studio.</p></div>';sanro_studio_ui_admin_end();return;}
    echo '<section class="sanro-admin-card"><h2>'.esc_html($product->get_name()).'</h2><nav class="sanro-admin-tabs" aria-label="Pengaturan produk">';foreach(['mockup'=>'Mockup','area'=>'Area Cetak','variants'=>'Warna & Size','prices'=>'Harga'] as $key=>$title)echo '<button type="button" data-sanro-admin-tab="'.$key.'" aria-selected="'.(($prices?$key==='prices':$key==='area')?'true':'false').'">'.esc_html($title).'</button>';echo '</nav><form method="post" class="sanro-product-form" action="'.esc_url(admin_url('admin-post.php')).'" data-start-tab="'.($prices?'prices':'area').'">';
    echo '<input type="hidden" name="action" value="sanro_studio_ui_save_product"><input type="hidden" name="product_id" value="'.$id.'">';wp_nonce_field('sanro_ui_product_'.$id);
    $old=$GLOBALS['post']??null;$GLOBALS['post']=get_post($id);
    try{
        echo '<div data-sanro-admin-panel="mockup">';sanro_studio_product_mockup_fields();echo '</div><div data-sanro-admin-panel="area">';sanro_studio_product_print_zone_fields();sanro_studio_product_area_fields();echo '<p class="sanro-help">Geser area atau tarik sudutnya. Posisi visual (%) terpisah dari batas fisik (cm).</p></div><div data-sanro-admin-panel="variants">';sanro_studio_mockup_matrix_fields();echo '</div><div data-sanro-admin-panel="prices">';sanro_studio_product_pricing_fields();$config=sanro_studio_public_editor_config($id,true);if(!empty($config['pricing']['table']))echo '<script type="application/json" class="sanro-ui-price-config">'.wp_json_encode($config['pricing']['table'],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script>';echo '</div>';
    }finally{$GLOBALS['post']=$old;}
    submit_button('Simpan Produk','primary','sanro_ui_save_product');echo '</form></section>';sanro_studio_ui_admin_end();
}
function sanro_studio_ui_save_product(){
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST')wp_die('Gunakan formulir produk.','',['response'=>405]);$id=absint($_POST['product_id']??0);
    if(!$id||!current_user_can('edit_products')||!current_user_can('edit_post',$id))wp_die('Akses ditolak.','',['response'=>403]);check_admin_referer('sanro_ui_product_'.$id);
    $product=wc_get_product($id);if(!$product)wp_die('Produk tidak tersedia.','',['response'=>404]);if(!method_exists($product,'is_type')||(!$product->is_type('simple')&&!$product->is_type('variable')))wp_die('Tipe produk tidak didukung SANRO Studio.','',['response'=>409]);
    sanro_studio_save_product_areas($product);sanro_studio_save_product_print_zones($product);sanro_studio_save_product_pricing($product);$mockups_valid=sanro_studio_save_product_mockups($product);sanro_studio_mockup_matrix_save($product);$product->save();
    wp_safe_redirect(admin_url('admin.php?page=sanro-studio-products&product_id='.$id.($mockups_valid?'&updated=1':'&mockup_error=1')),303);exit;
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
    $asset=plugin_dir_url(__DIR__.'/../sanro-studio.php').'assets/';wp_enqueue_style('sanro-studio-admin-ui',$asset.'studio-admin.css',[],SANRO_STUDIO_VERSION.'-'.substr(hash_file('sha256',dirname(__DIR__).'/assets/studio-admin.css'),0,12));$admin_js=dirname(__DIR__).'/assets/studio-admin.js';wp_enqueue_script('sanro-studio-admin-ui',$asset.'studio-admin.js',[],SANRO_STUDIO_VERSION.'-'.substr(hash_file('sha256',$admin_js),0,12),true);
    if(in_array($page,['sanro-studio-products','sanro-studio-prices'],true)&&current_user_can('edit_products')){
        wp_enqueue_media();foreach(['admin-mockups','admin-mockup-mapping','admin-print-zones'] as $name){$path=dirname(__DIR__).'/assets/'.$name.'.js';wp_enqueue_script('sanro-ui-'.$name,$asset.$name.'.js',['jquery'],SANRO_STUDIO_VERSION.'-'.substr(hash_file('sha256',$path),0,12),true);}
    }
}
function sanro_studio_ui_download_guide(){
    if(!current_user_can('manage_woocommerce'))wp_die('Akses ditolak.','',['response'=>403]);$id=absint($_GET['order_id']??0);$item=absint($_GET['item_id']??0);check_admin_referer('sanro_guide_'.$id.'_'.$item);$order=wc_get_order($id);
    if(!$order||!sanro_studio_auto_drive_order_eligible($order)||empty(sanro_studio_saved_order_context($order,true)['valid']))wp_die('Pesanan belum siap produksi.','',['response'=>409]);
    $package=sanro_studio_prepare_production_package($order,$item);if(!empty($package['valid']))foreach($package['assets'] as $asset)if(($asset['kind']??null)==='guide'&&($asset['mime']??null)==='application/pdf'){nocache_headers();header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="sanro-order-'.$id.'-item-'.$item.'-guide.pdf"');header('X-Content-Type-Options: nosniff');echo $asset['bytes'];exit;}
    wp_die('Panduan belum tersedia.','',['response'=>409]);
}
add_action('admin_enqueue_scripts','sanro_studio_ui_admin_assets');
add_filter('admin_body_class','sanro_studio_ui_admin_body_scope');
add_action('admin_post_sanro_studio_ui_save_product','sanro_studio_ui_save_product');
add_action('admin_post_sanro_studio_catalog_add','sanro_studio_catalog_add');
add_action('admin_post_sanro_studio_catalog_activate','sanro_studio_catalog_activate');
add_action('admin_post_sanro_studio_catalog_disable','sanro_studio_catalog_disable');
add_action('admin_post_sanro_studio_catalog_archive','sanro_studio_catalog_archive');
add_action('admin_post_sanro_studio_catalog_restore','sanro_studio_catalog_restore');
add_action('admin_post_sanro_studio_ui_guide','sanro_studio_ui_download_guide');

function sanro_studio_ui_drive(){if(!current_user_can('manage_options'))return;sanro_studio_ui_admin_begin('settings');sanro_studio_drive_oauth_page();sanro_studio_ui_admin_end();}
