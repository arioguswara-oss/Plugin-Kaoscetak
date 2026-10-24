<?php
if (!defined('ABSPATH')) exit;
add_action('woocommerce_before_calculate_totals',function($cart){
 if(is_admin()&&!wp_doing_ajax())return;
 foreach($cart->get_cart() as $item){
  if(!empty($item['kld_design_id'])&&isset($item['kld_unit_price'])){
   $id=absint($item['kld_design_id']);
   $sides=get_post_meta($id,'_kld_sides',true);$product=absint(get_post_meta($id,'_kld_product',true));
   if(!is_array($sides)||!$product)continue;
   $price=kld_unit_price($product,$item['kld_size'],$sides['front']['tier']??'none',$sides['back']['tier']??'none');
   if($price!==null)$item['data']->set_price($price);
  }
 }
},50);
add_filter('woocommerce_get_item_data',function($data,$item){
 if(empty($item['kld_design_id']))return $data;
 $s=get_post_meta($item['kld_design_id'],'_kld_sides',true);
 $data[]=['key'=>'Ukuran kaos','value'=>sanitize_text_field($item['kld_size'])];
 $data[]=['key'=>'Warna kaos','value'=>sanitize_text_field($item['kld_color'])];
 $data[]=['key'=>'Cetak depan','value'=>sanitize_text_field($s['front']['tier']??'none')];
 $data[]=['key'=>'Cetak belakang','value'=>sanitize_text_field($s['back']['tier']??'none')];
 return $data;
},10,2);
add_action('woocommerce_checkout_create_order_line_item',function($line,$key,$item,$order){
 if(empty($item['kld_design_id']))return;
 $id=absint($item['kld_design_id']);
 $line->add_meta_data('_kld_design_id',$id,true);
 $line->add_meta_data('Ukuran Kaos',sanitize_text_field($item['kld_size']),true);
 $line->add_meta_data('Warna Kaos',sanitize_text_field($item['kld_color']),true);
 $sides=get_post_meta($id,'_kld_sides',true);
 $line->add_meta_data('Cetak Depan',$sides['front']['tier']??'Tanpa cetak',true);
 $line->add_meta_data('Cetak Belakang',$sides['back']['tier']??'Tanpa cetak',true);
},10,4);
function kld_order_designs($order){
 $found=[];
 foreach($order->get_items() as $item){$id=absint($item->get_meta('_kld_design_id',true));if($id && !isset($found[$id]))$found[$id]=get_post_meta($id,'_kld_sides',true);}
 return $found;
}
function kld_render_order_admin($order){
 if(!$order instanceof WC_Order)return;
 $designs=kld_order_designs($order);if(!$designs)return;
 echo '<div class="order_data_column_container" style="clear:both;padding:15px 0"><h3>Kaoscetak - Desain & Produksi</h3>';
 foreach($designs as $id=>$sides){
  echo '<div style="border:1px solid #ddd;margin:8px 0;padding:12px"><strong>Desain #'.esc_html($id).'</strong><div style="display:flex;gap:15px;flex-wrap:wrap">';
  foreach(['front'=>'Depan','back'=>'Belakang'] as $side=>$name){
   $base=get_post_meta($id,'_kld_preview_'.$side,true);
   echo '<div><div>'.esc_html($name).' — '.esc_html($sides[$side]['tier']??'none').'</div>';
   if($base)echo '<img alt="'.esc_attr($name).'" src="data:image/jpeg;base64,'.esc_attr($base).'" width="150" height="150" style="object-fit:contain;border:1px solid #eee">';
   else echo '<em>Tanpa preview</em>';
   echo '</div>';
  }
  echo '</div></div>';
 }
 $folder=$order->get_meta('_kld_drive_order_folder');
 if($folder){echo '<p><strong>File Produksi:</strong> <a href="'.esc_url('https://drive.google.com/drive/folders/'.$folder).'" target="_blank" rel="noopener noreferrer">Buka folder Google Drive</a></p>';} 
 else {echo '<p><strong>File Produksi:</strong> Belum tersedia (menunggu pembayaran / siap produksi).</p>';
 $link=wp_nonce_url(admin_url('admin-post.php?action=kld_ready_order&order='.$order->get_id()),'kld_ready_'.$order->get_id());
 echo '<p><a class="button" href="'.esc_url($link).'">Tandai Siap Produksi & Siapkan Drive</a></p>';}
 $error=$order->get_meta('_kld_drive_last_error');if($error)echo '<p style="color:#a00">Status sinkronisasi terakhir: '.esc_html($error).'</p>';
 echo '</div>';
}
add_action('woocommerce_admin_order_data_after_order_details','kld_render_order_admin');
add_action('woocommerce_order_status_processing','kld_auto_production',20);
add_action('woocommerce_order_status_completed','kld_auto_production',20);
function kld_auto_production($order_id){
 $order=wc_get_order($order_id);
 if(!$order||!$order->is_paid())return;
 $r=kld_prepare_production($order);
 if(is_wp_error($r))$order->add_order_note('Kaoscetak: penyiapan Google Drive gagal: '.$r->get_error_message());
}
add_action('admin_post_kld_ready_order',function(){
 $id=absint($_GET['order']??0);
 if(!$id||!current_user_can('edit_shop_orders'))wp_die('Tidak memiliki hak akses.');
 check_admin_referer('kld_ready_'.$id);
 $order=wc_get_order($id);if(!$order)wp_die('Pesanan tidak ditemukan.');
 $r=kld_prepare_production($order);
 if(is_wp_error($r))$order->add_order_note('Kaoscetak: sinkronisasi produksi gagal: '.$r->get_error_message());
 wp_safe_redirect($order->get_edit_order_url());exit;
});
function kld_prepare_production($order){
 if(!$order)return new WP_Error('order','Pesanan tidak ditemukan.');
 $existing=$order->get_meta('_kld_drive_order_folder');
 if($existing)return $existing;
 $designs=kld_order_designs($order);if(!$designs)return new WP_Error('no_design','Tidak ada desain Kaoscetak pada pesanan ini.');
 if(!kld_drive_connected())return new WP_Error('no_drive','Google Drive belum terhubung.');
 $lockkey='kld_prod_lock_'.$order->get_id();
 if(get_transient($lockkey))return new WP_Error('locked','Sinkronisasi sedang berjalan.');
 set_transient($lockkey,1,180);
 try {
  $roots=kld_drive_roots();if(is_wp_error($roots))throw new RuntimeException($roots->get_error_message());
  $folder=$order->get_meta('_kld_drive_order_pending');
  if(!$folder){$folder=kld_drive_create_folder('ORDER-'.$order->get_order_number(),$roots['orders']);if(is_wp_error($folder))throw new RuntimeException($folder->get_error_message());$order->update_meta_data('_kld_drive_order_pending',$folder);$order->save();}
  // Shortcuts are idempotent using a per-file completion marker on the order.
  foreach($designs as $id=>$sides){
   foreach(['front','back'] as $side){
    $fileid=get_post_meta($id,'_kld_file_'.$side,true);if(!$fileid)continue;
    $tag='_kld_sc_'.$id.'_'.$side;
    if($order->get_meta($tag))continue;
    $r=kld_drive_shortcut($fileid,$folder,'DESAIN-'.$id.'-'.strtoupper($side));
    if(is_wp_error($r))throw new RuntimeException($r->get_error_message());
    $order->update_meta_data($tag,$r['id']);$order->save();
   }
  }
  foreach($designs as $designid=>$layout){
   $guidekey='_kld_guide_uploaded_'.(int)$designid;
   if($order->get_meta($guidekey))continue;
   $pdf=kld_make_guide_pdf($order,[$designid=>$layout]);
   if(is_wp_error($pdf))throw new RuntimeException($pdf->get_error_message());
   $r=kld_drive_upload_small('ORDER-'.$order->get_order_number().'-DESAIN-'.$designid.'-PANDUAN.pdf','application/pdf',$pdf,$folder);
   if(is_wp_error($r))throw new RuntimeException($r->get_error_message());
   $order->update_meta_data($guidekey,$r['id']);$order->save();
  }
  $order->update_meta_data('_kld_drive_order_folder',$folder);
  $order->delete_meta_data('_kld_drive_last_error');$order->save();
  $order->add_order_note('Kaoscetak: folder Google Drive dan PDF panduan produksi tersedia.');
  return $folder;
 }catch(Throwable $e){
  $order->update_meta_data('_kld_drive_last_error',$e->getMessage());$order->save();
  return new WP_Error('production_failed',$e->getMessage());
 }finally{delete_transient($lockkey);}
}
function kld_drive_upload_small($name,$mime,$bytes,$parent){
 if(strlen($bytes)>1000000)return new WP_Error('too_large','Panduan produksi melebihi 1 MB.');
 $token=kld_drive_token();if(is_wp_error($token))return $token;
 $boundary='kld'.bin2hex(random_bytes(8));
 $metadata=wp_json_encode(['name'=>$name,'mimeType'=>$mime,'parents'=>[$parent]]);
 $body='--'.$boundary."\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n".$metadata."\r\n";
 $body.='--'.$boundary."\r\nContent-Type: ".$mime."\r\n\r\n".$bytes."\r\n--".$boundary."--\r\n";
 $r=wp_remote_post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,name',[
  'timeout'=>40,'headers'=>['Authorization'=>'Bearer '.$token,'Content-Type'=>'multipart/related; boundary='.$boundary], 'body'=>$body]);
 if(is_wp_error($r))return $r;
 $data=json_decode(wp_remote_retrieve_body($r),true);
 if(wp_remote_retrieve_response_code($r)<200||wp_remote_retrieve_response_code($r)>=300||empty($data['id']))return new WP_Error('drive_pdf','Upload PDF panduan gagal (HTTP '.wp_remote_retrieve_response_code($r).').');
 return $data;
}
/** Minimal valid PDF with native JPEG mockups (no heavyweight image rendering on hosting). */
function kld_make_guide_pdf($order,$designs){
 $objects=[];
 $add=function($s)use(&$objects){$objects[]=$s;return count($objects);};
 $catalog=$add('');$pages=$add('');$page=$add('');$font=$add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
 $xobjs=[];$stream='';$y=790;
 $esc=function($s){$s=remove_accents((string)$s);$s=preg_replace('/[^\x20-\x7E]/','?',$s);return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$s);};
 $line=function($text,$size=11,$x=40)use(&$stream,&$y,$esc){$stream.="BT /F1 {$size} Tf {$x} {$y} Td (".$esc($text).") Tj ET\n";$y-=($size+8);};
 $line('KAOSCETAK - PANDUAN PRODUKSI',17);
 $line('ORDER #'.$order->get_order_number().' | '.wp_date('d-m-Y H:i'),10);
 $line('Jenis output: FILE ASLI + PANDUAN. Siapkan RIP/DTF di komputer produksi.',9);
 $line('Ukuran fisik mengikuti tata letak desain dan kategori area yang dipilih.',9);
 foreach($order->get_items() as $item){
  $id=absint($item->get_meta('_kld_design_id',true));if(!$id||!isset($designs[$id]))continue;
  $line('Produk: '.$item->get_name().' | Qty '.$item->get_quantity().' | '.$item->get_meta('Ukuran Kaos').' | '.$item->get_meta('Warna Kaos'),10);
 }
 foreach($designs as $id=>$sides){
  if($y<350){$line('Lihat rincian desain tambahan di metadata order WooCommerce.',10);break;}
  $line('DESAIN #'.$id,13);
  foreach(['front'=>'DEPAN','back'=>'BELAKANG'] as $side=>$name){
   $d=$sides[$side]??[];
   $line($name.' | Kategori: '.($d['tier']??'none'),11);
   if(($d['tier']??'none')!=='none'){
    $pos=$d['placement']??[];
    $line('Letak area cetak: '.($pos['center_offset_cm']??0).' cm dari tengah; '.($pos['top_from_collar_cm']??10.83).' cm di bawah kerah',9);
   }
   if(!empty($d['needs_bg_removal']))$line('PERHATIAN: hapus background dari FILE ASLI di komputer produksi.',9);
   foreach(['image'=>'Gambar','text'=>'Teks'] as $layer){
    if(!empty($d[$layer])){
     $v=$d[$layer];$line($layer.': '.($v['w']??'?').' x '.($v['h']??'?').' cm | X '.($v['x']??'?').' cm | Y '.($v['y']??'?').' cm',9);
     if($layer==='text')$line('Teks: '.preg_replace('/\s+/',' ',(string)($v['value']??'')),9);
    }
   }
  }
  $imgs=[];
  foreach(['front','back'] as $side){
   $raw=base64_decode((string)get_post_meta($id,'_kld_preview_'.$side,true),true);
   if(!$raw||strlen($raw)>61440||substr($raw,0,2)!=="\xFF\xD8")continue;
   $info=@getimagesizefromstring($raw);if(!$info||$info[2]!==IMAGETYPE_JPEG)continue;
   $imgName='Im'.(count($xobjs)+1);
   $obj=$add('<< /Type /XObject /Subtype /Image /Width '.(int)$info[0].' /Height '.(int)$info[1].' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($raw).' >>' ."\nstream\n".$raw."\nendstream");
   $xobjs[$imgName]=$obj;$imgs[]=$imgName;
  }
  if($imgs){
   $y-=148;$x=45;
   foreach($imgs as $name){$stream.="q 130 0 0 130 {$x} {$y} cm /{$name} Do Q\n";$x+=170;}
  }
  $y-=12;
 }
 $line('Catatan: ukuran cetak final dan profil warna dicek operator sebelum produksi.',9);
 $resources='<< /Font << /F1 '.$font.' 0 R >>';
 if($xobjs){$resources.=' /XObject <<';foreach($xobjs as $name=>$id)$resources.=' /'.$name.' '.$id.' 0 R';$resources.=' >>';}
 $resources.=' >>';
 $content=$add('<< /Length '.strlen($stream).' >>'."\nstream\n".$stream."endstream");
 $objects[$catalog-1]='<< /Type /Catalog /Pages '.$pages.' 0 R >>';
 $objects[$pages-1]='<< /Type /Pages /Kids ['.$page.' 0 R] /Count 1 >>';
 $objects[$page-1]='<< /Type /Page /Parent '.$pages.' 0 R /MediaBox [0 0 595 842] /Resources '.$resources.' /Contents '.$content.' 0 R >>';
 $pdf="%PDF-1.4\n%KLD\n";$offsets=[0];
 foreach($objects as $i=>$obj){$offsets[]=strlen($pdf);$pdf.=($i+1)." 0 obj\n".$obj."\nendobj\n";}
 $xref=strlen($pdf);$pdf.='xref' ."\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
 for($i=1;$i<=count($objects);$i++)$pdf.=sprintf('%010d 00000 n ', $offsets[$i])."\n";
 $pdf.='trailer << /Size '.(count($objects)+1).' /Root '.$catalog.' 0 R >>'."\nstartxref\n".$xref."\n%%EOF";
 return $pdf;
}