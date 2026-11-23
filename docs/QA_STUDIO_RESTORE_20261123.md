# SANRO Studio restore QA — 2026-11-23

Base: `feature/studio-bootstrap-admin` at `71955dd3d4df9669b1e667e928a64508a2d579b6`.
Source checkout: `fix/sanro-studio-master-text-float-guard-20261122` at `179850842be5f801e9c79aad6afc4c522e6e8e23`.
Pricing profile comparison: `work/claude-sanro-studio-product-pricing-profile-20261123` at `7a732797ebe43960f3f98e8d666cc792d21454f9`.

The restore branch keeps the integration bootstrap and leaves all feature flags undefined. Later work on this same branch added the historical companions and opt-in hook wiring; the original checkpoint results below are retained for traceability.

## Putaran koordinasi 12 — update 2026-11-23

**IMPLEMENTED:** restored historical `plugins/kaoscetak-lite-designer/`, `ops/hermes/`, `ops/hostinger/`, `scripts/`, and `prototypes/` from source commit `179850842be5f801e9c79aad6afc4c522e6e8e23`. Wired native settings/admin-post, private storage initialization, explicitly gated buyer editor, and explicitly gated classic checkout/cart hooks in the integration bootstrap. All flags remain undefined by default; DTF generator and Drive stay OFF. Updated the buyer-render fixture to match the source asset graph, without changing the editor implementation. The DTF pricing handoff is in `docs/architecture/studio-dtf-pricing-handoff-20261123.md`; the pricing engine is untouched.

**VERIFIED:** 176 tracked PHP files pass `php -l`. Local CI: 184 checks PASS, one harness FAIL because Node `spawnSync('php')` is denied with `EPERM` in this sandbox; the same 400 PHP/JS pricing parity cases PASS when PHP and Node run as separate shell processes. The 22 PHP tests previously failing solely for missing companions now PASS. Editor geometry: 10 assertions PASS; legacy editor unit: 21 tests PASS; buyer render: 12 assertions PASS; staging preflight: 9 groups PASS; checkout security: 23 checks PASS; default-OFF/bootstrap checks PASS. Actual WordPress staging, WooCommerce Place Order/payment, GD rendering, and Drive remain NOT TESTED. The `tests/test_sanro_editor_geometry.js` case was executed with Node (a PHP invocation of a JS file is not a valid test).

**DECIDED:** Pricing DTF remains Codex Cloud-owned. No numeric progress increase is asserted: the user-provided baseline is 71.95%, while the current official seven-stage ledger was not available in this workspace or connected Drive/Pages search. The historical roadmap supplies the seven stage names, not current stage percentages.

## Restore branch PHP lint

Every tracked PHP file was checked with `php -l`; the new guard test was checked separately.

