# SANRO Studio — Integration Status

Updated: 2026-11-23
Overall progress: **89.4%** (historical estimate; unchanged pending runtime verification).

## Branch policy
- Integration branch: `integration/sanro-studio`, created from `main` and verified identical at baseline.
- Feature branches must target this integration branch directly. No stacked PRs.
- Do not merge into integration or main without Owner approval and local tests PASS.
- No production deployment, live database changes, or disabling legacy designer without explicit approval.

## Vertical slice acceptance
Buyer creates and reopens front/back design → adds verified DTF quote to cart → checks out **one synthetic staging order** → admin sees front/back previews → private production artifact is accessible to authorized admin.

| Feature | Status | Evidence / gap |
|---|---|---|
| Editor, draft, front/back preview | IMPLEMENTED | Existing feature branches; integration pending |
| DTF pricing, cart, checkout bridge | IMPLEMENTED | Existing checkout branch; integration and staging E2E pending |
| Admin order previews | IMPLEMENTED | Existing feature branch; staging readback pending |
| Private production file | DECIDED | Verify actual file and authorization before marking VERIFIED |
| DTF 300 DPI, Drive automation | DECIDED | Deferred, feature flags OFF |

## Evidence
- Checkout source branch: `dev/staging-one-order-gate-20261122`.
- Local fast check: `bash scripts/sanro-studio-slice-check.sh` on source branch; **NOT RUN**.
- Latest preview status fix: `49cb791`; regression tests: `e01bdc5`; **NOT RUN**.
- Historical 143 PASS / 0 FAIL is not proof for the latest source.
- PR #28–30, #34, #37, #41 closed as obsolete QA/plan-only branches.
- This STATUS file records the integration baseline, not a completed feature integration.

## Next
1. Review/select minimal functional files for first editor → cart → checkout vertical slice.
2. Prepare a small non-stacked PR targeting `integration/sanro-studio`.
3. Run local contract checks and request Owner approval before merge.
4. Perform one staging E2E after slice is integrated and staging changes are authorized.
