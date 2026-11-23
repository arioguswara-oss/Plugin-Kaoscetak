# Lead Developer integration handoff — 2026-11-23

Ownership and immutable inputs:

- Pricing Draft PR #51: `work/claude-sanro-studio-product-pricing-profile-20261123`,
  checkpoint `b39d13f`, adapter delta `c4dd7155dd4ba800aa47d7542465f6e849520d26`.
- CI Draft PR #48: `work/codex-studio-ci-20261123`; this document and runner update
  belong to that branch. Use its final PR head, not the older duplicate runner
  copied into PR #51 at its initial checkpoint.
- Read-only restore reference PR #50:
  `1d35edac388a9317832669d5a9ca0020b2c8013d`. No writes to that branch/ref.
  Latest source was compared only; previous full offline run was at `0b3004b`.
  A detached disposable worktree was used to exercise the CI overlay.

## Unique deltas to accept deliberately

| Owner | Accept once | Integration treatment |
| --- | --- | --- |
| Pricing #51 | Existing `product-pricing.php` profile opt-in and hooks | Restore #50 already contains this module. Compare it; do not duplicate functions or hooks. |
| Pricing #51 | `pricing-quote.php` from `b39d13f`, with 688-assertion quote test | ALREADY IN #50 at `1d35eda`, identical calculator; do not reapply. Hermes has adapted quote callers. Retain its newer test additions. |
| Pricing #51 | `pricing-woocommerce.php` and 31-assertion lifecycle test from `c4dd715` | New default-OFF adapter. Resolver and Classic-only gates below are mandatory before enablement. |
| Pricing #51 | Two require lines and `sanro_studio_register_pricing_wc_hooks()` bootstrap call | Apply surgically to restore bootstrap. Do not replace the entire restore bootstrap with pricing branch's sparse version. |
| CI #48 | Latest `tests/run-all.sh` and runner contract test | Canonical restore-compatible runner; supersedes earlier copies in #48/#51. Never silently skip failing offline tests. |
| CI #48/#51 | `tests/studio-assets.test.cjs` | Identical reused delta, accept once. |
| Docs | Pricing contract, seven-stage ledger, security snapshot, current QA/handoff | Retain dated context. An older sparse-tree security BLOCKED report is not a review of newly restored handlers. |

Official price/DTF geometry engine files are unchanged by this completion work.
No arbitrary product nominal, repeat entitlement or new payment rule was added.

## tests/README.md conflict resolution

Use the README in the latest CI #48 branch as the proposed resolved content. It
retains the common quality-gate text, documents the classified offline runner,
and includes #50's entire `0.3.2 RC reproducible local fixture` section. Preserve
both sections; do not choose `--ours`/`--theirs` blindly. Manual resolution:

```sh
# Only in Lead's authorized integration worktree after reviewing the incoming diff:
git show origin/work/codex-studio-ci-20261123:tests/README.md > tests/README.md
```

That command is a proposed content resolution, not an instruction to merge or
change PR #50. Fixture setup requires an explicit disposable environment; the
runner does not execute fixture setup, browser navigation or website mutations.

## Pricing / restore contract

The detailed filter, metadata, allocations and failure behavior are specified in
`docs/architecture/studio-dtf-pricing-contract.md` on #51. Key acceptance gates:

1. Supply `sanro_studio_pricing_authorized_design` from trusted integration code.
   Reauthorize owner/session and product binding, locked/revoked state and image
   master readiness for every quote/add/cart/checkout call. Return no secret in
   the contract or cart metadata. Missing resolver fails closed.
2. Keep `SANRO_STUDIO_ENABLE_WC_PRICING` OFF by default. Do not enable it alongside
   #50's `SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK`; the new adapter refuses that
   combination. The old signed-price writer fixes quantities and must not reprice
   the new editable-quantity path or overwrite its current quote.
3. #50 order-binding checks use saved quote allocations. Lead must adapt binding
   to current native cart quantities before enabling order creation. Never remove
   ownership, master-file or full-order consistency guards to make tests pass.
4. New adapter is Classic/simple-product only. Variation mapping and Store API /
   Checkout Blocks guards are pending; do not enable it on those flows.
5. Native Woo owns stock, taxes, shipping, coupons, checkout validation and order
   creation. Browser amounts are never authority. Repeat-order discount requires
   its own verified eligibility path and is not enabled by this adapter.

## Quota-recovery delta summary

Hermes integrated the server calculator and #46 hardening by `1d35eda`. The
remaining executable delta is `pricing-woocommerce.php`, its bootstrap registration
and its 31-assertion test; accept only those adapter parts of `c4dd715` rather than
reapplying the shared calculator. For the existing pricing contract document,
append/review the completion adapter section instead of overwriting Hermes'
current copy. CI runner/self-test and README resolution remain independent deltas.
No further full suite was run during recovery.

## Remaining restored-suite failures

See `docs/qa/studio-pricing-ci-completion-20261123.md`. Tests in the restored plugin
expose contracts/fixtures that are not fixed in this CI-only change. Their FAIL
results remain in the runner output. No plugin/test fixture is rewritten on #50,
no failing assertion is suppressed, and no real runtime release gate is waived.
