<?php
defined('ABSPATH') || exit;

/**
 * SANRO Studio DTF raster candidate v1 — ONE transparent PNG image layer.
 * Origin: verified, locked private order draft (never buyer-supplied pixels).
 * Output is an IN-MEMORY PNG candidate. No public endpoint, filesystem write,
 * checkout hook, Google Drive upload or claim of press-ready DTF separations.
 */
function sanro_studio_dtf_image_render_enabled() {
    return defined('SANRO_STUDIO_ENABLE_DTF_IMAGE_RASTER')
        && SANRO_STUDIO_ENABLE_DTF_IMAGE_RASTER === true;
}

/**
 * Build a strict production geometry contract in centimeters and 300 DPI.
 * Reject text/mixed layers rather than silently dropping or misrendering them.
 * The physical output canvas tightly crops the print object, while offsets
 * preserve its original location relative to the product's safe area.
 */
function sanro_studio_dtf_image_plan($order, $item_id, $side) {
    if (!sanro_studio_dtf_image_render_enabled()) {
        return ['valid' => false, 'error' => 'dtf_render_disabled'];
    }
    if (!in_array($side, ['front', 'back'], true) || !is_int($item_id)
        || $item_id < 1 || !is_object($order)
        || !method_exists($order, 'get_id') || !method_exists($order, 'get_items')
        || !function_exists('get_option')
        || !function_exists('sanro_studio_locked_record_has_item')
        || !function_exists('sanro_studio_auto_drive_order_eligible')
        || !sanro_studio_auto_drive_order_eligible($order)
        || !function_exists('sanro_studio_product_safe_areas')
        || !function_exists('sanro_studio_validate_product_design')
        || !function_exists('sanro_studio_locked_item_master_source')) {
        return ['valid' => false, 'error' => 'dtf_render_unavailable'];
    }
    $items = $order->get_items('line_item');
    if (!is_array($items) || !isset($items[$item_id])
        || !is_object($items[$item_id])
        || !method_exists($items[$item_id], 'get_meta')
        || !method_exists($items[$item_id], 'get_product_id')) {
        return ['valid' => false, 'error' => 'invalid_order_item'];
    }
    $item = $items[$item_id];
    $design_id = $item->get_meta('_sanro_studio_design_id', true);
    $product_id = $item->get_product_id();
    if (!is_string($design_id)
        || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $design_id)
        || !is_int($product_id) || $product_id < 1) {
        return ['valid' => false, 'error' => 'invalid_design_binding'];
    }
    $record = get_option('sanro_studio_draft_' . $design_id, false);
    if (!is_array($record) || ($record['status'] ?? null) !== 'locked'
        || ($record['order_id'] ?? null) !== $order->get_id()
        || ($record['product_id'] ?? null) !== $product_id
        || !sanro_studio_locked_record_has_item($record, $item_id)
        || !is_array($record['design'] ?? null)) {
        return ['valid' => false, 'error' => 'draft_order_binding_missing'];
    }
    $design = $record['design'];
    $areas = sanro_studio_product_safe_areas($product_id);
    if (!is_array($areas) || !isset($areas[$side])
        || empty(sanro_studio_validate_product_design($product_id, $design)['valid'])) {
        return ['valid' => false, 'error' => 'invalid_print_geometry'];
    }
    if (!array_key_exists($side, $design)) {
        return ['valid' => true, 'skipped' => true, 'side' => $side, 'reason' => 'blank_side'];
    }
    $objects = $design[$side]['objects'] ?? null;
    if (!is_array($objects)) {
        return ['valid' => false, 'error' => 'invalid_print_geometry'];
    }
    if (!$objects) {
        return ['valid' => true, 'skipped' => true, 'side' => $side, 'reason' => 'blank_side'];
    }
    // All text and mixed-layer designs require a font/flattening renderer
    // that agrees with the buyer editor; NEVER omit their content.
    if (count($objects) !== 1 || ($objects[0]['type'] ?? null) !== 'image'
        || !empty($design[$side]['text'])) {
        return ['valid' => false, 'error' => 'dtf_layers_unsupported'];
    }
    $rect = $objects[0];
    $width = (float) $rect['width'];
    $height = (float) $rect['height'];
    $x = (float) $rect['x']; $y = (float) $rect['y'];
    if ($width <= 0 || $height <= 0 || $x < 0 || $y < 0
        || $width > 100 || $height > 100
        || !is_finite($width) || !is_finite($height)
        || !is_finite($x) || !is_finite($y)) {
        return ['valid' => false, 'error' => 'invalid_print_geometry'];
    }
    $dpi = 300;
    $out_w = (int) round($width * $dpi / 2.54);
    $out_h = (int) round($height * $dpi / 2.54);
    // v1 resource limit avoids multi-hundred-MB PHP-GD allocations.
    if ($out_w < 1 || $out_h < 1 || $out_w > 4096 || $out_h > 4096
        || $out_w * $out_h > 6000000) {
        return ['valid' => false, 'error' => 'dtf_canvas_limit'];
    }
    $source = sanro_studio_locked_item_master_source($order, $item_id, $side);
    if (empty($source['valid']) || !empty($source['skipped'])
        || !is_string($source['bytes'] ?? null)
        || !is_string($source['sha256'] ?? null)) {
        return ['valid' => false, 'error' => 'dtf_master_unavailable'];
    }
    // Require an RGBA8 non-interlaced PNG. JPEG/RGB files cannot guarantee
    // that backgrounds will remain transparent on the transfer film.
    $bytes = $source['bytes'];
    if (($source['mime'] ?? null) !== 'image/png'
        || strlen($bytes) < 64
        || substr($bytes, 0, 8) !== "\x89PNG\r\n\x1a\n"
        || substr($bytes, 12, 4) !== 'IHDR'
        || unpack('N', substr($bytes, 8, 4))[1] !== 13
        || ord($bytes[24]) !== 8 || ord($bytes[25]) !== 6
        || ord($bytes[28]) !== 0) {
        return ['valid' => false, 'error' => 'dtf_rgba_png_required'];
    }
    $info = function_exists('getimagesizefromstring')
        ? @getimagesizefromstring($bytes) : false;
    if (!is_array($info) || ($info['mime'] ?? null) !== 'image/png'
        || !isset($info[0], $info[1])
        || $info[0] < $out_w || $info[1] < $out_h
        || $info[0] > 10000 || $info[1] > 10000
        || $info[0] * $info[1] > 12000000) {
        return ['valid' => false, 'error' => 'dtf_source_resolution'];
    }
    $source_ratio = $info[0] / $info[1];
    $target_ratio = $width / $height;
    if (abs($source_ratio / $target_ratio - 1) > 0.015) {
        return ['valid' => false, 'error' => 'dtf_source_aspect_mismatch'];
    }
    if (!hash_equals(hash('sha256', $bytes), $source['sha256'])) {
        return ['valid' => false, 'error' => 'dtf_source_integrity_failed'];
    }
    return [
        'valid' => true, 'skipped' => false,
        'side' => $side, 'design_id' => $design_id, 'order_id' => $order->get_id(),
        'item_id' => $item_id, 'width_cm' => $width, 'height_cm' => $height,
        'offset_x_cm' => $x, 'offset_y_cm' => $y,
        'dpi' => $dpi, 'width_px' => $out_w, 'height_px' => $out_h,
        'source_sha256' => $source['sha256'], 'source_bytes' => $bytes,
        'source_width_px' => $info[0], 'source_height_px' => $info[1],
    ];
}

