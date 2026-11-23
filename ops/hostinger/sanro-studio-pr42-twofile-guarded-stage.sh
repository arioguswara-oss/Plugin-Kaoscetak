#!/usr/bin/env bash
# SANRO Studio Sprint 152 / PR42 — two-file staging-only DTF safety patch.
# Default: PLAN. APPLY: separately approved. Never changes WooCommerce orders,
# payments, wp-config, plugin bootstrap, production, or DTF/Drive flags.
set -Eeuo pipefail
umask 077

BRANCH='manual/sanro-studio-pr42-twofile-guarded-staging-20261122'
BASE_BRANCH='manual/sanro-studio-dtf-admin-staging-targeted-20261121'
BASE_SHA='31deb5e8e65a212e1e883b745187db1b685236e1'
SHA="${SANRO_STUDIO_EXPECTED_SHA:-}"
MODE="${SANRO_STUDIO_STAGE_DEPLOY_MODE:-plan}"
APPROVAL="${SANRO_STUDIO_APPROVE_PR42_STAGE_APPLY:-}"
HOST='saddlebrown-peafowl-616656.hostingersite.com'
ROOT="/home/u606139982/domains/$HOST/public_html"
PLUGIN="$ROOT/wp-content/plugins/sanro-studio"
INCLUDES="$PLUGIN/includes"
MAIN="$PLUGIN/sanro-studio.php"

if [[ "$(hostname -s)" != 'id-dci-web1631'
    || "$(id -un)" != 'u606139982'
    || "$EUID" -eq 0
    || "${SANRO_STUDIO_QA_ENV:-}" != 'staging'
    || ! "$SHA" =~ ^[a-f0-9]{40}$
    || ( "$MODE" != 'plan' && "$MODE" != 'apply' ) ]]; then
    echo 'BLOCKED: exact Hostinger staging owner, SHA, env and mode required' >&2
    exit 2
fi
if [[ "$MODE" == 'apply' && "$APPROVAL" != 'PR42_STAGING_TWOFILE_OFF_APPROVED' ]]; then
    echo 'BLOCKED: PR42 requires separate explicit Owner staging APPLY approval' >&2
    exit 2
fi
for bin in git php realpath sha256sum mktemp install mv cmp flock stat grep; do
    command -v "$bin" >/dev/null 2>&1 || {
        echo "BLOCKED: missing dependency $bin" >&2
        exit 2
    }
done
for dir in "$ROOT" "$ROOT/wp-content" "$ROOT/wp-content/plugins" "$PLUGIN" "$INCLUDES"; do
    if [[ ! -d "$dir" || -L "$dir" || "$(realpath "$dir")" != "$dir" ]]; then
        echo 'BLOCKED: staging path missing, symlinked or redirected' >&2
        exit 2
    fi
done
for file in \
    "$MAIN" \
    "$INCLUDES/dtf-image-render.php" \
    "$INCLUDES/dtf-layer-render.php" \
    "$INCLUDES/dtf-admin-order-status.php" \
    "$INCLUDES/dtf-admin-generate.php" \
    "$INCLUDES/dtf-admin-readiness.php" \
    "$INCLUDES/dtf-private-artifacts.php" \
    "$INCLUDES/dtf-order-workflow.php"; do
    if [[ ! -f "$file" || -L "$file" ]]; then
        echo 'BLOCKED: expected live staging module absent or unsafe' >&2
        exit 2
    fi
done

STATE="$HOME/.local/state/sanro-studio-staging-deploy"
mkdir -p -m 700 "$STATE"
if [[ ! -d "$STATE" || -L "$STATE" || "$(stat -c '%a' "$STATE")" != '700' ]]; then
    echo 'BLOCKED: unsafe private staging state directory' >&2
    exit 2
fi
exec 9>"$STATE/pr42.lock"
flock -n 9 || { echo 'BLOCKED: concurrent PR42 staging PLAN/APPLY' >&2; exit 75; }

