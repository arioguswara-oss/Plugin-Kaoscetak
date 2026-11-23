<?php
defined('ABSPATH') || exit;

/**
 * Server-side, opt-in Drive provisioning. Never runs from a buyer endpoint.
 * Requires an existing trusted OAuth filter; does not implement OAuth itself.
 */
function sanro_studio_auto_drive_enabled() {
    return defined('SANRO_STUDIO_ENABLE_DRIVE_AUTO_PROVISION')
        && SANRO_STUDIO_ENABLE_DRIVE_AUTO_PROVISION === true
        && defined('SANRO_STUDIO_ENABLE_VERIFIED_DRIVE_ATTACHMENT')
        && SANRO_STUDIO_ENABLE_VERIFIED_DRIVE_ATTACHMENT === true
        && defined('SANRO_STUDIO_DRIVE_PARENT_FOLDER_ID')
        && is_string(SANRO_STUDIO_DRIVE_PARENT_FOLDER_ID)
        && (bool) preg_match('/^[A-Za-z0-9_-]{10,128}$/D', SANRO_STUDIO_DRIVE_PARENT_FOLDER_ID);
}

/** Payment must actually be confirmed, or an authorized admin must mark ready. */
function sanro_studio_auto_drive_order_eligible($order) {
    if (!is_object($order) || !method_exists($order, 'get_id')
        || !method_exists($order, 'get_meta') || !method_exists($order, 'get_status')
        || !method_exists($order, 'is_paid') || !method_exists($order, 'get_items')
        || (int) $order->get_id() < 1) return false;
    $status = $order->get_status();
    if (!is_string($status) || in_array($status, ['cancelled', 'refunded', 'failed', 'trash'], true)) return false;
    if ($order->is_paid() === true) return true;
    return in_array($status, ['pending', 'on-hold'], true)
        && $order->get_meta('_sanro_studio_production_ready', true) === 'yes';
}

/** Schedule asynchronously, so neither payment checkout nor admin POST waits on Drive. */
function sanro_studio_schedule_drive_production($order_id) {
    if (!sanro_studio_auto_drive_enabled() || !is_numeric($order_id)
        || (int) $order_id < 1 || !function_exists('wc_get_order')) return;
    $order_id = (int) $order_id;
    if (!sanro_studio_auto_drive_order_eligible(wc_get_order($order_id))) return;
    if (function_exists('as_enqueue_async_action')) {
        as_enqueue_async_action('sanro_studio_drive_provision_order', [$order_id], 'sanro-studio', true);
    } elseif (function_exists('wp_schedule_single_event') && function_exists('wp_next_scheduled')) {
        if (!wp_next_scheduled('sanro_studio_drive_provision_order', [$order_id])) {
            wp_schedule_single_event(time() + 5, 'sanro_studio_drive_provision_order', [$order_id]);
        }
    }
}

