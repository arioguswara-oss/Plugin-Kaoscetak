#!/usr/bin/env bash
# SANRO Studio Sprint 141: targeted, default-PLAN-only Hostinger staging patch.
# Add ONLY five DTF candidate modules, additive main bootstrap, and staging-
# guarded read-only admin display MU flag; never replace the entire plugin.
# No WordPress database edits, product/order changes, checkout or production.
set -Eeuo pipefail
umask 077

BRANCH='manual/sanro-studio-dtf-admin-staging-targeted-20261121'
STAGE_HOST='saddlebrown-peafowl-616656.hostingersite.com'
WP_ROOT="/home/u606139982/domains/$STAGE_HOST/public_html"
PLUGIN_DIR="$WP_ROOT/wp-content/plugins/sanro-studio"
PLUGIN_MAIN="$PLUGIN_DIR/sanro-studio.php"
MU_DIR="$WP_ROOT/wp-content/mu-plugins"
MU_NAME='sanro-studio-dtf-admin-staging.php'
MODE="${SANRO_STUDIO_STAGE_DEPLOY_MODE:-plan}"
EXPECTED="${SANRO_STUDIO_EXPECTED_SHA:-}"

if [[ "$(hostname -s)" != 'id-dci-web1631'
    || "$(id -un)" != 'u606139982'
    || "$EUID" -eq 0
    || "${SANRO_STUDIO_QA_ENV:-}" != 'staging' ]]; then
    echo 'BLOCKED: exact Hostinger SANRO Studio staging user/host required' >&2
    exit 2
fi
if [[ "$MODE" != 'plan' && "$MODE" != 'apply' ]]; then
    echo 'BLOCKED: mode must be plan or apply' >&2; exit 2
fi
if [[ ! "$EXPECTED" =~ ^[0-9a-f]{40}$ ]]; then
    echo 'BLOCKED: exact PR SHA is required' >&2; exit 2
fi
if [[ "$MODE" == 'apply'
    && "${SANRO_STUDIO_APPROVE_STAGING_DEPLOY:-}" != 'STAGING_ONLY_APPROVED' ]]; then
    echo 'BLOCKED: explicit staging-only deployment approval required' >&2
    exit 2
fi
for binary in php git sha256sum cmp mktemp stat install grep; do
    command -v "$binary" >/dev/null || {
        echo "BLOCKED: missing prerequisite $binary" >&2
        exit 2
    }
done
if [[ ! -f "$PLUGIN_MAIN" || -L "$PLUGIN_MAIN"
    || ! -d "$PLUGIN_DIR" || -L "$PLUGIN_DIR"
    || "$(realpath "$WP_ROOT")" != "$WP_ROOT" ]]; then
    echo 'BLOCKED: exact staging WordPress plugin not found or unsafe symlink' >&2
    exit 2
fi

ROOT_TMP="$(mktemp -d "$HOME/.sanro-pr31-stage.XXXXXXXX")"
cleanup(){ [[ ! -d "${ROOT_TMP:-}" ]] || rm -rf -- "$ROOT_TMP"; }
trap cleanup EXIT
GIT_TERMINAL_PROMPT=0 git clone --depth 1 --single-branch \
  --branch "$BRANCH" \
  https://github.com/arioguswara-oss/Plugin-Kaoscetak.git "$ROOT_TMP/repo" >/dev/null
SOURCE="$ROOT_TMP/repo"
GOT="$(git -C "$SOURCE" rev-parse HEAD)"
if [[ "$GOT" != "$EXPECTED" ]]; then
    echo 'BLOCKED: GitHub branch SHA changed; do not patch staging' >&2
    exit 2
fi

# Real WP bootstrap preflight independently verifies exact Hostinger staging
# home/site URL and live WooCommerce. This script never trusts hostname alone.
SANRO_STUDIO_QA_ENV=staging \
    php -d display_errors=0 "$SOURCE/ops/hostinger/sanro-studio-pr28-wp-runtime-readonly.php" \
    > "$ROOT_TMP/before.log" 2>&1 || {
        cat "$ROOT_TMP/before.log"
        echo 'BLOCKED: real staging WP runtime preflight failed' >&2
        exit 2
    }
