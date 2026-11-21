#!/usr/bin/env bash
# SANRO Studio Sprint 134 — portable real PHP-GD QA on a SEPARATE test host.
# Run from an isolated Git checkout outside the web document root.
# Does not install packages, use Docker, contact APIs, or touch WordPress.
set -Eeuo pipefail
umask 077

ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd -P)"
if [[ "${EUID}" -eq 0 ]]; then
    printf 'BLOCKED: run as a non-root QA user\n' >&2
    exit 2
fi
for name in php git sha256sum mktemp; do
    if ! command -v "$name" >/dev/null 2>&1; then
        printf 'BLOCKED: missing prerequisite %s\n' "$name" >&2
        exit 2
    fi
done
if ! git -C "$ROOT" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    printf 'BLOCKED: exact source must be an isolated Git checkout\n' >&2
    exit 2
fi
head="$(git -C "$ROOT" rev-parse HEAD)"
if [[ -z "${SANRO_STUDIO_EXPECTED_SHA:-}" || "$head" != "$SANRO_STUDIO_EXPECTED_SHA" ]]; then
    printf 'BLOCKED: explicit SANRO_STUDIO_EXPECTED_SHA required and must match checkout HEAD (%s)\n' "$head" >&2
    exit 2
fi
# Keep all writes away from any WordPress/webroot directory.
if [[ -n "${DOCUMENT_ROOT:-}" && -d "$DOCUMENT_ROOT" ]]; then
    docroot="$(realpath "$DOCUMENT_ROOT")"
    if [[ "$ROOT" == "$docroot" || "$ROOT" == "$docroot/"* ]]; then
        printf 'BLOCKED: test source is within the web document root\n' >&2
        exit 2
    fi
fi
if ! php -r 'exit(extension_loaded("gd") && function_exists("imageresolution") ? 0 : 2);'; then
    printf 'BLOCKED: this QA host must ALREADY provide PHP GD; no installation will be attempted\n' >&2
    exit 2
fi
php -l "$ROOT/plugins/sanro-studio/includes/dtf-image-render.php"
php -l "$ROOT/plugins/sanro-studio/tests/dtf-image-render-smoke.php"
php -l "$ROOT/plugins/sanro-studio/tests/dtf-image-gd-real-smoke.php"

private="$(mktemp -d "${TMPDIR:-/tmp}/sanro-studio-gd-qa.XXXXXXXX")"
chmod 700 "$private"
log="$private/gd-real-test.log"
echo "QA_HEAD: $head"
echo "PHP_VERSION: $(php -r 'echo PHP_VERSION;')"
echo "PHP_GD: AVAILABLE"
echo "PRIVATE_ARTIFACT_DIR: $private"
# Synthetic-only fixture; no user images, WooCommerce DB or Drive access.
set +e
(cd "$ROOT" && SANRO_DTF_ARTIFACT_DIR="$private" \
    php -d memory_limit=256M -d display_errors=1 \
    plugins/sanro-studio/tests/dtf-image-gd-real-smoke.php) > "$log" 2>&1
status=$?
set -e
cat "$log"
if [[ "$status" -ne 0 ]] \
    || ! grep -Fqx 'PASS: 15 SANRO real PHP GD DTF raster assertions' "$log" \
    || ! grep -Fq 'PHP_GD_RASTER: PASS (actual PNG decoded and verified)' "$log" \
    || ! grep -Fq 'GD_RASTER_REAL: VERIFIED' "$log"; then
    printf 'FAIL: GD raster regression (exit %s); log: %s\n' "$status" "$log" >&2
    exit 1
fi
artifact="$private/synthetic-dtf-5cm-300dpi-rgba.png"
manifest="$private/synthetic-dtf-manifest.json"
if [[ ! -s "$artifact" || ! -s "$manifest" ]]; then
    printf 'FAIL: missing private PNG or manifest\n' >&2
    exit 1
fi
expected="$(php -r '$m=json_decode(file_get_contents($argv[1]),true); echo is_array($m)?($m["sha256"]??""):"";' "$manifest")"
actual="$(sha256sum "$artifact" | cut -d ' ' -f 1)"
if [[ ! "$actual" =~ ^[a-f0-9]{64}$ || "$expected" != "$actual" ]]; then
    printf 'FAIL: output digest differs from manifest\n' >&2
    exit 1
fi
printf 'PR: #23\nHEAD: %s\nSTATUS: PASS\nTEST_EXIT: 0\nMODE: EXTERNAL_ISOLATED_QA_HOST\nREAL_GD_ASSERTIONS: 15 PASS, 0 FAIL\nGD_RASTER_REAL: VERIFIED\nARTIFACT_SHA256: %s\nPRIVATE_ARTIFACT_DIR: %s\n' "$head" "$actual" "$private"
