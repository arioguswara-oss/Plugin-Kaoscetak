<?php
if (!defined('ABSPATH')) exit;
function kld_tiers(){return ['A5'=>[14.8,21],'A4'=>[21,29.7],'A3'=>[29.7,42],'A3+'=>[32,48]];}
function kld_sizes(){return ['S','M','L','XL','2XL','3XL','4XL','5XL'];}
function kld_surcharges(){
 $d=['A5'=>15000,'A4'=>25000,'A3'=>35000,'A3+'=>40000];$r=get_option('kld_surcharges',[]);
 return is_array($r)?array_replace($d,array_intersect_key($r,$d)):$d;
}
function kld_price_presets_default(){
 $regular=[
  'S'=>[62000,72000,82000,87000], 'M'=>[65000,75000,85000,90000],
  'L'=>[68000,78000,88000,93000], 'XL'=>[72000,82000,92000,97000],
  '2XL'=>[77000,87000,97000,102000], '3XL'=>[80000,90000,100000,105000],
  '4XL'=>[83000,93000,103000,108000], '5XL'=>[85000,95000,105000,110000]
 ];
 $tunik=['M'=>[85000,95000,105000,110000],'L'=>[90000,100000,110000,115000],'XL'=>[95000,105000,115000,120000]];
 $assoc=function($matrix){$r=[];foreach($matrix as $size=>$row)$r[$size]=array_combine(array_keys(kld_tiers()),$row);return $r;};
 return [
 'reguler-24s'=>['label'=>'Kaos Reguler Cotton Combed 24s','matrix'=>$assoc($regular)],
 'lengan-panjang-24s'=>['label'=>'Kaos Cotton Combed 24s Lengan Panjang','matrix'=>[]],
 'tunik-oversize34'=>['label'=>'Tunik Oversize Lengan 3/4','matrix'=>$assoc($tunik)],
 'hoodie'=>['label'=>'Custom Hoodie (belum ada tarif resmi)','matrix'=>[]],
 'sweatshirt'=>['label'=>'Custom Sweatshirt (belum ada tarif resmi)','matrix'=>[]],
 'mug-11oz'=>['label'=>'Custom Mug Putih 11 oz (harga satuan)','matrix'=>[]],
 ];
}
function kld_normalize_price_matrix($matrix){
 if(!is_array($matrix))return [];$result=[];
 foreach(kld_sizes() as $size){
  if(!isset($matrix[$size])||!is_array($matrix[$size]))continue;
  foreach(kld_tiers() as $tier=>$dimensions){
   $value=$matrix[$size][$tier]??null;
   if((is_int($value)||is_string($value))&&preg_match('/^[0-9]{1,9}$/D',(string)$value)&&(int)$value>0){$result[$size][$tier]=(int)$value;}
  }
 }
 return $result;
}
function kld_price_presets(){
 $defaults=kld_price_presets_default();$edited=get_option('kld_price_presets',[]);
 if(!is_array($edited))$edited=[];
 foreach($defaults as $key=>&$template){
  if(array_key_exists($key,$edited))$template['matrix']=kld_normalize_price_matrix($edited[$key]);
 }
 unset($template);
 // Sleeve supplement stays editable separately. Never alter owner-edited regular prices on plugin update.
 $extra=max(0,(int)get_option('kld_long_sleeve_extra',10000));
 $long=$defaults['reguler-24s']['matrix'];
 foreach($long as &$priceRow)foreach($priceRow as &$value)$value+=$extra;
 unset($priceRow,$value);
 $defaults['lengan-panjang-24s']['matrix']=$long;
 if(array_key_exists('lengan-panjang-24s',$edited))$defaults['lengan-panjang-24s']['matrix']=kld_normalize_price_matrix($edited['lengan-panjang-24s']);
 return $defaults;
}
/**
 * Common garment colours for the shop. Hex codes are DISPLAY APPROXIMATIONS,
 * not calibrated Knitto colour specifications or stock information.
 * Existing product colour names from v0.3.0 remain supported.
 */
