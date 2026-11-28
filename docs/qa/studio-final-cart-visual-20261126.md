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
- **PASS** the native buyer pointer-drag browser fixture on checkpoint `6a2c7bb` after supplying its extracted `clampPosition()` dependency; responsive overflow, touch targets, canvas size, drag and resize assertions pass at 390 px and 320 px.

## Package and staging state

Two clean builds of source `644739c88e8bacebd4a42d7272da93eff8fec419` produced the same ZIP SHA256 `ee039c9b60b1eab65b257e4545f0da63e30cc44ecf0919d955589002a7281f17`. Manifest SHA256 is `bd38a4a14208df0be483ef8a86f2c521ed1ac53b57219c63567c6da0604d6976`; plugin digest is `e5cbc6ce82a3a957aed2cfa02653daffb87ff679941f50d2ed75dc362f8fe42a`. The source-bound transport SHA256 is `00abb9ae37a4c966028e5d03ade56555ff64462422aaa89b18e9115c9d73ab68`.

The transport was bound to the exact installed predecessor source `608579528eeb9d3eddea0dec74b2f8de7e476abe` and tree digest `d9792ebcc58ad813a63b6593ab0778976fcfa6bced5ddae0419142d28e0c1314`. Mandatory authenticated HTTPS pre-install gate passed. The candidate was installed using the pinned CLI installer with retained rollback. Final SSH readback matched the committed candidate tree digest `6b39138c7a8312eb8bf8d720bbafdf1221d44dac2f50bbf01b1fd734f4a18f3e` and build marker; SANRO Studio 1.1.0 remains active. The release transport was restored to 0.3.3 inactive, and its temporary files and Application Password were removed. Full sanitized runtime record: [runtime-acceptance-evidence.json](../../artifacts/studio-v1/final-cart-visual-20261126/runtime-acceptance-evidence.json).

## Final staging acceptance execution — 26 November 2026

Candidate ZIP, manifest, and transport SHA256 values were reused and rechecked. The latest PR checkpoint was `6a2c7bb1b9fb223eaf2c56b23bcbeb2cdb7da3d8`; candidate source remains `644739c88e8bacebd4a42d7272da93eff8fec419`.

SSH authenticated to staging (`SSH_AUTO_AUTH_OK`) as `u606139982` on `id-dci-web1631.main-hosting.eu`. The existing predecessor tree digest `d9792ebc…e0c1314`, active Studio/WooCommerce, private roots and all writer flags OFF matched the transport gate. The mandatory authenticated HTTPS pre-install gate **PASS** on LiteSpeed PHP 8.3.37; runtime readiness, private roots and all listed writer flags were PASS/OFF.

One temporary WordPress Application Password was created, used for the HTTPS gate and one REST install POST, then revoked immediately after the POST returned HTTP 403. The source-bound transport endpoint requires an `X-WP-Nonce`; WP-CLI confirmed the temporary credential label was absent after revocation. The candidate was then installed using the existing pinned CLI installer with the exact Owner-approved source/archive/tree digest and the passing HTTPS gate evidence. The installer committed source `644739c`, retained rollback, and changed no activation, writer flags or order data. SSH readback verified active Studio 1.1.0, build marker source `644739c`, and installed tree digest `6b39138c7a8312eb8bf8d720bbafdf1221d44dac2f50bbf01b1fd734f4a18f3e`, matching both the transaction journal and candidate ZIP tree. The temporary release transport was restored to 0.3.3 inactive and temporary transport files were removed. An authenticated post-install HTTPS gate was not run after the credential was revoked.

Fresh unauthenticated Guest Buyer acceptance on the installed candidate **PASS** at 390×844. A synthetic text design used S × 1 and M × 1; the server quote, loaded artwork previews, Edit Desain restoration, populated Mini Cart, Cart and guest Checkout review all passed. Mobile text movement passed via Chromium touch event emulation. A separate read-only browser pass verified 320×700, 390×844 and 768×1024 layouts, current buyer CSS/JS fingerprints, 44 px controls and front/back print-zone placement. There were zero uncaught page errors. No Place Order action, order or payment occurred.

The accepted cart was cleared. To remove artifacts from intermediate browser retries, the exact recent unbound synthetic QA drafts and sessions were matched by product 24, S/M quantities, recent QA-only design state and absence of order binding; 8 drafts, 14 private preview files and 6 guest cart sessions were removed. Other staging sessions and data were left untouched.

Screenshots: [Editor](../../artifacts/studio-v1/final-cart-visual-20261126/fresh-guest-acceptance/guest-editor-mobile-390.png), [Cart](../../artifacts/studio-v1/final-cart-visual-20261126/fresh-guest-acceptance/guest-cart-mobile-390.png), [Mini Cart](../../artifacts/studio-v1/final-cart-visual-20261126/fresh-guest-acceptance/guest-mini-cart-mobile-390.png), [Checkout](../../artifacts/studio-v1/final-cart-visual-20261126/fresh-guest-acceptance/guest-checkout-mobile-390.png). Sanitized browser evidence: [fresh Guest Buyer acceptance](../../artifacts/studio-v1/final-cart-visual-20261126/fresh-guest-acceptance/guest-acceptance.json). Full gate/install/readback evidence: [runtime acceptance evidence](../../artifacts/studio-v1/final-cart-visual-20261126/runtime-acceptance-evidence.json).

## Status

Staging acceptance is **PASS** for candidate `644739c`. Development baseline remains **96.50% → 96.50%, delta 0.00**. No merge, production deployment, order submission, payment, DTF activation or Google Drive activation occurred. The touch drag result is browser emulation, not a physical-device test. Google Drive live OAuth remains deferred.

[Build/install evidence and hashes](../../artifacts/studio-v1/final-cart-visual-20261126/build-evidence.json) · [Reproducible candidate ZIP](../../artifacts/studio-v1/final-cart-visual-20261126/sanro-studio-staging-candidate.zip) · [Predecessor guest screenshots](../../artifacts/studio-v1/final-cart-visual-20261126/previous-candidate-608/)
