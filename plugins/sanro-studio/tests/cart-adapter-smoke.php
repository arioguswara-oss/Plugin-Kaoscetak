<?php
/** Offline staging-cart preflight smoke test; never touches WooCommerce. */
define('ABSPATH', dirname(__DIR__, 3) . '/');
require_once __DIR__ . '/../includes/cart-contract.php';
require_once __DIR__ . '/../includes/cart-adapter.php';
function get_woocommerce_currency() { return 'IDR'; }
function wp_salt($scheme) { return 'sanro-test-signing-key-00000000000000'; }
function wc_get_product($id) { return new SanroMockProduct(); }
class SanroMockProduct {
    function is_type($type) { return $type === 'simple'; }
    function is_purchasable() { return true; }
}
class SanroMockCart {
    public $added = 0;
    public $contents = [];
    public $throwOnRead = false;
    public $nonArrayOnRead = false;
    function add_to_cart() { $this->added++; return 'key'; }
    function remove_cart_item($key) { return true; }
    function get_cart() {
        if ($this->throwOnRead) throw new RuntimeException('synthetic cart session failure');
        return $this->nonArrayOnRead ? null : $this->contents;
    }
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
$cart->throwOnRead = true;
$unreadable = sanro_studio_stage_add_cart_lines($contract, $cart);
check_adapter(($unreadable['valid'] ?? null) === false
    && ($unreadable['error'] ?? null) === 'cart_unavailable',
    'cart preflight exception fails closed');
check_adapter($cart->added === 0, 'cart preflight exception never adds items');
$cart->throwOnRead = false;
$cart->nonArrayOnRead = true;
$malformed = sanro_studio_stage_add_cart_lines($contract, $cart);
check_adapter(($malformed['valid'] ?? null) === false
    && ($malformed['error'] ?? null) === 'cart_unavailable',
    'malformed WooCommerce cart fails closed');
check_adapter($cart->added === 0, 'malformed cart preflight never adds items');
$cart->nonArrayOnRead = false;
$bad = $contract; $bad['quote']['items']['M']['unit_price'] = 1;
check_adapter(sanro_studio_stage_add_cart_lines($bad, $cart)['valid'] === false, 'bad quote rejected');
class SanroPriceProduct {
    public $price = null;
    function set_price($price) { $this->price = $price; }
    function get_price() { return $this->price; }
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
    public $totalsMode = 'normal';
    public $throwAfterInsert = false;
    public $failRollback = false;
    function get_cart() { return $this->lines; }
    function add_to_cart($product, $qty, $variation, $attrs, $meta) {
        if ($this->failAfter > 0 && count($this->lines) >= $this->failAfter) return false;
        $key = 'line-'.count($this->lines);
        $this->lines[$key] = array_merge(['product_id'=>$product, 'quantity'=>$qty,
            'data'=>new SanroPriceProduct()], $meta);
        if ($this->throwAfterInsert) throw new RuntimeException('synthetic hook threw after insert');
        return $key;
    }
    function remove_cart_item($key) {
        if ($this->failRollback) return false;
        unset($this->lines[$key]);
        return true;
    }
    function calculate_totals() {
        $this->totals++;
        if ($this->totalsMode === 'throw') throw new RuntimeException('synthetic pricing hook failure');
        if ($this->totalsMode === 'price_drift') {
            foreach ($this->lines as &$line) $line['data']->price = '1';
            unset($line);
        }
        if ($this->totalsMode === 'quantity_drift') {
            foreach ($this->lines as &$line) $line['quantity']++;
            unset($line);
        }
    }
}
$transaction = new SanroTransactionCart();
check_adapter(sanro_studio_stage_commit_cart_lines($contract, $transaction)['valid'] === false, 'commit disabled separately');
check_adapter(count($transaction->lines) === 0, 'disabled commit does not mutate');
define('SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT', true);
// Checkout must remain fail-closed whenever cart mutation is enabled.
define('SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK', true);
$committed = sanro_studio_stage_commit_cart_lines($contract, $transaction);
check_adapter($committed['valid'] === true && count($transaction->lines) === 1, 'staging cart line inserted');
check_adapter($transaction->lines['line-0']['data']->price === '65000', 'inserted cart line priced');
check_adapter($transaction->totals === 1, 'cart totals recalculated');
check_adapter(isset($transaction->lines['line-0']['_sanro_qa_expires'],
    $transaction->lines['line-0']['_sanro_qa_signature']),
    'commit includes signed short-lived server quote');
$signed = $transaction->lines['line-0'];
check_adapter(!isset($signed['owner_token']) && !isset($signed['sanro_studio_owner_token']),
    'no draft owner token persisted with signed cart');
check_adapter(sanro_studio_stage_cart_signature($signed, $signed['_sanro_qa_expires'])
    === $signed['_sanro_qa_signature'], 'server quote signature verifies');
$transaction->lines['line-0']['data']->price = '76000.00';
sanro_studio_stage_reprice_signed_cart($transaction);
check_adapter($transaction->lines['line-0']['data']->get_price() === '65000',
    'Woo session catalog price restored to authorized unit price');
$tamperedPrice = new SanroTransactionCart();
$tamperedPrice->lines['line-0'] = $signed;
$tamperedPrice->lines['line-0']['sanro_studio_unit_price'] = 1;
sanro_studio_stage_reprice_signed_cart($tamperedPrice);
check_adapter(count($tamperedPrice->lines) === 0, 'forged quote price removed');
$tamperedQty = new SanroTransactionCart();
$tamperedQty->lines['line-0'] = $signed;
$tamperedQty->lines['line-0']['quantity'] = 3;
sanro_studio_stage_reprice_signed_cart($tamperedQty);
check_adapter(count($tamperedQty->lines) === 0, 'modified size quantity removed');
$expired = new SanroTransactionCart();
$expired->lines['line-0'] = $signed;
$expired->lines['line-0']['_sanro_qa_expires'] = time() - 60;
$expired->lines['line-0']['_sanro_qa_signature'] =
    sanro_studio_stage_cart_signature($signed, $expired->lines['line-0']['_sanro_qa_expires']);
sanro_studio_stage_reprice_signed_cart($expired);
check_adapter(count($expired->lines) === 0, 'expired signed quote removed');
$unsigned = new SanroTransactionCart();
$unsigned->lines['line-0'] = $signed;
unset($unsigned->lines['line-0']['_sanro_qa_signature']);
sanro_studio_stage_reprice_signed_cart($unsigned);
check_adapter(count($unsigned->lines) === 0, 'unsigned Studio line removed');
$regular = new SanroTransactionCart();
$regular->lines['ordinary'] = ['product_id'=>9, 'quantity'=>1,
    'data'=>new SanroPriceProduct()];
sanro_studio_stage_reprice_signed_cart($regular);
check_adapter(count($regular->lines) === 1, 'ordinary Woo cart line untouched');
$wrongDesign = new SanroTransactionCart();
$wrongDesign->lines['line-0'] = $signed;
$wrongDesign->lines['line-0']['sanro_studio_design_id'] = 'another-design';
sanro_studio_stage_reprice_signed_cart($wrongDesign);
check_adapter(count($wrongDesign->lines) === 0, 'cross-design quote replay removed');
$existingMeta = [['key'=>'Asal','value'=>'Tersimpan']];
$visible = sanro_studio_stage_cart_item_display($existingMeta, $signed);
check_adapter(count($visible) === 3, 'signed Studio cart shows size and design');
check_adapter($visible[0] === $existingMeta[0], 'existing Woo item data preserved');
check_adapter(($visible[1]['value'] ?? null) === 'M', 'buyer sees verified size M');
check_adapter(($visible[2]['value'] ?? null) === '00000000', 'buyer sees short design reference');
check_adapter(strpos(json_encode($visible), 'owner') === false,
    'cart presentation never includes owner credential');
check_adapter(sanro_studio_stage_cart_item_display([], ['product_id'=>42]) === [],
    'ordinary Woo product has no Studio metadata');
$unsignedDisplay = $signed;
unset($unsignedDisplay['_sanro_qa_signature']);
check_adapter(sanro_studio_stage_cart_item_display([], $unsignedDisplay) === [],
    'unsigned Studio cart details hidden');
$expiredDisplay = $signed;
$expiredDisplay['_sanro_qa_expires'] = time()-60;
$expiredDisplay['_sanro_qa_signature'] =
    sanro_studio_stage_cart_signature($signed, $expiredDisplay['_sanro_qa_expires']);
check_adapter(sanro_studio_stage_cart_item_display([], $expiredDisplay) === [],
    'expired Studio cart details hidden');
$forgedDisplay = $signed;
$forgedDisplay['sanro_studio_size'] = 'XL';
check_adapter(sanro_studio_stage_cart_item_display([], $forgedDisplay) === [],
    'forged size never appears in cart or checkout');
check_adapter(sanro_studio_stage_cart_item_display('unavailable', $signed) === 'unavailable',
    'invalid upstream Woo item-data container preserved');
$two = $contract;
$two['quote']['items'] = ['M'=>['quantity'=>1,'unit_price'=>65000,'subtotal'=>65000],
    'L'=>['quantity'=>1,'unit_price'=>65000,'subtotal'=>65000]];
$failed = new SanroTransactionCart(); $failed->failAfter = 1;
check_adapter(sanro_studio_stage_commit_cart_lines($two, $failed)['valid'] === false, 'partial add failure detected');
check_adapter(count($failed->lines) === 0, 'partial add rolled back');
$drift = new SanroTransactionCart();
$drift->totalsMode = 'price_drift';
check_adapter(sanro_studio_stage_commit_cart_lines($contract, $drift)['valid'] === false,
    'third-party price drift after calculate_totals rejected');
check_adapter(count($drift->lines) === 0, 'price drift rolls back every new line');
$quantityDrift = new SanroTransactionCart();
$quantityDrift->totalsMode = 'quantity_drift';
check_adapter(sanro_studio_stage_commit_cart_lines($contract, $quantityDrift)['valid'] === false,
    'quantity drift after totals rejected');
check_adapter(count($quantityDrift->lines) === 0, 'quantity drift rolls back');
$throws = new SanroTransactionCart();
$throws->totalsMode = 'throw';
check_adapter(sanro_studio_stage_commit_cart_lines($contract, $throws)['valid'] === false,
    'pricing hook exception converted to fail-closed result');
check_adapter(count($throws->lines) === 0, 'exception rolls back all inserted lines');
$midInsert = new SanroTransactionCart();
$midInsert->throwAfterInsert = true;
check_adapter(sanro_studio_stage_commit_cart_lines($contract, $midInsert)['valid'] === false,
    'hook exception after insertion fails closed');
check_adapter(count($midInsert->lines) === 0,
    'untracked WooCommerce line removed after mid-insert exception');
$rollbackFailed = new SanroTransactionCart();
$rollbackFailed->totalsMode = 'throw';
$rollbackFailed->failRollback = true;
$unsafe = sanro_studio_stage_commit_cart_lines($contract, $rollbackFailed);
check_adapter(($unsafe['valid'] ?? null) === false
    && ($unsafe['error'] ?? null) === 'cart_rollback_incomplete',
    'incomplete rollback explicitly rejected');
check_adapter(count($rollbackFailed->lines) === 1,
    'staging guard reports failure without masking leftover cart entry');
$twoSuccess = new SanroTransactionCart();
$multi = sanro_studio_stage_commit_cart_lines($two, $twoSuccess);
check_adapter($multi['valid'] === true && count($twoSuccess->lines) === 2,
    'two size lines committed with distinct cart keys');
check_adapter($twoSuccess->lines['line-0']['data']->get_price() === '65000'
    && $twoSuccess->lines['line-1']['data']->get_price() === '65000',
    'both unit prices verified after WooCommerce totals');
function sanro_studio_read_draft($id, $token) {
    global $contract;
    if ($token !== 'valid-owner-token' || $id !== $contract['design_id']) {
        return ['valid'=>false, 'error'=>'not_authorized'];
    }
    return ['valid'=>true, 'status'=>'draft', 'design_id'=>$id,
        'product_id'=>$contract['product_id'],
        'design'=>['quantities'=>['M'=>2], 'long_sleeve'=>false],
        'master_refs'=>[]];
}
function sanro_studio_validate_product_design($product, $design) {
    return ['valid'=>true];
}
function sanro_studio_draft_image_binding_allowed($design, $refs, $id) {
    return true;
}
function sanro_studio_quote_size_quantities($product, $design, $quantities, $longSleeve) {
    global $contract;
    return $contract['quote'];
}
$restored = new SanroTransactionCart();
$restored->add_to_cart(42, 2, 0, [], [
    'sanro_studio_design_id'=>$contract['design_id'], 'sanro_studio_size'=>'M',
    'sanro_studio_unit_price'=>65000, 'sanro_studio_line_subtotal'=>130000,
    'sanro_studio_quote_currency'=>'IDR']);
check_adapter(sanro_studio_stage_reauthorize_cart($contract['design_id'], 'bad', 42, $restored)['valid'] === false, 'wrong owner rejected');
check_adapter($restored->totals === 0, 'wrong owner never recalculates totals');
check_adapter(sanro_studio_stage_reauthorize_cart($contract['design_id'], 'valid-owner-token', 42, $restored)['valid'] === true, 'valid owner restores cart');
check_adapter($restored->totals === 1, 'restored cart recalculates totals');
$restored->lines['line-0']['sanro_studio_unit_price'] = 1;
check_adapter(sanro_studio_stage_reauthorize_cart($contract['design_id'], 'valid-owner-token', 42, $restored)['valid'] === false, 'tampered restored cart rejected');
$restored->lines['line-0']['sanro_studio_unit_price'] = 65000;
$restored->lines['line-0']['data']->price = '65000.00';
check_adapter(sanro_studio_stage_verify_cart_prices($contract, $restored)['valid'] === true,
    'canonical two-decimal server unit price accepted');
$restored->totalsMode = 'price_drift';
check_adapter(sanro_studio_stage_reauthorize_cart($contract['design_id'], 'valid-owner-token', 42, $restored)['valid'] === false,
    'session reload price drift cannot authorize cart');
// Restore canonical unit price before testing a *different* failure mode.
// Otherwise the earlier price_drift would make every later test trivially fail.
$restored->lines['line-0']['data']->price = '65000';
$restored->totalsMode = 'throw';
$exceptionResult = sanro_studio_stage_reauthorize_cart(
    $contract['design_id'], 'valid-owner-token', 42, $restored
);
check_adapter(($exceptionResult['valid'] ?? null) === false
    && ($exceptionResult['error'] ?? null) === 'cart_reprice_failed',
    'session reload exception fails closed with expected error');
$restored->totalsMode = 'quantity_drift';
$restored->lines['line-0']['quantity'] = 2;
$quantityResult = sanro_studio_stage_reauthorize_cart(
    $contract['design_id'], 'valid-owner-token', 42, $restored
);
check_adapter(($quantityResult['valid'] ?? null) === false
    && ($quantityResult['error'] ?? null) === 'cart_line_mismatch',
    'session reload quantity drift cannot authorize cart');
echo "PASS: 66 staging cart adapter checks\n";
