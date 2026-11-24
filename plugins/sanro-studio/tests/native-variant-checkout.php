<?php
/** Checkout integration rerun justified by changed per-size preview binding; reuse the existing real WC boundary fixture. */
require __DIR__.'/feature-checkout-flow.php';
require __DIR__.'/../includes/mockup-mapping.php';
require_once __DIR__.'/../includes/order-integrity.php';
$checks=0;$record=get_option($key);$record['preview_variant_required']=true;
foreach(['S','L'] as $size)foreach(['front','back'] as $side){
    $img=imagecreatetruecolor(16,16);$colour=imagecolorallocate($img,$size==='S'?200:30,$side==='front'?80:170,90);imagefill($img,0,0,$colour);ob_start();imagejpeg($img);$data=ob_get_clean();imagedestroy($img);
    $stored=sanro_studio_store_private_preview($id,$side,$data);$record['preview_refs_by_size'][$size][$side]=$stored['file_id'];
}
$GLOBALS['options'][$key]=$record;
flow_check(empty(sanro_studio_checkout_bind_existing_item_previews($order,$id,$token,[71,72])['valid']),'existing different item refs cannot be silently overwritten');
foreach($order->items as $item)foreach(['front','back'] as $side)unset($item->meta['_sanro_studio_preview_'.$side.'_ref']);
$bound=sanro_studio_checkout_bind_existing_item_previews($order,$id,$token,[71,72]);flow_check($bound['valid'],'all size previews preflight and bind');
foreach([71=>'S',72=>'L'] as $item_id=>$size)foreach(['front','back'] as $side){
 flow_check($order->items[$item_id]->meta['_sanro_studio_preview_'.$side.'_ref']===$record['preview_refs_by_size'][$size][$side],'correct side/size linked to item');
 flow_check(sanro_studio_bound_order_preview_bytes($order,$side,$item_id)['valid'],'admin can read matching immutable preview');
}
flow_check(sanro_studio_saved_order_context($order,true)['valid'],'full saved-order integrity accepts correct size variants');
$correct=$order->items[72]->meta['_sanro_studio_preview_front_ref'];$order->items[72]->meta['_sanro_studio_preview_front_ref']=$record['preview_refs_by_size']['S']['front'];
flow_check(!sanro_studio_saved_order_context($order,true)['valid'],'substituting another size preview fails saved-order integrity');$order->items[72]->meta['_sanro_studio_preview_front_ref']=$correct;
$missing=$record;unset($missing['preview_refs_by_size']['L']);$GLOBALS['options'][$key]=$missing;
flow_check(!sanro_studio_checkout_bind_existing_item_previews($order,$id,$token,[71,72])['valid'],'missing mapped size fail-closed without global fallback');
echo "PASS: $checks size/side preview checkout/admin/order integrity checks\n";
