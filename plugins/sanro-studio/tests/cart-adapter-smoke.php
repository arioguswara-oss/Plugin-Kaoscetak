<?php
/** Offline staging-cart preflight smoke test; never touches WooCommerce. */
define('ABSPATH', dirname(__DIR__, 3) . '/');
require_once __DIR__ . '/../includes/cart-contract.php';
require_once __DIR__ . '/../includes/cart-adapter.php';
function get_woocommerce_currency() { return 'IDR'; }
function wc_get_product($id) { return new SanroMockProduct(); }
class SanroMockProduct {
    function is_type($type) { return $type === 'simple'; }
    function is_purchasable() { return true; }
}
class SanroMockCart {
    public $added = 0;
    public $contents = [];
    function add_to_cart() { $this->added++; return 'key'; }
    function remove_cart_item($key) { return true; }
    function get_cart() { return $this->contents; }
}
function check_adapter($ok, $message) {
    if (!$ok) { fwrite(STDERR, "FAIL: ".$message."\n"); exit(1); }
}
$contract = ['valid'=>true,'design_id'=>'00000000-0000-4000-8000-000000000000',
    'product_id'=>42,'quote'=>['valid'=>true,'currency'=>'IDR',
    'total_quantity'=>2,'total_price'=>130000,'items'=>[
        'M'=>['quantity'=>2,'unit_price'=>65000,'subtotal'=>130000]]]];
$cart = new SanroMockCart();
$disabled = sanro_studio_stage_add_cart_lines($contract, $cart);
check_adapter($disabled['valid'] === false && $cart->added === 0, 'disabled by default');
define('SANRO_STUDIO_ENABLE_STAGING_CART', true);
$ready = sanro_studio_stage_add_cart_lines($contract, $cart);
check_adapter($ready['valid'] === true && $ready['requires_price_hook'] === true, 'preflight returns candidates');
check_adapter($cart->added === 0 && $ready['cart_mutation_enabled'] === false, 'no premature cart mutation');
$cart->contents = ['existing'=>[]];
check_adapter(sanro_studio_stage_add_cart_lines($contract, $cart)['valid'] === false, 'existing cart rejected');
$cart->contents = [];
$bad = $contract; $bad['quote']['items']['M']['unit_price'] = 1;
check_adapter(sanro_studio_stage_add_cart_lines($bad, $cart)['valid'] === false, 'bad quote rejected');
class SanroPriceProduct {
    public $price = null;
    function set_price($price) { $this->price = $price; }
}
$priceProduct = new SanroPriceProduct();
$line = $ready['lines'][0];
$applied = sanro_studio_stage_price_cart_line($contract, $line, $priceProduct);
check_adapter($applied['valid'] === true && $priceProduct->price === '65000', 'server unit price applied');
$priceProduct = new SanroPriceProduct();
$wrong = $line; $wrong['sanro_studio_unit_price'] = 1;
check_adapter(sanro_studio_stage_price_cart_line($contract, $wrong, $priceProduct)['valid'] === false, 'tampered price refused');
check_adapter($priceProduct->price === null, 'tampered line never mutates price');
$wrong = $line; $wrong['sanro_studio_design_id'] = 'another-design';
check_adapter(sanro_studio_stage_price_cart_line($contract, $wrong, $priceProduct)['valid'] === false, 'cross-design price refused');
$wrong = $line; $wrong['quantity'] = 999;
check_adapter(sanro_studio_stage_price_cart_line($contract, $wrong, $priceProduct)['valid'] === false, 'quantity mismatch refused');
$batchProduct = new SanroPriceProduct();
$batch = [$line + ['data'=>$batchProduct]];
check_adapter(sanro_studio_stage_price_cart_batch($contract, $batch)['valid'] === true, 'complete cart priced');
check_adapter($batchProduct->price === '65000', 'batch applies unit price');
$batchProduct = new SanroPriceProduct();
$badBatch = [$line + ['data'=>$batchProduct]];
$badBatch[0]['sanro_studio_line_subtotal'] = 1;
check_adapter(sanro_studio_stage_price_cart_batch($contract, $badBatch)['valid'] === false, 'tampered batch rejected');
check_adapter($batchProduct->price === null, 'batch rejection does not mutate price');
class SanroTransactionCart {
    public $lines = [];
    public $totals = 0;
    public $failAfter = 0;
    function get_cart() { return $this->lines; }
    function add_to_cart($product, $qty, $variation, $attrs, $meta) {
        if ($this->failAfter > 0 && count($this->lines) >= $this->failAfter) return false;
        $key = 'line-'.count($this->lines);
        $this->lines[$key] = array_merge(['product_id'=>$product, 'quantity'=>$qty,
            'data'=>new SanroPriceProduct()], $meta);
        return $key;
    }
    function remove_cart_item($key) { unset($this->lines[$key]); return true; }
    function calculate_totals() { $this->totals++; }
}
$transaction = new SanroTransactionCart();
check_adapter(sanro_studio_stage_commit_cart_lines($contract, $transaction)['valid'] === false, 'commit disabled separately');
check_adapter(count($transaction->lines) === 0, 'disabled commit does not mutate');
define('SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT', true);
$committed = sanro_studio_stage_commit_cart_lines($contract, $transaction);
check_adapter($committed['valid'] === true && count($transaction->lines) === 1, 'staging cart line inserted');
check_adapter($transaction->lines['line-0']['data']->price === '65000', 'inserted cart line priced');
check_adapter($transaction->totals === 1, 'cart totals recalculated');
$two = $contract;
$two['quote']['items'] = ['M'=>['quantity'=>1,'unit_price'=>65000,'subtotal'=>65000],
    'L'=>['quantity'=>1,'unit_price'=>65000,'subtotal'=>65000]];
$failed = new SanroTransactionCart(); $failed->failAfter = 1;
check_adapter(sanro_studio_stage_commit_cart_lines($two, $failed)['valid'] === false, 'partial add failure detected');
check_adapter(count($failed->lines) === 0, 'partial add rolled back');
echo "PASS: 21 staging cart adapter checks\n";
