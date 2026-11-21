#!/usr/bin/env bash
# SANRO Studio Sprint 134 — REAL PHP-GD raster QA in an isolated container.
# Fetches an exact PR head to a detached worktree; does not deploy/merge or
# install PHP packages on the VPS. Only synthetic images, no external API.
set -Eeuo pipefail
umask 077

BRANCH='manual/sanro-studio-dtf-gd-isolated-20261121'
REPO="${SANRO_STUDIO_REPO:-/home/sanro/Plugin-Kaoscetak}"
STATE="${SANRO_STUDIO_CI_STATE_DIR:-/home/sanro/.local/state/sanro-studio-ci}"
if [[ "$EUID" -eq 0 ]]; then
    printf 'FAIL: use unprivileged sanro account, not root\n' >&2
    exit 2
fi
for bin in git php node flock mktemp sha256sum; do
    command -v "$bin" >/dev/null 2>&1 || {
        printf 'FAIL: missing host dependency: %s\n' "$bin" >&2
        exit 2
    }
done
ENGINE=''
if command -v docker >/dev/null 2>&1; then
    ENGINE=docker
elif command -v podman >/dev/null 2>&1; then
    ENGINE=podman
fi
if [[ -z "$ENGINE" ]]; then
    printf 'BLOCKED: Docker or Podman is not installed. No system changes were made.\n' >&2
    exit 2
fi
if ! "$ENGINE" info >/dev/null 2>&1; then
    printf 'BLOCKED: %s not accessible to user sanro. No sudo or host installation attempted.\n' "$ENGINE" >&2
    exit 2
fi
if [[ ! -d "$REPO/.git" ]] && ! git -C "$REPO" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    printf 'FAIL: expected local Git checkout not available\n' >&2
    exit 2
fi

mkdir -p "$STATE"; chmod 700 "$STATE"
exec 9>"$STATE/pr23-gd.lock"
if ! flock -n 9; then
    printf 'FAIL: another isolated PHP-GD QA is running\n' >&2
    exit 75
fi
git -C "$REPO" fetch --no-tags origin \
    "+refs/heads/$BRANCH:refs/remotes/origin/$BRANCH"
sha="$(git -C "$REPO" rev-parse "refs/remotes/origin/$BRANCH^{commit}")"
if [[ -n "${SANRO_STUDIO_EXPECTED_SHA:-}" && "$sha" != "$SANRO_STUDIO_EXPECTED_SHA" ]]; then
    printf 'FAIL: SHA mismatch; expected %s, actual %s\n' "$SANRO_STUDIO_EXPECTED_SHA" "$sha" >&2
    exit 2
fi

RUN_ID="$(date -u +%Y%m%dT%H%M%SZ)-${sha:0:12}"
ARTIFACT_DIR="$STATE/pr23-gd-$RUN_ID"
LOG="$STATE/pr23-gd-$RUN_ID.log"
mkdir -p "$ARTIFACT_DIR"
chmod 700 "$ARTIFACT_DIR"
WORKTREE="$(mktemp -d "${TMPDIR:-/tmp}/sanro-pr23-gd.XXXXXXXX")"
IMAGE="sanro-studio-gd-qa:${sha:0:12}"
image_built=false
cleanup() {
    git -C "$REPO" worktree remove --force "$WORKTREE" >/dev/null 2>&1 || true
    if [[ -d "$WORKTREE" ]]; then rmdir "$WORKTREE" 2>/dev/null || true; fi
    if [[ "$image_built" == true ]]; then "$ENGINE" image rm "$IMAGE" >/dev/null 2>&1 || true; fi
}
trap cleanup EXIT
git -C "$REPO" worktree add --detach "$WORKTREE" "$sha" >/dev/null
printf 'Sprint 134 isolated QA\nHEAD: %s\nBranch: %s\nEngine: %s\n' "$sha" "$BRANCH" "$ENGINE" > "$LOG"

