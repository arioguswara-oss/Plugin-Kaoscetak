# Staging QA Harness V1 — build and evidence

- Reviewed source HEAD: `24817098889853a2bf0c1295a0d65e21fb66f2b0`
- Installed Studio source: `7aa6eb23d226272bc8a523b28854ccbed64ae497`
- Version: `1.1.0`
- Bundle contents: README, offline source-contract checker, manifest; no plugin source or installer payload.
- PHP lint: PASS.
- Contract check: 15/15 PASS against files fetched from the reviewed GitHub source tree.
- Builder syntax: PASS.
- Two independent builds: identical ZIP SHA-256 `1f4f84e34ce8d57221c023380e7a255a18682e6bf12668420117148647648969`.
- ZIP integrity: `unzip -t` PASS; exactly three expected entries.
- Manifest SHA-256: `58a7d08da6afef7aa943f415c04399c51f6bf99599fed416c41910fadcda17ed`.
- Runtime staging/install/test: NOT RUN by instruction.
- Checkout/order/payment/email/webhook/fulfillment/Drive/DTF/GC effects: NONE.
- Baseline: `96.60%` (unchanged).

The contract runner intentionally asserts that the exact staging transport still rejects acceptance POSTs with 403. The result records a policy blocker, not runtime acceptance.
