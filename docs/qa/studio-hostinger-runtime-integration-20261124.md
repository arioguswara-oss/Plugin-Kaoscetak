# SANRO Studio Hostinger runtime integration — 24 November 2026

## Seven official progress stages

Progress baseline is unchanged: **90.50% → 90.50%, delta 0.00**. No feature credit is added for runtime inspection or packaging.

| Tahap | Bobot | Baseline sekarang | Status pekerjaan ini |
|---|---:|---:|---|
| 1. Fondasi/core | 25% | 95% | PASS — source candidate retained; installer SAPI gate corrected |
| 2. Backend produk/admin | 10% | 94% | PASS — one active plugin confirmed; duplicate menu cause identified |
| 3. Frontend buyer | 15% | 96% | NOT TESTED — no browser or buyer flow run |
| 4. Harga server/quote | 10% | 96% | NOT TESTED — no quote or checkout write/run |
| 5. Simpan/ownership/share | 10% | 98% | PASS — private master/preview roots safe; feature flags inspected through WP-CLI |
| 6. Checkout/produksi/Drive | 20% | 97% | PARTIAL — Woo active; DTF/Drive/GC OFF; production workflow not exercised |
| 7. QA/rilis/mobile | 10% | 40% | PARTIAL — candidate PLAN_ONLY/lint pass; install remains NO-GO |

## Runtime evidence

- SSH `kaoscetak-staging` from Codex CLI returned host `id-dci-web1631.main-hosting.eu`, user `u606139982`, home `/home/u606139982`. Used `-F /home/sanro/.ssh/config` because the system SSH config contained a bad-owner symlink. No escalation or credential output was needed.
- Exact staging docroot: `/home/u606139982/domains/saddlebrown-peafowl-616656.hostingersite.com/public_html`; WordPress `siteurl` matched the requested HTTPS staging URL, canonical path matched, WordPress 7.1.5, database blog id 1.
- The staging docroot is a distinct domain directory from `kaoscetak.id/public_html`. Production was not accessed.
- WooCommerce 11.3.0 and SANRO Studio 0.1.0-dev are active. SANRO Studio appears once in the plugin list; legacy SANRO Studio QA is inactive. The `sanro-studio-staging-pr50` and DTF admin display MU plugins remain installed.
- Web SAPI: **PASS — LiteSpeed SAPI** in authenticated Site Health evidence for this staging site dated 24 November. A fresh read-only HTTP HEAD returned `x-powered-by: PHP/8.3.37`. SSH PHP is 8.2.36 CLI and is not treated as web runtime. WP-CLI PHP capability checks found GD, ctype, fileinfo, FreeType, and int64 support. No PHP-FPM claim is made.
- Current Studio plugin tree digest: `e58d3cd03d3dba87ce17d8121e19720ab2dc9939394130c9fcd142ce8fd7bab9`; build marker absent. It does not match candidate plugin digest `f21bcd20f5bdb8013d1a939d09761d4af3a0b2b96b4ef4790fd09616600d63d2`.
- Both private master and preview storage roots are configured, exist outside the document root, have private permissions, and are readable/writable. The DTF artifact root is not configured.
- All current feature flags from WP-CLI readback:

| Flag | State | Flag | State |
|---|---|---|---|
| BUYER_EDITOR | ON | DRAFT_API | ON |
| STORAGE_GC | OFF | PRIVATE_PREVIEWS | ON |
| PREVIEW_UPLOAD | ON | PRIVATE_MASTERS | ON |
| MASTER_UPLOAD | ON | WC_PRICING | ON |
| STAGING_CHECKOUT_HOOK | OFF | DTF_IMAGE_RASTER | OFF |
| DTF_LAYER_RASTER | OFF | DTF_PRIVATE_ARTIFACTS | OFF |
| DRIVE_MASTER_EXPORT | OFF | DTF_ADMIN_GENERATE | OFF |
| DTF_ORDER_BATCH | OFF | DRIVE_AUTO_PROVISION | OFF |
| VERIFIED_DRIVE_ATTACHMENT | OFF | DRIVE_PACKAGE_EXPORT | OFF |
| DRIVE_DTF_CANDIDATES | OFF |  |  |

- Installer recovery state: **PASS**. Recursive account-home scan found no transaction journal, lock file, incoming tree, previous backup, or interrupted quarantine. The exact staging plugin-parent lock sentinel is absent; `/tmp` and `/var/tmp` contain no matching installer artifacts. `/proc/locks` is not readable to this account, but the pinned installer retains its lock sentinel and does not unlink it. No interrupted installation evidence was found.
- The installed source registers SANRO Studio Settings as a top-level page in `includes/admin.php`, while `sanro-studio.php` adds its integration status under WooCommerce. This explains the duplicate menu locations; the inventory shows one active plugin, not two. Candidate 793784f groups Settings, Status and Private Storage under one SANRO Studio root. Live corrected menu remains NOT TESTED.
- Cache/lease semantics, browser E2E, and candidate installed-source readback remain NOT TESTED. These are post-install/read-write workflow gates, not required for Owner approval of a replacement-only staging install.

## Runtime gate correction

The earlier installer approval contract required `php_fpm_verified=true`, which cannot describe the observed LiteSpeed runtime. The gate now requires `web_runtime_verified=true` and the exact `web_php_sapi=litespeed`; PHP-FPM is neither required nor asserted. CLI-only candidate preflight reports remote web SAPI as NOT TESTED because it does not contact Hostinger. Authenticated Site Health supplies the actual web SAPI evidence. This is a policy fix only; it changes no Hostinger runtime configuration.

## Candidate and approval

- Source: `793784f5c33c885dfd9f1b7a52a5e0786f072194`
- ZIP SHA256: `d76514ed96c122a6242baad9ea36f4b15f11d93c36de733e9292664bba826381`
- Manifest SHA256: `00fcd0483b41825c2983d78e936b0e86333a7e43c68042d7310c4fff595de62e`
- Existing plugin digest bound in conditional request: `e58d3cd03d3dba87ce17d8121e19720ab2dc9939394130c9fcd142ce8fd7bab9`
- Local candidate preflight: PLAN_ONLY, PHP lint PASS, deployment_authorized false.
- Approval package is `artifacts/studio-v1/793784f/approval-request.json`; source, ZIP, manifest, staging identity, installed digest, web SAPI, private storage, flags, and journal state are bound. `approved=false`, no machine approval file. It is ready to present to the Owner and is not yet installation authorization.

**GO to request Owner approval** for the exact staging replacement package. **NO-GO to install** until that approval is explicit. DTF generator, Google Drive and storage GC remain OFF. No order, database, production, or checkout mutation occurred.