grep -Fqx 'RUNTIME_PREFLIGHT: PASS' "$ROOT_TMP/before.log"
grep -Fqx 'SANRO_STUDIO_ACTIVE: YES' "$ROOT_TMP/before.log"
grep -Fqx 'WOOCOMMERCE_RUNTIME: AVAILABLE' "$ROOT_TMP/before.log"
grep -Fqx "STAGING_HOST: $STAGE_HOST" "$ROOT_TMP/before.log"
grep -Fqx 'CURRENT_PREVIEW_HOOK: REGISTERED' "$ROOT_TMP/before.log"
grep -Fqx 'HPOS_RUNTIME: ENABLED' "$ROOT_TMP/before.log"
grep -Fqx 'CURRENT_ADMIN_HOOK: ABSENT' "$ROOT_TMP/before.log" || {
    echo 'BLOCKED: candidate admin hook already registered; no blind update' >&2
    exit 2
}

# Inspect only the exact files added by this surgical staging patch.
MODULES=(
  dtf-image-render.php
  dtf-layer-render.php
  dtf-private-artifacts.php
  dtf-order-workflow.php
  dtf-admin-order-status.php
)
for module in "${MODULES[@]}"; do
    sourcefile="$SOURCE/plugins/sanro-studio/includes/$module"
    target="$PLUGIN_DIR/includes/$module"
    php -l "$sourcefile" >/dev/null
    if [[ -L "$target" || ( -e "$target" && ! -f "$target" ) ]]; then
        echo "BLOCKED: unsafe existing module: $module" >&2; exit 2
    fi
    if [[ -f "$target" ]] && ! cmp -s "$sourcefile" "$target"; then
        echo "BLOCKED: unexpected existing module differs: $module" >&2
        exit 2
    fi
done
# Output a linted candidate into private temporary storage, never WordPress.
php "$SOURCE/ops/hostinger/sanro-studio-pr31-bootstrap-preview.php" \
    "$PLUGIN_MAIN" "$ROOT_TMP/patched-main.php" > "$ROOT_TMP/patch-info.txt"
php -l "$ROOT_TMP/patched-main.php" >/dev/null
MU_SOURCE="$SOURCE/ops/hostinger/mu-plugins/$MU_NAME"
MU_TARGET="$MU_DIR/$MU_NAME"
php -l "$MU_SOURCE" >/dev/null
if [[ -L "$MU_DIR" || -L "$MU_TARGET"
    || ( -e "$MU_TARGET" && ! -f "$MU_TARGET" ) ]]; then
    echo 'BLOCKED: unexpected WordPress must-use plugin path' >&2
    exit 2
fi
if [[ -f "$MU_TARGET" ]] && ! cmp -s "$MU_SOURCE" "$MU_TARGET"; then
    echo 'BLOCKED: conflicting existing staging-only admin opt-in' >&2
    exit 2
fi

printf 'PR: #31\nHEAD: %s\nTARGET: HOSTINGER_STAGING_ONLY\nMODE: %s\n' "$GOT" "$MODE"
grep -E '^(WP_VERSION|PHP_VERSION|WOOCOMMERCE_VERSION|HPOS_RUNTIME|WP_ENVIRONMENT): ' "$ROOT_TMP/before.log"
if grep -Fqx 'WP_ENVIRONMENT: production' "$ROOT_TMP/before.log"; then
    echo 'WARNING: WordPress still reports WP_ENVIRONMENT production; host/root guard enforced'
fi
cat "$ROOT_TMP/patch-info.txt"
for module in "${MODULES[@]}"; do
    target="$PLUGIN_DIR/includes/$module"
    if [[ -f "$target" ]]; then
        echo "MODULE_$module: SAME"
    else
        echo "MODULE_$module: ADD"
    fi
done
printf 'STAGING_ADMIN_FLAG: %s\n' "$([[ -f "$MU_TARGET" ]] && echo SAME || echo ADD)"
if [[ "$MODE" == 'plan' ]]; then
    echo 'STATUS: READY_FOR_EXPLICIT_STAGING_APPROVAL'
    echo 'PRODUCTION_MODIFIED: NO'
    exit 0
fi

