<?php
defined('ABSPATH') || exit;

/**
 * Frontend entrypoint is opt-in and inert until SANRO Studio is explicitly
 * enabled on a staging site. No existing WooCommerce purchase flow is replaced.
 */
function sanro_studio_frontend_enabled() {
    return defined('SANRO_STUDIO_ENABLE_BUYER_EDITOR') && SANRO_STUDIO_ENABLE_BUYER_EDITOR === true;
}

function sanro_studio_product_editor_link() {
    if (!sanro_studio_frontend_enabled() || !function_exists('is_product') || !is_product()) return;
    global $product;
    if (!is_object($product) || !method_exists($product, 'get_id')) return;
    $id = absint($product->get_id());
    if (empty(sanro_studio_public_editor_config($id)['valid'])) return;
    $url = add_query_arg(['sanro_studio_editor' => '1', 'product_id' => $id], get_permalink($id));
    echo '<p class="sanro-studio-entry"><a class="button alt" href="' . esc_url($url) . '">Custom Desain (SANRO Studio)</a></p>';
}

/** Dedicated buyer editor view, not wp-admin or Edit Post. */
function sanro_studio_render_buyer_editor() {
    if (!sanro_studio_frontend_enabled() || !isset($_GET['sanro_studio_editor'])
        || !is_string($_GET['sanro_studio_editor']) || $_GET['sanro_studio_editor'] !== '1') return;
    if (!function_exists('is_product') || !is_product()) return;
    $product_id = absint(get_queried_object_id());
    if (!$product_id || !isset($_GET['product_id']) || !is_scalar($_GET['product_id'])
        || (string) $product_id !== (string) $_GET['product_id']) return;
    $config = sanro_studio_public_editor_config($product_id);
    if (empty($config['valid'])) return;

    // Explicit product identity for the REST draft creation payload.
    $config['product_id'] = $product_id;
    // Render as a dedicated view: no WooCommerce add-to-cart form is included.
    $asset = plugin_dir_url(__DIR__ . '/../sanro-studio.php') . 'assets/';
    wp_enqueue_style('sanro-studio-buyer', $asset . 'buyer.css', [], SANRO_STUDIO_VERSION);
    wp_enqueue_script('sanro-studio-estimate', $asset . 'estimate.js', [], SANRO_STUDIO_VERSION, true);
    wp_enqueue_script('sanro-studio-buyer', $asset . 'buyer.js', ['sanro-studio-estimate'], SANRO_STUDIO_VERSION, true);
    // Supply API bootstrap only on explicitly enabled staging; no owner token
    // is ever included in page HTML or a shared JavaScript configuration.
    if (function_exists('sanro_studio_draft_rest_enabled') && sanro_studio_draft_rest_enabled()) {
        // Do not serve stale per-page nonce bootstrap through page caches.
        if (function_exists('nocache_headers')) nocache_headers();
        $config['draft_api'] = [
            'url' => esc_url_raw(rest_url('sanro-studio/v1/drafts')),
            'nonce' => wp_create_nonce('sanro_studio_draft'),
        ];
    }
    wp_add_inline_script('sanro-studio-buyer', 'window.SANRO_STUDIO_EDITOR_CONFIG=' . wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';', 'before');

    // WordPress may have a canonical redirect for product query arguments.
    // This view is selected only after product identity and visibility checks.
    get_header();
    echo '<main id="sanro-studio-buyer" class="sanro-buyer" aria-label="SANRO Studio">';
    echo '<header><h1>SANRO Studio</h1><p>Editor kaos — pratinjau saja, belum dapat checkout</p></header>';
    echo '<div class="sanro-layout"><section class="sanro-preview" aria-label="Pratinjau desain">';
    echo '<nav aria-label="Sisi kaos"><button type="button" data-side="front" aria-pressed="true">Depan</button> <button type="button" data-side="back" aria-pressed="false">Belakang</button></nav>';
    echo '<div class="sanro-shirt"><div class="sanro-safe"><img alt="Gambar desain" hidden><div class="sanro-text" hidden></div></div></div>';
    echo '<p id="sanro-side-summary" role="status">Depan: kosong · Belakang: kosong</p>';
    echo '<p class="sanro-hint">Geser gambar atau teks. Ukuran cetak dihitung dalam cm.</p></section>';
    echo '<section class="sanro-controls"><h2>Atur desain</h2>';
    echo '<label>Warna kaos<select id="sanro-shirt-color"><option value="#ffffff">Putih</option><option value="#171717">Hitam</option><option value="#b91c1c">Merah</option><option value="#1d4ed8">Biru</option><option value="#166534">Hijau</option></select></label>';
    echo '<fieldset class="sanro-size-qty"><legend>Jumlah per ukuran</legend>';
    foreach (['S','M','L','XL','2XL','3XL','4XL','5XL'] as $size) {
        echo '<label>' . esc_html($size) . '<input type="number" data-size="' . esc_attr($size) . '" min="0" max="999" step="1" value="0" inputmode="numeric"></label>';
    }
    echo '</fieldset><p id="sanro-qty-total" role="status">Total: 0 pcs</p>';
    echo '<p id="sanro-price-estimate" role="status">Estimasi harga: pilih desain dan jumlah. Harga final dihitung saat checkout.</p>';
    echo '<label>Gambar PNG/JPEG maks. 10 MB<input type="file" id="sanro-image" accept="image/png,image/jpeg"></label>';
    echo '<label>Ukuran gambar<input type="range" id="sanro-image-size" min="10" max="100" value="60"></label>';
    echo '<button type="button" id="sanro-center-image">Posisikan gambar ke tengah</button>';
    echo '<button type="button" id="sanro-remove-image">Hapus gambar</button>';
    echo '<label>Teks<textarea id="sanro-text-input" maxlength="300" rows="3"></textarea></label>';
    echo '<label>Ukuran teks<input type="range" id="sanro-font-size" min="10" max="48" value="22"></label>';
    echo '<label>Warna teks<input type="color" id="sanro-text-color" value="#171717"></label>';
    echo '<button type="button" id="sanro-center-text">Posisikan teks ke tengah</button>';
    echo '<button type="button" id="sanro-remove-text">Hapus teks sisi ini</button>';
    echo '<label>Kategori cetak<select id="sanro-category"><option>A5</option><option>A4</option><option>A3</option><option>A3+</option></select></label>';
    echo '<p id="sanro-validation" role="status">Belum ada desain.</p>';
    if (!empty($config['draft_api'])) {
        echo '<button type="button" id="sanro-save-draft">Simpan draft teks</button>';
        echo '<p id="sanro-save-status" role="status">Penyimpanan gambar dan checkout belum tersedia.</p>';
    }
    echo '<button type="button" disabled>Simpan dan checkout — belum aktif</button></section></div></main>';
    get_footer();
    exit;
}
