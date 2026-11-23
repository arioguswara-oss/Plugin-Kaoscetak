#!/usr/bin/env bash
# Sprint 138: Hostinger staging-only synthetic WooCommerce admin metadata QA.
# No plugin deployment, no WooCommerce DB access, no production file reads.
set -Eeuo pipefail
umask 077

if [[ "$(hostname -s)" != "id-dci-web1631" || "${SANRO_STUDIO_QA_ENV:-}" != 'staging' ]]; then
    printf 'BLOCKED: only Hostinger SANRO Studio staging permitted\n' >&2
    exit 2
fi
if [[ "$EUID" -eq 0 ]]; then
    printf 'BLOCKED: staging unprivileged user required\n' >&2
    exit 2
fi
expected="${SANRO_STUDIO_EXPECTED_SHA:-}"
if [[ ! "$expected" =~ ^[0-9a-f]{40}$ ]]; then
    printf 'BLOCKED: expected exact SHA-1 commit\n' >&2
    exit 2
fi
for program in php git grep mktemp; do
    command -v "$program" >/dev/null || exit 2
done
qa_dir="$(mktemp -d "$HOME/.sanro-studio-pr27.XXXXXXXX")"
trap '[[ "$qa_dir" == "$HOME/.sanro-studio-pr27."* ]] && rm -rf -- "$qa_dir"' EXIT
GIT_TERMINAL_PROMPT=0 git clone --depth 1 --single-branch \
    --branch manual/sanro-studio-admin-dtf-status-20261121 \
    https://github.com/arioguswara-oss/Plugin-Kaoscetak.git "$qa_dir/repo" >/dev/null
head="$(git -C "$qa_dir/repo" rev-parse HEAD)"
if [[ "$head" != "$expected" ]]; then
    printf 'FAIL: PR SHA mismatch\n' >&2
    exit 1
fi
cd "$qa_dir/repo"
php -l plugins/sanro-studio/includes/dtf-admin-order-status.php
php -l plugins/sanro-studio/tests/dtf-admin-order-status-smoke.php
php -l plugins/sanro-studio/tests/dtf-admin-order-status-default-off.php
php plugins/sanro-studio/tests/dtf-admin-order-status-smoke.php > "$qa_dir/test.log" 2>&1
cat "$qa_dir/test.log"
grep -Fqx 'PASS: 28 SANRO WooCommerce admin DTF status access checks' "$qa_dir/test.log"
php plugins/sanro-studio/tests/dtf-admin-order-status-default-off.php > "$qa_dir/disabled.log" 2>&1
cat "$qa_dir/disabled.log"
grep -Fqx 'PASS: SANRO WooCommerce admin DTF candidate status defaults disabled' "$qa_dir/disabled.log"
printf 'PR: #27\nHEAD: %s\nSTATUS: PASS\nTEST_EXIT: 0\nADMIN_STATUS_CHECKS: 28 PASS, 0 FAIL\n' "$head"
