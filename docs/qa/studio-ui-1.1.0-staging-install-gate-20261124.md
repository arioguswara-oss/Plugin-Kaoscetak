# SANRO Studio UI/UX 1.1.0 staging installation gate — 24 November 2026

## Result

**BLOCKED — no installation performed.** The SSH alias authenticated and the staging identity matched. The current staging state does not satisfy the immutable source-bound transport gate, so no plugin replacement was attempted.

## Candidate integrity

- Repository/PR/checkpoint: `arioguswara-oss/Plugin-Kaoscetak`, #52, `af71d0397b183967c5b20965ad8676990aafe16c`.
- UI candidate source: `12b2c043cc6ccbb3e7c828949bf524ff6f2e84b1`, version `1.1.0`.
- Candidate archive SHA256: `991f53c93ad6ae7c8c73bad1af4bd1b5406beac93ca7c64d6b0365a289e7896b`.
- Candidate manifest SHA256: `a822f0312b8183b98ac37e35c2189e12149e6d55a4e7a5b9bf7950fd15a91d0f`.
- Exact-target release transport SHA256: `a8778a0cc0ac4c689135ca8deed87a587ab47127b99a40690d00e87520d25357`.
- All entries in `artifacts/studio-v1/1.1.0-ui/SHA256SUMS` verified.

## Fresh read-only runtime observations

- `kaoscetak-staging` authenticated as `u606139982` on `id-dci-web1631.main-hosting.eu`.
- WordPress URL and canonical document root identify the named staging site; production was not accessed.
- SANRO Studio `1.0.0` and WooCommerce `11.3.1` are active.
- Checkout, DTF, Drive, and storage-GC writer flags are OFF.
- Private master and preview roots passed read-only existence, permission, and access checks.
- Fresh installed Studio tree digest: `c3524d76a6992179716946b9c353e98d9baad11e13feb8bfa24d387fe383db61`.
- The packaged release transport is hard-bound to existing digest `60c8b0f3a272b024537e152fe46763c31b1beafcb36933fa2e57bb115c132273`. The mismatch is a mandatory gate failure. The exact web SAPI was not freshly verified through SSH; SSH PHP is CLI and is not used as web-runtime proof.

The transport instructions require the exact-target internal gate to pass and forbid direct ZIP overwrite. No baseline, transport binding, or installer was altered to force a pass. No plugin files, activation, settings, order data, or production state changed.

## Next action

Rebuild the source-bound release transport against the freshly verified installed-tree digest and bind fresh exact-target web-runtime evidence, then rerun the same mandatory gate. That is new package/gate work and was not done here. After a successful installation, record installed member hashes and active version. Owner performs visual acceptance separately in a browser.
