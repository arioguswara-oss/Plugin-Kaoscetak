# SANRO Studio 1.1.0 UI P1 correction — staging evidence

Candidate built from PR #52 commit `826c152c3dbf2e35fff74d276e9c2fd00aed2905` after the independent review at checkpoint `906e7a5dfab6002f109f7c51ce6cdd36b8a2ab03`.

The two Owner concept screenshots in `artifacts/studio-v1/1.1.0-ui/review-20261125/references/` remain the primary visual references. Review findings V01–V07 were addressed; the requested admin page hierarchy and navigation refinements were included without changing server quotes, security boundaries, or WooCommerce behavior. Owner browser visual acceptance remains pending.

## Candidate and transport

- Candidate ZIP: `sanro-studio-staging-candidate.zip`, SHA256 `cd4c86ec548de7d109b751734c0a30dff55f67bf83594ec4430b276633cf0b9a`.
- Candidate manifest SHA256: `bae045a6d8255a482bba191c2a7a5983c83234f7cee887d9302d02231bfabd15`.
- Manifest-derived plugin digest: `275487367a6de2a8c965686896eed1bc83f0098a97db01a8a523bb7fe54bebec`.
- Candidate ZIP and manifest reproduced identically in two clean builds.
- Source-bound transport SHA256: `971e8d3f0218663f01430ecbabc720ff9616c9f88694f41582934bd92026e8e2`, reproduced identically twice.
- Transport predecessor is pinned to the previously installed V1.1.0 candidate manifest SHA256 `a822f0312b8183b98ac37e35c2189e12149e6d55a4e7a5b9bf7950fd15a91d0f` and ZIP SHA256 `991f53c93ad6ae7c8c73bad1af4bd1b5406beac93ca7c64d6b0365a289e7896b`. Its manifest/archive inventory, member hashes, build marker and live staging digest were verified before transport construction.
- The earlier V1.0.0 Final Release manifest SHA256 `556ecd490dcad05a67c55b34e02d04bba6b3e1e25a517f073196f94649b242cd` remains verified against its V1.0.0 tree digest `c3524d76a6992179716946b9c353e98d9baad11e13feb8bfa24d387fe383db61`; that older digest is historical predecessor evidence, not the currently installed tree.

## Staging result

- Exact staging target: `https://saddlebrown-peafowl-616656.hostingersite.com` via SSH alias `kaoscetak-staging`.
- Fresh pre-install live tree digest: `0c7900e3b5779b19897ad8f1443f4e0bee8fd0b377ffa35f27bb993b68116301`, matched the installed V1.1.0 manifest and build marker.
- Mandatory pre-install HTTPS gate: **PASS**, web PHP `8.3.37`, SAPI `litespeed`; no PHP CLI SAPI claim.
- Install transaction: **committed**; rollback retained; activation and writer flags unchanged; no order writes.
- Post-install HTTPS gate: **PASS**. SANRO Studio `1.1.0` active; installed tree digest `f746c3928b96b5c6ca20cb04a7d5615a2f636799d2f88939c93fc4cadb88f049`.
- Runtime smoke: product 24 editor configuration valid; buyer CSS and JS HTTP 200 and match candidate SHA256; all storage-GC, checkout, DTF, and Drive writer flags OFF; private roots PASS.
- Original staging release transport restored to version `0.3.3` and inactive; the restore matched original tree digest `4411c6ca2752e0a91c7c739945e22cb40378714fe6389c439be7a4dafd075106`.
- Temporary application password revoked and temporary private staging files removed.

See [installation report](../../../docs/qa/studio-ui-p1-correction-installation-20261125.md), `installation-execution.json`, `runtime-evidence.json`, and `targeted-tests.json`. `SHA256SUMS` covers the artifacts in this directory.

Production, merge, checkout/order/payment, DTF, Google Drive OAuth, and Full Audit were not performed. Owner visual acceptance remains pending.
