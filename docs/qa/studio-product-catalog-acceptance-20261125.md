# SANRO Studio — Owner product catalog review

Date: 25 November 2026 (Asia/Jakarta)

Repository: `arioguswara-oss/Plugin-Kaoscetak`, PR #52

Reviewed source: `826c152c3dbf2e35fff74d276e9c2fd00aed2905`

Installed tree digest: `f746c3928b96b5c6ca20cb04a7d5615a2f636799d2f88939c93fc4cadb88f049`

Staging: `https://saddlebrown-peafowl-616656.hostingersite.com`

## Decision

Owner's manual finding is confirmed as a **P1 functional release blocker**. Current UI lets an Administrator configure selected WooCommerce products, but cannot manage SANRO Studio catalog membership independently. Final Acceptance is **NO-GO** until the Studio catalog lifecycle and its mapping persistence work through the SANRO Studio UI.

## Direct browser observations

Opened the live authenticated admin route `SANRO Studio → Produk & Mockup` and selected the existing staging fixture “SANRO Studio Staging QA — Cotton Combed 24s” (Woo product ID 53). The selector also displayed the existing `test` fixture. This was observation only. No save was submitted and no product/order/design was modified.

| Acceptance check | Result | Evidence |
|---|---|---|
| Add SANRO Studio product | **FAIL** | No Add Product control or form. The picker only lists existing published WooCommerce products. The separate WooCommerce Add Product route is not a Studio catalog workflow. |
| Edit an existing product | **PARTIAL** | Product opens in Studio with Mockup, Area Cetak, Warna & Size, Harga tabs and “Simpan Produk”. Persisted edit/readback was not submitted in this review. |
| Remove/archive product from Studio catalog | **FAIL** | No product-level Remove/Archive control. The “Hapus baris” action belongs to one color/size mockup mapping row only. No Woo product delete/trash was attempted. |
| Activate/deactivate product in Studio | **FAIL** | No independent Studio toggle. Changing Woo publication state is not a Studio-only activation control and was not attempted. |
| WooCommerce product mapping | **PARTIAL** | Studio configuration is reached by selecting an existing Woo product ID, but there is no explicit mapping lifecycle or mapped/unmapped state. Only simple products are accepted by the editor-config validator. |
| Front/back mockups | **UI PASS; persistence NOT TESTED** | Separate Media Library attachment controls exist for front/back. |
| Print area, category and physical dimensions | **UI PASS; persistence NOT TESTED** | The product UI displays visual zone controls, front/back switches, category selection and physical width/height fields. |
| Color/size mapping | **UI PARTIAL** | “Tambah warna/ukuran” exposed a row with color/name, size selection and separate front/back image pickers. The unsaved row was discarded on reload; no persistent change. |
| Price mapping | **UI PASS; persistence NOT TESTED** | Price profile selector and per-size/per-tier inputs are visible. Existing buyer quote evidence was reused, not repeated. |

No product was created in WooCommerce, deleted, trashed, unpublished, or modified. No order or design was changed. A browser screenshot attempt timed out; the live UI state was verified through Cloud Browser accessibility output. The existing concept-versus-actual screenshots remain in `artifacts/studio-v1/1.1.0-ui/review-20261125/`; they depict the earlier visual-review checkpoint, not a screenshot of this product-catalog screen.

## Source-confirmed root cause

- `plugins/sanro-studio/includes/studio-ui.php:29-45`: `sanro_studio_ui_product_page()` retrieves up to 100 published WooCommerce products, renders a selector and configuration tabs for an existing Woo ID. It has no Studio product-create or product-remove/archive control.
- `plugins/sanro-studio/includes/studio-ui.php:46-51`: the only catalog-product POST endpoint is `sanro_studio_ui_save_product()`. It updates existing product configuration metadata and saves that WooCommerce object.
- `plugins/sanro-studio/includes/studio-ui.php:74`: only `admin_post_sanro_studio_ui_save_product` is registered for Studio product management; no add/archive/delete/enable action is registered here.
- `plugins/sanro-studio/includes/editor-config.php:8-25`: buyer editor availability requires a published, purchasable, **simple** WooCommerce product and valid Studio price/print-area configuration.
- `plugins/sanro-studio/includes/mockup-mapping.php` and `product-pricing.php`: color/size matrix, mockup and price configuration operate as metadata on the selected WooCommerce product rather than as a separate Studio catalog record.

Therefore the current “Katalog Anda” is a WooCommerce-product configuration editor, not an independently manageable SANRO Studio catalog. The lifecycle controls Owner expects are not implemented. This is a functional gap, not merely the location or styling of a hidden button.

## Required implementation and acceptance

1. Add SANRO Studio catalog actions inside the independent Studio admin: Add/Map, Edit/Save, Enable/Disable, and recoverable Studio-only Remove/Archive.
2. Add/Map should allow binding a selected existing WooCommerce product, or launch the native WooCommerce creation UI and return to Studio setup. Never create a duplicate Woo product silently.
3. Removal must archive the Studio listing only. Do not call WooCommerce delete/trash/unpublish, hard-delete configuration, alter order items, or invalidate existing design/order snapshots. Keep restore/idempotency behavior and guard entries referenced by active designs/carts as appropriate.
4. Preserve capability checks and nonce validation for every write action. Validate Woo mapping, mockup front/back, physical print area/category, color/size/variation and server price as one saved configuration; show readiness/errors before buyer publication.
5. Decide and implement variable-product variation mapping under the current server-quote contract, or reject unsupported product types clearly before saving. Current code accepts only simple products.
6. Focused staging acceptance with disposable synthetic data: add/map, edit and read back, activate and verify buyer visibility, deactivate and verify buyer exclusion, archive then restore with Woo product count/status unchanged, verify per-size color/mockup/print-area/price configuration and server quote, and confirm an existing order/design reference remains intact. Do not use order #56 for destructive tests.

## Acceptance status

| Area | Current result |
|---|---|
| Live admin screen inspected | PASS |
| Existing-product editor controls inspected | PARTIAL PASS |
| Add Studio product | FAIL — absent |
| Persist existing-product edit | NOT TESTED — no-op/save avoided |
| Studio-only product removal | FAIL — absent |
| Independent Studio activation | FAIL — absent |
| Configuration UI availability | PASS/PARTIAL by mapping area, see table |
| WooCommerce delete/trash/product status | NOT TESTED — deliberately untouched |
| Catalog lifecycle release gate | **NO-GO / P1 blocker** |
| Final Acceptance | **NO-GO** |

Visual reviewer scores in `studio-ui-1.1.0-independent-review-20261125.md` remain qualified and tied to the earlier source/screenshot checkpoint. This review does not recalculate them or repeat buyer, DTF, Drive, checkout, or order tests already marked PASS/deferred.
