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
    $editor_page_id = (int) get_option('sanro_studio_editor_page_id', 0);
    $editor_page_url = $editor_page_id && get_post_type($editor_page_id) === 'page' && get_post_status($editor_page_id) === 'publish' ? get_permalink($editor_page_id) : false;
    $url = add_query_arg(['sanro_studio_editor' => '1', 'product_id' => $id], $editor_page_url ?: get_permalink($id));
    echo '<p class="sanro-studio-entry"><a class="button alt" href="' . esc_url($url) . '">Custom Desain (SANRO Studio)</a></p>';
}

/**
 * Standalone entrypoint: only published, purchasable products with valid
 * SANRO Studio print areas and pricing profiles appear in the picker.
 * Read-only, bounded listing; no checkout or private product data.
 */
function sanro_studio_render_product_picker($page_url) {
    // Use the shared buyer stylesheet for responsive layout; enqueue before get_header().
    $ids = wc_get_products(['status' => 'publish', 'limit' => 48, 'return' => 'ids', 'orderby' => 'title', 'order' => 'ASC']);
    echo '<main id="sanro-studio-buyer" class="sanro-buyer sanro-product-picker" aria-label="SANRO Studio">';
    echo '<header><h1>SANRO Studio</h1><p>Pilih produk untuk mulai mendesain.</p></header>';
    echo '<label for="sanro-product-search">Cari produk</label> ';
    echo '<input id="sanro-product-search" type="search" placeholder="Cari nama produk" autocomplete="off">';
    echo '<div class="sanro-product-grid">';
    $shown = 0;
    foreach ($ids as $id) {
        if (empty(sanro_studio_public_editor_config($id)['valid'])) continue;
        $product = wc_get_product($id);
        if (!$product) continue;
        $name = $product->get_name();
        $url = add_query_arg(['sanro_studio_editor' => '1', 'product_id' => (int) $id], $page_url);
        echo '<a class="sanro-product-card" href="' . esc_url($url) . '" data-name="' . esc_attr(strtolower($name)) . '">';
        echo $product->get_image('woocommerce_thumbnail', ['loading' => 'lazy']);
        echo '<strong>' . esc_html($name) . '</strong>';
        echo '<span>' . wp_kses_post(wc_price(wc_get_price_to_display($product))) . '</span></a>';
        $shown++;
    }
    echo '</div>';
    if (!$shown) echo '<p>Belum ada produk yang siap didesain. Administrator perlu mengatur area cetak dan profil harga produk.</p>';
    echo '<p id="sanro-product-empty" hidden>Tidak ada produk yang cocok.</p>';
    echo '</main>';

    echo '<script>document.addEventListener("DOMContentLoaded",function(){var input=document.getElementById("sanro-product-search"),cards=[].slice.call(document.querySelectorAll(".sanro-product-card")),empty=document.getElementById("sanro-product-empty");if(!input)return;input.addEventListener("input",function(){var q=input.value.toLocaleLowerCase().trim(),count=0;cards.forEach(function(card){var match=card.dataset.name.includes(q);card.hidden=!match;if(match)count++});if(empty)empty.hidden=count>0||cards.length===0})});</script>';
}

