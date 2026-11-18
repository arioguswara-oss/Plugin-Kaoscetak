<?php
// Run: php plugins/sanro-studio/tests/cart-contract.php
define('ABSPATH', __DIR__);
require dirname(__DIR__) . '/includes/cart-contract.php';
function verify($ok, $label) {
    if (!$ok) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    echo "PASS: $label\n";
}
function sanro_studio_read_draft($id, $token) {
    if ($token !== 'owner') return ['valid' => false, 'error' => 'not_found'];
    return [
        'valid' => true, 'design_id' => $id, 'product_id' => 12,
        'status' => $id === 'locked' ? 'locked' : 'draft',
        'design' => ['quantities' => ['M' => 2], 'long_sleeve' => false],
    ];
}
function sanro_studio_quote_size_quantities($product, $design, $qty, $sleeve) {
    if ($qty !== ['M' => 2] || $product !== 12) return ['valid' => false, 'error' => 'invalid_quantities'];
    return ['valid' => true, 'total_quantity' => 2, 'total_price' => $GLOBALS['quote_price'] ?? 150000, 'currency' => $GLOBALS['quote_currency'] ?? 'IDR'];
}
verify(sanro_studio_prepare_cart_design_contract('draft', 'bad', 12)['error'] === 'not_found', 'wrong owner rejected');
verify(sanro_studio_prepare_cart_design_contract('locked', 'owner', 12)['error'] === 'draft_locked', 'locked draft rejected');
verify(sanro_studio_prepare_cart_design_contract('draft', 'owner', 13)['error'] === 'product_mismatch', 'product mismatch rejected');
$contract = sanro_studio_prepare_cart_design_contract('draft', 'owner', 12);
verify($contract['valid'] && $contract['quote']['total_price'] === 150000, 'server quote retained');
verify($contract['checkout_enabled'] === false && $contract['cart_mutation_enabled'] === false, 'checkout and cart writes remain disabled');
verify(!isset($contract['owner_token']) && !isset($contract['design']), 'no owner secret or raw design leaked');

$cart = ['product_id' => 12, 'sanro_studio_design_id' => 'draft', 'quantity' => 2];
verify(sanro_studio_validate_cart_design_identity($contract, $cart)['valid'], 'matching cart identity');
$wrongProduct = $cart; $wrongProduct['product_id'] = 13;
verify(sanro_studio_validate_cart_design_identity($contract, $wrongProduct)['error'] === 'cart_identity_mismatch', 'cart product substitution denied');
$wrongDraft = $cart; $wrongDraft['sanro_studio_design_id'] = 'another-draft';
verify(sanro_studio_validate_cart_design_identity($contract, $wrongDraft)['error'] === 'cart_identity_mismatch', 'cart design substitution denied');
$wrongQty = $cart; $wrongQty['quantity'] = 3;
verify(sanro_studio_validate_cart_design_identity($contract, $wrongQty)['error'] === 'cart_quantity_mismatch', 'cart quantity tampering denied');
verify(sanro_studio_validate_cart_design_identity($contract, $cart)['checkout_enabled'] === false, 'cart validation does not enable checkout');

$GLOBALS['quote_price'] = 0;
verify(sanro_studio_prepare_cart_design_contract('draft', 'owner', 12)['error'] === 'invalid_quote', 'zero-price cart quote rejected');
$GLOBALS['quote_price'] = 150000;
$GLOBALS['quote_currency'] = 'USD';
verify(sanro_studio_prepare_cart_design_contract('draft', 'owner', 12)['error'] === 'invalid_quote', 'foreign-currency cart quote rejected');
