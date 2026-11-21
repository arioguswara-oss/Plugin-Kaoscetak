#!/usr/bin/env bash
# Sprint 148: exact-host, SHA-pinned STAGING READ-ONLY readiness module installer.
# DEFAULT PLAN. APPLY requires separate, explicit owner authorization.
# Readiness/generator/raster/private storage/batch/Drive MUST remain OFF.
# No database/order edits, checkout/payment, Google Drive or production.
set -Eeuo pipefail
umask 077

BRANCH='manual/sanro-studio-dtf-readiness-disabled-deploy-20261122'
SHA="${SANRO_STUDIO_EXPECTED_SHA:-}"
MODE="${SANRO_STUDIO_STAGE_DEPLOY_MODE:-plan}"
APPROVAL="${SANRO_STUDIO_APPROVE_PR38_STAGE_APPLY:-}"
STAGE_HOST='saddlebrown-peafowl-616656.hostingersite.com'
WP_ROOT="/home/u606139982/domains/$STAGE_HOST/public_html"
PLUGIN="$WP_ROOT/wp-content/plugins/sanro-studio"
MAIN="$PLUGIN/sanro-studio.php"
MODULE="$PLUGIN/includes/dtf-admin-readiness.php"

# The two digests are evidenced by approved PR34 PLAN on Hostinger 20:12 WIB.
BASE_SHA='5dfb26a784882356dac82b19d6d4f5883cecbce473d74ae2d7da4daaecacdf35'
PREVIEW_SHA='fb9d36d5c196e0681799f214259075eaf2f6ea3e3f6076cc8ee935204d3fb25d'

if [[ "$(hostname -s)" != 'id-dci-web1631'
    || "$(id -un)" != 'u606139982'
    || "$EUID" -eq 0
    || "${SANRO_STUDIO_QA_ENV:-}" != 'staging'
    || ! "$SHA" =~ ^[0-9a-f]{40}$
    || ( "$MODE" != 'plan' && "$MODE" != 'apply' ) ]]; then
    echo 'BLOCKED: exact Hostinger staging owner, env, SHA and mode required' >&2
    exit 2
fi
if [[ "$MODE" == 'apply' && "$APPROVAL" != 'PR38_STAGING_READINESS_OFF_APPROVED' ]]; then
    echo 'BLOCKED: no explicit PR38 staging-only deployment approval' >&2
    exit 2
fi
for bin in php git realpath sha256sum grep mktemp install mv cmp chmod flock stat cut; do
    command -v "$bin" >/dev/null || {
        echo "BLOCKED: prerequisite missing ($bin)" >&2
        exit 2
    }
done
for dir in "$WP_ROOT" "$WP_ROOT/wp-content" "$WP_ROOT/wp-content/plugins" \
           "$PLUGIN" "$PLUGIN/includes"; do
    if [[ ! -d "$dir" || -L "$dir" || "$(realpath "$dir")" != "$dir" ]]; then
        echo 'BLOCKED: staging directory symlink or path drift' >&2
        exit 2
    fi
done
if [[ ! -f "$MAIN" || -L "$MAIN" || -e "$MODULE" || -L "$MODULE"
    || "$(sha256sum "$MAIN" | cut -d' ' -f1)" != "$BASE_SHA" ]]; then
    echo 'BLOCKED: live bootstrap differs from approved PLAN or module already exists' >&2
    exit 2
fi

# Cross-process lock outside website; prevents overlapping deploy attempts.
STATE="$HOME/.local/state/sanro-studio-staging-deploy"
mkdir -p -m 700 "$STATE"
if [[ -L "$STATE" || ! -d "$STATE"
    || ( "$(stat -c '%a' "$STATE")" != '700' ) ]]; then
    echo 'BLOCKED: unsafe staging installer state directory' >&2; exit 2
fi
exec 9>"$STATE/pr38.lock"
flock -n 9 || { echo 'BLOCKED: another staging apply/plan owns the lock' >&2; exit 75; }

