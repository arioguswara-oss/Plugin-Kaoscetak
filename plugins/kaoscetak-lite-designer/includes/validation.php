<?php
/** Shared fail-closed cart/checkout checks. Masters remain private and off hosting. */
if (!defined('ABSPATH')) exit;
/** Reject coercions such as negative IDs, floats, arrays, exponents and overflow. */
function kld_positive_id($value){
 if(is_int($value))return $value>0?$value:0;
 if(!is_string($value)||!preg_match('/^[1-9][0-9]*$/D',$value))return 0;
 $id=(int)$value;return $id>0&&(string)$id===$value?$id:0;
}
/** Match native cart product/variation objects and attributes to the design choices. */
function kld_cart_product_matches($item,$product,$size,$color){
 $parent=wc_get_product($product);$data=$item['data']??null;
 if(!$parent||!is_object($data)||!method_exists($data,'get_id'))return false;
 $raw=$item['variation_id']??0;
 $variation=$raw===0||$raw==='0'?0:kld_positive_id($raw);
 if(!$variation&&$raw!==0&&$raw!=='0')return false;
 if($parent->is_type('simple'))return !$variation&&(int)$data->get_id()===$product&&empty($item['variation']);
 if(!$parent->is_type('variable')||!$variation||(int)$data->get_id()!==$variation||!method_exists($data,'get_parent_id')||(int)$data->get_parent_id()!==$product||!$data->is_purchasable())return false;
 $chosen=$item['variation']??null;$native=$data->get_variation_attributes();$schema=$parent->get_attributes();
 if(!is_array($chosen)||!is_array($native)||count($chosen)!==count($native))return false;
 $hasSize=false;
 foreach($native as $key=>$restricted){
  if(!is_string($key)||!is_string($restricted)||!array_key_exists($key,$chosen)||!is_string($chosen[$key]))return false;
  if(preg_match('/size|ukuran/i',$key)){$choice=$size;$hasSize=true;}
  elseif(preg_match('/warna|color/i',$key))$choice=$color;
  else return false;
  $field=$schema[substr($key,10)]??null;if(!$field)return false;
  $expected=$field->is_taxonomy()?sanitize_title($choice):'';
  if(!$field->is_taxonomy())foreach($field->get_options() as $option)if(sanitize_title($option)===sanitize_title($choice)){$expected=(string)$option;break;}
  if($expected===''||$chosen[$key]!==$expected||($restricted!==''&&$restricted!==$expected))return false;
 }
 return $hasSize;
}
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
  if(!array_key_exists('kld_design_id',$item))continue;
  $id=kld_positive_id($item['kld_design_id']);
  if(!$id){$errors['invalid']='Design ID tidak valid. Hapus item custom dan tambahkan kembali.';continue;}
  $q=$item['quantity']??null;$size=$item['kld_size']??null;$color=$item['kld_color']??null;
  if((!is_int($q)&&(!is_string($q)||!preg_match('/^[1-9][0-9]{0,2}$/D',$q)))||$q<1||$q>100||!is_string($size)||!is_string($color)){$errors[$id]='Size, warna atau jumlah tidak valid.';continue;}
  $counts[$id]=($counts[$id]??0)+(int)$q;
  if($counts[$id]>200){$errors[$id]='Maksimal 200 pcs per desain.';continue;}
  if(!isset($checked[$id]))$checked[$id]=kld_design_ready($id,$remote);
  if(is_wp_error($checked[$id])){$errors[$id]=$checked[$id]->get_error_message();continue;}
  $product=absint(get_post_meta($id,'_kld_product',true));$s=get_post_meta($id,'_kld_sides',true);
  if(kld_positive_id($item['product_id']??null)!==$product||!kld_cart_product_matches($item,$product,$size,$color)||!kld_design_fits_size($s,$size,$product)||!kld_color_allowed($id,$color)||kld_unit_price($product,$size,$s['front']['tier'],$s['back']['tier'])===null)$errors[$id]='Produk, variasi, size, warna, jumlah atau area cetak tidak lagi valid. Perbarui item custom.';
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
function kld_lock_release($key,$token){
 $old=get_option($key);
 if(!is_array($old)||($old['token']??'')!==$token)return;
 // A successor may acquire the expired lease between this read and the delete.
 global $wpdb;
 $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s",$key,maybe_serialize($old)));
 wp_cache_delete($key,'options');
}
