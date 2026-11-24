<?php
defined('ABSPATH') || exit;

/** One independent root; retain both settings-admin and Woo manager access. */
function sanro_studio_admin_menu() {
    $capability = current_user_can('manage_options') ? 'manage_options' : 'manage_woocommerce';
    add_menu_page('SANRO Studio', 'SANRO Studio', $capability,
        'sanro-studio', 'sanro_studio_admin_landing', 'dashicons-art', 56);
    add_submenu_page('sanro-studio', 'Pengaturan SANRO Studio', 'Pengaturan',
        'manage_options', 'sanro-studio', 'sanro_studio_admin_landing');
}

/** Stable root URL: settings for administrators, status for Woo managers. */
function sanro_studio_admin_landing() {
    if (current_user_can('manage_options')) {
        sanro_studio_render_settings();
        return;
    }
    if (current_user_can('manage_woocommerce')) {
        sanro_studio_bootstrap_admin_page();
        return;
    }
    wp_die('Akses ditolak.', '', ['response' => 403]);
}

function sanro_studio_save_settings() {
    if (!current_user_can('manage_options')) {
        wp_die('Akses ditolak.', '', ['response' => 403]);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        wp_die('Gunakan formulir pengaturan.', '', ['response' => 405]);
    }
    check_admin_referer('sanro_studio_settings');
    // Native page selector and legacy preview use distinct actions; do not overwrite either accidentally.
    $settings_type = isset($_POST['settings_type']) && is_string($_POST['settings_type']) ? sanitize_key(wp_unslash($_POST['settings_type'])) : '';
    if ($settings_type === 'editor_page') {
        $page_id = isset($_POST['editor_page_id']) ? absint(wp_unslash($_POST['editor_page_id'])) : 0;
        if ($page_id && (get_post_type($page_id) !== 'page' || get_post_status($page_id) !== 'publish')) {
            wp_die('Halaman editor harus berupa halaman WordPress yang diterbitkan.', '', ['response' => 400]);
        }
        update_option('sanro_studio_editor_page_id', $page_id, false);
    } elseif ($settings_type === 'native_preferences') {
        $label = isset($_POST['studio_label']) && is_string($_POST['studio_label'])
            ? sanitize_text_field(wp_unslash($_POST['studio_label'])) : '';
        $label = trim($label);
        if ($label === '' || strlen($label) > 80) {
            wp_die('Nama studio wajib diisi dan maksimal 80 byte.', '', ['response' => 400]);
        }
        $default_side = isset($_POST['default_side']) && is_string($_POST['default_side'])
            ? sanitize_key(wp_unslash($_POST['default_side'])) : '';
        if (!in_array($default_side, ['front', 'back'], true)) {
            wp_die('Sisi awal tidak valid.', '', ['response' => 400]);
        }
        update_option('sanro_studio_native_preferences', [
            'studio_label' => $label,
            'default_side' => $default_side,
        ], false);
    } elseif ($settings_type === 'legacy_preview' && defined('SANRO_STUDIO_ENABLE_LEGACY_COMPAT') && SANRO_STUDIO_ENABLE_LEGACY_COMPAT === true) {
        update_option('sanro_studio_options', [
            'admin_preview' => isset($_POST['admin_preview']) && $_POST['admin_preview'] === 'yes' ? 'yes' : 'no',
        ], false);
    } else {
        wp_die('Jenis pengaturan tidak valid.', '', ['response' => 400]);
    }
    wp_safe_redirect(admin_url('admin.php?page=sanro-studio&saved=1'));
    exit;
}

