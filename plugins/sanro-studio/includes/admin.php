<?php
defined('ABSPATH') || exit;

function sanro_studio_admin_menu() {
    add_submenu_page('woocommerce', 'SANRO Studio', 'SANRO Studio', 'manage_options',
        'sanro-studio', 'sanro_studio_render_settings');
}

function sanro_studio_save_settings() {
    if (!current_user_can('manage_options')) {
        wp_die('Akses ditolak.', '', ['response' => 403]);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        wp_die('Gunakan formulir pengaturan.', '', ['response' => 405]);
    }
    check_admin_referer('sanro_studio_settings');
    // One dedicated option; never write Lumise, KLD, OAuth, product or order settings here.
    update_option('sanro_studio_options', [
        'admin_preview' => isset($_POST['admin_preview']) && $_POST['admin_preview'] === 'yes' ? 'yes' : 'no',
    ], false);
    wp_safe_redirect(admin_url('admin.php?page=sanro-studio&saved=1'));
    exit;
}

function sanro_studio_render_settings() {
    if (!current_user_can('manage_options')) {
        wp_die('Akses ditolak.', '', ['response' => 403]);
    }
    $state = sanro_studio_dependencies();
    $runtime = sanro_studio_runtime_status();
    $options = sanro_studio_options();
    echo '<div class="wrap"><h1>SANRO Studio</h1>';
    echo '<p>Fondasi editor SANRO Studio. Integrasi harga Kaoscetak dan Drive belum diaktifkan.</p>';
    echo '<table class="widefat striped"><tbody>';
    $rows = [
        'WooCommerce' => $state['woocommerce'] ? 'Terdeteksi' : 'Belum aktif',
        'Core editor' => $state['lumise'] ? 'Terdeteksi' : 'Belum aktif',
        'Versi core' => $state['lumise_version'] !== '' ? $state['lumise_version'] : 'Tidak tersedia',
        'Kompatibilitas fondasi' => $state['supported'] ? 'Versi 2.1.2 dikenali; QA editor masih diperlukan' : 'Profil tidak diterapkan',
        'Mode pelanggan' => 'Tetap memakai alur editor yang sudah ada',
    ];
    $runtime_labels = [
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
    foreach ($rows as $label => $value) {
        echo '<tr><th scope="row">' . esc_html($label) . '</th><td>' . esc_html($value) . '</td></tr>';
    }
    echo '</tbody></table>';
    $page_id = (int) get_option('lumise_editor_page', 0);
    $editor_url = $page_id > 0 ? get_permalink($page_id) : false;
    if ($editor_url) {
        echo '<p><a class="button" href="' . esc_url($editor_url) . '">Buka halaman editor yang sudah diatur</a></p>';
    }
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="sanro_studio_save">';
    wp_nonce_field('sanro_studio_settings');
    echo '<p><label><input type="checkbox" name="admin_preview" value="yes" ';
    checked($options['admin_preview']);
    echo '> Pratinjau profil editor sederhana untuk administrator</label></p>';
    echo '<p>Profil menyederhanakan menu dan toolbar pada sesi administrator. Ini belum membatasi jumlah objek atau memindahkan master ke Drive.</p>';
    submit_button('Simpan pengaturan SANRO Studio');
    echo '</form></div>';
}