| File | Result |
| --- | --- |
| `plugins/sanro-studio/includes/admin.php` | PASS |
| `plugins/sanro-studio/includes/buyer.php` | PASS |
| `plugins/sanro-studio/includes/cart-adapter.php` | PASS |
| `plugins/sanro-studio/includes/cart-contract.php` | PASS |
| `plugins/sanro-studio/includes/checkout-batch-lock.php` | PASS |
| `plugins/sanro-studio/includes/checkout-bridge.php` | PASS |
| `plugins/sanro-studio/includes/core.php` | PASS |
| `plugins/sanro-studio/includes/draft-rest.php` | PASS |
| `plugins/sanro-studio/includes/draft.php` | PASS |
| `plugins/sanro-studio/includes/drive-auto-provision.php` | PASS |
| `plugins/sanro-studio/includes/drive-master-export.php` | PASS |
| `plugins/sanro-studio/includes/drive-production.php` | PASS |
| `plugins/sanro-studio/includes/dtf-admin-generate.php` | PASS |
| `plugins/sanro-studio/includes/dtf-admin-order-status.php` | PASS |
| `plugins/sanro-studio/includes/dtf-admin-readiness.php` | PASS |
| `plugins/sanro-studio/includes/dtf-image-render.php` | PASS |
| `plugins/sanro-studio/includes/dtf-layer-render.php` | PASS |
| `plugins/sanro-studio/includes/dtf-order-workflow.php` | PASS |
| `plugins/sanro-studio/includes/dtf-private-artifacts.php` | PASS |
| `plugins/sanro-studio/includes/editor-config.php` | PASS |
| `plugins/sanro-studio/includes/master-storage.php` | PASS |
| `plugins/sanro-studio/includes/order-master-source.php` | PASS |
| `plugins/sanro-studio/includes/order-preview.php` | PASS |
| `plugins/sanro-studio/includes/pricing.php` | PASS |
| `plugins/sanro-studio/includes/print-area.php` | PASS |
| `plugins/sanro-studio/includes/private-master-setup.php` | PASS |
| `plugins/sanro-studio/includes/product-area.php` | PASS |
| `plugins/sanro-studio/includes/product-mockups.php` | PASS |
| `plugins/sanro-studio/includes/product-pricing.php` | PASS |
| `plugins/sanro-studio/includes/product-print-zones.php` | PASS |
| `plugins/sanro-studio/includes/production-ready-admin.php` | PASS |
| `plugins/sanro-studio/includes/quote.php` | PASS |
| `plugins/sanro-studio/includes/runtime.php` | PASS |
| `plugins/sanro-studio/includes/storage-policy.php` | PASS |
| `plugins/sanro-studio/sanro-studio.php` | PASS |
| `plugins/sanro-studio/tests/admin-preview-delivery.php` | PASS |
| `plugins/sanro-studio/tests/buyer-asset-version-smoke.php` | PASS |
| `plugins/sanro-studio/tests/cart-adapter-smoke.php` | PASS |
| `plugins/sanro-studio/tests/cart-contract.php` | PASS |
| `plugins/sanro-studio/tests/cart-identity-contract.php` | PASS |
| `plugins/sanro-studio/tests/cart-line-snapshot-smoke.php` | PASS |
| `plugins/sanro-studio/tests/checkout-batch-lock-default-off.php` | PASS |
| `plugins/sanro-studio/tests/checkout-batch-lock-smoke.php` | PASS |
| `plugins/sanro-studio/tests/checkout-bridge.php` | PASS |
| `plugins/sanro-studio/tests/checkout-gate-smoke.php` | PASS |
| `plugins/sanro-studio/tests/draft-cleanup.php` | PASS |
| `plugins/sanro-studio/tests/draft-lock.php` | PASS |
| `plugins/sanro-studio/tests/draft-revision-rest-smoke.php` | PASS |
| `plugins/sanro-studio/tests/drive-auto-default-off.php` | PASS |
| `plugins/sanro-studio/tests/drive-auto-provision-smoke.php` | PASS |
| `plugins/sanro-studio/tests/drive-master-export-default-off.php` | PASS |
| `plugins/sanro-studio/tests/drive-master-export-smoke.php` | PASS |
| `plugins/sanro-studio/tests/drive-production-default-off.php` | PASS |
| `plugins/sanro-studio/tests/drive-production-smoke.php` | PASS |
| `plugins/sanro-studio/tests/dtf-admin-generate-default-off.php` | PASS |
| `plugins/sanro-studio/tests/dtf-admin-generate-smoke.php` | PASS |
| `plugins/sanro-studio/tests/dtf-admin-order-status-default-off.php` | PASS |
| `plugins/sanro-studio/tests/dtf-admin-order-status-smoke.php` | PASS |
| `plugins/sanro-studio/tests/dtf-admin-readiness-default-off.php` | PASS |
| `plugins/sanro-studio/tests/dtf-admin-readiness-smoke.php` | PASS |
| `plugins/sanro-studio/tests/dtf-image-gd-real-smoke.php` | PASS |
| `plugins/sanro-studio/tests/dtf-image-render-default-off.php` | PASS |
| `plugins/sanro-studio/tests/dtf-image-render-smoke.php` | PASS |
| `plugins/sanro-studio/tests/dtf-layer-compose-smoke.php` | PASS |
| `plugins/sanro-studio/tests/dtf-layer-default-off.php` | PASS |
| `plugins/sanro-studio/tests/dtf-locked-snapshot-policy.php` | PASS |
| `plugins/sanro-studio/tests/dtf-order-preflight-policy.php` | PASS |
| `plugins/sanro-studio/tests/dtf-order-workflow-default-off.php` | PASS |
| `plugins/sanro-studio/tests/dtf-order-workflow-real-smoke.php` | PASS |
| `plugins/sanro-studio/tests/dtf-order-workflow-smoke.php` | PASS |
| `plugins/sanro-studio/tests/dtf-private-artifacts-default-off.php` | PASS |
| `plugins/sanro-studio/tests/dtf-private-artifacts-smoke.php` | PASS |
| `plugins/sanro-studio/tests/legacy-isolation.php` | PASS |
| `plugins/sanro-studio/tests/master-binding-smoke.php` | PASS |
| `plugins/sanro-studio/tests/master-read-route-smoke.php` | PASS |
| `plugins/sanro-studio/tests/master-storage-roundtrip.php` | PASS |
| `plugins/sanro-studio/tests/master-storage-smoke.php` | PASS |
| `plugins/sanro-studio/tests/mobile-viewport-smoke.php` | PASS |
| `plugins/sanro-studio/tests/native-core.php` | PASS |
| `plugins/sanro-studio/tests/native-missing-component.php` | PASS |
| `plugins/sanro-studio/tests/native-readiness-gates.php` | PASS |
| `plugins/sanro-studio/tests/native-staging-gate.php` | PASS |
| `plugins/sanro-studio/tests/order-amount-contract.php` | PASS |
| `plugins/sanro-studio/tests/order-lock-boundary.php` | PASS |
| `plugins/sanro-studio/tests/order-preview.php` | PASS |
| `plugins/sanro-studio/tests/preview-upload.php` | PASS |
| `plugins/sanro-studio/tests/print-zone-smoke.php` | PASS |
| `plugins/sanro-studio/tests/private-master-default-off-smoke.php` | PASS |
| `plugins/sanro-studio/tests/private-master-setup-smoke.php` | PASS |
| `plugins/sanro-studio/tests/private-preview-files.php` | PASS |
| `plugins/sanro-studio/tests/product-mockups-smoke.php` | PASS |
| `plugins/sanro-studio/tests/rest-cart-bootstrap-smoke.php` | PASS |
| `plugins/sanro-studio/tests/size-checkout-bridge-smoke.php` | PASS |
| `plugins/sanro-studio/tests/storage-policy.php` | PASS |
| `tests/drive-fixture.php` | PASS |
| `tests/fixture.php` | PASS |
| `tests/router.php` | PASS |
| `tests/sanro-core-boot-contract.php` | PASS |
| `tests/sanro-studio-fixture.php` | PASS |
| `tests/sanro-vendor-contract.php` | PASS |
| `tests/sanro-wp-integration-smoke.php` | PASS |
| `tests/staging-dtf-admin-bootstrap-plan-smoke.php` | PASS |
| `tests/staging-hpos-order-metadata-readonly.php` | PASS |
| `tests/staging-hpos-qa-order-policy-smoke.php` | PASS |
| `tests/staging-order-item-binding-offline.php` | PASS |
| `tests/staging-pr35-apply-guard-smoke.php` | PASS |
| `tests/staging-pr35-runtime-output-smoke.php` | PASS |
| `tests/staging-pr37-readiness-preview-smoke.php` | PASS |
| `tests/staging-pr38-disabled-deploy-guard-smoke.php` | PASS |
| `tests/staging-pr41-twofile-plan-policy.php` | PASS |
| `tests/staging-pr42-twofile-apply-policy.php` | PASS |
| `tests/staging-targeted-bootstrap-smoke.php` | PASS |
| `tests/studio-bootstrap-offline.php` | PASS |
| `tests/test_drive_security.php` | PASS |
| `tests/test_editable_fork.php` | PASS |
| `tests/test_lock_security.php` | PASS |
| `tests/test_order_snapshot.php` | PASS |
| `tests/test_owner_verification.php` | PASS |
| `tests/test_price_presets.php` | PASS |
| `tests/test_repeat.php` | PASS |
| `tests/test_repeat_checkout.php` | PASS |
| `tests/test_rules.php` | PASS |
| `tests/test_sanro_buyer_access.php` | PASS |
| `tests/test_sanro_buyer_gate.php` | PASS |
| `tests/test_sanro_buyer_render.php` | PASS |
| `tests/test_sanro_checkout_bridge.php` | PASS |
| `tests/test_sanro_checkout_guards.php` | PASS |
| `tests/test_sanro_checkout_process.php` | PASS |
| `tests/test_sanro_draft.php` | PASS |
| `tests/test_sanro_draft_preferences.php` | PASS |
| `tests/test_sanro_draft_rest.php` | PASS |
| `tests/test_sanro_editor_config.php` | PASS |
| `tests/test_sanro_font_metadata.php` | PASS |
| `tests/test_sanro_order_item_preview.php` | PASS |
| `tests/test_sanro_order_preview.php` | PASS |
| `tests/test_sanro_pricing.php` | PASS |
| `tests/test_sanro_print_area.php` | PASS |
| `tests/test_sanro_product_area.php` | PASS |
| `tests/test_sanro_product_pricing.php` | PASS |
| `tests/test_sanro_production_readiness.php` | PASS |
| `tests/test_sanro_quote.php` | PASS |
| `tests/test_sanro_runtime.php` | PASS |
| `tests/test_sanro_staging_cart_gate.php` | PASS |
| `tests/test_sanro_studio.php` | PASS |
| `tests/test_sanro_text_color.php` | PASS |
| `tests/test_sanro_text_control.php` | PASS |
| `tests/test_server_validation.php` | PASS |
| `tests/test_share_clone.php` | PASS |
| `tests/test_share_security.php` | PASS |
| `tests/test_upgrade_settings.php` | PASS |
| `tests/variable_integration.php` | PASS |
| `tests/wordpress_regression.php` | PASS |
| `tests/wordpress_smoke.php` | PASS |
| `tests/studio-restore-default-off.php` | PASS |

