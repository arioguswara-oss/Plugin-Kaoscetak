#!/usr/bin/env bash
# Sprint 134 non-privileged, read-only runtime capability inspection.
# No sudo, package installs, group changes, daemon restarts or production I/O.
set -uo pipefail

printf 'SANRO DTF GD runtime preflight (read-only)\n'
printf 'USER: %s\n' "$(id -un)"
printf 'UID: %s\n' "$(id -u)"
printf 'GROUPS: %s\n' "$(id -nG)"
for engine in docker podman; do
  if ! command -v "$engine" >/dev/null 2>&1; then
    printf 'ENGINE_%s: NOT_INSTALLED\n' "$(printf '%s' "$engine" | tr '[:lower:]' '[:upper:]')"
    continue
  fi
  if "$engine" info >/dev/null 2>&1; then
    printf 'ENGINE_%s: ACCESSIBLE\n' "$(printf '%s' "$engine" | tr '[:lower:]' '[:upper:]')"
  else
    printf 'ENGINE_%s: PRESENT_BUT_INACCESSIBLE\n' "$(printf '%s' "$engine" | tr '[:lower:]' '[:upper:]')"
  fi
done
if [[ -S /var/run/docker.sock ]]; then
  stat -c 'DOCKER_SOCKET: mode=%a owner=%U group=%G' /var/run/docker.sock 2>/dev/null ||
    printf 'DOCKER_SOCKET: access metadata unavailable\n'
else
  printf 'DOCKER_SOCKET: ABSENT\n'
fi
# Check only existing PHP CLI binaries; do not load untrusted extensions.
checked=' '
for executable in php php8.1 php8.2 php8.3 php8.4 php8.5; do
  candidate="$(command -v "$executable" 2>/dev/null || true)"
  if [[ -z "$candidate" || ! -x "$candidate" || "$checked" == *" $candidate "* ]]; then
    continue
  fi
  checked="$checked$candidate "
  version="$("$candidate" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || true)"
  if [[ -z "$version" ]]; then
    printf 'PHP_CLI: %s UNAVAILABLE\n' "$executable"
    continue
  fi
  gd="$("$candidate" -r 'echo extension_loaded("gd") && function_exists("imageresolution") ? "AVAILABLE" : "UNAVAILABLE";' 2>/dev/null || true)"
  printf 'PHP_CLI: %s version=%s GD=%s\n' "$candidate" "$version" "${gd:-ERROR}"
done
printf 'DIAGNOSTIC_DONE: no host configuration modified\n'
