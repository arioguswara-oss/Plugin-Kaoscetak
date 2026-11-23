# SANRO Studio DTF pricing contract v1

Work branch: `work/claude-sanro-studio-product-pricing-profile-20261123`.
Baseline: `7a732797ebe43960f3f98e8d666cc792d21454f9`. This extends the existing
pricing-profile branch; it does not restore editor code or change PR #50.

## Authoritative rules

Source: [Owner decisions](../business-rules/kaoscetak-lite-designer-v1.md).
Only the existing approved `cotton_combed_24s` product profile is executable on
this branch. Existing prices are unchanged. Hoodie/sweatshirt prices are OPEN;
no regular-shirt fallback is permitted. The approved tunic table is documented
in the Owner decisions but its product profile is not implemented here.

- A5 14.8 × 21, A4 21 × 29.7, A3 29.7 × 42, A3+ 32 × 48 cm.
- Category is the selected maximum bounding box, not a compulsory print size.
  Smaller artwork does not automatically downgrade the selected category.
- Image and text share one combined bounding box on each side. Product safe
  areas come from administrator metadata. Switching garment size does not
  rescale physical artwork.
- One-side package already includes printing; no blank-shirt price component.
- Two nonempty sides: larger category's package plus smaller category's extra:
  A5 15,000; A4 25,000; A3 35,000; A3+ 40,000 IDR. Equal categories use that
  category's extra. Empty sides are not charged.
- 24s long sleeve adds exactly 10,000 IDR per piece, after the two-side calculation.
- No unverified repeat-order discount is applied. The future verified 5% repeat
  path requires trusted customer/order evidence; a browser eligibility flag is
  rejected. It must be integrated before repeat-discount checkout is enabled.

## Editor → server quote

`sanro_studio_product_quote(int $product_id, array $design)` is read-only. It
requires native WooCommerce currency IDR and a published, purchasable product
with its approved pricing profile and valid administrator-owned safe areas.
It rejects unknown product profiles and malformed design/preferences/quantities.
It calls the existing geometry validator and price function rather than trusting
browser totals. No new REST endpoint or buyer hook is added; pricing hooks require an explicit development flag.

Example (all geometry in cm):

```php
$design = [
    'front' => ['category' => 'A3', 'objects' => [
        ['type' => 'image', 'x' => 0, 'y' => 0, 'width' => 10, 'height' => 10],
    ]],
    'back' => ['category' => 'A5', 'objects' => [
        ['type' => 'image', 'x' => 0, 'y' => 0, 'width' => 10, 'height' => 10],
    ]],
    'quantities' => ['S' => 2, 'L' => 1],
    'long_sleeve' => false,
];
$quote = sanro_studio_product_quote(7, $design);
// If product 7 is configured: S 97,000 × 2 + L 103,000 × 1 = 297,000 IDR.
```

Successful result: `valid`, `contract_version` (1), `product_id`,
`pricing_profile`, `currency`, nonempty side `categories`, validated `sides`
(including `span_cm`), size-keyed `lines` (`quantity`, `unit_price`, `subtotal`),
`pieces` and `total`. Quantities are strict integers 0–999 per size with at least
one piece and at most 5,000 overall, matching existing limits. Zero lines are
omitted. An absent side differs from malformed content; malformed content fails.

Failure: `valid => false`, machine-readable `error`, optionally `side` from
geometry validation. Callers must stop the Studio transaction on failure.
The quote is the current package calculation in whole IDR; it does not calculate
WooCommerce taxes, shipping, coupons or payment amounts and is not a signed or
persisted price reservation.

## WooCommerce adapter boundary

`sanro_studio_validate_priced_cart_line($product_id, $design, $size, $quantity)`
recalculates the complete quote and returns the validated size line only when
the native integer cart quantity matches the authorized design's size quantity.
It rejects missing sizes, stale quantities, unsupported currency and unavailable
products. It accepts no posted total, price, safe-area or discount authority.

Integration caller requirements (activation remains default-OFF):

1. Resolve the WooCommerce product ID from a trusted product/draft relationship.
   For variations validate the native parent/variation relationship and selected
   attributes separately; do not silently map arbitrary product IDs.
2. Authenticate/authorize the private draft and verify master-file readiness
   before passing its server-loaded design. This calculator is not ownership,
   nonce, stock or file-readiness authorization.
3. On add-to-cart and again on totals/checkout, reload current authoritative
   configuration and recalculate. Use returned unit price with native
   `$cart_item['data']->set_price(...)` for that Studio line. Never divide the
   aggregate total across unrelated cart items or trust a saved/browser price.
4. Quantity editing must update the authorized size allocation before re-quote;
   reject a stale mismatch. Preserve ordinary cart lines. Block the affected
   Studio operation on invalid quote; never fall back to a catalog/zero price.
5. Retain native variation, stock, quantity, shipping, tax, coupon, checkout nonce
   and order validation. Prevent merging different authorized design IDs into
   the same cart line. Freeze the validated production/price snapshot only at
   the appropriate order lifecycle point, outside this read-only contract.

The actual draft/cart bridge is absent from this branch. The optional adapter now uses `set_price`; no order creation hook is added. Therefore native WooCommerce
runtime integration is **NOT VERIFIED** and production release is **NO-GO**.

