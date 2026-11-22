#!/usr/bin/env bash
# Fast offline unit/contract checks for the first SANRO Studio vertical slice.
# Run: bash scripts/sanro-studio-slice-check.sh
set -euo pipefail
cd "$(dirname "$0")/.."
command -v php >/dev/null || { echo "FAIL: php is required" >&2; exit 2; }
for file in \
  plugins/sanro-studio/includes/buyer.php \
  plugins/sanro-studio/includes/draft-rest.php \
  plugins/sanro-studio/includes/checkout-bridge.php \
  plugins/sanro-studio/includes/checkout-batch-lock.php \
  plugins/sanro-studio/includes/order-preview.php \
  plugins/sanro-studio/includes/storage-policy.php; do
  php -l "$file" >/dev/null
done
for test in \
  tests/staging-one-order-checkout-boundary-offline.php \
  tests/staging-one-order-woo-cart-adapter-offline.php \
  plugins/sanro-studio/tests/checkout-batch-lock-smoke.php; do
  echo "RUN: $test"
  php "$test"
done
echo "PASS: SANRO Studio offline slice checks (no DB, no order, no deploy)"
