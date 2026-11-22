#!/usr/bin/env bash
# Sprint 151 / PR41: exact Hostinger staging integration PLAN only.
# NEVER deploys, merges, edits WP/HPOS records or enables DTF/Drive.
set -Eeuo pipefail
umask 077

BRANCH='manual/sanro-studio-pr41-twofile-staging-plan-20261122'
BASE_BRANCH='manual/sanro-studio-dtf-admin-staging-targeted-20261121'
BASE_SHA='31deb5e8e65a212e1e883b745187db1b685236e1'
EXPECTED="${SANRO_STUDIO_EXPECTED_SHA:-}"
HOST='saddlebrown-peafowl-616656.hostingersite.com'
ROOT="/home/u606139982/domains/$HOST/public_html"
PLUGIN="$ROOT/wp-content/plugins/sanro-studio"
MAIN="$PLUGIN/sanro-studio.php"

if [[ "$(hostname -s)" != 'id-dci-web1631'
    || "$(id -un)" != 'u606139982'
    || "$EUID" -eq 0
    || "${SANRO_STUDIO_QA_ENV:-}" != 'staging'
    || "${SANRO_STUDIO_STAGE_DEPLOY_MODE:-plan}" != 'plan'
    || ! "$EXPECTED" =~ ^[a-f0-9]{40}$ ]]; then
    echo 'BLOCKED: Hostinger staging owner, exact SHA and PLAN-only mode required' >&2
    exit 2
fi
for bin in git php mktemp realpath cmp sha256sum grep; do
    command -v "$bin" >/dev/null 2>&1 || {
        echo "BLOCKED: missing prerequisite $bin" >&2
        exit 2
    }
done
for dir in "$ROOT" "$ROOT/wp-content" "$ROOT/wp-content/plugins" \
           "$PLUGIN" "$PLUGIN/includes"; do
    if [[ ! -d "$dir" || -L "$dir" || "$(realpath "$dir")" != "$dir" ]]; then
        echo 'BLOCKED: staging directory missing or symlinked' >&2
        exit 2
    fi
done
for f in \
    "$MAIN" \
    "$PLUGIN/includes/dtf-image-render.php" \
    "$PLUGIN/includes/dtf-layer-render.php" \
    "$PLUGIN/includes/dtf-private-artifacts.php" \
    "$PLUGIN/includes/dtf-order-workflow.php" \
    "$PLUGIN/includes/dtf-admin-order-status.php" \
    "$PLUGIN/includes/dtf-admin-generate.php" \
    "$PLUGIN/includes/dtf-admin-readiness.php"; do
    if [[ ! -f "$f" || -L "$f" ]]; then
        echo 'BLOCKED: staging module missing or unsafe' >&2
        exit 2
    fi
done
QA="$(mktemp -d "$HOME/.sanro-pr41-readonly.XXXXXXXX")"
trap 'rm -rf -- "$QA"' EXIT
GIT_TERMINAL_PROMPT=0 git clone -q --depth 1 --single-branch \
    --branch "$BRANCH" \
    https://github.com/arioguswara-oss/Plugin-Kaoscetak.git "$QA/repo"
SOURCE="$QA/repo"
HEAD_SHA="$(git -C "$SOURCE" rev-parse HEAD)"
if [[ "$HEAD_SHA" != "$EXPECTED" ]]; then
    echo 'BLOCKED: PR41 source HEAD differs from pinned SHA' >&2
    exit 2
fi

# Fetch the *exact* PR31 baseline previously applied on staging.
GIT_TERMINAL_PROMPT=0 git -C "$SOURCE" fetch -q --depth 1 --no-tags origin \
    "+refs/heads/$BASE_BRANCH:refs/remotes/origin/$BASE_BRANCH"
got_base="$(git -C "$SOURCE" rev-parse "refs/remotes/origin/$BASE_BRANCH^{commit}")"
if [[ "$got_base" != "$BASE_SHA" ]]; then
    echo 'BLOCKED: PR31 baseline branch has moved' >&2
    exit 2
fi

