<?php
defined('ABSPATH') || exit;
require_once __DIR__.'/production-typography.php';

/** Side bytes depend on this artwork/master/font, not garment size or other-side edits. */
function sanro_studio_artifact_source_fingerprint($record,$order_id,$item_id,$design_id,$side) {
    if(!is_array($record)||!is_array($record['design'][$side]??null)||!in_array($side,['front','back'],true))return false;
    $font=function_exists('sanro_studio_dtf_private_font')?sanro_studio_dtf_private_font():sanro_studio_production_font();
    if(!$font)return false;
    $json=json_encode(['dtf-layer-v2',$order_id,$item_id,$design_id,$record['product_id']??null,$side,$record['design'][$side],$record['master_refs'][$side]??null,hash_file('sha256',$font)],JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION);
    return is_string($json)?hash('sha256',$json):false;
}
