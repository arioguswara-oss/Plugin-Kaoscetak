# SANRO Studio Staging QA Harness V1

Repository: `arioguswara-oss/Plugin-Kaoscetak` · PR #52 · reviewed source HEAD `24817098889853a2bf0c1295a0d65e21fb66f2b0`.

This bundle is an **offline contract check and handoff** for the existing staging QA paths. It is not a WordPress plugin, installer, checkout simulator, or permission to submit checkout. It makes no network, database, WooCommerce, payment, email, webhook, fulfillment, or staging calls.

## Run the source contract check

From the reviewed repository checkout, run:

```sh
SANRO_STUDIO_REPO_ROOT=/path/to/Plugin-Kaoscetak php tests/staging-qa-harness-contract.php
```

The checker verifies the exact Hostinger staging identity, administrator capabilities and nonce, checkout boundaries, Woo Store API policy hook, HPOS read-only path, existing synthetic zero-charge CLI fixture guard, and that the Final Release builder packages only the Studio plugin subtree. It does not replace runtime acceptance.

## Existing QA paths to reuse

- `tools/staging-web-release/` is the exact-host, source-bound staging transport. Its install gate, source nonce, capability checks, lock, rollback journal and digest checks remain authoritative. Keep it outside the Final Release plugin ZIP.
- `tools/staging-web-release/mixed-checkout-acceptance.php` checks a two-design cart using Studio's current server quote/ownership validation. The Classic boundary records validation, then stops before order creation or payment. Its isolated CAS probe is separate and rolls back.
- `plugins/sanro-studio/includes/checkout-policy.php` registers the actual `woocommerce_store_api_cart_errors` guard. Runtime Store API submission has not been accepted by the current staging boundary.
- `tests/staging-hpos-order-metadata-readonly.php` reads existing HPOS fixture #52 only. Existing order #56 front/back preview and first-guide PDF readback evidence is recorded in the current staging acceptance report.
- `ops/hostinger/sanro-studio-pr32-qa-order.php` is a separate exact-staging HPOS CLI fixture runner. Its PLAN mode is read-only; its optional zero-charge order is productless and does not exercise SANRO Studio checkout/locking. Reuse only under its documented staging procedure.
- `tests/staging-hpos-qa-order-policy-smoke.php` is historical safety coverage. The 50 targeted Studio regression results and positive pagination runtime result are reused; this cycle did not rerun them.

## Runtime boundary and handoff

The installed staging transport explicitly rejects every legacy acceptance POST with HTTP 403 (`admin_init` in `tools/staging-web-release/sanro-studio-staging-release.php`). Its UI says new orders and production actions are disabled. The one-order helper in `acceptance.php` is therefore unreachable through that transport. This harness preserves that policy; do not enable the helper, remove the boundary, or call the Store API checkout route to work around it.

To prove HPOS write/readback and a complete Studio mixed checkout, ChatGPT Work needs an officially approved disposable staging site/database or a revised staging policy that explicitly permits one synthetic unpaid order. Before any future dispatch, require a verified egress-blocking path for outbound mail and webhooks, a unique synthetic marker, native Studio authorization, idempotency lock, readback, and an approved retention/cleanup decision. Keep payment, fulfillment, Drive, DTF and GC disabled. Until then, mark HPOS write, live checkout, duplicate submit, Store API submit, frozen-configuration runtime, and repeat-discount runtime **NOT TESTED / BLOCKED BY STAGING POLICY**.

No staging request or installation was made in this cycle. Baseline remains 96.60%.