/** Only server cron / WooCommerce Action Scheduler calls this registered action. */
function sanro_studio_run_drive_production($order_id) {
    if (!sanro_studio_auto_drive_enabled() || !is_numeric($order_id)
        || (int) $order_id < 1 || !function_exists('wc_get_order')) {
        return ['valid' => false, 'error' => 'drive_auto_disabled'];
    }
    $order = wc_get_order((int) $order_id);
    if (!sanro_studio_auto_drive_order_eligible($order)) {
        return ['valid' => false, 'error' => 'production_not_ready'];
    }
    $items = $order->get_items('line_item');
    if (!is_array($items)) return ['valid' => false, 'error' => 'invalid_items'];
    $targets = [];
    // Preflight EVERY design line before any network or external side effects.
    foreach ($items as $item_id => $item) {
        if (!is_int($item_id) || $item_id < 1 || !is_object($item)
            || !method_exists($item, 'get_meta')) return ['valid' => false, 'error' => 'invalid_item'];
        $has_studio_data = false;
        foreach (['_sanro_studio_design_id', '_sanro_studio_preview_front_ref',
            '_sanro_studio_preview_back_ref'] as $field) {
            if ($item->get_meta($field, true) !== '') $has_studio_data = true;
        }
        if (!$has_studio_data) continue;
        $manifest = sanro_studio_order_item_production_manifest($order, $item_id);
        if (empty($manifest['valid']) || empty($manifest['ready_for_drive_integration'])) {
            return ['valid' => false, 'error' => 'invalid_production_manifest'];
        }
        $targets[] = $item_id;
    }
    if (!$targets) return ['valid' => false, 'error' => 'no_studio_designs'];
    // Validate every guide/preview/locked allocation before the first remote
    // folder is created. This new package path is independently default OFF.
    if (function_exists('sanro_studio_drive_package_enabled') && sanro_studio_drive_package_enabled()) {
        foreach ($targets as $item_id) {
            $package=sanro_studio_prepare_production_package($order,$item_id);
            if (empty($package['valid'])) return ['valid'=>false,'error'=>$package['error']??'production_package_invalid'];
            if(defined('SANRO_STUDIO_ENABLE_DRIVE_DTF_CANDIDATES')&&SANRO_STUDIO_ENABLE_DRIVE_DTF_CANDIDATES===true){
                $dtf=sanro_studio_dtf_preflight_order_item_candidates($order,$item_id);
                if(empty($dtf['valid']))return ['valid'=>false,'error'=>'dtf_package_preflight_failed'];
            }
        }
    }
    $created = 0; $reused = 0;
    $masters_exported = 0; $masters_reused = 0; $masters_skipped = 0;
    foreach ($targets as $item_id) {
        $result = sanro_studio_auto_drive_provision_item($order, $item_id);
        if (empty($result['valid'])) return ['valid' => false,
            'error' => $result['error'] ?? 'drive_provision_failed',
            'created' => $created, 'reused' => $reused];
        if (!empty($result['created'])) $created++;
        else $reused++;
        if (function_exists('sanro_studio_drive_package_enabled') && sanro_studio_drive_package_enabled()) {
            $package=sanro_studio_export_production_package($order,$item_id,$result['folder_id']);
            if (empty($package['valid'])) return ['valid'=>false,'error'=>$package['error']??'production_package_failed','created'=>$created,'reused'=>$reused];
        }
        // A folder being ready does NOT mean that a final DTF composition is
        // ready. Export only explicitly bound, locked ORIGINAL image assets.
        if (function_exists('sanro_studio_drive_master_export_enabled')
            && sanro_studio_drive_master_export_enabled()) {
            foreach (['front', 'back'] as $side) {
                $export = sanro_studio_drive_upload_locked_item_master(
                    $order, $item_id, $result['folder_id'], $side
                );
                if (empty($export['valid'])) {
                    return ['valid' => false,
                        'error' => $export['error'] ?? 'drive_master_export_failed',
                        'created' => $created, 'reused' => $reused,
                        'masters_exported' => $masters_exported,
                        'masters_reused' => $masters_reused];
                }
                if (!empty($export['skipped'])) $masters_skipped++;
                elseif (!empty($export['created'])) $masters_exported++;
                else $masters_reused++;
            }
        }
    }
    return ['valid' => true, 'created' => $created, 'reused' => $reused,
        'masters_exported' => $masters_exported,
        'masters_reused' => $masters_reused, 'masters_skipped' => $masters_skipped];
}

