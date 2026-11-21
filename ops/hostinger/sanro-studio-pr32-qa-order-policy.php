<?php
/**
 * Sprint 142 — pure policy for exactly ONE synthetic WooCommerce order.
 * No WordPress dependencies; also imported by isolated offline tests.
 */
if (!defined('SANRO_STUDIO_S142_STAGE_HOST')) {
    define('SANRO_STUDIO_S142_STAGE_HOST', 'saddlebrown-peafowl-616656.hostingersite.com');
}
if (!defined('SANRO_STUDIO_S142_STAGE_ROOT')) {
    define('SANRO_STUDIO_S142_STAGE_ROOT',
        '/home/u606139982/domains/saddlebrown-peafowl-616656.hostingersite.com/public_html');
}
if (!defined('SANRO_STUDIO_S142_FIXTURE')) {
    define('SANRO_STUDIO_S142_FIXTURE', 'sanro-studio-dtf-admin-qa-order-20261121-v1');
}
function sanro_s142_staging_identity_ok($host, $user, $mode, $realRoot, $home, $site)
{
    $url = 'https://' . SANRO_STUDIO_S142_STAGE_HOST;
    return $host === 'id-dci-web1631'
        && $user === 'u606139982'
        && $mode === 'staging'
        && $realRoot === SANRO_STUDIO_S142_STAGE_ROOT
        && $home === $url && $site === $url;
}
function sanro_s142_valid_design_uuid($value)
{
    return is_string($value)
        && preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD',
            $value) === 1;
}
/**
 * True ONLY for production/print/Drive features whose activation makes a
 * synthetic order riskier. Checkout/editor feature flags are allowed: this
 * CLI creates a WC_Order directly, never enters the WC_Checkout workflow.
 */
function sanro_s142_writer_flags_safe($flags)
{
    if (!is_array($flags)) return false;
    $writers = [
        'SANRO_STUDIO_ENABLE_DTF_IMAGE_RASTER',
        'SANRO_STUDIO_ENABLE_DTF_LAYER_RASTER',
        'SANRO_STUDIO_ENABLE_DTF_PRIVATE_ARTIFACTS',
        'SANRO_STUDIO_ENABLE_DTF_ORDER_BATCH',
        'SANRO_STUDIO_ENABLE_DRIVE_AUTO_PROVISION',
        'SANRO_STUDIO_ENABLE_VERIFIED_DRIVE_ATTACHMENT',
    ];
    foreach ($writers as $name) {
        if (!array_key_exists($name, $flags)
            || $flags[$name] !== false) return false;
    }
    return true;
}

/** WordPress checkout callbacks must not have run in this isolated PHP CLI. */
function sanro_s142_checkout_not_invoked($isCli, $requestMethod, $actionsFired)
{
    if ($isCli !== true || $requestMethod !== 'GET'
        || !is_array($actionsFired)) return false;
    foreach ($actionsFired as $count) {
        if (!is_int($count) || $count !== 0) return false;
    }
    return count($actionsFired) === 4;
}

function sanro_s142_fixture_is_safe($order)
{
    if (!is_object($order)
        || !method_exists($order, 'get_id')
        || !method_exists($order, 'get_meta')
        || !method_exists($order, 'get_status')
        || !method_exists($order, 'get_created_via')
        || !method_exists($order, 'get_total')
        || !method_exists($order, 'get_customer_id')
        || !method_exists($order, 'get_payment_method')
        || !method_exists($order, 'get_transaction_id')
        || !method_exists($order, 'get_items')
        || !method_exists($order, 'is_paid')) return false;
    if ((int) $order->get_id() < 1
        || $order->get_meta('_sanro_studio_qa_fixture', true)
            !== SANRO_STUDIO_S142_FIXTURE
        || $order->get_created_via() !== 'sanro_studio_staging_qa'
        || $order->get_status() !== 'pending'
        || !is_numeric($order->get_total())
        || (float) $order->get_total() !== 0.0
        || (int) $order->get_customer_id() !== 0
        || $order->get_payment_method() !== ''
        || $order->get_transaction_id() !== ''
        || $order->is_paid()) return false;
    $items = $order->get_items('line_item');
    if (!is_array($items) || count($items) !== 1) return false;
    $item = reset($items);
    if (!is_object($item)
        || !method_exists($item, 'get_id')
        || !method_exists($item, 'get_order_id')
        || !method_exists($item, 'get_product_id')
        || !method_exists($item, 'get_quantity')
        || !method_exists($item, 'get_total')
        || !method_exists($item, 'get_subtotal')
        || !method_exists($item, 'get_meta')
        || (int) $item->get_id() < 1
        || (int) $item->get_order_id() !== (int) $order->get_id()
        || (int) $item->get_product_id() !== 0
        || (int) $item->get_quantity() !== 1
        || !is_numeric($item->get_total())
        || !is_numeric($item->get_subtotal())
        || (float) $item->get_total() !== 0.0
        || (float) $item->get_subtotal() !== 0.0
        || !sanro_s142_valid_design_uuid(
            $item->get_meta('_sanro_studio_design_id', true))) {
        return false;
    }
    return true;
}
