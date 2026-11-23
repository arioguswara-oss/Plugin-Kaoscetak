<?php
defined('ABSPATH') || exit;
require_once __DIR__.'/production-typography.php';

/** Guides may flatten preview alpha onto white; DTF artwork never uses this conversion. */
function sanro_studio_guide_preview_jpeg($preview) {
    if (!is_array($preview)||!is_string($preview['bytes']??null)||strlen($preview['bytes'])>51200)return ['valid'=>false,'error'=>'guide_preview_invalid'];
    $info=@getimagesizefromstring($preview['bytes']);
    if(!$info||($info['mime']??null)!==($preview['mime']??null)||!in_array($info['mime'],['image/jpeg','image/png','image/webp'],true)||$info[0]*$info[1]>4000000)return ['valid'=>false,'error'=>'guide_preview_invalid'];
    if($info['mime']==='image/jpeg'&&($info['channels']??3)===3)return $preview+['valid'=>true];
    if(!function_exists('imagecreatefromstring'))return ['valid'=>false,'error'=>'guide_gd_required'];
    $image=@imagecreatefromstring($preview['bytes']);if(!$image)return ['valid'=>false,'error'=>'guide_preview_decode_failed'];
    $canvas=imagecreatetruecolor($info[0],$info[1]);if(!$canvas){imagedestroy($image);return ['valid'=>false,'error'=>'guide_canvas_failed'];}
    try{imagefill($canvas,0,0,imagecolorallocate($canvas,255,255,255));imagealphablending($canvas,true);imagecopy($canvas,$image,0,0,0,0,$info[0],$info[1]);ob_start();$ok=imagejpeg($canvas,null,85);$bytes=ob_get_clean();return $ok&&is_string($bytes)?['valid'=>true,'mime'=>'image/jpeg','bytes'=>$bytes]:['valid'=>false,'error'=>'guide_preview_encode_failed'];}
    finally{imagedestroy($image);imagedestroy($canvas);}
}

