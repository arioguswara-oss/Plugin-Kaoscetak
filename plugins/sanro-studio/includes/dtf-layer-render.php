<?php
defined('ABSPATH') || exit;

/**
 * Experimental, server-only SANRO Studio layered DTF raster candidate.
 * The buyer currently supports at most ONE image and ONE text object/side.
 * No public route, file write, Drive upload, or production-ready claim.
 */
function sanro_studio_dtf_layer_enabled() {
    return function_exists('sanro_studio_dtf_image_render_enabled')
        && sanro_studio_dtf_image_render_enabled()
        && defined('SANRO_STUDIO_ENABLE_DTF_LAYER_RASTER')
        && SANRO_STUDIO_ENABLE_DTF_LAYER_RASTER === true;
}

/** Trusted, local, private TrueType font. NEVER accept path from buyer/order. */
function sanro_studio_dtf_private_font() {
    if (!defined('SANRO_STUDIO_DTF_FONT_PATH')
        || !is_string(SANRO_STUDIO_DTF_FONT_PATH)
        || !preg_match('/\.ttf$/iD', SANRO_STUDIO_DTF_FONT_PATH)
        || is_link(SANRO_STUDIO_DTF_FONT_PATH)) return false;
    $path = realpath(SANRO_STUDIO_DTF_FONT_PATH);
    if (!$path || !is_file($path) || !is_readable($path)
        || is_link($path)) return false;
    $size = @filesize($path);
    if (!is_int($size) || $size < 1024 || $size > 16777216) return false;
    foreach ([
        defined('ABSPATH') ? realpath(ABSPATH) : false,
        isset($_SERVER['DOCUMENT_ROOT']) && is_string($_SERVER['DOCUMENT_ROOT'])
            ? realpath($_SERVER['DOCUMENT_ROOT']) : false,
    ] as $root) {
        if (!$root || $path === $root
            || strpos($path, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) === 0) {
            return false;
        }
    }
    return $path;
}

/**
 * One image + one single-line ASCII text object per side, never more.
 * The final canvas is the union of both boxes in physical centimeters.
 * Unknown fonts, multi-line text and complex Unicode require a later parity
 * renderer; rejecting them avoids dropping buyer text from print artwork.
 */
