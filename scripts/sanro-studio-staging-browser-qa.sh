#!/usr/bin/env bash
# Free, staging-only SANRO Studio buyer browser QA (no order/payment submit).
# Default: read-only preflight. Explicit SANRO_STAGING_CART_E2E=1 writes one
# synthetic design draft and cart on the owner-authorized staging site.
set -Eeuo pipefail
ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
if ! command -v node >/dev/null 2>&1; then
  echo "BLOCKED: Node.js is required on the Hermes QA worktree." >&2
  exit 2
fi

# Reuse already-installed QA dependencies. Do not install or download packages
# automatically and do not require a paid third-party browser service.
if ! node -e "require('playwright')" >/dev/null 2>&1; then
  for dir in "$ROOT/tests/node_modules" "${HOME:-/home/sanro}/sanro-browser-qa/node_modules" "/home/sanro/sanro-browser-qa/node_modules"; do
    if [[ -d "$dir/playwright" ]]; then
      export NODE_PATH="$dir${NODE_PATH:+:$NODE_PATH}"
      break
    fi
  done
fi
if ! node -e "require('playwright')" >/dev/null 2>&1; then
  echo "BLOCKED: Playwright not found in existing QA node_modules." >&2
  echo "Use the existing isolated browser QA installation; do not use TinyFish." >&2
  exit 2
fi

if [[ -z "${SANRO_CHROMIUM_EXECUTABLE:-}" ]]; then
  for browser in chromium chromium-browser google-chrome; do
    if command -v "$browser" >/dev/null 2>&1; then
      export SANRO_CHROMIUM_EXECUTABLE
      SANRO_CHROMIUM_EXECUTABLE="$(command -v "$browser")"
      break
    fi
  done
fi

# Dedicated mobile emulator gate is strictly read-only; it never starts
# the synthetic Cart/Checkout branch even if a caller sets both flags.
if [[ "${SANRO_STAGING_MOBILE_QA:-0}" == "1" ]]; then
  if [[ "${SANRO_STAGING_CART_E2E:-0}" == "1" ]]; then
    echo "BLOCKED: Mobile QA is read-only; cannot combine with Cart E2E." >&2
    exit 2
  fi
  echo "STAGING MOBILE QA: touch emulation at 320/390/768px, no draft/cart/order/payment."
  node "$ROOT/tests/staging-mobile-readonly.cjs"
  exit $?
fi

if [[ "${SANRO_STAGING_CART_E2E:-0}" == "1" ]]; then
  echo "STAGING E2E: one synthetic draft and one cart addition; no order or payment."
else
  echo "STAGING QA: read-only editor preflight; no draft/cart mutation."
fi
node "$ROOT/tests/staging-cart-checkout-e2e.cjs"
