<?php
defined('ABSPATH') || exit;

/**
 * Read-only order preview reference inventory. This never accepts buyer
 * input or creates order metadata, public links, or master file URLs.
 */
function sanro_studio_order_preview_inventory($order) {
    if (!is_object($order) || !method_exists($order, 'get_meta')) {
        return ['valid' => false, 'error' => 'invalid_order'];
    }
    $result = ['valid' => true, 'front' => null, 'back' => null];
    foreach (['front', 'back'] as $side) {
        $ref = $order->get_meta('_sanro_studio_preview_' . $side . '_ref', true);
        if (is_string($ref) && preg_match(
            '/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}-' .
            $side . '-[a-f0-9]{64}\.(png|jpg|webp)$/iD', $ref
        )) {
            $result[$side] = $ref;
        }
    }
    return $result;
}

/** Display metadata availability only, never an unprotected file link. */
function sanro_studio_admin_order_preview_status($order) {
    if (!current_user_can('manage_woocommerce')) return;
    $inventory = sanro_studio_order_preview_inventory($order);
    if (empty($inventory['valid'])) return;
    echo '<div class="sanro-studio-order-preview"><h3>' . esc_html('SANRO Studio — Preview desain') . '</h3>';
    if($inventory['front']===null&&$inventory['back']===null&&method_exists($order,'get_items')){
        $items=$order->get_items('line_item');$count=0;
        foreach(is_array($items)?$items:[] as $item_id=>$item){$native=sanro_studio_order_item_preview_inventory($order,$item_id);if(!empty($native['valid'])&&!empty($native['front'])&&!empty($native['back']))$count++;}
        if($count){echo '<p>'.esc_html('Referensi preview privat depan/belakang tercatat pada '.$count.' item. Gunakan tautan preview pada setiap item pesanan di bawah.').'</p></div>';return;}
    }
    foreach (['front' => 'Depan', 'back' => 'Belakang'] as $side => $label) {
        $state = $inventory[$side] === null
            ? 'Belum tersedia'
            : 'Referensi privat tercatat; gunakan tautan admin untuk melihat preview';
        echo '<p>' . esc_html($label . ': ' . $state) . '</p>';
        if ($inventory[$side] !== null && method_exists($order, 'get_id')) {
            $order_id = absint($order->get_id());
            if ($order_id && function_exists('wp_nonce_url') && function_exists('admin_url')) {
                $url = add_query_arg([
                    'action' => 'sanro_studio_order_preview',
                    'order_id' => $order_id,
                    'side' => $side,
                ], admin_url('admin-post.php'));
                $url = wp_nonce_url($url, 'sanro_order_preview_' . $order_id . '_' . $side);
                echo '<a target="_blank" rel="noopener noreferrer" href="' . esc_url($url) . '">' .
                    esc_html('Lihat preview ' . $label) . '</a>';
            }
        }
    }
    echo '</div>';
}

/**
 * Internal-only order reference attachment. Caller must already possess an
 * authorized order object; never register this as a guest REST endpoint.
 * Does not store owner tokens, raw image bytes, or master file locations.
 */
function sanro_studio_attach_order_preview_refs($order, $design_id, $owner_token, $refs) {
    if (!current_user_can('manage_woocommerce')) {
        return ['valid' => false, 'error' => 'forbidden'];
    }
    if (!is_object($order) || !method_exists($order, 'update_meta_data')
        || !method_exists($order, 'save') || !method_exists($order, 'get_items')
        || !is_array($refs) || !$refs || count($refs) > 2
        || !function_exists('sanro_studio_read_draft')) {
        return ['valid' => false, 'error' => 'invalid_order_preview'];
    }
    $draft = sanro_studio_read_draft($design_id, $owner_token);
    if (empty($draft['valid'])) return $draft;
    if (($draft['status'] ?? null) !== 'draft') {
        return ['valid' => false, 'error' => 'draft_locked'];
    }
    $product_match = false;
    foreach ($order->get_items('line_item') as $item) {
        if (is_object($item) && method_exists($item, 'get_product_id')
            && (int) $item->get_product_id() === (int) $draft['product_id']) {
            $product_match = true;
            break;
        }
    }
    if (!$product_match) return ['valid' => false, 'error' => 'product_mismatch'];
    foreach ($refs as $side => $ref) {
        if (!in_array($side, ['front', 'back'], true)
            || !is_string($ref) || !is_string($design_id)
            || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $design_id)
            || !preg_match('/^' . preg_quote(strtolower($design_id), '/') . '-' . $side .
                '-[a-f0-9]{64}\.(png|jpg|webp)$/D', $ref)) {
            return ['valid' => false, 'error' => 'invalid_preview_reference'];
        }
    }
    // Confirm each private file is readable and checksum-valid before linking.
    // This is intentionally fail-closed when private storage is not configured.
    if (!function_exists('sanro_studio_read_owned_draft_preview')) {
        return ['valid' => false, 'error' => 'preview_store_unavailable'];
    }
    foreach ($refs as $side => $ref) {
        $preview = sanro_studio_read_owned_draft_preview($design_id, $owner_token, $side, $ref);
        if (empty($preview['valid'])) {
            return ['valid' => false, 'error' => $preview['error'] ?? 'preview_not_found'];
        }
    }
    foreach ($refs as $side => $ref) {
        $order->update_meta_data('_sanro_studio_preview_' . $side . '_ref', $ref);
    }
    $order->save();
    return ['valid' => true, 'attached_sides' => array_keys($refs)];
}

