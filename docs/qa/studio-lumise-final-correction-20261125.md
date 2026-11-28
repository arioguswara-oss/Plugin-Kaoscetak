# SANRO Studio — Lumise benchmark and final functional correction

25 November 2026 · Repository `arioguswara-oss/Plugin-Kaoscetak` · PR #52

## 1. Checkpoint and benchmark

Fetched PR checkpoint `067814607d973c0d45705297d68068a32918b974`; implementation source is `964e428442e21cbae05bd336b81f8aef82cac424`. The official Lumise Classic documentation was used only to compare administrator product setup, editor/design-area behavior, cart context and order review. It was not used as a source of code or assets. The existing Owner concept screenshots remain the visual source of truth. [Product Base](https://lumise.com/docs/classic/backend/product-base/), [Design Area](https://lumise.com/docs/classic/design-editor/design-area/), [Cart & Languages](https://lumise.com/docs/classic/design-editor/cart-and-languages/), [Orders](https://lumise.com/docs/classic/backend/orders/), [Editor Settings](https://lumise.com/docs/classic/settings/editor/).

## 2. P1 corrections

The catalog Add form now binds the selected product’s own nonce to the submitted hidden field on selection and submit. Empty product/nonce submissions are blocked. The server capability checks and `sanro_catalog_<product_id>` nonce verification remain unchanged. Admin assets use content-derived versions so same-version browser caches cannot retain the old binding code.

Mockup validation now checks the attachment’s raw WordPress status and type. Public orphaned Media Library images stored as raw `inherit` are accepted when their effective status is public; images whose parent is private, password protected or otherwise nonpublic remain rejected. Invalid IDs and non-images remain rejected. The Studio product save now reports failure and retains the previous mockup pair.

## 3. Buyer UI and retained behavior

Theme chrome suppression remains scoped to `body.sanro-studio-page`; selectors cover the JurnalFX header/footer wrappers while leaving other site pages alone. The buyer editor’s existing drag/resize interactions, variable selection, multi-size, Desain Saya, SANRO quote rules, repeat discount, WooCommerce integration and production metadata were not altered. Black/yellow Owner styling remains intact. Browser visual acceptance against the two concept screenshots is still assigned to ChatGPT Work.

## 4. Targeted verification

- PHP lint for changed PHP files and JavaScript/Python syntax: PASS.
- Product mockup validator/save smoke: 39 assertions PASS, including private attachment/parent rejection.
- Nonce binding VM interaction: selected product nonce submitted; empty selection blocked.
- Chromium layout harness: responsive portrait widths, theme chrome scope, touch-target sizing, pointer drag and resize PASS.
- Staging fixtures #74/#75: raw `inherit`, effective `publish`, both accepted by the installed validator.
- Synthetic product #53: #74/#75 persisted through the save helper, then the exact prior mockup metadata was restored.
- No Full Audit, checkout, order, payment, pricing or Drive OAuth exercise was repeated.

## 5. Candidate and installation

Candidate ZIP SHA256 `60c9bd4096765c854f70aa46dbe1bbfa606052f01aec0d13d01b1d184b967aed`; manifest SHA256 `fd9708b0d603bc338a1eeb63d673fb493a2e7be6d751f6548935f55167b33d77`; plugin digest `6b943e4e73eeb8a9b3b7e9c24df4e8c500c1d9fbfc57847e48f46675755e025b`. The source-bound transport SHA256 is `95d4fa23648dae18fbca4df2607c8b55640b568669530f0bbf5ea66abf84da25`.

The prior active 1.1.0 tree was freshly checked over SSH as `6240c2816723cec83803416484fb2149611b61a96c96db109e2914b45a8d96f7` and matched the exact 690fb0b manifest/archive. Mandatory HTTPS pre-install gate passed on LiteSpeed Web SAPI with private roots PASS, WooCommerce active and all writer flags OFF. The official installer committed under lock and retained rollback. Post-install HTTPS gate passed for source 964e428; active SANRO Studio is 1.1.0 and installed tree digest is `f8243694f32da6cbb54ce7186b32f91e0c78cef828d8616b32d27e9fabbfbf73`, independently recomputed over SSH. The original transport v0.3.3 was restored with digest `4411c6ca2752e0a91c7c739945e22cb40378714fe6389c439be7a4dafd075106` and left inactive. The temporary application password and transport files were removed.

## 6. Ledger and release status

Official development baseline remains **96.50% → 96.50%, delta 0.00**; implementation and test counts do not create rubric credit. The two reported P1 implementation blockers are closed on the installed candidate. Functional targeted evidence is PASS. Final visual sign-off remains pending; this candidate is not a Final Release approval. Google Drive live OAuth remains **DEFERRED BY OWNER**. Production and merge were not accessed.

## 7. ChatGPT Work handoff

Please run independent visual acceptance on staging at the installed source above, comparing both Owner concept screenshots. Verify the live Admin “Tambah produk” nonce flow and save #74/#75 through the Administrator UI; confirm the buyer page shows no JurnalFX header/footer while adjacent site pages retain them. Review buyer product → design → quote → cart, Desain Saya, portrait/mobile touch, drag, resize and pinch behavior. Review Admin settings, product/mockup, print area, pricing, storage and order-design detail screens. Do not create a paid checkout/order. Google Drive OAuth remains deferred. Record screenshots and update the ledger against this installed digest; do not merge or touch production.

Evidence: [`runtime-evidence.json`](../../artifacts/studio-v1/lumise-final-correction-20261125/runtime-evidence.json), [`candidate-manifest.json`](../../artifacts/studio-v1/lumise-final-correction-20261125/candidate-manifest.json), [`transport-binding.json`](../../artifacts/studio-v1/lumise-final-correction-20261125/transport-binding.json).
