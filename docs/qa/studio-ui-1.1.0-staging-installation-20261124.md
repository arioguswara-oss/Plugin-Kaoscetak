# SANRO Studio UI/UX 1.1.0 staging installation — 24 November 2026

## Result

**PASS — installed and active on staging.** This continues the initial transport digest mismatch recorded in [the gate refusal](studio-ui-1.1.0-staging-install-gate-20261124.md). The live V1.0.0 tree was validated against its previously approved Final Release manifest; the installer transport was rebuilt from that evidence and its HTTPS runtime gate passed before apply.

## Verified candidate and transaction

- PR #52 UI source: `12b2c043cc6ccbb3e7c828949bf524ff6f2e84b1`, SANRO Studio `1.1.0`.
- Candidate ZIP SHA256: `991f53c93ad6ae7c8c73bad1af4bd1b5406beac93ca7c64d6b0365a289e7896b`.
- Manifest SHA256: `a822f0312b8183b98ac37e35c2189e12149e6d55a4e7a5b9bf7950fd15a91d0f`.
- Installed tree digest: `0c7900e3b5779b19897ad8f1443f4e0bee8fd0b377ffa35f27bb993b68116301`; matches all candidate members and build marker.
- Previous V1.0.0 staging digest `c3524d76a6992179716946b9c353e98d9baad11e13feb8bfa24d387fe383db61` independently matches the ZIP-derived tree from approved manifest SHA256 `556ecd490dcad05a67c55b34e02d04bba6b3e1e25a517f073196f94649b242cd`.
- Rebuilt transport SHA256: `a4a441b60d2b3d376cbe71e336d0359f7ddda26bb37feae9c76954842b5262da`, built reproducibly twice. Its prior-tree binding is derived only after member, manifest, build-marker and fresh-live-tree checks.
- Transaction committed with the previous tree retained. Plugin activation and settings were preserved; no order writes were requested.

## Mandatory HTTPS gate and smoke

- Exact staging host, account, site URL and canonical docroot: **PASS**; production was not accessed.
- HTTPS web runtime diagnostic: **PASS**, PHP `8.3.37`, SAPI `litespeed`; SSH CLI PHP was not used as SAPI evidence.
- Existing transaction journal and retained backup, private roots and required capabilities: **PASS**.
- WooCommerce and SANRO Studio were active before install; every checkout, DTF, Drive and storage-GC writer flag was OFF before and after.
- Post-install HTTPS gate: **PASS**, installed source and digest matched candidate.
- Read-only runtime readiness and published product 24 editor configuration: **HTTP 200 / valid**.
- Buyer CSS and JS: **HTTP 200** and both hashes match the candidate manifest.
- Final SSH readback: SANRO Studio `1.1.0` active, installed tree digest matches; temporary release transport restored to `0.3.3` and inactive. The temporary application password was revoked and its auth files removed.

## Acceptance boundary

Owner browser visual/interaction acceptance remains **PENDING**. No screenshot or visual pass is claimed. No checkout/order/payment, DTF, Drive, production change, or merge occurred. V1.1.0 is installed for staging review but is not marked Final Release.

Full sanitized gate and execution evidence is in [installation-execution.json](../../artifacts/studio-v1/1.1.0-ui/installation-execution.json); current runtime state is in [runtime-evidence.json](../../artifacts/studio-v1/1.1.0-ui/runtime-evidence.json).