QA="$(mktemp -d "$HOME/.sanro-pr38-stage.XXXXXXXX")"
MODULE_TMP=''
MAIN_TMP=''
MODULE_ADDED=0
MAIN_CHANGED=0
SUCCEEDED=0
ROLLBACK=''
on_exit() {
    local rc=$?
    trap - EXIT
    if [[ "$MAIN_CHANGED" -eq 1 && "$SUCCEEDED" -eq 0 && -f "$ROLLBACK" ]]; then
        local rescue
        rescue="$(mktemp -p "$PLUGIN" .sanro-pr38-rollback.XXXXXXXX.tmp)" || true
        if [[ -n "${rescue:-}" ]]; then
            if install -m 0600 "$ROLLBACK" "$rescue" && mv -f "$rescue" "$MAIN"; then
                chmod 0644 "$MAIN" || true
                echo 'ROLLBACK_BOOTSTRAP: RESTORED'
            else
                echo 'ROLLBACK_BOOTSTRAP: FAILED_MANUAL_INTERVENTION_REQUIRED' >&2
            fi
        fi
    fi
    if [[ "$MODULE_ADDED" -eq 1 && "$SUCCEEDED" -eq 0 ]]; then
        rm -f -- "$MODULE" || true
    fi
    [[ -z "$MAIN_TMP" ]] || rm -f -- "$MAIN_TMP" || true
    [[ -z "$MODULE_TMP" ]] || rm -f -- "$MODULE_TMP" || true
    [[ ! -d "${QA:-}" ]] || rm -rf -- "$QA"
    return "$rc"
}
trap on_exit EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

GIT_TERMINAL_PROMPT=0 git clone -q --depth 1 --single-branch \
    --branch "$BRANCH" \
    https://github.com/arioguswara-oss/Plugin-Kaoscetak.git "$QA/repo"
SOURCE="$QA/repo"
HEAD_SHA="$(git -C "$SOURCE" rev-parse HEAD)"
if [[ "$HEAD_SHA" != "$SHA" ]]; then
    echo 'BLOCKED: Git branch HEAD differs from pinned commit' >&2; exit 2
fi

for f in \
    "$SOURCE/plugins/sanro-studio/includes/dtf-admin-readiness.php" \
    "$SOURCE/ops/hostinger/sanro-studio-pr37-readiness-bootstrap-plan.php" \
    "$SOURCE/ops/hostinger/sanro-studio-pr38-runtime-probe.php"; do
    php -l "$f" >/dev/null
done

# Read real site state. No consumer orders/customer data are inspected.
SANRO_STUDIO_QA_ENV=staging SANRO_STUDIO_PR38_PROBE_PHASE=before \
    php -d display_errors=0 \
    "$SOURCE/ops/hostinger/sanro-studio-pr38-runtime-probe.php" \
    > "$QA/before.log" 2> "$QA/preflight-error.log" || {
    echo 'BLOCKED: WordPress staging live preflight failed; no changes' >&2
    exit 2
}
for requirement in \
    'PROBE_STATUS: PASS' \
    'SANRO_STAGING_RUNTIME: VERIFIED' \
    'WOOCOMMERCE_HPOS: ENABLED' \
    'LEGACY_ITEM_PREVIEW_HOOK: REGISTERED' \
    'DTF_READONLY_ADMIN_PANEL: REGISTERED' \
    'READINESS_HOOK: ABSENT' \
    'READINESS_ENABLED: NO'; do
    if ! grep -Fqx "$requirement" "$QA/before.log"; then
        echo "BLOCKED: staging runtime probe line missing ($requirement)" >&2
        exit 2
    fi
done

php "$SOURCE/ops/hostinger/sanro-studio-pr37-readiness-bootstrap-plan.php" \
    "$MAIN" "$QA/main-ready.php" > "$QA/patch.log"
php -l "$QA/main-ready.php" >/dev/null
if [[ "$(sha256sum "$QA/main-ready.php" | cut -d' ' -f1)" != "$PREVIEW_SHA" ]]; then
    echo 'BLOCKED: new bootstrap digest differs from verified PLAN' >&2; exit 2
fi
if [[ "$(sha256sum "$MAIN" | cut -d' ' -f1)" != "$BASE_SHA" || -e "$MODULE" ]]; then
    echo 'BLOCKED: live files drifted since preflight' >&2; exit 2
fi

printf 'PR: #35\nHEAD: %s\nTARGET: HOSTINGER_SANRO_STAGING_ONLY\nMODE: %s\n' "$HEAD_SHA" "$MODE"
echo 'PREVIOUS_BOOTSTRAP_SHA256: '"$BASE_SHA"
echo 'PATCHED_BOOTSTRAP_SHA256: '"$PREVIEW_SHA"
echo 'MODULE_dtf-admin-readiness.php: ADD'
echo 'READINESS_FLAG: OFF'
echo 'DTF_PRIVATE_ARTIFACTS: OFF'
echo 'DTF_ORDER_BATCH: OFF'
if [[ "$MODE" == 'plan' ]]; then
    echo 'WORDPRESS_FILES_MODIFIED: NO'
    echo 'WOOCOMMERCE_ORDERS_MODIFIED: NO'
    echo 'PRODUCTION_MODIFIED: NO'
    echo 'STATUS: READY_FOR_EXPLICIT_PR38_STAGING_APPROVAL'
    exit 0