function sanro_studio_dtf_layer_plan($order, $item_id, $side) {
    if (!sanro_studio_dtf_layer_enabled()) {
        return ['valid' => false, 'error' => 'layer_render_disabled'];
    }
    if (!is_object($order) || !method_exists($order, 'get_id')
        || !method_exists($order, 'get_items')
        || !is_int($item_id) || $item_id < 1
        || !in_array($side, ['front', 'back'], true)
        || !function_exists('get_option')
        || !function_exists('sanro_studio_auto_drive_order_eligible')
        || !sanro_studio_auto_drive_order_eligible($order)
        || !function_exists('sanro_studio_locked_record_has_item')
        || !function_exists('sanro_studio_product_safe_areas')
        || !function_exists('sanro_studio_validate_product_design')) {
        return ['valid' => false, 'error' => 'layer_runtime_unavailable'];
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
        return ['valid' => true, 'skipped' => true, 'reason' => 'blank_side'];
    }
    $entry = $design[$side];
    $objects = $entry['objects'] ?? null;
    if (!is_array($objects) || count($objects) > 2) {
        return ['valid' => false, 'error' => 'invalid_layers'];
    }
    if (!$objects) return ['valid' => true, 'skipped' => true, 'reason' => 'blank_side'];

    $image = null; $text = null;
    $min_x = $min_y = INF; $max_x = $max_y = -INF;
    foreach ($objects as $obj) {
        if (!is_array($obj) || !isset($obj['type'])
            || !in_array($obj['type'], ['image', 'text'], true)) {
            return ['valid' => false, 'error' => 'invalid_layers'];
        }
        if ($obj['type'] === 'image') {
            if ($image !== null) return ['valid' => false, 'error' => 'invalid_layers'];
            $image = $obj;
        } else {
            if ($text !== null) return ['valid' => false, 'error' => 'invalid_layers'];
            $text = $obj;
        }
        foreach (['x','y','width','height'] as $field) {
            if (!isset($obj[$field]) || !is_numeric($obj[$field])
                || !is_finite((float) $obj[$field])) {
                return ['valid' => false, 'error' => 'invalid_print_geometry'];
            }
        }
        $x = (float) $obj['x']; $y = (float) $obj['y'];
        $w = (float) $obj['width']; $h = (float) $obj['height'];
        if ($x < 0 || $y < 0 || $w <= 0 || $h <= 0) {
            return ['valid' => false, 'error' => 'invalid_print_geometry'];
        }
        $min_x = min($min_x, $x); $min_y = min($min_y, $y);
        $max_x = max($max_x, $x + $w); $max_y = max($max_y, $y + $h);
    }
    if (($text === null && array_key_exists('text', $entry))
        || ($text !== null && !isset($entry['text']))) {
        return ['valid' => false, 'error' => 'text_binding_missing'];
    }
    $dpi = 300;
    $to_px = static function ($cm) use ($dpi) {
        return (int) round($cm * $dpi / 2.54);
    };
    $width_px = $to_px($max_x - $min_x);
    $height_px = $to_px($max_y - $min_y);
    if ($width_px < 1 || $height_px < 1 || $width_px > 4096
        || $height_px > 4096 || $width_px * $height_px > 6000000) {
        return ['valid' => false, 'error' => 'dtf_canvas_limit'];
    }
    $plan = [
        'valid' => true, 'skipped' => false,
        'kind' => 'layered_dtf_raster_candidate',
        'quality_approved' => false,
        'font_parity_approved' => false,
        'dpi' => $dpi, 'width_px' => $width_px, 'height_px' => $height_px,
        'width_cm' => $max_x - $min_x, 'height_cm' => $max_y - $min_y,
        'offset_x_cm' => $min_x, 'offset_y_cm' => $min_y,
        'design_id' => $design_id, 'order_id' => $order->get_id(),
        'item_id' => $item_id, 'side' => $side,
        'image' => null, 'text_layer' => null,
    ];

    if ($image !== null) {
        if (!function_exists('sanro_studio_locked_item_master_source')) {
            return ['valid' => false, 'error' => 'master_reader_unavailable'];
        }
        $source = sanro_studio_locked_item_master_source($order, $item_id, $side);
        if (empty($source['valid']) || !empty($source['skipped'])
            || !is_string($source['bytes'] ?? null)
            || !is_string($source['sha256'] ?? null)) {
            return ['valid' => false, 'error' => 'dtf_master_unavailable'];
        }
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
        $target_w = $to_px((float) $image['width']);
        $target_h = $to_px((float) $image['height']);
        if (!is_array($info) || ($info['mime'] ?? null) !== 'image/png'
            || $target_w < 1 || $target_h < 1
            || $info[0] < $target_w || $info[1] < $target_h
            || $info[0] > 10000 || $info[1] > 10000
            || $info[0] * $info[1] > 12000000) {
            return ['valid' => false, 'error' => 'dtf_source_resolution'];
        }
        if (abs(($info[0] / $info[1]) /
            ((float) $image['width'] / (float) $image['height']) - 1) > 0.015) {
            return ['valid' => false, 'error' => 'dtf_source_aspect_mismatch'];
        }
        if (!hash_equals(hash('sha256', $bytes), $source['sha256'])) {
            return ['valid' => false, 'error' => 'dtf_source_integrity_failed'];
        }
        $plan['image'] = [
            'x' => $to_px((float) $image['x'] - $min_x),
            'y' => $to_px((float) $image['y'] - $min_y),
            'width' => $target_w, 'height' => $target_h,
            'source_w' => $info[0], 'source_h' => $info[1],
            'sha256' => $source['sha256'], 'bytes' => $bytes,
        ];
    }
    if ($text !== null) {
        $value = $entry['text'];
        // v1 intentionally excludes multiline, Unicode shaping and wrap:
        // these require browser/server font parity approval before printing.
        if (!is_string($value) || !preg_match('/^[\x20-\x7e]{1,160}$/D', $value)
            || trim($value) === ''
            || !isset($entry['font_size']) || !is_int($entry['font_size'])
            || $entry['font_size'] < 10 || $entry['font_size'] > 48
            || !isset($entry['text_color']) || !is_string($entry['text_color'])
            || !preg_match('/^#[0-9a-fA-F]{6}$/D', $entry['text_color'])) {
            return ['valid' => false, 'error' => 'dtf_text_layout_unsupported'];
        }
        if (!sanro_studio_dtf_private_font()) {
            return ['valid' => false, 'error' => 'dtf_private_font_required'];
        }
        $plan['text_layer'] = [
            'x' => $to_px((float) $text['x'] - $min_x),
            'y' => $to_px((float) $text['y'] - $min_y),
            'width' => $to_px((float) $text['width']),
            'height' => $to_px((float) $text['height']),
            'text' => $value, 'color' => $entry['text_color'],
        ];
    }
    return $plan;
}

