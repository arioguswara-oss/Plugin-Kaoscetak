<?php
defined('ABSPATH') || exit;

/**
 * Approved front/back reference mockups from the WordPress Media Library.
 * Mockups are public product illustrations, NEVER private print masters.
 * Do not accept a buyer-provided image URL or arbitrary external URL.
 */
function sanro_studio_valid_mockup_attachment($value) {
    if (!(is_int($value) || is_string($value))
        || !preg_match('/^[1-9][0-9]*$/D', (string) $value)) return 0;
    $id = (int) $value;
    if ($id < 1 || !function_exists('get_post') || !function_exists('wp_attachment_is_image')) return 0;
    $attachment=get_post($id);
    if (!is_object($attachment)||($attachment->post_type??null)!=='attachment'
        ||($attachment->post_status??null)!=='inherit'||!wp_attachment_is_image($id)) return 0;
    // get_post_status() resolves "inherit" through a parent, and orphaned
    // inherited attachments are effectively public on WordPress. Inspect raw
    // status, then separately enforce visibility/password on any parent.
    $parent_id=absint($attachment->post_parent??0);
    if($parent_id){
        $parent=get_post($parent_id);
        if(!is_object($parent)||($parent->post_status??null)!=='publish'
            ||!empty($parent->post_password)
            ||(function_exists('is_post_publicly_viewable')&&!is_post_publicly_viewable($parent)))return 0;
    }elseif(function_exists('get_post_status')&&get_post_status($id)!=='publish')return 0;
    return $id;
}

function sanro_studio_product_mockup_attachments($product_id) {
    $product_id = absint($product_id);
    if (!$product_id) return [];
    $stored = get_post_meta($product_id, '_sanro_studio_mockups', true);
    if (!is_array($stored)) return [];
    $result = [];
    foreach (['front', 'back'] as $side) {
        if (!isset($stored[$side])) continue;
        $id = sanro_studio_valid_mockup_attachment($stored[$side]);
        if ($id) $result[$side] = $id;
    }
    return $result;
}

/** Public thumbnail URLs, never arbitrary metadata, private paths or full-size originals. */
function sanro_studio_product_mockup_urls($product_id) {
    $result = [];
    if (!function_exists('wp_get_attachment_image_url')) return $result;
    foreach (sanro_studio_product_mockup_attachments($product_id) as $side => $id) {
        $url = wp_get_attachment_image_url($id, 'large');
        if (is_string($url) && $url !== '' && preg_match('~^https?://~i', $url)) {
            $result[$side] = esc_url_raw($url);
        }
    }
    return $result;
}

/** Render only within WooCommerce's authenticated product editing form. */
function sanro_studio_product_mockup_fields() {
    if (!function_exists('woocommerce_wp_text_input')) return;
    $product_id = absint(get_the_ID());
    if (!$product_id || !current_user_can('edit_post', $product_id)) return;
    $mockups = sanro_studio_product_mockup_attachments($product_id);
    echo '<div class="options_group"><p><strong>Mockup SANRO Studio — depan / belakang</strong></p>';
    echo '<p class="form-field">Pilih gambar produk dari Media Library. Ini bukan master cetak DTF.</p>';
    foreach (['front' => 'Depan', 'back' => 'Belakang'] as $side => $label) {
        woocommerce_wp_text_input([
            'id' => '_sanro_studio_mockup_' . $side,
            'label' => 'Mockup ' . $label . ' (attachment ID)',
            'type' => 'number',
            'custom_attributes' => ['min' => '1', 'step' => '1'],
            'value' => $mockups[$side] ?? '',
            'description' => 'Gambar publik produk di WordPress Media Library. Kosongkan untuk menggunakan pratinjau kaos standar.',
            'desc_tip' => true,
        ]);
        echo '<p class="form-field"><button type="button" class="button sanro-choose-mockup" data-sanro-side="'
            . esc_attr($side) . '">Pilih mockup ' . esc_html($label) . '</button> ';
        echo '<button type="button" class="button sanro-clear-mockup" data-sanro-side="'
            . esc_attr($side) . '">Hapus pilihan</button></p>';
        // Show the current, validated Media Library thumbnail after page reload.
        // Use a scoped image slot for the picker without injecting HTML in JS.
        $thumb = isset($mockups[$side]) ? wp_get_attachment_image_url($mockups[$side], 'thumbnail') : false;
        $thumb_ok = is_string($thumb) && preg_match('~^https?://~i', $thumb);
        echo '<p class="form-field"><img id="sanro-mockup-preview-' . esc_attr($side)
            . '" class="sanro-mockup-thumbnail" alt="Pratinjau mockup ' . esc_attr($label)
            . '" width="112" height="112" style="object-fit:contain;max-width:112px;border:1px solid #cbd5e1;border-radius:6px"'
            . ($thumb_ok ? ' src="' . esc_url($thumb) . '"' : ' hidden')
            . '></p>';
    }
    echo '</div>';
}

