<?php
defined('ABSPATH') || exit;

/**
 * Frontend entrypoint is opt-in and inert until SANRO Studio is explicitly
 * enabled on a staging site. No existing WooCommerce purchase flow is replaced.
 */
/**
 * Content-based cache busting for the buyer runtime. Dev assets may change
 * without a plugin version bump; a static ver=0.1.1-dev can leave browsers
 * running a stale Cart handler after an approved staging update.
 * Failure to hash falls back to the ordinary plugin version.
 */
function sanro_studio_buyer_script_version() {
    $path = __DIR__ . '/../assets/buyer.js';
    if (!is_file($path) || !is_readable($path)) return SANRO_STUDIO_VERSION;
    $hash = hash_file('sha256', $path);
    return is_string($hash) && preg_match('/^[a-f0-9]{64}$/D', $hash)
        ? SANRO_STUDIO_VERSION . '-' . substr($hash, 0, 12)
        : SANRO_STUDIO_VERSION;
}

/**
 * Cache-bust the buyer stylesheet as well as buyer.js: plugin staging builds
 * retain the same development version, while theme/CDN caches can keep an
 * older 35px mobile button stylesheet after a ZIP overwrite.
 */
function sanro_studio_buyer_style_version() {
    $path = __DIR__ . '/../assets/buyer.css';
    if (!is_file($path) || !is_readable($path)) return SANRO_STUDIO_VERSION;
    $hash = hash_file('sha256', $path);
    return is_string($hash) && preg_match('/^[a-f0-9]{64}$/D', $hash)
        ? SANRO_STUDIO_VERSION . '-' . substr($hash, 0, 12)
        : SANRO_STUDIO_VERSION;
}

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
    // Paginate the underlying Woo catalog, not the first 48 rows after Studio
    // eligibility filtering. This keeps each mobile response bounded while
    // allowing eligible products later in a large catalog to be reached.
    $page_raw=$_GET['studio_catalog_page']??1;$page=max(1,absint(is_scalar($page_raw)?$page_raw:1));
    $search_raw=$_GET['studio_search']??'';$search=is_string($search_raw)?sanitize_text_field(wp_unslash($search_raw)):'';
    $query=wc_get_products(['status'=>'publish','limit'=>48,'page'=>$page,'paginate'=>true,'return'=>'ids',
        'orderby'=>'title','order'=>'ASC','s'=>$search]);
    $ids=is_object($query)&&isset($query->products)&&is_array($query->products)?$query->products:(is_array($query)?$query:[]);
    $max_pages=is_object($query)?max(1,(int)($query->max_num_pages??1)):1;
    echo '<main id="sanro-studio-buyer" class="sanro-buyer sanro-product-picker" aria-label="SANRO Studio">';
    $preferences = get_option('sanro_studio_native_preferences', []);
    $label = is_array($preferences) && isset($preferences['studio_label'])
        && is_string($preferences['studio_label']) && trim($preferences['studio_label']) !== ''
        ? $preferences['studio_label'] : 'SANRO Studio';
    sanro_studio_ui_header($label);
    echo '<div class="sanro-catalog-heading"><h2>Mau cetak apa?</h2><p>Pilih produk, lalu unggah desainmu.</p></div>';
    echo '<nav class="sanro-workflow" aria-label="Tahapan desain"><span class="sanro-workflow-current" aria-current="step">1. Pilih Produk</span><span class="sanro-workflow-pending">2. Atur Desain</span><span class="sanro-workflow-pending">3. Desain Saya</span></nav>';
    echo '<form class="sanro-catalog-search" method="get"><label for="sanro-product-search">Cari produk</label> ';
    echo '<input id="sanro-product-search" name="studio_search" type="search" value="'.esc_attr($search).'" placeholder="Cari nama produk" autocomplete="off"><button type="submit">Cari</button></form>';
    echo '<div class="sanro-product-grid">';
    $shown = 0;
    foreach ($ids as $id) {
        $editor_config=sanro_studio_public_editor_config($id);
        if (empty($editor_config['valid'])) continue;
        $product = wc_get_product($id);
        if (!$product) continue;
        $name = $product->get_name();
        $url = add_query_arg(['sanro_studio_editor' => '1', 'product_id' => (int) $id], $page_url);
        echo '<a class="sanro-product-card" href="' . esc_url($url) . '" data-name="' . esc_attr(strtolower($name)) . '">';
        echo $product->get_image_id() ? $product->get_image('woocommerce_thumbnail', ['loading' => 'lazy']) : '<img loading="lazy" src="'.esc_url(plugin_dir_url(__DIR__.'/../sanro-studio.php').'assets/garment.svg').'" alt="'.esc_attr($name).'">';
        echo '<strong>' . esc_html($name) . '</strong>';
        $minimum=function_exists('sanro_studio_minimum_configured_price')?sanro_studio_minimum_configured_price($editor_config):null;
        echo '<span>'.($minimum===null?esc_html('Harga dihitung server sesuai desain'):esc_html('Mulai ').wp_kses_post(wc_price($minimum))).'</span></a>';
        $shown++;
    }
    echo '</div>';
    if (!$shown) echo '<p>Belum ada produk yang siap didesain. Administrator perlu mengatur area cetak dan profil harga produk.</p>';
    echo '<p id="sanro-product-empty" hidden>Tidak ada produk yang cocok.</p>';
    if($max_pages>1){echo '<nav class="sanro-catalog-pages" aria-label="Halaman produk">';for($p=1;$p<=$max_pages;$p++)echo $p===$page?'<strong aria-current="page">'.$p.'</strong> ':'<a href="'.esc_url(add_query_arg(['studio_catalog_page'=>$p])).'">'.$p.'</a> ';echo '</nav>';}
    echo '<div class="sanro-catalog-footer">'.sanro_studio_ui_icon('shirt').'Pilih produk, lalu unggah desainmu</div>';
    echo '</main>';

    echo '<script>document.addEventListener("DOMContentLoaded",function(){var input=document.getElementById("sanro-product-search"),cards=[].slice.call(document.querySelectorAll(".sanro-product-card")),empty=document.getElementById("sanro-product-empty");if(!input)return;input.addEventListener("input",function(){var q=input.value.toLocaleLowerCase().trim(),count=0;cards.forEach(function(card){var match=card.dataset.name.includes(q);card.hidden=!match;if(match)count++});if(empty)empty.hidden=count>0||cards.length===0})});</script>';
}