/** Return a *candidate* PNG only. Text always overlays the image per buyer DOM. */
function sanro_studio_dtf_render_layer_side($order, $item_id, $side) {
    $plan = sanro_studio_dtf_layer_plan($order, $item_id, $side);
    if (empty($plan['valid']) || !empty($plan['skipped'])) return $plan;
    foreach (['imagecreatetruecolor','imagealphablending','imagesavealpha',
        'imagecolorallocatealpha','imagefill','imagecopyresampled','imagecreatefromstring',
        'imageresolution','imagepng','imagedestroy','imagettfbbox','imagettftext'] as $name) {
        if (!function_exists($name)) return ['valid' => false, 'error' => 'dtf_gd_unavailable'];
    }
    $canvas = imagecreatetruecolor($plan['width_px'], $plan['height_px']);
    if ($canvas === false) return ['valid' => false, 'error' => 'dtf_canvas_unavailable'];
    try {
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagealphablending($canvas, true);
        if ($plan['image'] !== null) {
            $i = $plan['image'];
            $source = @imagecreatefromstring($i['bytes']);
            if ($source === false) return ['valid' => false, 'error' => 'dtf_image_decode_failed'];
            try {
                if (!imagecopyresampled($canvas, $source,
                    $i['x'], $i['y'], 0, 0, $i['width'], $i['height'],
                    $i['source_w'], $i['source_h'])) {
                    return ['valid' => false, 'error' => 'dtf_image_composite_failed'];
                }
            } finally {
                imagedestroy($source);
            }
        }
        if ($plan['text_layer'] !== null) {
            $t = $plan['text_layer'];
            $font = sanro_studio_dtf_private_font();
            if (!$font || $t['width'] < 2 || $t['height'] < 2) {
                return ['valid' => false, 'error' => 'dtf_private_font_required'];
            }
            $font_size = 0; $bbox = null;
            // Fit the trusted server font to the buyer's measured physical
            // bounding rectangle. This is not a claim of exact browser parity.
            for ($size = min(256, $t['height'] * 2); $size >= 1; $size--) {
                $box = @imagettfbbox($size, 0, $font, $t['text']);
                if (!is_array($box) || count($box) < 8) continue;
                $xs = [$box[0],$box[2],$box[4],$box[6]];
                $ys = [$box[1],$box[3],$box[5],$box[7]];
                if (max($xs) - min($xs) <= $t['width'] - 1
                    && max($ys) - min($ys) <= $t['height'] - 1) {
                    $font_size = $size;
                    $bbox = ['min_x' => min($xs), 'min_y' => min($ys)];
                    break;
                }
            }
            if ($font_size < 1 || $bbox === null) {
                return ['valid' => false, 'error' => 'dtf_text_does_not_fit'];
            }
            $rgb = sscanf(substr($t['color'], 1), '%02x%02x%02x');
            if (!is_array($rgb) || count($rgb) !== 3) {
                return ['valid' => false, 'error' => 'dtf_text_color_invalid'];
            }
            $color = imagecolorallocatealpha($canvas, $rgb[0], $rgb[1], $rgb[2], 0);
            $draw = @imagettftext($canvas, $font_size, 0,
                $t['x'] - $bbox['min_x'], $t['y'] - $bbox['min_y'],
                $color, $font, $t['text']);
            if (!is_array($draw)) {
                return ['valid' => false, 'error' => 'dtf_text_draw_failed'];
            }
        }
        if (!imageresolution($canvas, 300, 300)) {
            return ['valid' => false, 'error' => 'dtf_dpi_set_failed'];
        }
        imagealphablending($canvas, false);
        ob_start();
        $ok = imagepng($canvas, null, 9);
        $bytes = ob_get_clean();
        if (!$ok || !is_string($bytes) || strlen($bytes) < 64
            || strlen($bytes) > 33554432) {
            return ['valid' => false, 'error' => 'dtf_png_encode_failed'];
        }
        $info = @getimagesizefromstring($bytes);
        if (!is_array($info) || $info[0] !== $plan['width_px']
            || $info[1] !== $plan['height_px']
            || ($info['mime'] ?? null) !== 'image/png'
            || ord($bytes[25]) !== 6
            || !function_exists('sanro_studio_dtf_png_has_300dpi')
            || !sanro_studio_dtf_png_has_300dpi($bytes)) {
            return ['valid' => false, 'error' => 'dtf_output_integrity_failed'];
        }
        unset($plan['image'], $plan['text_layer']);
        return $plan + ['bytes' => $bytes, 'sha256' => hash('sha256', $bytes),
            'mime' => 'image/png'];
    } finally {
        imagedestroy($canvas);
    }
}
