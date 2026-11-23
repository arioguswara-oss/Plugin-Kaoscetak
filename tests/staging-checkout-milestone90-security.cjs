'use strict';
/**
 * Milestone-90 checkout security gate audit.
 * Offline/source-only: does not create orders, access WordPress, or call HTTP.
 * A PASS is a structural safeguard, not a claim that payment or Place Order works.
 */
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const root=path.resolve(__dirname,'../plugins/sanro-studio');
const read=file=>fs.readFileSync(path.join(root,file),'utf8');
const main=read('sanro-studio.php');
const bridge=read('includes/checkout-bridge.php');
const cart=read('includes/cart-adapter.php');
const contract=read('includes/cart-contract.php');
const lock=read('includes/checkout-batch-lock.php');
const e2e=fs.readFileSync(path.join(__dirname,'staging-image-checkout-e2e.cjs'),'utf8');
let passed=0;
function check(value,label){assert(value,label);passed++;}

check(main.includes("defined('SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK')")&&
  main.includes("SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK === true"),
  'checkout hooks explicitly opt-in');
check(main.includes("defined('SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT')")&&
  main.includes("SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT === true"),
  'signed cart pricing opt-in');
check(main.includes("'sanro_studio_stage_checkout_process', 5"),
  'checkout authorization runs before order-item creation');
check(main.includes("'sanro_studio_stage_checkout_create_order_line_item', 10, 4"),
  'order line binding hook registered inside gate');
check(main.includes("'sanro_studio_stage_checkout_verify_order_batch', 10, 2"),
  'full order batch verification hook registered inside gate');
check(main.includes('if (sanro_studio_stage_checkout_batch_lock_enabled())'),
  'draft locking independently gated');
check(bridge.includes("apply_filters('sanro_studio_staging_checkout_owner_token', null, $id, $product_id)"),
  'no default owner token supplied');
check(bridge.includes("wc_add_notice('SANRO Studio: reauthorize your design before checkout.'"),
  'missing authorization fails closed');
check(bridge.includes('sanro_studio_stage_authorize_checkout($id, $token, $product_id, $wc->cart)'),
  'fresh quote/size validation before checkout');
check(bridge.includes("unset($GLOBALS['sanro_studio_checkout_verified'])"),
  'failed draft lock validation clears transient checkout state');
check(bridge.includes('sanro_studio_validate_size_checkout_batch('),
  'order batch checked before binding');
check(bridge.includes('sanro_studio_prepare_cart_design_contract('),
  'owner-bound contract used at checkout');
check(cart.includes('sanro_studio_stage_cart_signature('),
  'cart metadata signature verified');
check(cart.includes("remove_cart_item("),
  'untrusted cart lines removed');
check(cart.includes('sanro_studio_stage_verify_cart_prices('),
  'post-total server price verification');
check(cart.includes('sanro_studio_stage_reprice_signed_cart('),
  'signed price restored on cart reload');
check(contract.includes('sanro_studio_validate_size_cart_lines('),
  'server contract validates cart sizes');
check(contract.includes('sanro_studio_validate_checkout_totals('),
  'checkout totals checked against quote');
check(lock.includes('hash_equals(')&&lock.includes("hash('sha256', $owner_token)"),
  'draft owner token compared via hash');
check(lock.includes('sanro_studio_stage_checkout_lock_created_order('),
  'order draft lock callback exists');
check(e2e.includes('NO ORDER, NO PAYMENT'),
  'image E2E explicitly avoids Place Order and payment');
check(e2e.includes("assert.equal(cart.status(),200")&&
  e2e.includes('Checkout summary lost image design identity, size or price'),
  'E2E verifies real cart and checkout display');
check(!e2e.includes("locator('#place_order').click()")&&
  !e2e.includes("locator('[name=woocommerce_checkout_place_order]').click()"),
  'E2E does not submit order');
console.log('PASS: '+passed+' SANRO checkout security gate source checks (offline, no order)');
console.log('NOT TESTED: actual Place Order, order item persistence, payment, Drive and production');
