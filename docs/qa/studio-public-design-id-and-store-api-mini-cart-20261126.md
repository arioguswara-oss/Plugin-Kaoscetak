# SANRO Studio owner revision — Design IDs and native Mini Cart

## Checkpoint

Work continues on PR #52, branch `work/studio-mobile-checkout-integration-20261123`, from checkpoint `9d9fc70937e403ae99d4345c659df201c8bf9159`. Installed staging predecessor remains source `644739c` / SANRO Studio 1.1.0. Development progress remains **96.50%** until the changed candidate passes the required staging gates and fresh Guest Buyer acceptance.

## Implementation

- Added a persistent SQL mapping table with a unique internal UUID key and a unique `AUTO_INCREMENT` sequence. Concurrent calls for the same UUID are idempotent; the internal UUID, owner token, order metadata, and authorization paths remain the private identity. Mapping rows are never deleted. IDs format as `ID-` plus at least five digits and grow beyond 99999.
- New drafts and forks allocate IDs from their fresh UUIDs. Editing/reopening uses the existing UUID-to-ID row. Historical orders resolve their existing internal UUID lazily, retaining the same mapping after first display.
- Editor status, Desain Saya cards, Cart/Checkout item data, order item details, SANRO order admin, and production guide manifests display public IDs. Internal UUID references remain in internal metadata and production provenance.
- Read only staging runtime check found active theme Twenty Twenty-Five and WooCommerce 11.3.1. The theme uses the native WooCommerce Mini Cart block path; WooCommerce Store API `CartItemSchema` applies `woocommerce_store_api_cart_item_images` to cart item images. The plugin now replaces that image only after its existing server-side current-cart/session/revision checks succeed. The image URL points to the existing no-store same-origin endpoint, which verifies the current cart session; a public Design ID alone cannot retrieve a preview. Native Mini Cart quantity/remove/subtotal/navigation data is left to WooCommerce.

## Targeted evidence

- **PASS** PHP syntax checks for all changed PHP modules.
- **PASS** JavaScript syntax checks for `buyer.js`, `collection.js`, and `studio-ui.js`.
- **PASS** public Design ID regression: UUID mapping retry stability, sequence monotonicity, invalid identity rejection, and formatting beyond five digits.
- **PASS** Store API Mini Cart regression: image shape/alt/authorized URL, unrelated cart preservation, and cross-session rejection.
- **PASS** existing cart labels/admin preview summary, authenticated cart session (10 checks), size/checkout bridge (13 checks), integrated pricing/multi-size/order lock (17 checks), and draft lock/order binding suite.
- Existing private-preview-file test reports `SKIP: GD unavailable`; its separate owner, session, checkout and draft lock checks pass. Headless Chromium Mini Cart execution could not start because the managed sandbox denies Chromium sandbox syscalls. No escalation was requested.
- SQL-level concurrency safety is provided by MySQL/InnoDB unique-key insertion and `AUTO_INCREMENT`. A live concurrent database stress test could not run: local MariaDB socket creation is denied by the managed sandbox. The current staging database has not been modified for a test.

## Staging release gate

No new build has been installed. Mandatory authenticated HTTPS pre-install and post-install gates, the updated candidate install, and fresh changed-feature Guest Buyer acceptance remain **PENDING**. The previous temporary Application Password has been revoked. One consolidated Owner approval is required before creating a new temporary Application Password for both HTTPS gates and installation; it will be revoked immediately after the required authenticated calls and checked for absence.

No order, payment, DTF activation, Google Drive activation, merge, or production deployment is authorized or performed. Official baseline remains **96.50%**, with no progress credit until the evidence is complete.
