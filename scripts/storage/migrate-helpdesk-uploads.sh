#!/usr/bin/env bash
# Migrate helpdesk/backend/storage/app/public → /var/staffdata/{site-id}/helpdesk/
set -euo pipefail
source "$(dirname "$0")/_common.sh"

MODULE="helpdesk"
SRC="${STAFF_HELPDESK_LEGACY_ROOT:-${REPO_ROOT}/helpdesk/backend/storage/app/public}"
DEST="${STAFF_HELPDESK_FILES_ROOT:-${STAFF_DATA_ROOT}/helpdesk}"

migrate_copy "$MODULE" "$SRC" "$DEST"
if [[ "${VERIFY:-true}" == "true" && "${DRY_RUN:-false}" != "true" ]]; then
  verify_sizes "$SRC" "$DEST"
fi

log "Set in helpdesk/backend/.env:"
log "  STAFF_DATA_ROOT=${STAFF_DATA_ROOT}"
log "  STAFF_HELPDESK_FILES_ROOT=${DEST}"
log "  STAFF_PORTAL_UPLOADS_ROOT=${STAFF_PORTAL_UPLOADS_ROOT:-${STAFF_DATA_ROOT}/ci}"
log "Then: cd ${REPO_ROOT}/helpdesk/backend && php artisan storage:link"
