#!/usr/bin/env bash
# Safely remove legacy CodeIgniter uploads/ after a verified host-storage migration.
#
# Requirements:
#   - Host copy exists under STAFF_DATA_ROOT/ci (or STAFF_PORTAL_UPLOADS_ROOT)
#   - Byte-size verify against legacy uploads/ succeeds
#   - CONFIRM=DELETE_CI_UPLOADS
#
# Usage:
#   CONFIRM=DELETE_CI_UPLOADS ./scripts/storage/purge-ci-uploads.sh
#   DRY_RUN=true CONFIRM=DELETE_CI_UPLOADS ./scripts/storage/purge-ci-uploads.sh
set -euo pipefail
source "$(dirname "$0")/_common.sh"

MODULE="ci"
SRC="${STAFF_CI_LEGACY_ROOT:-${REPO_ROOT}/uploads}"
DEST="${STAFF_PORTAL_UPLOADS_ROOT:-${STAFF_DATA_ROOT}/ci}"

if [[ "${CONFIRM:-}" != "DELETE_CI_UPLOADS" ]]; then
  die "Refusing to purge. Re-run with CONFIRM=DELETE_CI_UPLOADS after verifying host storage."
fi

if [[ ! -d "$SRC" ]]; then
  log "Nothing to purge: legacy path missing (${SRC})"
  exit 0
fi

# Never delete a path that is already a symlink to host storage.
if [[ -L "$SRC" ]]; then
  target="$(readlink "$SRC" || true)"
  log "Legacy path is a symlink → ${target}. Leaving it in place (already using host storage)."
  exit 0
fi

if [[ ! -d "$DEST" ]]; then
  die "Host destination missing (${DEST}). Migrate first."
fi

# Refuse if destination is inside the source tree (would wipe host copy).
src_real="$(cd "$SRC" && pwd -P)"
dest_real="$(cd "$DEST" && pwd -P)"
case "$dest_real" in
  "$src_real"|"$src_real"/*)
    die "Host destination is inside legacy uploads — aborting purge."
    ;;
esac

src_n="$(find "$SRC" -type f 2>/dev/null | wc -l | tr -d ' ')"
dest_n="$(find "$DEST" -type f 2>/dev/null | wc -l | tr -d ' ')"
if [[ "$src_n" -gt 0 && "$dest_n" -lt "$src_n" ]]; then
  die "Host has fewer files (${dest_n}) than legacy (${src_n}). Migrate/verify before purge."
fi

if [[ "${SKIP_VERIFY:-false}" != "true" ]]; then
  verify_sizes "$SRC" "$DEST"
fi

STAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP="${SRC}.purged-${STAMP}"

log "Purge plan: move ${SRC} → ${BACKUP}, then symlink ${DEST} as uploads/"
if [[ "${DRY_RUN:-false}" == "true" ]]; then
  log "DRY_RUN: would mv ${SRC} ${BACKUP}"
  log "DRY_RUN: would ln -sfn ${DEST} ${SRC}"
  exit 0
fi

mv "$SRC" "$BACKUP"
ln -sfn "$DEST" "$SRC"
log "Legacy uploads archived at ${BACKUP}"
log "Symlinked ${SRC} → ${DEST}"
log "Optional permanent delete after soak: rm -rf ${BACKUP}"
