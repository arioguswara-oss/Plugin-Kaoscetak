#!/usr/bin/env bash
# SANRO Studio local CI: deterministic, read-only checks for the isolated QA worktree.
# Run with: bash scripts/sanro-studio-local-ci.sh
set -u
ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)" || exit 2
cd "$ROOT" || exit 2
for tool in php node; do
  if ! command -v "$tool" >/dev/null 2>&1; then
    printf 'FAIL: missing required command: %s\n' "$tool" >&2
    exit 2
  fi
done

passed=0
failed=0
run_check() {
  local label="$1" expected="$2"
  shift 2
  local output rc
  output="$("$@" 2>&1)" && rc=0 || rc=$?
  printf '\n=== %s ===\n%s\n' "$label" "$output"
  if [[ $rc -eq 0 && "$output" == *"$expected"* ]]; then
    printf 'PASS: %s\n' "$label"
    passed=$((passed + 1))
  else
    printf 'FAIL: %s (exit=%s; expected: %s)\n' "$label" "$rc" "$expected" >&2
    failed=$((failed + 1))
  fi
}

printf 'SANRO STUDIO LOCAL QA\n'
printf 'Root: %s\n' "$ROOT"
printf 'Commit: %s\n' "$(git rev-parse --short HEAD 2>/dev/null || printf 'unknown')"

for file in \
  tests/test_sanro_editor_config.php \
  tests/test_sanro_pricing.php \
  tests/test_sanro_draft_rest.php \
  tests/test_sanro_checkout_guards.php \
  plugins/sanro-studio/includes/editor-config.php \
  plugins/sanro-studio/includes/cart-adapter.php \
  plugins/sanro-studio/sanro-studio.php
do
  run_check "PHP lint $file" 'No syntax errors detected' php -l "$file"
done

run_check 'Staging cart gate PHP syntax' 'No syntax errors detected' php -l tests/test_sanro_staging_cart_gate.php
run_check 'Cart REST smoke PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/rest-cart-bootstrap-smoke.php
# Isolated cart/checkout contracts. All fixtures use local mocks; no WC DB writes.
for file in \
  plugins/sanro-studio/tests/cart-adapter-smoke.php \
  plugins/sanro-studio/tests/cart-line-snapshot-smoke.php \
  plugins/sanro-studio/tests/checkout-gate-smoke.php \
  plugins/sanro-studio/tests/size-checkout-bridge-smoke.php
do
  run_check "PHP lint $file" 'No syntax errors detected' php -l "$file"
done
run_check 'Cart adapter preflight and rollback' 'PASS: 74 staging cart adapter checks' php plugins/sanro-studio/tests/cart-adapter-smoke.php
run_check 'Cart line identity and metadata' 'PASS: 24 isolated cart-line checks' php plugins/sanro-studio/tests/cart-line-snapshot-smoke.php
run_check 'Checkout authorization gate' 'PASS: 43 checkout gate checks' php plugins/sanro-studio/tests/checkout-gate-smoke.php
run_check 'Size checkout order-line binding' 'PASS: 13 isolated size-checkout checks' php plugins/sanro-studio/tests/size-checkout-bridge-smoke.php

