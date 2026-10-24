<?php
if (!defined('ABSPATH')) exit;
function kld_tiers(){ return ['A5'=>[14.8,21], 'A4'=>[21,29.7], 'A3'=>[29.7,42], 'A3+'=>[32,48]]; }
function kld_sizes(){ return ['S','M','L','XL','2XL','3XL','4XL','5XL']; }
function kld_surcharges(){
 $v=get_option('kld_surcharges',[]);
 $default=['A5'=>15000,'A4'=>25000,'A3'=>35000,'A3+'=>40000];
 return is_array($v)?array_merge($default,array_intersect_key($v,$default)):$default;
}
/** Official Owner-approved pricing templates. Available immediately after installation. */
function kld_default_price_templates(){
 $short=[
  'S'=>[62000,72000,82000,87000], 'M'=>[65000,75000,85000,90000],
  'L'=>[68000,78000,88000,93000], 'XL'=>[72000,82000,92000,97000],
  '2XL'=>[77000,87000,97000,102000], '3XL'=>[80000,90000,100000,105000],
  '4XL'=>[83000,93000,103000,108000], '5XL'=>[85000,95000,105000,110000]
 ];
 $tunik=['M'=>[85000,95000,105000,110000], 'L'=>[90000,100000,110000,115000], 'XL'=>[95000,105000,115000,120000]];
 $tiers=array_keys(kld_tiers());
 return [
  'cotton-24s-short'=>['label'=>'Cotton Combed 24s — Lengan Pendek','matrix'=>array_map(function($row)use($tiers){return array_combine($tiers,$row);},$short)],
  'tunik-oversize34'=>['label'=>'Tunik Oversize Lengan 3/4','matrix'=>array_map(function($row)use($tiers){return array_combine($tiers,$row);},$tunik)],
  'hoodie'=>['label'=>'Custom Hoodie (tarif belum disetujui)','matrix'=>[]],
  'sweatshirt'=>['label'=>'Custom Sweatshirt (tarif belum disetujui)','matrix'=>[]]
 ];
}
/** Settings override ONLY the intended templates; do not overwrite prices when upgrading. */
function kld_price_templates(){
 $defaults=kld_default_price_templates();
 $stored=get_option('kld_price_templates',[]);
 if(!is_array($stored))$stored=[];
 foreach($defaults as $key=>&$template){
  if(isset($stored[$key])&&is_array($stored[$key])){
   $template['matrix']=kld_normalize_matrix($stored[$key]);
  }
 }
 unset($template);
 $long=[];$add=kld_long_sleeve_extra();
 foreach($defaults['cotton-24s-short']['matrix'] as $size=>$prices){foreach($prices as $tier=>$amount)$long[$size][$tier]=$amount+$add;}
 $defaults['cotton-24s-long']=['label'=>'Cotton Combed 24s — Lengan Panjang (+Rp'.number_format($add,0,',','.').')','matrix'=>$long];
 return $defaults;
}
function kld_long_sleeve_extra(){return max(0,(int)get_option('kld_long_sleeve_extra',10000));}
function kld_normalize_matrix($raw){
 if(!is_array($raw))return [];
 $result=[];
 foreach($raw as $size=>$prices){
  if(!in_array($size,kld_sizes(),true)||!is_array($prices))continue;
  foreach(kld_tiers() as $tier=>$dims){
   if(isset($prices[$tier])&&is_numeric($prices[$tier])&&(int)$prices[$tier]>0)$result[$size][$tier]=(int)$prices[$tier];
  }
 }
 return $result;
}
/** Unknown WooCommerce products are NEVER assigned guessed prices. */
function kld_product_preset_key($id){
 $preset=(string)get_post_meta($id,'_kld_price_preset',true);
 if($preset==='none')return '';
 if(isset(kld_price_templates()[$preset]))return $preset;
 if($preset!==''||!function_exists('get_the_title'))return '';
 $title=strtolower(trim((string)get_the_title($id)));
 $title=preg_replace('/\s+/',' ',$title);
 $aliases=[
  'cotton-24s-short'=>['cotton combed 24s','kaos cotton combed 24s','kaos standar cotton combed 24s','cotton combed 24s lengan pendek','kaos cotton combed 24s lengan pendek'],
  'cotton-24s-long'=>['cotton combed 24s lengan panjang','kaos cotton combed 24s lengan panjang'],
  'tunik-oversize34'=>['tunik oversize lengan 3/4','kaos tunik oversize lengan 3/4','tunik oversize 3/4'],
  'hoodie'=>['custom hoodie','hoodie custom'],
  'sweatshirt'=>['custom sweatshirt','sweatshirt custom']
 ];
 foreach($aliases as $key=>$names)if(in_array($title,$names,true))return $key;
 return '';
}
function kld_product_prices($id){
 $legacy=get_post_meta($id,'_kld_matrix',true);
 $custom=(string)get_post_meta($id,'_kld_price_custom',true);
 if(is_array($legacy) && ($custom==='yes'||($custom===''&&count($legacy)>0)))return kld_normalize_matrix($legacy);
 $key=kld_product_preset_key($id);
 $templates=kld_price_templates();
 return isset($templates[$key])?$templates[$key]['matrix']:[];
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
  // Built-in matrices are installed automatically, and remain editable.
  $input=isset($_POST['kld_templates'])&&is_array($_POST['kld_templates'])?wp_unslash($_POST['kld_templates']):[];
  $new=[];
  foreach(kld_default_price_templates() as $key=>$def){
   $new[$key]=kld_normalize_matrix($input[$key]??[]);
  }
  update_option('kld_price_templates',$new,false);
  update_option('kld_long_sleeve_extra',max(0,absint($_POST['kld_long_sleeve_extra']??10000)),false);
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
 echo '</table><h2>Harga bawaan kaos sablon 1 sisi (dapat diedit)</h2><p>Sudah terisi sejak instalasi. Perubahan harga yang disimpan dipertahankan saat pembaruan plugin. Nilai kosong berarti size/kategori tidak dijual. Harga bukan kaos polos.</p>';
 $templates=kld_price_templates();
 echo '<p><strong>Penting:</strong> Harga Hoodie dan Sweatshirt tidak disamakan dengan kaos reguler. Isi tabelnya setelah Owner memberikan tarif resmi; sebelum itu produk tersebut tidak boleh checkout.</p>';
 foreach(kld_default_price_templates() as $key=>$unused){
  $model=$templates[$key];
  echo '<h3>'.esc_html($model['label']).'</h3><table class="widefat striped"><thead><tr><th>Size</th>';
  foreach(kld_tiers() as $tier=>$dims)echo '<th>'.esc_html($tier).' (Rp)</th>';
  echo '</tr></thead><tbody>';
  // Always show the official supported rows, even if the owner cleared all rates.
  $rowSizes=$key==='tunik-oversize34'?array_keys(kld_default_price_templates()[$key]['matrix']):kld_sizes();
  foreach($rowSizes as $size){
   $row=$model['matrix'][$size]??[];
   echo '<tr><th>'.esc_html($size).'</th>';
   foreach(kld_tiers() as $tier=>$dims){
    $v=$row[$tier]??'';
    echo '<td><input type="number" min="0" step="100" style="width:100%;max-width:150px" name="kld_templates['.esc_attr($key).']['.esc_attr($size).']['.esc_attr($tier).']" value="'.esc_attr($v).'" /></td>';
   }
   echo '</tr>';
  }
  echo '</tbody></table>';
 }
 echo '<h3>Cotton Combed 24s — Lengan Panjang</h3><p>Harga dihitung otomatis dari tabel Cotton 24s Lengan Pendek + tarif tambahan berikut. Tidak perlu tabel ganda.</p>';
 echo '<p><label>Tambahan lengan panjang (Rp) <input type="number" min="0" step="100" name="kld_long_sleeve_extra" value="'.esc_attr(kld_long_sleeve_extra()).'"></label></p>';
 echo '<p><button class="button button-primary" name="kld_save_settings" value="1">Simpan Pengaturan</button></p></form>';
 if(get_option('kld_client_id')&&kld_decrypt_secret(get_option('kld_client_secret'))){
  $connect=wp_nonce_url(admin_url('admin-post.php?action=kld_connect'),'kld_connect');
  echo '<p><a class="button" href="'.esc_url($connect).'">'.($connected?'Hubungkan ulang Google Drive':'Hubungkan Google Drive').'</a></p>';
 }
 if($connected){
  echo '<p>Folder master: <code>'.esc_html(get_option('kld_master_folder','')).'</code><br>Folder pesanan: <code>'.esc_html(get_option('kld_orders_folder','')).'</code></p>';
  echo '<p><a class="button" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=kld_test_drive'),'kld_test_drive')).'">Uji koneksi</a></p>';
 }
 echo '<h2>Langkah pengujian</h2><ol><li>Masukkan OAuth ID dan Secret dari Google Cloud Console, aktifkan Drive API dan daftarkan Redirect URI di atas.</li><li>Klik Hubungkan Google Drive, kemudian Uji koneksi.</li><li>Edit produk WooCommerce, aktifkan Kaoscetak Designer dan pilih jenis harga bawaan; tidak perlu isi harga manual.</li><li>Buka halaman produk saat login admin; tombol Custom Desain terlihat meskipun Drive belum terhubung.</li></ol>';
 echo '<p><strong>Catatan:</strong> versi ini perlu diuji di situs asli sebelum dipakai publik, terutama direct upload lintas domain, size-variasi, dan hak akses Google Drive.</p></div>';
}
add_action('add_meta_boxes_product',function(){add_meta_box('kld_product_box','Kaoscetak Lite Designer - Harga per size','kld_product_box','product','normal','default');});
function kld_product_box($post){
 wp_nonce_field('kld_product_save','kld_product_nonce');
 $on=get_post_meta($post->ID,'_kld_enabled',true)==='yes';$m=kld_product_prices($post->ID);
 $preset=kld_product_preset_key($post->ID);
 $legacy=get_post_meta($post->ID,'_kld_matrix',true);
 $customFlag=get_post_meta($post->ID,'_kld_price_custom',true);
 $custom=$customFlag==='yes'||($customFlag===''&&is_array($legacy)&&count($legacy)>0);
 echo '<p><label><input type="checkbox" name="kld_enabled" value="yes" '.checked($on,true,false).'> Aktifkan editor untuk produk ini</label></p>';
 echo '<p><label><strong>Jenis harga bawaan</strong> <select name="kld_price_preset"><option value="none">-- Pilih jenis produk / harga khusus --</option>';
 foreach(kld_price_templates() as $key=>$template)echo '<option value="'.esc_attr($key).'" '.selected($preset,$key,false).'>'.esc_html($template['label']).'</option>';
 echo '</select></label></p>';
 echo '<p>Harga resmi Cotton Combed 24s dan Tunik sudah siap. Hoodie/Sweatshirt punya tabel sendiri tetapi belum memiliki angka resmi; produk tanpa harga tidak bisa dipesan. Untuk nama lain, pilih jenis produk lalu simpan sekali.</p>';
 echo '<p><label><input type="checkbox" name="kld_price_custom" value="yes" '.checked($custom,true,false).'> <strong>Gunakan harga khusus produk ini (override preset)</strong></label></p>';
 echo '<p>Harga di bawah adalah <strong>harga jadi termasuk sablon satu sisi</strong>. Tanpa centang harga khusus, tabel ini hanya pratinjau; setelah mengganti jenis harga, simpan dan muat ulang produk untuk melihat harga barunya.</p>';
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
 $preset=sanitize_key((string)wp_unslash($_POST['kld_price_preset']??'none'));
 if($preset!=='none'&&!isset(kld_price_templates()[$preset]))$preset='none';
 update_post_meta($id,'_kld_price_preset',$preset);
 $custom=isset($_POST['kld_price_custom']);
 update_post_meta($id,'_kld_price_custom',$custom?'yes':'no');
 $raw=isset($_POST['kld_matrix'])&&is_array($_POST['kld_matrix'])?wp_unslash($_POST['kld_matrix']):[];
 $m=[];
 foreach(kld_sizes() as $s){foreach(kld_tiers() as $t=>$d){$v=isset($raw[$s][$t])?absint($raw[$s][$t]):0;if($v>0)$m[$s][$t]=$v;}}
 if($custom)update_post_meta($id,'_kld_matrix',$m);
});