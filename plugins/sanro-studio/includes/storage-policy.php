<?php
defined('ABSPATH') || exit;

/**
 * Validate a lightweight preview before any future private persistence.
 * No upload, filesystem write, public URL or master image storage here.
 */
function sanro_studio_validate_preview_bytes($bytes) {
    if (!is_string($bytes)) return ['valid' => false, 'error' => 'invalid_preview'];
    $length = strlen($bytes);
    if ($length < 100 || $length > 51200) {
        return ['valid' => false, 'error' => 'preview_size'];
    }
    if (!function_exists('getimagesizefromstring')) {
        return ['valid' => false, 'error' => 'image_inspection_unavailable'];
    }
    $image = @getimagesizefromstring($bytes);
    if (!is_array($image) || !isset($image[0], $image[1], $image['mime'])) {
        return ['valid' => false, 'error' => 'invalid_preview'];
    }
    if (!in_array($image['mime'], ['image/png', 'image/jpeg', 'image/webp'], true)
        || $image[0] < 1 || $image[1] < 1 || $image[0] > 2048 || $image[1] > 2048
        || $image[0] * $image[1] > 2097152) {
        return ['valid' => false, 'error' => 'preview_dimensions'];
    }
    return [
        'valid' => true,
        'mime' => $image['mime'],
        'width' => (int) $image[0],
        'height' => (int) $image[1],
        'bytes' => $length,
        'sha256' => hash('sha256', $bytes),
    ];
}

/**
 * Master production folder creation is permitted only after payment is
 * confirmed OR an authorized workflow has marked the order production-ready.
 * This function does not create folders or authorize an actor.
 */
function sanro_studio_production_folder_eligible($payment_confirmed, $production_ready) {
    return $payment_confirmed === true || $production_ready === true;
}
