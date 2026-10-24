<?php
/**
 * Guest ownership and optional public design links.
 * The owner token is issued by EMAIL only, never in a public API response.
 * Public tokens give preview/purchase access, never design editing privileges.
 */
if (!defined('ABSPATH')) exit;

function kld_design_exists($id) {
 return $id > 0 && get_post_type($id) === 'kld_design' && get_post_meta($id, '_kld_deleted', true) !== 'yes';
}
function kld_verify_owner_token($id, $token) {
 if (!kld_design_exists($id) || !is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) return false;
 $digest = hash('sha256', $token);
 $existing = (string)get_post_meta($id, '_kld_owner_hash', true);
 if ($existing && hash_equals($existing, $digest)) return true;
 $pending = get_post_meta($id, '_kld_pending_owner', true);
 if (!is_array($pending) || time() > (int)($pending['expires'] ?? 0) || empty($pending['email'])) return false;
 if (!hash_equals((string)($pending['hash'] ?? ''), $digest)) return false;
 $email = sanitize_email((string)$pending['email']);
 $verified = (string)get_post_meta($id, '_kld_owner_email', true);
 if ($verified && strcasecmp($email, $verified) !== 0) return false;
 update_post_meta($id, '_kld_owner_email', $email);
 update_post_meta($id, '_kld_owner_hash', $digest);
 update_post_meta($id, '_kld_status', 'saved');
 delete_post_meta($id, '_kld_pending_owner');
 return true;
}
function kld_share_token($id) {
 if (!kld_design_exists($id) || !get_post_meta($id, '_kld_owner_email', true)) return '';
 $seed = (string)get_post_meta($id, '_kld_share_seed', true);
 if (!preg_match('/^[a-f0-9]{40}$/D', $seed)) return '';
 return hash_hmac('sha256', 'kld-share-v1|'.$id.'|'.$seed, wp_salt('auth'));
}
function kld_share_allowed($id, $token) {
 $expected = kld_share_token($id);
 return $expected !== '' && is_string($token) && strlen($token) === 64 && hash_equals($expected, $token);
}
function kld_share_url($id) {
 $token = kld_share_token($id);
 return $token ? add_query_arg(['kld_share'=>$id,'token'=>$token], home_url('/')) : '';
}
function kld_render_preview_sides($id) {
 $layout = get_post_meta($id, '_kld_sides', true);
 if (!is_array($layout)) return;
 echo '<div style="display:flex;gap:1rem;flex-wrap:wrap">';
 foreach (['front'=>'Depan','back'=>'Belakang'] as $side=>$name) {
  $tier = (string)($layout[$side]['tier'] ?? 'none');
  echo '<div style="flex:1;min-width:135px"><strong>'.esc_html($name.' — '.$tier).'</strong><br>';
  $thumbnail = get_post_meta($id, '_kld_preview_'.$side, true);
  if ($thumbnail) echo '<img src="data:image/jpeg;base64,'.esc_attr($thumbnail).'" alt="'.esc_attr($name).'" style="width:100%;max-width:240px;height:auto" />';
  else echo '<p>Belum ada preview sisi ini.</p>';
  echo '</div>';
 }
 echo '</div>';
}
function kld_guest_page_start($title) {
 nocache_headers(); header('Referrer-Policy: no-referrer'); header('X-Robots-Tag: noindex, nofollow', true);
 echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>'.esc_html($title).'</title><style>body{font-family:system-ui,sans-serif;margin:0;background:#f4f5f6;color:#222}main{max-width:720px;margin:24px auto;background:white;border-radius:14px;padding:24px;box-shadow:0 3px 12px #0001}button,input,select{font:inherit;padding:9px;margin:4px}button{cursor:pointer}label{display:inline-flex;align-items:center;gap:5px}p{line-height:1.5}.kld-warning{color:#9a3300}</style></head><body><main>';
}
function kld_guest_page_end() { echo '</main></body></html>'; exit; }

add_action('template_redirect', function () {
 if (!isset($_GET['kld_owner']) && !isset($_GET['kld_share'])) return;
 if (isset($_GET['kld_owner'])) {
  $id = absint($_GET['kld_owner']);
  $key = sanitize_text_field(wp_unslash($_GET['key'] ?? ''));
  if (!kld_verify_owner_token($id, $key)) { status_header(403); kld_guest_page_start('Akses tidak tersedia');echo '<h1>Tautan tidak valid atau kedaluwarsa.</h1>'; kld_guest_page_end(); }
  kld_guest_page_start('Kelola Desain Kaoscetak');
  echo '<h1>Kelola Desain #'.esc_html($id).'</h1><p>Desain ini hanya bisa dikelola melalui tautan khusus yang dikirim ke email pemilik. Jangan bagikan tautan ini.</p>';
  kld_render_preview_sides($id);
  $public = kld_share_url($id);
  if(get_option('kld_public_enabled','no')!=='yes')echo '<p class="kld-warning">Mode publik belum aktif. Link berbagi belum dapat diakses pengunjung sampai pengujian plugin selesai.</p>';
  if ($public) echo '<p><strong>Link berbagi aktif:</strong> <input style="width:95%;box-sizing:border-box" aria-label="Link berbagi" readonly value="'.esc_attr($public).'" /></p>';
  else echo '<p><strong>Privasi:</strong> Privat. Belum dibagikan.</p>';
  echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="kld_owner_action"><input type="hidden" name="design_id" value="'.esc_attr($id).'"><input type="hidden" name="key" value="'.esc_attr($key).'">';
  wp_nonce_field('kld_owner_'.$id);
  if ($public) echo '<button type="submit" name="operation" value="revoke">Nonaktifkan link berbagi</button>';
  else echo '<button type="submit" name="operation" value="share">Aktifkan Bagikan Desain</button>';
  echo '<p class="kld-warning">Hapus desain akan menghilangkannya dari koleksi pemilik. Arsip order yang sudah dibuat tetap disimpan.</p><label><input type="checkbox" name="confirm_delete" value="yes">Saya memahami konsekuensi penghapusan.</label><button type="submit" name="operation" value="delete">Hapus desain</button></form>';
  kld_guest_page_end();
 }
 $id=absint($_GET['kld_share'] ?? 0);
 $token=sanitize_text_field(wp_unslash($_GET['token'] ?? ''));
 if (!kld_share_allowed($id, $token) || get_option('kld_public_enabled','no') !== 'yes') { status_header(404); kld_guest_page_start('Desain tidak tersedia');echo '<h1>Desain tidak tersedia.</h1>'; kld_guest_page_end(); }
 $product = absint(get_post_meta($id, '_kld_product', true));
 $p = function_exists('wc_get_product') ? wc_get_product($product) : null;
 kld_guest_page_start('Desain Kaos Kaoscetak');
 echo '<h1>Desain Kaos #'.esc_html($id).'</h1><p>Preview desain yang dibagikan. Desain asli tidak dapat diedit melalui link ini.</p>';
 kld_render_preview_sides($id);
 if (!$p || !get_post_meta($product,'_kld_enabled',true) || get_option('kld_public_enabled','no') !== 'yes') {
  echo '<p>Pemesanan melalui link ini belum diaktifkan.</p>'; kld_guest_page_end();
 }
 echo '<p><strong>Produk:</strong> '.esc_html($p->get_name()).'</p><p>Pilih size, jumlah, dan warna. Harga akan divalidasi kembali oleh WooCommerce.</p>';
 echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="kld_share_buy"><input type="hidden" name="design_id" value="'.esc_attr($id).'"><input type="hidden" name="token" value="'.esc_attr($token).'">';
 wp_nonce_field('kld_share_buy_'.$id);
 $colors = array_values(array_filter(array_map('trim', explode('|', (string)get_post_meta($product,'_kld_colors',true)))));
 if (!$colors) $colors=['Hitam','Putih','Army Green','Navy'];
 echo '<p><label>Warna <select name="color">';foreach ($colors as $c) echo '<option value="'.esc_attr($c).'">'.esc_html($c).'</option>';echo '</select></label></p>';
 echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:6px">';
 foreach (array_keys(kld_product_prices($product)) as $size) echo '<label>'.esc_html($size).' <input type="number" min="0" max="100" name="quantities['.esc_attr($size).']" value="0" style="width:72px"></label>';
 echo '</div><p><button type="submit">Masukkan ke Keranjang</button></p></form>';
 if(kld_fork_has_artwork($id)){
  echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
  echo '<input type="hidden" name="action" value="kld_share_fork"><input type="hidden" name="design_id" value="'.esc_attr($id).'">';
  echo '<input type="hidden" name="token" value="'.esc_attr($token).'">';
  wp_nonce_field('kld_share_fork_'.$id);
  echo '<p><button type="submit">Edit Salinan Desain</button></p></form>';
 }else{
  echo '<p>Salinan untuk diedit memerlukan preview gambar editable. Desain lama ini tetap bisa dibeli.</p>';
 }
 kld_guest_page_end();
});

function kld_owner_post_action() {
 $id=absint($_POST['design_id'] ?? 0);
 $token=sanitize_text_field(wp_unslash($_POST['key'] ?? ''));
 if (!kld_verify_owner_token($id, $token)) wp_die('Tidak memiliki hak mengelola desain.', 'Akses ditolak', ['response'=>403]);
 check_admin_referer('kld_owner_'.$id);
 $op = sanitize_key(wp_unslash($_POST['operation'] ?? ''));
 switch ($op) {
  case 'share':
   if(get_option('kld_public_enabled','no')!=='yes')wp_die('Fitur publik belum diaktifkan oleh admin.','Belum tersedia',['response'=>409]);
   if (!kld_share_token($id)) update_post_meta($id, '_kld_share_seed', bin2hex(random_bytes(20)));
   break;
  case 'revoke':
   delete_post_meta($id, '_kld_share_seed');
   break;
  case 'delete':
   if (($_POST['confirm_delete'] ?? '') !== 'yes') wp_die('Centang konfirmasi penghapusan terlebih dahulu.', 'Konfirmasi', ['response'=>400]);
   delete_post_meta($id, '_kld_share_seed');
   update_post_meta($id, '_kld_deleted', 'yes');
   update_post_meta($id, '_kld_status', 'deleted');
   break;
  default: wp_die('Aksi tidak dikenal.', 'Permintaan invalid', ['response'=>400]);
 }
 if ($op === 'delete') { kld_guest_page_start('Desain dihapus');echo '<h1>Desain telah dihapus dari koleksi.</h1><p>Arsip pesanan yang sudah tercatat tetap aman.</p>'; kld_guest_page_end(); }
 wp_safe_redirect(add_query_arg(['kld_owner'=>$id,'key'=>$token],home_url('/')),303);exit;
}
add_action('admin_post_kld_owner_action','kld_owner_post_action');
add_action('admin_post_nopriv_kld_owner_action','kld_owner_post_action');

/** Purchasing a shared design always clones a private immutable order candidate.
 * The recipient gets no edit token and no Drive URL. Revoke doesn't alter this copy.
 */
function kld_share_buy() {
 $id=absint($_POST['design_id'] ?? 0);
 $token=sanitize_text_field(wp_unslash($_POST['token'] ?? ''));
 if(!kld_share_allowed($id, $token) || get_option('kld_public_enabled','no') !== 'yes')wp_die('Tautan desain tidak aktif.', 'Akses ditolak', ['response'=>403]);
 check_admin_referer('kld_share_buy_'.$id);
 if(!kld_public_rate_limit('share_buy',20,HOUR_IN_SECONDS))wp_die('Terlalu banyak percobaan pemesanan.', 'Batas permintaan', ['response'=>429]);
 $product=absint(get_post_meta($id,'_kld_product',true));
 $p=wc_get_product($product);
 if(!$p || !$p->is_purchasable() || get_post_meta($product,'_kld_enabled',true)!=='yes')wp_die('Produk tidak tersedia.', 'Produk', ['response'=>409]);
 $color=sanitize_text_field(wp_unslash($_POST['color'] ?? ''));
 $available=array_values(array_filter(array_map('trim',explode('|',(string)get_post_meta($product,'_kld_colors',true)))));
 if(!$available)$available=['Hitam','Putih','Army Green','Navy'];
 if(!in_array($color,$available,true))wp_die('Warna tidak tersedia.', 'Warna', ['response'=>400]);
 $qty=isset($_POST['quantities'])&&is_array($_POST['quantities'])?wp_unslash($_POST['quantities']):[];
 $qtyClean=[];$count=0;
 foreach(kld_sizes() as $size) {
  $raw=$qty[$size]??'0';
  if(!is_string($raw)||!preg_match('/^(0|[1-9][0-9]{0,2})$/D',$raw))wp_die('Jumlah per size harus bilangan bulat 0–100.', 'Jumlah', ['response'=>400]);
  $n=(int)$raw;if($n>100)wp_die('Maksimal 100 pcs per size.', 'Jumlah', ['response'=>400]);
  if(!$n)continue;
  $sides=get_post_meta($id,'_kld_sides',true);
  if(!kld_design_fits_size($sides,$size))wp_die('Desain tidak muat pada size '.$size.'.', 'Area cetak', ['response'=>400]);
  if(kld_unit_price($product,$size,$sides['front']['tier']??'none',$sides['back']['tier']??'none')===null)wp_die('Harga size tidak tersedia.', 'Harga', ['response'=>409]);
  $qtyClean[$size]=$n;$count+=$n;
 }
 if($count<1||$count>200)wp_die('Pilih 1–200 pcs.', 'Jumlah', ['response'=>400]);
 // The new private candidate is independent of later source edits/revocation.
 $copy=kld_clone_shared_design($id,$color);
 if(is_wp_error($copy))wp_die('Tidak dapat membuat salinan pesanan.', 'Penyimpanan', ['response'=>500]);
 [$new,$editToken]=$copy;
 // Reuse one WooCommerce cart validation path, not a second price calculation.
 $req=new WP_REST_Request('POST','/kld/v1/add-cart');
 $req->set_header('X-KLD-Token',$editToken);
 $req->set_param('design_id',$new);
 $req->set_param('quantities',$qtyClean);
 $req->set_param('color',$color);
 $response=kld_api_add_cart($req);
 if(is_wp_error($response)) {
  wp_delete_post($new,true); // Does not delete shared master files in Drive.
  wp_die(esc_html($response->get_error_message()),'Keranjang belum siap',['response'=>409]);
 }
 $data=$response instanceof WP_REST_Response?$response->get_data():$response;
 if(empty($data['added']))wp_die('Gagal menyiapkan keranjang.', 'Keranjang', ['response'=>500]);
 wp_safe_redirect(wc_get_cart_url(),303);exit;
}
add_action('admin_post_kld_share_buy','kld_share_buy');
add_action('admin_post_nopriv_kld_share_buy','kld_share_buy');

/** Copy references to immutable original files without uploading them again. */
function kld_clone_shared_design($source, $color) {
 if(!kld_design_exists($source))return new WP_Error('source_missing','Desain sumber tidak tersedia.');
 $product=absint(get_post_meta($source,'_kld_product',true));
 $new=wp_insert_post(['post_type'=>'kld_design','post_status'=>'private','post_title'=>'KLD Salinan '.gmdate('Y-m-d H:i:s')],true);
 if(is_wp_error($new))return $new;
 $secret=bin2hex(random_bytes(32));
 update_post_meta($new,'_kld_edit_hash',hash('sha256',$secret));
 update_post_meta($new,'_kld_product',$product);
 update_post_meta($new,'_kld_sides',get_post_meta($source,'_kld_sides',true));
 update_post_meta($new,'_kld_color',$color);
 update_post_meta($new,'_kld_status','saved_pending_verify');
 update_post_meta($new,'_kld_fork_of',$source);
 update_post_meta($new,'_kld_created',time());
 foreach(['front','back'] as $side) {
  foreach(['_kld_file_','_kld_file_details_','_kld_preview_','_kld_artwork_'] as $prefix) {
   $v=get_post_meta($source,$prefix.$side,true);
   if($v)update_post_meta($new,$prefix.$side,$v);
  }
 }
 return [$new,$secret];
}
/** Avoid lying about the visible design when the older source has no artwork layer. */
function kld_fork_has_artwork($source){
 if(!kld_design_exists($source))return false;
 $layout=get_post_meta($source,'_kld_sides',true);
 if(!is_array($layout))return false;
 foreach(['front','back'] as $side){
  if(!empty($layout[$side]['image']) && !get_post_meta($source,'_kld_artwork_'.$side,true))return false;
 }
 return true;
}
function kld_share_fork(){
 $source=absint($_POST['design_id']??0);
 $token=sanitize_text_field(wp_unslash($_POST['token']??''));
 if(get_option('kld_public_enabled','no')!=='yes'||!kld_share_allowed($source,$token)){
  wp_die('Tautan berbagi tidak aktif.','Akses ditolak',['response'=>403]);
 }
 check_admin_referer('kld_share_fork_'.$source);
 if(!kld_public_rate_limit('fork',12,HOUR_IN_SECONDS))wp_die('Batas salinan sementara tercapai.','Coba lagi',['response'=>429]);
 if(!kld_fork_has_artwork($source))wp_die('Preview gambar untuk edit salinan tidak tersedia.','Belum tersedia',['response'=>409]);
 $product=absint(get_post_meta($source,'_kld_product',true));
 $p=function_exists('wc_get_product')?wc_get_product($product):null;
 if(!$p || get_post_meta($product,'_kld_enabled',true)!=='yes')wp_die('Produk tidak tersedia.','Produk',['response'=>404]);
 $color=(string)get_post_meta($source,'_kld_color',true);
 if(!kld_color_allowed($source,$color))wp_die('Warna desain tidak tersedia.','Produk',['response'=>409]);
 $copy=kld_clone_shared_design($source,$color);
 if(is_wp_error($copy))wp_die('Gagal membuat salinan desain.','Penyimpanan',['response'=>500]);
 [$id,$editToken]=$copy;
 // Fragment not transmitted to WordPress or through HTTP Referer.
 // The editor clears it from browser history as soon as it is read.
 wp_safe_redirect(get_permalink($product).'#kld-fork='.$id.'.'.$editToken,303);
 exit;
}
add_action('admin_post_kld_share_fork','kld_share_fork');
add_action('admin_post_nopriv_kld_share_fork','kld_share_fork');
