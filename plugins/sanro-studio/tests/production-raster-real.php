<?php
/** Actual GD/FreeType/A3+ output and private artifact lifecycle; synthetic order only. */
require __DIR__.'/production-package-flow.php';
if(!extension_loaded('gd')){fwrite(STDERR,"NOT TESTED: GD required for this real-raster scenario\n");exit(2);}
define('SANRO_STUDIO_ENABLE_PRIVATE_MASTERS',true);define('SANRO_STUDIO_PRIVATE_MASTER_DIR',$private);
define('SANRO_STUDIO_ENABLE_DTF_IMAGE_RASTER',true);define('SANRO_STUDIO_ENABLE_DTF_LAYER_RASTER',true);define('SANRO_STUDIO_ENABLE_DTF_PRIVATE_ARTIFACTS',true);define('SANRO_STUDIO_ENABLE_DTF_ORDER_BATCH',true);define('SANRO_STUDIO_DTF_ARTIFACT_DIR',$private);
foreach(['master-storage','dtf-image-render','dtf-layer-render','dtf-private-artifacts','dtf-order-workflow'] as $module)require __DIR__.'/../includes/'.$module.'.php';
$production->paid=true;$production->status='processing';$checks=0;
$source=imagecreatetruecolor(300,300);imagealphablending($source,false);imagesavealpha($source,true);imagefill($source,0,0,imagecolorallocatealpha($source,0,0,0,127));imagefilledellipse($source,150,150,220,220,imagecolorallocatealpha($source,240,30,20,0));ob_start();imagepng($source);$png=ob_get_clean();imagedestroy($source);
$ref=$id.'-back-'.hash('sha256',$png).'.png';file_put_contents($private.'/'.$ref,$png);
$record=get_option($key);$record['master_refs']=['back'=>$ref];
$record['design']['front']=['category'=>'A3+','objects'=>[['type'=>'text','x'=>0.0,'y'=>0.0,'width'=>32.0,'height'=>48.0]],'text'=>"SANRO École\nΩ Привет",'font_size'=>24,'text_color'=>'#171717'];
$record['design']['back']=['category'=>'A5','objects'=>[['type'=>'image','x'=>1.0,'y'=>1.0,'width'=>2.0,'height'=>2.0],['type'=>'text','x'=>1.0,'y'=>4.0,'width'=>5.0,'height'=>2.0]],'text'=>"Belakang\né Ω",'font_size'=>24,'text_color'=>'#171717'];
$record['preview_design_hash']=hash('sha256',serialize($record['design']));$GLOBALS['options'][$key]=$record;
foreach($production->items as $item)$item->meta['_sanro_studio_design_snapshot']=sanro_studio_pricing_design_snapshot(['id'=>$id,'contract'=>['design'=>$record['design']]]);
$plan=sanro_studio_dtf_layer_plan($production,71,'front');flow_check(!empty($plan['valid'])&&$plan['width_px']===3780&&$plan['height_px']===5669,'A3+ at300DPI fits complete 32x48cm canvas: '.json_encode($plan));
$result=sanro_studio_dtf_prepare_order_item_candidates($production,71);flow_check(!empty($result['valid'])&&!empty($result['complete']),'real front/back batch persists and verifies: '.json_encode($result));
foreach(['front','back'] as $side){$artifact=sanro_studio_dtf_verify_private_artifact($production,71,$side);flow_check(!empty($artifact['valid'])&&$artifact['dpi']===300&&$artifact['quality_approved']===false,'side verified exact dimensions/density/SHA, not print-approved');$bytes=sanro_studio_dtf_private_candidate_source($production,71,$side);$decoded=imagecreatefromstring($bytes['bytes']);flow_check(((imagecolorat($decoded,imagesx($decoded)-1,0)>>24)&127)===127,'transparent outer pixel preserved');imagedestroy($decoded);}
$back=sanro_studio_dtf_private_candidate_source($production,71,'back');$validation=sanro_studio_dtf_validate_raster($back['bytes'],sanro_studio_dtf_layer_plan($production,71,'back'));flow_check(!empty($validation['valid'])&&$validation['visible_content'],'visible mixed image/text output physically validated');
$empty=imagecreatetruecolor(12,12);imagealphablending($empty,false);imagesavealpha($empty,true);imagefill($empty,0,0,imagecolorallocatealpha($empty,0,0,0,127));imageresolution($empty,300,300);ob_start();imagepng($empty);$blank=ob_get_clean();imagedestroy($empty);flow_check(sanro_studio_dtf_validate_raster($blank,['width_px'=>12,'height_px'=>12,'width_cm'=>0.1,'height_cm'=>0.1])['error']==='dtf_output_empty','all-transparent output cannot pass content validation');
flow_check(!sanro_studio_dtf_memory_available(6001,5669),'oversized canvas refused before allocation');
$oldLimit=ini_get('memory_limit');ini_set('memory_limit','64M');$again=sanro_studio_dtf_prepare_order_item_candidates($production,71);flow_check(!empty($again['valid'])&&!sanro_studio_dtf_memory_available(3780,5669),'existing A3+ candidate retries under64MiB without allocating a render canvas');ini_set('memory_limit',$oldLimit);
$originalText=$GLOBALS['options'][$key]['design']['front']['text'];$GLOBALS['options'][$key]['design']['front']['text']='changed text, identical geometry';flow_check(empty(sanro_studio_dtf_verify_private_artifact($production,71,'front')['valid']),'changed text at identical geometry invalidates private source provenance');$GLOBALS['options'][$key]['design']['front']['text']=$originalText;
$binding=sanro_studio_dtf_artifact_binding($production,71,'front');$legacy=$private.'/'.substr(sanro_studio_dtf_artifact_basename($binding),0,-3).'.png';file_put_contents($legacy,'legacy-v1-marker');flow_check(!empty(sanro_studio_dtf_verify_private_artifact($production,71,'front')['valid'])&&file_get_contents($legacy)==='legacy-v1-marker','versioned v2 paths never overwrite legacy artifacts');
$font=sanro_studio_production_font();flow_check(sanro_studio_text_glyphs_supported("École Ω Привет\nBaris dua"),'supported Unicode font coverage');flow_check(!sanro_studio_text_glyphs_supported('مرحبا'),'complex shaping never silently substituted');
# Check exact UTF-8 survives PDF text extraction in the invoking smoke command.
$production->items[71]->name='Kaos École Ω Привет';$package=sanro_studio_prepare_production_package($production,71);flow_check(!empty($package['valid']),'Unicode multi-line guide generated');
$out=getenv('SANRO_STUDIO_QA_OUTPUT');if(is_string($out)&&is_dir($out))file_put_contents($out.'/unicode-guide.pdf',$package['assets'][2]['bytes']);
echo "PASS: $checks actual A3+ front/back raster and Unicode guide checks\n";
