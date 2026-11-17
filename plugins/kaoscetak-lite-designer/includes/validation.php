<?php
/** Shared fail-closed cart/checkout checks. Masters remain private and off hosting. */
if (!defined('ABSPATH')) exit;
function kld_design_locked($id){return get_post_meta($id,'_kld_cart_locked',true)==='yes'||get_post_meta($id,'_kld_status',true)==='cart';}
function kld_design_ready($id,$remote=false){
 if(!kld_design_exists($id))return kld_api_error('design_missing','Desain tidak tersedia.',409);
 if(!kld_drive_connected())return kld_api_error('drive_unavailable','Penyimpanan produksi belum terhubung.',409);
 $product=absint(get_post_meta($id,'_kld_product',true));$p=wc_get_product($product);
 if(!$p||get_post_meta($product,'_kld_enabled',true)!=='yes'||!$p->is_purchasable()||!$p->is_type(['simple','variable']))return kld_api_error('product','Produk custom tidak tersedia.',409);
 $sides=get_post_meta($id,'_kld_sides',true);
 if(!is_array($sides))return kld_api_error('not_saved','Simpan desain terlebih dahulu.',409);
 $active=false;
 foreach(['front','back'] as $side){
  $layout=kld_validate_side($sides[$side]??[],$product,$side);
  if($layout===false)return kld_api_error('layout','Tata letak desain tidak valid.',409);
  if($layout['tier']==='none')continue;
  $active=true;
  if(empty($layout['image']))continue;
  $file=(string)get_post_meta($id,'_kld_file_'.$side,true);
  $d=get_post_meta($id,'_kld_file_details_'.$side,true);
  if(get_post_meta($id,'_kld_pending_'.$side,true)||!preg_match('/^[a-zA-Z0-9_-]{10,200}$/D',$file)||!is_array($d)||empty($d['name'])||!in_array($d['mime']??'',['image/png','image/jpeg'],true)||($d['size']??0)<=0||$d['size']>10485760)return kld_api_error('file_pending','File master belum tersimpan valid. Selesaikan atau ulangi upload.',409);
  if($remote){$check=kld_drive_verify($file,$d['mime'],$d['size'],$d['name'],$d['folder']??'');if(is_wp_error($check))return kld_api_error('file_unavailable','File master tidak dapat diverifikasi di Drive. Coba lagi sebelum checkout.',409);}
 }
 return $active?true:kld_api_error('empty','Desain tidak boleh kosong.',409);
}
function kld_validate_cart_designs($cart,$remote=false){
 $errors=[];$checked=[];$counts=[];
 foreach($cart->get_cart() as $item){
  if(empty($item['kld_design_id']))continue;
  $id=absint($item['kld_design_id']);
  $counts[$id]=($counts[$id]??0)+(float)($item['quantity']??0);
  if($counts[$id]>200){$errors[$id]='Maksimal 200 pcs per desain.';continue;}
  if(!isset($checked[$id]))$checked[$id]=kld_design_ready($id,$remote);
  if(is_wp_error($checked[$id])){$errors[$id]=$checked[$id]->get_error_message();continue;}
  $product=absint(get_post_meta($id,'_kld_product',true));$s=get_post_meta($id,'_kld_sides',true);
  $q=$item['quantity']??0;$size=$item['kld_size']??'';
  if((int)($item['product_id']??0)!==$product||!is_numeric($q)||!is_finite((float)$q)||(float)$q!=(int)$q||$q<1||$q>100||!kld_design_fits_size($s,$size,$product)||!kld_color_allowed($id,$item['kld_color']??'')||kld_unit_price($product,$size,$s['front']['tier'],$s['back']['tier'])===null)$errors[$id]='Size, warna, jumlah atau area cetak tidak lagi valid. Perbarui item custom.';
 }
 return array_values($errors);
}
/** One local atomic writer per design/order. Expired leases require a fresh request. */
function kld_lock_acquire($key,$ttl=180){
 $token=wp_generate_uuid4();$value=['token'=>$token,'expires'=>time()+$ttl];
 if(add_option($key,$value,'','no'))return $token;
 $old=get_option($key);
 if(is_array($old)&&($old['expires']??PHP_INT_MAX)<time()){
  // Compare-and-delete prevents removal of a different writer's renewed lease.
  global $wpdb;
  $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s",$key,maybe_serialize($old)));
  wp_cache_delete($key,'options');
 }
 return false;
}
function kld_lock_release($key,$token){if((get_option($key)['token']??'')===$token)delete_option($key);}
