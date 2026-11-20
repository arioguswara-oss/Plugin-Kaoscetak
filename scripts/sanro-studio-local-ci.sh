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
run_check 'Buyer PHP asset-version syntax' 'No syntax errors detected' php -l plugins/sanro-studio/includes/buyer.php
run_check 'Buyer asset fingerprint regression syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/buyer-asset-version-smoke.php
run_check 'Buyer asset fingerprint' 'PASS: 3 SANRO Studio buyer asset fingerprint assertions' php plugins/sanro-studio/tests/buyer-asset-version-smoke.php
run_check 'Staging buyer frontend JS syntax' '' node --check plugins/sanro-studio/assets/buyer.js
run_check 'Cart handler regression JS syntax' '' node --check plugins/sanro-studio/tests/buyer-cart-handler-node.cjs
run_check 'Cart handler delegation and no duplicate POST' 'PASS: 8 delegated Cart handler regression checks' node plugins/sanro-studio/tests/buyer-cart-handler-node.cjs
run_check 'Staging preflight JS syntax' '' node --check scripts/sanro-studio-staging-preflight.cjs
run_check 'Staging preflight regression JS syntax' '' node --check tests/sanro-studio-staging-preflight-node.cjs
run_check 'Staging preflight read-only regression' 'PASS: 8 staging preflight regression groups' node tests/sanro-studio-staging-preflight-node.cjs
run_check 'Staging buyer Cart/Checkout E2E JS syntax' '' node --check tests/staging-cart-checkout-e2e.cjs
run_check 'Staging DTF front/back image E2E JS syntax' '' node --check tests/staging-image-checkout-e2e.cjs
run_check 'Mobile touch staging QA JS syntax' '' node --check tests/staging-mobile-readonly.cjs
run_check 'Buyer mobile viewport PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/includes/buyer.php
run_check 'Scoped buyer viewport regression PHP syntax' 'No syntax errors detected' php -l plugins/sanro-studio/tests/mobile-viewport-smoke.php
run_check 'Scoped buyer viewport registration' 'PASS: 10 SANRO buyer viewport/CSS checks' php plugins/sanro-studio/tests/mobile-viewport-smoke.php
run_check 'Staging browser QA launcher shell syntax' '' bash -n scripts/sanro-studio-staging-browser-qa.sh
run_check 'Pricing parity JS syntax' '' node --check tests/test_sanro_pricing_parity.js
run_check 'Pricing PHP/JS parity' '400 cases PASS, 0 FAIL' node tests/test_sanro_pricing_parity.js

printf '\nSUMMARY: %s checks PASS, %s checks FAIL\n' "$passed" "$failed"
if [[ $failed -gt 0 ]]; then
  exit 1
fi
exit 0
