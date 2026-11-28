<?php
defined('ABSPATH') || exit;

function sanro_studio_variation_for_mapping($product_id,$variation_id,$color_label,$size){
    if(!function_exists('wc_get_product')||!is_int($product_id)||$product_id<1||!is_int($variation_id)||$variation_id<1)return false;
    $parent=wc_get_product($product_id);$variation=wc_get_product($variation_id);
    if(!$parent||!$parent->is_type('variable')||!$variation||!$variation->is_type('variation')||$variation->get_parent_id()!==$product_id
        ||$variation->get_status()!=='publish')return false;
    $attrs=$variation->get_attributes();if(!is_array($attrs))return false;$color=null;$mapped_size=null;
    foreach($attrs as $key=>$value){$name=sanitize_title(preg_replace('/^attribute_/','',(string)$key));$name=preg_replace('/^pa-/','',$name);
        // Explicit supported Woo attribute slugs. The selected variation ID
        // remains the binding; its parent, status and actual attribute values
        // are checked below, so aliases do not weaken variation validation.
        if(in_array($name,['color','colour','warna'],true))$color=$value;
        if(in_array($name,['size','ukuran'],true))$mapped_size=$value;
    }
    $size_slug=is_string($mapped_size)?sanitize_title($mapped_size):'';$expected=sanitize_title($size);
    $aliases=['s'=>['s','small','small-s'],'m'=>['m','medium','medium-m'],'l'=>['l','large','large-l'],
        'xl'=>['xl','x-large','extra-large','extra-large-xl'],'2xl'=>['2xl','xxl','2x-large','xx-large'],
        '3xl'=>['3xl','xxxl','3x-large','xxx-large'],'4xl'=>['4xl','xxxxl','4x-large'],'5xl'=>['5xl','xxxxxl','5x-large']];
    return is_string($color)&&is_string($mapped_size)
        &&sanitize_title($color)===sanitize_title($color_label)&&in_array($size_slug,$aliases[$expected]??[$expected],true);
}
function sanro_studio_mockup_matrix_validate($rows,$sizes,$product_id=0){
    if(!is_array($rows)||($rows!==[]&&array_keys($rows)!==range(0,count($rows)-1))||count($rows)>100)return false;$out=[];$keys=[];
    foreach($rows as $row){
        if(!is_array($row)||array_diff(array_keys($row),['color','label','size','front','back','variation_id'])||!is_string($row['color']??null)||!preg_match('/^#[a-f0-9]{6}$/D',$row['color'])
            ||!is_string($row['label']??null)||$row['label']===''||strlen($row['label'])>80||!in_array($row['size']??null,array_merge(['*'],$sizes),true))return false;
        $key=$row['color'].'/'.$row['size'];if(isset($keys[$key]))return false;$keys[$key]=true;
        foreach(['front','back'] as $side){$id=sanro_studio_valid_mockup_attachment($row[$side]??null);if(!$id)return false;$row[$side]=$id;}
        $product=function_exists('wc_get_product')&&$product_id?wc_get_product($product_id):null;
        if($product&&$product->is_type('variable')){if(!is_int($row['variation_id']??null)||!sanro_studio_variation_for_mapping($product_id,$row['variation_id'],$row['label'],$row['size']))return false;}
        elseif(array_key_exists('variation_id',$row)&&$row['variation_id']!==null&&$row['variation_id']!==0)return false;
        $row['label']=sanitize_text_field($row['label']);$out[]=$row;
    }return $out;
}
function sanro_studio_mockup_matrix($product_id){
    $stored=get_post_meta($product_id,'_sanro_studio_mockup_matrix',true);if($stored===''||$stored===null)return [];
    $pricing=sanro_studio_product_pricing_config($product_id);$rows=sanro_studio_mockup_matrix_validate($stored,$pricing['sizes']??['S','M','L','XL','2XL','3XL','4XL','5XL'],(int)$product_id);
    return $rows===false?false:$rows;
}
function sanro_studio_mockup_palette($product_id){
    $rows=sanro_studio_mockup_matrix($product_id);$palette=[];if(is_array($rows))foreach($rows as $row)$palette[$row['color']]=$row['label'];
    return $palette?:['#ffffff'=>'Putih','#171717'=>'Hitam','#b91c1c'=>'Merah','#1d4ed8'=>'Biru','#166534'=>'Hijau'];
}
/** Current, purchasable WooCommerce variation map, keyed by displayed color and Studio size. */
function sanro_studio_variation_map($product_id){
    $product=function_exists('wc_get_product')?wc_get_product($product_id):null;
    if(!$product||!$product->is_type('variable'))return [];
    $rows=sanro_studio_mockup_matrix($product_id);if(!is_array($rows))return false;$map=[];
    foreach($rows as $row){
        if($row['size']==='*'||!isset($row['variation_id'])||!sanro_studio_variation_for_mapping((int)$product_id,$row['variation_id'],$row['label'],$row['size']))return false;
        if(isset($map[$row['color']][$row['size']]))return false;
        $variation=wc_get_product($row['variation_id']);
        $attrs=function_exists('wc_get_product_variation_attributes')?wc_get_product_variation_attributes($row['variation_id']):$variation->get_attributes();
        if(!is_array($attrs)||!$attrs)return false;
        $map[$row['color']][$row['size']]=['variation_id'=>$row['variation_id'],'attributes'=>$attrs,'available'=>$variation->is_purchasable()&&$variation->is_in_stock()];
    }
    return $map;
}
/**
 * Legacy catalog: prefer the all-sizes pair, otherwise take the first saved
 * pair for the color. Admin uses this same rule when opening the grouped UI.
 */
