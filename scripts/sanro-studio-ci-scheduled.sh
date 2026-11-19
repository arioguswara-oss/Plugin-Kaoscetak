#!/usr/bin/env bash
# SANRO Studio scheduled QA - isolated worktree only; no merge, deploy or live DB access.
set -Eeuo pipefail
umask 077

source_repo="${SANRO_STUDIO_REPO:-/home/sanro/Plugin-Kaoscetak}"
qa_dir="${SANRO_STUDIO_QA_DIR:-/home/sanro/sanro-studio-ci-qa}"
branch="fix/sanro-studio-editor-config-validation-20261119"
lock_file="${SANRO_STUDIO_CI_LOCK:-/home/sanro/.sanro-studio-ci.lock}"

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
if [[ ! -x /usr/bin/php && ! $(command -v php || true) ]]; then
  echo 'FAIL: PHP unavailable' >&2
  exit 2
fi
command -v git >/dev/null || exit 2
command -v node >/dev/null || exit 2

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
bash "$qa_dir/scripts/sanro-studio-local-ci.sh"