# Unchanged dependency sources must match both deployed files and PR41.
for f in dtf-image-render.php dtf-layer-render.php dtf-admin-order-status.php; do
    git -C "$SOURCE" show \
        "refs/remotes/origin/$BASE_BRANCH:plugins/sanro-studio/includes/$f" \
        > "$QA/old-$f"
    if ! cmp -s "$PLUGIN/includes/$f" "$QA/old-$f" \
        || ! cmp -s "$SOURCE/plugins/sanro-studio/includes/$f" "$QA/old-$f"; then
        echo "BLOCKED: unchanged DTF module drifted: $f" >&2
        exit 2
    fi
done
for f in dtf-private-artifacts.php dtf-order-workflow.php; do
    new="$SOURCE/plugins/sanro-studio/includes/$f"
    php -l "$new" >/dev/null
    git -C "$SOURCE" show \
        "refs/remotes/origin/$BASE_BRANCH:plugins/sanro-studio/includes/$f" \
        > "$QA/old-$f"
    if ! cmp -s "$PLUGIN/includes/$f" "$QA/old-$f"; then
        echo "BLOCKED: deployed file differs from exact PR31 baseline: $f" >&2
        exit 2
    fi
    if cmp -s "$new" "$QA/old-$f"; then
        echo "BLOCKED: PR41 candidate does not change $f" >&2
        exit 2
    fi
done

php -l "$SOURCE/ops/hostinger/sanro-studio-pr38-runtime-probe.php" >/dev/null
main_before="$(sha256sum "$MAIN")"
private_before="$(sha256sum "$PLUGIN/includes/dtf-private-artifacts.php")"
workflow_before="$(sha256sum "$PLUGIN/includes/dtf-order-workflow.php")"
# Existing PR38 probe bootstraps WordPress in CLI GET mode, disables wp-cron,
# and inspects hooks + HPOS + OFF flags; no order content is retrieved.
SANRO_STUDIO_QA_ENV=staging SANRO_STUDIO_PR38_PROBE_PHASE=after \
    php -d display_errors=0 \
    "$SOURCE/ops/hostinger/sanro-studio-pr38-runtime-probe.php" \
    > "$QA/runtime.log" 2> "$QA/runtime.err" || {
        echo 'BLOCKED: existing staging WordPress/HPOS runtime probe failed' >&2
        exit 2
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
    if ! grep -Fqx "$required" "$QA/runtime.log"; then
        echo "BLOCKED: staging runtime requirement missing: $required" >&2
        exit 2
    fi
done
if [[ "$main_before" != "$(sha256sum "$MAIN")" \
    || "$private_before" != "$(sha256sum "$PLUGIN/includes/dtf-private-artifacts.php")" \
    || "$workflow_before" != "$(sha256sum "$PLUGIN/includes/dtf-order-workflow.php")" ]]; then
    echo 'BLOCKED: staging files changed during PLAN' >&2
    exit 2
fi

printf 'PR: #41\nHEAD: %s\nBASELINE_PR: #31\nBASELINE_SHA: %s\n' \
    "$HEAD_SHA" "$BASE_SHA"
echo 'TARGET: HOSTINGER_SANRO_STAGING_ONLY'
echo 'MODE: plan'
echo 'WP_HPOS: VERIFIED'
echo 'EXISTING_ADMIN_PREVIEW_STATUS_GENERATOR_READINESS: REGISTERED'
echo 'GENERATOR_ENABLED: NO'
echo 'READINESS_ENABLED: NO'
echo 'DTF_RASTER_PRIVATE_BATCH: OFF'
echo 'DRIVE_WRITERS: OFF'
echo 'FILE_dtf-private-artifacts.php: PLAN_REPLACE_ONLY'
echo 'FILE_dtf-order-workflow.php: PLAN_REPLACE_ONLY'
echo 'UNCHANGED_RUNTIME_MODULES: BASELINE_VERIFIED'
echo 'WORDPRESS_FILES_MODIFIED: NO'
echo 'WOOCOMMERCE_ORDERS_MODIFIED: NO'
echo 'PRODUCTION_MODIFIED: NO'
echo 'APPLY_IMPLEMENTED: NO'
echo 'STATUS: READY_FOR_SEPARATE_PR41_STAGING_REVIEW'