/**
 * Resolve order-linked preview for an authorized WooCommerce administrator.
 * No owner token is needed after a reference has been attached to the order.
 * No public URL or filesystem path is returned.
 */
function sanro_studio_admin_order_preview_bytes($order, $side, $item_id = null) {
    if (!function_exists('current_user_can') || !current_user_can('manage_woocommerce')) {
        return ['valid' => false, 'error' => 'forbidden'];
    }
    return sanro_studio_bound_order_preview_bytes($order, $side, $item_id);
}

/** Internal reader shared by authorized admin delivery and paid/ready workers. */
function sanro_studio_bound_order_preview_bytes($order, $side, $item_id = null) {
    if (!in_array($side, ['front', 'back'], true)) {
        return ['valid' => false, 'error' => 'invalid_side'];
    }
    $inventory = $item_id === null
        ? sanro_studio_order_preview_inventory($order)
        : sanro_studio_order_item_preview_inventory($order, $item_id);
    if (empty($inventory['valid']) || empty($inventory[$side])) {
        return ['valid' => false, 'error' => 'preview_not_found'];
    }
    $ref = $inventory[$side];
    if (!defined('SANRO_STUDIO_ENABLE_PRIVATE_PREVIEWS')
        || SANRO_STUDIO_ENABLE_PRIVATE_PREVIEWS !== true
        || !defined('SANRO_STUDIO_PRIVATE_PREVIEW_DIR')
        || !is_string(SANRO_STUDIO_PRIVATE_PREVIEW_DIR)) {
        return ['valid' => false, 'error' => 'preview_storage_disabled'];
    }
    $directory = realpath(SANRO_STUDIO_PRIVATE_PREVIEW_DIR);
    $root = defined('ABSPATH') ? realpath(ABSPATH) : false;
    $webroot = isset($_SERVER['DOCUMENT_ROOT']) && is_string($_SERVER['DOCUMENT_ROOT'])
        ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    if (!$directory || !is_dir($directory) || is_link(SANRO_STUDIO_PRIVATE_PREVIEW_DIR)
        || !$root || !$webroot || $directory === $root || $directory === $webroot
        || strpos($directory, $root . DIRECTORY_SEPARATOR) === 0
        || strpos($directory, $webroot . DIRECTORY_SEPARATOR) === 0) {
        return ['valid' => false, 'error' => 'unsafe_preview_directory'];
    }
    $path = $directory . DIRECTORY_SEPARATOR . $ref;
    if (is_link($path) || !is_file($path) || !is_readable($path)
        || filesize($path) > 51200) {
        return ['valid' => false, 'error' => 'preview_not_found'];
    }
    $bytes = @file_get_contents($path);
    $checked = sanro_studio_validate_preview_bytes($bytes);
    $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    $expected = substr($ref, 0, 36) . '-' . $side . '-' . ($checked['sha256'] ?? '') .
        '.' . ($extensions[$checked['mime'] ?? ''] ?? '');
    if (empty($checked['valid']) || !hash_equals($expected, $ref)) {
        return ['valid' => false, 'error' => 'preview_integrity_failed'];
    }
    return ['valid' => true, 'bytes' => $bytes, 'mime' => $checked['mime']];
}