run_check 'Default-disabled staging cart' '10 assertions PASS, 0 FAIL' php tests/test_sanro_staging_cart_gate.php
run_check 'Cart REST ownership/session/idempotency' 'PASS: 42 staging REST cart-bootstrap checks' php plugins/sanro-studio/tests/rest-cart-bootstrap-smoke.php
run_check 'Editor config REST' '26 assertions PASS, 0 FAIL' php tests/test_sanro_editor_config.php
run_check 'Pricing matrix' '356 assertions PASS, 0 FAIL' php tests/test_sanro_pricing.php
run_check 'Draft REST' '35 assertions PASS, 0 FAIL' php tests/test_sanro_draft_rest.php
run_check 'Checkout guards' '6 assertions PASS, 0 FAIL' php tests/test_sanro_checkout_guards.php
run_check 'Admin production-ready PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/includes/production-ready-admin.php
run_check 'Order production-ready regression PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/order-preview.php
run_check 'WooCommerce admin production-ready eligibility + POST guards' 'PASS: already-ready order does not show duplicate action' php plugins/sanro-studio/tests/order-preview.php
run_check 'Drive production verifier PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/includes/drive-production.php
run_check 'Drive production metadata smoke PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/drive-production-smoke.php
run_check 'Drive production metadata and admin links' 'private SANRO Drive folder metadata and admin link checks' php plugins/sanro-studio/tests/drive-production-smoke.php
run_check 'Drive default-disabled regression PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/drive-production-default-off.php
run_check 'Drive verification disabled by default' 'PASS: SANRO Drive remote verification defaults disabled' php plugins/sanro-studio/tests/drive-production-default-off.php
run_check 'Drive auto-provision worker PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/includes/drive-auto-provision.php
run_check 'Drive auto-provision regression PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/drive-auto-provision-smoke.php
run_check 'Drive auto-provision eligibility, duplicate and privacy' 'PASS: 23 SANRO Drive auto-provision regression checks' php plugins/sanro-studio/tests/drive-auto-provision-smoke.php
run_check 'Drive auto default-disabled PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/drive-auto-default-off.php
run_check 'Drive auto disabled by default' 'PASS: SANRO Drive automatic provisioning defaults disabled' php plugins/sanro-studio/tests/drive-auto-default-off.php
run_check 'Order-bound private master source PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/includes/order-master-source.php
run_check 'Drive original master exporter PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/includes/drive-master-export.php
run_check 'Drive original master export regression PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/drive-master-export-smoke.php
run_check 'Locked original master Drive export and idempotency' 'PASS: 24 SANRO private master Drive export regression checks' php plugins/sanro-studio/tests/drive-master-export-smoke.php
run_check 'Drive master default-disabled regression PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/drive-master-export-default-off.php
run_check 'Drive master export disabled by default' 'PASS: SANRO private master Drive export defaults disabled' php plugins/sanro-studio/tests/drive-master-export-default-off.php
run_check 'Checkout batch draft-lock module PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/includes/checkout-batch-lock.php
run_check 'Checkout batch lock synthetic regression syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/checkout-batch-lock-smoke.php
run_check 'Multi-size checkout draft lock, CAS and ownership' 'PASS: 31 SANRO staging batch draft-lock checks' php plugins/sanro-studio/tests/checkout-batch-lock-smoke.php
run_check 'Checkout batch lock default-deny regression syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/checkout-batch-lock-default-off.php
run_check 'Checkout batch lock disabled by default' 'PASS: SANRO staging checkout batch lock defaults disabled' php plugins/sanro-studio/tests/checkout-batch-lock-default-off.php
run_check 'DTF image raster module PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/includes/dtf-image-render.php
run_check 'DTF image-only safety regression PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/dtf-image-render-smoke.php
run_check 'DTF RGBA 300-DPI geometry and fail-closed raster' 'PASS: 24 SANRO image-only DTF raster safety checks' php plugins/sanro-studio/tests/dtf-image-render-smoke.php
run_check 'DTF image raster default-off PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/dtf-image-render-default-off.php
run_check 'DTF image raster disabled by default' 'PASS: SANRO DTF RGBA image raster defaults disabled' php plugins/sanro-studio/tests/dtf-image-render-default-off.php
run_check 'DTF layered compositor PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/includes/dtf-layer-render.php
run_check 'DTF layered compositor regression syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/dtf-layer-compose-smoke.php
run_check 'DTF image/text bound layer and fail-closed font policy' 'PASS: 18 SANRO layered DTF composition checks' php plugins/sanro-studio/tests/dtf-layer-compose-smoke.php
run_check 'DTF layered default-disabled regression syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/dtf-layer-default-off.php
run_check 'DTF layered compositor disabled by default' 'PASS: SANRO layered DTF raster defaults disabled' php plugins/sanro-studio/tests/dtf-layer-default-off.php
run_check 'DTF private order artifact module PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/includes/dtf-private-artifacts.php
run_check 'DTF private artifact lifecycle regression syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/dtf-private-artifacts-smoke.php
run_check 'DTF order item side private persistence and integrity' 'PASS: 28 SANRO order-bound private DTF artifact checks' php plugins/sanro-studio/tests/dtf-private-artifacts-smoke.php
run_check 'DTF private artifact default-off syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/dtf-private-artifacts-default-off.php
run_check 'DTF private artifact storage remains default-off' 'PASS: SANRO DTF private artifact storage defaults disabled' php plugins/sanro-studio/tests/dtf-private-artifacts-default-off.php
run_check 'DTF order batch coordinator PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/includes/dtf-order-workflow.php
run_check 'DTF order batch synthetic regression PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/dtf-order-workflow-smoke.php
run_check 'Real GD two-side preflight fixture PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/dtf-order-workflow-real-smoke.php
run_check 'Hostinger real two-side preflight E2E Bash syntax' '' bash -n ops/hostinger/sanro-studio-pr39-real-preflight-ci.sh
run_check 'Hostinger real GD locked snapshot E2E Bash syntax' '' bash -n ops/hostinger/sanro-studio-pr40-real-snapshot-ci.sh
run_check 'DTF two-side preflight contract PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/dtf-order-preflight-policy.php
run_check 'DTF two-side source/master preflight before any write' 'PASS: 12 SANRO two-side no-write preflight policy checks' php plugins/sanro-studio/tests/dtf-order-preflight-policy.php
run_check 'DTF locked snapshot policy PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/dtf-locked-snapshot-policy.php
run_check 'DTF locked draft version across preflight/render/commit' 'PASS: 14 SANRO locked DTF draft fingerprint policy checks' php plugins/sanro-studio/tests/dtf-locked-snapshot-policy.php
run_check 'DTF order item render persist private readback lifecycle' 'PASS: 40 SANRO order-item DTF batch and private status checks' php plugins/sanro-studio/tests/dtf-order-workflow-smoke.php
run_check 'DTF order batch default-off syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/dtf-order-workflow-default-off.php
run_check 'DTF order batch disabled by default' 'PASS: SANRO DTF order batch defaults disabled' php plugins/sanro-studio/tests/dtf-order-workflow-default-off.php
run_check 'DTF two-side real GD staging smoke PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/dtf-order-workflow-real-smoke.php
run_check 'DTF Woo admin candidate status module PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/includes/dtf-admin-order-status.php
run_check 'DTF Woo admin candidate status regression PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/dtf-admin-order-status-smoke.php
run_check 'DTF Woo admin role item binding and no-leak metadata' 'PASS: 32 SANRO WooCommerce admin DTF status access checks' php plugins/sanro-studio/tests/dtf-admin-order-status-smoke.php
run_check 'DTF Woo admin candidate status default-OFF PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/dtf-admin-order-status-default-off.php
run_check 'DTF Woo admin candidate status remains disabled by default' 'PASS: SANRO WooCommerce admin DTF candidate status defaults disabled' php plugins/sanro-studio/tests/dtf-admin-order-status-default-off.php
run_check 'Hostinger one synthetic HPOS order policy PHP syntax' 'No syntax errors detected' php -l ops/hostinger/sanro-studio-pr32-qa-order-policy.php
run_check 'Hostinger one synthetic HPOS order CLI PHP syntax' 'No syntax errors detected' php -l ops/hostinger/sanro-studio-pr32-qa-order.php
run_check 'Single zero-value HPOS order safety fixture PHP syntax' 'No syntax errors detected' php -l tests/staging-hpos-qa-order-policy-smoke.php
run_check 'One QA order fixture never binds stock/payment/production' 'PASS: 40 SANRO HPOS staging synthetic zero-order safety checks' php tests/staging-hpos-qa-order-policy-smoke.php
run_check 'Hostinger SHA-pinned one QA order shell syntax' '' bash -n ops/hostinger/sanro-studio-pr32-qa-order-ci.sh
run_check 'Staging DTF admin additive bootstrap PHP syntax' 'No syntax errors detected' php -l ops/hostinger/sanro-studio-pr31-bootstrap-preview.php
run_check 'Staging DTF admin target bootstrap fixture PHP syntax' 'No syntax errors detected' php -l tests/staging-targeted-bootstrap-smoke.php
run_check 'Staging DTF admin bootstrap preserves legacy preview' 'PASS: 14 SANRO staging targeted bootstrap patch checks' php tests/staging-targeted-bootstrap-smoke.php
run_check 'Staging DTF admin must-use flag PHP syntax' 'No syntax errors detected' php -l ops/hostinger/mu-plugins/sanro-studio-dtf-admin-staging.php
run_check 'Staging DTF targeted deployment shell safety syntax' '' bash -n ops/hostinger/sanro-studio-pr31-targeted-staging.sh
run_check 'Hostinger real WordPress runtime preflight PHP syntax' 'No syntax errors detected' php -l ops/hostinger/sanro-studio-pr28-wp-runtime-readonly.php
run_check 'Hostinger runtime preflight shell syntax' '' bash -n ops/hostinger/sanro-studio-pr28-wp-runtime-ci.sh
run_check 'Buyer PHP asset-version syntax' 'No syntax errors detected' php -l plugins/sanro-studio/includes/buyer.php
run_check 'Buyer asset fingerprint regression syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/buyer-asset-version-smoke.php
run_check 'Buyer asset fingerprint' 'PASS: 3 SANRO Studio buyer asset fingerprint assertions' php plugins/sanro-studio/tests/buyer-asset-version-smoke.php
run_check 'Staging buyer frontend JS syntax' '' node --check plugins/sanro-studio/assets/buyer.js
run_check 'Cart handler regression JS syntax' '' node --check plugins/sanro-studio/tests/buyer-cart-handler-node.cjs
run_check 'Cart handler delegation and no duplicate POST' 'PASS: 8 delegated Cart handler regression checks' node plugins/sanro-studio/tests/buyer-cart-handler-node.cjs
run_check 'Staging preflight JS syntax' '' node --check scripts/sanro-studio-staging-preflight.cjs
run_check 'Staging preflight regression JS syntax' '' node --check tests/sanro-studio-staging-preflight-node.cjs
run_check 'Staging preflight read-only regression' 'PASS: 9 staging preflight regression groups' node tests/sanro-studio-staging-preflight-node.cjs
run_check 'Staging buyer Cart/Checkout E2E JS syntax' '' node --check tests/staging-cart-checkout-e2e.cjs
run_check 'Staging DTF front/back image E2E JS syntax' '' node --check tests/staging-image-checkout-e2e.cjs
run_check 'Private DTF image QA reuse helper JS syntax' '' node --check tests/staging-image-private-draft.cjs
run_check 'Private DTF image QA reuse regression JS syntax' '' node --check tests/staging-image-private-draft-node.cjs
run_check 'Owner-private DTF image QA reuse safety' 'PASS: 15 owner-private DTF QA reuse and permission checks' node tests/staging-image-private-draft-node.cjs
run_check 'Private DTF image previews and master binding' 'PASS: 7 private DTF image-preview and master-binding checks' node plugins/sanro-studio/tests/buyer-image-preview-node.cjs
run_check 'Private DTF master PHP module syntax' 'No syntax errors detected' php -l plugins/sanro-studio/includes/master-storage.php
run_check 'Private DTF front/back storage regression PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/master-storage-roundtrip.php
run_check 'Private DTF front/back owner-authorized storage roundtrip' 'PASS: 15 isolated private-master front/back roundtrip checks' php plugins/sanro-studio/tests/master-storage-roundtrip.php
run_check 'Short REST master readback PHP test syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/master-read-route-smoke.php
run_check 'Short REST master readback auth and integrity' 'PASS: 20 short-route private DTF read security checks' php plugins/sanro-studio/tests/master-read-route-smoke.php
run_check 'Mobile touch staging QA JS syntax' '' node --check tests/staging-mobile-readonly.cjs
run_check 'Authenticated staging admin visual QA JS syntax' '' node --check tests/staging-admin-print-zones-readonly.cjs
run_check 'Staging runtime output regression PHP syntax' 'No syntax errors detected' php -l tests/staging-pr35-runtime-output-smoke.php
run_check 'Staging runtime exact-line and safe diagnostics' 'PASS: 8 SANRO staging runtime probe newline and fail-closed checks' php tests/staging-pr35-runtime-output-smoke.php
run_check 'Staging generator disabled-mode runtime probe PHP syntax' 'No syntax errors detected' php -l ops/hostinger/sanro-studio-pr35-runtime-probe.php
run_check 'Staging generator guarded deploy Bash syntax' '' bash -n ops/hostinger/sanro-studio-pr35-disabled-generator-stage.sh
run_check 'Staging generator deploy safety fixture PHP syntax' 'No syntax errors detected' php -l tests/staging-pr35-apply-guard-smoke.php
run_check 'Staging generator apply approval and rollback safety contract' 'PASS: 19 SANRO staging generator deployment guard assertions' php tests/staging-pr35-apply-guard-smoke.php
run_check 'DTF admin staging-only plan patcher PHP syntax' 'No syntax errors detected' php -l ops/hostinger/sanro-studio-pr34-bootstrap-plan.php
run_check 'DTF admin staging-only plan fixture PHP syntax' 'No syntax errors detected' php -l tests/staging-dtf-admin-bootstrap-plan-smoke.php
run_check 'DTF admin staging-only plan preservation and OFF controls' 'PASS: 13 SANRO safe admin DTF staging preview assertions' php tests/staging-dtf-admin-bootstrap-plan-smoke.php
run_check 'DTF admin staging SHA-pinned plan Bash syntax' '' bash -n ops/hostinger/sanro-studio-pr34-staging-plan.sh
run_check 'Readiness staging APPLY runtime probe PHP syntax' 'No syntax errors detected' php -l ops/hostinger/sanro-studio-pr38-runtime-probe.php
run_check 'Readiness staging PLAN/APPLY installer Bash syntax' '' bash -n ops/hostinger/sanro-studio-pr38-readiness-disabled-stage.sh
run_check 'Readiness staging APPLY policy smoke PHP syntax' 'No syntax errors detected' php -l tests/staging-pr38-disabled-deploy-guard-smoke.php
run_check 'Readiness staging explicit approval and rollback security' 'PASS: 22 SANRO staging DTF readiness guarded deployment checks' php tests/staging-pr38-disabled-deploy-guard-smoke.php
run_check 'Staging readiness bootstrap preview PHP syntax' 'No syntax errors detected' php -l ops/hostinger/sanro-studio-pr37-readiness-bootstrap-plan.php
run_check 'Staging readiness WP read-only preflight PHP syntax' 'No syntax errors detected' php -l ops/hostinger/sanro-studio-pr37-readiness-runtime-preflight.php
run_check 'Staging readiness PLAN regression PHP syntax' 'No syntax errors detected' php -l tests/staging-pr37-readiness-preview-smoke.php
run_check 'Staging readiness bootstrap preservation and default OFF' 'PASS: 13 SANRO read-only staging readiness preview assertions' php tests/staging-pr37-readiness-preview-smoke.php
run_check 'Staging readiness SHA-pinned PLAN Bash syntax' '' bash -n ops/hostinger/sanro-studio-pr37-readiness-stage-plan.sh
run_check 'Read-only DTF admin readiness module PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/includes/dtf-admin-readiness.php
run_check 'Read-only DTF readiness regression PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/dtf-admin-readiness-smoke.php
run_check 'Read-only DTF admin order/draft readiness and privacy' 'PASS: 29 SANRO read-only admin DTF eligibility checks' php plugins/sanro-studio/tests/dtf-admin-readiness-smoke.php
run_check 'Read-only DTF readiness default-OFF PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/dtf-admin-readiness-default-off.php
run_check 'Read-only DTF readiness default-OFF no draft lookup' 'PASS: 5 SANRO DTF readiness default-OFF checks' php plugins/sanro-studio/tests/dtf-admin-readiness-default-off.php
run_check 'DTF admin candidate generation module PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/includes/dtf-admin-generate.php
run_check 'DTF admin candidate generation regression PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/dtf-admin-generate-smoke.php
run_check 'DTF admin candidate generation security and readback' 'PASS: 34 SANRO nonce-protected DTF admin candidate generation checks' php plugins/sanro-studio/tests/dtf-admin-generate-smoke.php
run_check 'DTF admin generation default-OFF PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/dtf-admin-generate-default-off.php
run_check 'DTF admin generation default-OFF and no-POST' 'PASS: 6 SANRO admin DTF generation default-OFF checks' php plugins/sanro-studio/tests/dtf-admin-generate-default-off.php
run_check 'Staging Woo admin URL/auth guard JS syntax' '' node --check tests/lib/staging-admin-guards.cjs
run_check 'Staging Woo admin browser test JS syntax' '' node --check tests/staging-admin-order-readonly.cjs
run_check 'Staging Woo admin offline guard regression syntax' '' node --check tests/staging-admin-guards-node.cjs
run_check 'Staging Woo admin read-only browser security regression' 'PASS: 30 SANRO staging admin Playwright offline security guards' node tests/staging-admin-guards-node.cjs
run_check 'Hermes staging Woo admin Playwright runner Bash syntax' '' bash -n ops/hermes/sanro-studio-pr29-browser-qa.sh
run_check 'Staging admin login capture policy JS syntax' '' node --check tests/lib/staging-session-capture-policy.cjs
run_check 'Staging admin login CLI JS syntax' '' node --check ops/hermes/sanro-studio-pr30-capture-staging-login.cjs
run_check 'Staging admin login security test JS syntax' '' node --check tests/staging-session-capture-node.cjs
run_check 'Staging admin private session safety regression' 'PASS: 32 SANRO secure staging login session policy checks' node tests/staging-session-capture-node.cjs
run_check 'Hermes one-time staging admin login runner Bash syntax' '' bash -n ops/hermes/sanro-studio-pr30-session-qa.sh