function sanro_studio_render_settings() {
    if (!current_user_can('manage_options')) {
        wp_die('Akses ditolak.', '', ['response' => 403]);
    }
    $legacy_compat = defined('SANRO_STUDIO_ENABLE_LEGACY_COMPAT') && SANRO_STUDIO_ENABLE_LEGACY_COMPAT === true;
    // Legacy helpers live in runtime.php, which is deliberately not loaded in native mode.
    $state = $legacy_compat ? sanro_studio_dependencies() : [
        'woocommerce' => class_exists('WooCommerce', false),
        'lumise' => false,
        'lumise_version' => '',
    ];
    $runtime = $legacy_compat ? sanro_studio_runtime_status() : ['state' => 'native_default', 'mode' => 'native'];
    $options = $legacy_compat ? sanro_studio_options() : ['admin_preview' => false];
    $native = sanro_studio_native_capabilities();
    echo '<div class="wrap"><h1>SANRO Studio</h1>';
    echo '<p>Editor SANRO Studio native dan integrasi WooCommerce. Fitur penulisan checkout, produksi, dan Drive tetap mengikuti konfigurasi server.</p>';
    echo '<table class="widefat striped"><tbody>';
    $rows = [
        'WooCommerce' => $state['woocommerce'] ? 'Terdeteksi' : 'Belum aktif',
        'Mode core' => $legacy_compat ? 'Kompatibilitas lama (opt-in)' : 'SANRO Studio native',
        'Editor buyer' => sanro_studio_frontend_enabled() ? 'Aktif melalui konfigurasi server' : 'Nonaktif (default)',
        'Draft API' => sanro_studio_draft_rest_enabled() ? 'Aktif melalui konfigurasi server' : 'Nonaktif (default)',
        'Checkout WooCommerce' => function_exists('sanro_studio_pricing_wc_enabled')
            && sanro_studio_pricing_wc_enabled()
            ? 'Aktif melalui konfigurasi server'
            : (function_exists('sanro_studio_pricing_lock_order')
                ? 'Kode tersedia; writer menunggu aktivasi server'
                : 'Komponen checkout belum dimuat'),
    ];
    // Expose only operational state, never the private absolute filesystem path.
    $private_path = defined('SANRO_STUDIO_PRIVATE_MASTER_DIR')
        ? SANRO_STUDIO_PRIVATE_MASTER_DIR
        : get_option('sanro_studio_auto_private_master_dir', '');
    $private_ready = function_exists('sanro_studio_private_master_path_safe')
        && sanro_studio_private_master_path_safe($private_path);
    $upload_ready = defined('SANRO_STUDIO_ENABLE_MASTER_UPLOAD')
        && SANRO_STUDIO_ENABLE_MASTER_UPLOAD === true
        && defined('SANRO_STUDIO_ENABLE_PRIVATE_MASTERS')
        && SANRO_STUDIO_ENABLE_PRIVATE_MASTERS === true;
    $rows['Master DTF privat'] = !$private_ready
        ? 'Belum tersedia: hosting tidak mengizinkan folder privat aman di luar webroot'
        : ($upload_ready ? 'Siap: folder privat aman dan upload aktif' : 'Folder aman; menunggu aktivasi buyer dan draft');

    $runtime_labels = [
        'native_default' => 'SANRO native aktif sebagai fondasi; bootstrap editor lama tidak dijalankan',
        'waiting' => 'Menunggu inisialisasi',
        'external_loaded' => 'Kompatibilitas: core dari plugin lama yang masih aktif',
        'external_unsupported' => 'Versi core eksternal belum didukung',
        'internal_loaded' => 'Core internal dimuat; QA editor lengkap masih diperlukan',
        'woocommerce_missing' => 'WooCommerce belum aktif',
        'package_missing' => 'Paket core internal belum disertakan',
        'package_incomplete' => 'Paket core internal tidak lengkap',
        'package_changed' => 'Paket core berbeda dari source yang diperiksa',
        'manifest_invalid' => 'Manifest core tidak valid',
        'schema_missing' => 'Database core belum tersedia; pemasangan data perlu ditinjau',
        'symbol_conflict' => 'Komponen editor lain bentrok; core internal tidak dimuat',
        'bootstrap_incomplete' => 'Inisialisasi core internal belum lengkap',
    ];
    $rows['Pemuat core'] = $runtime_labels[$runtime['state']] ?? 'Status belum dikenali';
    if ($legacy_compat) {
        $rows['Core kompatibilitas'] = $state['lumise'] ? 'Terdeteksi' : 'Belum aktif';
        $rows['Versi kompatibilitas'] = $state['lumise_version'] !== '' ? $state['lumise_version'] : 'Tidak tersedia';
    }
    foreach ($rows as $label => $value) {
        echo '<tr><th scope="row">' . esc_html($label) . '</th><td>' . esc_html($value) . '</td></tr>';
    }
    echo '</tbody></table>';
    echo '<h2>Kapabilitas SANRO Studio native (tanpa core editor lama)</h2>';
    echo '<table class="widefat striped"><tbody>';
    $native_labels = [
        'buyer_editor' => 'Editor buyer',
        'product_configuration' => 'Konfigurasi produk',
        'design_validation' => 'Validasi desain',
        'server_quote' => 'Perhitungan harga server',
        'private_text_drafts' => 'Draft teks privat',
        'private_master_storage' => 'Penyimpanan file master privat',
        'checkout' => 'Checkout',
        'production_ready' => 'Siap production',
    ];
    foreach ($native_labels as $key => $label) {
        echo '<tr><th scope="row">' . esc_html($label) . '</th><td>'
            . esc_html(!empty($native[$key]) ? 'Kode tersedia (belum tentu lolos QA)' : 'Belum tersedia')
            . '</td></tr>';
    }
    echo '</tbody></table>';
    echo '<h2>Pengaturan Umum</h2>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="sanro_studio_save">';
    echo '<input type="hidden" name="settings_type" value="editor_page">';
    wp_nonce_field('sanro_studio_settings');
    echo '<p><label for="sanro-editor-page">Halaman Editor</label></p>';
    wp_dropdown_pages(['name' => 'editor_page_id', 'id' => 'sanro-editor-page', 'selected' => (int) get_option('sanro_studio_editor_page_id', 0), 'show_option_none' => '— Pilih halaman WordPress —', 'option_none_value' => '0', 'post_status' => 'publish']);
    echo '<p class="description">Pilih halaman WordPress yang akan menjadi editor SANRO Studio. Nama dan slug bebas. Editor buyer tetap memerlukan aktivasi staging.</p>';
    submit_button('Simpan Halaman Editor');
    echo '</form>';
    $preferences = get_option('sanro_studio_native_preferences', []);
    if (!is_array($preferences)) $preferences = [];
    $studio_label = isset($preferences['studio_label']) && is_string($preferences['studio_label'])
        ? $preferences['studio_label'] : 'SANRO Studio';
    $default_side = ($preferences['default_side'] ?? 'front') === 'back' ? 'back' : 'front';
    echo '<h2>Preferensi Studio Native</h2>';
    echo '<p class="description">Preferensi sesi editor buyer. Menyimpan preferensi tidak mengaktifkan writer checkout atau mengubah produk.</p>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="sanro_studio_save">';
    echo '<input type="hidden" name="settings_type" value="native_preferences">';
    wp_nonce_field('sanro_studio_settings');
    echo '<p><label for="sanro-studio-label">Nama Studio</label></p>';
    echo '<input id="sanro-studio-label" name="studio_label" type="text" maxlength="80" required class="regular-text" value="' . esc_attr($studio_label) . '">';
    echo '<p><label for="sanro-default-side">Sisi desain awal</label></p>';
    echo '<select id="sanro-default-side" name="default_side">';
    echo '<option value="front"' . selected($default_side, 'front', false) . '>Depan</option>';
    echo '<option value="back"' . selected($default_side, 'back', false) . '>Belakang</option>';
    echo '</select>';
    submit_button('Simpan Preferensi Native');
    echo '</form>';
    // Legacy editor links are never promoted in the native admin mode.
    if ($legacy_compat) {
        $page_id = (int) get_option('lumise_editor_page', 0);
        $editor_url = $page_id > 0 ? get_permalink($page_id) : false;
        if ($editor_url) {
            echo '<p><a class="button" href="' . esc_url($editor_url) . '">Buka halaman editor kompatibilitas</a></p>';
        }
    }
    if ($legacy_compat) {
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="sanro_studio_save">';
    echo '<input type="hidden" name="settings_type" value="legacy_preview">';
    wp_nonce_field('sanro_studio_settings');
    echo '<p><label><input type="checkbox" name="admin_preview" value="yes" ';
    checked($options['admin_preview']);
    echo '> Pratinjau profil editor sederhana untuk administrator</label></p>';
    echo '<p>Profil menyederhanakan menu dan toolbar pada sesi administrator. Ini belum membatasi jumlah objek atau memindahkan master ke Drive.</p>';
    submit_button('Simpan pengaturan SANRO Studio');
    echo '</form>';
    } else {
        echo '<p>Mode native tidak memerlukan pengaturan editor kompatibilitas. Buyer dan draft API hanya diaktifkan melalui konfigurasi server staging.</p>';
    }
    echo '</div>';
}
