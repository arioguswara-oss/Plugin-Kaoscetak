#!/usr/bin/env bash
# Sprint 147 — SHA-pinned Hostinger staging readiness diagnostics PLAN ONLY.
# No apply mode, WordPress changes, order writes, feature flag enables or Drive.
set -Eeuo pipefail
umask 077
BRANCH='manual/sanro-studio-dtf-readiness-stage-plan-20261122'
SHA="${SANRO_STUDIO_EXPECTED_SHA:-}"
HOST='saddlebrown-peafowl-616656.hostingersite.com'
WP_ROOT="/home/u606139982/domains/$HOST/public_html"
PLUGIN="$WP_ROOT/wp-content/plugins/sanro-studio"
MAIN="$PLUGIN/sanro-studio.php"
MODULE="$PLUGIN/includes/dtf-admin-readiness.php"
OLD_SHA='5dfb26a784882356dac82b19d6d4f5883cecbce473d74ae2d7da4daaecacdf35'

if [[ "$(hostname -s)" != 'id-dci-web1631'
    || "$(id -un)" != 'u606139982'
    || "$EUID" -eq 0
    || "${SANRO_STUDIO_QA_ENV:-}" != 'staging'
    || "${SANRO_STUDIO_STAGE_DEPLOY_MODE:-plan}" != 'plan'
    || ! "$SHA" =~ ^[0-9a-f]{40}$ ]]; then
    echo 'BLOCKED: Hostinger staging owner, SHA and PLAN-only mode required' >&2
    exit 2
fi
for bin in git php sha256sum realpath grep mktemp stat; do
    command -v "$bin" >/dev/null || {
        echo "BLOCKED: prerequisite missing ($bin)" >&2
        exit 2
    }
done
for dir in "$WP_ROOT" "$WP_ROOT/wp-content" "$PLUGIN" "$PLUGIN/includes"; do
    if [[ ! -d "$dir" || -L "$dir" || "$(realpath "$dir")" != "$dir" ]]; then
        echo 'BLOCKED: staging directory unsafe' >&2
        exit 2
    fi
done
if [[ ! -f "$MAIN" || -L "$MAIN" || -e "$MODULE" || -L "$MODULE"
    || "$(sha256sum "$MAIN" | cut -d' ' -f1)" != "$OLD_SHA" ]]; then
    echo 'BLOCKED: PR35 bootstrap hash drift or readiness already installed' >&2
    exit 2
fi
QA="$(mktemp -d "$HOME/.sanro-pr37-plan.XXXXXXXX")"
trap 'rm -rf -- "$QA"' EXIT
GIT_TERMINAL_PROMPT=0 git clone -q --depth 1 --single-branch \
    --branch "$BRANCH" \
    https://github.com/arioguswara-oss/Plugin-Kaoscetak.git "$QA/repo"
if [[ "$(git -C "$QA/repo" rev-parse HEAD)" != "$SHA" ]]; then
    echo 'BLOCKED: GitHub branch HEAD differs from pinned SHA' >&2
    exit 2
fi
SOURCE="$QA/repo"
for f in \
    "$SOURCE/plugins/sanro-studio/includes/dtf-admin-readiness.php" \
    "$SOURCE/ops/hostinger/sanro-studio-pr37-readiness-bootstrap-plan.php" \
    "$SOURCE/ops/hostinger/sanro-studio-pr37-readiness-runtime-preflight.php"; do
    php -l "$f" >/dev/null
done
SANRO_STUDIO_QA_ENV=staging SANRO_STUDIO_PR37_MODE=plan \
    php -d display_errors=0 \
    "$SOURCE/ops/hostinger/sanro-studio-pr37-readiness-runtime-preflight.php" \
    > "$QA/preflight.log" 2> "$QA/preflight.err" || {
        echo 'BLOCKED: live WordPress/HPOS/PR35 staging readiness preflight failed' >&2
        exit 2
    }
for required in \
    'PR37_WP_RUNTIME: VERIFIED' \
    'PR35_GENERATOR_HOOK: REGISTERED' \
    'PR35_GENERATOR_FLAG: OFF' \
    'PR37_READINESS_HOOK: ABSENT' \
    'PR37_READINESS_FLAG: OFF' \
    'DTF_RASTER_PRIVATE_BATCH: OFF' \
    'DRIVE_WRITERS: OFF' \
    'HPOS_RUNTIME: ENABLED' \
    'STAGING_WP_READ_ONLY: PASS'; do
    if ! grep -Fqx "$required" "$QA/preflight.log"; then
        echo "BLOCKED: required read-only probe missing ($required)" >&2
        exit 2
    fi
done
php "$SOURCE/ops/hostinger/sanro-studio-pr37-readiness-bootstrap-plan.php" \
    "$MAIN" "$QA/readiness-preview.php" > "$QA/patch.log"
php -l "$QA/readiness-preview.php" >/dev/null
php -l "$SOURCE/tests/staging-pr37-readiness-preview-smoke.php" >/dev/null
php "$SOURCE/tests/staging-pr37-readiness-preview-smoke.php" \
    > "$QA/test.log"
if [[ "$(sha256sum "$MAIN" | cut -d' ' -f1)" != "$OLD_SHA" || -e "$MODULE" ]]; then
    echo 'BLOCKED: staging files drifted during PLAN' >&2
    exit 2
fi
printf 'PR: #37\nHEAD: %s\nTARGET: HOSTINGER_SANRO_STAGING_ONLY\nMODE: plan\n' "$SHA"
cat "$QA/test.log"
cat "$QA/patch.log"
echo 'MODULE_dtf-admin-readiness.php: ADD'
echo 'PR35_LEGACY_PREVIEW_STATUS_GENERATOR: PRESERVED'
echo 'DTF_READINESS_FLAG: OFF'
echo 'DTF_GENERATOR_FLAG: OFF'
echo 'DTF_PRIVATE_ARTIFACTS: OFF'
echo 'DTF_ORDER_BATCH: OFF'
echo 'DRIVE_WRITERS: OFF'
echo 'WORDPRESS_FILES_MODIFIED: NO'
echo 'WOOCOMMERCE_ORDERS_MODIFIED: NO'
echo 'PRODUCTION_MODIFIED: NO'
echo 'STATUS: READY_FOR_SEPARATE_PR37_STAGING_APPROVAL'
