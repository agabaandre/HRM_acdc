#!/usr/bin/env bash
# Migrate apm/storage/app/public → /var/staffdata/{site-id}/apm/
set -euo pipefail
source "$(dirname "$0")/_common.sh"

MODULE="apm"
SRC="${STAFF_APM_LEGACY_ROOT:-${REPO_ROOT}/apm/storage/app/public}"
DEST="${STAFF_APM_FILES_ROOT:-${STAFF_DATA_ROOT}/apm}"

migrate_copy "$MODULE" "$SRC" "$DEST"
if [[ "${VERIFY:-true}" == "true" && "${DRY_RUN:-false}" != "true" ]]; then
  verify_sizes "$SRC" "$DEST"
fi

log "Set in apm/.env:"
log "  STAFF_DATA_ROOT=${STAFF_DATA_ROOT}"
log "  STAFF_APM_FILES_ROOT=${DEST}"
log "Then: cd ${REPO_ROOT}/apm && php artisan storage:link"
