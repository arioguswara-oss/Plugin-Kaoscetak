# SANRO Studio V1.1.0 — runtime acceptance ec57148

27 November 2026 WIB · PR #52 · staging only.

## 1. Progres

**96,60% → 96,60% (Δ 0,00).** SANRO-7-v1 retains subtotal 96,45 plus historical balance 0,15. The catalog fix is already credited in the stage 2 baseline. Stage 7 is at its historical cap. This runtime evidence does not add new feature weights.

## 2. Tahap

| Stage | Weight | Score unchanged | Evidence |
| --- | ---: | ---: | --- |
| 1. Core | 25% | 95% | Prior evidence retained |
| 2. Admin | 10% | 95% | #107 runtime fix PASS, new variable Add and absent-meta legacy still open |
| 3. Buyer | 15% | 96% | Fresh desktop Guest PASS, exact live mobile open |
| 4. Quote | 10% | 96% | Guest Cart/Checkout review Rp187.000 matched |
| 5. Save/ownership | 10% | 98% | Guest one-click save and stable Design ID PASS |
| 6. Checkout/production/Drive | 20% | 97% | Isolated CAS PASS; mixed live submission open; #56 reused |
| 7. QA/release/mobile | 10% | 100% | Authenticated HTTPS gates PASS; remaining acceptance tracked separately |

## 3. Runtime siap

- **Deployment PASS.** Authenticated HTTPS pre/post gates under LiteSpeed PHP 8.3.37, prior digest matched, transaction committed and rollback retained. Private roots passed. DTF, Drive and GC flags off.
- **Catalog #107 PASS.** Draft published for the test. Before mapping the card was Belum dipetakan and present in Tambah & Atur. Explicit Add returned Nonaktif. Incomplete activation was rejected. With full front/back areas, Studio profile and a temporary native price, activation passed and the buyer catalog showed the product. Disable hid it, archive/restore returned it to Nonaktif. WooCommerce returned to Draft with its initial blank regular price. Synthetic Studio configuration remains disabled on that draft. #76 variable restored to Aktif and returned to Arsip after the test. Fresh variable Add and actual absent legacy lifecycle metadata remain untested.
- **Isolated database probe PASS.** Production CAS exercised on two QA rows. Stale batch and duplicate stale write denied, both rows read back on the positive path, rollback verified. No real draft or order touched by the probe.
- **Fresh Guest desktop PASS.** 1363 CSS px, no horizontal overflow. Upload, drag, resize, rotation, blue swatch, one-click save ID-00110, S ×1 and 5XL ×1, Mini Cart and Cart Rp187.000. Classic Guest Checkout displayed the same total, with no submission. Guest Cart emptied.

## 4. SHA dan evidence

Candidate `ec57148f1dbbd09462c92904616c5a721487fd6e`. Installed tree digest `3c73a6874497d367d167d740f3fdbd034146df583044cc653b99aaaa4aa11a06`. Archive SHA256 `e5cd30b1de03d7929ce653882da2b541bca0f6673a5bd14cb69cb90a37d4d3fa`. Manifest SHA256 `5135d37cc50026bd7fb78b7e0052c2d6cab923e301c1c7f1545820ca4720a74d`. [Runtime JSON](../../artifacts/studio-v1/final-blocker-fix-20261127/runtime-acceptance.json) · [Checkout/editor screenshot](../../artifacts/studio-v1/final-blocker-fix-20261127/staging-guest-editor.jpg) · [Package checksums](../../artifacts/studio-v1/final-blocker-fix-20261127/SHA256SUMS).

## 5. Gates belum complete

Mixed after-validation (positive, duplicate and negative) **NOT TESTED**. The managed Chromium profile disables Incognito and no isolated second session was available to keep Admin and Guest available together. The mixed guard was not armed and no checkout was submitted. CAS does not count as live checkout duplicate acceptance. Exact 320/390/430 on staging **NOT TESTED**, and Android physical test remains Owner pending. Prior local viewport and staging runtime evidence are retained on their original source.

## 6. Cleanup

Admin WooCommerce count was two orders (#56 and #52) before and after admin/probe. Existing order #56 and production artifact evidence reused. Guest classic checkout was review only. Temporary arm/receipt/probe readback returned false/false/false and the transport was deactivated. Studio and WooCommerce remain active. No production change, release publication, merge, new order or payment.

## 7. Keputusan

**GO staging. NO-GO Final Release** pending the remaining mixed Guest after-validation, catalog edge cases and mobile staging/Owner gates.