## Root `tests/` PHP and Node scripts

| File | Result | Detail |
| --- | --- | --- |
| `tests/customer-flow.cjs` | BLOCKED | External staging, WordPress, or Docker fixture required; not executed |
| `tests/drive-fixture.php` | HELPER | Fixture/helper, not a standalone test |
| `tests/fixture.php` | HELPER | Fixture/helper, not a standalone test |
| `tests/lifecycle.cjs` | BLOCKED | External staging, WordPress, or Docker fixture required; not executed |
| `tests/router.php` | HELPER | Fixture/helper, not a standalone test |
| `tests/sanro-core-boot-contract.php` | FAIL |   thrown in /home/sanro/Plugin-Kaoscetak/tests/sanro-core-boot-contract.php on line 28 |
| `tests/sanro-studio-fixture.php` | HELPER | Fixture/helper, not a standalone test |
| `tests/sanro-studio-staging-preflight-node.cjs` | FAIL | Node.js v22.22.1 |
| `tests/sanro-vendor-contract.php` | FAIL | Set SANRO_LUMISE_CONFIG_FILE to the private 2.1.2 config source |
| `tests/sanro-wp-integration-smoke.php` | BLOCKED | External staging, WordPress, or Docker fixture required; not executed |
| `tests/staging-admin-guards-node.cjs` | PASS | PASS: 30 SANRO staging admin Playwright offline security guards |
| `tests/staging-admin-order-readonly.cjs` | BLOCKED | External staging, WordPress, or Docker fixture required; not executed |
| `tests/staging-admin-print-zones-readonly.cjs` | BLOCKED | External staging, WordPress, or Docker fixture required; not executed |
| `tests/staging-buyer-auth-policy-node.cjs` | PASS | PASS: 13 SANRO authenticated staging buyer E2E safeguards |
| `tests/staging-buyer-e2e.cjs` | BLOCKED | External staging, WordPress, or Docker fixture required; not executed |
| `tests/staging-cart-checkout-e2e.cjs` | BLOCKED | External staging, WordPress, or Docker fixture required; not executed |
| `tests/staging-checkout-milestone90-security.cjs` | FAIL | Node.js v22.22.1 |
| `tests/staging-discover-and-test.cjs` | BLOCKED | External staging, WordPress, or Docker fixture required; not executed |
| `tests/staging-draft-e2e.cjs` | BLOCKED | External staging, WordPress, or Docker fixture required; not executed |
| `tests/staging-dtf-admin-bootstrap-plan-smoke.php` | FAIL | Missing companion source in integration base; see local test log |
| `tests/staging-hpos-order-metadata-readonly.php` | BLOCKED | External staging, WordPress, or Docker fixture required; not executed |
| `tests/staging-hpos-qa-order-policy-smoke.php` | FAIL | Missing companion source in integration base; see local test log |
| `tests/staging-image-checkout-e2e.cjs` | BLOCKED | External staging, WordPress, or Docker fixture required; not executed |
| `tests/staging-image-metadata-readonly-diagnostic.cjs` | BLOCKED | External staging, WordPress, or Docker fixture required; not executed |
| `tests/staging-image-metadata-readonly-policy.cjs` | PASS | PASS: 16 SANRO staging image metadata read-only diagnostic safeguards |
| `tests/staging-image-partial-master-helper.cjs` | HELPER | Fixture/helper, not a standalone test |
| `tests/staging-image-partial-master-policy.cjs` | PASS | PASS: 22 SANRO persisted front master resume safeguards (offline synthetic) |
| `tests/staging-image-private-draft-node.cjs` | PASS | PASS: 15 owner-private DTF QA reuse and permission checks |
| `tests/staging-image-private-draft.cjs` | BLOCKED | External staging, WordPress, or Docker fixture required; not executed |
| `tests/staging-image-text-qa-reuse-policy.cjs` | PASS | PASS: 23 SANRO image reuse of text QA draft safeguards (offline synthetic) |
| `tests/staging-master-text-float-guard.cjs` | PASS | PASS: 21 SANRO text x/y tolerance safeguards (offline actual runtime code) |
| `tests/staging-mobile-readonly.cjs` | BLOCKED | External staging, WordPress, or Docker fixture required; not executed |
| `tests/staging-order-item-binding-offline.php` | PASS | PASS: 27 SANRO offline unsaved Woo order-item binding assertions (NO ORDER, NO DB) |
| `tests/staging-pr35-apply-guard-smoke.php` | FAIL | Missing companion source in integration base; see local test log |
| `tests/staging-pr35-runtime-output-smoke.php` | FAIL | Missing companion source in integration base; see local test log |
| `tests/staging-pr37-readiness-preview-smoke.php` | FAIL | Missing companion source in integration base; see local test log |
| `tests/staging-pr38-disabled-deploy-guard-smoke.php` | FAIL | Missing companion source in integration base; see local test log |
| `tests/staging-pr41-twofile-plan-policy.php` | FAIL | Missing companion source in integration base; see local test log |
| `tests/staging-pr42-twofile-apply-policy.php` | FAIL | Missing companion source in integration base; see local test log |
| `tests/staging-session-capture-node.cjs` | PASS | PASS: 32 SANRO secure staging login session policy checks |
| `tests/staging-targeted-bootstrap-smoke.php` | FAIL | Missing companion source in integration base; see local test log |
| `tests/studio-bootstrap-offline.php` | PASS | PASS: SANRO Studio isolated admin bootstrap contract |
| `tests/test_drive_security.php` | FAIL | Missing companion source in integration base; see local test log |
| `tests/test_editable_fork.php` | FAIL | Missing companion source in integration base; see local test log |
| `tests/test_editor_unit.cjs` | FAIL | Node.js v22.22.1 |
| `tests/test_lock_security.php` | FAIL | Missing companion source in integration base; see local test log |
| `tests/test_order_snapshot.php` | FAIL | Missing companion source in integration base; see local test log |
| `tests/test_owner_verification.php` | FAIL | Missing companion source in integration base; see local test log |
| `tests/test_price_presets.php` | FAIL | Missing companion source in integration base; see local test log |
| `tests/test_repeat.php` | FAIL | Missing companion source in integration base; see local test log |
| `tests/test_repeat_checkout.php` | FAIL | Missing companion source in integration base; see local test log |
| `tests/test_rules.php` | FAIL | Missing companion source in integration base; see local test log |
| `tests/test_sanro_buyer_access.php` | PASS | SANRO buyer access: 9 assertions PASS, 0 FAIL |
| `tests/test_sanro_buyer_gate.php` | PASS | SANRO buyer gate: 3 assertions PASS, 0 FAIL |
| `tests/test_sanro_buyer_render.php` | FAIL | FAIL: header and footer |
| `tests/test_sanro_checkout_bridge.php` | PASS | SANRO checkout bridge: 4 assertions PASS, 0 FAIL |
| `tests/test_sanro_checkout_guards.php` | PASS | SANRO Sprint 21 checkout guards: 6 assertions PASS, 0 FAIL |
| `tests/test_sanro_checkout_process.php` | PASS | SANRO Sprint 22 checkout process: 8 assertions PASS, 0 FAIL |
| `tests/test_sanro_draft.php` | PASS | SANRO draft ID: 27 assertions PASS, 0 FAIL |
| `tests/test_sanro_draft_preferences.php` | PASS | SANRO draft preferences: 7 assertions PASS, 0 FAIL |
| `tests/test_sanro_draft_rest.php` | PASS | SANRO draft REST: 35 assertions PASS, 0 FAIL |
| `tests/test_sanro_editor_config.php` | PASS | SANRO editor REST config: 26 assertions PASS, 0 FAIL |
| `tests/test_sanro_editor_geometry.js` | FAIL | Missing companion source in integration base; see local test log |
| `tests/test_sanro_estimate.js` | PASS | SANRO estimator: 366 assertions PASS, 0 FAIL |
| `tests/test_sanro_font_metadata.php` | PASS | SANRO font metadata: 8 assertions PASS, 0 FAIL |
| `tests/test_sanro_order_item_preview.php` | PASS | SANRO item preview isolation: 6 assertions PASS, 0 FAIL |
| `tests/test_sanro_order_preview.php` | PASS | SANRO order preview inventory: 4 assertions PASS, 0 FAIL |
| `tests/test_sanro_pricing.php` | PASS | SANRO 24s price matrix: 356 assertions PASS, 0 FAIL |
| `tests/test_sanro_pricing_parity.js` | FAIL | FAIL: Node child_process.spawnSync(php) returned EPERM in sandbox |
| `tests/test_sanro_print_area.php` | PASS | SANRO print bounds: 28 assertions PASS, 0 FAIL |
| `tests/test_sanro_product_area.php` | PASS | SANRO product areas: 16 assertions PASS, 0 FAIL |
| `tests/test_sanro_product_pricing.php` | PASS | SANRO product pricing profile: 7 assertions PASS, 0 FAIL |
| `tests/test_sanro_production_readiness.php` | PASS | SANRO production readiness: 14 assertions PASS, 0 FAIL |
| `tests/test_sanro_quote.php` | PASS | SANRO quote integration: 31 assertions PASS, 0 FAIL |
| `tests/test_sanro_runtime.php` | FAIL |   thrown in /home/sanro/Plugin-Kaoscetak/tests/test_sanro_runtime.php on line 12 |
| `tests/test_sanro_staging_cart_gate.php` | PASS | SANRO staging cart default gate: 10 assertions PASS, 0 FAIL |
| `tests/test_sanro_studio.php` | FAIL |   thrown in /home/sanro/Plugin-Kaoscetak/tests/sanro-studio-fixture.php on line 62 |
| `tests/test_sanro_text_color.php` | PASS | SANRO text color: 8 assertions PASS, 0 FAIL |
| `tests/test_sanro_text_control.php` | PASS | SANRO text control bytes: 6 assertions PASS, 0 FAIL |
| `tests/test_server_validation.php` | FAIL | Missing companion source in integration base; see local test log |
| `tests/test_share_clone.php` | FAIL | Missing companion source in integration base; see local test log |
| `tests/test_share_security.php` | FAIL | Missing companion source in integration base; see local test log |
| `tests/test_upgrade_settings.php` | FAIL | Missing companion source in integration base; see local test log |
| `tests/variable_integration.php` | FAIL |   thrown in /home/sanro/Plugin-Kaoscetak/tests/variable_integration.php on line 2 |
| `tests/wordpress_regression.php` | BLOCKED | External staging, WordPress, or Docker fixture required; not executed |
| `tests/wordpress_smoke.php` | BLOCKED | External staging, WordPress, or Docker fixture required; not executed |
| `tests/studio-restore-default-off.php` | PASS | default-off flags and no frontend/staging cart hooks |