# Keep the 100 pre-existing CI gates before testing a real GD image.
set +e
(cd "$WORKTREE" && bash scripts/sanro-studio-local-ci.sh) >> "$LOG" 2>&1
ci_exit=$?
set -e
ci_summary="$(grep -E '^SUMMARY: [0-9]+ checks PASS, [0-9]+ checks FAIL$' "$LOG" | tail -n 1 || true)"
if [[ "$ci_exit" -ne 0 || "$ci_summary" != 'SUMMARY: 100 checks PASS, 0 checks FAIL' ]]; then
    printf 'FAIL: baseline local CI failed (%s): %s\n' "$ci_exit" "$ci_summary" >&2
    grep -n '^FAIL:' "$LOG" >&2 || true
    exit 1
fi
printf 'BASELINE_CI: %s\n' "$ci_summary"

# Pull/build happens in disposable image layers; no apt on the VPS itself.
echo 'BUILD: isolated php:8.3-cli-bookworm + GD (may need image registry access)'
if ! "$ENGINE" build --pull -f "$WORKTREE/ops/hermes/dtf-gd/Dockerfile" \
    -t "$IMAGE" "$WORKTREE/ops/hermes/dtf-gd" >> "$LOG" 2>&1; then
    printf 'FAIL: container image build failed; see private log %s\n' "$LOG" >&2
    tail -n 30 "$LOG" >&2
    exit 1
fi
image_built=true

EXTRA=()
if [[ "$ENGINE" == podman ]]; then EXTRA=(--userns=keep-id); fi
set +e
"$ENGINE" run --rm --network none --read-only \
    --cap-drop ALL --security-opt no-new-privileges \
    --pids-limit 128 --memory 512m --cpus 2 \
    --tmpfs /tmp:rw,nosuid,nodev,noexec,size=32m \
    --user "$(id -u):$(id -g)" "${EXTRA[@]}" \
    --mount "type=bind,src=$WORKTREE,dst=/src,readonly" \
    --mount "type=bind,src=$ARTIFACT_DIR,dst=/out" \
    --workdir /src --env SANRO_DTF_ARTIFACT_DIR=/out \
    "$IMAGE" php -d memory_limit=256M -d display_errors=1 \
    plugins/sanro-studio/tests/dtf-image-gd-real-smoke.php >> "$LOG" 2>&1
gd_exit=$?
set -e
if [[ "$gd_exit" -ne 0 ]] || ! grep -Fqx 'PASS: 15 SANRO real PHP GD DTF raster assertions' "$LOG" \
    || ! grep -Fq 'GD_RASTER_REAL: VERIFIED' "$LOG" \
    || ! grep -Fq 'PHP_GD_RASTER: PASS (actual PNG decoded and verified)' "$LOG"; then
    printf 'FAIL: real GD pixel verification failed (exit %s)\n' "$gd_exit" >&2
    tail -n 40 "$LOG" >&2
    exit 1
fi

FILE="$ARTIFACT_DIR/synthetic-dtf-5cm-300dpi-rgba.png"
MANIFEST="$ARTIFACT_DIR/synthetic-dtf-manifest.json"
if [[ ! -s "$FILE" || ! -s "$MANIFEST" ]]; then
    printf 'FAIL: private QA artifact or manifest missing\n' >&2; exit 1
fi
expected="$(php -r '$m=json_decode(file_get_contents($argv[1]),true);echo is_array($m)?($m["sha256"]??""):"";' "$MANIFEST")"
actual="$(sha256sum "$FILE" | cut -d ' ' -f 1)"
if [[ -z "$expected" || "$expected" != "$actual" ]]; then
    printf 'FAIL: persisted PNG SHA-256 does not match manifest\n' >&2; exit 1
fi

printf 'PR: #23\nHEAD: %s\nSTATUS: PASS\nTEST_EXIT: 0\nBASELINE: %s\nPHP_GD: AVAILABLE (isolated image)\nGD_RASTER_REAL: VERIFIED\nREAL_GD_ASSERTIONS: 15 PASS, 0 FAIL\nARTIFACT_SHA256: %s\nPRIVATE_ARTIFACT_DIR: %s\nLOG: %s\n' \
    "$sha" "$ci_summary" "$actual" "$ARTIFACT_DIR" "$LOG"