QA="$(mktemp -d "$HOME/.sanro-pr42-twofile.XXXXXXXX")"
ROLLBACK=''
private_tmp=''
workflow_tmp=''
WRITE_STARTED=0
SUCCESS=0
on_exit() {
    local rc=$?
    trap - EXIT
    if [[ "$WRITE_STARTED" -eq 1 && "$SUCCESS" -ne 1 ]]; then
        local restore_ok=1 tmp
        for f in dtf-private-artifacts.php dtf-order-workflow.php; do
            if [[ ! -f "$ROLLBACK/$f" || -L "$ROLLBACK/$f" ]] \
                || ! cmp -s "$ROLLBACK/$f" "$QA/old-$f"; then
                restore_ok=0
                continue
            fi
            tmp="$(mktemp -p "$INCLUDES" ".sanro-pr42-restore.XXXXXXXX.tmp")" || {
                restore_ok=0
                continue
            }
            if ! install -m 0644 "$ROLLBACK/$f" "$tmp" \
                || ! mv -f -- "$tmp" "$INCLUDES/$f" \
                || ! cmp -s "$ROLLBACK/$f" "$INCLUDES/$f"; then
                restore_ok=0
            fi
            [[ ! -e "$tmp" ]] || rm -f -- "$tmp" || true
        done
        if [[ "$restore_ok" -eq 1 ]]; then
            echo 'ROLLBACK_TWO_FILE: RESTORED_VERIFIED'
        else
            echo "ROLLBACK_TWO_FILE: FAILED_MANUAL_INTERVENTION_REQUIRED ($ROLLBACK)" >&2
        fi
    fi
    [[ -z "$private_tmp" || ! -e "$private_tmp" ]] || rm -f -- "$private_tmp" || true
    [[ -z "$workflow_tmp" || ! -e "$workflow_tmp" ]] || rm -f -- "$workflow_tmp" || true
    [[ ! -d "${QA:-}" ]] || rm -rf -- "$QA"
    return "$rc"
}
trap on_exit EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

 # Full-history clone: avoid Git .git/shallow metadata races observed on Hostinger.
GIT_TERMINAL_PROMPT=0 git -c maintenance.auto=false -c gc.auto=0 clone -q --single-branch \
    --branch "$BRANCH" \
    https://github.com/arioguswara-oss/Plugin-Kaoscetak.git "$QA/repo"
SOURCE="$QA/repo"
HEAD="$(git -C "$SOURCE" rev-parse HEAD)"
if [[ "$HEAD" != "$SHA" ]]; then
    echo 'BLOCKED: PR42 HEAD has drifted from pinned commit' >&2
    exit 2
fi
GIT_TERMINAL_PROMPT=0 git -c maintenance.auto=false -c gc.auto=0 -C "$SOURCE" fetch -q --no-tags origin \
    "+refs/heads/$BASE_BRANCH:refs/remotes/origin/$BASE_BRANCH"
if [[ "$(git -C "$SOURCE" rev-parse "refs/remotes/origin/$BASE_BRANCH^{commit}")" != "$BASE_SHA" ]]; then
    echo 'BLOCKED: historical PR31 baseline branch moved' >&2
    exit 2
fi

# Verify unchanged dependencies are precisely identical to the installed PR31 source.
for f in dtf-image-render.php dtf-layer-render.php dtf-admin-order-status.php; do
    git -C "$SOURCE" show \
        "refs/remotes/origin/$BASE_BRANCH:plugins/sanro-studio/includes/$f" \
        > "$QA/old-$f"
    if ! cmp -s "$QA/old-$f" "$INCLUDES/$f" \
        || ! cmp -s "$QA/old-$f" "$SOURCE/plugins/sanro-studio/includes/$f"; then
        echo "BLOCKED: unchanged dependency drifted: $f" >&2
        exit 2
    fi
done

# Never overwrite a file previously updated by another job or manually modified.
for f in dtf-private-artifacts.php dtf-order-workflow.php; do
    git -C "$SOURCE" show \
        "refs/remotes/origin/$BASE_BRANCH:plugins/sanro-studio/includes/$f" \
        > "$QA/old-$f"
    if ! cmp -s "$QA/old-$f" "$INCLUDES/$f"; then
        echo "BLOCKED: current staging file differs from approved PR31 baseline: $f" >&2
        exit 2
    fi
    php -l "$SOURCE/plugins/sanro-studio/includes/$f" >/dev/null
done

php -l "$SOURCE/ops/hostinger/sanro-studio-pr38-runtime-probe.php" >/dev/null
before_main="$(sha256sum "$MAIN")"
before_private="$(sha256sum "$INCLUDES/dtf-private-artifacts.php")"
before_workflow="$(sha256sum "$INCLUDES/dtf-order-workflow.php")"

probe() {
    local phase="$1" dest="$2" required
    SANRO_STUDIO_QA_ENV=staging SANRO_STUDIO_PR38_PROBE_PHASE=after \
        php -d display_errors=0 \
        "$SOURCE/ops/hostinger/sanro-studio-pr38-runtime-probe.php" \
        > "$dest" 2>"$QA/$phase.err" || {
            echo "BLOCKED: real WP/HPOS $phase probe failed" >&2
            return 1
        }
    for required in \
        'PROBE_STATUS: PASS' \
        'PR38_WP_RUNTIME: VERIFIED' \
        'HPOS_RUNTIME: ENABLED' \
        'LEGACY_PREVIEW_STATUS_GENERATOR_HOOKS: REGISTERED' \
        'READINESS_HOOK: REGISTERED' \
        'READINESS_ENABLED: NO' \
        'GENERATOR_ENABLED: NO' \
        'DTF_RASTER_PRIVATE_BATCH: OFF' \
        'DRIVE_WRITERS: OFF'; do
        if ! grep -Fqx "$required" "$dest"; then
            echo "BLOCKED: WP/HPOS $phase requirement missing: $required" >&2
            return 1
        fi
    done
}
probe before "$QA/before.log"
if [[ "$before_main" != "$(sha256sum "$MAIN")" \
    || "$before_private" != "$(sha256sum "$INCLUDES/dtf-private-artifacts.php")" \
    || "$before_workflow" != "$(sha256sum "$INCLUDES/dtf-order-workflow.php")" ]]; then
    echo 'BLOCKED: WordPress staging files changed during preflight' >&2
    exit 2