/** Dedicated buyer editor view, not wp-admin or Edit Post. */
function sanro_studio_render_buyer_editor() {
    if (!sanro_studio_frontend_enabled()) return;
    $editor_page_id = (int) get_option('sanro_studio_editor_page_id', 0);
    $on_selected_page = $editor_page_id > 0 && get_post_type($editor_page_id) === 'page'
        && get_post_status($editor_page_id) === 'publish' && function_exists('is_page') && is_page($editor_page_id);
    $on_product = function_exists('is_product') && is_product();
    if (!$on_selected_page && !$on_product) return;
    // The configured page is an entrypoint even without query parameters.
    // Never hijack unrelated pages or product URLs.
    if ($on_selected_page && !isset($_GET['product_id']) && !isset($_GET['sanro_studio_editor'])) {
        $asset = plugin_dir_url(__DIR__ . '/../sanro-studio.php') . 'assets/';
        wp_enqueue_style('sanro-studio-buyer', $asset . 'buyer.css', [], SANRO_STUDIO_VERSION);
        get_header();
        sanro_studio_render_product_picker(get_permalink($editor_page_id));
        get_footer();
        exit;
    }
    if (!isset($_GET['sanro_studio_editor']) || !is_string($_GET['sanro_studio_editor']) || $_GET['sanro_studio_editor'] !== '1') return;
    if (!isset($_GET['product_id']) || !is_scalar($_GET['product_id'])) return;
    $product_id = absint($_GET['product_id']);
    if (!$product_id || (string) $product_id !== (string) $_GET['product_id']) return;
    if ($on_product && $product_id !== absint(get_queried_object_id())) return;
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
    if (function_exists('sanro_studio_preview_upload_enabled') && sanro_studio_preview_upload_enabled()) {
        $config['preview_upload_enabled'] = true;
    }
    wp_add_inline_script('sanro-studio-buyer', 'window.SANRO_STUDIO_EDITOR_CONFIG=' . wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';', 'before');

    // WordPress may have a canonical redirect for product query arguments.
    // This view is selected only after product identity and visibility checks.
    get_header();
    echo '<main id="sanro-studio-buyer" class="sanro-buyer" aria-label="SANRO Studio">';
    echo '<header><h1>SANRO Studio</h1><p>Editor kaos — pratinjau saja, belum dapat checkout</p>';
    // Keep the standalone catalog reachable without losing the selected editor page.
    if ($on_selected_page) {
        echo '<p><a class="sanro-back-to-products" href="' . esc_url(get_permalink($editor_page_id)) . '">← Kembali pilih produk</a></p>';
    } elseif ($on_product) {
        $catalog_page_id = (int) get_option('sanro_studio_editor_page_id', 0);
        if ($catalog_page_id > 0 && get_post_type($catalog_page_id) === 'page'
            && get_post_status($catalog_page_id) === 'publish') {
            echo '<p><a class="sanro-back-to-products" href="' . esc_url(get_permalink($catalog_page_id)) . '">← Kembali pilih produk</a></p>';
        }
    }
    echo '</header>';
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
    echo '<label>Model lengan<select id="sanro-sleeve"><option value="short">Lengan pendek</option><option value="long">Lengan panjang (+Rp10.000/pcs)</option></select></label>';
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
    echo '<button type="button" id="sanro-copy-text">Salin teks ke sisi lainnya</button>';
    echo '<p id="sanro-copy-status" role="status" aria-live="polite"></p>';
    echo '<label>Kategori cetak<select id="sanro-category"><option>A5</option><option>A4</option><option>A3</option><option>A3+</option></select></label>';
    echo '<button type="button" id="sanro-reset-side">Kosongkan desain sisi ini</button>';
    echo '<p id="sanro-validation" role="status">Belum ada desain.</p>';
    if (!empty($config['draft_api'])) {
        echo '<button type="button" id="sanro-save-draft">Simpan draft teks</button>';
        if (!empty($config['preview_upload_enabled'])) {
            echo '<button type="button" id="sanro-upload-previews">Simpan preview depan/belakang (staging)</button>';
            echo '<p id="sanro-preview-upload-status" role="status" aria-live="polite"></p>';
        }
        echo '<button type="button" id="sanro-server-quote">Cek harga resmi draft tersimpan</button>';
        echo '<p id="sanro-server-quote-status" role="status" aria-live="polite">Simpan draft teks sebelum meminta harga server.</p>';
        echo '<p id="sanro-save-status" role="status">Penyimpanan gambar dan checkout belum tersedia.</p>';
    }
    echo '<button type="button" disabled>Simpan dan checkout — belum aktif</button></section></div></main>';
    get_footer();
    exit;
}
