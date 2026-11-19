#!/usr/bin/env bash
# SANRO Studio local CI: deterministic, read-only checks for the isolated QA worktree.
# Run with: bash scripts/sanro-studio-local-ci.sh
set -u
ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)" || exit 2
cd "$ROOT" || exit 2
for tool in php node; do
  if ! command -v "$tool" >/dev/null 2>&1; then
    printf 'FAIL: missing required command: %s\n' "$tool" >&2
    exit 2
  fi
done

passed=0
failed=0
run_check() {
  local label="$1" expected="$2"
  shift 2
  local output rc
  output="$("$@" 2>&1)" && rc=0 || rc=$?
  printf '\n=== %s ===\n%s\n' "$label" "$output"
  if [[ $rc -eq 0 && "$output" == *"$expected"* ]]; then
    printf 'PASS: %s\n' "$label"
    passed=$((passed + 1))
  else
    printf 'FAIL: %s (exit=%s; expected: %s)\n' "$label" "$rc" "$expected" >&2
    failed=$((failed + 1))
  fi
}

printf 'SANRO STUDIO LOCAL QA\n'
printf 'Root: %s\n' "$ROOT"
printf 'Commit: %s\n' "$(git rev-parse --short HEAD 2>/dev/null || printf 'unknown')"

for file in \
  tests/test_sanro_editor_config.php \
  tests/test_sanro_pricing.php \
  tests/test_sanro_draft_rest.php \
  tests/test_sanro_checkout_guards.php \
  plugins/sanro-studio/includes/editor-config.php
do
  run_check "PHP lint $file" 'No syntax errors detected' php -l "$file"
done

run_check 'Editor config REST' '26 assertions PASS, 0 FAIL' php tests/test_sanro_editor_config.php
run_check 'Pricing matrix' '356 assertions PASS, 0 FAIL' php tests/test_sanro_pricing.php
run_check 'Draft REST' '35 assertions PASS, 0 FAIL' php tests/test_sanro_draft_rest.php
run_check 'Checkout guards' '6 assertions PASS, 0 FAIL' php tests/test_sanro_checkout_guards.php
run_check 'Pricing parity JS syntax' '' node --check tests/test_sanro_pricing_parity.js
run_check 'Pricing PHP/JS parity' '400 cases PASS, 0 FAIL' node tests/test_sanro_pricing_parity.js

printf '\nSUMMARY: %s checks PASS, %s checks FAIL\n' "$passed" "$failed"
if [[ $failed -gt 0 ]]; then
  exit 1
fi
exit 0
