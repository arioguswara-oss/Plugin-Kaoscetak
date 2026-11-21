#!/usr/bin/env bash
# Sprint 133: fail-fast, isolated PHP diagnostic. No WordPress, network,
# checkout, production writes or external image dependencies.
set -Eeuo pipefail

ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

for source in \
    plugins/sanro-studio/includes/print-area.php \
    plugins/sanro-studio/includes/dtf-image-render.php \
    plugins/sanro-studio/tests/dtf-image-render-smoke.php; do
    php -l "$source" || exit 1
done

echo '=== Targeted DTF regression (fail-fast) ==='
set +e
output="$(php -d display_errors=1 -d display_startup_errors=1 \
    -d error_reporting=-1 plugins/sanro-studio/tests/dtf-image-render-smoke.php 2>&1)"
rc=$?
set -e
printf '%s\n' "$output"

if [[ "$rc" -ne 0 ]]; then
    printf 'FAIL: targeted DTF PHP smoke exited %s\n' "$rc" >&2
    exit 1
fi
if ! grep -Fqx 'PASS: 24 SANRO image-only DTF raster safety checks' <<< "$output"; then
    printf 'FAIL: DTF smoke did not execute all 24 dynamic assertions\n' >&2
    exit 1
fi

gd="$(php -r 'echo function_exists("imagecreatefromstring") && function_exists("imageresolution") ? "yes" : "no";')"
if [[ "$gd" == yes ]]; then
    if ! grep -Fq 'PHP_GD_RASTER: PASS (actual PNG decoded and verified)' <<< "$output"; then
        printf 'FAIL: PHP GD exists but no actual raster was verified\n' >&2
        exit 1
    fi
else
    if ! grep -Fq 'PHP_GD_RASTER: NOT_TESTED (GD extension unavailable; fail-closed verified)' <<< "$output"; then
        printf 'FAIL: no-GD fail-closed assertions were not verified\n' >&2
        exit 1
    fi
fi

printf 'PASS: targeted DTF smoke; PHP GD=%s\n' "$gd"