/** Explicitly admin-only delivery, protected by order-scoped nonce. */
function sanro_studio_admin_deliver_order_preview() {
    if (!current_user_can('manage_woocommerce')) wp_die('Forbidden', '', ['response' => 403]);
    $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
    $side = isset($_GET['side']) && is_string($_GET['side']) ? $_GET['side'] : '';
    $nonce = isset($_GET['_wpnonce']) && is_string($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';
    $item_id = isset($_GET['item_id']) ? absint($_GET['item_id']) : null;
    $nonce_action = 'sanro_order_preview_' . $order_id . '_' . $side
        . ($item_id === null ? '' : '_item_' . $item_id);
    if (!$order_id || !in_array($side, ['front', 'back'], true)
        || ($item_id !== null && $item_id < 1)
        || !wp_verify_nonce($nonce, $nonce_action)) {
        wp_die('Forbidden', '', ['response' => 403]);
    }
    $order = function_exists('wc_get_order') ? wc_get_order($order_id) : false;
    $result = sanro_studio_admin_order_preview_bytes($order, $side, $item_id);
    if (empty($result['valid'])) wp_die('Preview unavailable', '', ['response' => 404]);
    if (function_exists('nocache_headers')) nocache_headers();
    header('Content-Type: ' . $result['mime']);
    header('Content-Disposition: inline; filename="sanro-preview-' . $side . '.img"');
    header('X-Content-Type-Options: nosniff');
    header('Content-Security-Policy: sandbox');
    echo $result['bytes'];
    exit;
}

/**
 * Order-item-scoped inventory. Keeps designs for separate line items isolated.
 * Legacy order-level metadata is intentionally not used as a fallback.
 */
function sanro_studio_order_item_preview_inventory($order, $item_id) {
    if (!is_object($order) || !method_exists($order, 'get_items')
        || !is_int($item_id) || $item_id < 1) {
        return ['valid' => false, 'error' => 'invalid_order_item'];
    }
    $items = $order->get_items('line_item');
    if (!is_array($items) || !isset($items[$item_id])
        || !is_object($items[$item_id]) || !method_exists($items[$item_id], 'get_meta')) {
        return ['valid' => false, 'error' => 'invalid_order_item'];
    }
    $item = $items[$item_id];
    $inventory = sanro_studio_order_preview_inventory($item);
    $design_id = $item->get_meta('_sanro_studio_design_id', true);
    // An item preview must always have an explicit design binding; otherwise
    // an orphaned reference could be displayed against the wrong line item.
    if ((!is_string($design_id) || $design_id === '')
        && (!empty($inventory['front']) || !empty($inventory['back']))) {
        return ['valid' => false, 'error' => 'design_not_bound'];
    }
    // A bound line item must never serve preview references for another design.
    if ($design_id !== '' && (!is_string($design_id)
        || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $design_id))) {
        return ['valid' => false, 'error' => 'invalid_design_binding'];
    }
    foreach (['front', 'back'] as $side) {
        if ($design_id !== '' && !empty($inventory[$side])
            && strpos($inventory[$side], strtolower($design_id) . '-' . $side . '-') !== 0) {
            return ['valid' => false, 'error' => 'preview_design_mismatch'];
        }
    }
    return $inventory;
}

/**
 * Internal-only attachment for one exact order line. No guest route.
 * The owner token is used transiently for validation, never saved to order metadata.
 */
function sanro_studio_attach_item_preview_refs($order, $item_id, $design_id, $owner_token, $refs) {
    if (!function_exists('current_user_can') || !current_user_can('manage_woocommerce')) {
        return ['valid' => false, 'error' => 'forbidden'];
    }
    if(function_exists('sanro_studio_saved_order_context')&&empty(sanro_studio_saved_order_context($order,true)['valid']))return ['valid'=>false,'error'=>'order_integrity_failed'];
    $inventory = sanro_studio_order_item_preview_inventory($order, $item_id);
    if (empty($inventory['valid']) || !is_array($refs) || !$refs || count($refs) > 2
        || !function_exists('sanro_studio_read_draft')
        || !function_exists('sanro_studio_read_owned_draft_preview')) {
        return ['valid' => false, 'error' => 'invalid_order_item'];
    }
    $items = $order->get_items('line_item');
    $item = $items[$item_id];
    if (!method_exists($item, 'get_product_id') || !method_exists($item, 'update_meta_data')
        || !method_exists($item, 'save')) {
        return ['valid' => false, 'error' => 'invalid_order_item'];
    }
    $draft = sanro_studio_read_draft($design_id, $owner_token);
    if (empty($draft['valid'])) return $draft;
    if (($draft['status'] ?? null) !== 'draft') {
        return ['valid' => false, 'error' => 'draft_locked'];
    }
    if ((int) $draft['product_id'] !== (int) $item->get_product_id()) {
        return ['valid' => false, 'error' => 'product_mismatch'];
    }
    foreach ($refs as $side => $ref) {
        if (!in_array($side, ['front', 'back'], true)
            || !is_string($design_id) || !is_string($ref)
            || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $design_id)
            || !preg_match('/^' . preg_quote(strtolower($design_id), '/') . '-' . $side .
                '-[a-f0-9]{64}\.(png|jpg|webp)$/D', $ref)) {
            return ['valid' => false, 'error' => 'invalid_preview_reference'];
        }
        $preview = sanro_studio_read_owned_draft_preview($design_id, $owner_token, $side, $ref);
        if (empty($preview['valid'])) {
            return ['valid' => false, 'error' => $preview['error'] ?? 'preview_not_found'];
        }
    }
    // Bind the line item to one design. Identical reattachments are allowed;
    // conflicting references and cross-design attachments fail closed.
    $bound_design = $item->get_meta('_sanro_studio_design_id', true);
    if ($bound_design !== '' && (!is_string($bound_design)
        || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $bound_design))) {
        return ['valid' => false, 'error' => 'invalid_design_binding'];
    }
    if ($bound_design !== '' && $bound_design !== $design_id) {
        return ['valid' => false, 'error' => 'design_conflict'];
    }
    foreach ($refs as $side => $ref) {
        $existing = $item->get_meta('_sanro_studio_preview_' . $side . '_ref', true);
        if ($existing !== '' && $existing !== $ref) {
            return ['valid' => false, 'error' => 'preview_conflict'];
        }
    }
    $item->update_meta_data('_sanro_studio_design_id', $design_id);
    foreach ($refs as $side => $ref) {
        $item->update_meta_data('_sanro_studio_preview_' . $side . '_ref', $ref);
    }
    $saved = $item->save();
    if ($saved === false) {
        return ['valid' => false, 'error' => 'preview_save_failed'];
    }
    return ['valid' => true, 'item_id' => $item_id, 'attached_sides' => array_keys($refs)];
}

