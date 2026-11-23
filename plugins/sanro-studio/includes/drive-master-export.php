<?php
defined('ABSPATH') || exit;

/**
 * Export original, content-addressed master images to a VERIFIED private
 * order-item folder. NOT an export of the composited print-ready DTF file.
 * Disabled by default and never exposed as an HTTP endpoint.
 */
function sanro_studio_drive_master_export_enabled() {
    return function_exists('sanro_studio_auto_drive_enabled')
        && sanro_studio_auto_drive_enabled()
        && defined('SANRO_STUDIO_ENABLE_DRIVE_MASTER_EXPORT')
        && SANRO_STUDIO_ENABLE_DRIVE_MASTER_EXPORT === true;
}

/** Remote file must be private, untrashed and bound to exact item/source. */
function sanro_studio_drive_master_remote_valid($folder_id, $file_id, $properties, $source, $token) {
    if (!is_string($file_id) || !preg_match('/^[A-Za-z0-9_-]{10,128}$/D', $file_id)) return false;
    $url = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($file_id)
        . '?fields=' . rawurlencode('id,mimeType,parents,trashed,size,md5Checksum,permissions(type),appProperties');
    $response = wp_remote_get($url, [
        'timeout' => 15, 'redirection' => 0, 'limit_response_size' => 8192,
        'headers' => ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'],
    ]);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) return false;
    $body = wp_remote_retrieve_body($response);
    if (!is_string($body) || strlen($body) > 8192) return false;
    $remote = json_decode($body, true);
    if (!is_array($remote) || ($remote['id'] ?? null) !== $file_id
        || ($remote['mimeType'] ?? null) !== $source['mime']
        || ($remote['parents'] ?? null) !== [$folder_id]
        || ($remote['trashed'] ?? true) !== false
        || (string) ($remote['size'] ?? '') !== (string) strlen($source['bytes'])
        || ($remote['md5Checksum'] ?? null) !== md5($source['bytes'])
        || !is_array($remote['appProperties'] ?? null)) return false;
    foreach ($properties as $key => $value) {
        if (($remote['appProperties'][$key] ?? null) !== $value) return false;
    }
    $permissions = $remote['permissions'] ?? null;
    if (!is_array($permissions) || !$permissions) return false;
    foreach ($permissions as $permission) {
        if (!is_array($permission) || ($permission['type'] ?? null) !== 'user') return false;
    }
    return true;
}

/**
 * Replays are idempotent and recover uploads that completed remotely before
 * WooCommerce CRUD save. Ambiguous remote matches fail CLOSED.
 */
function sanro_studio_drive_upload_locked_item_master($order, $item_id, $folder_id, $side) {
    if (!sanro_studio_drive_master_export_enabled() || !is_int($item_id) || $item_id < 1
        || !in_array($side, ['front', 'back'], true)
        || !is_string($folder_id) || !preg_match('/^[A-Za-z0-9_-]{10,128}$/D', $folder_id)
        || !function_exists('wp_remote_get') || !function_exists('wp_remote_post')
        || !function_exists('wp_remote_retrieve_response_code')
        || !function_exists('wp_remote_retrieve_body') || !function_exists('wp_json_encode')
        || !function_exists('add_option') || !function_exists('delete_option')
        || !function_exists('apply_filters') || !function_exists('home_url')
        || !function_exists('is_wp_error')) {
        return ['valid' => false, 'error' => 'drive_master_export_disabled'];
    }
    $source = sanro_studio_locked_item_master_source($order, $item_id, $side);
    if (empty($source['valid'])) return $source;
    if (!empty($source['skipped'])) return ['valid' => true, 'skipped' => true];
    return sanro_studio_drive_upload_item_source($order,$item_id,$folder_id,$side,$source);
}

