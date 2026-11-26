<?php
defined('ABSPATH') || exit;

/**
 * Product-level opt-in for the approved 24s price schedule.
 * Other products remain unconfigured until their own tables are implemented.
 */
function sanro_studio_product_pricing_fields() {
    if (!function_exists('woocommerce_wp_select')) return;
    $product_id = get_the_ID();
    $current = get_post_meta($product_id, '_sanro_studio_pricing_profile', true);
    $presets=function_exists('sanro_studio_pricing_presets')?sanro_studio_pricing_presets():['cotton_combed_24s'=>['label'=>'Cotton Combed 24s']];
    $options=[''=>'Nonaktif (belum dikonfigurasi)'];foreach($presets as $key=>$preset)$options[$key]=$preset['label'];
    woocommerce_wp_select([
        'id' => '_sanro_studio_pricing_profile',
        'label' => 'SANRO Studio — Profil harga',
        'value' => is_string($current)&&isset($presets[$current])?$current:'',
        'options' => $options,
        'description' => 'Harga dari server. Produk lain membutuhkan tabel harga tersendiri.',
        'desc_tip' => true,
    ]);
    woocommerce_wp_select([
        'id' => '_sanro_studio_product_sleeve',
        'label' => 'SANRO Studio — Model produk kaos',
        'value' => get_post_meta($product_id, '_sanro_studio_product_sleeve', true) === 'long' ? 'long' : 'short',
        'options' => ['short' => 'Lengan pendek', 'long' => 'Lengan panjang'],
        'description' => 'Pakai produk WooCommerce tersendiri dan tabel harga khusus untuk kaos lengan panjang. Profil tunik selalu lengan 3/4.',
        'desc_tip' => true,
    ]);
    if(function_exists('woocommerce_wp_textarea_input')){
        $override=get_post_meta($product_id,'_sanro_studio_price_table',true);
        woocommerce_wp_textarea_input(['id'=>'_sanro_studio_price_table_json','label'=>'SANRO Studio — Tabel khusus produk (JSON)','value'=>is_array($override)&&isset($override['table'])?json_encode($override['table'],JSON_PRETTY_PRINT):'',
            'description'=>'Kosong = preset resmi. Setiap size profil wajib memiliki empat harga IDR berurutan A5/A4/A3/A3+. Tidak mengubah produk lain atau preset.','desc_tip'=>true]);
    }
    if (function_exists('woocommerce_wp_text_input') && function_exists('sanro_studio_product_second_side_prices')) {
        echo '<p><strong>Tarif cetak sisi tambahan (IDR)</strong></p>';
        $extras = sanro_studio_product_second_side_prices($product_id);
        foreach (['A5','A4','A3','A3+'] as $index => $category) {
            woocommerce_wp_text_input([
                'id'=>'_sanro_extra_side_'.$index, 'label'=>$category,
                'type'=>'number', 'value'=>$extras[$index],
                'custom_attributes'=>['step'=>'1','min'=>'0','max'=>'1000000'],
            ]);
        }
    }

}


/**
 * WooCommerce has already verified the product-editor nonce.
 * Fail closed on missing/tampered values, without silently changing pricing.
 */
function sanro_studio_save_product_pricing($product) {
    if (!is_object($product) || !method_exists($product, 'get_id')
        || !method_exists($product, 'update_meta_data')
        || !current_user_can('edit_post', $product->get_id())
        || !isset($_POST['_sanro_studio_pricing_profile'])
        || !is_string($_POST['_sanro_studio_pricing_profile'])) return;
    $profile = wp_unslash($_POST['_sanro_studio_pricing_profile']);
    $allowed=['','cotton_combed_24s','tunik_oversize_34'];if(!in_array($profile,$allowed,true))return;
    $sleeve = $_POST['_sanro_studio_product_sleeve'] ?? get_post_meta($product->get_id(), '_sanro_studio_product_sleeve', true);
    if (!in_array($sleeve, ['', 'short', 'long'], true)) return;
    if ($profile === 'tunik_oversize_34') $sleeve = 'short';
    $override=null;
    if(array_key_exists('_sanro_studio_price_table_json',$_POST)){
        if(!is_string($_POST['_sanro_studio_price_table_json']))return;
        $raw=trim(wp_unslash($_POST['_sanro_studio_price_table_json']));
        if($raw!==''){$table=json_decode($raw,true);$presets=sanro_studio_pricing_presets();
            if(!isset($presets[$profile])||!sanro_studio_validate_product_price_table($table,$presets[$profile]['sizes'])){if(class_exists('WC_Admin_Meta_Boxes'))WC_Admin_Meta_Boxes::add_error('SANRO Studio: tabel harga tidak valid; profil/harga sebelumnya dipertahankan.');return;}
            $override=['profile'=>$profile,'table'=>$table];
        }else $override=[];
    }
    $effective_override = $override ?? get_post_meta($product->get_id(), '_sanro_studio_price_table', true);
    if ($sleeve === 'long' && $profile !== '' && (empty($effective_override['table']) || ($effective_override['profile'] ?? null) !== $profile)) {
        if (class_exists('WC_Admin_Meta_Boxes')) WC_Admin_Meta_Boxes::add_error('SANRO Studio: isi tabel harga khusus untuk produk lengan panjang.');
        return;
    }
    $extras = [];
    for ($index = 0; $index < 4; $index++) {
        $key = '_sanro_extra_side_'.$index;
        if (!array_key_exists($key,$_POST)) continue;
        $amount = $_POST[$key];
        if (!is_string($amount) || !preg_match('/^(0|[1-9][0-9]{0,6})$/D',$amount) || (int)$amount > 1000000) {
            if (class_exists('WC_Admin_Meta_Boxes')) WC_Admin_Meta_Boxes::add_error('Tarif sisi tambahan harus bilangan bulat IDR.');
            return;
        }
        $extras[$index] = (int)$amount;
    }
    if ($extras && count($extras) !== 4) {
        if (class_exists('WC_Admin_Meta_Boxes')) WC_Admin_Meta_Boxes::add_error('Lengkapi keempat tarif sisi tambahan.');
        return;
    }
    if ($extras) $product->update_meta_data('_sanro_studio_second_side_prices',$extras);
    $product->update_meta_data('_sanro_studio_product_sleeve', $sleeve);
    if($override!==null)$product->update_meta_data('_sanro_studio_price_table',$override);
    $product->update_meta_data('_sanro_studio_pricing_profile', $profile);
}