/**
 * Called by WooCommerce product object save after WooCommerce's nonce check.
 * Validate the complete new config before writing a single field. Never allow
 * public/buyer requests to change product mockup metadata.
 */
function sanro_studio_save_product_mockups($product) {
    if (!is_object($product) || !method_exists($product, 'get_id')
        || !method_exists($product, 'update_meta_data')) return false;
    $id = absint($product->get_id());
    if (!$id || !current_user_can('edit_post', $id)) return false;
    $result = [];
    foreach (['front', 'back'] as $side) {
        $key = '_sanro_studio_mockup_' . $side;
        if (!array_key_exists($key, $_POST) || !is_string($_POST[$key])) return false;
        $value = trim(wp_unslash($_POST[$key]));
        if ($value === '') continue;
        $attachment_id = sanro_studio_valid_mockup_attachment($value);
        if (!$attachment_id) {
            if(class_exists('WC_Admin_Meta_Boxes'))WC_Admin_Meta_Boxes::add_error('Mockup '.$side.' tidak disimpan. Pilih gambar publik dari Media Library; konfigurasi sebelumnya tetap dipertahankan.');
            return false;
        }
        $result[$side] = $attachment_id;
    }
    $product->update_meta_data('_sanro_studio_mockups', $result);
    return true;
}

/** Load Media Library chooser only for edit/new WooCommerce product screens. */
function sanro_studio_enqueue_product_mockup_admin($hook) {
    if (!in_array($hook, ['post.php', 'post-new.php'], true)
        || !function_exists('get_current_screen')) return;
    $screen = get_current_screen();
    if (!is_object($screen) || ($screen->id ?? '') !== 'product'
        || !current_user_can('edit_products')) return;
    wp_enqueue_media();
    wp_enqueue_script(
        'sanro-studio-mockup-admin',
        plugin_dir_url(__DIR__ . '/../sanro-studio.php') . 'assets/admin-mockups.js',
        ['jquery'], SANRO_STUDIO_VERSION . '-' . substr(hash_file('sha256', dirname(__DIR__).'/assets/admin-mockups.js'),0,12), true
    );
    wp_enqueue_script('sanro-studio-mockup-mapping', plugin_dir_url(__DIR__ . '/../sanro-studio.php') . 'assets/admin-mockup-mapping.js', ['sanro-studio-mockup-admin'], SANRO_STUDIO_VERSION . '-' . substr(hash_file('sha256', dirname(__DIR__).'/assets/admin-mockup-mapping.js'),0,12), true);
    wp_enqueue_script(
        'sanro-studio-print-zone-admin',
        plugin_dir_url(__DIR__ . '/../sanro-studio.php') . 'assets/admin-print-zones.js',
        ['sanro-studio-mockup-admin'], SANRO_STUDIO_VERSION . '-' . substr(hash_file('sha256', dirname(__DIR__).'/assets/admin-print-zones.js'),0,12), true
    );
}
