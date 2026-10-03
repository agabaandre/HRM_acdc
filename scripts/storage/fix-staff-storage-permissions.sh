#!/usr/bin/env bash
# Create host data dirs and set permissions for staff ecosystem storage.
# Skips interactive sudo when dirs are already writable (existing installs).
set -euo pipefail
source "$(dirname "$0")/_common.sh"

run_priv() {
  if "$@" 2>/dev/null; then
    return 0
  fi
  if [[ "$(id -u)" -eq 0 ]]; then
    "$@"
    return $?
  fi
  if command -v sudo >/dev/null 2>&1; then
    if sudo -n "$@" 2>/dev/null; then
      return 0
    fi
    if [[ "${ALLOW_INTERACTIVE_SUDO:-0}" == "1" ]]; then
      sudo "$@" && return 0
    fi
  fi
  return 1
}

host_storage_ok() {
  local root="$1" d
  [[ -d "$root" && -w "$root" ]] || return 1
  for d in ci apm helpdesk staff-portal backups/files; do
    [[ -d "${root}/${d}" && -w "${root}/${d}" ]] || return 1
  done
  return 0
}

DIRS=(
  "${STAFF_DATA_ROOT}/ci"
  "${STAFF_DATA_ROOT}/apm"
  "${STAFF_DATA_ROOT}/helpdesk"
  "${STAFF_DATA_ROOT}/staff-portal"
  "${STAFF_DATA_ROOT}/backups/files"
)

if host_storage_ok "${STAFF_DATA_ROOT}"; then
  log "Host staffdata already writable — skip chown/sudo (${STAFF_DATA_ROOT})"
  chmod -R ug+rwX "${STAFF_DATA_ROOT}" 2>/dev/null || true
  exit 0
fi

for d in "${DIRS[@]}"; do
  if [[ ! -d "$d" ]]; then
    mkdir -p "$d" 2>/dev/null || run_priv mkdir -p "$d" || true
  fi
done

if ! run_priv chown -R "${OWNER}:${GROUP}" "${STAFF_DATA_ROOT}"; then
  if host_storage_ok "${STAFF_DATA_ROOT}"; then
    log "Host staffdata writable without chown (${STAFF_DATA_ROOT})"
  else
    log "WARNING: could not chown ${STAFF_DATA_ROOT} (need root or passwordless sudo on first install)"
  fi
fi

chmod -R ug+rwX "${STAFF_DATA_ROOT}" 2>/dev/null || true
# Site root must be traversable by the web server (e.g. Apache _www).
chmod ug+rwX "${STAFF_HOST_DATA_ROOT}" 2>/dev/null \
  || run_priv chmod ug+rwX "${STAFF_HOST_DATA_ROOT}" \
  || true

log "Permissions set on ${STAFF_DATA_ROOT}"