/** Internal uploader shared by verified originals and production packages. */
function sanro_studio_drive_upload_item_source($order,$item_id,$folder_id,$side,$source,$kind='original') {
    if (!sanro_studio_auto_drive_order_eligible($order)
        || ($kind==='original' ? !sanro_studio_drive_master_export_enabled() : (!function_exists('sanro_studio_drive_package_enabled') || !sanro_studio_drive_package_enabled()))
        || !in_array($kind,['original','preview','guide','manifest','dtf-candidate'],true)
        || !in_array($side,['front','back','guide'],true)
        || !is_int($item_id) || $item_id<1 || !is_string($folder_id) || !preg_match('/^[A-Za-z0-9_-]{10,128}$/D',$folder_id)
        || !is_array($source) || empty($source['valid']) || !is_string($source['bytes']??null)
        || strlen($source['bytes'])<1 || strlen($source['bytes'])>33554432
        || !is_string($source['sha256']??null) || !hash_equals($source['sha256'],hash('sha256',$source['bytes']))
        || !is_string($source['design_id']??null)) return ['valid'=>false,'error'=>'production_source_invalid'];
    $formats=['original'=>['image/png'=>'png','image/jpeg'=>'jpg'],'preview'=>['image/jpeg'=>'jpg'],
        'guide'=>['application/pdf'=>'pdf'],'manifest'=>['application/json'=>'json'],'dtf-candidate'=>['image/png'=>'png']];
    if (($formats[$kind][$source['mime']??'']??null)!==($source['ext']??null)) return ['valid'=>false,'error'=>'production_type_invalid'];
    $all_items=$order->get_items('line_item');
    if (!isset($all_items[$item_id]) || $all_items[$item_id]->get_meta('_sanro_studio_design_id',true)!==$source['design_id']) return ['valid'=>false,'error'=>'production_source_mismatch'];
    $folder = sanro_studio_verify_drive_item_folder($order, $item_id, $folder_id);
    if (empty($folder['valid'])) return ['valid' => false, 'error' => 'folder_unverified'];
    $token = apply_filters('sanro_studio_drive_access_token', '');
    if (!is_string($token) || strlen($token) < 12 || strlen($token) > 4096
        || !preg_match('/^[A-Za-z0-9._~+\/-]+$/D', $token)) {
        return ['valid' => false, 'error' => 'drive_not_connected'];
    }
    $items = $order->get_items('line_item');
    $item = $items[$item_id];
    if (!method_exists($item, 'update_meta_data') || !method_exists($item, 'save')) {
        return ['valid' => false, 'error' => 'invalid_order_item'];
    }
    $properties = [
        'sanroSite' => hash('sha256', rtrim(home_url('/'), '/')),
        'sanroOrderId' => (string) $order->get_id(),
        'sanroItemId' => (string) $item_id,
        'sanroDesignId' => $source['design_id'],
        'sanroSide' => $side,
        'sanroSha256' => $source['sha256'],
    ];
    if ($kind!=='original') $properties['sanroAssetKind']=$kind;
    $lock = 'sanro_drive_master_lock_' . md5(home_url('/') . ':' . $order->get_id() . ':' . $item_id . ':' . $side . ($kind==='original'?'':':'.$kind));
    if (!add_option($lock, (string) time(), '', false)) {
        return ['valid' => false, 'error' => 'drive_master_locked'];
    }
    try {
        $meta_key = $kind==='original' ? '_sanro_studio_drive_master_' . $side . '_id' : '_sanro_studio_drive_package_'.str_replace('-','_',$kind).'_'.$side.'_id';
        $existing = $item->get_meta($meta_key, true);
        if ($existing !== '') {
            return sanro_studio_drive_master_remote_valid($folder_id, $existing, $properties, $source, $token)
                ? ['valid' => true, 'created' => false, 'file_id' => $existing]
                : ['valid' => false, 'error' => 'existing_master_unverified'];
        }
        $q = "'" . $folder_id . "' in parents and trashed = false";
        foreach ($properties as $key => $value) {
            $q .= " and appProperties has { key='" . $key . "' and value='" . $value . "' }";
        }
        $url = 'https://www.googleapis.com/drive/v3/files?fields='
            . rawurlencode('nextPageToken,files(id)') . '&pageSize=2&q=' . rawurlencode($q);
        $headers = ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'];
        $response = wp_remote_get($url, [
            'timeout' => 15, 'redirection' => 0, 'limit_response_size' => 8192,
            'headers' => $headers,
        ]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return ['valid' => false, 'error' => 'drive_master_search_failed'];
        }
        $body = wp_remote_retrieve_body($response);
        $result = is_string($body) && strlen($body) <= 8192 ? json_decode($body, true) : null;
        if (!is_array($result) || !is_array($result['files'] ?? null)
            || count($result['files']) > 1 || !empty($result['nextPageToken'])) {
            return ['valid' => false, 'error' => 'drive_master_search_ambiguous'];
        }
        $created = false;
        if ($result['files']) {
            $file_id = $result['files'][0]['id'] ?? null;
        } else {
            // Keep raw image bytes solely in this server-side HTTPS POST; no
            // buyer URL, WP Media Library attachment or public Drive sharing.
            $boundary = 'sanro-' . bin2hex(random_bytes(16));
            $metadata = [
                'name' => 'SANRO-' . (int) $order->get_id() . '-ITEM-' . $item_id
                    . '-' . $side . '-' . $kind . '.' . $source['ext'],
                'parents' => [$folder_id], 'mimeType' => $source['mime'],
                'appProperties' => $properties,
            ];
            $body = '--' . $boundary . "\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n"
                . wp_json_encode($metadata) . "\r\n--" . $boundary
                . "\r\nContent-Type: " . $source['mime'] . "\r\n\r\n"
                . $source['bytes'] . "\r\n--" . $boundary . "--\r\n";
            $response = wp_remote_post(
                'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id',
                ['timeout' => 60, 'redirection' => 0, 'limit_response_size' => 8192,
                    'headers' => $headers + ['Content-Type' => 'multipart/related; boundary=' . $boundary],
                    'body' => $body]
            );
            if (is_wp_error($response)
                || !in_array(wp_remote_retrieve_response_code($response), [200, 201], true)) {
                return ['valid' => false, 'error' => 'drive_master_upload_failed'];
            }
            $reply = json_decode(wp_remote_retrieve_body($response), true);
            $file_id = is_array($reply) ? ($reply['id'] ?? null) : null;
            $created = true;
        }
        if (!sanro_studio_drive_master_remote_valid($folder_id, $file_id, $properties, $source, $token)) {
            return ['valid' => false, 'error' => 'drive_uploaded_master_unverified'];
        }
        // Re-read the in-memory item after the remote call; DB lock serializes
        // this worker's writes. Never silently overwrite an existing binding.
        if (!sanro_studio_auto_drive_order_eligible($order) || $item->get_meta($meta_key, true) !== '') {
            return ['valid' => false, 'error' => 'drive_master_conflict'];
        }
        $item->update_meta_data($meta_key, $file_id);
        if ($item->save() === false) return ['valid' => false, 'error' => 'drive_master_save_failed'];
        return ['valid' => true, 'created' => $created, 'file_id' => $file_id];
    } finally {
        delete_option($lock);
    }
}
