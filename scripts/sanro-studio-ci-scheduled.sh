#!/usr/bin/env bash
# SANRO Studio scheduled QA - isolated worktree only; no merge, deploy or live DB access.
set -Eeuo pipefail
umask 077

source_repo="${SANRO_STUDIO_REPO:-/home/sanro/Plugin-Kaoscetak}"
qa_dir="${SANRO_STUDIO_QA_DIR:-/home/sanro/sanro-studio-ci-qa}"
branch="fix/sanro-studio-editor-config-validation-20261119"
lock_file="${SANRO_STUDIO_CI_LOCK:-/home/sanro/.sanro-studio-ci.lock}"

if ! command -v flock >/dev/null 2>&1; then
  echo 'FAIL: flock unavailable' >&2
  exit 2
fi
exec 9>"$lock_file"
if ! flock -n 9; then
  echo 'SANRO STUDIO CI: another run is active; skipping.'
  exit 0
fi

if [[ ! -d "$source_repo/.git" && ! -f "$source_repo/.git" ]]; then
  echo "FAIL: source git checkout not found: $source_repo" >&2
  exit 2
fi
if [[ "$qa_dir" == "$source_repo" ]]; then
  echo 'FAIL: QA directory must differ from source repository' >&2
  exit 2
fi
for executable in php node git flock; do
  if ! command -v "$executable" >/dev/null 2>&1; then
    printf 'FAIL: %s unavailable\n' "$executable" >&2
    exit 2
  fi
done

echo 'SANRO STUDIO CI: fetching PR #15 branch for isolated QA.'
git -C "$source_repo" fetch --no-tags origin \
  "+refs/heads/$branch:refs/remotes/origin/$branch"
target_sha="$(git -C "$source_repo" rev-parse "refs/remotes/origin/$branch^{commit}")"
echo "SANRO STUDIO CI: target $target_sha"

if [[ -e "$qa_dir" ]]; then
  actual_root="$(git -C "$qa_dir" rev-parse --show-toplevel 2>/dev/null || true)"
  if [[ "$actual_root" != "$qa_dir" ]]; then
    echo 'FAIL: QA directory exists but is not the expected git worktree' >&2
    exit 2
  fi
  if [[ -n "$(git -C "$qa_dir" status --porcelain)" ]]; then
    echo 'FAIL: QA worktree has local changes; refusing to overwrite' >&2
    exit 2
  fi
  git -C "$qa_dir" switch --detach --quiet "$target_sha"
else
  git -C "$source_repo" worktree add --detach "$qa_dir" "$target_sha"
fi

if [[ ! -f "$qa_dir/scripts/sanro-studio-local-ci.sh" ]]; then
  echo 'FAIL: SANRO Studio local QA script not found in target revision' >&2
  exit 2
fi
echo "SANRO STUDIO CI: running checks in $qa_dir"
# Preserve evidence even if local CI fails. No GitHub Actions or deployment.
evidence_dir="${SANRO_STUDIO_CI_EVIDENCE_DIR:-/home/sanro/sanro-studio-ci-evidence}"
mkdir -p -- "$evidence_dir"
run_stamp="$(date -u +%Y%m%dT%H%M%SZ)"
log_file="$evidence_dir/pr15-${target_sha:0:12}-$run_stamp.log"
latest_file="$evidence_dir/latest.txt"
result='FAIL'

if bash "$qa_dir/scripts/sanro-studio-local-ci.sh" >"$log_file" 2>&1; then
  if grep -Fq 'PASS: 74 staging cart adapter checks' "$log_file" \
     && grep -Eq '^SUMMARY: [1-9][0-9]* checks PASS, 0 checks FAIL$' "$log_file"; then
    result='PASS'
  fi
fi

cat -- "$log_file"
# Write the latest receipt atomically; never claim PASS based on grep alone
# when the actual CI process exited unsuccessfully.
receipt_temp="$(mktemp "$evidence_dir/.latest.XXXXXXXX")"
{
  printf 'PROJECT=SANRO_STUDIO\nPR=15\n'
  printf 'SOURCE_SHA=%s\n' "$target_sha"
  printf 'RUN_UTC=%s\n' "$run_stamp"
  printf 'RESULT=%s\n' "$result"
  printf 'LOG=%s\n' "$log_file"
  grep -F 'PASS: 74 staging cart adapter checks' "$log_file" || true
  grep -E '^SUMMARY: ' "$log_file" || true
} >"$receipt_temp"
mv -f -- "$receipt_temp" "$latest_file"

if [[ "$result" == 'PASS' ]]; then
  printf 'VERIFIED HERMES SCHEDULED CI: PASS; SHA=%s\n' "$target_sha"
  exit 0
fi
printf 'FAIL: Hermes scheduled CI did not complete required 74 Cart checks and 0-failure suite; log=%s\n' "$log_file" >&2
exit 1
