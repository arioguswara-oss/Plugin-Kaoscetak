# SANRO Studio installer self-fix and Hostinger dry-run — 24 November 2026

## Outcome

The sparse WordPress `active_plugins` representation blocker is fixed in the official installer probe. WordPress staging returned JSON keys `[0,1,2,3,4,6,7]`; the installer now normalizes numeric-key JSON objects into ordered lists while rejecting malformed keys, non-string values, duplicate paths, and oversized values. The sanitized regression fixture uses the actual staging plugin names and sparse key layout. No WordPress option, database, activation state, or plugin file was changed.

Targeted installer/recovery tests: **PASS, 21 tests** (`test_studio_staging_install.py`: 11; `test_studio_installer_crash.py`: 10). Local candidate preflight and PHP lint: **PASS**. The official installer was copied to a private temporary transport directory on the staging account and run in `PLAN_ONLY`; it returned **PASS**, `recovery_required=false`, writer flags OFF, PHP lint PASS, and `apply_authorized=false`. Temporary transport files contain only the candidate and installer tools; they are not in the WordPress document root.

## Mandatory gate evidence

- Staging identity: `https://saddlebrown-peafowl-616656.hostingersite.com`, account `u606139982`, host `id-dci-web1631.main-hosting.eu`, document root and WordPress root `/home/u606139982/domains/saddlebrown-peafowl-616656.hostingersite.com/public_html`, multisite false; **PASS**.
- Production isolation: staging resides in its own domain directory; production was not accessed; **PASS**.
- Runtime: web PHP `8.3.37`, LiteSpeed/LSAPI from authenticated Site Health evidence dated 24 November; CLI PHP `8.2.36` is separately identified and used by remote installer lint; **PASS**. LiteSpeed is accepted by the exact installer approval contract; PHP-FPM is not required.
- Plugins: SANRO Studio `0.1.0-dev` and WooCommerce `11.3.0` are active; the seven-entry actual sparse array is normalized only in the read-only probe; **PASS**.
- Private storage: master and preview roots are outside document root, private, and were verified readable/writable in the same-day staging preflight; **PASS**.
- Writer flags: DTF generator/artifacts/order batch, Google Drive exports/provisioning, storage GC, and staging checkout writer remain OFF; the official probe independently confirmed all installer-controlled writer flags false; **PASS**.
- Journal/lock/recovery: rollback-root journal absent, installer lock sentinel absent, no incoming tree, previous backup, or interrupted quarantine; plan-only returned `recovery_required=false`; **PASS**.
- Existing tree: remains `e58d3cd03d3dba87ce17d8121e19720ab2dc9939394130c9fcd142ce8fd7bab9`; plugin was not replaced; **PASS**.

No known installer incompatibility or mandatory pre-install gate blocker remains for this candidate. Post-install menu/assets/runtime smoke checks remain **NOT TESTED** because installation is not authorized by this package.

## Final candidate and approval state

- Source: `081a63f78b88a3139327af6a545be1993b48506f`
- Plugin digest: `f21bcd20f5bdb8013d1a939d09761d4af3a0b2b96b4ef4790fd09616600d63d2`
- ZIP SHA256: `f66cdc28e3b2b1914df94ca4b4f6e40c7a84fb2da60fc01884a231ffbd903800`
- Manifest SHA256: `7452cbc476266cd3d36454445ebacbd0bb06dd1add80a6fe91277755501b9177`
- Approval request: [approval-request.json](../../artifacts/studio-v1/081a63f/approval-request.json), explicitly `approved: false`.

An Owner approval for a prior source or ZIP does not authorize this candidate. No install, deploy, merge, production access/change, order DB write, activation change, DTF, Drive, or storage GC action occurred.

## Seven-stage progress

| Stage | Before | Now | Delta |
|---|---:|---:|---:|
| Core/foundation | 95% | 95% | 0 |
| Product/backend/admin | 94% | 94% | 0 |
| Buyer frontend | 96% | 96% | 0 |
| Server pricing/quote | 96% | 96% | 0 |
| Save/ownership/share | 98% | 98% | 0 |
| Checkout/production/Drive | 97% | 97% | 0 |
| QA/release/mobile | 40% | 40% | 0 |

Official baseline remains **90.50% → 90.50%, delta 0.00**. Installer readiness evidence does not claim feature-completion credit.
