#!/usr/bin/env bash
# Sprint 140 — one-time staging admin Playwright login/session bootstrap on Hermes.
# OWNER TYPES credentials in SSH terminal. No install, deploy or production access.
set -Eeuo pipefail
umask 077

BRANCH='manual/sanro-studio-hermes-staging-admin-session-20261121'
REPO="${SANRO_STUDIO_REPO:-/home/sanro/Plugin-Kaoscetak}"
EXPECTED="${SANRO_STUDIO_EXPECTED_SHA:-}"
STATE_DIR='/home/sanro/.local/state/sanro-studio-browser-qa'
STATE_FILE="$STATE_DIR/staging-admin.json"

if [[ "$(hostname -s)" != 'vmi3646685'
    || "$(id -un)" != 'sanro'
    || "${SANRO_STUDIO_QA_ENV:-}" != 'staging'
    || "$EUID" -eq 0
    || ! -t 0 || ! -t 1 ]]; then
  echo 'BLOCKED: interactive sanro@Hermes staging terminal required' >&2
  exit 2
fi
if [[ ! "$EXPECTED" =~ ^[0-9a-f]{40}$ ]]; then
  echo 'BLOCKED: exact SHA required' >&2
  exit 2
fi
for tool in git node bash mktemp flock; do
  command -v "$tool" >/dev/null || { echo "BLOCKED: missing $tool" >&2; exit 2; }
done
if [[ ! -d "$REPO" ]] || ! git -C "$REPO" rev-parse --is-inside-work-tree 2>/dev/null | grep -qx true; then
  echo 'BLOCKED: no SANRO Studio Hermes repository' >&2; exit 2
fi
mkdir -p "$STATE_DIR"
chmod 700 "$STATE_DIR"
if [[ -e "$STATE_FILE" || -L "$STATE_FILE" ]]; then
  echo 'BLOCKED: staging login state already exists; never overwrite session' >&2
  exit 2
fi
exec 9>"$STATE_DIR/pr30-session.lock"
flock -n 9 || { echo 'BLOCKED: another staging session capture running' >&2; exit 75; }

# Reuse existing Playwright. NO npm/apt installs.
export NODE_PATH="/home/sanro/sanro-browser-qa/node_modules${NODE_PATH:+:$NODE_PATH}"
node -e "require('playwright')" >/dev/null || {
  echo 'BLOCKED: existing Hermes Playwright package unavailable' >&2
  exit 2
}

git -C "$REPO" fetch --no-tags origin \
  "+refs/heads/$BRANCH:refs/remotes/origin/$BRANCH"
head="$(git -C "$REPO" rev-parse "refs/remotes/origin/$BRANCH^{commit}")"
if [[ "$head" != "$EXPECTED" ]]; then
  echo 'BLOCKED: branch SHA moved; no session captured' >&2; exit 2
fi
workspace="$(mktemp -d "${TMPDIR:-/tmp}/sanro-studio-pr30.XXXXXXXX")"
cleanup(){
  git -C "$REPO" worktree remove --force "$workspace" >/dev/null 2>&1 || true
  [[ ! -d "$workspace" ]] || rmdir "$workspace" 2>/dev/null || true
}
trap cleanup EXIT
git -C "$REPO" worktree add --detach "$workspace" "$head" >/dev/null
node --check "$workspace/tests/lib/staging-session-capture-policy.cjs"
node --check "$workspace/ops/hermes/sanro-studio-pr30-capture-staging-login.cjs"
node "$workspace/tests/staging-session-capture-node.cjs"

echo "PR: #30"
echo "HEAD: $head"
echo 'CHROMIUM_SMOKE: testing existing headless browser'
node - <<'NODE'
(async()=>{
  const {chromium}=require('playwright');
  const browser=await chromium.launch({headless:true});
  try { const p=await browser.newPage(); await p.goto('about:blank'); }
  finally {await browser.close()}
  console.log('CHROMIUM_SMOKE: PASS');
})().catch(_=>{console.error('CHROMIUM_SMOKE: FAIL');process.exitCode=2});
NODE

echo '=== LOGIN KHUSUS WORDPRESS STAGING ==='
echo 'Username dan password diketik LANGSUNG di terminal ini, bukan di ChatGPT.'
echo 'Tidak ada akun atau pembayaran yang dibuat. Hanya satu POST login WP staging.'
SANRO_STUDIO_QA_ENV=staging node \
  "$workspace/ops/hermes/sanro-studio-pr30-capture-staging-login.cjs"
printf 'PR: #30\nHEAD: %s\nSESSION_QA: PASS\n' "$head"
