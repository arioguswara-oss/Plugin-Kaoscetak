<?php
if (!defined('ABSPATH')) exit;
function kld_api_auth(WP_REST_Request $r){
 $id=absint($r->get_param('design_id'));
 $token=(string)$r->get_header('X-KLD-Token');
 $hash=(string)get_post_meta($id,'_kld_edit_hash',true);
 if(!$id||get_post_type($id)!=='kld_design'||get_post_meta($id,'_kld_deleted',true)==='yes'||strlen($token)!==64||!$hash||!hash_equals($hash,hash('sha256',$token)))return new WP_Error('no_access','Akses desain tidak valid.',['status'=>403]);
 return $id;
}
function kld_public_rate_limit($type,$limit,$ttl){
 $addr=(string)($_SERVER['REMOTE_ADDR']??'unknown');
 $key='kld_rl_'.substr(hash_hmac('sha256',$type.'|'.$addr,wp_salt('nonce')),0,36);
 $n=(int)get_transient($key);
 if($n>=$limit)return false;
 set_transient($key,$n+1,$ttl);return true;
}
function kld_api_error($code,$message,$status=400){return new WP_Error($code,$message,['status'=>$status]);}
add_action('rest_api_init',function(){
 foreach(['init','start-upload','confirm-upload','save','add-cart','request-verify','load'] as $route){
  register_rest_route('kld/v1','/'.$route,['methods'=>'POST','callback'=>'kld_api_'.str_replace('-','_',$route),'permission_callback'=>'__return_true']);
 }
});
function kld_api_init(WP_REST_Request $r){
 if(!kld_public_rate_limit('new',12,HOUR_IN_SECONDS))return kld_api_error('rate_limit','Batas pembuatan desain sementara tercapai. Coba lagi nanti.',429);
 $id=absint($r['product_id']);$p=wc_get_product($id);
 if(!$p||get_post_meta($id,'_kld_enabled',true)!=='yes')return kld_api_error('bad_product','Produk tidak tersedia untuk custom.');
 if(!kld_drive_connected())return kld_api_error('drive_not_ready','Penyimpanan produksi belum dikonfigurasi.');
 if(get_option('kld_public_enabled','no')!=='yes'&&!current_user_can('manage_woocommerce'))return kld_api_error('not_public','Editor masih dalam mode pengujian.',503);
 $token=bin2hex(random_bytes(32));
 $design=wp_insert_post(['post_type'=>'kld_design','post_status'=>'private','post_title'=>'KLD Draft '.gmdate('Y-m-d H:i:s')],true);
 if(is_wp_error($design))return kld_api_error('save_failed','Tidak dapat memulai desain.',500);
 update_post_meta($design,'_kld_edit_hash',hash('sha256',$token));
 update_post_meta($design,'_kld_product',$id);
 update_post_meta($design,'_kld_created',time());
 return rest_ensure_response(['design_id'=>$design,'token'=>$token]);
}
function kld_api_start_upload(WP_REST_Request $r){
 $id=kld_api_auth($r);if(is_wp_error($id))return $id;
 $side=(string)$r['side'];$size=absint($r['size']);$mime=(string)$r['mime'];
 if(!in_array($side,['front','back'],true)||!in_array($mime,['image/png','image/jpeg'],true)||$size<=0||$size>10485760)return kld_api_error('upload_invalid','Hanya PNG/JPEG maksimal 10 MB per sisi.');
 if(!kld_public_rate_limit('upload',35,HOUR_IN_SECONDS))return kld_api_error('rate_limit','Terlalu banyak percobaan upload.',429);
 $name='KLD-'.$id.'-'.strtoupper($side).'-'.bin2hex(random_bytes(6)).($mime==='image/png'?'.png':'.jpg');
 $url=kld_drive_initiate($name,$mime,$size);
 if(is_wp_error($url))return kld_api_error('drive_error',$url->get_error_message(),503);
 update_post_meta($id,'_kld_pending_'.$side,['mime'=>$mime,'size'=>$size,'name'=>$name,'at'=>time()]);
 return rest_ensure_response(['upload_url'=>$url]);
}
function kld_api_confirm_upload(WP_REST_Request $r){
 $id=kld_api_auth($r);if(is_wp_error($id))return $id;
 $side=(string)$r['side'];$fileid=(string)$r['file_id'];
 if(!in_array($side,['front','back'],true))return kld_api_error('side','Sisi tidak valid.');
 $pending=get_post_meta($id,'_kld_pending_'.$side,true);
 if(!is_array($pending)||time()-($pending['at']??0)>7200)return kld_api_error('expired','Sesi upload kedaluwarsa.');
 $data=kld_drive_verify($fileid,$pending['mime'],$pending['size'],$pending['name']);
 if(is_wp_error($data))return kld_api_error('verify_failed',$data->get_error_message(),409);
 $prior=get_post_meta($id,'_kld_file_'.$side,true);
 update_post_meta($id,'_kld_file_'.$side,$fileid);
 // Never delete an earlier master here: it might already be referenced by a paid order.
 update_post_meta($id,'_kld_file_details_'.$side,['name'=>$data['name'],'size'=>(int)$data['size'],'mime'=>$data['mimeType']]);
 delete_post_meta($id,'_kld_pending_'.$side);
 return rest_ensure_response(['verified'=>true,'name'=>$data['name']]);
}
function kld_validate_side($side){
 if(!is_array($side))return false;
 $tier=$side['tier']??'none';
 if($tier==='none')return ['tier'=>'none'];
 $tiers=kld_tiers();if(!isset($tiers[$tier]))return false;
 $limitW=$tiers[$tier][0];$limitH=$tiers[$tier][1];
 $p=$side['placement']??[];
 if(!is_array($p))return false;
 $offset=(float)($p['center_offset_cm']??0);$top=(float)($p['top_from_collar_cm']??10.83);
 if(!is_finite($offset)||!is_finite($top)||abs($offset)>40||$top<0||$top>70)return false;
 $result=['tier'=>$tier,'placement'=>['center_offset_cm'=>round($offset,2),'top_from_collar_cm'=>round($top,2)],'needs_bg_removal'=>!empty($side['needs_bg_removal'])];
 foreach(['image','text'] as $type){
  if(empty($side[$type])||!is_array($side[$type]))continue;
  $a=$side[$type];$x=(float)($a['x']??-1);$y=(float)($a['y']??-1);$w=(float)($a['w']??-1);$h=(float)($a['h']??-1);
  if(!is_finite($x)||!is_finite($y)||!is_finite($w)||!is_finite($h)||$x<0||$y<0||$w<=0||$h<=0||$x+$w>$limitW+0.02||$y+$h>$limitH+0.02)return false;
  $result[$type]=['x'=>round($x,2),'y'=>round($y,2),'w'=>round($w,2),'h'=>round($h,2)];
  if($type==='text'){
   $txt=sanitize_textarea_field((string)($a['value']??''));
   if((function_exists('mb_strlen')?mb_strlen($txt):strlen($txt))>160||substr_count($txt,"\n")>4)return false;
   $result[$type]['value']=$txt;
   $result[$type]['font']=in_array($a['font']??'', ['Arial','Georgia','Verdana','Tahoma'],true)?$a['font']:'Arial';
   $color=sanitize_hex_color($a['color']??'#ffffff');$result[$type]['color']=$color?:'#ffffff';
  }
 }
 if(empty($result['image'])&&empty($result['text']))return false;
 return $result;
}
/** Product default garment dimensions in cm. Admin/product-specific geometry will be configurable separately. */
function kld_default_garments(){return ['S'=>[43,65],'M'=>[46,69],'L'=>[49,73],'XL'=>[51,73],'2XL'=>[54,76],'3XL'=>[60,78],'4XL'=>[63,80],'5XL'=>[66,80]];}
function kld_design_fits_size($sides,$size){
 $measure=kld_default_garments()[$size]??null;
 if(!$measure||!is_array($sides))return false;
 foreach(['front','back'] as $key){
  $s=$sides[$key]??[];$tier=$s['tier']??'none';if($tier==='none')continue;
  $d=kld_tiers()[$tier]??null;if(!$d)return false;
  $place=$s['placement']??[];
  $center=(float)($place['center_offset_cm']??0);$top=(float)($place['top_from_collar_cm']??10.83);
  $bounds=[];
  foreach(['image','text'] as $kind)if(isset($s[$kind]))$bounds[]=$s[$kind];
  if(!$bounds)return false;
  $safeWidth=$measure[0]-8;
  foreach($bounds as $layer){
   $left=$center-$d[0]/2+(float)$layer['x'];$right=$left+(float)$layer['w'];
   $layerTop=$top+(float)$layer['y'];$layerBottom=$layerTop+(float)$layer['h'];
   if($left<-$safeWidth/2-0.03||$right>$safeWidth/2+0.03||$layerTop<3.33||$layerBottom>$measure[1]-5+0.03)return false;
  }
 }
 return true;
}
function kld_api_save(WP_REST_Request $r){
 $id=kld_api_auth($r);if(is_wp_error($id))return $id;
 $sides=$r->get_param('sides');if(!is_array($sides))return kld_api_error('sides','Data sisi tidak valid.');
 $f=kld_validate_side($sides['front']??[]);$b=kld_validate_side($sides['back']??[]);
 if($f===false||$b===false||($f['tier']==='none'&&$b['tier']==='none'))return kld_api_error('bounds','Gambar/teks melewati batas area cetak atau tidak ada area terpilih.');
 foreach(['front'=>$f,'back'=>$b] as $side=>$data){
  if($data['tier']!=='none'&&!empty($data['image'])&&!get_post_meta($id,'_kld_file_'.$side,true))return kld_api_error('upload_pending','File asli sisi '.($side==='front'?'depan':'belakang').' belum tersimpan di Google Drive.',409);
 }
 $previews=$r->get_param('previews');if(!is_array($previews))$previews=[];
 foreach(['front','back'] as $side){
  $base=(string)($previews[$side]??'');
  if($base==='')continue;
  if(strlen($base)>100000||strpos($base,'data:image/jpeg;base64,')!==0)return kld_api_error('preview','Preview terlalu besar / format tidak valid.');
  $bytes=base64_decode(substr($base,23),true);
  if(!$bytes||strlen($bytes)>61440||substr($bytes,0,2)!=="\xFF\xD8")return kld_api_error('preview_limit','Target preview maksimal 60 KB per sisi.');
  update_post_meta($id,'_kld_preview_'.$side,base64_encode($bytes));
 }
 // Small editable layer thumbnails; master PNG/JPEG remains private in Drive.
 // Only the holder of the separate design edit capability can read these previews.
 $artwork=$r->get_param('artwork');if(!is_array($artwork))$artwork=[];
 foreach(['front','back'] as $side){
  if(($side==='front'?$f:$b)['tier']==='none'){delete_post_meta($id,'_kld_artwork_'.$side);continue;}
  if(!array_key_exists($side,$artwork))continue; // Keep existing layer preview on metadata-only edits.
  $encoded=(string)$artwork[$side];
  if($encoded===''){delete_post_meta($id,'_kld_artwork_'.$side);continue;}
  if(strlen($encoded)>85000||strpos($encoded,'data:image/webp;base64,')!==0)return kld_api_error('artwork','Preview layer tidak valid.',400);
  $bytes=base64_decode(substr($encoded,23),true);
  if(!$bytes||strlen($bytes)>61440||substr($bytes,0,4)!=='RIFF'||substr($bytes,8,4)!=='WEBP')return kld_api_error('artwork','Preview layer harus WebP maksimal 60 KB.',400);
  update_post_meta($id,'_kld_artwork_'.$side,base64_encode($bytes));
 }
 update_post_meta($id,'_kld_sides',['front'=>$f,'back'=>$b]);
 $color=sanitize_text_field((string)$r['color']);
 if(!kld_color_allowed($id,$color))return kld_api_error('color','Warna kaos tidak tersedia.');
 update_post_meta($id,'_kld_color',$color);
 if(!get_post_meta($id,'_kld_owner_email',true))update_post_meta($id,'_kld_status','saved_pending_verify');
 else update_post_meta($id,'_kld_status','saved');
 return rest_ensure_response(['saved'=>true,'design_id'=>$id]);
}
/** Resolve allowed garment colors on the server: never trust a client-supplied option. */
function kld_color_allowed($design,$color){
 $product=absint(get_post_meta($design,'_kld_product',true));
 $colors=array_values(array_filter(array_map('trim',explode('|',(string)get_post_meta($product,'_kld_colors',true)))));
 if(!$colors)$colors=['Hitam','Putih','Army Green','Navy'];
 return in_array($color,$colors,true);
}
function kld_api_add_cart(WP_REST_Request $r){
 $id=kld_api_auth($r);if(is_wp_error($id))return $id;
 if(!in_array(get_post_meta($id,'_kld_status',true),['saved','saved_pending_verify','cart'],true))return kld_api_error('not_saved','Simpan desain dahulu sebelum checkout.');
 if(!function_exists('wc_load_cart'))return kld_api_error('wc','WooCommerce tidak aktif.',503);
 if(!WC()->cart)wc_load_cart();
 $product=absint(get_post_meta($id,'_kld_product',true));$p=wc_get_product($product);
 if(!$p||!$p->is_purchasable())return kld_api_error('bad_product','Produk tidak tersedia.');
 $sides=get_post_meta($id,'_kld_sides',true);
 $front=$sides['front']['tier']??'none';$back=$sides['back']['tier']??'none';
 foreach(['front','back'] as $side){
  if(!empty($sides[$side]['image'])&&!get_post_meta($id,'_kld_file_'.$side,true))return kld_api_error('file_missing','File master desain tidak tersedia; checkout ditolak.',409);
 }
 $qty=$r->get_param('quantities');$color=sanitize_text_field((string)$r['color']);
 if(!kld_color_allowed($id,$color))return kld_api_error('color','Warna kaos tidak tersedia.');
 if(!is_array($qty))return kld_api_error('qty','Jumlah tidak valid.');
 $validated=[];$count=0;
 foreach(kld_sizes() as $size){
  $raw=$qty[$size]??0;
  if(!is_int($raw)&&(!is_string($raw)||!preg_match('/^(0|[1-9][0-9]{0,2})$/',$raw)))return kld_api_error('qty','Jumlah harus bilangan bulat 0 sampai 100.');
  $n=(int)$raw;
  if($n<0||$n>100)return kld_api_error('qty','Maksimal 100 pcs per ukuran.');
  if(!$n)continue;
  if(!kld_design_fits_size($sides,$size))return kld_api_error('placement','Posisi area cetak tidak muat pada kaos size '.$size.'. Sesuaikan di editor sebelum checkout.');
  $unit=kld_unit_price($product,$size,$front,$back);if($unit===null)return kld_api_error('price','Harga untuk ukuran '.$size.' belum dikonfigurasi.');
  $validated[]=[$size,$n,$unit];$count+=$n;
 }
 if($count<1||$count>200)return kld_api_error('qty','Pilih 1–200 pcs.');
 // Variable products must resolve an actual purchasable variation.
 $items=[];
 foreach($validated as [$size,$n,$unit]){
  $variation=0;$attrs=[];
  if($p->is_type('variable')){
   $available=$p->get_available_variations();
   foreach($available as $v){
    $a=$v['attributes']??[];$matchesSize=false;$matchesColor=true;
    foreach($a as $attr=>$slug){
     $low=strtolower($attr);
     if(strpos($low,'size')!==false||strpos($low,'ukuran')!==false){$matchesSize=($slug===''||sanitize_title($slug)===sanitize_title($size));}
     if(strpos($low,'warna')!==false||strpos($low,'color')!==false){$matchesColor=($slug===''||sanitize_title($slug)===sanitize_title($color));}
    }
    if($matchesSize&&$matchesColor&&($v['is_purchasable']??false)){$variation=(int)$v['variation_id'];$attrs=$a;break;}
   }
   if(!$variation)return kld_api_error('variation','Tidak ditemukan variasi WooCommerce untuk size '.$size.' / warna '.$color.'.');
  }
  $items[]=[$size,$n,$unit,$variation,$attrs];
 }
 // Validate all variations and prices before mutating the cart. Roll back partial insertions.
 WC()->session->set_customer_session_cookie(true);
 $inserted=[];
 foreach($items as [$size,$n,$unit,$variation,$attrs]){
  $key=WC()->cart->add_to_cart($product,$n,$variation,$attrs,['kld_design_id'=>$id,'kld_size'=>$size,'kld_color'=>$color,'kld_unit_price'=>$unit,'kld_unique'=>wp_generate_uuid4()]);
  if(!$key){foreach($inserted as $added)WC()->cart->remove_cart_item($added);return kld_api_error('cart_error','Gagal memasukkan item ke keranjang. Tidak ada item parsial yang disimpan.',500);}
  $inserted[]=$key;
 }
 update_post_meta($id,'_kld_status','cart');
 return rest_ensure_response(['added'=>true,'count'=>$count,'cart_url'=>wc_get_cart_url()]);
}
function kld_api_request_verify(WP_REST_Request $r){
 $id=kld_api_auth($r);if(is_wp_error($id))return $id;
 if(!kld_public_rate_limit('verify',8,HOUR_IN_SECONDS))return kld_api_error('rate_limit','Coba lagi nanti.',429);
 $provided=sanitize_email((string)$r['email']);if(!is_email($provided))return kld_api_error('email','Alamat email tidak valid.');
 // Once verified, an editor session must NEVER be able to transfer ownership to another email.
 $existing=sanitize_email((string)get_post_meta($id,'_kld_owner_email',true));
 if($existing && strcasecmp($provided,$existing)!==0)return kld_api_error('owner_email','Desain sudah mempunyai pemilik. Pemulihan akses hanya dapat dikirim ke email pemilik.',403);
 $email=$existing?:$provided;
 $token=bin2hex(random_bytes(32));
 update_post_meta($id,'_kld_pending_owner',['email'=>$email,'hash'=>hash('sha256',$token),'expires'=>time()+1800]);
 $url=add_query_arg(['kld_owner'=>$id,'key'=>$token],home_url('/'));
 $sent=wp_mail($email,'Akses privat desain Kaoscetak',"Klik tautan berikut untuk memverifikasi email dan mengelola desain Anda (verifikasi pertama berlaku 30 menit):\n".$url."\n\nJANGAN BAGIKAN tautan ini karena berisi akses pemilik.");
 if(!$sent){delete_post_meta($id,'_kld_pending_owner');return kld_api_error('mail','Email belum dapat dikirim. Periksa pengaturan SMTP WordPress.',503);}
 return rest_ensure_response(['sent'=>true]);
}
/** Private editor reconstruction, authorised by the independent edit capability. */
function kld_api_load(WP_REST_Request $r){
 $id=kld_api_auth($r);if(is_wp_error($id))return $id;
 $product=absint(get_post_meta($id,'_kld_product',true));
 if(!$product||!wc_get_product($product))return kld_api_error('product_missing','Produk desain tidak tersedia.',404);
 $sides=get_post_meta($id,'_kld_sides',true);
 if(!is_array($sides))return kld_api_error('not_saved','Desain belum disimpan.',409);
 $layers=[];
 foreach(['front','back'] as $side){
  $image=(string)get_post_meta($id,'_kld_artwork_'.$side,true);
  if($image!=='' && strlen($image)<=85000 && preg_match('~^[A-Za-z0-9+/=]+$~D',$image)){
   $layers[$side]='data:image/webp;base64,'.$image;
  }
 }
 return rest_ensure_response(['design_id'=>$id,'product_id'=>$product,'sides'=>$sides,
  'color'=>(string)get_post_meta($id,'_kld_color',true),'layers'=>$layers]);
}
