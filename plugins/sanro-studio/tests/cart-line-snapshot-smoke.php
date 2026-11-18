<?php
/** Isolated cart contract regression: no WordPress bootstrap or live writes. */
define('ABSPATH', dirname(__DIR__, 3) . '/');
require_once __DIR__ . '/../includes/cart-contract.php';
function check_cart($condition, $label) {
    if (!$condition) { fwrite(STDERR, "FAIL: ".$label."\n"); exit(1); }
}
$id = '00000000-0000-4000-8000-000000000000';
$contract = ['valid'=>true,'design_id'=>$id,'product_id'=>42,
    'quote'=>['valid'=>true,'currency'=>'IDR','total_quantity'=>2,'total_price'=>100000]];
$line = sanro_studio_prepare_cart_line_snapshot($contract);
check_cart(($line['valid'] ?? false) === true, 'valid cart snapshot');
check_cart(!isset($line['owner_token'], $line['image_base64']), 'no owner credentials');
check_cart($line['cart_mutation_enabled'] === false, 'mutation disabled');
check_cart(sanro_studio_validate_cart_design_identity($contract, $line)['valid'] === true, 'identity and quote match');
$bad = $line; $bad['sanro_studio_quote_total'] = 1;
check_cart(sanro_studio_validate_cart_design_identity($contract, $bad)['valid'] === false, 'tampered total rejected');
$bad = $line; $bad['sanro_studio_quote_currency'] = 'USD';
check_cart(sanro_studio_validate_cart_design_identity($contract, $bad)['valid'] === false, 'tampered currency rejected');
$bad = $line; $bad['quantity'] = 3;
check_cart(sanro_studio_validate_cart_design_identity($contract, $bad)['valid'] === false, 'tampered quantity rejected');
$bad = $contract; $bad['design_id'] = 'not-uuid';
check_cart(sanro_studio_prepare_cart_line_snapshot($bad)['valid'] === false, 'invalid design id rejected');
$bad = $contract; $bad['quote']['total_price'] = 0;
check_cart(sanro_studio_prepare_cart_line_snapshot($bad)['valid'] === false, 'zero quote rejected');
$priced = $contract;
$priced['quote']['items'] = [
    'M' => ['quantity'=>1,'unit_price'=>45000,'subtotal'=>45000],
    'L' => ['quantity'=>1,'unit_price'=>55000,'subtotal'=>55000],
];
$sizeLines = sanro_studio_prepare_size_cart_lines($priced);
check_cart(($sizeLines['valid'] ?? false) === true && count($sizeLines['lines']) === 2, 'two size lines created');
check_cart($sizeLines['lines'][0]['sanro_studio_unit_price'] === 45000, 'unit price not total');
check_cart($sizeLines['total_price'] === 100000, 'sum matches quote');
$bad = $priced; $bad['quote']['items']['L']['subtotal'] = 1;
check_cart(sanro_studio_prepare_size_cart_lines($bad)['valid'] === false, 'tampered size subtotal rejected');
$bad = $priced; $bad['quote']['items']['L']['quantity'] = 2; $bad['quote']['items']['L']['subtotal'] = 110000;
check_cart(sanro_studio_prepare_size_cart_lines($bad)['valid'] === false, 'tampered aggregate rejected');
echo "PASS: 14 isolated cart-line checks\n";
