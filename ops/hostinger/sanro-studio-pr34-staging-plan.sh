#!/usr/bin/env bash
# Sprint 144 — PLAN ONLY. There is intentionally NO apply flag or live write.
set -Eeuo pipefail
umask 077
BRANCH='manual/sanro-studio-dtf-admin-safe-staging-plan-20261121'
SHA="${SANRO_STUDIO_EXPECTED_SHA:-}"
HOST='saddlebrown-peafowl-616656.hostingersite.com'
ROOT="/home/u606139982/domains/$HOST/public_html"
PLUGIN="$ROOT/wp-content/plugins/sanro-studio"

if [[ "$(hostname -s)" != 'id-dci-web1631' ||
      "$(id -un)" != 'u606139982' ||
      "$EUID" -eq 0 ||
      "${SANRO_STUDIO_QA_ENV:-}" != 'staging' ||
      ! "$SHA" =~ ^[a-f0-9]{40}$ ||
      "${SANRO_STUDIO_STAGE_DEPLOY_MODE:-plan}" != 'plan' ]]; then
    echo 'BLOCKED: exact staging PLAN mode and SHA required' >&2; exit 2
fi
for bin in git php grep mktemp sha256sum realpath; do
    command -v "$bin" >/dev/null || { echo 'BLOCKED: prerequisite missing' >&2; exit 2; }
done
for dir in "$ROOT" "$ROOT/wp-content" "$ROOT/wp-content/plugins" "$PLUGIN" "$PLUGIN/includes"; do
    if [[ ! -d "$dir" || -L "$dir" || "$(realpath "$dir")" != "$dir" ]]; then
        echo 'BLOCKED: staging directory not safe' >&2; exit 2
    fi
done
MAIN="$PLUGIN/sanro-studio.php"
NEW_MODULE="$PLUGIN/includes/dtf-admin-generate.php"
if [[ ! -f "$MAIN" || -L "$MAIN" || -e "$NEW_MODULE" || -L "$NEW_MODULE" ]]; then
    echo 'BLOCKED: live bootstrap unsafe or PR33 generator is already deployed' >&2
    exit 2
fi

QA="$(mktemp -d "$HOME/.sanro-pr34-plan.XXXXXXXX")"
trap 'rm -rf -- "$QA"' EXIT
GIT_TERMINAL_PROMPT=0 git clone -q --depth 1 --single-branch \
    --branch "$BRANCH" \
    https://github.com/arioguswara-oss/Plugin-Kaoscetak.git "$QA/repo"
SOURCE="$QA/repo"
if [[ "$(git -C "$SOURCE" rev-parse HEAD)" != "$SHA" ]]; then
    echo 'BLOCKED: GitHub branch moved; exact SHA mismatch' >&2; exit 2
fi

# Existing WordPress runtime, order and hook inspection is read-only.
SANRO_STUDIO_QA_ENV=staging php -d display_errors=0 \
    "$SOURCE/ops/hostinger/sanro-studio-pr28-wp-runtime-readonly.php" \
    > "$QA/preflight.log" 2>&1 || {
        echo 'BLOCKED: existing real staging WordPress preflight failed' >&2; exit 2;
    }
for requirement in \
    'RUNTIME_PREFLIGHT: PASS' \
    "STAGING_HOST: $HOST" \
    'SANRO_STUDIO_ACTIVE: YES' \
    'WOOCOMMERCE_RUNTIME: AVAILABLE' \
    'HPOS_RUNTIME: ENABLED' \
    'CURRENT_ADMIN_HOOK: REGISTERED' \
    'CURRENT_PREVIEW_HOOK: REGISTERED' \
    'DTF_ADMIN_STATUS_FLAG: YES' \
    'DTF_PRIVATE_ARTIFACT_FLAG: NO' \
    'DTF_ORDER_BATCH_FLAG: NO'; do
    grep -Fqx "$requirement" "$QA/preflight.log" || {
        echo 'BLOCKED: real staging live state drifted; no plan approved' >&2; exit 2;
    }
done

# Confirm the live candidate is not already enabled, even through wp-config.
# No additional WP bootstrap, no reading customer or WooCommerce order data.
php -l "$SOURCE/plugins/sanro-studio/includes/dtf-admin-generate.php" >/dev/null
php -l "$SOURCE/ops/hostinger/sanro-studio-pr34-bootstrap-plan.php" >/dev/null
php "$SOURCE/ops/hostinger/sanro-studio-pr34-bootstrap-plan.php" \
    "$MAIN" "$QA/candidate-main.php" > "$QA/patch.log"
php -l "$QA/candidate-main.php" >/dev/null
php -l "$SOURCE/tests/staging-dtf-admin-bootstrap-plan-smoke.php" >/dev/null
php "$SOURCE/tests/staging-dtf-admin-bootstrap-plan-smoke.php"

printf 'PR: #34\nHEAD: %s\nMODE: plan\nTARGET: HOSTINGER_SANRO_STAGING_ONLY\n' "$SHA"
grep -E '^(WP_VERSION|PHP_VERSION|WP_ENVIRONMENT|WOOCOMMERCE_VERSION|HPOS_RUNTIME|CURRENT_ADMIN_HOOK|DTF_ADMIN_STATUS_FLAG|DTF_PRIVATE_ARTIFACT_FLAG|DTF_ORDER_BATCH_FLAG): ' "$QA/preflight.log"
cat "$QA/patch.log"
echo 'MODULE_dtf-admin-generate.php: ADD'
echo 'BOOTSTRAP: ADDITIVE_PATCH_PREVIEW_ONLY'
echo 'SANRO_STUDIO_ENABLE_DTF_ADMIN_GENERATE: NOT_ENABLED_BY_THIS_PLAN'
echo 'DTF_PRIVATE_ARTIFACTS: OFF'
echo 'DTF_ORDER_BATCH: OFF'
echo 'WORDPRESS_FILES_MODIFIED: NO'
echo 'WOOCOMMERCE_ORDERS_MODIFIED: NO'
echo 'PRODUCTION_MODIFIED: NO'
echo 'STATUS: READY_FOR_SEPARATE_STAGING_REVIEW'
