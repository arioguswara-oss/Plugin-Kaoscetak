#!/usr/bin/env bash
set -Eeuo pipefail
cd "$(dirname "$0")/.."
tests=(test_sanro_buyer_gate test_sanro_draft test_sanro_draft_rest test_sanro_quote test_sanro_product_pricing test_sanro_print_area test_sanro_checkout_bridge test_sanro_order_preview test_sanro_order_item_preview test_sanro_production_readiness)
pass=0; fail=0
for name in "${tests[@]}"; do
  file="tests/${name}.php"
  if [[ ! -f "$file" ]]; then echo "FAIL missing: $file"; ((fail+=1)); continue; fi
  if php -l "$file" && {
    if [[ "$name" == test_sanro_draft ]]; then
      php -r 'define("DAY_IN_SECONDS",86400); require $argv[1];' "$file"
    else
      php "$file"
    fi
  }; then echo "PASS: $file"; ((pass+=1))
  else echo "FAIL: $file"; ((fail+=1)); fi
done
echo "SUMMARY PASS=$pass FAIL=$fail"
echo "NOT TESTED: WooCommerce live/staging checkout and order preview E2E"
((fail==0))
