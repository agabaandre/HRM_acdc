#!/usr/bin/env bash
# Migrate CodeIgniter uploads/ → /var/staffdata/{site-id}/ci/
set -euo pipefail
source "$(dirname "$0")/_common.sh"

MODULE="ci"
SRC="${STAFF_CI_LEGACY_ROOT:-${REPO_ROOT}/uploads}"
DEST="${STAFF_PORTAL_UPLOADS_ROOT:-${STAFF_DATA_ROOT}/ci}"

migrate_copy "$MODULE" "$SRC" "$DEST"
if [[ "${VERIFY:-true}" == "true" && "${DRY_RUN:-false}" != "true" ]]; then
  verify_sizes "$SRC" "$DEST"
fi

log "Set in staff .env:"
log "  STAFF_DATA_ROOT=${STAFF_DATA_ROOT}"
log "  STAFF_PORTAL_UPLOADS_ROOT=${DEST}"
log "Optional symlink: ln -sfn ${DEST} ${REPO_ROOT}/uploads"
