#!/usr/bin/env bash
# Sprint 142: owner-approved, exactly-one Rp0 SANRO Studio QA order
# Hostinger staging HPOS ONLY. Default PLAN, no production, no payment.
set -Eeuo pipefail
umask 077

BRANCH='manual/sanro-studio-hpos-qa-order-20261121'
EXPECTED="${SANRO_STUDIO_EXPECTED_SHA:-}"
MODE="${SANRO_STUDIO_QA_ORDER_MODE:-plan}"
QA_ENV="${SANRO_STUDIO_QA_ENV:-}"
EXPECTED_STAGE='/home/u606139982/domains/saddlebrown-peafowl-616656.hostingersite.com/public_html'

if [[ "$(hostname -s)" != 'id-dci-web1631'
  || "$(id -un)" != 'u606139982'
  || "$EUID" -eq 0
  || "$QA_ENV" != 'staging' ]]; then
  echo 'BLOCKED: Hostinger SANRO Studio staging CLI only' >&2
  exit 2
fi
if [[ "$MODE" != 'plan' && "$MODE" != 'create' ]]; then
  echo 'BLOCKED: use mode plan/create only' >&2
  exit 2
fi
if [[ ! "$EXPECTED" =~ ^[a-f0-9]{40}$ ]]; then
  echo 'BLOCKED: exact GitHub commit SHA required' >&2
  exit 2
fi
if [[ "$MODE" == 'create'
  && "${SANRO_STUDIO_APPROVE_ONE_QA_ORDER:-}" != 'ONE_ZERO_STAGING_ORDER_APPROVED' ]]; then
  echo 'BLOCKED: owner must explicitly approve one Rp0 staging QA order' >&2
  exit 2
fi
for bin in php git mktemp flock grep; do
  command -v "$bin" >/dev/null || { echo "BLOCKED: missing $bin" >&2; exit 2; }
done
if [[ ! -f "$EXPECTED_STAGE/wp-load.php"
   || -L "$EXPECTED_STAGE" || "$(realpath "$EXPECTED_STAGE")" != "$EXPECTED_STAGE" ]]; then
  echo 'BLOCKED: unexpected staging WordPress target' >&2
  exit 2
fi

STATE_DIR="$HOME/.local/state/sanro-studio-qa-order"
if [[ ! -d "$STATE_DIR" ]]; then mkdir -p -m 700 "$STATE_DIR"; fi
if [[ -L "$STATE_DIR"
  || "$(stat -c '%a' "$STATE_DIR")" != '700' ]]; then
  echo 'BLOCKED: QA order state directory unsafe' >&2; exit 2
fi
exec 9>"$STATE_DIR/s142.lock"
if ! flock -n 9; then
  echo 'BLOCKED: QA order command already running' >&2; exit 75
fi

QA="$(mktemp -d "$HOME/.sanro-pr32-qa.XXXXXXXX")"
trap '[[ -z "${QA:-}" || ! -d "$QA" ]] || rm -rf -- "$QA"' EXIT
GIT_TERMINAL_PROMPT=0 git clone --depth 1 --single-branch \
  --branch "$BRANCH" \
  https://github.com/arioguswara-oss/Plugin-Kaoscetak.git "$QA/repo" >/dev/null
HEAD="$(git -C "$QA/repo" rev-parse HEAD)"
if [[ "$HEAD" != "$EXPECTED" ]]; then
  echo 'BLOCKED: branch commit changed; no order created' >&2
  exit 2
fi
php -l "$QA/repo/ops/hostinger/sanro-studio-pr32-qa-order-policy.php"
php -l "$QA/repo/ops/hostinger/sanro-studio-pr32-qa-order.php"
php -l "$QA/repo/tests/staging-hpos-qa-order-policy-smoke.php"
php "$QA/repo/tests/staging-hpos-qa-order-policy-smoke.php"

echo 'PR: #32'
echo "HEAD: $HEAD"
echo "MODE: $MODE"
# The only possible write is ONE owner-approved zero-value synthetic order.
if ! SANRO_STUDIO_QA_ENV=staging \
  SANRO_STUDIO_QA_ORDER_MODE="$MODE" \
  SANRO_STUDIO_APPROVE_ONE_QA_ORDER="${SANRO_STUDIO_APPROVE_ONE_QA_ORDER:-}" \
  php -d display_errors=0 \
    "$QA/repo/ops/hostinger/sanro-studio-pr32-qa-order.php" \
    > "$QA/out.log" 2> "$QA/error.log"; then
  cat "$QA/out.log"
  echo 'STATUS: BLOCKED_OR_FAILED — do not retry automatically'
  exit 2
fi
cat "$QA/out.log"
grep -Fqx 'TARGET: HOSTINGER_SANRO_STAGING_ONLY' "$QA/out.log"
grep -Fqx 'HPOS: ENABLED' "$QA/out.log"
grep -Fqx 'PRODUCTION_MODIFIED: NO' "$QA/out.log"
if [[ "$MODE" == 'create' ]]; then
  if ! grep -Eq '^FIXTURE_STATUS: (CREATED_VERIFIED|EXISTING_SAFE_REUSE)$' "$QA/out.log"; then
    echo 'FAIL: no verified single synthetic order returned' >&2; exit 1
  fi
  grep -Eq '^SANRO_STAGING_ADMIN_ORDER_ID: [1-9][0-9]{0,8}$' "$QA/out.log"
  grep -Fqx 'ORDER_TOTAL: 0 IDR' "$QA/out.log"
  grep -Fqx 'PAYMENT: NONE' "$QA/out.log"
else
  grep -Eq '^FIXTURE_STATUS: (READY_TO_CREATE_ONE_ZERO_PENDING_ORDER|EXISTING_SAFE_REUSE)$' "$QA/out.log"
fi
printf 'PR: #32\nHEAD: %s\nSTATUS: PASS\nTEST_EXIT: 0\n' "$HEAD"
