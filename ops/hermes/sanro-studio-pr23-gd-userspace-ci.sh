#!/usr/bin/env bash
# SANRO Studio Sprint 134 — non-root PHP-GD real pixel QA without Docker.
# Pull a configured APT repository binary archive into an isolated directory,
# extract gd.so ONLY (never apt install or modify system PHP), run synthetic QA,
# and remove extracted package/module when finished. This uses existing trusted
# APT repository configuration; never accepts arbitrary external package URLs.
set -Eeuo pipefail
umask 077

BRANCH='manual/sanro-studio-dtf-gd-isolated-20261121'
REPO="${SANRO_STUDIO_REPO:-/home/sanro/Plugin-Kaoscetak}"
STATE="${SANRO_STUDIO_CI_STATE_DIR:-/home/sanro/.local/state/sanro-studio-ci}"

if [[ "$EUID" -eq 0 ]]; then
    printf 'FAIL: run as unprivileged sanro, not root\n' >&2
    exit 2
fi
for cmd in git php node apt-get apt-cache dpkg dpkg-deb find sha256sum mktemp flock; do
    if ! command -v "$cmd" >/dev/null 2>&1; then
        printf 'BLOCKED: missing tool %s; no host changes made\n' "$cmd" >&2
        exit 2
    fi
done
if ! git -C "$REPO" rev-parse --is-inside-work-tree 2>/dev/null | grep -qx true; then
    printf 'FAIL: expected Git checkout missing: %s\n' "$REPO" >&2
    exit 2
fi
minor="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
if [[ "$minor" != '8.5' ]]; then
    printf 'BLOCKED: this non-root GD QA is pinned to PHP 8.5; found %s\n' "$minor" >&2
    exit 2
fi
package='php8.5-gd'
candidate="$(apt-cache policy "$package" | awk '/Candidate:/ {print $2;exit}')"
if [[ -z "$candidate" || "$candidate" == '(none)' ]]; then
    printf 'BLOCKED: %s not indexed in the VPS configured APT repositories.\n' "$package" >&2
    printf 'Do not add repos, install packages, or run sudo for this test.\n' >&2
    exit 2
fi
mkdir -p "$STATE"
chmod 700 "$STATE"
exec 9>"$STATE/pr23-gd-userspace.lock"
if ! flock -n 9; then
    printf 'FAIL: another userspace GD test is running\n' >&2
    exit 75
fi

git -C "$REPO" fetch --no-tags origin \
    "+refs/heads/$BRANCH:refs/remotes/origin/$BRANCH"
sha="$(git -C "$REPO" rev-parse "refs/remotes/origin/$BRANCH^{commit}")"
if [[ -n "${SANRO_STUDIO_EXPECTED_SHA:-}" && "$sha" != "$SANRO_STUDIO_EXPECTED_SHA" ]]; then
    printf 'FAIL: expected %s; fetched %s\n' "$SANRO_STUDIO_EXPECTED_SHA" "$sha" >&2
    exit 2
fi
run_id="$(date -u +%Y%m%dT%H%M%SZ)-${sha:0:12}"
output="$STATE/pr23-gd-userspace-$run_id"
log="$STATE/pr23-gd-userspace-$run_id.log"
scratch="$(mktemp -d "$STATE/.pr23-gd-module.XXXXXXXX")"
worktree="$(mktemp -d "${TMPDIR:-/tmp}/sanro-pr23-gd-userspace.XXXXXXXX")"
cleanup() {
    git -C "$REPO" worktree remove --force "$worktree" >/dev/null 2>&1 || true
    if [[ -d "$worktree" ]]; then rmdir "$worktree" 2>/dev/null || true; fi
    rm -rf -- "$scratch"
}
trap cleanup EXIT
mkdir -p "$output"
chmod 700 "$output"
git -C "$REPO" worktree add --detach "$worktree" "$sha" >/dev/null
printf 'PR: #23\nHEAD: %s\nAPT_PACKAGE: %s\nAPT_CANDIDATE: %s\n' \
    "$sha" "$package" "$candidate" > "$log"

# Reverify earlier isolated repo gates against this exact commit.
set +e
(cd "$worktree" && bash scripts/sanro-studio-local-ci.sh) >> "$log" 2>&1
ci_exit=$?
set -e
summary="$(grep -E '^SUMMARY: [0-9]+ checks PASS, [0-9]+ checks FAIL$' "$log" | tail -n 1 || true)"
if [[ "$ci_exit" -ne 0 || "$summary" != 'SUMMARY: 100 checks PASS, 0 checks FAIL' ]]; then
    printf 'FAIL: baseline CI (exit=%s) %s\n' "$ci_exit" "$summary" >&2
    grep -n '^FAIL:' "$log" >&2 || true
    exit 1
