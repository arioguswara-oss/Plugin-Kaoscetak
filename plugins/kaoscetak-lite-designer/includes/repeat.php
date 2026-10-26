<?php
/**
 * Server-authoritative repeat-customer discount.
 * Business rule: 5% per piece, including a single item, only when the current
 * checkout email is the verified design owner AND a prior paid order exists.
 */
if (!defined('ABSPATH')) exit;

function kld_repeat_verified_email($design_id) {
    $email = sanitize_email((string) get_post_meta(absint($design_id), '_kld_owner_email', true));
    return is_email($email) ? strtolower($email) : '';
}

/** Only paid, non-refunded orders qualify; draft/pending/failed orders do not. */
function kld_repeat_has_paid_order($email) {
    static $checked = [];
    $email = strtolower(sanitize_email((string) $email));
    if (!is_email($email) || !function_exists('wc_get_orders')) return false;
    if (array_key_exists($email, $checked)) return $checked[$email];
    $statuses = function_exists('wc_get_is_paid_statuses') ? wc_get_is_paid_statuses() : ['processing', 'completed'];
    if (!$statuses) return $checked[$email] = false;
    $ids = wc_get_orders([
        'billing_email' => $email,
        'status' => $statuses,
        'limit' => 1,
        'return' => 'ids',
    ]);
    return $checked[$email] = is_array($ids) && !empty($ids);
}

function kld_repeat_eligible($design_id, $billing_email) {
    $verified = kld_repeat_verified_email($design_id);
    $billing = strtolower(sanitize_email((string) $billing_email));
    return $verified !== '' && $billing !== ''
        && hash_equals($verified, $billing)
        && kld_repeat_has_paid_order($billing);
}

function kld_repeat_unit_price($design_id, $base_price, $billing_email) {
    $base = (int) $base_price;
    if ($base <= 0 || !kld_repeat_eligible($design_id, $billing_email)) return $base;
    // Integer rupiah: deterministic arithmetic avoids float rounding errors.
    return $base - (int) round($base * 5 / 100, 0, PHP_ROUND_HALF_UP);
}