function kld_color_catalog(){return [
 'Hitam'=>'#242527', 'Putih'=>'#f7f7f2', 'Navy'=>'#28354d', 'Army Green'=>'#586444',
 'Maroon'=>'#722f42', 'Merah'=>'#b83940', 'Abu'=>'#84878b', 'Abu Muda'=>'#c5c6c8',
 'Abu Tua'=>'#56595d', 'Hijau Botol'=>'#184e43', 'Royal Blue'=>'#275aa4',
 'Biru Muda'=>'#86b7da', 'Steel Blue'=>'#627f96', 'Mineral Green'=>'#91a69b',
 'Coklat Susu'=>'#b99676', 'Coklat Tua'=>'#654d42', 'Cream'=>'#e6d8b8',
 'Pink'=>'#e9a3bb', 'Kuning'=>'#e9c94d', 'Orange'=>'#e37c3e',
 'Ungu'=>'#77548d', 'Turkis'=>'#4fabb0', 'Hitam Reaktif'=>'#191a1c',
 'Putih Netral'=>'#faf9f3'
];}
/** Preserve custom names already used by older products rather than silently dropping them. */
function kld_product_color_palette($product){
 $palette=kld_color_catalog();
 if(kld_is_mug($product))return ['Putih'=>$palette['Putih']];
 $legacy=(string)get_post_meta($product,'_kld_colors',true);
 foreach(explode('|',$legacy) as $name){
  $name=trim(sanitize_text_field($name));
  if($name!==''&&strlen($name)<=80&&!isset($palette[$name]))$palette[$name]='#777777';
 }
 return $palette;
}
/** An empty array explicitly means every colour is disabled; no default fallback. */
function kld_product_active_colors($product){
 if(kld_is_mug($product))return ['Putih'];
 $palette=kld_product_color_palette($product);
 $saved=get_post_meta($product,'_kld_active_colors',true);
 if(is_array($saved)){
  $picked=[];
  foreach($saved as $value){
   if(!is_string($value))continue;
   $value=trim($value);
   if(isset($palette[$value]))$picked[$value]=true;
  }
  return array_values(array_filter(array_keys($palette),function($key)use($picked){return isset($picked[$key]);}));
 }
 $legacy=(string)get_post_meta($product,'_kld_colors',true);
 if(trim($legacy)!==''){
  $picked=[];
  foreach(explode('|',$legacy) as $name){$name=trim($name);if(isset($palette[$name]))$picked[$name]=true;}
  return array_values(array_filter(array_keys($palette),function($key)use($picked){return isset($picked[$key]);}));
 }
 // Keep existing defaults enabled for backwards compatibility. New catalogue colours are OFF.
 return ['Hitam','Putih','Army Green','Navy'];
}
/** Mug is its own product family: no clothing sizes or garment price fallback. */
function kld_is_mug($productId){return kld_product_preset_key($productId)==='mug-11oz';}
function kld_product_sizes($id){return kld_is_mug($id)?['ONE']:kld_sizes();}
function kld_product_tiers($id){return kld_is_mug($id)?['MUG'=>[18.0,8.5]]:kld_tiers();}
function kld_mug_price($id){
 $override=get_post_meta($id,'_kld_mug_price',true);
 if($override!=='' && $override!==null){
  if(!preg_match('/^[0-9]{1,9}$/D',(string)$override))return null;
  return (int)$override>0?(int)$override:null;
 }
 $default=get_option('kld_mug_price',25000);
 return is_numeric($default) && (int)$default>0?(int)$default:null;
}
function kld_product_preset_key($productId){
 $stored=(string)get_post_meta($productId,'_kld_price_preset',true);
 if(array_key_exists($stored,kld_price_presets()))return $stored;
 // Conservative auto-detection for an existing WooCommerce product without a chosen profile.
 $title=function_exists('get_the_title')?strtolower((string)get_the_title($productId)):'';
 if(strpos($title,'mug')!==false && preg_match('/11\s*oz/', $title))return 'mug-11oz';
 if(strpos($title,'hoodie')!==false)return 'hoodie';
 if(strpos($title,'sweatshirt')!==false)return 'sweatshirt';
 if(strpos($title,'tunik')!==false)return 'tunik-oversize34';
 if(strpos($title,'lengan panjang')!==false && strpos($title,'24s')!==false)return 'lengan-panjang-24s';
 if((strpos($title,'24s')!==false || strpos($title,'kaos reguler')!==false) && strpos($title,'kaos')!==false)return 'reguler-24s';
 return 'none';
}
function kld_product_prices($productId){
 if(kld_is_mug($productId)){ $price=kld_mug_price($productId);return $price?['ONE'=>['MUG'=>$price]]:[]; }
 $legacy=get_post_meta($productId,'_kld_matrix',true);
 $custom=get_post_meta($productId,'_kld_price_custom',true);
 // Preserve owner edits from 0.1.0 when upgrading; do not overwrite them.
 if(is_array($legacy) && (($custom==='yes') || ($custom==='' && count($legacy)>0)))return kld_normalize_price_matrix($legacy);
 $key=kld_product_preset_key($productId);
 $presets=kld_price_presets();
 return $presets[$key]['matrix']??[];
}
/** Real garment dimensions (width, height cm) per WooCommerce product. No generic 24s fallback for hoodies/tunics. */
function kld_garment_defaults(){return ['S'=>[43,65],'M'=>[46,69],'L'=>[49,73],'XL'=>[51,73],'2XL'=>[54,76],'3XL'=>[60,78],'4XL'=>[63,80],'5XL'=>[66,80]];}
function kld_product_garments($product){
 $stored=get_post_meta($product,'_kld_garments',true);$values=[];
 if(is_array($stored))foreach(kld_sizes() as $size){
  $a=$stored[$size]??null;if(!is_array($a))continue;
  $width=(float)($a[0]??0);$height=(float)($a[1]??0);
  if($width>=25&&$width<=110&&$height>=40&&$height<=150)$values[$size]=[$width,$height];
 }
 if($values)return $values;
 $preset=kld_product_preset_key($product);
 return in_array($preset,['reguler-24s','lengan-panjang-24s'],true)?kld_garment_defaults():[];
}
function kld_product_safe_margins($product){
 $raw=get_post_meta($product,'_kld_safe_margins',true);$d=['side'=>4,'top'=>3.33,'bottom'=>5];
 if(!is_array($raw))return $d;
 foreach($d as $key=>$v){$n=(float)($raw[$key]??$v);if(is_finite($n)&&$n>=0&&$n<=15)$d[$key]=$n;}
 return $d;
}
function kld_unit_price($id,$size,$front,$back){
 if(kld_is_mug($id))return $size==='ONE' && $front==='MUG' && $back==='none'?kld_mug_price($id):null;
 $matrix=kld_product_prices($id);$tiers=array_keys(kld_tiers());
 if(!isset($matrix[$size]))return null;
 $chosen=array_values(array_filter([$front,$back],function($tier)use($tiers){return in_array($tier,$tiers,true);}));
 if(!$chosen)return null;
 usort($chosen,function($a,$b)use($tiers){return array_search($a,$tiers,true)<=>array_search($b,$tiers,true);});
 $large=end($chosen);$base=$matrix[$size][$large]??null;
 if(!$base)return null;
 if(count($chosen)===2)$base+=(int)kld_surcharges()[$chosen[0]];
 return (int)$base;
}
add_action('admin_menu',function(){add_submenu_page('woocommerce','Kaoscetak Designer','Kaoscetak Designer','manage_woocommerce','kld-settings','kld_render_settings');});
add_action('admin_init',function(){
 if(!current_user_can('manage_woocommerce')||!isset($_POST['kld_save_settings']))return;
 check_admin_referer('kld_settings');
 update_option('kld_client_id',sanitize_text_field(wp_unslash($_POST['kld_client_id']??'')),false);
 update_option('kld_public_enabled',isset($_POST['kld_public_enabled'])?'yes':'no',false);
 $secret=trim((string)wp_unslash($_POST['kld_client_secret']??''));
 if($secret!=='')update_option('kld_client_secret',kld_encrypt_secret($secret),false);
 $rates=[];foreach(kld_tiers() as $tier=>$unused)$rates[$tier]=max(0,absint($_POST['rates'][$tier]??0));
 update_option('kld_surcharges',$rates,false);
 $submitted=isset($_POST['kld_presets'])&&is_array($_POST['kld_presets'])?wp_unslash($_POST['kld_presets']):[];
 $presets=[];
 foreach(kld_price_presets_default() as $key=>$def){
  if(in_array($key,['lengan-panjang-24s','mug-11oz'],true))continue; // standalone mug price, derived long-sleeve matrix
  $presets[$key]=kld_normalize_price_matrix($submitted[$key]??[]);
 }
 update_option('kld_price_presets',$presets,false);
 update_option('kld_long_sleeve_extra',max(0,absint($_POST['kld_long_sleeve_extra']??10000)),false);
 // Mug has a separate, globally editable base price. Never reset during plugin upgrades.
 if(isset($_POST['kld_mug_price']))update_option('kld_mug_price',max(0,absint($_POST['kld_mug_price'])),false);
 add_settings_error('kld','saved','Pengaturan disimpan. Harga tetap dipertahankan ketika plugin diperbarui.','updated');
});
function kld_render_settings(){
 if(!current_user_can('manage_woocommerce'))return;
 $connected=kld_drive_connected();$rates=kld_surcharges();$presets=kld_price_presets();
 echo '<div class="wrap"><h1>Kaoscetak Lite Designer</h1><p>Preset harga resmi langsung terisi, dapat diedit tanpa mengubah kode. Harga hoodie dan sweatshirt sengaja kosong sampai ditetapkan Owner.</p>';
 settings_errors('kld');
 echo '<form method="post">';wp_nonce_field('kld_settings');
 echo '<h2>Google Drive</h2><p>Status: <strong>'.($connected?'Terhubung':'Belum terhubung').'</strong></p>';
 echo '<table class="form-table"><tr><th>OAuth Client ID</th><td><input class="regular-text" name="kld_client_id" value="'.esc_attr(get_option('kld_client_id','')).'" autocomplete="off"></td></tr>';
 echo '<tr><th>OAuth Client Secret</th><td><input class="regular-text" type="password" name="kld_client_secret" autocomplete="new-password" value="" placeholder="Kosongkan untuk mempertahankan"></td></tr>';
 echo '<tr><th>Authorized redirect URI</th><td><code>'.esc_html(admin_url('admin-post.php?action=kld_oauth_callback')).'</code></td></tr></table>';
 echo '<h2>Publikasi</h2><p><label><input type="checkbox" name="kld_public_enabled" value="yes" '.checked(get_option('kld_public_enabled','no'),'yes',false).'> Aktifkan editor publik setelah diuji</label></p>';
 echo '<h2>Tambahan sisi kedua (Rp)</h2><table class="form-table">';
 foreach($rates as $tier=>$v)echo '<tr><th>'.esc_html($tier).'</th><td><input type="number" name="rates['.esc_attr($tier).']" value="'.esc_attr($v).'" min="0"></td></tr>';
 echo '</table><h2>Preset harga paket termasuk sablon 1 sisi</h2>';
 foreach($presets as $key=>$preset){
  if(in_array($key,['lengan-panjang-24s','mug-11oz'],true))continue;
  echo '<h3>'.esc_html($preset['label']).'</h3><table class="widefat striped" style="max-width:700px"><thead><tr><th>Size</th>';
  foreach(kld_tiers() as $t=>$d)echo '<th>'.esc_html($t).'</th>';echo '</tr></thead><tbody>';
  $sizes=$key==='tunik-oversize34'?['M','L','XL']:kld_sizes();
  foreach($sizes as $size){echo '<tr><th>'.esc_html($size).'</th>';foreach(kld_tiers() as $tier=>$d){$v=$preset['matrix'][$size][$tier]??'';echo '<td><input style="width:100%" type="number" min="0" name="kld_presets['.esc_attr($key).']['.esc_attr($size).']['.esc_attr($tier).']" value="'.esc_attr($v).'" placeholder="Belum ada harga"></td>';}echo '</tr>';}
  echo '</tbody></table>';
 }
 echo '<h2>Custom Mug Putih 11 oz</h2><p>Harga termasuk mug putih dan cetak desain; ukuran maksimal 18 × 8,5 cm, tidak mengikuti tarif kaos.</p>';
 echo '<p><label>Harga bawaan mug 11 oz (Rp/pcs): <input type="number" min="0" step="1" name="kld_mug_price" value="'.esc_attr(get_option('kld_mug_price',25000)).'"></label></p>';
 echo '<p><label>Tambahan lengan panjang Cotton Combed 24s (Rp): <input type="number" min="0" name="kld_long_sleeve_extra" value="'.esc_attr(get_option('kld_long_sleeve_extra',10000)).'"></label></p>';
 echo '<p><button class="button button-primary" name="kld_save_settings" value="1">Simpan Pengaturan</button></p></form>';
 if(get_option('kld_client_id')&&kld_decrypt_secret(get_option('kld_client_secret'))){
  echo '<p><a class="button" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=kld_connect'),'kld_connect')).'">Hubungkan / Ulangi Google Drive</a></p>';
 }
 if($connected){echo '<p>Folder master: <code>'.esc_html(get_option('kld_master_folder','')).'</code> | Order: <code>'.esc_html(get_option('kld_orders_folder','')).'</code></p>';
 echo '<p><a class="button" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=kld_test_drive'),'kld_test_drive')).'">Uji koneksi</a></p>';}
 echo '<p><strong>Editor admin:</strong> edit produk, aktifkan Kaoscetak Designer, lalu buka halaman produk sebagai admin. Preview tersedia meskipun Drive belum terkoneksi.</p></div>';
}
add_action('add_meta_boxes_product',function(){add_meta_box('kld_product_box','Kaoscetak Designer — Harga Per Produk','kld_product_box','product','normal','default');});
function kld_product_box($post){
 wp_nonce_field('kld_product_save','kld_product_nonce');
 $on=get_post_meta($post->ID,'_kld_enabled',true)==='yes';$m=kld_product_prices($post->ID);$current=kld_product_preset_key($post->ID);
 $custom=get_post_meta($post->ID,'_kld_price_custom',true)==='yes';
 echo '<p><label><input type="checkbox" name="kld_enabled" value="yes" '.checked($on,true,false).'> Aktifkan Custom Desain untuk produk ini</label></p>';
 echo '<p><label>Jenis produk / preset harga <select name="kld_price_preset"><option value="none">Pilih preset (atau harga khusus)</option>';
 foreach(kld_price_presets() as $key=>$preset)echo '<option value="'.esc_attr($key).'" '.selected($current,$key,false).'>'.esc_html($preset['label']).'</option>';
 echo '</select></label></p>';
 if($current==='mug-11oz'){echo '<p>Produk mug memakai <strong>satu harga per pcs</strong>, area cetak 18 × 8,5 cm, hanya warna Putih, tanpa size kaos.</p>';
 echo '<p><label>Harga khusus mug produk ini (Rp/pcs; kosong = harga bawaan): <input type="number" name="kld_mug_price_product" min="0" step="1" value="'.esc_attr(get_post_meta($post->ID,'_kld_mug_price',true)).'" placeholder="25000"></label></p>'; }
 else echo '<p><label><input type="checkbox" name="kld_price_custom" value="yes" '.checked($custom,true,false).'> Gunakan harga KHUSUS hanya untuk produk ini (isi tabel di bawah)</label></p>';
 if($current!=='mug-11oz'){
 echo '<table class="widefat striped"><thead><tr><th>Size</th>';foreach(kld_tiers() as $t=>$d)echo '<th>'.esc_html($t).' (Rp)</th>';echo '</tr></thead><tbody>';
 foreach(kld_sizes() as $size){echo '<tr><th>'.esc_html($size).'</th>';foreach(kld_tiers() as $tier=>$d){echo '<td><input type="number" min="0" style="width:100%" name="kld_matrix['.esc_attr($size).']['.esc_attr($tier).']" value="'.esc_attr($m[$size][$tier]??'').'"></td>';}echo '</tr>';}
 echo '</tbody></table><p>Harga kosong berarti size/kategori itu tidak dapat di-checkout, bukan memakai harga produk lain.</p>';
 }
 if($current==='mug-11oz')echo '<p>Warna tetap <strong>Putih</strong> (mug 11 oz), tanpa pengaturan warna pakaian.</p>';
 else {
  $palette=kld_product_color_palette($post->ID);$enabled=array_flip(kld_product_active_colors($post->ID));
  echo '<h4>Warna yang tersedia untuk produk ini</h4><p>Centang hanya warna yang benar-benar siap dijual. Warna nonaktif tidak muncul di editor dan tidak dapat dipesan, termasuk dari keranjang yang belum checkout. Warna produk lain tidak berubah.</p>';
  echo '<input type="hidden" name="kld_color_settings_present" value="1"><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(175px,1fr));gap:9px;max-width:850px">';
  foreach($palette as $name=>$hex){
   $active=isset($enabled[$name]);
   echo '<label style="display:flex;align-items:center;gap:8px;padding:8px;border:1px solid #ccd0d4;border-radius:5px;background:#fff">';
   echo '<input type="checkbox" name="kld_active_colors[]" value="'.esc_attr($name).'" '.checked($active,true,false).'>';
   echo '<span aria-hidden="true" style="width:24px;height:24px;flex:none;border:1px solid #a6a6a6;border-radius:50%;background:'.esc_attr($hex).'"></span>';
   echo '<span>'.esc_html($name).'</span></label>';
  }
  echo '</div><p><small>Warna swatch hanya perkiraan tampilan layar, bukan warna kain Knitto terkalibrasi. Warna dari pengaturan produk versi lama tetap dipertahankan.</small></p>';
 }
 $garments=kld_product_garments($post->ID);$margin=kld_product_safe_margins($post->ID);
 if($current!=='mug-11oz'){
 echo '<h4>Ukuran fisik badan pakaian (lebar x panjang, cm)</h4><p>Kaos 24s terisi bawaan. Hoodie, sweatshirt, dan tunik memerlukan ukuran aktual agar area aman tidak salah. Isi untuk produk ini saja.</p>';
 echo '<table class="widefat striped" style="max-width:540px"><thead><tr><th>Size</th><th>Lebar cm</th><th>Panjang cm</th></tr></thead><tbody>';
 foreach(kld_sizes() as $size){$v=$garments[$size]??['',''];echo '<tr><th>'.esc_html($size).'</th><td><input type="number" step="0.1" min="25" max="110" name="kld_garments['.esc_attr($size).'][0]" value="'.esc_attr($v[0]).'"></td><td><input type="number" step="0.1" min="40" max="150" name="kld_garments['.esc_attr($size).'][1]" value="'.esc_attr($v[1]).'"></td></tr>';}
 echo '</tbody></table><h4>Margin area aman cetak (cm)</h4>';
 foreach(['side'=>'Tepi kiri/kanan','top'=>'Dari kerah','bottom'=>'Dari bawah kaos'] as $key=>$label){echo '<label style="display:inline-block;margin-right:15px">'.esc_html($label).' <input type="number" min="0" max="15" step="0.1" name="kld_safe_margins['.esc_attr($key).']" value="'.esc_attr($margin[$key]).'"></label>';}
 }
 if($on)echo '<p><a class="button" href="'.esc_url(get_permalink($post->ID)).'" target="_blank" rel="noopener">Buka editor visual produk (login admin)</a></p>';
}
add_action('save_post_product',function($id){
 if(!isset($_POST['kld_product_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['kld_product_nonce'])),'kld_product_save'))return;
 if(!current_user_can('edit_post',$id)||wp_is_post_revision($id))return;
 update_post_meta($id,'_kld_enabled',isset($_POST['kld_enabled'])?'yes':'no');
 // Only the apparel checkbox field changes product colour availability.
 // On a mug/older editor/form without that field, retain existing saved colours.
 $key=sanitize_key(wp_unslash($_POST['kld_price_preset']??'none'));
 if($key!=='mug-11oz'&&isset($_POST['kld_color_settings_present'])){
  $palette=kld_product_color_palette($id);
  $input=isset($_POST['kld_active_colors'])&&is_array($_POST['kld_active_colors'])?wp_unslash($_POST['kld_active_colors']):[];
  $picked=[];
  foreach($input as $name){
   if(!is_string($name))continue;
   $name=sanitize_text_field($name);
   if(isset($palette[$name]))$picked[$name]=true;
  }
  // Empty array = out of stock for all colours, not the legacy default set.
  update_post_meta($id,'_kld_active_colors',array_keys($picked));
 }

 $key=sanitize_key(wp_unslash($_POST['kld_price_preset']??'none'));
 if(!array_key_exists($key,kld_price_presets()))$key='none';
 update_post_meta($id,'_kld_price_preset',$key);
 if($key==='mug-11oz' && array_key_exists('kld_mug_price_product',$_POST)){
  $value=trim((string)wp_unslash($_POST['kld_mug_price_product']));
  if($value==='')delete_post_meta($id,'_kld_mug_price');
  elseif(preg_match('/^[0-9]{1,9}$/D',$value))update_post_meta($id,'_kld_mug_price',(int)$value);
 }
 $custom=isset($_POST['kld_price_custom']);update_post_meta($id,'_kld_price_custom',$custom?'yes':'no');
 if($custom){$submitted=isset($_POST['kld_matrix'])&&is_array($_POST['kld_matrix'])?wp_unslash($_POST['kld_matrix']):[];
  update_post_meta($id,'_kld_matrix',kld_normalize_price_matrix($submitted));}
 $raw=isset($_POST['kld_garments'])&&is_array($_POST['kld_garments'])?wp_unslash($_POST['kld_garments']):[];
 $garments=[];
 foreach(kld_sizes() as $size){$dims=$raw[$size]??[];if(!is_array($dims))continue;
  $width=(float)($dims[0]??0);$height=(float)($dims[1]??0);
  if($width>=25&&$width<=110&&$height>=40&&$height<=150)$garments[$size]=[round($width,1),round($height,1)];
 }
 // Editing unrelated settings must not change sizes for products that have no dimension inputs.
 update_post_meta($id,'_kld_garments',$garments);
 $submitted=isset($_POST['kld_safe_margins'])&&is_array($_POST['kld_safe_margins'])?wp_unslash($_POST['kld_safe_margins']):[];
 $margin=['side'=>4,'top'=>3.33,'bottom'=>5];
 foreach($margin as $key=>$d){$n=(float)($submitted[$key]??$d);if(is_finite($n)&&$n>=0&&$n<=15)$margin[$key]=$n;}
 update_post_meta($id,'_kld_safe_margins',$margin);
});