function sanro_studio_color_mockups_public($product_id) {
    $rows = sanro_studio_mockup_matrix($product_id);
    if ($rows === false) return false;

    $pairs = [];
    foreach ($rows as $row) {
        $color = $row['color'];
        if (!isset($pairs[$color]) || $row['size'] === '*') $pairs[$color] = $row;
    }
    $mockups = [];
    foreach ($pairs as $color => $row) {
        foreach (['front', 'back'] as $side) {
            $url = wp_get_attachment_image_url($row[$side], 'large');
            if (!is_string($url) || !preg_match('~^https?://~i', $url)) return false;
            $mockups[$color][$side] = esc_url_raw($url);
        }
    }
    return $mockups;
}
function sanro_studio_mockup_matrix_fields(){
    $id=absint(get_the_ID());if(!$id||!current_user_can('edit_post',$id))return;$rows=get_post_meta($id,'_sanro_studio_mockup_matrix',true);
    $pricing=sanro_studio_product_pricing_config($id);
    $product=wc_get_product($id);$options=[];if($product&&$product->is_type('variable'))foreach($product->get_children() as $vid){$v=wc_get_product($vid);if($v)$options[]=['id'=>(int)$vid,'attributes'=>$v->get_attributes(),'available'=>$v->get_status()==='publish'&&$v->is_purchasable()&&$v->is_in_stock()];}
    echo '<div class="options_group sanro-mockup-matrix" data-variable="'.($product&&$product->is_type('variable')?'1':'0').'" data-variations="'.esc_attr(wp_json_encode($options)).'" data-sizes="'.esc_attr(wp_json_encode($pricing['sizes']??['S','M','L','XL','2XL','3XL','4XL','5XL'])).'"><p class="form-field"><strong>Mockup standar per warna</strong></p><input type="hidden" name="_sanro_studio_mockup_matrix" value="'.esc_attr(wp_json_encode(is_array($rows)?$rows:[])).'"><table class="widefat"><thead><tr><th>Warna</th><th>Nama atribut warna WooCommerce</th><th>Depan</th><th>Belakang</th><th>Variasi WooCommerce</th><th>Aksi</th></tr></thead><tbody></tbody></table><p><button type="button" class="button" data-add-mapping>Tambah warna</button></p><p>Gunakan satu gambar per sisi untuk semua ukuran. Pada produk variable, pilih variasi WooCommerce untuk setiap ukuran yang dijual.</p><noscript>Aktifkan JavaScript untuk mengubah mapping. Mapping sebelumnya tetap dipertahankan.</noscript></div>';

}
function sanro_studio_mockup_matrix_save($product){
    if(!is_object($product)||!method_exists($product,'get_id')||!method_exists($product,'update_meta_data')||!current_user_can('edit_post',$product->get_id())||!is_string($_POST['_sanro_studio_mockup_matrix']??null))return;
    $raw=wp_unslash($_POST['_sanro_studio_mockup_matrix']);$pricing=sanro_studio_product_pricing_config($product->get_id());
    $rows=strlen($raw)<=40000?sanro_studio_mockup_matrix_validate(json_decode($raw,true),$pricing['sizes']??['S','M','L','XL','2XL','3XL','4XL','5XL'],(int)$product->get_id()):false;
    if($rows===false){if(class_exists('WC_Admin_Meta_Boxes'))WC_Admin_Meta_Boxes::add_error('Mapping mockup SANRO tidak valid; mapping sebelumnya dipertahankan.');return;}
    $product->update_meta_data('_sanro_studio_mockup_matrix',$rows);
}
function sanro_studio_record_preview_refs($record,$size=null){
    if(is_string($size)&&isset($record['preview_refs_by_size'][$size]))return $record['preview_refs_by_size'][$size];
    // New mapped snapshots cannot quietly substitute another size. Existing orders without this marker remain compatible.
    if(!empty($record['preview_variant_required'])&&is_string($size))return [];
    return $record['preview_refs']??[];
}
