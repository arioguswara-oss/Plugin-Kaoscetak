#!/bin/sh
# No Docker, network, production credentials or WordPress installation required.
set -eu
cd "$(dirname "$0")/.."
PHP_BIN=${PHP_BIN:-php}
command -v node >/dev/null 2>&1 || { echo 'BLOCKED: Node.js unavailable' >&2; exit 127; }
find plugins tests -path 'plugins/sanro-studio/runtime/editor' -prune -o -type f \( -name '*.js' -o -name '*.cjs' \) -print0 | xargs -0 -n1 node --check
node tests/test_editor_unit.cjs
node tests/test_sanro_editor_geometry.js
node tests/test_sanro_estimate.js
node tests/test_sanro_pricing_parity.js
command -v "$PHP_BIN" >/dev/null 2>&1 || { echo 'BLOCKED: PHP CLI unavailable' >&2; exit 127; }
find plugins tests -path 'plugins/sanro-studio/runtime/editor' -prune -o -type f -name '*.php' -print0 | xargs -0 -n1 "$PHP_BIN" -l
"$PHP_BIN" plugins/kaoscetak-lite-designer/tests/pricing-smoke.php
# Native staging gates run in separate processes because test fixtures define globals.
for scenario in missing_woocommerce buyer_disabled buyer_enabled; do
  "$PHP_BIN" plugins/sanro-studio/tests/native-readiness-gates.php "$scenario"
done
"$PHP_BIN" plugins/sanro-studio/tests/native-missing-component.php
"$PHP_BIN" plugins/sanro-studio/tests/cart-identity-contract.php
for test_file in tests/test_*.php; do "$PHP_BIN" "$test_file"; done
python3 -m unittest discover -s tests -p 'test_sanro_runtime.py'
git diff --check
