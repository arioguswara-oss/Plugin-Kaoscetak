<?php
if (!defined('ABSPATH')) exit;
function kld_tiers(){ return ['A5'=>[14.8,21], 'A4'=>[21,29.7], 'A3'=>[29.7,42], 'A3+'=>[32,48]]; }
function kld_sizes(){ return ['S','M','L','XL','2XL','3XL','4XL','5XL']; }
function kld_surcharges(){
 $v=get_option('kld_surcharges',[]);
 $default=['A5'=>15000,'A4'=>25000,'A3'=>35000,'A3+'=>40000];
 return is_array($v)?array_merge($default,array_intersect_key($v,$default)):$default;
}
function kld_product_prices($id){
 $raw=get_post_meta($id,'_kld_matrix',true);
 if (!is_array($raw))return [];
 $r=[];
 foreach($raw as $size=>$tiers){
  if(!in_array($size,kld_sizes(),true)||!is_array($tiers))continue;
  foreach(kld_tiers() as $tier=>$dims){ if(isset($tiers[$tier]) && is_numeric($tiers[$tier]) && $tiers[$tier]>0) $r[$size][$tier]=(int)$tiers[$tier]; }
 }
 return $r;
}
function kld_unit_price($id,$size,$front,$back){
 $m=kld_product_prices($id); $tiers=array_keys(kld_tiers());
 $selected=array_values(array_filter([$front,$back],function($v)use($tiers){return in_array($v,$tiers,true);}));
 if(!isset($m[$size])||!count($selected))return null;
 usort($selected,function($a,$b)use($tiers){return array_search($a,$tiers,true)<=>array_search($b,$tiers,true);});
 $larger=end($selected);
 if(!isset($m[$size][$larger]))return null;
 $price=(int)$m[$size][$larger];
 if(count($selected)===2){$extra=kld_surcharges();$price+=(int)$extra[$selected[0]];}
 return $price;
}
add_action('admin_menu',function(){add_submenu_page('woocommerce','Kaoscetak Designer','Kaoscetak Designer','manage_woocommerce','kld-settings','kld_render_settings');});
add_action('admin_init',function(){
 if(!current_user_can('manage_woocommerce'))return;
 if(isset($_POST['kld_save_settings'])){
  check_admin_referer('kld_settings');
  update_option('kld_client_id',sanitize_text_field(wp_unslash($_POST['kld_client_id']??'')),false);
  update_option('kld_public_enabled',isset($_POST['kld_public_enabled'])?'yes':'no',false);
  $secret=trim((string)wp_unslash($_POST['kld_client_secret']??''));
  if($secret!=='') update_option('kld_client_secret',kld_encrypt_secret($secret),false);
  $rates=[];
  foreach(kld_tiers() as $t=>$unused){$rates[$t]=max(0,absint($_POST['rates'][$t]??0));}
  update_option('kld_surcharges',$rates,false);
  add_settings_error('kld','saved','Pengaturan disimpan.','updated');
 }
});
function kld_render_settings(){
 if(!current_user_can('manage_woocommerce'))return;
 $connected=kld_drive_connected();$rates=kld_surcharges();
 echo '<div class="wrap"><h1>Kaoscetak Lite Designer</h1><p>Mode aman: tanpa koneksi Drive, admin dapat melihat tombol editor langsung di halaman produk yang diaktifkan. Pelanggan tidak menerima alur checkout yang belum siap.</p>';
 settings_errors('kld');
 echo '<h2>Google Drive</h2><p>Status: <strong>'.($connected?'Terhubung':'Belum terhubung').'</strong></p>';
 echo '<form method="post">';wp_nonce_field('kld_settings');
 echo '<table class="form-table"><tr><th>OAuth Client ID</th><td><input class="regular-text" name="kld_client_id" value="'.esc_attr(get_option('kld_client_id','')).'" autocomplete="off"></td></tr>';
 echo '<tr><th>OAuth Client Secret</th><td><input type="password" class="regular-text" name="kld_client_secret" value="" autocomplete="new-password"><p class="description">Kosongkan untuk mempertahankan secret yang sudah tersimpan.</p></td></tr>';
 echo '<tr><th>Authorized redirect URI</th><td><code>'.esc_html(admin_url('admin-post.php?action=kld_oauth_callback')).'</code></td></tr></table>';
 echo '<h2>Publikasi</h2><p><label><input type="checkbox" name="kld_public_enabled" value="yes" '.checked(get_option('kld_public_enabled','no'),'yes',false).'> Tampilkan tombol Custom Desain untuk pelanggan (aktifkan hanya setelah pengujian lengkap)</label></p>';
 echo '<h2>Biaya tambahan sisi kedua (Rp)</h2><table class="form-table">';
 foreach($rates as $t=>$v)echo '<tr><th>'.esc_html($t).'</th><td><input type="number" min="0" name="rates['.esc_attr($t).']" value="'.esc_attr($v).'" required></td></tr>';
 echo '</table><p><button class="button button-primary" name="kld_save_settings" value="1">Simpan Pengaturan</button></p></form>';
 if(get_option('kld_client_id')&&kld_decrypt_secret(get_option('kld_client_secret'))){
  $connect=wp_nonce_url(admin_url('admin-post.php?action=kld_connect'),'kld_connect');
  echo '<p><a class="button" href="'.esc_url($connect).'">'.($connected?'Hubungkan ulang Google Drive':'Hubungkan Google Drive').'</a></p>';
 }
 if($connected){
  echo '<p>Folder master: <code>'.esc_html(get_option('kld_master_folder','')).'</code><br>Folder pesanan: <code>'.esc_html(get_option('kld_orders_folder','')).'</code></p>';
  echo '<p><a class="button" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=kld_test_drive'),'kld_test_drive')).'">Uji koneksi</a></p>';
 }
 echo '<h2>Langkah pengujian</h2><ol><li>Masukkan OAuth ID dan Secret dari Google Cloud Console, aktifkan Drive API dan daftarkan Redirect URI di atas.</li><li>Klik Hubungkan Google Drive, kemudian Uji koneksi.</li><li>Edit produk WooCommerce, aktifkan Kaoscetak Designer dan isi matriks harga.</li><li>Buka halaman produk saat login admin; tombol Custom Desain terlihat meskipun Drive belum terhubung.</li></ol>';
 echo '<p><strong>Catatan:</strong> versi ini perlu diuji di situs asli sebelum dipakai publik, terutama direct upload lintas domain, size-variasi, dan hak akses Google Drive.</p></div>';
}
add_action('add_meta_boxes_product',function(){add_meta_box('kld_product_box','Kaoscetak Lite Designer - Harga per size','kld_product_box','product','normal','default');});
function kld_product_box($post){
 wp_nonce_field('kld_product_save','kld_product_nonce');
 $on=get_post_meta($post->ID,'_kld_enabled',true)==='yes';$m=kld_product_prices($post->ID);
 echo '<p><label><input type="checkbox" name="kld_enabled" value="yes" '.checked($on,true,false).'> Aktifkan editor untuk produk ini</label></p>';
 echo '<p>Harga di bawah adalah <strong>harga jadi termasuk sablon satu sisi</strong>. Harga kedua sisi = paket ukuran terbesar + tambahan ukuran terkecil.</p>';
 echo '<table class="widefat striped"><thead><tr><th>Size</th>';
 foreach(kld_tiers() as $t=>$d)echo '<th>'.esc_html($t).' (Rp)</th>';
 echo '</tr></thead><tbody>';
 foreach(kld_sizes() as $s){echo '<tr><th>'.esc_html($s).'</th>';foreach(kld_tiers() as $t=>$d){$v=$m[$s][$t]??'';echo '<td><input type="number" min="0" step="100" style="width:100%" name="kld_matrix['.esc_attr($s).']['.esc_attr($t).']" value="'.esc_attr($v).'"></td>';}echo '</tr>';}
 echo '</tbody></table><p><label>Warna kaos (pisahkan dengan tanda |)<br><input style="width:100%" name="kld_colors" value="'.esc_attr(get_post_meta($post->ID,'_kld_colors',true)).'" placeholder="Hitam|Putih|Army Green|Navy"></label></p>';
 echo '<p class="description">Kosongkan ukuran yang tidak dijual. Produk variable memerlukan variasi WooCommerce yang cocok dengan ukuran dan warna.</p>';
 if($on)echo '<p><a class="button" href="'.esc_url(get_permalink($post->ID)).'" target="_blank" rel="noopener">Buka editor visual pada halaman produk</a> <em>(login admin; tidak perlu koneksi Drive)</em></p>';
}
add_action('save_post_product',function($id){
 if(!isset($_POST['kld_product_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['kld_product_nonce'])),'kld_product_save'))return;
 if(!current_user_can('edit_post',$id)||wp_is_post_revision($id))return;
 update_post_meta($id,'_kld_enabled',isset($_POST['kld_enabled'])?'yes':'no');
 update_post_meta($id,'_kld_colors',sanitize_text_field(wp_unslash($_POST['kld_colors']??'')));
 $raw=isset($_POST['kld_matrix'])&&is_array($_POST['kld_matrix'])?wp_unslash($_POST['kld_matrix']):[];
 $m=[];
 foreach(kld_sizes() as $s){foreach(kld_tiers() as $t=>$d){$v=isset($raw[$s][$t])?absint($raw[$s][$t]):0;if($v>0)$m[$s][$t]=$v;}}
 update_post_meta($id,'_kld_matrix',$m);
});