# SANRO Studio — Cart Visual Integration

26 November 2026 · PR #52 · source `644739c` · V1.1.0 candidate

## Change

The Studio header cart control now opens an accessible, mobile-sized popover rendered by WooCommerce's native `woocommerce_mini_cart()` template. It retains WooCommerce cart rows, quantities, totals, remove controls and the `Lihat keranjang` fallback link. Private artwork thumbnails continue to use the existing session-authorized preview integration. Escape closes the panel and restores focus; clicking outside closes it. Cart, checkout, quote, pricing and order logic are unchanged.

## Targeted checks

- **PASS** PHP lint: `includes/buyer.php`, `includes/studio-ui.php`.
- **PASS** JavaScript syntax: `assets/studio-ui.js`; **PASS** whitespace check.
- **PASS** 74 cart-adapter checks, 42 REST cart-bootstrap checks, 13 private-preview route assertions, cart contract and 17 integrated pricing/cart/multi-size/order-lock checks.
- **PASS** 20 same-order checkout recovery checks.
- **PASS** focused Chromium test at 390 px: Mini Cart opens, exposes preview and Cart link, updates `aria-expanded`, receives focus, and closes by Escape and outside click.
- **RETAINED** fresh-guest staging acceptance from predecessor source `6085795`: two designs of the same product, two Design IDs, three size rows, private thumbnails, Edit Desain, Lanjut Belanja and equal Cart/Checkout total Rp192.000. Unauthorized private preview returned 404; no order or payment was created. Screenshots and JSON are retained under `artifacts/studio-v1/final-cart-visual-20261126/previous-candidate-608/`.
- **NOT PASS** existing broad `ui-layout-browser.cjs` drag assertion fails in its local fixture (`pointer drag moves the print object through native buyer handler`). The current diff does not change the drag implementation; prior staging evidence is retained, but that fixture assertion remains unresolved.

## Package and staging state

Two clean builds of source `644739c88e8bacebd4a42d7272da93eff8fec419` produced the same ZIP SHA256 `ee039c9b60b1eab65b257e4545f0da63e30cc44ecf0919d955589002a7281f17`. Manifest SHA256 is `bd38a4a14208df0be483ef8a86f2c521ed1ac53b57219c63567c6da0604d6976`; plugin digest is `e5cbc6ce82a3a957aed2cfa02653daffb87ff679941f50d2ed75dc362f8fe42a`. The exact source-bound transport SHA256 is `00abb9ae37a4c966028e5d03ade56555ff64462422aaa89b18e9115c9d73ab68`.

Before packaging, read-only SSH confirmed staging still had V1.1.0 active and the installed-tree digest `d9792ebcc58ad813a63b6593ab0778976fcfa6bced5ddae0419142d28e0c1314`, exactly matching the immutable predecessor manifest and archive for source `6085795`. The official transport builder validated those predecessor checksums and bound the rollback digest.

The new candidate has **not** been installed. The mandatory HTTPS Web SAPI gate and install route require an authenticated WordPress account with `install_plugins` and `manage_options`. The previously used official method creates a temporary application password and revokes it after install. The latest Owner instruction makes credential creation approval-only; SSH access does not provide an authenticated HTTPS REST session. No credential was created, no gate was bypassed, and the active staging installation was not changed. Latest-candidate guest Mini Cart, Cart and Checkout acceptance therefore remains pending installation.

## Final staging acceptance execution — 26 November 2026

Candidate ZIP, manifest, and transport SHA256 values were rechecked against the recorded build evidence and matched exactly. The repository HEAD and `origin/work/studio-mobile-checkout-integration-20261123` both resolve to checkpoint `021a36579939c4e476f7e016f8b2027cd76759d4`.

The public staging homepage returned HTTPS 200 and exposed `PHP/8.3.37` with Hostinger/LiteSpeed edge headers. This is reachability evidence only; it does not satisfy the authenticated HTTPS Web SAPI gate. The SSH request to the configured `kaoscetak-staging` host was rejected (`Permission denied (publickey,password)`). The Hostinger connector was unavailable in this execution. As a result, the temporary WordPress Application Password could not be created, the mandatory gate and candidate installation were not run, and no credential was created that would require revocation.

Fresh Guest Buyer acceptance on the new candidate, including populated Mini Cart, thumbnails, Edit Desain, multi-size, Cart and Checkout, remains **NOT RUN** because the candidate could not be installed. No staging files or plugin state were changed. Existing predecessor evidence is retained but is not counted as acceptance of source `644739c`.

The previously failing local native buyer drag assertion was caused by the browser fixture extracting `drag()` without supplying its `clampPosition()` dependency. The fixture now supplies bounded percentage positioning. Its focused Chromium run **PASS** at 390 px and 320 px for overflow, touch-target dimensions, canvas size, native pointer drag, and resize. This is local browser evidence; real-device touch drag remains unverified on staging. No order or payment was submitted.

Detailed sanitized execution evidence: [final-staging-execution.json](../../artifacts/studio-v1/final-cart-visual-20261126/final-staging-execution.json).

## Status

Official development baseline remains **96.50% → 96.50%, delta 0.00**. Final staging acceptance is **BLOCKED** on staging SSH/authenticated HTTPS access; candidate installation, fresh guest acceptance and credential revocation remain pending. Local pointer drag fixture passes after correcting its missing dependency; staging touch drag remains unverified. No merge, production deployment, order submission, payment, DTF activation or Google Drive activation occurred. Google Drive live OAuth remains deferred.

[Build/install evidence and hashes](../../artifacts/studio-v1/final-cart-visual-20261126/build-evidence.json) · [Reproducible candidate ZIP](../../artifacts/studio-v1/final-cart-visual-20261126/sanro-studio-staging-candidate.zip) · [Predecessor guest screenshots](../../artifacts/studio-v1/final-cart-visual-20261126/previous-candidate-608/)
