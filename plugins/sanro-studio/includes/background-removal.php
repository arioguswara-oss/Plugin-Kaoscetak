<?php
defined('ABSPATH') || exit;

/** Local edge-connected colour removal, not semantic AI segmentation. Never overwrite source bytes. */
function sanro_studio_remove_background_bytes($bytes,$tolerance=24){
    $checked=sanro_studio_validate_master_bytes($bytes);
    if(empty($checked['valid']))return $checked;
    if(!is_int($tolerance)||$tolerance<0||$tolerance>96)return ['valid'=>false,'error'=>'background_tolerance'];
    if(!function_exists('imagecreatefromstring')||!function_exists('imagepalettetotruecolor'))return ['valid'=>false,'error'=>'background_gd_unavailable'];
    $pixels=$checked['width']*$checked['height'];$limit=ini_get('memory_limit');$memory=-1;
    if(is_string($limit)&&preg_match('/^([0-9]+)([KMG]?)$/iD',trim($limit),$m))$memory=(int)$m[1]*(1024**([''=>0,'K'=>1,'M'=>2,'G'=>3][strtoupper($m[2])]));
    if($memory>0&&memory_get_usage(true)+$pixels*12+strlen($bytes)*2+33554432>$memory)return ['valid'=>false,'error'=>'background_memory_budget'];
    $image=@imagecreatefromstring($bytes);if(!$image)return ['valid'=>false,'error'=>'background_decode'];
    $deadline=microtime(true)+8;$width=$checked['width'];$height=$checked['height'];
    try{
        if(!imageistruecolor($image)&&!imagepalettetotruecolor($image))return ['valid'=>false,'error'=>'background_decode'];
        // Inspect actual decoded alpha (palette PNG included), never the extension.
        for($y=0;$y<$height;$y++){
            if(microtime(true)>$deadline)return ['valid'=>false,'error'=>'background_time_budget'];
            for($x=0;$x<$width;$x++)if((imagecolorat($image,$x,$y)>>24)&127)return ['valid'=>false,'error'=>'image_already_transparent'];
        }
        imagealphablending($image,false);imagesavealpha($image,true);$clear=imagecolorallocatealpha($image,0,0,0,127);
        $colours=[];foreach([[0,0],[$width-1,0],[0,$height-1],[$width-1,$height-1]] as [$x,$y])$colours[]=imagecolorat($image,$x,$y)&0xffffff;
        $matches=static function($x,$y)use($image,$colours,$tolerance){
            $p=imagecolorat($image,$x,$y);if(($p>>24)&127)return false;
            foreach($colours as $c)if(abs((($p>>16)&255)-(($c>>16)&255))<=$tolerance&&abs((($p>>8)&255)-(($c>>8)&255))<=$tolerance&&abs(($p&255)-($c&255))<=$tolerance)return true;
            return false;
        };
        $queue=new SplQueue();for($x=0;$x<$width;$x++){$queue->enqueue([$x,0]);$queue->enqueue([$x,$height-1]);}
        for($y=1;$y<$height-1;$y++){$queue->enqueue([0,$y]);$queue->enqueue([$width-1,$y]);}$removed=0;
        while(!$queue->isEmpty()){
            if($queue->count()>40000||microtime(true)>$deadline)return ['valid'=>false,'error'=>'background_work_budget'];
            [$x,$y]=$queue->dequeue();if(!$matches($x,$y))continue;
            $left=$x;while($left>0&&$matches($left-1,$y))$left--;
            $right=$x;while($right+1<$width&&$matches($right+1,$y))$right++;
            for($xx=$left;$xx<=$right;$xx++){imagesetpixel($image,$xx,$y,$clear);$removed++;}
            foreach([$y-1,$y+1] as $yy){if($yy<0||$yy>=$height)continue;$run=false;
                for($xx=$left;$xx<=$right;$xx++){$match=$matches($xx,$yy);if($match&&!$run)$queue->enqueue([$xx,$yy]);$run=$match;}
            }
        }
        if($removed===0||$removed===$pixels)return ['valid'=>false,'error'=>'background_no_foreground'];
        ob_start();try{$ok=imagepng($image,null,6);$output=ob_get_contents();}finally{ob_end_clean();}
        $result=$ok?sanro_studio_validate_master_bytes($output):['valid'=>false];
        if(empty($result['valid'])||$result['width']!==$width||$result['height']!==$height||$result['mime']!=='image/png')return ['valid'=>false,'error'=>'background_output_invalid'];
        return ['valid'=>true,'bytes'=>$output,'removed_pixels'=>$removed];
    }finally{imagedestroy($image);}
}
function sanro_studio_background_apply($id,$token,$side,$expected,$restore=false,$tolerance=24){
    if(!sanro_studio_storage_inside())return sanro_studio_storage_transaction(null,0,static function()use($id,$token,$side,$expected,$restore,$tolerance){return sanro_studio_background_apply($id,$token,$side,$expected,$restore,$tolerance);});
    if(!in_array($side,['front','back'],true)||!is_string($expected)||!preg_match('/^[a-f0-9]{64}$/D',$expected))return ['valid'=>false,'error'=>'background_input'];
    $draft=sanro_studio_read_draft($id,$token);$before=get_option('sanro_studio_draft_'.$id,false);
    if(empty($draft['valid'])||$draft['status']!=='draft'||!hash_equals($expected,$draft['design_hash'])||!is_array($before))return ['valid'=>false,'error'=>'draft_conflict'];
    $ref=$before['master_refs'][$side]??null;if(!is_string($ref))return ['valid'=>false,'error'=>'master_not_bound'];
    if($restore){$next=$before['original_master_refs'][$side]??null;if(!is_string($next))return ['valid'=>false,'error'=>'original_not_available'];
        $read=sanro_studio_collection_asset(['source_design_id'=>$id],$next,$side,true);if(empty($read['valid']))return $read;
    }else{
        $read=sanro_studio_read_owned_draft_master($id,$token,$side,$ref);if(empty($read['valid']))return $read;
        $processed=sanro_studio_remove_background_bytes($read['bytes'],$tolerance);if(empty($processed['valid']))return $processed;
        $write=sanro_studio_store_private_master($id,$side,$processed['bytes']);if(empty($write['valid']))return $write;$next=$write['file_id'];
    }
    $after=$before;$after['original_master_refs'][$side]=$before['original_master_refs'][$side]??$ref;$after['master_refs'][$side]=$next;
    unset($after['preview_refs'],$after['preview_refs_by_size'],$after['preview_design_hash']);
    if(!sanro_studio_collection_cas('sanro_studio_draft_'.$id,$before,$after))return ['valid'=>false,'error'=>'draft_conflict'];
    return ['valid'=>true,'file_id'=>$next,'restored'=>$restore];
}
function sanro_studio_background_routes(){
    if(!sanro_studio_draft_rest_enabled()||!defined('SANRO_STUDIO_ENABLE_MASTER_UPLOAD')||SANRO_STUDIO_ENABLE_MASTER_UPLOAD!==true)return;
    register_rest_route('sanro-studio/v1','/drafts/(?P<id>[a-f0-9-]{36})/remove-background/(?P<side>front|back)',['methods'=>'POST','permission_callback'=>'sanro_studio_rest_draft_nonce','callback'=>static function($r){
        $result=sanro_studio_background_apply($r->get_param('id'),$r->get_header('X-Sanro-Owner-Token'),$r->get_param('side'),$r->get_param('expected_design_hash'),$r->get_param('restore')===true,$r->get_param('tolerance')??24);
        if(empty($result['valid']))return new WP_Error('sanro_'.($result['error']??'background_unavailable'),'Hapus latar ditolak: '.($result['error']??'unavailable'),['status'=>($result['error']??'')==='storage_capacity'?507:409]);
        $response=rest_ensure_response($result);$response->header('Cache-Control','private, no-store, max-age=0');return $response;
    }]);
}
