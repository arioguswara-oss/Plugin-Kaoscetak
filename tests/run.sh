#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
node --check plugins/kaoscetak-lite-designer/assets/editor.js
docker compose -f tests/compose.yml exec -T wordpress sh -c 'set -e; find /fixtures/plugins /fixtures/tests -name "*.php" -print0 | xargs -0 -n1 php -l; cd /fixtures; for f in tests/test_*.php; do php "$f"; done'
docker compose -f tests/compose.yml exec -T wordpress php /fixtures/tests/.downloads/wp-cli.phar --allow-root eval-file /fixtures/tests/wordpress_regression.php
docker compose -f tests/compose.yml exec -T wordpress php /fixtures/tests/.downloads/wp-cli.phar --allow-root eval-file /fixtures/tests/variable_integration.php
CHROMIUM_PATH=${CHROMIUM_PATH:-/usr/bin/chromium} python3 tests/test_editor_responsive.py
CHROMIUM_PATH=${CHROMIUM_PATH:-/usr/bin/chromium} python3 tests/test_editor_browser.py
CHROMIUM_PATH=${CHROMIUM_PATH:-/usr/bin/chromium} node tests/customer-flow.cjs
CHROMIUM_PATH=${CHROMIUM_PATH:-/usr/bin/chromium} node tests/lifecycle.cjs
git diff --check
