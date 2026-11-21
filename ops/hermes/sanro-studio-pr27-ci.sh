#!/usr/bin/env bash
# SANRO Studio PR #27 — isolated local CI on Hermes, no GitHub Actions minutes.
# This script never deploys, merges, touches WooCommerce production, or changes
# the VPS checkout branch. It runs only synthetic/offline repository QA.
set -Eeuo pipefail
umask 077

BRANCH='manual/sanro-studio-admin-dtf-status-20261121'
REPO="${SANRO_STUDIO_REPO:-/home/sanro/Plugin-Kaoscetak}"
STATE_DIR="${SANRO_STUDIO_CI_STATE_DIR:-/home/sanro/.local/state/sanro-studio-ci}"
if [[ "${EUID}" -eq 0 ]]; then
    printf 'FAIL: run as non-root sanro user, not root\n' >&2
    exit 2
fi
for bin in git php node bash flock mktemp; do
    if ! command -v "$bin" >/dev/null 2>&1; then
        printf 'FAIL: missing dependency %s\n' "$bin" >&2
        exit 2
    fi
done
if [[ ! -d "$REPO" ]] || ! git -C "$REPO" rev-parse --is-inside-work-tree 2>/dev/null | grep -qx true; then
    printf 'FAIL: local Git checkout missing: %s\n' "$REPO" >&2
    exit 2
fi
mkdir -p "$STATE_DIR"
chmod 700 "$STATE_DIR"
exec 9>"$STATE_DIR/pr27.lock"
if ! flock -n 9; then
    printf 'FAIL: another PR27 CI check is in progress\n' >&2
    exit 75
fi

# Only fetch the dedicated PR head into a remote-tracking ref; never switch
# the local staging or production checkout.
git -C "$REPO" fetch --no-tags origin \
    "+refs/heads/$BRANCH:refs/remotes/origin/$BRANCH"
sha="$(git -C "$REPO" rev-parse "refs/remotes/origin/$BRANCH^{commit}")"
if [[ -n "${SANRO_STUDIO_EXPECTED_SHA:-}" && "$sha" != "$SANRO_STUDIO_EXPECTED_SHA" ]]; then
    printf 'FAIL: expected SHA %s but fetched %s\n' "$SANRO_STUDIO_EXPECTED_SHA" "$sha" >&2
    exit 2
fi
run_id="$(date -u +%Y%m%dT%H%M%SZ)-${sha:0:12}"
log="$STATE_DIR/pr27-$run_id.log"
worktree="$(mktemp -d "${TMPDIR:-/tmp}/sanro-studio-pr27.XXXXXXXX")"
cleanup() {
    git -C "$REPO" worktree remove --force "$worktree" >/dev/null 2>&1 || true
    if [[ -d "$worktree" ]]; then rmdir "$worktree" 2>/dev/null || true; fi
}
trap cleanup EXIT
git -C "$REPO" worktree add --detach "$worktree" "$sha" >/dev/null
if [[ ! -f "$worktree/scripts/sanro-studio-local-ci.sh" ]]; then
    printf 'FAIL: local CI script not present in commit %s\n' "$sha" >&2
    exit 2
fi

printf 'SANRO Studio Hermes CI\nPR: #27\nBranch: %s\nSHA: %s\n' "$BRANCH" "$sha" > "$log"
# Fail fast on the exact renderer test instead of running 100 gates before
# discovering a PHP fatal. Preserve the full diagnostic in the private log.
set +e
(cd "$worktree" && bash scripts/sanro-studio-dtf-targeted-ci.sh) >> "$log" 2>&1
target_exit=$?
set -e
if [[ "$target_exit" -ne 0 ]]; then
    printf 'PR: #27\nHEAD: %s\nTARGETED_STATUS: FAIL\nTARGETED_EXIT: %s\nLOG: %s\n' \
        "$sha" "$target_exit" "$log" >&2
    printf '\nDTF diagnostics:\n' >&2
    tail -n 50 "$log" >&2
    exit 1
fi
printf 'TARGETED_STATUS: PASS\n'
set +e
(cd "$worktree" && bash scripts/sanro-studio-local-ci.sh) >> "$log" 2>&1
test_exit=$?
set -e
summary="$(grep -E '^SUMMARY: [0-9]+ checks PASS, [0-9]+ checks FAIL$' "$log" | tail -n 1 || true)"
status='FAIL'
if [[ "$test_exit" -eq 0 && "$summary" =~ ^SUMMARY:\ ([0-9]+)\ checks\ PASS,\ 0\ checks\ FAIL$ ]]; then
    # Reject a shortened QA script that silently skips the expected gates.
    if [[ "${BASH_REMATCH[1]}" -ge 121 ]]; then
        status='PASS'
    fi
fi
gd_runtime="$(php -r 'echo function_exists("imagecreatefromstring") && function_exists("imageresolution") ? "AVAILABLE" : "UNAVAILABLE";')"
raster_evidence="$(grep '^PHP_GD_RASTER:' "$log" | tail -n 1 || true)"
printf '%s\n' "${raster_evidence:-PHP_GD_RASTER: NOT_TESTED (no raster evidence found)}"
printf 'PR: #27\nHEAD: %s\nSTATUS: %s\nTEST_EXIT: %s\nPHP_GD: %s\n%s\nLOG: %s\n' \
    "$sha" "$status" "$test_exit" "$gd_runtime" "${summary:-SUMMARY: unavailable}" "$log"
if [[ "$status" == 'FAIL' ]]; then
    printf '\nLast 25 log lines:\n' >&2
    tail -n 25 "$log" >&2
    printf '\nAll failed gates:\n' >&2
    grep -n -E '^FAIL:' "$log" >&2 || true
    exit 1
fi
exit 0