fi

printf 'PR: #42\nHEAD: %s\nTARGET: HOSTINGER_SANRO_STAGING_ONLY\nMODE: %s\n' "$HEAD" "$MODE"
echo 'SOURCE_FILES: dtf-private-artifacts.php dtf-order-workflow.php'
echo 'EXISTING_ADMIN_HOOKS: REGISTERED'
echo 'HPOS_RUNTIME: ENABLED'
echo 'GENERATOR_ENABLED: NO'
echo 'READINESS_ENABLED: NO'
echo 'DTF_RASTER_PRIVATE_BATCH: OFF'
echo 'DRIVE_WRITERS: OFF'
if [[ "$MODE" == 'plan' ]]; then
    echo 'WORDPRESS_FILES_MODIFIED: NO'
    echo 'WOOCOMMERCE_ORDERS_MODIFIED: NO'
    echo 'PRODUCTION_MODIFIED: NO'
    echo 'STATUS: READY_FOR_SEPARATE_PR42_STAGING_APPROVAL'
    exit 0
fi

# Scope rollback to only the two changed PHP files; never clone the entire site.
ROLLBACK="$(mktemp -d "$STATE/pr42-rollback.XXXXXXXX")"
chmod 700 "$ROLLBACK"
for f in dtf-private-artifacts.php dtf-order-workflow.php; do
    install -m 0600 "$INCLUDES/$f" "$ROLLBACK/$f"
    cmp -s "$ROLLBACK/$f" "$QA/old-$f" || {
        echo "BLOCKED: rollback source verification failed for $f" >&2
        exit 2
    }
done
private_tmp="$(mktemp -p "$INCLUDES" .sanro-pr42-private.XXXXXXXX.tmp)"
workflow_tmp="$(mktemp -p "$INCLUDES" .sanro-pr42-workflow.XXXXXXXX.tmp)"
install -m 0644 "$SOURCE/plugins/sanro-studio/includes/dtf-private-artifacts.php" "$private_tmp"
install -m 0644 "$SOURCE/plugins/sanro-studio/includes/dtf-order-workflow.php" "$workflow_tmp"
cmp -s "$private_tmp" "$SOURCE/plugins/sanro-studio/includes/dtf-private-artifacts.php"
cmp -s "$workflow_tmp" "$SOURCE/plugins/sanro-studio/includes/dtf-order-workflow.php"
php -l "$private_tmp" >/dev/null
php -l "$workflow_tmp" >/dev/null
if [[ "$before_main" != "$(sha256sum "$MAIN")" \
    || "$before_private" != "$(sha256sum "$INCLUDES/dtf-private-artifacts.php")" \
    || "$before_workflow" != "$(sha256sum "$INCLUDES/dtf-order-workflow.php")" ]]; then
    echo 'BLOCKED: staging files changed after QA, before two-file commit' >&2
    exit 2
fi

# With generator/raster/private/batch flags OFF, switch only two file paths.
# This is not an atomic two-file filesystem transaction: rollback on failure.
WRITE_STARTED=1
mv -f -- "$private_tmp" "$INCLUDES/dtf-private-artifacts.php"
private_tmp=''
mv -f -- "$workflow_tmp" "$INCLUDES/dtf-order-workflow.php"
workflow_tmp=''
if ! cmp -s "$INCLUDES/dtf-private-artifacts.php" \
       "$SOURCE/plugins/sanro-studio/includes/dtf-private-artifacts.php" \
    || ! cmp -s "$INCLUDES/dtf-order-workflow.php" \
       "$SOURCE/plugins/sanro-studio/includes/dtf-order-workflow.php" \
    || [[ "$before_main" != "$(sha256sum "$MAIN")" ]]; then
    echo 'FAIL: two-file runtime hashes differ after staging commit' >&2
    exit 1
fi
if ! probe after "$QA/after.log"; then
    echo 'FAIL: staging postflight did not preserve OFF flags/hooks' >&2
    exit 1
fi
SUCCESS=1
echo 'STAGING_TWOFILE: DEPLOYED_VERIFIED'
echo 'GENERATOR_ENABLED: NO'
echo 'READINESS_ENABLED: NO'
echo 'DTF_RASTER_PRIVATE_BATCH: OFF'
echo 'DRIVE_WRITERS: OFF'
echo 'WOOCOMMERCE_ORDERS_MODIFIED: NO'
echo 'PRODUCTION_MODIFIED: NO'
echo 'STATUS: DEPLOYED_STAGING_ONLY_DTF_TWOFILE_DISABLED'
