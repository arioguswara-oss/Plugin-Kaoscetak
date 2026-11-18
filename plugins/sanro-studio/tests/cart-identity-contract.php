<?php
/** Isolated, read-only cart identity regression. No WooCommerce writes. */
define('ABSPATH', __DIR__);
require dirname(__DIR__) . '/includes/cart-contract.php';
$contract = [
    'valid' => true,
    'design_id' => '11111111-1111-4111-8111-111111111111',
    'product_id' => 42,
    'quote' => ['total_quantity' => 3, 'total_price' => 90000, 'currency' => 'IDR'],
];
$item = ['product_id' => 42, 'sanro_studio_design_id' => $contract['design_id'], 'quantity' => 3];
$cases = [
    'valid_identity' => [$contract, $item, true, null],
    'wrong_product' => [$contract, array_merge($item, ['product_id' => 43]), false, 'cart_identity_mismatch'],
    'wrong_design' => [$contract, array_merge($item, ['sanro_studio_design_id' => 'forged']), false, 'cart_identity_mismatch'],
    'wrong_quantity' => [$contract, array_merge($item, ['quantity' => 4]), false, 'cart_quantity_mismatch'],
    'string_quantity' => [$contract, array_merge($item, ['quantity' => '3']), false, 'cart_quantity_mismatch'],
    'missing_identity' => [$contract, ['product_id' => 42, 'quantity' => 3], false, 'cart_identity_mismatch'],
    'invalid_contract' => [array_merge($contract, ['valid' => false]), $item, false, 'cart_identity_mismatch'],
];
foreach ($cases as $name => [$input, $cart, $valid, $error]) {
    $result = sanro_studio_validate_cart_design_identity($input, $cart);
    if (($result['valid'] ?? false) !== $valid || (!$valid && ($result['error'] ?? null) !== $error)
        || ($result['checkout_enabled'] ?? false) !== false) {
        fwrite(STDERR, "FAIL: $name\n");
        exit(1);
    }
    echo "PASS: $name\n";
}

$totals_cases = [
    'valid_total' => ['IDR', 90000, true],
    'price_tampered' => ['IDR', 1, false],
    'currency_tampered' => ['USD', 90000, false],
    'string_total' => ['IDR', '90000', false],
    'negative_total' => ['IDR', -90000, false],
];
foreach ($totals_cases as $name => [$currency, $total, $expected]) {
    $result = sanro_studio_validate_checkout_totals($contract, $item, $currency, $total);
    if (($result['valid'] ?? false) !== $expected
        || (!$expected && ($result['error'] ?? null) !== 'checkout_total_mismatch')
        || ($result['checkout_enabled'] ?? false) !== false) {
        fwrite(STDERR, "FAIL: $name\n");
        exit(1);
    }
    echo "PASS: $name\n";
}
