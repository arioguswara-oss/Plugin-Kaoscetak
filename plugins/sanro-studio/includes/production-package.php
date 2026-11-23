<?php
defined('ABSPATH') || exit;

/** Separate opt-in: loading this module never renders, uploads or schedules. */
function sanro_studio_drive_package_enabled() {
    return defined('SANRO_STUDIO_ENABLE_DRIVE_PACKAGE_EXPORT')
        && SANRO_STUDIO_ENABLE_DRIVE_PACKAGE_EXPORT === true
        && function_exists('sanro_studio_auto_drive_enabled') && sanro_studio_auto_drive_enabled();
}

/** Build an immutable, credential-free production description from a locked item. */
function sanro_studio_production_item_manifest($order, $item_id) {
    if (!is_int($item_id) || $item_id < 1
        || !sanro_studio_auto_drive_order_eligible($order)) return ['valid'=>false,'error'=>'production_not_ready'];
    $items=$order->get_items('line_item'); $item=$items[$item_id]??null;
    if (!is_object($item) || !method_exists($item,'get_product_id') || !method_exists($item,'get_quantity')) return ['valid'=>false,'error'=>'invalid_item'];
    $id=$item->get_meta('_sanro_studio_design_id',true);
    if (!is_string($id) || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD',$id)) return ['valid'=>false,'error'=>'design_not_bound'];
    $record=get_option('sanro_studio_draft_'.$id,false);
    if (!is_array($record) || ($record['status']??null)!=='locked'
        || ($record['order_id']??null)!==$order->get_id()
        || ($record['product_id']??null)!==$item->get_product_id()
        || !sanro_studio_locked_record_has_item($record,$item_id)
        || !is_array($record['design']??null)
        || empty(sanro_studio_validate_product_design($item->get_product_id(),$record['design'])['valid'])) return ['valid'=>false,'error'=>'production_binding_mismatch'];
    $size=$item->get_meta('_sanro_studio_size',true);$qty=sanro_studio_pricing_quantity($item->get_quantity());
    $unit=$item->get_meta('_sanro_studio_unit_price',true);
    if (!is_string($size) || !in_array($size,['S','M','L','XL','2XL','3XL','4XL','5XL'],true)
        || $qty===null || ($record['design']['quantities'][$size]??null)!==$qty
        || (!is_int($unit) && !(is_string($unit)&&preg_match('/^[1-9][0-9]*$/D',$unit))) || (int)$unit<1
        || $item->get_meta('_sanro_studio_quote_currency',true)!=='IDR') return ['valid'=>false,'error'=>'production_allocation_mismatch'];
    $line=['product_id'=>$item->get_product_id(),'quantity'=>$qty,'sanro_studio_design_id'=>$id,
        'sanro_studio_size'=>$size,'sanro_studio_unit_price'=>(int)$unit,
        'sanro_studio_line_subtotal'=>(int)$unit*$qty,'sanro_studio_quote_currency'=>'IDR'];
    if (!method_exists($order,'get_currency') || $order->get_currency()!=='IDR'
        || empty(sanro_studio_validate_size_order_item($line,$item)['valid'])) return ['valid'=>false,'error'=>'production_order_amount_mismatch'];
    $design=$record['design'];$hash=hash('sha256',maybe_serialize($design));
    $saved=$item->get_meta('_sanro_studio_design_snapshot',true);
    if (is_array($saved) && (($saved['design_id']??null)!==$id || ($saved['design_hash']??null)!==$hash)) return ['valid'=>false,'error'=>'production_snapshot_mismatch'];
    $snapshot=sanro_studio_pricing_design_snapshot(['id'=>$id,'contract'=>['design'=>$design]]);
    $manifest=['version'=>1,'kind'=>'production_guide','print_ready'=>false,
        'order_id'=>$order->get_id(),'order_number'=>method_exists($order,'get_order_number')?(string)$order->get_order_number():(string)$order->get_id(),
        'item_id'=>$item_id,'product_id'=>$item->get_product_id(),
        'product_name'=>method_exists($item,'get_name')?(string)$item->get_name():'',
        'design_id'=>$id,'design_hash'=>$hash,'size'=>$size,'quantity'=>$qty,
        'quantities'=>$design['quantities'],'unit_price'=>(int)$unit,'subtotal'=>(int)$unit*$qty,'currency'=>'IDR',
        'shirt_color'=>$snapshot['shirt_color'],'long_sleeve'=>$snapshot['long_sleeve'],'sides'=>$snapshot['sides']];
    return ['valid'=>true,'manifest'=>$manifest];
}

/** Small multipage A4 guide with JPEG previews. Never a printable DTF file.
 * Non-ASCII content needs an explicitly trusted Unicode renderer, never '?'. */
function sanro_studio_production_guide_pdf($manifest, $previews) {
    $encoded=json_encode($manifest,JSON_UNESCAPED_UNICODE);
    if(!is_string($encoded)||strlen($encoded)>1048576)return ['valid'=>false,'error'=>'production_manifest_invalid'];
    if (preg_match('/[^\x09\x0A\x0D\x20-\x7E]/u',$encoded)) {
        $provided=function_exists('apply_filters')?apply_filters('sanro_studio_unicode_guide_pdf',null,$manifest,$previews):null;
        return is_string($provided) && strlen($provided)<=4194304 && strpos($provided,'%PDF-')===0
            ? ['valid'=>true,'bytes'=>$provided] : ['valid'=>false,'error'=>'guide_unicode_renderer_required'];
    }
    $objects=[];$add=function($s)use(&$objects){$objects[]=$s;return count($objects);};
    $catalog=$add('');$pages=$add('');$font=$add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');$page_ids=[];
    foreach (['front','back'] as $side) {
        $preview=$previews[$side]??null;
        $info=is_array($preview)&&is_string($preview['bytes']??null)?@getimagesizefromstring($preview['bytes']):false;
        if (!$info || ($info['mime']??null)!=='image/jpeg' || ($info['channels']??3)!==3 || ($preview['mime']??null)!=='image/jpeg'
            || strlen($preview['bytes'])>51200) return ['valid'=>false,'error'=>'guide_jpeg_preview_required'];
        $entry=$manifest['sides'][$side];$lines=[
            'SANRO STUDIO - PRODUCTION GUIDE (NOT PRINT-READY)',
            'Order '.$manifest['order_number'].' / item '.$manifest['item_id'],
            'Product '.$manifest['product_id'].': '.$manifest['product_name'],
            'Design '.$manifest['design_id'],
            'Size '.$manifest['size'].' x '.$manifest['quantity'].' | IDR '.$manifest['unit_price'].' each',
            'All sizes: '.json_encode($manifest['quantities']),
            'Shirt '.$manifest['shirt_color'].' | Sleeve '.($manifest['long_sleeve']?'long':'short'),
            strtoupper($side).' | Category '.($entry['category']??'blank'),
            'Positions/sizes below are centimeters from the product safe-area origin.'];
        foreach ($entry['objects'] as $object) $lines[]=$object['type'].' x='.$object['x'].' y='.$object['y'].' width='.$object['width'].' height='.$object['height'].' cm';
        $lines[]='Text font size: '.($entry['font_size']??'none').' / color '.($entry['text_color']??'none');
        $lines[]='Text: '.$entry['text'];
        $flat=[];foreach($lines as $line)foreach(explode("\n",wordwrap($line,88,"\n",true)) as $piece)$flat[]=$piece;
        $chunks=array_chunk($flat,32);
        foreach ($chunks as $index=>$chunk) {
            $stream='';$y=805;
            foreach($chunk as $line){$escaped=str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$line);$stream.="BT /F1 10 Tf 30 $y Td ($escaped) Tj ET\n";$y-=15;}
            $image_id=null;
            if($index===0){$bytes=$preview['bytes'];$image_id=$add('<< /Type /XObject /Subtype /Image /Width '.(int)$info[0].' /Height '.(int)$info[1].' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($bytes).">>\nstream\n".$bytes."\nendstream");$stream.="q 220 0 0 220 30 65 cm /Im1 Do Q\n";}
            $content=$add('<< /Length '.strlen($stream).">>\nstream\n".$stream."endstream");
            $resource='<< /Font << /F1 '.$font.' 0 R >>'.($image_id?' /XObject << /Im1 '.$image_id.' 0 R >>':'').' >>';
            $page_ids[]=$add('<< /Type /Page /Parent '.$pages.' 0 R /MediaBox [0 0 595 842] /Resources '.$resource.' /Contents '.$content.' 0 R >>');
        }
    }
    $objects[$catalog-1]='<< /Type /Catalog /Pages '.$pages.' 0 R >>';
    $objects[$pages-1]='<< /Type /Pages /Kids ['.implode(' ',array_map(fn($id)=>$id.' 0 R',$page_ids)).'] /Count '.count($page_ids).' >>';
    $pdf="%PDF-1.4\n";$offsets=[0];
    foreach($objects as $i=>$object){$offsets[]=strlen($pdf);$pdf.=($i+1)." 0 obj\n".$object."\nendobj\n";}
    $xref=strlen($pdf);$pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
    foreach(array_slice($offsets,1) as $offset)$pdf.=sprintf('%010d 00000 n ',$offset)."\n";
    $pdf.='trailer << /Size '.(count($objects)+1).' /Root '.$catalog." 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    return ['valid'=>true,'bytes'=>$pdf];
}

/** Internal in-memory package. No file, order, Drive, or payment writes. */
function sanro_studio_prepare_production_package($order,$item_id) {
    $description=sanro_studio_production_item_manifest($order,$item_id);
    if (empty($description['valid'])) return $description;
    $manifest=$description['manifest'];$previews=[];$assets=[];
    foreach(['front','back'] as $side){
        $preview=sanro_studio_bound_order_preview_bytes($order,$side,$item_id);
        if(empty($preview['valid']))return ['valid'=>false,'error'=>'production_preview_unavailable'];
        $previews[$side]=$preview;
        $assets[]=['kind'=>'preview','side'=>$side,'mime'=>$preview['mime'],'ext'=>'jpg','bytes'=>$preview['bytes']];
    }
    $guide=sanro_studio_production_guide_pdf($manifest,$previews);
    if(empty($guide['valid']))return $guide;
    $assets[]=['kind'=>'guide','side'=>'guide','mime'=>'application/pdf','ext'=>'pdf','bytes'=>$guide['bytes']];
    $json=json_encode($manifest,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    if(!is_string($json)||strlen($json)>1048576)return ['valid'=>false,'error'=>'production_manifest_invalid'];
    $assets[]=['kind'=>'manifest','side'=>'guide','mime'=>'application/json','ext'=>'json','bytes'=>$json];
    foreach($assets as &$asset){$asset['valid']=true;$asset['design_id']=$manifest['design_id'];$asset['sha256']=hash('sha256',$asset['bytes']);}unset($asset);
    return ['valid'=>true,'manifest'=>$manifest,'assets'=>$assets,'print_ready'=>false];
}

/** Gated coordinator; originals, guides/previews, optional unapproved DTF candidates. */
function sanro_studio_export_production_package($order,$item_id,$folder_id) {
    if(!sanro_studio_drive_package_enabled())return ['valid'=>false,'error'=>'drive_package_disabled'];
    $package=sanro_studio_prepare_production_package($order,$item_id);
    if(empty($package['valid']))return $package;
    if(defined('SANRO_STUDIO_ENABLE_DRIVE_DTF_CANDIDATES')&&SANRO_STUDIO_ENABLE_DRIVE_DTF_CANDIDATES===true){
        $render=sanro_studio_dtf_prepare_order_item_candidates($order,$item_id);
        if(empty($render['valid'])||empty($render['complete']))return ['valid'=>false,'error'=>'dtf_package_incomplete'];
        foreach(['front','back'] as $side){
            $candidate=sanro_studio_dtf_private_candidate_source($order,$item_id,$side);
            if(empty($candidate['valid']))return $candidate;
            if(empty($candidate['skipped']))$package['assets'][]=$candidate+['kind'=>'dtf-candidate','side'=>$side];
        }
    }
    $uploaded=0;$reused=0;
    foreach($package['assets'] as $asset){
        $now=sanro_studio_production_item_manifest($order,$item_id);
        if(empty($now['valid'])||$now['manifest']!==$package['manifest'])return ['valid'=>false,'error'=>'production_snapshot_changed'];
        $result=sanro_studio_drive_upload_item_source($order,$item_id,$folder_id,$asset['side'],$asset,$asset['kind']);
        if(empty($result['valid']))return ['valid'=>false,'error'=>$result['error']??'production_upload_failed','uploaded'=>$uploaded,'reused'=>$reused];
        if(!empty($result['created']))$uploaded++;else $reused++;
    }
    return ['valid'=>true,'uploaded'=>$uploaded,'reused'=>$reused,'print_ready'=>false];
}