## Local evidence — 2026-11-23

Runner reused from Draft [PR #48](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/48),
without importing its branch-specific ledger. `tests/run-all.sh` runs PHP/JS
syntax checks and all PHP/Node test scripts; stdout ends in `PASS|FAIL <HEAD>`.

Environment: PHP 8.3.36 CLI from `wordpress:php8.3-fpm-alpine`, repository mounted
read-only; Node v24.22.1. No WordPress service/database/site was started.

- PASS: 688 server quote assertions, including the entire 8-size × 4-front ×
  5-back-option × 2-sleeve matrix and reversed sides; Owner price examples;
  multi-size totals; exact/oversized category bounds; combined image/text span;
  empty side; invalid quantity, product, currency, profile and injected prices;
  cart size/quantity mismatch.
- PASS: existing bootstrap and pricing-profile PHP contracts.
- PASS: three Node cases (price examples/symmetry, invalid quantities, safe cart URL).
- PASS: 11 PHP syntax checks, four JavaScript syntax checks, Bash syntax and diff checks.
- NOT VERIFIED: real WordPress/WooCommerce add-to-cart/totals/checkout, native
  variations, shipping/tax/coupon behavior, browser UI, repeat-order verification,
  Drive readiness and production runtime.
- GitHub CI is not certified by these local results; no workflow added or changed.

No plugin version/release ZIP is issued: this is a development pricing contract,
not a deployable integrated checkout release. No merge or deployment performed.

## Completion delta — WooCommerce lifecycle adapter

`includes/pricing-woocommerce.php` now registers native validation, totals and
Classic checkout callbacks **only** when `SANRO_STUDIO_ENABLE_WC_PRICING === true`.
It refuses to activate alongside `SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK` from
PR #50. The adapter supersedes the earlier statement that no pricing hooks exist;
all flags remain OFF by default. It does not replace the completed pricing engine.

The integration supplies `sanro_studio_pricing_authorized_design` filter:
`($default, $design_id, $product_id, $context)` returns an array containing
`authorized => true`, `master_ready => true`, exact `design_id`, exact integer
`product_id`, and the freshly server-loaded `design`. Return null on any failure.
The resolver is trusted server code, not a public REST response or cart metadata.
It MUST check current session ownership, current draft status and product binding,
actual image/master readiness, and revocation. No resolver is supplied by default;
all Studio operations fail closed without one. Never persist owner tokens in cart.
PR #50's `sanro_studio_read_draft` and binding checks are available integration
building blocks; they must not be replaced with a bare metadata lookup.

Line metadata contract: `sanro_studio_pricing_version => 1`, opaque
`sanro_studio_design_id` (UUID), `sanro_studio_size` (approved size). Native WC
supplies `product_id`, `variation_id`, `quantity`, and product `data`.
The marker is an adapter selector, **not authorization**. No nominal is trusted.
A caller can reuse `sanro_studio_pricing_wc_quote($line,$quantity,'editor')` for
an authorized editor allocation; add/cart/checkout use the same underlying quote.

- Add-to-cart validates the authorized design and requested allocation alongside
  existing Studio lines. Existing native validation failure remains a failure.
- Quantity changes use the native cart allocation, replacing only the quote's
  quantities in memory; artwork, cm, categories and sleeve remain server-owned.
  No draft data is rewritten. Per-size merged cap 999 and per-design cap 5,000
  are checked across the cart. Native zero removal remains available.
- Totals prevalidate all Studio lines before mutation, clone product objects per
  line and assign the freshly calculated unit price using `set_price`. Repeated
  totals never add a surcharge to an already adjusted price. Ordinary lines
  and buyer cart contents are preserved.
- Cart validation and Classic checkout reauthorize/reprice again. Failed totals
  abort; failed validation adds a generic WC error, without leaking credentials.
  Native WooCommerce remains responsible for taxes, shipping, stock and coupons.

Integration limits: simple products only; nonzero variation IDs fail closed until
native attribute-to-design mapping is implemented/tested. Checkout Blocks/Store
API order guards are NOT implemented and must not be enabled for this adapter.
The integration owner must use Classic Checkout and keep this development flag
OFF until real WordPress QA and ownership resolver tests pass.

### PR #50 handoff (read-only reference: `0b3004b`)

Do not register both `sanro_studio_stage_reprice_signed_cart` (signed stored-price
QA path) and the new live quote writer. Lead Developer must choose the new
contract explicitly and keep legacy QA flags OFF. PR #50's current checkout
bridge compares saved size allocations/signatures, so its binding callbacks
cannot be mixed with the new quantity-edit contract without adapting them.
That source is not edited here. Any final order binding must consume freshly
recomputed native allocation rather than the original saved draft quantities.
No signature or private-file authorization guard may be silently removed.

Additional local evidence: 31 WooCommerce adapter assertions PASS against real
price/geometry functions and synthetic WC objects: hook registration, editor/add/
quantity/totals/checkout, tampered nominal, repeated totals, shared product object
isolation, invalid/merged/aggregate quantities, missing resolver/master, currency/
safe-area change, stale category refresh, ordinary/empty cart and adapter conflict.
The underlying 688 quote assertions also rerun. Real WC hooks/browser/Store API,
shipping/tax/coupon/order persistence remain NOT TESTED. No production activation.
