# Sprint 134 isolated raster QA only. Never deployed as an application image.
# Build packages are installed INSIDE this disposable image, NOT on the VPS.
FROM php:8.3-cli-bookworm
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends libpng-dev; \
    docker-php-ext-install -j2 gd; \
    php -r 'if (!extension_loaded("gd") || !function_exists("imageresolution")) exit(1);'; \
    rm -rf /var/lib/apt/lists/*
WORKDIR /src
