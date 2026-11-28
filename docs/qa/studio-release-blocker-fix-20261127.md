# SANRO Studio — Release blocker correction checkpoint

PR #52 · integration branch `work/studio-mobile-checkout-integration-20261123` · base `8bfa9b3c3fb5374439821811ce0d5b171fda8d4a` · candidate code basis `ec57148f1dbbd09462c92904616c5a721487fd6e`.

## Change summary

- **ST-03:** Quotes now carry a server-resolved, versioned product configuration snapshot: safe areas, color palette, print limits/zones, pricing profile/revision, and available variation mapping. The quote snapshot is stored at order lock and is covered by the immutable quote hash already pinned to WooCommerce order metadata. Locked production validation uses those frozen safe areas and palette; malformed snapshots fail closed. Older locked records without the new field retain their legacy validation path; no historical physical values are synthesized.
- **ST-02:** Variation attribute validation now accepts explicit WooCommerce slugs `pa_warna`/`pa_ukuran` as well as `pa_color`/`pa_size` and `color`/`size`. The mapped variation ID is still checked against its parent, published/purchasable/in-stock state and its exact saved attributes.
- **ST-04:** Buyer catalog reads one bounded 48-item WooCommerce page at a time, applies eligibility to that page, and exposes page navigation and server-side search. An eligible product after the old first-48 cutoff can now be reached without loading the full catalog on mobile.
- **ST-01:** Rate-limit cache values accept integers or canonical decimal integer strings only, normalize them to integers, and preserve the eight-attempt threshold, lock leases, and 5% eligibility/discount policy.
- **ST-R01:** Classic cart/checkout and WooCommerce Store API cart validation now fail closed if any Studio design line remains while the pricing adapter is disabled or replaced. Ordinary WooCommerce-only carts are unaffected.

## Targeted validation

PASS: PHP syntax checks for all modified PHP files and new tests.

PASS: `tests/release-blocker-frozen-config.php` (3 assertions); `tests/release-blocker-guards.php` (7 assertions); `tests/buyer-catalog-pagination.php`; `tests/variable-product-e2e-contract.php` (10 checks); `tests/production-package-flow.php` (36 checks); `tests/order-lock-boundary.php`; `tests/pricing-integration-offline.php` (8 checks); `tests/repeat-route-security.php` (11 checks).

These are local synthetic/WP-WC simulation tests. No staging, production, payment, order, database, or live catalog state was changed. Full audit, unrelated test suites, Full Release gate, and runtime acceptance were not rerun. HPOS runtime acceptance remains open as previously assigned.

## Release state and handoff

- Official development baseline remains **96.60%**; no progress credit is claimed by this correction alone.
- Source corrections and focused regression evidence are ready for PR review. No ZIP/manifest candidate was built in this checkpoint.
- **Staging install: NOT RUN. Production and merge: NOT RUN.** Final Release remains **NO-GO** pending candidate build and release/runtime gates.
- Base branch was verified at the requested SHA before edits. Integration should be pushed as a fast-forward only; if GitHub branch head changes, stop and inspect before retrying.

## Files

Runtime: `plugins/sanro-studio/includes/{buyer,checkout-policy,mockup-mapping,pricing-integration,pricing-quote,product-area,production-package,repeat-discount}.php`.

Tests: `plugins/sanro-studio/tests/{buyer-catalog-pagination,release-blocker-frozen-config,release-blocker-guards,variable-product-e2e-contract}.php`.
