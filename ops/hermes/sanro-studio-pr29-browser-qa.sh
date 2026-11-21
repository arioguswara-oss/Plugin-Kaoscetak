#!/usr/bin/env bash
# SANRO Studio PR29 — existing Hermes Playwright browser on WP STAGING ONLY.
# NO INSTALL, NO MERGE, NO DEPLOY, NO ORDER/PAYMENT POST.
set -Eeuo pipefail
umask 077
BRANCH='manual/sanro-studio-hermes-admin-browser-e2e-20261121'
REPO="${SANRO_STUDIO_REPO:-/home/sanro/Plugin-Kaoscetak}"
STATE_DIR="${SANRO_STUDIO_BROWSER_QA_STATE_DIR:-/home/sanro/.local/state/sanro-studio-browser-qa}"
if [[ "$(id -un)" != "sanro" || "$(hostname -s)" != "vmi3646685" || "$EUID" -eq 0 ]]; then
  echo "BLOCKED: SANRO Hermes VPS sanro user only" >&2
  exit 2
fi
for program in git node bash mktemp flock; do
  command -v "$program" >/dev/null || {
    echo "BLOCKED: prerequisite missing: $program" >&2; exit 2;
  }
done
expected="${SANRO_STUDIO_EXPECTED_SHA:-}"
if [[ ! "$expected" =~ ^[0-9a-f]{40}$ ]]; then
  echo "BLOCKED: SANRO_STUDIO_EXPECTED_SHA must pin a 40-digit PR29 commit" >&2
  exit 2
fi
if [[ ! -d "$REPO/.git" ]] && ! git -C "$REPO" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "BLOCKED: Hermes SANRO repository not found" >&2
  exit 2
fi
mkdir -p "$STATE_DIR"
chmod 700 "$STATE_DIR"
exec 9>"$STATE_DIR/pr29-browser.lock"
if ! flock -n 9; then
  echo "BLOCKED: PR29 browser QA already running" >&2
  exit 75
fi
# Use existing Hermes Playwright installation, never npm install or apt.
if ! node -e "require('playwright')" >/dev/null 2>&1; then
  for modules in "$HOME/sanro-browser-qa/node_modules" "/home/sanro/sanro-browser-qa/node_modules" "$REPO/tests/node_modules"; do
    if [[ -d "$modules/playwright" ]]; then
      export NODE_PATH="$modules${NODE_PATH:+:$NODE_PATH}"
      break
    fi
  done
fi
if ! node -e "require('playwright')" >/dev/null 2>&1; then
  echo "BLOCKED: existing Hermes Playwright package not found" >&2
  exit 2
fi
if [[ -z "${SANRO_CHROMIUM_EXECUTABLE:-}" ]]; then
  for executable in chromium chromium-browser google-chrome; do
    if command -v "$executable" >/dev/null 2>&1; then
      SANRO_CHROMIUM_EXECUTABLE="$(command -v "$executable")"
      export SANRO_CHROMIUM_EXECUTABLE
      break
    fi
  done
fi

git -C "$REPO" fetch --no-tags origin \
  "+refs/heads/$BRANCH:refs/remotes/origin/$BRANCH"
head="$(git -C "$REPO" rev-parse "refs/remotes/origin/$BRANCH^{commit}")"
if [[ "$head" != "$expected" ]]; then
  echo "BLOCKED: PR29 HEAD differs from pinned expected SHA" >&2
  exit 2
fi

workspace="$(mktemp -d "${TMPDIR:-/tmp}/sanro-studio-pr29.XXXXXXXX")"
cleanup(){
  git -C "$REPO" worktree remove --force "$workspace" >/dev/null 2>&1 || true
  [[ ! -d "$workspace" ]] || rmdir "$workspace" 2>/dev/null || true
}
trap cleanup EXIT
git -C "$REPO" worktree add --detach "$workspace" "$head" >/dev/null

printf 'PR: #29\nHEAD: %s\n' "$head"
node --check "$workspace/tests/lib/staging-admin-guards.cjs"
node --check "$workspace/tests/staging-admin-guards-node.cjs"
node --check "$workspace/tests/staging-admin-order-readonly.cjs"
node "$workspace/tests/staging-admin-guards-node.cjs"

if [[ -z "${SANRO_STAGING_ADMIN_STATE:-}" ]]; then
  echo "BROWSER_QA: BLOCKED_MISSING_EXISTING_STAGING_LOGIN_STATE"
  echo "Provide the private owner-only SANRO_STAGING_ADMIN_STATE used by existing Hermes admin QA."
  exit 2
fi

# The dedicated mode in existing QA launcher disallows buyer/cart/write paths.
SANRO_STUDIO_QA_ENV=staging SANRO_STAGING_ORDER_ADMIN_QA=1 \
  bash "$workspace/scripts/sanro-studio-staging-browser-qa.sh"
printf 'BROWSER_RUNNER: PASS\nPR: #29\nHEAD: %s\n' "$head"
