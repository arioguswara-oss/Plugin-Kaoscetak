# SANRO Studio — final admin and release gate

Date: 27 November 2026 (WIB)
Repository: `arioguswara-oss/Plugin-Kaoscetak`
PR #52 · branch `work/studio-mobile-checkout-integration-20261123`

## Result

**Development change PASS. Final Release NO-GO.** The legacy catalog mapping gap is fixed and the V1.1.0 candidate is reproducible. The candidate was not installed: SSH stopped before reaching staging because the system SSH configuration file `/etc/ssh/ssh_config.d/20-systemd-ssh-proxy.conf` is rejected for unsafe ownership/permissions. No global permission or SSH configuration was changed. Authenticated HTTPS pre/post gates and live acceptance therefore remain open.

## Admin lifecycle revision

`sanro_studio_catalog_is_mapped()` recognizes explicit lifecycle metadata and legacy products with saved Studio configuration. Legacy listings keep the existing buyer-visible `active` default, gain the lifecycle controls, and are excluded from the Add selector. Add rejects a duplicate legacy mapping. Lifecycle writes reject products with no Studio mapping. Configuration remains attached to the original WooCommerce product; archive/restore only changes Studio metadata. Capability checks, product-bound nonce validation, and simple/variable type checks remain in place.

The native WooCommerce product creation link and original-product edit link from commits `217b76d` and `417d45e` were reviewed and retained. Current live navigation was not retested on this candidate.

Targeted regression results:

- Simple and variable product lifecycle, legacy mapping, duplicate rejection, activation visibility, deactivation, archive/restore, capability, nonce, unsupported type and Woo product status preservation: **25 assertions PASS**.
- Editor configuration: **31 PASS**; server quote: **533 PASS**; checkout bridge: **4 PASS**.
- Mixed checkout offline fixture: **8 PASS** across two designs and two WooCommerce products, including independent authorization, quote/line matching, product separation, cross-design substitution rejection and changed-total rejection.
- Actual after-validation hook contract: **PASS** for default denial before writes, source-scoped evidence, evidence-storage failure denial, and production/non-admin isolation.
- Atomic lock synthetic interleaving: **10 PASS**. Offline mixed checkout boundary: **19 PASS**, with no database, order or payment writes.
- Independent Studio admin permissions/order hooks: **71 PASS**. Candidate security/preflight unit tests: **7 PASS**. PHP lint and `git diff --check`: **PASS**.

## Reused live evidence and limits

Prior live simple and variable catalog lifecycle acceptance (Woo fixtures #53 and #76) is retained from [`studio-release-closure-20261126.md`](studio-release-closure-20261126.md); it is not represented as testing the new legacy-detection code. Existing order #56 integrity and production artifacts are reused per [`studio-v1-final-acceptance.md`](studio-v1-final-acceptance.md). The fresh Guest Buyer First evidence for source `c082c7f` remains in [`runtime-acceptance.json`](../../artifacts/studio-v1/final-save-ux-20261127/runtime-acceptance.json).

The new candidate's authenticated HTTPS gates, installation digest, runtime admin lifecycle, and mixed checkout boundary on the installed candidate are **NOT TESTED**. The inherited SSH agent lists a staging-labeled identity, but this does not prove host authentication. No configured `kaoscetak-staging` alias is present. The one read-only SSH attempt was blocked locally before a connection; it was not retried. No new credential was created. No order/payment was submitted. DTF and Google Drive remain OFF.

## Candidate

Source: `bf8ba02c4dbb073bc140085d0e6c703ffbe79beb`
Version: `1.1.0`
ZIP: `artifacts/studio-v1/final-admin-release-gate-20261127/sanro-studio-staging-candidate.zip`
ZIP SHA256: `fc9496e168b63ba69e9a7bb46b9f52043cda6cfd8c7e55c3783a7b08ccae6f25`
Manifest SHA256: `8de4376a75a27cbe67ceb220c625fd42f7ee817cda308c2b2fbeb65ecd7d6d2a`

Two independent builds were byte-identical (89 plugin files). Local read-only preflight is **PASS / PLAN_ONLY**; remote SAPI, flags and installed tree were not inspected for this candidate. Detailed status and checksums are in [`build-and-gate-evidence.json`](../../artifacts/studio-v1/final-admin-release-gate-20261127/build-and-gate-evidence.json) and [`SHA256SUMS`](../../artifacts/studio-v1/final-admin-release-gate-20261127/SHA256SUMS).

## Release decision

| Area | Status |
|---|---|
| Admin lifecycle implementation and local regression | **PASS** |
| Previous simple/variable admin staging lifecycle | **PASS, reused** |
| Mixed design/product server quote and authorization contract | **PASS, offline regression** |
| Existing order #56 evidence | **PASS, reused** |
| Fresh after-validation on this installed candidate | **OPEN — staging access blocked** |
| Authenticated pre/post HTTPS gates and installed digest | **OPEN — candidate not installed** |
| New order, payment, production, DTF, Drive | **NOT RUN / OUT OF SCOPE** |
| Final Release | **NO-GO** |