/** Render independent preview links for each WooCommerce line item. */
function sanro_studio_admin_item_preview_status($item_id, $item, $product) {
    if (!current_user_can('manage_woocommerce') || !is_object($item)
        || !method_exists($item, 'get_order_id')) return;
    $order_id = absint($item->get_order_id());
    $item_id = absint($item_id);
    if (!$order_id || !$item_id) return;
    // Use the same strict binding validation as the private delivery endpoint.
    $order = function_exists('wc_get_order') ? wc_get_order($order_id) : false;
    if(function_exists('sanro_studio_saved_order_context')&&empty(sanro_studio_saved_order_context($order,true)['valid'])){
        echo '<p>'.esc_html('SANRO Studio: integritas pesanan perlu dipulihkan sebelum preview/metadata digunakan untuk produksi.').'</p>';return;
    }
    $inventory = sanro_studio_order_item_preview_inventory($order, $item_id);
    if (empty($inventory['valid'])) return;
    $design_id = method_exists($item, 'get_meta') ? $item->get_meta('_sanro_studio_design_id', true) : '';
    if ($design_id !== '') {
        if (!is_string($design_id) || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $design_id)) return;
        foreach (['front', 'back'] as $side) {
            if (!empty($inventory[$side]) && strpos($inventory[$side], strtolower($design_id) . '-' . $side . '-') !== 0) return;
        }
    }
    echo '<div class="sanro-studio-item-preview"><strong>' .
        esc_html('SANRO Studio — Preview item') . '</strong>';
    $snapshot = $item->get_meta('_sanro_studio_design_snapshot', true);
    if (is_array($snapshot) && ($snapshot['version'] ?? null) === 1
        && ($snapshot['design_id'] ?? null) === $design_id) {
        $labels = ['Kaos: ' . (is_string($snapshot['shirt_color'] ?? null) ? $snapshot['shirt_color'] : ''),
            'Lengan: ' . (($snapshot['sleeve_label']??null)==='3/4'?'3/4':(($snapshot['sleeve_label']??(!empty($snapshot['long_sleeve'])?'long':'short'))==='long'?'panjang':'pendek'))];
        foreach (['front'=>'Depan','back'=>'Belakang'] as $side=>$label) {
            $category = $snapshot['sides'][$side]['category'] ?? null;
            $labels[] = $label . ': ' . (is_string($category) ? $category : 'tanpa cetak');
        }
        echo '<p class="sanro-studio-design-summary">' . esc_html(implode(' | ',$labels)) . '</p>';
    }
    // Admin-only order summary, based on saved WooCommerce item data.
    // Missing or mismatched quote metadata must not appear as a valid DTF price.
    if ($design_id !== '' && method_exists($item, 'get_meta')
        && method_exists($item, 'get_quantity')
        && method_exists($item, 'get_subtotal')
        && method_exists($item, 'get_total')) {
        $size = $item->get_meta('_sanro_studio_size', true);
        $price = $item->get_meta('_sanro_studio_unit_price', true);
        $currency = $item->get_meta('_sanro_studio_quote_currency', true);
        $qty = $item->get_quantity();
        $subtotal = $item->get_subtotal();
        $total = $item->get_total();
        $price_valid = is_string($size)
            && in_array($size, ['S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL'], true)
            && is_numeric($qty) && (int) $qty > 0
            && (string) (int) $qty === (string) $qty
            && (is_int($price) || (is_string($price) && preg_match('/^[1-9][0-9]*$/D', $price)))
            && (int) $price > 0 && $currency === 'IDR'
            && is_numeric($subtotal) && is_numeric($total)
            && (float) $subtotal === (float) ((int) $price * (int) $qty)
            && (float) $total === (float) ((int) $price * (int) $qty);
        $public_id=function_exists('sanro_studio_public_design_id')?sanro_studio_public_design_id($design_id):null;
        echo '<p>' . esc_html('Design ID: ' . (is_string($public_id)?$public_id:$design_id)) . '</p>';
        if ($price_valid) {
            echo '<p>' . esc_html('Ukuran: ' . $size . ' | Jumlah: ' . (int) $qty
                . ' | Harga DTF: Rp ' . number_format((int) $price, 0, ',', '.')
                . ' / pcs | Subtotal: Rp ' .
                number_format((int) $price * (int) $qty, 0, ',', '.')) . '</p>';
            $repeat=$item->get_meta('_sanro_studio_repeat_discount',true);
            if(is_array($repeat)&&is_array($repeat['proof']??null)&&($repeat['proof']['rate']??null)===5&&is_int($repeat['base_unit_price']??null)&&$repeat['base_unit_price']>0&&$repeat['base_unit_price']<=2000000&&is_int($repeat['unit_discount']??null)
                &&$repeat['unit_discount']===intdiv($repeat['base_unit_price']*5+50,100)&&$repeat['base_unit_price']-$repeat['unit_discount']===(int)$price){
                echo '<p>'.esc_html('Repeat 5% tersimpan | Harga dasar Rp '.number_format($repeat['base_unit_price'],0,',','.').' | Diskon Rp '.number_format($repeat['unit_discount'],0,',','.').' / pcs').'</p>';
            }
        } else {
            echo '<p>' . esc_html('Harga DTF / ukuran: belum terverifikasi pada item pesanan') . '</p>';
        }
    }
    echo '<div class="sanro-order-preview-grid">';
    foreach (['front' => 'Depan', 'back' => 'Belakang'] as $side => $label) {
        echo '<figure><figcaption>'.esc_html($label).'</figcaption>';
        if (empty($inventory[$side])) {
            echo '<p>' . esc_html($label . ': Belum tersedia') . '</p></figure>';
            continue;
        }
        $url = add_query_arg([
            'action' => 'sanro_studio_order_preview',
            'order_id' => $order_id,
            'item_id' => $item_id,
            'side' => $side,
        ], admin_url('admin-post.php'));
        $url = wp_nonce_url($url, 'sanro_order_preview_' . $order_id . '_' . $side . '_item_' . $item_id);
        echo '<p><a target="_blank" rel="noopener noreferrer" href="' . esc_url($url) . '">' .
            esc_html('Lihat preview ' . $label) . '</a></p>';
        echo '<img loading="lazy" width="160" height="160" style="max-width:100%;object-fit:contain"'
            . ' referrerpolicy="no-referrer" src="' . esc_url($url) . '" alt="'
            . esc_attr('Preview desain ' . $label) . '"></figure>';
    }
    echo '</div>';
    if(sanro_studio_auto_drive_order_eligible($order)){
        $guide_url=wp_nonce_url(add_query_arg(['action'=>'sanro_studio_ui_guide','order_id'=>$order_id,'item_id'=>$item_id],admin_url('admin-post.php')),'sanro_guide_'.$order_id.'_'.$item_id);
        echo '<a class="sanro-guide-link" href="'.esc_url($guide_url).'">PDF Panduan</a><p>Panduan posisi desain, bukan file siap cetak.</p>';
    }
    if(function_exists('sanro_studio_admin_item_production_readiness'))sanro_studio_admin_item_production_readiness($order,$item_id);
    echo '</div>';
}

