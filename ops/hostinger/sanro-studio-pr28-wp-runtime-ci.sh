#!/usr/bin/env bash
# Sprint 139: detect actual Hostinger WP + Woo runtime, hooks, HPOS and flags.
# No plugin ZIP, no WordPress DB mutations, no merge, no deployment.
set -Eeuo pipefail
umask 077
if [[ "$(hostname -s)" != 'id-dci-web1631'
    || "${SANRO_STUDIO_QA_ENV:-}" != 'staging' ]]; then
    printf 'BLOCKED: only Hostinger SANRO Studio staging allowed\n' >&2
    exit 2
fi
if [[ "$EUID" -eq 0 ]]; then
    printf 'BLOCKED: unprivileged staging user required\n' >&2
    exit 2
fi
for bin in git php mktemp grep; do
    command -v "$bin" >/dev/null 2>&1 || {
        printf 'BLOCKED: %s missing\n' "$bin" >&2; exit 2;
    }
done
expected="${SANRO_STUDIO_EXPECTED_SHA:-}"
if [[ ! "$expected" =~ ^[0-9a-f]{40}$ ]]; then
    printf 'BLOCKED: expected exact branch commit SHA\n' >&2
    exit 2
fi
qa_dir="$(mktemp -d "$HOME/.sanro-studio-pr28.XXXXXXXX")"
cleanup() {
    [[ -z "${qa_dir:-}" || ! -d "$qa_dir" ]] && return 0
    [[ "$qa_dir" == "$HOME/.sanro-studio-pr28."* ]] && rm -rf -- "$qa_dir"
}
trap cleanup EXIT
GIT_TERMINAL_PROMPT=0 git clone --depth 1 --single-branch \
  --branch manual/sanro-studio-wc-runtime-preflight-20261121 \
  https://github.com/arioguswara-oss/Plugin-Kaoscetak.git "$qa_dir/repo" >/dev/null

head="$(git -C "$qa_dir/repo" rev-parse HEAD)"
if [[ "$head" != "$expected" ]]; then
    printf 'FAIL: candidate commit moved; expected %s, got %s\n' "$expected" "$head" >&2
    exit 1
fi
test -f "$qa_dir/repo/ops/hostinger/sanro-studio-pr28-wp-runtime-readonly.php"
php -l "$qa_dir/repo/ops/hostinger/sanro-studio-pr28-wp-runtime-readonly.php"
if ! SANRO_STUDIO_QA_ENV=staging php -d display_errors=0 \
  "$qa_dir/repo/ops/hostinger/sanro-studio-pr28-wp-runtime-readonly.php" \
  > "$qa_dir/preflight.log" 2>&1; then
    cat "$qa_dir/preflight.log"
    printf 'STATUS: BLOCKED\n' >&2
    exit 2
fi
cat "$qa_dir/preflight.log"
grep -Fqx 'STAGING_HOST: saddlebrown-peafowl-616656.hostingersite.com' "$qa_dir/preflight.log"
grep -Fqx 'WP_RUNTIME: AVAILABLE' "$qa_dir/preflight.log"
grep -Fqx 'WOOCOMMERCE_RUNTIME: AVAILABLE' "$qa_dir/preflight.log"
grep -Fqx 'RUNTIME_PREFLIGHT: PASS' "$qa_dir/preflight.log"

# This is an environment check, not proof that the unmerged PR is deployed.
if grep -Fqx 'CURRENT_ADMIN_HOOK: REGISTERED' "$qa_dir/preflight.log" &&
   grep -Fqx 'STAGING_ADMIN_MODULE_MATCHES_CANDIDATE: YES' "$qa_dir/preflight.log"; then
    printf 'PANEL_DEPLOYMENT: SOURCE_PRESENT\n'
else
    printf 'PANEL_DEPLOYMENT: NOT_VERIFIED\n'
fi
printf 'PR: #28\nHEAD: %s\nSTATUS: PASS\nTEST_EXIT: 0\nWP_ADMIN_BROWSER_E2E: NOT_TESTED\n' "$head"
