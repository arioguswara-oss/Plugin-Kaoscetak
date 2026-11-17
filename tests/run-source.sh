#!/bin/sh
# No Docker, network, production credentials or WordPress installation required.
set -eu
cd "$(dirname "$0")/.."
PHP_BIN=${PHP_BIN:-php}
command -v node >/dev/null 2>&1 || { echo 'BLOCKED: Node.js unavailable' >&2; exit 127; }
find plugins tests -path 'plugins/sanro-studio/runtime/editor' -prune -o -type f \( -name '*.js' -o -name '*.cjs' \) -print0 | xargs -0 -n1 node --check
node tests/test_editor_unit.cjs
command -v "$PHP_BIN" >/dev/null 2>&1 || { echo 'BLOCKED: PHP CLI unavailable' >&2; exit 127; }
find plugins tests -path 'plugins/sanro-studio/runtime/editor' -prune -o -type f -name '*.php' -print0 | xargs -0 -n1 "$PHP_BIN" -l
"$PHP_BIN" plugins/kaoscetak-lite-designer/tests/pricing-smoke.php
for test_file in tests/test_*.php; do "$PHP_BIN" "$test_file"; done
python3 -m unittest discover -s tests -p 'test_sanro_runtime.py'
git diff --check