/**
 * Read-only production-file eligibility. No Google Drive API calls or folder
 * creation here. The Drive URL remains unavailable until an authorized
 * integration stores a verified private reference separately.
 */
function sanro_studio_order_production_file_status($order) {
    if (!is_object($order) || !method_exists($order, 'is_paid')
        || !method_exists($order, 'get_meta')) {
        return ['valid' => false, 'error' => 'invalid_order'];
    }
    $ready = $order->get_meta('_sanro_studio_production_ready', true);
    // Never trust arbitrary truthy strings or buyer-supplied status.
    $production_ready = $ready === 'yes';
    $paid = $order->is_paid() === true;
    return [
        'valid' => true,
        'eligible' => $paid || $production_ready,
        'reason' => $paid ? 'payment_confirmed' : ($production_ready ? 'production_ready' : 'awaiting_payment_or_production'),
        'drive_link_available' => false,
        'drive_url' => null,
    ];
}

/**
 * Read-only production manifest for one WooCommerce line item.
 * Eligibility is order-wide, but design identity and preview references
 * remain scoped to the requested line. Never exposes Drive credentials,
 * buyer tokens, filesystem paths, or an unverified Drive URL.
 */
function sanro_studio_order_item_production_manifest($order, $item_id) {
    $eligibility = sanro_studio_order_production_file_status($order);
    if (empty($eligibility['valid'])) return $eligibility;
    $inventory = sanro_studio_order_item_preview_inventory($order, $item_id);
    if (empty($inventory['valid'])) return $inventory;
    $items = $order->get_items('line_item');
    $item = $items[$item_id];
    $design_id = $item->get_meta('_sanro_studio_design_id', true);
    if (!is_string($design_id)
        || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $design_id)) {
        return ['valid' => false, 'error' => 'design_not_bound'];
    }
    $has_preview = $inventory['front'] !== null && $inventory['back'] !== null;
    return [
        'valid' => true,
        'eligible' => $eligibility['eligible'],
        'reason' => $eligibility['reason'],
        'item_id' => $item_id,
        'design_id' => $design_id,
        'public_design_id' => function_exists('sanro_studio_public_design_id')?sanro_studio_public_design_id($design_id):null,
        'preview_front_available' => $inventory['front'] !== null,
        'preview_back_available' => $inventory['back'] !== null,
        'ready_for_drive_integration' => $eligibility['eligible'] && $has_preview,
        'drive_link_available' => false,
        'drive_url' => null,
    ];
}