/**
 * PNG pHYs records pixels per meter. A 300 DPI request corresponds to
 * approximately 11,811 px/m, with integer rounding of the physical unit.
 */
function sanro_studio_dtf_png_has_300dpi($png) {
    if (!is_string($png) || substr($png, 0, 8) !== "\x89PNG\r\n\x1a\n") return false;
    $offset = 8;
    $total = strlen($png);
    while ($offset + 12 <= $total && $offset < 8192) {
        $length = unpack('N', substr($png, $offset, 4))[1];
        if ($length > 8192 || $offset + 12 + $length > $total) return false;
        $type = substr($png, $offset + 4, 4);
        if ($type === 'pHYs') {
            if ($length !== 9) return false;
            $x = unpack('N', substr($png, $offset + 8, 4))[1];
            $y = unpack('N', substr($png, $offset + 12, 4))[1];
            $unit = ord($png[$offset + 16]);
            return $unit === 1 && abs($x - 11811) <= 2 && abs($y - 11811) <= 2;
        }
        if ($type === 'IDAT' || $type === 'IEND') return false;
        $offset += 12 + $length;
    }
    return false;
}

/**
 * Rasterize actual pixels at physical 300 DPI, keeping the PNG alpha channel.
 * Requires the PHP GD extension and imageresolution() (no shell execution).
 * Returned bytes are private; the caller must NEVER print them to an HTTP
 * response or use a public WordPress media attachment.
 */
function sanro_studio_dtf_render_image_side($order, $item_id, $side) {
    $plan = sanro_studio_dtf_image_plan($order, $item_id, $side);
    if (empty($plan['valid']) || !empty($plan['skipped'])) return $plan;
    foreach (['imagecreatefromstring', 'imagecreatetruecolor', 'imagealphablending',
        'imagesavealpha', 'imagecolorallocatealpha', 'imagefill', 'imagecopyresampled',
        'imageresolution', 'imagepng', 'imagedestroy'] as $required) {
        if (!function_exists($required)) {
            return ['valid' => false, 'error' => 'dtf_gd_unavailable'];
        }
    }
    $original = @imagecreatefromstring($plan['source_bytes']);
    if ($original === false) {
        return ['valid' => false, 'error' => 'dtf_image_decode_failed'];
    }
    $canvas = imagecreatetruecolor($plan['width_px'], $plan['height_px']);
    if ($canvas === false) {
        imagedestroy($original);
        return ['valid' => false, 'error' => 'dtf_canvas_unavailable'];
    }
    try {
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);
        if (!imagecopyresampled($canvas, $original, 0, 0, 0, 0,
            $plan['width_px'], $plan['height_px'],
            $plan['source_width_px'], $plan['source_height_px'])
            || !imageresolution($canvas, $plan['dpi'], $plan['dpi'])) {
            return ['valid' => false, 'error' => 'dtf_rasterization_failed'];
        }
        ob_start();
        $saved = imagepng($canvas, null, 9);
        $png = ob_get_clean();
        if (!$saved || !is_string($png) || strlen($png) < 64
            || strlen($png) > 33554432) {
            return ['valid' => false, 'error' => 'dtf_png_encode_failed'];
        }
        $check = @getimagesizefromstring($png);
        if (!is_array($check) || $check[0] !== $plan['width_px']
            || $check[1] !== $plan['height_px']
            || ($check['mime'] ?? null) !== 'image/png'
            || ord($png[25]) !== 6
            || !sanro_studio_dtf_png_has_300dpi($png)) {
            return ['valid' => false, 'error' => 'dtf_output_integrity_failed'];
        }
        $metadata = $plan;
        unset($metadata['source_bytes']);
        return $metadata + [
            'bytes' => $png,
            'sha256' => hash('sha256', $png),
            'mime' => 'image/png',
            'kind' => 'image_only_dtf_raster_candidate',
            'quality_approved' => false,
        ];
    } finally {
        imagedestroy($canvas);
        imagedestroy($original);
    }
}
