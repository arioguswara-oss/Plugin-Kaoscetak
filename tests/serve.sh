#!/bin/sh
set -eu
cd /var/www/html
if [ ! -f wp-load.php ]; then cp -a /usr/src/wordpress/. .; fi
if [ ! -f wp-config.php ]; then cp wp-config-docker.php wp-config.php; fi
export PHP_CLI_SERVER_WORKERS=4
exec php -S 0.0.0.0:8000 /fixtures/tests/router.php