fi
printf 'BASELINE_CI: %s\n' "$summary"

# APT download (NOT install) uses only packages indexed in the host's existing
# configured repositories. Extraction is confined to private temporary space.
mkdir -p "$scratch/download" "$scratch/extracted"
if ! (cd "$scratch/download" && apt-get download "$package") >> "$log" 2>&1; then
    printf 'BLOCKED: could not download %s as unprivileged user; see %s\n' "$package" "$log" >&2
    tail -n 12 "$log" >&2
    exit 2
fi
shopt -s nullglob
packages=("$scratch/download/"*.deb)
shopt -u nullglob
if [[ "${#packages[@]}" -ne 1 ]]; then
    printf 'FAIL: exactly one trusted APT binary package expected\n' >&2
    exit 1
fi
deb="${packages[0]}"
if [[ "$(dpkg-deb -f "$deb" Package)" != "$package" ]]; then
    printf 'FAIL: downloaded package name mismatch\n' >&2
    exit 1
fi
arch="$(dpkg-deb -f "$deb" Architecture)"
if [[ "$arch" != "$(dpkg --print-architecture)" ]]; then
    printf 'FAIL: GD package architecture mismatch (%s)\n' "$arch" >&2
    exit 1
fi
dpkg-deb -x "$deb" "$scratch/extracted"
module="$(find "$scratch/extracted" -type f -name gd.so -print -quit)"
if [[ -z "$module" || ! -s "$module" ]]; then
    printf 'FAIL: GD extension missing from extracted package\n' >&2
    exit 1
fi
# The extension MUST load into PHP's own ABI; all dependent shared libs must
# already exist. Never install any missing dependency onto the host.
if ! php -d "extension=$module" -r \
    'if (!extension_loaded("gd") || !function_exists("imageresolution")) exit(1); echo "GD module loaded into running PHP ".PHP_VERSION."\\n";' \
    >> "$log" 2>&1; then
    printf 'BLOCKED: userspace GD binary cannot load (ABI or shared-library dependency).\n' >&2
    printf 'No GD package was installed. Diagnostics:\n' >&2
    tail -n 12 "$log" >&2
    exit 2
fi

set +e
(cd "$worktree" && SANRO_DTF_ARTIFACT_DIR="$output" \
    php -d "extension=$module" -d memory_limit=256M -d display_errors=1 \
    plugins/sanro-studio/tests/dtf-image-gd-real-smoke.php) >> "$log" 2>&1
gd_exit=$?
set -e
if [[ "$gd_exit" -ne 0 ]] \
    || ! grep -Fqx 'PASS: 15 SANRO real PHP GD DTF raster assertions' "$log" \
    || ! grep -Fq 'GD_RASTER_REAL: VERIFIED' "$log" \
    || ! grep -Fq 'PHP_GD_RASTER: PASS (actual PNG decoded and verified)' "$log"; then
    printf 'FAIL: real userspace PHP GD raster test (exit=%s)\n' "$gd_exit" >&2
    tail -n 50 "$log" >&2
    exit 1
fi
artifact="$output/synthetic-dtf-5cm-300dpi-rgba.png"
manifest="$output/synthetic-dtf-manifest.json"
if [[ ! -s "$artifact" || ! -s "$manifest" ]]; then
    printf 'FAIL: real raster artifact or manifest missing\n' >&2
    exit 1
fi
expected="$(php -r '$j=json_decode(file_get_contents($argv[1]),true);echo is_array($j)?($j["sha256"]??""):"";' "$manifest")"
actual="$(sha256sum "$artifact" | cut -d ' ' -f 1)"
if [[ ! "$actual" =~ ^[a-f0-9]{64}$ || "$expected" != "$actual" ]]; then
    printf 'FAIL: SHA-256 verification mismatch\n' >&2
    exit 1
fi
printf 'PR: #23\nHEAD: %s\nSTATUS: PASS\nTEST_EXIT: 0\nMODE: USERSPACE_PHP_GD_NO_DOCKER\nBASELINE: %s\nGD_RASTER_REAL: VERIFIED\nREAL_GD_ASSERTIONS: 15 PASS, 0 FAIL\nARTIFACT_SHA256: %s\nPRIVATE_ARTIFACT_DIR: %s\nLOG: %s\n' \
    "$sha" "$summary" "$actual" "$output" "$log"
