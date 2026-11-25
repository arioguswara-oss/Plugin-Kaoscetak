# SANRO Studio UI/UX P1 correction and staging installation — 25 November 2026

## Result

The P1 UI correction was built, installed, and verified on staging. The source commit is `826c152c3dbf2e35fff74d276e9c2fd00aed2905` on PR #52. SANRO Studio remains version `1.1.0`; this is a corrected staging candidate, not a Final Release. Owner browser visual acceptance remains pending.

The primary visual references remain the two Owner concepts in `artifacts/studio-v1/1.1.0-ui/review-20261125/references/`. The independent review and screenshots are retained unchanged.

## Review corrections

- V01: the shared root/submenu callback no longer duplicates the administrator summary; root access remains capability-guarded.
- V02: theme chrome suppression is limited to SANRO Studio buyer pages.
- V03: “Desain Saya” now separates save and email access and supplies private, loading, empty, and error guidance.
- V04: saving a draft with a local image gives a clear next step to upload the print master and opens its panel.
- V05: the remove-background control refreshes after the local file binding is cleared.
- V06: validation explains the affected side, object type, and repair control.
- V07: the original desktop review had no real phone/touch verification. Targeted portrait Chromium checks now cover 390px and 320px layouts, canvas proportion, overflow, touch target sizing, drag, and resize. This synthetic check is not Owner visual acceptance.
- Requested admin refinements: Studio admin pages use one scoped navigation surface and improved responsive hierarchy for settings, products/mockups, print areas, prices, storage, and order detail. Existing operations, server pricing, authorization, and WooCommerce hooks are retained.

Interaction changes preserve the existing API, server quote authority, design validation, private image/master binding, and WooCommerce integration. No payment, order, checkout, DTF, or Drive operation was performed.

## Targeted checks

PHP menu/permissions/order-hook contract: 71 assertions PASS. Buyer render: 20 PASS. Print area: 28 PASS. Buyer startup: 15 PASS. Private preview/master binding: 10 PASS. Changed PHP and JS syntax: PASS. Local Chromium responsive/layout/drag/resize harness: PASS. The native-buyer browser fixture requiring local PHP GD was not run because this machine’s CLI PHP lacks GD; no app dependency was changed. Full Audit was not repeated.

## Digest validation and installation

The actual current staging tree before install was `0c7900e3b5779b19897ad8f1443f4e0bee8fd0b377ffa35f27bb993b68116301`. It matched the previously installed V1.1.0 candidate manifest, ZIP inventory, member hashes, and build marker. The earlier approved V1.0.0 Final Release manifest remains independently recorded as matching its own tree digest `c3524d76a6992179716946b9c353e98d9baad11e13feb8bfa24d387fe383db61` in the preceding installation evidence; this historical digest was not substituted for the live V1.1.0 tree.

The builder accepts only that exact approved V1.0.0 Final manifest or the exact previously installed V1.1.0 staging candidate manifest/archive. It verifies archive inventory, all member hashes, build marker, derived tree, and equality with the fresh SSH-computed live digest. The transport reproduced twice with SHA256 `971e8d3f0218663f01430ecbabc720ff9616c9f88694f41582934bd92026e8e2`.

The mandatory pre-install HTTPS gate passed on web SAPI LiteSpeed PHP 8.3.37. It confirmed exact staging identity, current tree digest, committed prior journal/backup, private storage, and all writer flags OFF. The source-pinned install action then committed under lock, retained rollback, and left activation and flags unchanged.

Post-install HTTPS gate and runtime readiness passed. Product 24’s editor configuration was valid. Buyer CSS/JS returned HTTP 200 and their downloaded SHA256 values matched the new candidate. Final SSH readback verified active plugin version 1.1.0 and installed tree digest `f746c3928b96b5c6ca20cb04a7d5615a2f636799d2f88939c93fc4cadb88f049`, matching the candidate manifest and build marker.

The temporary release transport was restored to version 0.3.3 and inactive; its pre-update tree digest matched the backup archive. The temporary HTTPS diagnostic app password was revoked and its private temporary files removed. The committed transaction rollback remains retained by the installer.

## Boundaries

Only staging was accessed. Production and merge were not accessed. Checkout/order/payment, DTF, Google Drive OAuth, Full Audit, and Owner browser visual acceptance were not performed. Owner visual acceptance remains the next step.

Detailed hashes and sanitized execution evidence are in [`artifacts/studio-v1/1.1.0-ui-p1-20261125/`](../../artifacts/studio-v1/1.1.0-ui-p1-20261125/).
