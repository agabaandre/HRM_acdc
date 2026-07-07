#!/usr/bin/env bash
# Migrate staff-portal/storage/app/public → /var/staffdata/{site-id}/staff-portal/
set -euo pipefail
source "$(dirname "$0")/_common.sh"

MODULE="staff-portal"
SRC="${STAFF_PORTAL_LEGACY_ROOT:-${REPO_ROOT}/staff-portal/storage/app/public}"
DEST="${STAFF_PORTAL_MODULE_FILES_ROOT:-${STAFF_DATA_ROOT}/staff-portal}"

migrate_copy "$MODULE" "$SRC" "$DEST"
if [[ "${VERIFY:-true}" == "true" && "${DRY_RUN:-false}" != "true" ]]; then
  verify_sizes "$SRC" "$DEST"
fi

log "Set in staff-portal/.env:"
log "  STAFF_DATA_ROOT=${STAFF_DATA_ROOT}"
log "  STAFF_PORTAL_UPLOADS_ROOT=${STAFF_PORTAL_UPLOADS_ROOT:-${STAFF_DATA_ROOT}/ci}"
log "  STAFF_PORTAL_MODULE_FILES_ROOT=${DEST}"
log "Then: cd ${REPO_ROOT}/staff-portal && php artisan storage:link"
