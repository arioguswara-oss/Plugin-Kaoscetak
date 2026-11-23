#!/usr/bin/env bash
# Offline gate. Integration drivers/private vendor entrypoints are never auto-executed.
set -uo pipefail
cd "$(dirname "$0")/.." || exit 1
sha=$(git rev-parse HEAD) || exit 1
status=0
files=''
finish() {
    code=$?
    [[ -z "$files" ]] || rm -f "$files"
    trap - EXIT
    if (( code != 0 || status != 0 )); then printf 'FAIL %s\n' "$sha"; exit 1; fi
    printf 'PASS %s\n' "$sha"
}
trap finish EXIT
run() {
    printf 'RUN' >&2; printf ' %q' "$@" >&2; printf '\n' >&2
    if ! "$@" </dev/null >&2; then printf 'FAIL command:' >&2; printf ' %q' "$@" >&2; printf '\n' >&2; status=1; failed=$((failed+1));
    else passed=$((passed+1)); fi
}
files=$(mktemp) || exit 1
if ! git ls-files -z --cached --others --exclude-standard -- plugins tests | sort -zu > "$files"; then exit 1; fi
php_ok=1; node_ok=1
command -v php >/dev/null 2>&1 || { printf 'MISSING php\n' >&2; status=1; php_ok=0; }
command -v node >/dev/null 2>&1 || { printf 'MISSING node\n' >&2; status=1; node_ok=0; }
php_tests=0; node_tests=0; passed=0; failed=0; not_tested=0
while IFS= read -r -d '' file; do
    [[ -f "$file" ]] || continue
    case "$file" in
        tests/results/*|tests/.downloads/*|tests/node_modules/*|plugins/*/vendor/*|plugins/*/runtime/*) continue ;;
    esac
    case "$file" in
        *.php) (( php_ok == 0 )) || run php -l "$file" ;;
        *.js|*.cjs|*.mjs) (( node_ok == 0 )) || run node --check "$file" ;;
    esac
    case "$file" in
        plugins/sanro-studio/tests/dtf-order-workflow-real-smoke.php|plugins/sanro-studio/tests/dtf-image-gd-real-smoke.php)
            printf 'NOT TESTED %s: requires explicit trusted private TTF/GD QA environment\n' "$file" >&2; not_tested=$((not_tested+1)) ;;
        plugins/sanro-studio/tests/native-readiness-gates.php)
            php_tests=$((php_tests+1))
            if (( php_ok != 0 )); then
                for scenario in missing_woocommerce buyer_disabled buyer_enabled; do run php "$file" "$scenario"; done
            fi ;;
        plugins/*/tests/*.php)
            php_tests=$((php_tests+1)); (( php_ok == 0 )) || run php "$file" ;;
        plugins/*/tests/*-node.cjs)
            node_tests=$((node_tests+1)); (( node_ok == 0 )) || run node "$file" ;;
        tests/sanro-core-boot-contract.php|tests/sanro-vendor-contract.php)
            printf 'NOT TESTED %s: requires owner private vendor source\n' "$file" >&2; not_tested=$((not_tested+1)) ;;
        tests/wordpress*.php|tests/variable_integration.php|tests/sanro-wp-integration-smoke.php)
            printf 'NOT TESTED %s: requires explicit isolated WP/Woo environment\n' "$file" >&2; not_tested=$((not_tested+1)) ;;
        tests/fixture.php|tests/drive-fixture.php|tests/router.php|tests/sanro-studio-fixture.php|tests/lib/*)
            : ;; # Fixtures/support are linted, not standalone tests.
        tests/test_*.php|tests/studio-*-offline.php|tests/studio-restore-default-off.php|tests/staging-*-offline.php|tests/staging-*-policy.php|tests/staging-*-smoke.php)
            php_tests=$((php_tests+1)); (( php_ok == 0 )) || run php "$file" ;;
        tests/test_*.js|tests/test_*.cjs|tests/*-node.cjs|tests/*.test.cjs|tests/*.test.mjs)
            node_tests=$((node_tests+1)); (( node_ok == 0 )) || run node "$file" ;;
        tests/staging-*.cjs|tests/customer-flow.cjs|tests/lifecycle.cjs|tests/staging-*.php)
            printf 'NOT TESTED %s: integration driver/helper requires explicit QA invocation\n' "$file" >&2; not_tested=$((not_tested+1)) ;;
        tests/*.php|tests/*.js|tests/*.cjs|tests/*.mjs)
            printf 'FAIL unclassified executable: %s (classify before execution)\n' "$file" >&2; status=1; failed=$((failed+1)) ;;
    esac
done < "$files"
printf 'SUMMARY passed=%s failed=%s php_tests=%s node_tests=%s not_tested=%s\n' "$passed" "$failed" "$php_tests" "$node_tests" "$not_tested" >&2
if (( php_tests == 0 || node_tests == 0 )); then
    printf 'FAIL missing PHP or Node offline coverage\n' >&2; status=1
fi