/**
 * Admin-only production readiness transition. Never accepts a buyer request,
 * never changes payment status, and never creates a Drive folder.
 */
function sanro_studio_mark_order_production_ready($order) {
    if (!function_exists('current_user_can') || !current_user_can('manage_woocommerce')) {
        return ['valid' => false, 'error' => 'forbidden'];
    }
    if (!is_object($order) || !method_exists($order, 'get_id')
        || !method_exists($order, 'get_meta') || !method_exists($order, 'update_meta_data')
        || !method_exists($order, 'save') || (int) $order->get_id() < 1) {
        return ['valid' => false, 'error' => 'invalid_order'];
    }
    if ($order->get_meta('_sanro_studio_production_ready', true) === 'yes') {
        return ['valid' => true, 'already_ready' => true];
    }
    $order->update_meta_data('_sanro_studio_production_ready', 'yes');
    if ($order->save() === false) {
        return ['valid' => false, 'error' => 'production_ready_save_failed'];
    }
    // Dispatch only AFTER the admin-authorized order update is persisted.
    // The queued Drive worker is separately gated and never blocks this POST.
    if (function_exists('do_action')) {
        do_action('sanro_studio_production_ready_marked', (int) $order->get_id());
    }
    return ['valid' => true, 'already_ready' => false];
}

/**
 * Persist an opaque Drive folder ID only after an authorized integration has
 * independently created and verified that folder. This function does not
 * verify remote ownership or create a Google Drive URL.
 */
function sanro_studio_attach_item_production_folder_id($order, $item_id, $folder_id) {
    if (!function_exists('current_user_can') || !current_user_can('manage_woocommerce')) {
        return ['valid' => false, 'error' => 'forbidden'];
    }
    // An opaque ID is not proof of a Drive folder. Keep the persistence
    // helper disabled until the integration verifies folder ownership,
    // parent location and permissions through Google Drive API.
    if (!defined('SANRO_STUDIO_ENABLE_VERIFIED_DRIVE_ATTACHMENT')
        || SANRO_STUDIO_ENABLE_VERIFIED_DRIVE_ATTACHMENT !== true) {
        return ['valid' => false, 'error' => 'drive_verification_required'];
    }
    if (!is_string($folder_id) || !preg_match('/^[A-Za-z0-9_-]{10,128}$/D', $folder_id)) {
        return ['valid' => false, 'error' => 'invalid_folder_id'];
    }
    $manifest = sanro_studio_order_item_production_manifest($order, $item_id);
    if (empty($manifest['valid'])) return $manifest;
    if (empty($manifest['ready_for_drive_integration'])) {
        return ['valid' => false, 'error' => 'production_not_ready'];
    }
    $items = $order->get_items('line_item');
    $item = $items[$item_id];
    if (!method_exists($item, 'update_meta_data') || !method_exists($item, 'save')) {
        return ['valid' => false, 'error' => 'invalid_order_item'];
    }
    $existing = $item->get_meta('_sanro_studio_drive_folder_id', true);
    if ($existing !== '' && $existing !== $folder_id) {
        return ['valid' => false, 'error' => 'folder_conflict'];
    }
    // The feature flag alone cannot prove that a Drive folder belongs to this
    // order. Even idempotent reattachments must re-check the remote metadata.
    if (!function_exists('sanro_studio_verify_drive_item_folder')) {
        return ['valid' => false, 'error' => 'drive_verification_unavailable'];
    }
    $verified = sanro_studio_verify_drive_item_folder($order, $item_id, $folder_id);
    if (empty($verified['valid'])) {
        return ['valid' => false, 'error' => $verified['error'] ?? 'drive_verification_failed'];
    }
    if ($existing === $folder_id) return ['valid' => true, 'already_attached' => true];
    $item->update_meta_data('_sanro_studio_drive_folder_id', $folder_id);
    if ($item->save() === false) {
        return ['valid' => false, 'error' => 'folder_save_failed'];
    }
    return ['valid' => true, 'already_attached' => false];
}

