# SANRO Studio V1 completion sprint — 24 November 2026

Checkpoint source: `3d8247563799ba431bb5fd6ab26fe662f783a424`; PR #52. Existing installed candidate: `fad47c6f508258d594f92daca79c337d6911b404`.

## V1 status from source and evidence

| Area | Implementation | Evidence status | Remaining work |
|---|---|---|---|
| Buyer editor, front/back, draft/master/preview, multi-size quote | Implemented | Staging desktop/mobile PASS; not repeated in this sprint | None identified |
| Checkout and Woo order binding/recovery | Implemented: quote lock, retry/recovery, payment-complete hooks, item metadata/previews, shipping snapshot and policy guards | Offline Woo fixtures PASS; real checkout/order/payment NOT TESTED | One synthetic order and sandbox payment in the integrated staging session |
| DTF production candidate | Implemented: locked order snapshot, image/raster planner, provenance/hash, private artifacts and approval gate | Local rendering/workflow fixtures PASS; Hostinger order-bound generation NOT TESTED | Verify same synthetic order through the integrated staging session; physical print/RIP parity is outside this gate |
| Google Drive | Implemented: provisioning, private-user permissions, upload, verification, idempotency/recovery | HTTP doubles PASS; real OAuth identity, folder permissions, remote upload/recovery NOT TESTED | Verify one private staging folder and same order package during the integrated session |
| Storage GC | Implemented with protected orphan checks and explicit opt-in | Offline checks PASS; runtime GC intentionally NOT TESTED | Keep OFF during V1 integrated session |
| Admin menu | Implemented as one independent SANRO Studio root; no SANRO submenu under WooCommerce | Live WP-CLI menu registration PASS; authenticated visual screenshot NOT TESTED | Capture screenshot during the integrated session |

No unfinished V1 implementation gap was found in checkout, order binding, DTF package generation, or Drive workflows. Remaining items are external-runtime verification and unresolved merchant configuration, not absent source features. Tax/fee/coupon rules and unsupplied product price tables remain Owner-open and source fail-closed. Native variations, Blocks, mixed-design carts, live payment settlement, physical printing and complex font shaping remain out of V1 scope or require separate product decisions.

## One integrated staging approval

The approval request is [integrated-staging-e2e-approval.json](../../artifacts/studio-v1/fad47c6/integrated-staging-e2e-approval.json). It binds one staging host, the already-installed candidate hashes and tree digest, one synthetic order maximum, sandbox-only payment, private staging Drive upload and DTF candidate generation. GC stays OFF; no install, production access, physical print, merge or customer data is in scope. Approval is pending and no writer has been activated. If the sandbox gateway or staging-only Drive identity cannot be verified, checkout/order creation stops before the first write.

The current installed package identities are reused from [the installation record](../../artifacts/studio-v1/fad47c6/approval-request.json). No new plugin ZIP is needed because this sprint found no plugin-source defect and does not change plugin files.

## Measurable QA/release/mobile rubric

Stage 7 uses non-overlapping release evidence only; it does not re-award implementation points already carried by stages 1–6.

| Criterion | Points | Earned | Evidence |
|---|---:|---:|---|
| Targeted regression/build and package integrity | 15 | 15 | Existing source-targeted suites and pinned candidate package/checksums PASS |
| Staging install identity, runtime and rollback/journal | 15 | 15 | Exact candidate installed; staging identity, installed tree, journal/lock, plugin and asset fingerprints PASS |
| Buyer browser coverage: desktop/mobile, both sides, private preview and server quote | 25 | 25 | Latest installed-candidate browser evidence PASS; not repeated |
| Authenticated visual admin-menu evidence | 10 | 0 | NOT TESTED; live menu structure is verified by WP-CLI only |
| Woo checkout/order/payment lifecycle on staging | 15 | 0 | NOT TESTED; one-order approval pending |
| Order-bound DTF private candidate generation on staging | 10 | 0 | NOT TESTED; local fixtures do not count as staging runtime evidence |
| Private Drive OAuth/upload/recovery on staging | 10 | 0 | NOT TESTED; HTTP doubles do not count as remote verification |
| **Stage 7** | **100** | **55** | **55%** |

This evidence-based release rubric replaces the undocumented historical stage-7 estimate of 40%. The seven-stage weighted total is therefore **92.00%** (stage 7 contributes 5.50 points versus 4.00; the historical 0.15 Owner reconciliation remains unchanged). No buyer test was rerun and no criterion is counted in two stages.

## Result

- Implementation gap: **none found** in the requested checkout/Woo/DTF/Drive V1 paths; source behavior is present and guarded.
- Untested runtime: checkout/order/sandbox payment, order-bound DTF output, real Drive permissions/upload/recovery and authenticated admin screenshot.
- Writers: checkout, DTF and Drive **OFF**; GC **OFF**.
- Integrated activation: **NO-GO until one Owner approval** of the attached exact-scope request and successful fresh pre-write gates.
- Production, new install, merge, live payment and customer/order data: **out of scope**.