# Preserve only the directly modified bootstrap for safe rollback if needed;
# do not touch any WooCommerce order, option or database table.
ROLLBACK_DIR="$HOME/.local/state/sanro-studio-staging-deploy"
mkdir -p "$ROLLBACK_DIR"
chmod 700 "$ROLLBACK_DIR"
old_sha="$(sha256sum "$PLUGIN_MAIN" | cut -d' ' -f1)"
ROLLBACK_MAIN="$ROLLBACK_DIR/$(date -u +%Y%m%dT%H%M%SZ)-$old_sha.php"
install -m 0600 "$PLUGIN_MAIN" "$ROLLBACK_MAIN"
added_modules=()
mu_created=0
main_updated=0
apply_started=0
apply_succeeded=0
rollback(){
    if [[ "$main_updated" -eq 1 ]]; then
        install -m 0644 "$ROLLBACK_MAIN" "$ROOT_TMP/restore-main.php"
        mv -f "$ROOT_TMP/restore-main.php" "$PLUGIN_MAIN" || true
    fi
    if [[ "$mu_created" -eq 1 ]]; then rm -f -- "$MU_TARGET" || true; fi
    for module in "${added_modules[@]}"; do
        rm -f -- "$PLUGIN_DIR/includes/$module" || true
    done
}
on_exit(){
    local rc=$?
    if [[ "$apply_started" -eq 1 && "$apply_succeeded" -ne 1 ]]; then
        rollback
    fi
    cleanup
    return "$rc"
}
trap on_exit EXIT
apply_started=1

# Load modules first. Existing preview hook remains unchanged until main
# bootstrap is atomically switched to the linted additive candidate.
for module in "${MODULES[@]}"; do
    target="$PLUGIN_DIR/includes/$module"
    if [[ ! -f "$target" ]]; then
        install -m 0644 "$SOURCE/plugins/sanro-studio/includes/$module" "$target"
        added_modules+=("$module")
    fi
done
install -m 0644 "$ROOT_TMP/patched-main.php" "$ROOT_TMP/live-main-ready.php"
mv -f "$ROOT_TMP/live-main-ready.php" "$PLUGIN_MAIN"
main_updated=1
mkdir -p "$MU_DIR"
if [[ ! -f "$MU_TARGET" ]]; then
    install -m 0644 "$MU_SOURCE" "$MU_TARGET"
    mu_created=1
fi

# Verify PHP runtime loads our new admin status callback and opt-in, while
# private DTF writer and batch remain OFF. No customer data is read.
SANRO_STUDIO_QA_ENV=staging php -d display_errors=0 \
  "$SOURCE/ops/hostinger/sanro-studio-pr28-wp-runtime-readonly.php" \
  > "$ROOT_TMP/after.log" 2>&1
for expected_line in \
    'RUNTIME_PREFLIGHT: PASS' \
    'CURRENT_ADMIN_HOOK: REGISTERED' \
    'DTF_ADMIN_STATUS_FLAG: YES' \
    'DTF_PRIVATE_ARTIFACT_FLAG: NO' \
    'DTF_ORDER_BATCH_FLAG: NO' \
    'STAGING_ADMIN_MODULE_MATCHES_CANDIDATE: YES'; do
    if ! grep -Fqx "$expected_line" "$ROOT_TMP/after.log"; then
        grep -E '^(RUNTIME_PREFLIGHT|CURRENT_ADMIN_HOOK|DTF_ADMIN_STATUS_FLAG|DTF_PRIVATE_ARTIFACT_FLAG|DTF_ORDER_BATCH_FLAG|STAGING_ADMIN_MODULE_MATCHES_CANDIDATE): ' "$ROOT_TMP/after.log" || true
        echo "FAIL: staging deployment postflight assertion: $expected_line" >&2
        exit 1
    fi
done
apply_succeeded=1
echo 'STATUS: DEPLOYED_STAGING_ONLY'
echo 'CURRENT_ADMIN_HOOK: REGISTERED'
echo 'DTF_ADMIN_STATUS_FLAG: YES'
echo 'DTF_PRIVATE_ARTIFACT_FLAG: NO'
echo 'DTF_ORDER_BATCH_FLAG: NO'
echo 'PRODUCTION_MODIFIED: NO'
