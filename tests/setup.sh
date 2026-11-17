#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
test "${KLD_ALLOW_LOCAL_FIXTURE:-}" = 1 || { echo 'Set KLD_ALLOW_LOCAL_FIXTURE=1 for disposable loopback fixture only'; exit 1; }
mkdir -p tests/.downloads tests/results
if [ ! -f tests/.downloads/wp-cli.phar ]; then curl -fsSL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o tests/.downloads/wp-cli.phar; fi
if [ ! -f tests/.downloads/woocommerce.zip ]; then curl -fsSL https://downloads.wordpress.org/plugin/woocommerce.11.3.0.zip -o tests/.downloads/woocommerce.zip; fi
if [ ! -f tests/.downloads/twentytwentyone.zip ]; then curl -fsSL https://downloads.wordpress.org/theme/twentytwentyone.latest-stable.zip -o tests/.downloads/twentytwentyone.zip; fi
python3 scripts/build.py
docker compose -f tests/compose.yml up -d --wait
wp(){ docker compose -f tests/compose.yml exec -T wordpress php /fixtures/tests/.downloads/wp-cli.phar --allow-root "$@"; }
for attempt in 1 2 3 4 5 6 7 8 9 10; do if wp core is-installed >/dev/null 2>&1; then break; fi; if wp core install --url=http://127.0.0.1:8893 --title='KLD synthetic only' --admin_user=fixture --admin_password=fixture-local-only --admin_email=admin@example.test --skip-email; then break; fi; sleep 2; done
if ! wp plugin is-installed woocommerce; then wp plugin install /fixtures/tests/.downloads/woocommerce.zip --activate; fi
wp plugin activate woocommerce
KLD_QA_ZIP=$(python3 -c 'import json; print(json.load(open("dist/manifest.json"))["file"])')
wp plugin install "/fixtures/dist/$KLD_QA_ZIP" --activate --force
wp theme install /fixtures/tests/.downloads/twentytwentyone.zip --activate --force
wp rewrite structure '/%postname%/' --hard
wp option update woocommerce_currency IDR
wp option update woocommerce_default_country ID:JK
wp option update woocommerce_enable_guest_checkout yes
wp option update woocommerce_calc_taxes no
wp eval-file /fixtures/tests/wordpress_smoke.php
wp eval-file /fixtures/tests/fixture.php
