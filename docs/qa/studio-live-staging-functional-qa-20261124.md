# SANRO Studio live staging functional QA — 24 November 2026

## Scope and safety

- Hostinger staging only: `saddlebrown-peafowl-616656.hostingersite.com`.
- Installed source remains `081a63f78b88a3139327af6a545be1993b48506f`, installed tree `8d560cdb95611da98dc4a739737dff30ab9ac35f4ec6866defb8093808fd37db`.
- No installer, production, merge, checkout, order, payment, or order database mutation was performed. Browser write requests were restricted to staging SANRO draft/master endpoints. The only write was one synthetic private draft and its synthetic front master. No cart endpoint was called.
- DTF production generator, Drive, storage GC and checkout writer remain OFF. The runtime exposed staging draft/master/cart/preview feature flags ON; no generator, Drive, GC, checkout, or order operation was invoked.

## Browser and admin evidence

- Playwright with the cached Chromium binary ran against the live buyer editor. Desktop and mobile screenshots are in `artifacts/studio-v1/081a63f/qa-screenshots/` (`desktop-front/back.png`, `mobile-front/back.png`). The captured pages show the real responsive editor and garment mockup. JavaScript/page errors were absent in the screenshot smoke. Mobile read-only viewport QA at 320/390/768 widths passed, including asset fingerprint, touch controls, print-zone geometry and front/back selection, with no cart/order/payment request.
- The buyer garment mockup is rendered as DOM/CSS rather than a persistent `<canvas>`; a browser canvas is used to produce synthetic PNG test input. The live synthetic front/back images both passed print-area validation. Size, shirt-colour and quantity controls were present; M=2 and L=1 were saved in the private synthetic draft.
- Runtime admin registration evidence from the installation checkpoint remains: one independent SANRO Studio top-level menu, zero SANRO entries nested beneath WooCommerce, and WooCommerce product/order integration hooks present. An authenticated admin browser state was unavailable in Hermes, so an admin screenshot is **NOT TESTED**.

## Synthetic image flow and HTTP evidence

- One private synthetic QA draft was created successfully (HTTP 200), with quantities M=2/L=1. The front private master upload and owner-bound readback passed (POST/GET HTTP 200).
- Back master upload returned sanitized HTTP 429 `sanro_master_rate_limited`. Testing stopped immediately; no retry was issued. Owner-bound GET confirmed the draft is valid, front master exists, back master does not, and quantities remain M=2/L=1.
- The read-only server quote returned HTTP 409 for the intentionally incomplete one-sided master draft. Preview upload/readback and complete server price calculation are therefore **NOT TESTED**. No preview was fabricated or uploaded.
- The historical HTTP 422 was previously observed at preview readback, but the historical evidence did not preserve a sanitized request/response code sufficient to prove its root cause. The current run did not reach preview readback because the back upload was rate-limited; a current 422 was not observed. Do not mark the old 422 fixed or reproduced from this run.
- Existing owner-only QA draft credentials remain in the protected Hermes file for controlled later reuse; they are not included in the repository or logs. Respect staging rate limits before any future mutation.

## Targeted regressions

PASS:

- `plugins/sanro-studio/tests/buyer-image-preview-node.cjs` — 10 image preview/master-binding assertions.
- `plugins/sanro-studio/tests/buyer-startup-mobile-node.cjs` — 15 buyer startup, variant/size/side/color and print-area assertions.
- `plugins/sanro-studio/tests/buyer-print-zone-draft-node.cjs` — 18 geometry assertions.
- `plugins/sanro-studio/tests/buyer-asset-version-smoke.php` — 3 asset fingerprint assertions.
- `git diff --check`.

## Seven-stage progress

| Stage | Before | Now | Delta |
|---|---:|---:|---:|
| Core/foundation | 95% | 95% | 0 |
| Product/backend/admin | 94% | 94% | 0 |
| Buyer frontend | 96% | 96% | 0 |
| Server pricing/quote | 96% | 96% | 0 |
| Save/ownership/share | 98% | 98% | 0 |
| Checkout/production/Drive | 97% | 97% | 0 |
| QA/release/mobile | 40% | 40% | 0 |

Official baseline remains **90.50% → 90.50%, delta 0.00**. Runtime evidence does not change the feature-weight rubric. Full buyer pricing/preview and authenticated visual admin QA remain open.

## Gate summary

- Staging identity/source/installed tree and admin menu registration: **PASS** (installation checkpoint evidence; not repeated).
- Desktop/mobile buyer editor, responsive layout, asset fingerprint and front/back UI: **PASS**.
- Synthetic image input and print-area validation: **PASS**.
- Private draft, front master upload/readback and multi-size metadata: **PASS**.
- Back master: **FAIL / blocked by server HTTP 429 rate limit**; no retry.
- Complete authoritative quote, preview upload/readback, previous HTTP 422 root cause: **NOT TESTED / unresolved**.
- Admin visual screenshot: **NOT TESTED** (no authenticated browser state).
- Checkout/order/payment/production generator/Drive/GC: **NOT TESTED by design; remain OFF**.

**Overall:** buyer editor is live and functional for read-only and partial synthetic draft/master flow. Full image/preview/price sign-off is **NO-GO** until rate limit expires and a separately authorized safe continuation can verify the missing side and preview path without entering cart/checkout.
