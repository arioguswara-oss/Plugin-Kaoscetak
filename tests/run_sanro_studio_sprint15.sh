#!/usr/bin/env bash
# Sprint 15: isolated CLI regression, no WordPress writes or deployment.
set -Eeuo pipefail
cd "$(dirname "$0")/.."
tests=(
  tests/test_sanro_buyer_gate.php
  tests/test_sanro_draft.php
  tests/test_sanro_draft_rest.php
  tests/test_sanro_quote.php
  tests/test_sanro_product_pricing.php
  tests/test_sanro_print_area.php
  tests/test_sanro_checkout_bridge.php
  tests/test_sanro_order_preview.php
)
passed=0
failed=0
missing=0
for file in "${tests[@]}"; do
  if [[ ! -f "$file" ]]; then
    printf 'NOT TESTED missing: %s\n' "$file"
    ((missing+=1))
    continue
  fi
  if php -l "$file" && php "$file"; then
    printf 'PASS: %s\n' "$file"
    ((passed+=1))
  else
    printf 'FAIL: %s\n' "$file"
    ((failed+=1))
  fi
done
printf 'SPRINT 15 SUMMARY PASS=%d FAIL=%d NOT_TESTED=%d\n' "$passed" "$failed" "$missing"
git status --short
if (( failed > 0 || missing > 0 )); then exit 1; fi
