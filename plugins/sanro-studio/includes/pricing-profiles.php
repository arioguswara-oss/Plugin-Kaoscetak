<?php
defined('ABSPATH') || exit;
require_once __DIR__.'/pricing.php';

/** Only Owner-approved presets; product metadata never changes these defaults. */
function sanro_studio_pricing_presets() {
    return [
        'cotton_combed_24s'=>['label'=>'Cotton Combed 24s','sizes'=>['S','M','L','XL','2XL','3XL','4XL','5XL'],'table'=>sanro_studio_24s_price_table()],
        'tunik_oversize_34'=>['label'=>'Tunik Oversize lengan 3/4','sizes'=>['M','L','XL'],'table'=>['M'=>[85000,95000,105000,110000],'L'=>[90000,100000,110000,115000],'XL'=>[95000,105000,115000,120000]]],
    ];
}
function sanro_studio_validate_product_price_table($table,$sizes) {
    if(!is_array($table)||array_diff(array_keys($table),$sizes)||count($table)!==count($sizes))return false;
    foreach($sizes as $size){if(!isset($table[$size])||!is_array($table[$size])||array_keys($table[$size])!==[0,1,2,3])return false;
        foreach($table[$size] as $price)if(!is_int($price)||$price<1||$price>1000000)return false;
    }return true;
}
function sanro_studio_product_second_side_prices($product_id) {
    $stored = get_post_meta($product_id,'_sanro_studio_second_side_prices',true);
    if (is_array($stored) && array_keys($stored) === [0,1,2,3]) {
        foreach ($stored as $amount) {
            if (!is_int($amount) || $amount < 0 || $amount > 1000000) return sanro_studio_default_second_side_prices();
        }
        return $stored;
    }
    return sanro_studio_default_second_side_prices();
}
function sanro_studio_product_pricing_config($product_id) {
    $presets=sanro_studio_pricing_presets();$key=get_post_meta($product_id,'_sanro_studio_pricing_profile',true);
    if(!is_string($key)||!isset($presets[$key]))return ['valid'=>false,'error'=>'pricing_profile_unconfigured'];
    $profile=$presets[$key];$override=get_post_meta($product_id,'_sanro_studio_price_table',true);
    if($override!==''&&$override!==null&&$override!==[]){
        if(!is_array($override)||($override['profile']??null)!==$key||!sanro_studio_validate_product_price_table($override['table']??null,$profile['sizes']))return ['valid'=>false,'error'=>'product_price_table_invalid'];
        $profile['table']=$override['table'];
    }
    $sleeve = get_post_meta($product_id, '_sanro_studio_product_sleeve', true);
    if ($key === 'tunik_oversize_34') $sleeve = '3/4';
    elseif ($sleeve !== 'long') $sleeve = 'short';
    if ($sleeve === 'long' && empty($override['table'])) {
        return ['valid'=>false,'error'=>'long_sleeve_price_table_required'];
    }
    $extras = sanro_studio_product_second_side_prices($product_id);
    return ['valid'=>true,'profile'=>$key,'sleeve_label'=>$sleeve,'second_side'=>$extras,
        'revision'=>hash('sha256',json_encode([$key,$profile['table'],$sleeve,$extras,
            function_exists('sanro_studio_product_print_categories') ? sanro_studio_product_print_categories($product_id) : null]))]+$profile;
}
function sanro_studio_profile_unit_price($config,$size,$front,$back,$long=false) {
    if(empty($config['valid'])||!is_string($size)||!isset($config['table'][$size])||!is_bool($long))return ['valid'=>false,'error'=>'invalid_variant'];
    $categories=['A5','A4','A3','A3+'];$active=[];
    foreach([$front,$back] as $category){if($category===null)continue;$i=is_string($category)?array_search($category,$categories,true):false;if($i===false)return ['valid'=>false,'error'=>'invalid_category'];$active[]=$i;}
    if(!$active)return ['valid'=>false,'error'=>'empty_design'];rsort($active,SORT_NUMERIC);$extras=$config['second_side']??sanro_studio_default_second_side_prices();
    $price=$config['table'][$size][$active[0]]+(count($active)===2?$extras[$active[1]]:0);
    return ['valid'=>true,'currency'=>'IDR','unit_price'=>$price];
}