/** Searchable Unicode PDF, embedded licensed TrueType + explicit CID-to-Unicode map. */
function sanro_studio_unicode_production_pdf($manifest,$previews) {
    $font=sanro_studio_font_metrics();if(!$font)return ['valid'=>false,'error'=>'guide_font_unavailable'];
    $pages=[];$chars=[];$width=static function($cp)use($font){$gid=$font['map'][$cp];return 1000*($font['widths'][$gid]??end($font['widths']))/$font['units'];};
    foreach(['front','back'] as $side){
        $preview=sanro_studio_guide_preview_jpeg($previews[$side]??null);if(empty($preview['valid']))return $preview;
        $entry=$manifest['sides'][$side];$lines=[
            'SANRO STUDIO - PANDUAN PRODUKSI (BUKAN FILE SIAP CETAK)',
            'Order '.$manifest['order_number'].' / item '.$manifest['item_id'],
            'Produk '.$manifest['product_id'].': '.$manifest['product_name'],
            'Design ID: '.$manifest['design_id'],
            'Ukuran '.$manifest['size'].' x '.$manifest['quantity'].' | IDR '.$manifest['unit_price'].' / pcs',
            'Qty semua size: '.json_encode($manifest['quantities']),
            'Warna '.$manifest['shirt_color'].' | Lengan '.(($manifest['sleeve_label']??null)==='3/4'?'3/4':($manifest['long_sleeve']?'panjang':'pendek')),
            strtoupper($side).' | Kategori '.($entry['category']??'tanpa cetak'),
            'Posisi dan dimensi dalam cm dari titik awal area aman produk.'];
        if(isset($manifest['repeat_discount']))$lines[]='Repeat 5% | Harga dasar IDR '.$manifest['repeat_discount']['base_unit_price'].' | Diskon IDR '.$manifest['repeat_discount']['unit_discount'].' / pcs';
        foreach($entry['objects'] as $obj)$lines[]=$obj['type'].' x='.$obj['x'].' y='.$obj['y'].' lebar='.$obj['width'].' tinggi='.$obj['height'].' cm';
        $lines[]='Font size editor: '.($entry['font_size']??'none').' | Warna '.($entry['text_color']??'none');$lines[]='Teks: '.$entry['text'];
        $wrapped=[];
        foreach($lines as $line){$line=str_replace(["\r\n","\r","\t"],["\n","\n",'    '],$line);if(!sanro_studio_text_glyphs_supported($line))return ['valid'=>false,'error'=>'guide_glyph_or_shaping_unsupported'];
            $row='';$used=0;foreach(sanro_studio_unicode_chars($line) as $char){$cp=$char['cp'];if($cp===10){$wrapped[]=$row;$row='';$used=0;continue;}$cw=$width($cp)*10/1000;if($used+$cw>535&&$row!==''){$wrapped[]=$row;$row='';$used=0;}$row.=$char['text'];$used+=$cw;$chars[$cp]=true;}$wrapped[]=$row;
        }
        if(count($wrapped)>320)return ['valid'=>false,'error'=>'guide_page_limit'];
        foreach(array_chunk($wrapped,32) as $index=>$chunk)$pages[]=['lines'=>$chunk,'preview'=>$index===0?$preview:null];
    }
    $cp_to_cid=[];$cid_to_gid="\0\0";$unicode=[];$widths=[];$cid=0;
    foreach(array_keys($chars) as $cp){$cid++;$cp_to_cid[$cp]=$cid;$gid=$font['map'][$cp];$cid_to_gid.=pack('n',$gid);$utf=$cp<=65535?pack('n',$cp):pack('nn',0xd800+(($cp-65536)>>10),0xdc00+(($cp-65536)&1023));$unicode[]=sprintf('<%04X> <%s>',$cid,strtoupper(bin2hex($utf)));$widths[]=(string)(int)round($width($cp));}
    $objects=[];$add=static function($value)use(&$objects){$objects[]=$value;return count($objects);};
    $catalog=$add('');$page_root=$add('');$stream=static function($bytes,$extra=''){return '<< /Length '.strlen($bytes).' '.$extra.">>\nstream\n".$bytes."\nendstream";};
    $fontfile=$add($stream($font['data'],'/Length1 '.strlen($font['data'])));
    $bbox=implode(' ',array_map(static function($v)use($font){return (int)round(1000*$v/$font['units']);},$font['bbox']));
    $descriptor=$add('<< /Type /FontDescriptor /FontName /DejaVuSans /Flags 32 /FontBBox ['.$bbox.'] /ItalicAngle 0 /Ascent 928 /Descent -236 /CapHeight 729 /StemV 80 /FontFile2 '.$fontfile.' 0 R >>');
    $gid_map=$add($stream($cid_to_gid));$cidfont=$add('<< /Type /Font /Subtype /CIDFontType2 /BaseFont /DejaVuSans /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor '.$descriptor.' 0 R /CIDToGIDMap '.$gid_map.' 0 R /W [1 ['.implode(' ',$widths).']] >>');
    $cmap="/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n/CMapName /SanroUnicode def\n/CMapType 2 def\n1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange\n";
    foreach(array_chunk($unicode,100) as $block)$cmap.=count($block)." beginbfchar\n".implode("\n",$block)."\nendbfchar\n";
    $cmap.="endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend";$to_unicode=$add($stream($cmap));
    $font_id=$add('<< /Type /Font /Subtype /Type0 /BaseFont /DejaVuSans /Encoding /Identity-H /DescendantFonts ['.$cidfont.' 0 R] /ToUnicode '.$to_unicode.' 0 R >>');$page_ids=[];
    foreach($pages as $page){$content='';$y=805;foreach($page['lines'] as $line){$encoded='';$actual='FEFF';foreach(sanro_studio_unicode_chars($line) as $c){$encoded.=sprintf('%04X',$cp_to_cid[$c['cp']]);$cp=$c['cp'];$actual.=strtoupper(bin2hex($cp<=65535?pack('n',$cp):pack('nn',0xd800+(($cp-65536)>>10),0xdc00+(($cp-65536)&1023))));}$content.="/Span << /ActualText <$actual> >> BDC\nBT /F1 10 Tf 30 $y Td <$encoded> Tj ET\nEMC\n";$y-=15;}
        $image_id=null;if($page['preview']){$p=$page['preview'];$info=getimagesizefromstring($p['bytes']);$image_id=$add($stream($p['bytes'],'/Type /XObject /Subtype /Image /Width '.$info[0].' /Height '.$info[1].' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode'));$scale=min(250/$info[0],240/$info[1]);$content.='q '.($info[0]*$scale).' 0 0 '.($info[1]*$scale).' 30 65 cm /Im1 Do Q';}
        $content_id=$add($stream($content));$page_ids[]=$add('<< /Type /Page /Parent '.$page_root.' 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 '.$font_id.' 0 R >>'.($image_id?' /XObject << /Im1 '.$image_id.' 0 R >>':'').' >> /Contents '.$content_id.' 0 R >>');
    }
    $objects[$catalog-1]='<< /Type /Catalog /Pages '.$page_root.' 0 R >>';$objects[$page_root-1]='<< /Type /Pages /Kids ['.implode(' ',array_map(static function($id){return $id.' 0 R';},$page_ids)).'] /Count '.count($page_ids).' >>';
    $pdf="%PDF-1.4\n";$offsets=[];foreach($objects as $i=>$obj){$offsets[]=strlen($pdf);$pdf.=($i+1)." 0 obj\n".$obj."\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";foreach($offsets as $off)$pdf.=sprintf('%010d 00000 n ',$off)."\n";
    $pdf.='trailer << /Size '.(count($objects)+1).' /Root '.$catalog." 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    return strlen($pdf)<=4194304?['valid'=>true,'bytes'=>$pdf]:['valid'=>false,'error'=>'guide_size_limit'];
}
