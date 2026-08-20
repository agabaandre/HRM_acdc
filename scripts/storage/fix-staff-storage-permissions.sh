#!/usr/bin/env bash
# Create host data dirs and set permissions for staff ecosystem storage.
set -euo pipefail
source "$(dirname "$0")/_common.sh"

sudo mkdir -p \
  "${STAFF_DATA_ROOT}/ci" \
  "${STAFF_DATA_ROOT}/apm" \
  "${STAFF_DATA_ROOT}/helpdesk" \
  "${STAFF_DATA_ROOT}/staff-portal" \
  "${STAFF_DATA_ROOT}/backups/files"

sudo chown -R "${OWNER}:${GROUP}" "${STAFF_DATA_ROOT}"
chmod -R ug+rwX "${STAFF_DATA_ROOT}" 2>/dev/null || true
# Site root must be traversable by the web server (e.g. Apache _www).
chmod ug+rwX "${STAFF_HOST_DATA_ROOT}" 2>/dev/null || sudo chmod ug+rwX "${STAFF_HOST_DATA_ROOT}" 2>/dev/null || true

log "Permissions set on ${STAFF_DATA_ROOT}"