fi

# Approval required by the separate APPLY guard. Snapshot only the exact
# main bootstrap that will be altered, for local rollback if postflight fails.
ROLLBACK="$STATE/pr38-${BASE_SHA}.php"
if [[ ! -e "$ROLLBACK" ]]; then
    install -m 0600 "$MAIN" "$ROLLBACK"
fi
if [[ ! -f "$ROLLBACK" || -L "$ROLLBACK"
    || "$(sha256sum "$ROLLBACK" | cut -d' ' -f1)" != "$BASE_SHA" ]]; then
    echo 'BLOCKED: rollback original main not intact' >&2; exit 2
fi

# Stage file writes with 0600 permissions; only rename into place after both
# are linted and compared. Module must be installed before bootstrap require.
MODULE_TMP="$(mktemp -p "$PLUGIN/includes" .sanro-pr38-module.XXXXXXXX.tmp)"
MAIN_TMP="$(mktemp -p "$PLUGIN" .sanro-pr38-main.XXXXXXXX.tmp)"
install -m 0600 "$SOURCE/plugins/sanro-studio/includes/dtf-admin-readiness.php" "$MODULE_TMP"
install -m 0600 "$QA/main-ready.php" "$MAIN_TMP"
cmp -s "$MODULE_TMP" "$SOURCE/plugins/sanro-studio/includes/dtf-admin-readiness.php"
cmp -s "$MAIN_TMP" "$QA/main-ready.php"
if [[ -e "$MODULE" || -L "$MODULE"
    || "$(sha256sum "$MAIN" | cut -d' ' -f1)" != "$BASE_SHA" ]]; then
    echo 'BLOCKED: staging files changed concurrently' >&2; exit 2
fi
mv -n "$MODULE_TMP" "$MODULE"
if [[ -e "$MODULE_TMP" || ! -f "$MODULE" ]]; then
    echo 'FAIL: module atomic rename did not take effect' >&2; exit 1
fi
MODULE_ADDED=1
MODULE_TMP=''
if ! cmp -s "$MODULE" "$SOURCE/plugins/sanro-studio/includes/dtf-admin-readiness.php"; then
    echo 'FAIL: installed module differs from source; rolling back' >&2; exit 1
fi
chmod 0644 "$MODULE"
mv -f "$MAIN_TMP" "$MAIN"
MAIN_TMP=''
MAIN_CHANGED=1
chmod 0644 "$MAIN"

SANRO_STUDIO_QA_ENV=staging SANRO_STUDIO_PR38_PROBE_PHASE=after \
    php -d display_errors=0 \
    "$SOURCE/ops/hostinger/sanro-studio-pr38-runtime-probe.php" \
    > "$QA/after.log" 2> "$QA/after-error.log" || {
    echo 'FAIL: staging postflight failed; rolling back' >&2
    exit 1
}
for requirement in \
    'PROBE_STATUS: PASS' \
    'READINESS_HOOK: REGISTERED' \
    'READINESS_ENABLED: NO' \
    'GENERATOR_ENABLED: NO' \
    'DTF_RASTER_PRIVATE_BATCH: OFF' \
    'DTF_PRIVATE_ARTIFACTS: OFF' \
    'DTF_ORDER_BATCH: OFF' \
    'DRIVE_WRITERS: OFF' \
    'LEGACY_PREVIEW_STATUS_GENERATOR_HOOKS: REGISTERED' \
    'LEGACY_ITEM_PREVIEW_HOOK: REGISTERED' \
    'DTF_READONLY_ADMIN_PANEL: REGISTERED'; do
    if ! grep -Fqx "$requirement" "$QA/after.log"; then
        echo 'FAIL: postflight security gate failed; rolling back' >&2
        exit 1
    fi
done
if [[ "$(sha256sum "$MAIN" | cut -d' ' -f1)" != "$PREVIEW_SHA" ]]; then
    echo 'FAIL: live bootstrap hash changed after apply' >&2
    exit 1
fi
SUCCEEDED=1
echo 'STAGING_READINESS_HOOK: REGISTERED'
echo 'STAGING_READINESS_ENABLED: NO'
echo 'DTF_PRIVATE_ARTIFACTS: OFF'
echo 'DTF_ORDER_BATCH: OFF'
echo 'WOOCOMMERCE_ORDERS_MODIFIED: NO'
echo 'PRODUCTION_MODIFIED: NO'
echo 'STATUS: DEPLOYED_STAGING_ONLY_READINESS_DISABLED'
