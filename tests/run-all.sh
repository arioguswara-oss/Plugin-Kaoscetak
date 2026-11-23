#!/usr/bin/env bash
# Offline checks only: never installs packages, starts WordPress or touches a site.
set -u
cd "$(dirname "$0")/.." || exit 1
sha=$(git rev-parse HEAD) || exit 1
status=0
finish() {
    code=$?
    [[ -z "${files:-}" ]] || rm -f "$files"
    trap - EXIT
    if (( code != 0 || status != 0 )); then printf 'FAIL %s\n' "$sha"; exit 1; fi
    printf 'PASS %s\n' "$sha"
}
trap finish EXIT
run() {
    printf 'RUN' >&2; printf ' %q' "$@" >&2; printf '\n' >&2
    if ! "$@" >&2; then status=1; fi
}
# Discover repository files, excluding ignored vendor/cache/old test-result artifacts.
# Plugin source is linted but never modified or executed as an integration environment.
files=$(mktemp) || exit 1
if ! git ls-files -z --cached --others --exclude-standard -- plugins tests > "$files"; then exit 1; fi
php_ok=1; node_ok=1
command -v php >/dev/null 2>&1 || { printf 'MISSING php\n' >&2; status=1; php_ok=0; }
command -v node >/dev/null 2>&1 || { printf 'MISSING node\n' >&2; status=1; node_ok=0; }
php_tests=0; node_tests=0
while IFS= read -r -d '' file; do
    [[ -f "$file" ]] || continue
    case "$file" in
        *.php) (( php_ok == 0 )) || run php -l "$file" ;;
        *.js|*.cjs|*.mjs) (( node_ok == 0 )) || run node --check "$file" ;;
    esac
    # All PHP scripts under tests are executable tests by this directory's contract.
    case "$file" in
        tests/*.php)
            php_tests=$((php_tests + 1))
            (( php_ok == 0 )) || run php "$file"
            ;;
        tests/*.js|tests/*.cjs|tests/*.mjs)
            node_tests=$((node_tests + 1))
            (( node_ok == 0 )) || run node "$file"
            ;;
    esac
done < "$files"
rm -f "$files"
printf 'SUMMARY php_tests=%s node_tests=%s\n' "$php_tests" "$node_tests" >&2
if (( php_tests == 0 || node_tests == 0 )); then
    printf 'Missing PHP or Node test coverage\n' >&2; status=1
fi
