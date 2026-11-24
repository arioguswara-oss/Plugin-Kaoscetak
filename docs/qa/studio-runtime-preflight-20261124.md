# SANRO Studio — staging runtime preflight checkpoint

24 November 2026 WIB. Continues PR #52 checkpoint `006b1aecbf6b50ebfb9a3f73430e0aa62e238566`. The earlier independent-menu correction, targeted CI, and candidate ZIP checks are reused; none were rerun. No Full Audit, install, deploy, merge, production change, or order database operation was performed.

## CLI access result

GitHub/PR checkpoint was confirmed from the isolated PR worktree. SSH alias `sanro-hosting` reached the configured remote boundary, which returned `SANRO_AGENT_BLOCKED: command not permitted` before running the requested read-only identity command. A restricted escalation attempt returned the same remote-policy block. No credential or SSH configuration value was printed. The available Hostinger connector in this session exposes mail tools only, not hosting/runtime access. Therefore live SSH identity, WordPress files, PHP, plugin inventory, production separation, private storage, flags, and installed tree were **NOT TESTED in this run**.

## Staging evidence and menu cause

Prior authenticated read-only MCP/Site Health evidence in `artifacts/studio-v1/8d817c2/readiness-evidence.json` identifies WordPress site `258754643`, staging URL `https://saddlebrown-peafowl-616656.hostingersite.com`, WordPress 7.1.5, PHP 8.3.37 on LiteSpeed SAPI, WooCommerce 11.3.0 active, SANRO Studio 0.1.0-dev active, and legacy staging QA inactive. These are historical observations, not refreshed runtime facts. Prior browser evidence recorded live asset `ccf75da12b01` versus candidate `9573ce5cdcb9`, so the installed source did not match the candidate at that observation.

The duplicate menu cause is the old native plugin registering the settings root plus Studio status and storage under WooCommerce. Prior inventory reported one active SANRO Studio install; the duplicate entries do not establish duplicate plugin installs. Candidate source consolidates the Studio pages under one independent root. Live menu rendering and current installed source remain **NOT TESTED** in this run.

Production separation, current private paths/permissions/capacity, all runtime flags, current full plugin digest, cache/lease behavior, and unresolved transaction journal state remain **NOT TESTED**. DTF generator, Drive, and storage GC remain required OFF; source defaults are not evidence of current runtime flags.

## Candidate and installer binding

The existing candidate stays pinned to source `8d817c2ba92b89d03eaa4d680173925361ce0a36`, plugin digest `f21bcd20f5bdb8013d1a939d09761d4af3a0b2b96b4ef4790fd09616600d63d2`, ZIP SHA256 `ca55c23ef3b13408e45d4b011a7d47be3676aec7da6e2d3e906508873ae66afd`, manifest SHA256 `d77c4d63807cc0fc72d359b311dd883805c607735cc82d3ab8caa649d620f7dc`. These previous PASS results are reused.

Pinned installer review confirms default `PLAN_ONLY`, exact staging URL/site checks, active Studio/Woo requirements, writer-flag guards, target derivation from WordPress plugin directory, private rollback root outside document root, nonblocking `flock`, durable intent journal, atomic directory replacement, retained backup, tree digest checks, and source-bound crash recovery. Tool hashes match the candidate approval request:

- `tools/studio-staging-install.py`: `ec07c3cc62bad1eb464805a7d4e53a610ad0b1fef3247fbcbc52e52990b51e72`
- `tools/studio-install-transaction.py`: `ec1e90a2be49112dc355bd4d84774bf23f4390573b964e1f53b3ed434dd8ae61`
- `tools/studio-candidate-preflight.py`: `162e5218b4e4d095a7070f3569fb7a788afe501707041d663a0ed8434240d501`

One gate conflict must be resolved before an apply request: installer approval requires `php_fpm_verified=true`, but prior Site Health observes LiteSpeed SAPI, not PHP-FPM. Do not claim FPM. The installed tree digest, production separation, and exact current staging identity are also missing. `approval-request.json` is updated as a source/ZIP/target-bound **conditional request**, but `approved=false`, `existing_plugin_digest=null`, `machine_apply_approval_file=NOT_CREATED`, and `runtime_proof_complete=false` remain. It is not actionable approval.

## Conditional install sequence

1. Restore an authorized, read-only SSH/operator path; verify host/account, canonical staging webroot/site URL, and production separation. Inspect PHP web SAPI/capabilities, Woo/plugin inventory, checkout pages, private roots, free capacity, flags, cache/lease semantics, exact current plugin digest, and transaction journal without changing them.
2. Reconcile the installer’s PHP-FPM approval gate with the verified LiteSpeed runtime; change no runtime configuration to force a pass.
3. Run the pinned installer in `PLAN_ONLY`. Confirm the candidate source/archive/plugin/manifest hashes, active plugin state, all writers OFF, exact existing digest, and no unresolved journal. A pending journal requires recovery with the original transaction’s manifest/approval before considering a new candidate.
4. Obtain explicit Owner approval bound to the exact candidate hashes, verified staging identity and existing-plugin digest, runtime gate, and replacement-only scope. E2E writes require separate scope.
5. Only after approval, use the same pinned installer’s apply path, which takes the lock, rechecks runtime state, writes a durable intent journal, retains the old tree, verifies candidate digest, and supports recovery. Preserve activation/settings/private paths; do not seed, alter orders, activate writers, or touch production.
6. Read back installed source/member hashes and menu hierarchy. Keep DTF, Drive, and GC OFF.

No installation is authorized by this checkpoint. Current verdict: **GO** for read-only access restoration and preflight preparation; **NO-GO** for staging installation until the blocker set is closed and Owner approval is explicit.

## Official development progress

No new implementation scope closed, so progress is unchanged: **90.50% → 90.50%, delta 0.00**.

| Tahap | Bobot | Sebelum | Sekarang | Delta |
|---|---:|---:|---:|---:|
| 1. Fondasi/core | 25% | 95% | 95% | 0.00 |
| 2. Backend produk/admin | 10% | 94% | 94% | 0.00 |
| 3. Frontend buyer | 15% | 96% | 96% | 0.00 |
| 4. Harga server/quote | 10% | 96% | 96% | 0.00 |
| 5. Simpan/ownership/share | 10% | 98% | 98% | 0.00 |
| 6. Checkout/produksi/Drive | 20% | 97% | 97% | 0.00 |
| 7. QA/rilis/mobile | 10% | 40% | 40% | 0.00 |
| **Total baseline development** | **100%** | **90.50%** | **90.50%** | **0.00** |