## `plugins/sanro-studio/tests/` PHP and Node scripts

| File | Result | Detail |
| --- | --- | --- |
| `plugins/sanro-studio/tests/admin-mockups-node.cjs` | PASS | PASS: 15 SANRO product mockup chooser/preview checks |
| `plugins/sanro-studio/tests/admin-preview-delivery.php` | PASS | SKIP: GD missing; fixture-dependent assertions not executed |
| `plugins/sanro-studio/tests/admin-print-zones-node.cjs` | PASS | PASS: 15 SANRO live admin print-zone preview checks |
| `plugins/sanro-studio/tests/buyer-asset-version-smoke.php` | PASS | PASS: 3 SANRO Studio buyer asset fingerprint assertions |
| `plugins/sanro-studio/tests/buyer-cart-handler-node.cjs` | PASS | PASS: 8 delegated Cart handler regression checks |
| `plugins/sanro-studio/tests/buyer-image-preview-node.cjs` | PASS | PASS: 7 private DTF image-preview and master-binding checks |
| `plugins/sanro-studio/tests/buyer-print-zone-draft-node.cjs` | PASS | PASS: 18 side-specific print-zone draft geometry checks |
| `plugins/sanro-studio/tests/cart-adapter-smoke.php` | PASS | PASS: 74 staging cart adapter checks |
| `plugins/sanro-studio/tests/cart-contract.php` | FAIL | FAIL: server quote retained |
| `plugins/sanro-studio/tests/cart-identity-contract.php` | PASS | PASS: negative_total |
| `plugins/sanro-studio/tests/cart-line-snapshot-smoke.php` | PASS | PASS: 24 isolated cart-line checks |
| `plugins/sanro-studio/tests/cart-redirect-node.cjs` | PASS | PASS: 18 SANRO Studio cart redirect checks |
| `plugins/sanro-studio/tests/checkout-batch-lock-default-off.php` | PASS | PASS: SANRO staging checkout batch lock defaults disabled |
| `plugins/sanro-studio/tests/checkout-batch-lock-smoke.php` | PASS | PASS: 31 SANRO staging batch draft-lock checks |
| `plugins/sanro-studio/tests/checkout-bridge.php` | PASS | PASS: existing_design |
| `plugins/sanro-studio/tests/checkout-gate-smoke.php` | PASS | PASS: 43 checkout gate checks |
| `plugins/sanro-studio/tests/draft-cleanup.php` | PASS | PASS: unexpired record is retained |
| `plugins/sanro-studio/tests/draft-lock.php` | FAIL | FAIL: mismatched order quantity denied |
| `plugins/sanro-studio/tests/draft-revision-rest-smoke.php` | PASS | PASS: 18 draft REST and image-binding checks |
| `plugins/sanro-studio/tests/drive-auto-default-off.php` | PASS | PASS: SANRO Drive automatic provisioning defaults disabled |
| `plugins/sanro-studio/tests/drive-auto-provision-smoke.php` | PASS | PASS: 23 SANRO Drive auto-provision regression checks |
| `plugins/sanro-studio/tests/drive-master-export-default-off.php` | PASS | PASS: SANRO private master Drive export defaults disabled |
| `plugins/sanro-studio/tests/drive-master-export-smoke.php` | PASS | PASS: 24 SANRO private master Drive export regression checks |
| `plugins/sanro-studio/tests/drive-production-default-off.php` | PASS | PASS: SANRO Drive remote verification defaults disabled |
| `plugins/sanro-studio/tests/drive-production-smoke.php` | PASS | PASS: 33 private SANRO Drive folder metadata and admin link checks |
| `plugins/sanro-studio/tests/dtf-admin-generate-default-off.php` | PASS | PASS: 6 SANRO admin DTF generation default-OFF checks |
| `plugins/sanro-studio/tests/dtf-admin-generate-smoke.php` | PASS | PASS: 34 SANRO nonce-protected DTF admin candidate generation checks |
| `plugins/sanro-studio/tests/dtf-admin-order-status-default-off.php` | PASS | PASS: SANRO WooCommerce admin DTF candidate status defaults disabled |
| `plugins/sanro-studio/tests/dtf-admin-order-status-smoke.php` | PASS | PASS: 32 SANRO WooCommerce admin DTF status access checks |
| `plugins/sanro-studio/tests/dtf-admin-readiness-default-off.php` | PASS | PASS: 5 SANRO DTF readiness default-OFF checks |
| `plugins/sanro-studio/tests/dtf-admin-readiness-smoke.php` | PASS | PASS: 29 SANRO read-only admin DTF eligibility checks |
| `plugins/sanro-studio/tests/dtf-image-gd-real-smoke.php` | FAIL | FAIL: real PHP GD extension is mandatory for Sprint 134 |
| `plugins/sanro-studio/tests/dtf-image-render-default-off.php` | PASS | PASS: SANRO DTF RGBA image raster defaults disabled |
| `plugins/sanro-studio/tests/dtf-image-render-smoke.php` | PASS | PASS: 24 SANRO image-only DTF raster safety checks |
| `plugins/sanro-studio/tests/dtf-layer-compose-smoke.php` | PASS | PASS: 18 SANRO layered DTF composition checks |
| `plugins/sanro-studio/tests/dtf-layer-default-off.php` | PASS | PASS: SANRO layered DTF raster defaults disabled |
| `plugins/sanro-studio/tests/dtf-locked-snapshot-policy.php` | PASS | PASS: 14 SANRO locked DTF draft fingerprint policy checks |
| `plugins/sanro-studio/tests/dtf-order-preflight-policy.php` | PASS | PASS: 12 SANRO two-side no-write preflight policy checks |
| `plugins/sanro-studio/tests/dtf-order-workflow-default-off.php` | PASS | PASS: SANRO DTF order batch defaults disabled |
| `plugins/sanro-studio/tests/dtf-order-workflow-real-smoke.php` | FAIL | Missing companion source in integration base; see local test log |
| `plugins/sanro-studio/tests/dtf-order-workflow-smoke.php` | PASS | PASS: 40 SANRO order-item DTF batch and private status checks |
| `plugins/sanro-studio/tests/dtf-private-artifacts-default-off.php` | PASS | PASS: SANRO DTF private artifact storage defaults disabled |
| `plugins/sanro-studio/tests/dtf-private-artifacts-smoke.php` | PASS | PASS: 28 SANRO order-bound private DTF artifact checks |
| `plugins/sanro-studio/tests/legacy-isolation.php` | PASS | PASS: legacy module isolated and native release gates closed |
| `plugins/sanro-studio/tests/master-binding-smoke.php` | PASS | PASS: 8 private-master binding regression checks |
| `plugins/sanro-studio/tests/master-read-route-smoke.php` | PASS | PASS: 20 short-route private DTF read security checks |
| `plugins/sanro-studio/tests/master-storage-roundtrip.php` | PASS | PASS: 15 isolated private-master front/back roundtrip checks |
| `plugins/sanro-studio/tests/master-storage-smoke.php` | PASS | PASS: 6 master-storage regression checks |
| `plugins/sanro-studio/tests/mobile-viewport-smoke.php` | PASS | PASS: 10 SANRO buyer viewport/CSS checks |
| `plugins/sanro-studio/tests/native-core.php` | FAIL | FAIL: native function inventory detects provided fixtures |
| `plugins/sanro-studio/tests/native-missing-component.php` | PASS | PASS: missing order manifest blocks staging readiness |
| `plugins/sanro-studio/tests/native-readiness-gates.php` | PASS | missing_woocommerce, buyer_disabled, buyer_enabled: all PASS with required arguments |
| `plugins/sanro-studio/tests/native-staging-gate.php` | PASS | PASS: staging gate enabled, production release gates closed |
| `plugins/sanro-studio/tests/order-amount-contract.php` | PASS | PASS: array_design |
| `plugins/sanro-studio/tests/order-lock-boundary.php` | PASS | PASS: binder_invalid_owner_token |
| `plugins/sanro-studio/tests/order-preview.php` | PASS | PASS: already-ready order does not show duplicate action |
| `plugins/sanro-studio/tests/preview-upload.php` | PASS | PASS: draft API disabled without explicit flag |
| `plugins/sanro-studio/tests/print-zone-smoke.php` | PASS | PASS: SANRO per-product print overlay, security and admin controls (24 checks) |
| `plugins/sanro-studio/tests/private-master-default-off-smoke.php` | PASS | PASS: 3 default-disabled SANRO private-master gate checks |
| `plugins/sanro-studio/tests/private-master-setup-smoke.php` | PASS | PASS: 11 isolated SANRO auto-private installer checks |
| `plugins/sanro-studio/tests/private-preview-files.php` | PASS | SKIP: GD unavailable; image fixture not generated |
| `plugins/sanro-studio/tests/product-mockups-smoke.php` | PASS | PASS: SANRO product mockup metadata and admin controls (30 assertions) |
| `plugins/sanro-studio/tests/rest-cart-bootstrap-smoke.php` | PASS | PASS: 42 staging REST cart-bootstrap checks |
| `plugins/sanro-studio/tests/size-checkout-bridge-smoke.php` | PASS | PASS: 13 isolated size-checkout checks |
| `plugins/sanro-studio/tests/storage-policy.php` | PASS | PASS: preview read is disabled by default |

## Pricing profile branch check

`php -l`: 9 PASS, 0 FAIL.

| File | Result |
| --- | --- |
| `plugins/sanro-studio/includes/buyer.php` | PASS |
| `plugins/sanro-studio/includes/editor-config.php` | PASS |
| `plugins/sanro-studio/includes/pricing.php` | PASS |
| `plugins/sanro-studio/includes/print-area.php` | PASS |
| `plugins/sanro-studio/includes/product-area.php` | PASS |
| `plugins/sanro-studio/includes/product-pricing.php` | PASS |
| `plugins/sanro-studio/sanro-studio.php` | PASS |
| `tests/studio-bootstrap-offline.php` | PASS |
| `tests/studio-product-pricing-offline.php` | PASS |

| Offline test | Result |
| --- | --- |
| `tests/studio-bootstrap-offline.php` | PASS |
| `tests/studio-product-pricing-offline.php` | PASS |