/** Per-item exclusive DB lock plus Google search: safe across repeated hooks. */
function sanro_studio_auto_drive_provision_item($order, $item_id) {
    if (!sanro_studio_auto_drive_enabled() || !sanro_studio_auto_drive_order_eligible($order)
        || !is_int($item_id) || $item_id < 1
        || !function_exists('add_option') || !function_exists('delete_option')
        || !function_exists('home_url') || !function_exists('apply_filters')
        || !function_exists('wp_remote_get') || !function_exists('wp_remote_post')
        || !function_exists('wp_remote_retrieve_response_code')
        || !function_exists('wp_remote_retrieve_body') || !function_exists('is_wp_error')) {
        return ['valid' => false, 'error' => 'drive_runtime_unavailable'];
    }
    $items = $order->get_items('line_item');
    if (!is_array($items) || !isset($items[$item_id])) return ['valid' => false, 'error' => 'invalid_item'];
    $item = $items[$item_id];
    if (!is_object($item) || !method_exists($item, 'get_meta')
        || !method_exists($item, 'update_meta_data') || !method_exists($item, 'save')) {
        return ['valid' => false, 'error' => 'invalid_item'];
    }
    $manifest = sanro_studio_order_item_production_manifest($order, $item_id);
    if (empty($manifest['valid']) || empty($manifest['ready_for_drive_integration'])) {
        return ['valid' => false, 'error' => 'invalid_production_manifest'];
    }
    $lock = 'sanro_drive_lock_' . md5(home_url('/') . ':' . (int) $order->get_id() . ':' . $item_id);
    // An abandoned lock must be inspected manually; never risk double-create
    // by automatically stealing a possibly active lock.
    if (!add_option($lock, (string) time(), '', false)) {
        return ['valid' => false, 'error' => 'drive_provision_locked'];
    }
    try {
        $existing = $item->get_meta('_sanro_studio_drive_folder_id', true);
        if ($existing !== '') {
            if (!is_string($existing)) return ['valid' => false, 'error' => 'folder_conflict'];
            $verified = sanro_studio_verify_drive_item_folder($order, $item_id, $existing);
            return !empty($verified['valid'])
                ? ['valid' => true, 'created' => false, 'folder_id' => $existing]
                : ['valid' => false, 'error' => 'existing_folder_unverified'];
        }
        $token = apply_filters('sanro_studio_drive_access_token', '');
        if (!is_string($token) || strlen($token) < 12 || strlen($token) > 4096
            || !preg_match('/^[A-Za-z0-9._~+\/-]+$/D', $token)) {
            return ['valid' => false, 'error' => 'drive_not_connected'];
        }
        $parent = SANRO_STUDIO_DRIVE_PARENT_FOLDER_ID;
        $site = hash('sha256', rtrim(home_url('/'), '/'));
        $props = ['sanroSite' => $site, 'sanroOrderId' => (string) $order->get_id(),
            'sanroItemId' => (string) $item_id];
        $query = "'" . $parent . "' in parents and trashed = false and mimeType = 'application/vnd.google-apps.folder'";
        foreach ($props as $key => $value) {
            $query .= " and appProperties has { key='" . $key . "' and value='" . $value . "' }";
        }
        $url = 'https://www.googleapis.com/drive/v3/files?fields='
            . rawurlencode('nextPageToken,files(id)') . '&pageSize=2&q=' . rawurlencode($query);
        $headers = ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'];
        $response = wp_remote_get($url, ['timeout' => 15, 'redirection' => 0,
            'limit_response_size' => 8192, 'headers' => $headers]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return ['valid' => false, 'error' => 'drive_search_failed'];
        }
        $lookup = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($lookup) || !isset($lookup['files']) || !is_array($lookup['files'])
            || count($lookup['files']) > 1 || !empty($lookup['nextPageToken'])) {
            return ['valid' => false, 'error' => 'drive_search_ambiguous'];
        }
        $created = false;
        if (count($lookup['files']) === 1) {
            $folder_id = $lookup['files'][0]['id'] ?? null;
        } else {
            $payload = ['name' => 'SANRO-' . (int) $order->get_id() . '-ITEM-' . $item_id,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => [$parent], 'appProperties' => $props];
            $response = wp_remote_post('https://www.googleapis.com/drive/v3/files?fields=id', [
                'timeout' => 15, 'redirection' => 0, 'limit_response_size' => 8192,
                'headers' => $headers + ['Content-Type' => 'application/json'],
                'body' => wp_json_encode($payload),
            ]);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200
                && wp_remote_retrieve_response_code($response) !== 201) {
                return ['valid' => false, 'error' => 'drive_create_failed'];
            }
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $folder_id = is_array($body) ? ($body['id'] ?? null) : null;
            $created = true;
        }
        if (!is_string($folder_id) || !preg_match('/^[A-Za-z0-9_-]{10,128}$/D', $folder_id)) {
            return ['valid' => false, 'error' => 'drive_invalid_folder_id'];
        }
        $verified = sanro_studio_verify_drive_item_folder($order, $item_id, $folder_id);
        if (empty($verified['valid'])) {
            return ['valid' => false, 'error' => 'drive_created_folder_unverified'];
        }
        // No admin capability bypass in the public attachment helper: this
        // write occurs only inside this private server worker after live GET.
        if ($item->get_meta('_sanro_studio_drive_folder_id', true) !== '') {
            return ['valid' => false, 'error' => 'folder_conflict'];
        }
        $item->update_meta_data('_sanro_studio_drive_folder_id', $folder_id);
        if ($item->save() === false) return ['valid' => false, 'error' => 'folder_save_failed'];
        return ['valid' => true, 'created' => $created, 'folder_id' => $folder_id];
    } finally {
        delete_option($lock);
    }
}