/**
 * HPOS-compatible, read-only production status on the order details screen.
 * No Drive link is emitted until an independently verified integration exists.
 */
function sanro_studio_admin_production_status($order) {
    if (!function_exists('current_user_can') || !current_user_can('manage_woocommerce')
        || !is_object($order) || !method_exists($order, 'get_items')) return;
    $items = $order->get_items('line_item');
    if (!is_array($items)) return;
    $rows = [];
    $links = [];
    foreach ($items as $item_id => $item) {
        if (!is_object($item) || !method_exists($item, 'get_meta')
            || $item->get_meta('_sanro_studio_design_id', true) === '') continue;
        $manifest = sanro_studio_order_item_production_manifest($order, $item_id);
        if (empty($manifest['valid'])) {
            $rows[] = 'Item ' . (int) $item_id . ': data produksi belum valid';
            continue;
        }
        $url = function_exists('sanro_studio_verified_order_item_drive_url')
            ? sanro_studio_verified_order_item_drive_url($order, (int) $item_id) : null;
        $rows[$item_id] = 'Item ' . (int) $item_id . ': '
            . (!empty($manifest['eligible']) ? 'boleh diproses' : 'menunggu pembayaran atau persetujuan produksi')
            . '; preview depan ' . (!empty($manifest['preview_front_available']) ? 'tersedia' : 'belum tersedia')
            . '; belakang ' . (!empty($manifest['preview_back_available']) ? 'tersedia' : 'belum tersedia')
            . '; Google Drive ' . (is_string($url) ? 'terverifikasi' : 'belum terhubung');
        if (is_string($url)) $links[$item_id] = $url;
    }
    if (!$rows) return;
    echo '<div class="sanro-studio-production-status"><h3>'
        . esc_html('SANRO Studio — Status produksi') . '</h3>';
    foreach ($rows as $item_id => $row) {
        echo '<p>' . esc_html($row);
        if (isset($links[$item_id])) {
            echo ' <a target="_blank" rel="noopener noreferrer" href="'
                . esc_url($links[$item_id]) . '">' . esc_html('Buka folder produksi') . '</a>';
        }
        echo '</p>';
    }
    echo '</div>';
}

/**
 * Bind existing lightweight preview files to persisted Studio order items.
 * Called ONLY by the verified checkout draft-lock workflow, never via HTTP.
 * An ambiguous side (multiple different uploads) is deliberately left unbound.
 * No public links, token persistence, DTF rendering, or Drive writes.
 */
