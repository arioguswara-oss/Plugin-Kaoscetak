<?php
defined('ABSPATH') || exit;
require_once __DIR__.'/production-typography.php';

/** Covers A3+ (3780x5669 at 300 DPI); refuse before allocating beyond worker memory. */
function sanro_studio_dtf_memory_available($width,$height,$source_pixels=0) {
    if($width<1||$height<1||$width>6000||$height>6000||$width*$height>24000000)return false;
    $limit=ini_get('memory_limit');if($limit==='-1')return true;
    $bytes=(int)$limit;$suffix=strtolower(substr($limit,-1));if($suffix==='g')$bytes*=1073741824;elseif($suffix==='m')$bytes*=1048576;elseif($suffix==='k')$bytes*=1024;
    return $bytes>memory_get_usage(true)+$width*$height*12+$source_pixels*6+33554432;
}

/** Physical output validation is machine-checkable, not a human print approval. */
function sanro_studio_dtf_validate_raster($bytes,$plan) {
    $info=is_string($bytes)?@getimagesizefromstring($bytes):false;
    if(!$info||$info[0]!==$plan['width_px']||$info[1]!==$plan['height_px']||($info['mime']??null)!=='image/png'
        || strlen($bytes)>33554432 || ord($bytes[25])!==6 || !sanro_studio_dtf_png_has_300dpi($bytes)
        || abs($plan['width_px']-$plan['width_cm']*300/2.54)>0.51||abs($plan['height_px']-$plan['height_cm']*300/2.54)>0.51)return ['valid'=>false,'error'=>'dtf_output_integrity_failed'];
    if(!function_exists('imagecreatefromstring'))return ['valid'=>false,'error'=>'dtf_gd_unavailable'];
    $image=@imagecreatefromstring($bytes);if(!$image)return ['valid'=>false,'error'=>'dtf_image_decode_failed'];
    try{$visible=false;for($y=0;$y<$info[1]&&!$visible;$y++)for($x=0;$x<$info[0];$x++)if(((imagecolorat($image,$x,$y)>>24)&127)<127){$visible=true;break;}
        return $visible?['valid'=>true,'dpi'=>300,'alpha_channel'=>true,'physical_size_valid'=>true,'visible_content'=>true,'quality_approved'=>false]:['valid'=>false,'error'=>'dtf_output_empty'];
    }finally{imagedestroy($image);}
}

/** Fit multiline text in the saved physical box, with the same bundled editor font. */
function sanro_studio_dtf_text_layout($text,$font,$width,$height) {
    if(!sanro_studio_text_glyphs_supported($text))return false;
    $text=str_replace(["\r\n","\r","\t"],["\n","\n",'    '],$text);
    $best=false;$lo=1;$hi=min(2048,$height*2);
    while($lo<=$hi){$size=(int)floor(($lo+$hi)/2);$rows=[];$row='';$fits=true;
        foreach(sanro_studio_unicode_chars($text) as $char){if($char['cp']===10){$rows[]=$row;$row='';continue;}$next=$row.$char['text'];$box=imagettfbbox($size,0,$font,$next);$w=max($box[0],$box[2],$box[4],$box[6])-min($box[0],$box[2],$box[4],$box[6]);
            if($w>$width-2){if($row===''){$fits=false;break;}$rows[]=$row;$row=$char['text'];$single=imagettfbbox($size,0,$font,$row);if(max($single[0],$single[2],$single[4],$single[6])-min($single[0],$single[2],$single[4],$single[6])>$width-2){$fits=false;break;}}else $row=$next;
        }$rows[]=$row;$lineheight=(int)ceil($size*96/72*1.15);
        if($fits&&count($rows)*$lineheight<=$height-2){$best=['size'=>$size,'lines'=>$rows,'lineheight'=>$lineheight];$lo=$size+1;}else $hi=$size-1;
    }return $best;
}