run_check 'Buyer mobile viewport PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/includes/buyer.php
run_check 'Scoped buyer viewport regression PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/mobile-viewport-smoke.php
run_check 'Scoped buyer viewport registration' 'PASS: 10 SANRO buyer viewport/CSS checks' php plugins/sanro-studio/tests/mobile-viewport-smoke.php
run_check 'Auto-private installer PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/includes/private-master-setup.php
run_check 'Auto-private installer regression PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/private-master-setup-smoke.php
run_check 'Auto-private installer security and idempotency' 'PASS: 11 isolated SANRO auto-private installer checks' php plugins/sanro-studio/tests/private-master-setup-smoke.php
run_check 'Auto-private installer default deny PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/private-master-default-off-smoke.php
run_check 'Auto-private installer default deny' 'PASS: 3 default-disabled SANRO private-master gate checks' php plugins/sanro-studio/tests/private-master-default-off-smoke.php
run_check 'Staging browser QA launcher shell syntax' '' bash -n scripts/sanro-studio-staging-browser-qa.sh
run_check 'Pricing parity JS syntax' '' node --check tests/test_sanro_pricing_parity.js
run_check 'Pricing PHP/JS parity' '400 cases PASS, 0 FAIL' node tests/test_sanro_pricing_parity.js
run_check 'WooCommerce product mockup module syntax' 'No syntax errors detected' php -l plugins/sanro-studio/includes/product-mockups.php
run_check 'WooCommerce mockup Media Library JS syntax' '' node --check plugins/sanro-studio/assets/admin-mockups.js
run_check 'WooCommerce mockup regression PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/product-mockups-smoke.php
run_check 'WooCommerce product mockup settings and safety' 'PASS: SANRO product mockup metadata and admin controls' php plugins/sanro-studio/tests/product-mockups-smoke.php
run_check 'WooCommerce mockup preview JS regression syntax' '' node --check plugins/sanro-studio/tests/admin-mockups-node.cjs
run_check 'WooCommerce mockup chooser/preview behavior' '15 SANRO product mockup chooser/preview checks' node plugins/sanro-studio/tests/admin-mockups-node.cjs
run_check 'Per-product mockup print-zone module PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/includes/product-print-zones.php
run_check 'Per-product mockup print-zone regression PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/print-zone-smoke.php
run_check 'Per-product print-zone validation and controls' 'PASS: SANRO per-product print overlay, security and admin controls' php plugins/sanro-studio/tests/print-zone-smoke.php
run_check 'Admin print-zone JS syntax' '' node --check plugins/sanro-studio/assets/admin-print-zones.js
run_check 'Admin print-zone preview regression JS syntax' '' node --check plugins/sanro-studio/tests/admin-print-zones-node.cjs
run_check 'Admin print-zone live guide and mockup update' 'SANRO live admin print-zone preview checks' node plugins/sanro-studio/tests/admin-print-zones-node.cjs
run_check 'Buyer print-zone JS syntax' '' node --check plugins/sanro-studio/assets/buyer.js
run_check 'Side-specific draft geometry JS syntax' '' node --check plugins/sanro-studio/tests/buyer-print-zone-draft-node.cjs
run_check 'Side-specific text/image draft geometry' 'side-specific print-zone draft geometry checks' node plugins/sanro-studio/tests/buyer-print-zone-draft-node.cjs

run_check 'PR41 Hostinger staging two-file PLAN Bash syntax' '' bash -n ops/hostinger/sanro-studio-pr41-twofile-readonly-plan.sh
run_check 'PR41 staging PLAN security test PHP syntax' 'No syntax errors detected' php -l tests/staging-pr41-twofile-plan-policy.php
run_check 'PR41 staging PLAN-only source contract' 'PASS: 20 SANRO PR41 two-file staging PLAN safety checks' php tests/staging-pr41-twofile-plan-policy.php

printf '\nSUMMARY: %s checks PASS, %s checks FAIL\n' "$passed" "$failed"
if [[ $failed -gt 0 ]]; then
  exit 1
fi
exit 0
