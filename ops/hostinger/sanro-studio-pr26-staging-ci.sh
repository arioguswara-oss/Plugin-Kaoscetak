#!/usr/bin/env bash
# Sprint 137: real GD/FreeType two-sided DTF E2E in Hostinger STAGING only.
# Clone to a temporary PRIVATE non-webroot checkout, run synthetic QA and
# delete that temporary checkout. No WP installation, hooks, DB, Drive or deploy.
set -Eeuo pipefail
umask 077

BRANCH='manual/sanro-studio-dtf-order-workflow-20261121'
REPO='https://github.com/arioguswara-oss/Plugin-Kaoscetak.git'
HOST='id-dci-web1631'
if [[ "$(hostname -s)" != "$HOST" || "${SANRO_STUDIO_QA_ENV:-}" != 'staging' ]]; then
    printf 'BLOCKED: must run in authorized Hostinger SANRO Studio staging shell\n' >&2
    exit 2
fi
if [[ "$EUID" -eq 0 ]]; then
    printf 'BLOCKED: run with staging unprivileged user, not root\n' >&2
    exit 2
fi
for bin in git php mktemp find; do
    command -v "$bin" >/dev/null 2>&1 || {
        printf 'BLOCKED: prerequisite %s not installed\n' "$bin" >&2
        exit 2
    }
done
SHA="${SANRO_STUDIO_EXPECTED_SHA:-}"
if [[ ! "$SHA" =~ ^[0-9a-f]{40}$ ]]; then
    printf 'BLOCKED: exact SANRO_STUDIO_EXPECTED_SHA=40-hex commit required\n' >&2
    exit 2
fi
if ! php -r 'exit(extension_loaded("gd") && function_exists("imagettftext") && function_exists("imagepng") ? 0 : 2);'; then
    printf 'BLOCKED: staging CLI requires existing GD + FreeType\n' >&2
    exit 2
fi
FONT="${SANRO_STUDIO_TEST_FONT:-}"
if [[ -z "$FONT" ]]; then
    FONT="$(find /usr/share/fonts /usr/local/share/fonts -type f -iname '*.ttf' -print -quit 2>/dev/null || true)"
fi
if [[ -z "$FONT" || ! -f "$FONT" || ! -r "$FONT" || -L "$FONT" ]]; then
    printf 'BLOCKED: no trusted readable staging TTF; do not upload font files via chat\n' >&2
    exit 2
fi
qa_dir="$(mktemp -d "$HOME/.sanro-studio-pr26.XXXXXXXX")"
cleanup() {
    if [[ -n "${qa_dir:-}" && -d "$qa_dir" && "$qa_dir" == "$HOME/.sanro-studio-pr26."* ]]; then
        rm -rf -- "$qa_dir"
    fi
}
trap cleanup EXIT
if [[ -e "$qa_dir/wp-config.php" || "$qa_dir" == */public_html/* ]]; then
    printf 'BLOCKED: QA cannot run inside a WordPress document root\n' >&2
    exit 2
fi
GIT_TERMINAL_PROMPT=0 git clone --depth 1 --single-branch \
    --branch "$BRANCH" "$REPO" "$qa_dir/repo" >/dev/null
head="$(git -C "$qa_dir/repo" rev-parse HEAD)"
if [[ "$head" != "$SHA" ]]; then
    printf 'FAIL: GitHub SHA differs from expected; expected=%s got=%s\n' "$SHA" "$head" >&2
    exit 1
fi
printf 'QA_HEAD: %s\nPHP_VERSION: %s\n' "$head" "$(php -r 'echo PHP_VERSION;')"
printf 'GD_REAL: AVAILABLE\n'
if ! php -l "$qa_dir/repo/plugins/sanro-studio/tests/dtf-order-workflow-real-smoke.php"; then
    exit 1
fi
set +e
SANRO_STUDIO_TEST_FONT="$FONT" php -d memory_limit=256M \
    "$qa_dir/repo/plugins/sanro-studio/tests/dtf-order-workflow-real-smoke.php" \
    > "$qa_dir/real-gd-e2e.log" 2>&1
status=$?
set -e
cat "$qa_dir/real-gd-e2e.log"
if [[ "$status" -ne 0 ]] \
    || ! grep -Fqx 'PASS: 15 SANRO real GD two-sided DTF order lifecycle checks' "$qa_dir/real-gd-e2e.log" \
    || ! grep -Fq 'DTF_ORDER_REAL: PASS (front mixed image/text + back text-only;' "$qa_dir/real-gd-e2e.log"; then
    printf 'FAIL: real two-sided DTF QA (exit=%s)\n' "$status" >&2
    exit 1
fi
printf 'PR: #26\nHEAD: %s\nSTATUS: PASS\nTEST_EXIT: 0\nREAL_DTF_ORDER_ASSERTIONS: 15 PASS, 0 FAIL\n' "$head"
