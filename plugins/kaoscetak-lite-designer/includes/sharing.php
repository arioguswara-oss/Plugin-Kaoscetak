<?php
/** Private guest ownership, optional sharing, buying and independent editable forks. */
if(!defined('ABSPATH'))exit;
function kld_design_exists($id){return $id>0&&get_post_type($id)==='kld_design'&&get_post_meta($id,'_kld_deleted',true)!=='yes';}
function kld_verify_owner_token($id,$token){
 if(!kld_design_exists($id)||!is_string($token)||!preg_match('/^[a-f0-9]{64}$/D',$token))return false;
 $hash=(string)get_post_meta($id,'_kld_owner_hash',true);
 return $hash!==''&&hash_equals($hash,hash('sha256',$token));
}
/** Complete ownership only after the emailed verification key is presented. */
function kld_complete_owner_verification($id,$key){
 if(!kld_design_exists($id)||!is_string($key)||!preg_match('/^[a-f0-9]{64}$/D',$key))return false;
 $pending=get_post_meta($id,'_kld_pending_owner',true);
 if(!is_array($pending)||time()>(int)($pending['expires']??0))return false;
 $expected=(string)($pending['hash']??'');
 if(strlen($expected)!==64||!hash_equals($expected,hash('sha256',$key)))return false;
 $email=sanitize_email((string)($pending['email']??''));
 if(!is_email($email))return false;
 $owner=sanitize_email((string)get_post_meta($id,'_kld_owner_email',true));
 if($owner!==''&&strcasecmp($owner,$email)!==0)return false;
 update_post_meta($id,'_kld_owner_email',$email);
 update_post_meta($id,'_kld_owner_hash',hash('sha256',$key));
 update_post_meta($id,'_kld_status','saved');
 delete_post_meta($id,'_kld_pending_owner');
 return true;
}
function kld_share_token($id){
 if(!kld_design_exists($id)||!get_post_meta($id,'_kld_owner_email',true))return '';
 $seed=(string)get_post_meta($id,'_kld_share_seed',true);
 return preg_match('/^[a-f0-9]{40}$/D',$seed)?hash_hmac('sha256','kld-share|'.$id.'|'.$seed,wp_salt('auth')):'';
}
function kld_share_allowed($id,$token){$expected=kld_share_token($id);return $expected!==''&&is_string($token)&&strlen($token)===64&&hash_equals($expected,$token);}
function kld_share_url($id){$token=kld_share_token($id);return $token?add_query_arg(['kld_share'=>$id,'token'=>$token],home_url('/')):'';}
function kld_guest_start($title){
 nocache_headers();header('Referrer-Policy: no-referrer',true);header('X-Robots-Tag: noindex, nofollow',true);
 echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>'.esc_html($title).'</title><style>body{margin:0;background:#eef2f7;font:15px system-ui,sans-serif;color:#213040}main{max-width:720px;margin:25px auto;background:white;padding:20px;border-radius:14px}img{max-width:100%;height:auto}button,input,select{font:inherit;padding:9px;margin:5px 3px}button{cursor:pointer}label{display:inline-flex;gap:5px;align-items:center}.kld-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}</style></head><body><main>';
}
function kld_guest_end(){echo '</main></body></html>';exit;}
function kld_guest_previews($id){
 $sides=get_post_meta($id,'_kld_sides',true);if(!is_array($sides))return;
 echo '<div class="kld-grid">';foreach(['front'=>'Depan','back'=>'Belakang'] as $side=>$label){
  if($side==='back'&&($sides['front']['tier']??'none')==='MUG')continue;
  if($side==='front'&&($sides['front']['tier']??'none')==='MUG')$label='Bidang Mug';
  echo '<div><strong>'.esc_html($label.' — '.($sides[$side]['tier']??'none')).'</strong>';
  $raw=get_post_meta($id,'_kld_preview_'.$side,true);
  if($raw)echo '<p><img loading="lazy" alt="'.esc_attr($label).'" src="data:image/jpeg;base64,'.esc_attr($raw).'"></p>';
  echo '</div>';
 }echo '</div>';
}
function kld_fork_editable($source){
 if(!kld_design_exists($source))return false;
 $sides=get_post_meta($source,'_kld_sides',true);if(!is_array($sides))return false;
 foreach(['front','back'] as $side)if(!empty($sides[$side]['image'])&&!get_post_meta($source,'_kld_artwork_'.$side,true))return false;
 return true;
}
function kld_clone_shared_design($source,$color){
 if(!kld_design_exists($source))return new WP_Error('missing','Desain sumber tidak tersedia.');
 $product=absint(get_post_meta($source,'_kld_product',true));
 $new=wp_insert_post(['post_type'=>'kld_design','post_status'=>'private','post_title'=>'KLD Salinan '.gmdate('Y-m-d H:i:s')],true);
 if(is_wp_error($new))return $new;
 $token=bin2hex(random_bytes(32));update_post_meta($new,'_kld_edit_hash',hash('sha256',$token));
 update_post_meta($new,'_kld_product',$product);update_post_meta($new,'_kld_sides',get_post_meta($source,'_kld_sides',true));
 update_post_meta($new,'_kld_color',$color);update_post_meta($new,'_kld_status','saved_pending_verify');
 update_post_meta($new,'_kld_fork_of',$source);update_post_meta($new,'_kld_created',time());
 foreach(['front','back'] as $side){
  foreach(['_kld_file_','_kld_file_details_','_kld_preview_','_kld_artwork_'] as $prefix){
   $value=get_post_meta($source,$prefix.$side,true);if($value)update_post_meta($new,$prefix.$side,$value);
  }
 }
 return [$new,$token];
}
add_action('template_redirect',function(){
 if(!isset($_GET['kld_owner'])&&!isset($_GET['kld_share']))return;
 if(isset($_GET['kld_owner'])){
  $id=absint($_GET['kld_owner']);$key=sanitize_text_field(wp_unslash($_GET['key']??''));
  if(!kld_verify_owner_token($id,$key)){status_header(403);kld_guest_start('Akses ditolak');echo '<p>Tautan pengelolaan tidak valid.</p>';kld_guest_end();}
  kld_guest_start('Kelola Desain Kaoscetak');echo '<h1>Kelola Desain #'.esc_html($id).'</h1><p>Tautan ini PRIVAT; jangan dibagikan.</p>';kld_guest_previews($id);
  $shared=kld_share_url($id);
  if($shared)echo '<p><strong>Link publik aktif:</strong> <input style="width:90%" readonly value="'.esc_attr($shared).'"></p>';
  else echo '<p>Desain masih privat.</p>';
  echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="kld_owner_action"><input type="hidden" name="design_id" value="'.esc_attr($id).'"><input type="hidden" name="key" value="'.esc_attr($key).'">';
  wp_nonce_field('kld_owner_'.$id);
  if($shared)echo '<button name="operation" value="revoke">Nonaktifkan link berbagi</button>';
  else echo '<button name="operation" value="share">Aktifkan link berbagi</button>';
  echo '<p><label><input type="checkbox" name="confirm_delete" value="yes"> Hapus dari koleksi (arsip order tidak ikut dihapus)</label></p><button name="operation" value="delete">Hapus desain</button></form>';
  kld_guest_end();
 }
 $id=absint($_GET['kld_share']??0);$token=sanitize_text_field(wp_unslash($_GET['token']??''));
 if(get_option('kld_public_enabled','no')!=='yes'||!kld_share_allowed($id,$token)){status_header(404);kld_guest_start('Tidak tersedia');echo '<h1>Desain tidak tersedia</h1>';kld_guest_end();}
 $product=absint(get_post_meta($id,'_kld_product',true));$p=function_exists('wc_get_product')?wc_get_product($product):null;
 kld_guest_start('Desain Kaoscetak');echo '<h1>Desain Kaoscetak #'.esc_html($id).'</h1><p>Desain asli tidak dapat diedit oleh penerima link.</p>';
 kld_guest_previews($id);
 if(!$p||get_post_meta($product,'_kld_enabled',true)!=='yes'){echo '<p>Produk tidak tersedia untuk checkout.</p>';kld_guest_end();}
 echo '<h2>'.esc_html($p->get_name()).'</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="kld_share_buy"><input type="hidden" name="design_id" value="'.esc_attr($id).'"><input type="hidden" name="token" value="'.esc_attr($token).'">';
 wp_nonce_field('kld_share_buy_'.$id);
 $colors=kld_is_mug($product)?['Putih']:array_values(array_filter(array_map('trim',explode('|',(string)get_post_meta($product,'_kld_colors',true)))));
 if(!$colors)$colors=['Hitam','Putih','Army Green','Navy'];
 echo '<p><label>Warna <select name="color">';foreach($colors as $c)echo '<option value="'.esc_attr($c).'">'.esc_html($c).'</option>';echo '</select></label></p><div class="kld-grid">';
 foreach(array_keys(kld_product_prices($product)) as $size)echo '<label>'.esc_html($size==='ONE'?'Jumlah pcs':$size).' <input style="width:80px" type="number" min="0" max="100" name="quantities['.esc_attr($size).']" value="0"></label>';
 echo '</div><p><label>Email pembeli untuk verifikasi repeat order <input name="buyer_email" type="email" placeholder="email@contoh.com"></label></p>';
 echo '<p>Diskon repeat 5% hanya setelah email terverifikasi dan ada riwayat order berbayar.</p><button type="submit">Masukkan ke keranjang</button></form>';
 if(kld_fork_editable($id)){
  echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="kld_share_fork"><input type="hidden" name="design_id" value="'.esc_attr($id).'"><input type="hidden" name="token" value="'.esc_attr($token).'">';
  wp_nonce_field('kld_share_fork_'.$id);echo '<button type="submit">Edit Salinan Desain</button></form>';
 }
 kld_guest_end();
});
function kld_owner_action(){
 $id=absint($_POST['design_id']??0);$key=sanitize_text_field(wp_unslash($_POST['key']??''));
 if(!kld_verify_owner_token($id,$key))wp_die('Akses pemilik ditolak.','Akses ditolak',['response'=>403]);
 check_admin_referer('kld_owner_'.$id);
 $action=sanitize_key(wp_unslash($_POST['operation']??''));
 if($action==='share'){
  if(get_option('kld_public_enabled','no')!=='yes')wp_die('Editor publik belum diaktifkan.');
  if(!kld_share_token($id))update_post_meta($id,'_kld_share_seed',bin2hex(random_bytes(20)));
 }elseif($action==='revoke'){delete_post_meta($id,'_kld_share_seed');}
 elseif($action==='delete'){
  if(($_POST['confirm_delete']??'')!=='yes')wp_die('Centang konfirmasi penghapusan.');
  delete_post_meta($id,'_kld_share_seed');update_post_meta($id,'_kld_deleted','yes');update_post_meta($id,'_kld_status','deleted');
  kld_guest_start('Desain dihapus');echo '<p>Desain dihapus dari koleksi. Arsip order tetap dipertahankan.</p>';kld_guest_end();
 }else wp_die('Aksi tidak dikenal.');
 wp_safe_redirect(add_query_arg(['kld_owner'=>$id,'key'=>$key],home_url('/')),303);exit;
}
add_action('admin_post_kld_owner_action','kld_owner_action');add_action('admin_post_nopriv_kld_owner_action','kld_owner_action');
function kld_share_buy(){
 $source=absint($_POST['design_id']??0);$token=sanitize_text_field(wp_unslash($_POST['token']??''));
 if(!kld_share_allowed($source,$token)||get_option('kld_public_enabled','no')!=='yes')wp_die('Link berbagi tidak valid.', 'Akses ditolak',['response'=>403]);
 check_admin_referer('kld_share_buy_'.$source);
 if(!kld_public_rate_limit('sharebuy',20,HOUR_IN_SECONDS))wp_die('Batas percobaan telah tercapai.');
 $p=absint(get_post_meta($source,'_kld_product',true));$product=wc_get_product($p);
 if(!$product||!$product->is_purchasable()||get_post_meta($p,'_kld_enabled',true)!=='yes')wp_die('Produk tidak tersedia.');
 $color=sanitize_text_field(wp_unslash($_POST['color']??''));if(!kld_color_allowed($source,$color))wp_die('Warna tidak tersedia.');
 $qty=isset($_POST['quantities'])&&is_array($_POST['quantities'])?wp_unslash($_POST['quantities']):[];
 $copy=kld_clone_shared_design($source,$color);if(is_wp_error($copy))wp_die('Salinan desain gagal dibuat.');
 [$id,$edit]=$copy;
 $req=new WP_REST_Request('POST','/kld/v1/add-cart');$req->set_header('X-KLD-Token',$edit);
 $req->set_param('design_id',$id);$req->set_param('quantities',$qty);$req->set_param('color',$color);
 $result=kld_api_add_cart($req);
 if(is_wp_error($result)){wp_delete_post($id,true);wp_die(esc_html($result->get_error_message()),'Keranjang gagal',['response'=>409]);}
 $buyer=sanitize_email((string)wp_unslash($_POST['buyer_email']??''));
 if(is_email($buyer)){
  $vr=new WP_REST_Request('POST','/kld/v1/request-verify');$vr->set_header('X-KLD-Token',$edit);$vr->set_param('design_id',$id);$vr->set_param('email',$buyer);
  kld_api_request_verify($vr);
 }
 wp_safe_redirect(wc_get_cart_url(),303);exit;
}
add_action('admin_post_kld_share_buy','kld_share_buy');add_action('admin_post_nopriv_kld_share_buy','kld_share_buy');
function kld_share_fork(){
 $source=absint($_POST['design_id']??0);$token=sanitize_text_field(wp_unslash($_POST['token']??''));
 if(!kld_share_allowed($source,$token)||get_option('kld_public_enabled','no')!=='yes')wp_die('Link berbagi tidak valid.');
 check_admin_referer('kld_share_fork_'.$source);
 if(!kld_public_rate_limit('fork',12,HOUR_IN_SECONDS))wp_die('Batas salinan terlampaui.');
 if(!kld_fork_editable($source))wp_die('Desain belum mempunyai preview untuk diedit.');
 $product=absint(get_post_meta($source,'_kld_product',true));if(get_post_meta($product,'_kld_enabled',true)!=='yes')wp_die('Produk tidak tersedia.');
 $color=(string)get_post_meta($source,'_kld_color',true);if(!kld_color_allowed($source,$color))wp_die('Warna tidak valid.');
 $clone=kld_clone_shared_design($source,$color);if(is_wp_error($clone))wp_die('Salinan gagal.');
 [$id,$edit]=$clone;
 // Fragment is not sent to the web server or in Referer headers.
 wp_safe_redirect(get_permalink($product).'#kld-fork='.$id.'.'.$edit,303);exit;
}
add_action('admin_post_kld_share_fork','kld_share_fork');add_action('admin_post_nopriv_kld_share_fork','kld_share_fork');
