<?php
defined('ABSPATH') || exit;
require_once __DIR__.'/production-pdf.php';

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

/** Embedded-font Unicode A4 production guide; never a printable DTF file. */
function sanro_studio_production_guide_pdf($manifest, $previews) {
    $encoded=json_encode($manifest,JSON_UNESCAPED_UNICODE);
    if(!is_string($encoded)||strlen($encoded)>1048576)return ['valid'=>false,'error'=>'production_manifest_invalid'];
    return sanro_studio_unicode_production_pdf($manifest,$previews);
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
        $assets[]=['kind'=>'preview','side'=>$side,'mime'=>$preview['mime'],'ext'=>['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$preview['mime']],'bytes'=>$preview['bytes']];
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