function sanro_studio_checkout_bind_existing_item_previews($order, $design_id, $owner_token, $item_ids) {
    if (!is_object($order) || !method_exists($order, 'get_items')
        || !is_array($item_ids) || !$item_ids
        || !is_string($design_id) || !is_string($owner_token)
        || !function_exists('sanro_studio_read_owned_draft_preview')
        || !defined('SANRO_STUDIO_ENABLE_PRIVATE_PREVIEWS')
        || SANRO_STUDIO_ENABLE_PRIVATE_PREVIEWS !== true
        || !defined('SANRO_STUDIO_PRIVATE_PREVIEW_DIR')
        || !is_string(SANRO_STUDIO_PRIVATE_PREVIEW_DIR)) {
        return ['valid' => false, 'error' => 'preview_unavailable'];
    }
    $directory = realpath(SANRO_STUDIO_PRIVATE_PREVIEW_DIR);
    $root = defined('ABSPATH') ? realpath(ABSPATH) : false;
    $webroot = isset($_SERVER['DOCUMENT_ROOT']) && is_string($_SERVER['DOCUMENT_ROOT'])
        ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    if (!$directory || is_link(SANRO_STUDIO_PRIVATE_PREVIEW_DIR)
        || !$root || !$webroot || $directory === $root || $directory === $webroot
        || strpos($directory, $root . DIRECTORY_SEPARATOR) === 0
        || strpos($directory, $webroot . DIRECTORY_SEPARATOR) === 0) {
        return ['valid' => false, 'error' => 'unsafe_preview_directory'];
    }
    // The draft's explicit current references are authoritative. Never
    // select an older upload by scanning the private directory.
    $record = get_option('sanro_studio_draft_' . $design_id, false);
    if (!is_array($record) || !is_array($record['preview_refs'] ?? null)
        || !isset($record['token_hash']) || !is_string($record['token_hash'])
        || !hash_equals($record['token_hash'], hash('sha256', $owner_token))
        || !is_array($record['design'] ?? null)
        || !is_string($record['preview_design_hash'] ?? null)
        || !hash_equals(
            hash('sha256', maybe_serialize($record['design'])),
            $record['preview_design_hash']
        )) {
        return ['valid' => false, 'error' => 'preview_revision_mismatch'];
    }
    $refs = [];
    foreach (['front', 'back'] as $side) {
        $ref = $record['preview_refs'][$side] ?? null;
        if (!is_string($ref) || $ref === '') continue;
        $checked = sanro_studio_read_owned_draft_preview(
            $design_id, $owner_token, $side, $ref
        );
        if (!empty($checked['valid'])) $refs[$side] = $ref;
    }
    if (count($refs) !== 2) {
        return ['valid' => false, 'error' => 'incomplete_preview_refs'];
    }
    $items = $order->get_items('line_item');
    // Preflight every line before writing any metadata. A conflict in the
    // second size must not leave the first size with partially bound artwork.
    if (count(array_unique($item_ids)) !== count($item_ids)) {
        return ['valid' => false, 'error' => 'duplicate_order_item'];
    }
    foreach ($item_ids as $item_id) {
        if (!is_int($item_id) || $item_id < 1 || !isset($items[$item_id])
            || !is_object($items[$item_id])) {
            return ['valid' => false, 'error' => 'invalid_order_item'];
        }
        $candidate = $items[$item_id];
        if (!method_exists($candidate, 'get_meta') || !method_exists($candidate, 'save')
            || !method_exists($candidate, 'update_meta_data')
            || $candidate->get_meta('_sanro_studio_design_id', true) !== $design_id) {
            return ['valid' => false, 'error' => 'order_design_mismatch'];
        }
        $refs = function_exists('sanro_studio_record_preview_refs') ? sanro_studio_record_preview_refs($record, $candidate->get_meta('_sanro_studio_size', true)) : $refs;
        if (count($refs) !== 2) return ['valid'=>false, 'error'=>'incomplete_preview_refs'];
        foreach ($refs as $side => $ref) {
            if (empty(sanro_studio_read_owned_draft_preview($design_id, $owner_token, $side, $ref)['valid'])) return ['valid'=>false, 'error'=>'incomplete_preview_refs'];
            $existing = $candidate->get_meta('_sanro_studio_preview_' . $side . '_ref', true);
            if ($existing !== '' && $existing !== $ref) {
                return ['valid' => false, 'error' => 'preview_reference_conflict'];
            }
        }
    }
    $bound = 0;
    foreach ($item_ids as $item_id) {
        $item = $items[$item_id];
        $refs = function_exists('sanro_studio_record_preview_refs') ? sanro_studio_record_preview_refs($record, $item->get_meta('_sanro_studio_size', true)) : $refs;
        $changed = false;
        foreach ($refs as $side => $ref) {
            $key = '_sanro_studio_preview_' . $side . '_ref';
            $existing = $item->get_meta($key, true);
            if ($existing !== '' && $existing !== $ref) {
                return ['valid' => false, 'error' => 'preview_reference_conflict'];
            }
            if ($existing === '') {
                $item->update_meta_data($key, $ref);
                $changed = true;
            }
        }
        if ($changed && $item->save() === false) {
            return ['valid' => false, 'error' => 'preview_order_save_failed'];
        }
        // Confirm persisted references for each order line, including retries.
        foreach ($refs as $side => $ref) {
            if ($item->get_meta('_sanro_studio_preview_' . $side . '_ref', true) !== $ref) {
                return ['valid' => false, 'error' => 'preview_order_readback_failed'];
            }
        }
        $bound++;
    }
    return ['valid' => true, 'bound' => $bound];
}