/**
 * The active theme owns the document <head>. Some staging themes omit
 * the mobile viewport meta tag, causing Chromium on a 320px phone to use
 * its legacy 980px layout viewport and bypass SANRO's responsive CSS.
 * Register only for the opted-in SANRO product picker/editor routes.
 */
function sanro_studio_print_buyer_viewport_meta() {
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
}
function sanro_studio_register_buyer_viewport_meta() {
    add_filter('body_class','sanro_studio_ui_theme_scope');
    if (!has_action('wp_head', 'sanro_studio_print_buyer_viewport_meta')) {
        add_action('wp_head', 'sanro_studio_print_buyer_viewport_meta', 1);
    }
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
        wp_enqueue_style('sanro-studio-buyer', $asset . 'buyer.css', [], sanro_studio_buyer_style_version());
        wp_enqueue_script('sanro-studio-ui', $asset . 'studio-ui.js', [], SANRO_STUDIO_VERSION . '-' . substr(hash_file('sha256',dirname(__DIR__).'/assets/studio-ui.js'),0,12), true);
        sanro_studio_register_buyer_viewport_meta();
        if (function_exists('nocache_headers')) nocache_headers();
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

    // Admin preferences affect presentation only; never alter product, pricing or checkout authorization.
    $preferences = get_option('sanro_studio_native_preferences', []);
    if (!is_array($preferences)) $preferences = [];
    $studio_label = isset($preferences['studio_label']) && is_string($preferences['studio_label'])
        && trim($preferences['studio_label']) !== '' ? $preferences['studio_label'] : 'SANRO Studio';
    $preferred_side = ($preferences['default_side'] ?? 'front') === 'back' ? 'back' : 'front';
    $config['default_side'] = isset($config['safe_areas'][$preferred_side])
        ? $preferred_side : (isset($config['safe_areas']['front']) ? 'front' : 'back');
    // Explicit product identity for the REST draft creation payload.
    $config['product_id'] = $product_id;
    // Render as a dedicated view: no WooCommerce add-to-cart form is included.
    $asset = plugin_dir_url(__DIR__ . '/../sanro-studio.php') . 'assets/';
    wp_enqueue_style('sanro-studio-buyer', $asset . 'buyer.css', [], sanro_studio_buyer_style_version());
    wp_enqueue_script('sanro-studio-estimate', $asset . 'estimate.js', [], SANRO_STUDIO_VERSION . '-' . substr(hash_file('sha256',dirname(__DIR__).'/assets/estimate.js'),0,12), true);
    wp_enqueue_script('sanro-studio-transport', $asset . 'transport.js', [], SANRO_STUDIO_VERSION, true);
    wp_enqueue_script('sanro-studio-cart-redirect', $asset . 'cart-redirect.js', [], SANRO_STUDIO_VERSION, true);
    wp_enqueue_script('sanro-studio-buyer', $asset . 'buyer.js', ['sanro-studio-estimate', 'sanro-studio-cart-redirect', 'sanro-studio-transport'], sanro_studio_buyer_script_version(), true);
    wp_enqueue_script('sanro-studio-collection', $asset . 'collection.js', ['sanro-studio-buyer'], SANRO_STUDIO_VERSION . '-' . substr(hash_file('sha256', dirname(__DIR__).'/assets/collection.js'),0,12), true);
    wp_enqueue_script('sanro-studio-ui', $asset . 'studio-ui.js', ['sanro-studio-buyer','sanro-studio-collection'], SANRO_STUDIO_VERSION . '-' . substr(hash_file('sha256',dirname(__DIR__).'/assets/studio-ui.js'),0,12), true);
    // Supply API bootstrap only on explicitly enabled staging; no owner token
    // is ever included in page HTML or a shared JavaScript configuration.
    if (function_exists('sanro_studio_draft_rest_enabled') && sanro_studio_draft_rest_enabled()) {
        // Do not serve stale per-page nonce bootstrap through page caches.
        if (function_exists('nocache_headers')) nocache_headers();
        $config['draft_api'] = [
            'url' => esc_url_raw(rest_url('sanro-studio/v1/drafts')),
            'nonce' => wp_create_nonce('sanro_studio_draft'),
            // Preserve signed-in identity through WordPress REST cookie authentication.
            'rest_nonce' => wp_create_nonce('wp_rest'),
        ];
    }
    if (!empty($config['draft_api']) && defined('SANRO_STUDIO_ENABLE_MASTER_UPLOAD')
        && SANRO_STUDIO_ENABLE_MASTER_UPLOAD === true
        && defined('SANRO_STUDIO_ENABLE_PRIVATE_MASTERS')
        && SANRO_STUDIO_ENABLE_PRIVATE_MASTERS === true) {
        $config['master_upload_enabled'] = true;
    }
    if (function_exists('sanro_studio_preview_upload_enabled') && sanro_studio_preview_upload_enabled()) {
        $config['preview_upload_enabled'] = true;
    }
    wp_add_inline_script('sanro-studio-buyer', 'window.SANRO_STUDIO_EDITOR_CONFIG=' . wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';', 'before');

    // WordPress may have a canonical redirect for product query arguments.
    // This view is selected only after product identity and visibility checks.
    sanro_studio_register_buyer_viewport_meta();
    if (function_exists('nocache_headers')) nocache_headers();
    get_header();
    echo '<main id="sanro-studio-buyer" class="sanro-buyer sanro-buyer-editor" aria-label="SANRO Studio">';
    $back_id=(int)get_option('sanro_studio_editor_page_id',0);
    sanro_studio_ui_header($studio_label,$back_id?get_permalink($back_id):'');
    $display_product=wc_get_product($product_id);
    echo '<h2 class="sanro-editor-product">'.esc_html($display_product?$display_product->get_name():'Atur Desain').'</h2>';
    echo '<nav class="sanro-workflow" aria-label="Tahapan desain"><span class="sanro-workflow-done">1. Pilih Produk</span><span class="sanro-workflow-current" aria-current="step">2. Atur Desain</span><span class="sanro-workflow-pending">3. Desain Saya</span></nav>';
    echo '<nav class="sanro-mobile-jumps" aria-label="Navigasi editor"><a href="#sanro-design-preview">Lihat kaos</a><a href="#sanro-design-controls">Atur desain</a></nav>';
    echo '<div class="sanro-layout"><section class="sanro-preview" id="sanro-design-preview" aria-label="Pratinjau desain">';
    echo '<nav aria-label="Sisi kaos"><button type="button" data-side="front" aria-pressed="' . ($config['default_side'] === 'front' ? 'true' : 'false') . '">Depan</button> <button type="button" data-side="back" aria-pressed="' . ($config['default_side'] === 'back' ? 'true' : 'false') . '">Belakang</button></nav>';
    echo '<div class="sanro-shirt"><div class="sanro-safe"><img alt="Gambar desain" tabindex="0" hidden><div class="sanro-text" tabindex="0" hidden></div></div></div>';
    echo '<p id="sanro-side-summary" role="status">Depan: kosong · Belakang: kosong</p>';
    echo '<p class="sanro-hint">Geser gambar atau teks. Ukuran cetak dihitung dalam cm.</p></section>';
    echo '<section class="sanro-controls" id="sanro-design-controls"><h2>Atur desain</h2>';
    $palette = ($config['palette'] ?? []) ?: ['#ffffff'=>'Putih','#171717'=>'Hitam','#b91c1c'=>'Merah','#1d4ed8'=>'Biru','#166534'=>'Hijau'];
    $initial_color = array_key_first($palette);
    echo '<fieldset class="sanro-color-picker"><legend>Warna kaos</legend>';
    echo '<input type="hidden" id="sanro-shirt-color" value="'.esc_attr($initial_color).'">';
    echo '<div class="sanro-color-swatches">';
    foreach ($palette as $color=>$label) {
        echo '<label class="sanro-color-option" title="'.esc_attr($label).'"><input type="radio" name="sanro-shirt-swatch" value="'.esc_attr($color).'" aria-label="'.esc_attr($label).'"'.($color===$initial_color?' checked':'').'><span class="sanro-color-chip" style="--swatch:'.esc_attr($color).'" aria-hidden="true"></span></label>';
    }
    echo '</div><p id="sanro-color-name">' . esc_html($palette[$initial_color]) . '</p></fieldset>';
    echo '<fieldset class="sanro-size-qty" aria-describedby="sanro-qty-status"><legend>Jumlah per ukuran</legend>';
    foreach (($config['pricing']['sizes']??['S','M','L','XL','2XL','3XL','4XL','5XL']) as $size) {
        echo '<label>' . esc_html($size) . '<input type="number" data-size="' . esc_attr($size) . '" min="0" max="999" step="1" value="0" inputmode="numeric"><span class="sanro-unavailable-label">Habis</span></label>';
    }
    echo '</fieldset><p id="sanro-qty-total" role="status">Total: 0 pcs</p><p id="sanro-qty-status" role="status"></p>';
    echo '<p id="sanro-price-estimate" role="status">Estimasi harga: pilih desain dan jumlah. Harga final dihitung saat checkout.</p>';
    echo '<label class="sanro-image-upload"><span>Unggah gambar</span><input type="file" id="sanro-image" accept="image/png,image/jpeg" aria-label="Unggah Gambar PNG atau JPEG maks. 10 MB"></label>';
    echo '<input type="range" id="sanro-image-size" min="10" max="100" value="60" hidden aria-hidden="true" tabindex="-1">';
    echo '<input type="range" id="sanro-image-rotation" min="-180" max="180" value="0" hidden aria-hidden="true" tabindex="-1">';
    echo '<button type="button" id="sanro-center-image">Posisikan gambar ke tengah</button>';
    echo '<button type="button" id="sanro-remove-image">Hapus gambar</button>';
    echo '<label>Teks<textarea id="sanro-text-input" maxlength="300" rows="3"></textarea></label>';
    echo '<input type="range" id="sanro-font-size" min="10" max="48" value="22" hidden aria-hidden="true" tabindex="-1">';
    echo '<input type="range" id="sanro-text-rotation" min="-180" max="180" value="0" hidden aria-hidden="true" tabindex="-1">';
    echo '<label>Warna teks<input type="color" id="sanro-text-color" value="#171717"></label>';
    echo '<button type="button" id="sanro-center-text">Posisikan teks ke tengah</button>';
    echo '<button type="button" id="sanro-remove-text">Hapus teks sisi ini</button>';
    echo '<button type="button" id="sanro-copy-text">Salin teks ke sisi lainnya</button>';
    echo '<p id="sanro-copy-status" role="status" aria-live="polite"></p>';
    echo '<button type="button" id="sanro-reset-side">Kosongkan desain sisi ini</button>';
    echo '<p id="sanro-validation" role="status">Belum ada desain.</p>';
    if (!empty($config['draft_api'])) {
        echo '<button type="button" id="sanro-save-draft">Simpan Desain</button>';
        echo '<div class="sanro-session-design"><img id="sanro-session-preview" alt="Pratinjau desain tersimpan" hidden>';
        echo '<div><span>' . esc_html($config['label'] ?? 'Desain kaos') . '</span><p id="sanro-session-design-id"></p>';
        echo '<button type="button" id="sanro-reopen-draft">Lanjutkan desain sesi ini</button></div></div>';
        echo '<p id="sanro-reopen-status" role="status" aria-live="polite"></p>';
        if (!empty($config['master_upload_enabled'])) {
            echo '<button type="button" id="sanro-remove-background" disabled>Hapus Background</button><label id="sanro-background-tolerance-wrap" hidden>Toleransi latar<input id="sanro-background-tolerance" type="range" min="0" max="96" value="24"></label><button type="button" id="sanro-apply-background" hidden>Terapkan Remove Background</button><button type="button" id="sanro-restore-background">Pulihkan gambar asli</button><p id="sanro-background-status" role="status"></p>';
        }
        if (!empty($config['preview_upload_enabled'])) {
            echo '<button type="button" id="sanro-upload-previews">Perbarui pratinjau desain</button>';
            echo '<p id="sanro-preview-upload-status" role="status" aria-live="polite"></p>';
        }
        if(function_exists('sanro_studio_repeat_register_routes')){
            echo '<fieldset id="sanro-repeat"><legend>Cek diskon repeat 5%</legend><p>Pesan dengan email yang sama seperti pesanan yang sudah dibayar. Kode ini memverifikasi emailmu.</p><label>Email<input id="sanro-owner-email" type="email" autocomplete="email" maxlength="254"></label>';
            echo '<button type="button" id="sanro-request-owner-email">Kirim kode verifikasi</button><label>Kode dari email<input id="sanro-owner-email-code" type="text" autocomplete="one-time-code" maxlength="64" spellcheck="false"></label>';
            echo '<button type="button" id="sanro-confirm-owner-email">Verifikasi kode</button><p id="sanro-owner-email-status" role="status"></p></fieldset>';
        }
        echo '<fieldset id="sanro-collection"><legend>Desain Saya</legend>';
        echo '<div class="sanro-collection-save"><label>Nama desain<input data-title maxlength="120" placeholder="Contoh: Kaos komunitas"></label><button type="button" class="sanro-collection-primary" data-save>Simpan desain ini ke koleksi</button></div>';
        echo '<details class="sanro-collection-access"><summary>Akses koleksi dengan email</summary><p>Kirim kode sekali pakai ke email yang terverifikasi pada desainmu.</p><label>Email koleksi<input data-email type="email" autocomplete="email" maxlength="254"></label><button type="button" data-request>Kirim kode akses</button><label>Kode akses<input data-code autocomplete="one-time-code" maxlength="32" spellcheck="false"></label><button type="button" data-verify>Verifikasi dan buka koleksi</button></details>';
        echo '<div class="sanro-collection-session-actions"><button type="button" class="sanro-collection-primary" data-open>Buka koleksi sesi ini</button><button type="button" data-logout>Keluar koleksi</button></div>';
        echo '<p data-status role="status" aria-live="polite">Koleksi ini privat. Verifikasi email untuk menyimpan desain atau mengaksesnya dari browser lain.</p><div data-list></div><button type="button" data-more hidden>Halaman berikutnya</button></fieldset>';
        echo '<button type="button" id="sanro-server-quote">Lihat harga desain tersimpan</button>';
        echo '<p id="sanro-server-quote-status" role="status" aria-live="polite"></p>';
        echo '<p id="sanro-save-status" role="status" aria-live="polite">Belum disimpan</p>';
    }
    if (!empty($config['draft_api'])
        && function_exists('sanro_studio_pricing_wc_enabled')
        && sanro_studio_pricing_wc_enabled()) {
        echo '<button type="button" id="sanro-add-to-cart">Tambahkan ke Keranjang</button>';
        echo '<p id="sanro-cart-status" role="status"></p>';
    } elseif (!empty($config['draft_api'])
        && defined('SANRO_STUDIO_ENABLE_STAGING_CART')
        && SANRO_STUDIO_ENABLE_STAGING_CART === true
        && defined('SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT')
        && SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT === true
        && defined('SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK')
        && SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK === true) {
        echo '<button type="button" id="sanro-add-to-cart">Tambahkan ke Keranjang</button>';
        echo '<p id="sanro-cart-status" role="status"></p>';
    } else {
        echo '<button type="button" disabled>Simpan dan checkout — belum aktif</button>';
    }
    echo '</section></div></main>';
    get_footer();
    exit;
}
