<?php
/**
 * Offline regression suite for the pure one-order preflight.
 * Run: php tests/staging-one-order-preflight-offline.php
 * No WordPress bootstrap, HTTP, order, database or payment access.
 */
declare(strict_types=1);
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/../');
require_once __DIR__ . '/staging-one-order-preflight.php';

$good = [
    'host' => 'saddlebrown-peafowl-616656.hostingersite.com',
    'environment' => 'staging',
    'user' => 'u606139982',
    'root' => '/home/u606139982/domains/saddlebrown-peafowl-616656.hostingersite.com/public_html',
    'approval' => 'ONE_SYNTHETIC_CHECKOUT_ORDER_APPROVED',
    'mode' => 'create',
    'existing_qa_orders' => 0,
    'currency' => 'IDR',
    'server_quote' => 130000,
    'order_total' => 130000,
    'quantity' => 1,
    'size' => 'M',
    'product_id' => 24,
    'design_id' => '123e4567-e89b-42d3-a456-426614174000',
    'owner_authorized' => true,
    'server_quote_verified' => true,
    'all_items_bound' => true,
    'hp_os_enabled' => true,
    'disable_mail' => true,
    'disable_stock_changes' => true,
    'disable_production_writers' => true,
];

$assertions = 0;
$check = static function (string $label, array $input, bool $expected, ?string $reason = null) use (&$assertions): void {
    $result = sanro_studio_qa_one_order_preflight($input);
    if (($result['valid'] ?? null) !== $expected
        || ($reason !== null && ($result['reason'] ?? null) !== $reason)) {
        fwrite(STDERR, "FAIL: {$label}: " . json_encode($result) . PHP_EOL);
        exit(1);
    }
    ++$assertions;
};

$check('valid preflight (no order)', $good, true, 'preflight_only_no_write');
$withGatewayConfig = $good; $withGatewayConfig['payment_gateways'] = 0;
$check('preflight without gateway configuration', $withGatewayConfig, true, 'preflight_only_no_write');
$withGatewayConfig['payment_gateways'] = 2;
$check('gateway count does not gate Studio checkout', $withGatewayConfig, true, 'preflight_only_no_write');
$check('empty input', [], false, 'wrong_staging_identity');
foreach ([
    'host' => 'kaoscetak.id',
    'environment' => 'production',
    'user' => 'root',
    'root' => '/var/www/html',
] as $key => $bad) {
    $input = $good; $input[$key] = $bad;
    $check("reject {$key}", $input, false, 'wrong_staging_identity');
}
foreach ([
    'approval' => 'NO_APPROVAL',
    'mode' => 'plan',
    'existing_qa_orders' => 1,
] as $key => $bad) {
    $input = $good; $input[$key] = $bad;
    $check("reject {$key}", $input, false, 'approval_or_idempotence_missing');
}
foreach ([
    'currency' => 'USD',
    'server_quote' => 1000,
    'order_total' => 129999,
    'quantity' => 2,
    'size' => 'XL',
    'product_id' => 25,
] as $key => $bad) {
    $input = $good; $input[$key] = $bad;
    $check("reject {$key}", $input, false, 'quote_or_product_mismatch');
}
foreach ([
    'design_id' => '../invalid',
    'owner_authorized' => false,
    'server_quote_verified' => false,
    'all_items_bound' => false,
    'hp_os_enabled' => false,
    'disable_mail' => false,
    'disable_stock_changes' => false,
    'disable_production_writers' => false,
] as $key => $bad) {
    $input = $good; $input[$key] = $bad;
    $check("reject {$key}", $input, false, 'authorization_or_safety_missing');
}
foreach (array_keys($good) as $key) {
    $input = $good; unset($input[$key]);
    $check("missing {$key}", $input, false);
}
echo "PASS: {$assertions} offline one-order preflight assertions (NO ORDER, NO DB, NO PAYMENT)" . PHP_EOL;
