#!/usr/bin/env bash
# SANRO Studio: hermetic, staging-safe CI runner for PR #15.
# Execute on Hermes in a checkout of arioguswara-oss/Plugin-Kaoscetak.
# It tests a detached temporary worktree and NEVER deploys or merges.
set -Eeuo pipefail
umask 077

BRANCH='fix/sanro-studio-editor-config-validation-20261119'
REPO_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
if ! git -C "$REPO_ROOT" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  printf 'FAIL: run from the SANRO Studio Git checkout, not a loose script\n' >&2
  exit 2
fi
ORIGIN="$(git -C "$REPO_ROOT" remote get-url origin)"
case "$ORIGIN" in
  https://github.com/arioguswara-oss/Plugin-Kaoscetak|\
  https://github.com/arioguswara-oss/Plugin-Kaoscetak.git|\
  git@github.com:arioguswara-oss/Plugin-Kaoscetak|\
  git@github.com:arioguswara-oss/Plugin-Kaoscetak.git|\
  ssh://git@github.com/arioguswara-oss/Plugin-Kaoscetak.git) ;;
  *) printf 'FAIL: unexpected origin repository; refusing to run\n' >&2; exit 2 ;;
esac

# Update only remote-tracking ref, never a working branch.
GIT_TERMINAL_PROMPT=0 git -C "$REPO_ROOT" fetch --no-tags origin \
  "refs/heads/$BRANCH:refs/remotes/origin/$BRANCH"
SOURCE_SHA="$(git -C "$REPO_ROOT" rev-parse "refs/remotes/origin/$BRANCH^{commit}")"
if [[ -n "${SANRO_EXPECT_SHA:-}" && "$SOURCE_SHA" != "$SANRO_EXPECT_SHA" ]]; then
  printf 'FAIL: expected SHA %s; fetched %s\n' "$SANRO_EXPECT_SHA" "$SOURCE_SHA" >&2
  exit 2
fi

TEMP_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/sanro-studio-hermes-qa.XXXXXXXX")"
WORKTREE="$TEMP_ROOT/source"
cleanup() {
  git -C "$REPO_ROOT" worktree remove --force "$WORKTREE" >/dev/null 2>&1 || true
  rm -rf -- "$TEMP_ROOT"
}
trap cleanup EXIT

git -C "$REPO_ROOT" worktree add --detach "$WORKTREE" "$SOURCE_SHA" >/dev/null
LOG_DIR="${SANRO_CI_LOG_DIR:-$HOME/sanro-studio-qa-evidence}"
mkdir -p -- "$LOG_DIR"
LOG_FILE="$LOG_DIR/pr15-${SOURCE_SHA:0:12}-$(date -u +%Y%m%dT%H%M%SZ).log"

printf 'SANRO Studio PR #15 Hermes CI\n'
printf 'SHA: %s\nEvidence: %s\n' "$SOURCE_SHA" "$LOG_FILE"
printf 'Mode: no deployment, no merge, no payment, no live DB changes\n'

if (
  cd "$WORKTREE" || exit 2
  printf 'SOURCE_SHA=%s\n' "$(git rev-parse HEAD)"
  php -l plugins/sanro-studio/includes/cart-adapter.php || exit $?
  php plugins/sanro-studio/tests/cart-adapter-smoke.php || exit $?
  bash scripts/sanro-studio-local-ci.sh || exit $?
) 2>&1 | tee "$LOG_FILE"; then
  if grep -Fq 'PASS: 74 staging cart adapter checks' "$LOG_FILE" \
    && grep -Eq '^SUMMARY: [1-9][0-9]* checks PASS, 0 checks FAIL$' "$LOG_FILE"; then
    printf 'VERIFIED HERMES CI: PASS; SHA=%s\n' "$SOURCE_SHA"
    exit 0
  fi
  printf 'FAIL: missing required 72-assertion or zero-failure CI evidence\n' >&2
  exit 1
else
  printf 'FAIL: Hermes CI failed; see %s\n' "$LOG_FILE" >&2
  exit 1
fi
