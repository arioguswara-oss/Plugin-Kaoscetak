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

## Follow-up runtime investigation and source correction

- Read-only WP-CLI on the exact staging account inspected only the synthetic draft's hashed upload-budget transient. At `2026-11-24T06:46:49Z`, the persisted value was the decimal string `"1"`, with `2667` seconds until transient expiry (`2026-11-24T07:31:16Z`). No token, draft ID, artwork, or filesystem path was emitted.
- Source `081a63f` rejects every non-integer transient (`!is_int($count)`), so Hostinger's string representation of a legitimate count of one caused the second master upload to return application HTTP 429 `sanro_master_rate_limited`. This is an application counter-type incompatibility, not a WAF block, and it did not reach the 10-upload quota. The REST 429 code does not set a `Retry-After` header; the later live preview GET responses also had no such header. Reset time was read from the transient timeout, not inferred from a response header.
- A source fix now accepts only bounded decimal integer strings, casts them, and keeps malformed, negative, and exhausted counters fail-closed. Regression assertions cover missing, integer, serialized-string, exhausted, zero-padded, negative, non-decimal, and oversized values. It does not clear/reset the live transient or loosen the ten-per-hour quota.
- Owner-authorized GET-only `preview-read` probes for both sides on two existing synthetic QA drafts returned HTTP 409 `sanro_preview_not_current`, `X-Sanro-Source: 081a63f...`, JSON content type, and no Retry-After header. This is expected when the current draft revision has no saved preview. It does not reproduce the historical HTTP 422. The historical record lacks sanitized response headers/body; the old 422 root cause remains **unverified**. Existing synthetic text QA draft page restoration also reports stale/missing master (409), so it was not used for preview writes.
- A live dynamic WordPress admin hook check reports exactly one SANRO Studio top-level entry and zero SANRO submenus under WooCommerce. PHP shown by WP-CLI was `8.2.36 / cli`; this is not substituted for the verified web SAPI. Admin screenshot is still **NOT TESTED**: no authenticated browser storage state exists in the Hermes QA environment, and no session was fabricated.
- Owner-only buyer screenshot copies are available locally under `/home/sanro/sanro-browser-qa/owner-private/` with directory mode `0700` and file mode `0600`; they are not uploaded/shared or added to the repository. No admin screenshot was generated.
- Source-level targeted checks after the fix: `tests/test_sanro_draft_rest.php` 45 assertions PASS (including eight new normalization cases); `preview-current-route.php` 13 PASS; `preview-upload.php` default-OFF checks PASS; `master-binding-smoke.php` 8 PASS; changed PHP lint and `git diff --check` PASS. No previously passing 46-check suite was rerun.
- The source change is not installed. Completing back master, front/back previews, and authoritative multi-size quote requires exact-candidate Owner approval, installation, then the 10/hour transient reset. No staging transaction or candidate install occurred in this follow-up.
- Fixed source commit `fad47c6f508258d594f92daca79c337d6911b404` was built as `artifacts/studio-v1/fad47c6`. ZIP SHA256 `b83c1b03d18e0633d502aff09d9b1d40f01eb216a22850a7c990fc40c78a5723`; manifest SHA256 `8664f584c4b746120c98ed50b337fc9616508f3b390eca3fa2a8429a370f8309`; plugin digest `6d65fde1ceda80b1551da51817ccef14f0d618f1d5dffdcfcb412a8c129cc2bb`. Local PLAN_ONLY package preflight/lint PASS. `approval-request.json` is pending, not authorization. No staging install, deployment, merge, or activation occurred.

The live registered menu and counter fix do not change the seven-stage rubric. Progress remains **90.50% → 90.50%, delta 0.00**; stage 7 remains 40% until full buyer flow is verified.

## Buyer text-preview and multi-size quote continuation

After the above partial image attempt, an independent fresh synthetic **text-only** draft was tested on the still-installed `081a63f` runtime. The draft saved; authoritative quote returned HTTP 200 with IDR 273000, total quantity 3, M=2/L=1. Front and back private preview POSTs both completed and the buyer reported the preview current after its built-in owner readbacks. Browser page errors: 0. The POST allowlist permitted only one draft create and the two preview endpoints; no other POST was sent or attempted. No cart/checkout/order/payment was opened.

This closes text-only quote and two-side preview QA, not image-backed front/back master plus image-derived preview/quote continuity. The original 422 is still unverified; current text-preview flow did not return 422. The source-fix package is pending exact Owner approval; no install was performed. Baseline and seven-stage estimates remain unchanged pending the complete buyer flow.
