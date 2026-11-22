# SANRO Studio — STATUS

Updated: 2026-11-23
Single reported overall progress: **89.4%** (historical estimate; not reverified).
Source of truth: this file. Never increase progress for plans, PR counts, or untested changes.

## Vertical slice: buyer design → cart → one synthetic checkout → private production file
| Deliverable | State | Evidence / next acceptance |
|---|---|---|
| Front/back editor and saved draft | IMPLEMENTED | Buyer editor and draft endpoints; staging image E2E still pending |
| DTF quote and cart | IMPLEMENTED | Staging-only integration; verify same design/price through Woo cart |
| Single synthetic WooCommerce order | IMPLEMENTED | Checkout bridge and batch lock code; new end-to-end order NOT VERIFIED |
| Admin front/back preview per order line | IMPLEMENTED | order-preview.php, commits 6ec389e, bc8acaa; staging readback NOT VERIFIED |
| Private production file | DECIDED | Deliver one accessible, integrity-checked private artifact before marking VERIFIED |
| Google Drive export / automatic DTF 300 DPI | DECIDED | Deferred until vertical slice is VERIFIED; flags remain OFF |

## Integration and evidence
- Integration target: **develop**; creation via GitHub connector failed twice (HTTP 422 Reference update failed). Do not substitute a stacked PR.
- Current source branch: `dev/staging-one-order-gate-20261122`; this file is a checkpoint, **not** a merge or production deployment.
- PR #34, #37, #41 closed as plan-only; #28, #29, #30 closed as QA-only after file inventory review. Remaining PRs require diff review before closure or selective reuse.
- Last code changes: `6ec389e` order-line preview preflight; `bc8acaa` two-sided production readiness; `6a195e` checkout binding requirement.
- Fast single-command offline check added at `scripts/sanro-studio-slice-check.sh` (`9a6998f`); **NOT RUN** on a local PHP runtime yet.\n- Latest local test for those changes: **NOT TESTED**. Historical 143 PASS does not certify them.
- Approval required before merging to develop, merging to main, production deployment, live DB modification, or disabling the old plugin.

## Acceptance and workflow
1. One branch directly off develop; no PR-on-PR; one usable result per change, approximately <=400 lines including tests.
2. Run one fast local unit/contract command per change; stage E2E only after the slice is complete.
3. Verify one synthetic order end-to-end with design ID, DTF price, admin front/back previews, and private production artifact; record commit and test result here.
4. Status terms: DECIDED = approved specification; IMPLEMENTED = committed working code not yet runtime-verified; VERIFIED = reproducible staging proof.
