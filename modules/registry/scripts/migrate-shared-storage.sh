#!/usr/bin/env bash
# Apply STAFF_DATA_ROOT / module roots to staff ecosystem .env files and optionally migrate
# Laravel module uploads (APM, helpdesk, staff-portal) to host storage.
# CodeIgniter upload migration was removed — the portal no longer uses CI3.
# Opt-in: MIGRATE_SHARED_STORAGE=true ./scripts/migrate-shared-storage.sh
set -euo pipefail

PORTAL_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
STAFF_ROOT="$(cd "$PORTAL_ROOT/../.." && pwd)"
STORAGE_SCRIPTS="$STAFF_ROOT/scripts/storage"

# shellcheck source=lib/dotenv.sh
source "$PORTAL_ROOT/scripts/lib/dotenv.sh"

SETUP_ENV="${STAFF_PORTAL_SETUP_ENV:-$PORTAL_ROOT/setup.env}"
[[ -f "$SETUP_ENV" ]] && dotenv_load_file "$SETUP_ENV"

MODE="${MIGRATE_SHARED_STORAGE:-false}"
case "$MODE" in
  true|1|yes|YES) MODE=true ;;
  *) MODE=false ;;
esac

if [[ "$MODE" != "true" ]]; then
  exit 0
fi

BASE_URL="${BASE_URL:-http://localhost/staff/}"
export BASE_URL
STAFF_HOST_DATA_ROOT="${STAFF_HOST_DATA_ROOT:-/var/staffdata}"
if [[ -z "${STAFF_SITE_ID:-}" ]]; then
  STAFF_SITE_ID="$(
    BASE_URL="$BASE_URL" php -r '
      $u = trim(getenv("BASE_URL") ?: "http://localhost/staff");
      $p = parse_url($u);
      $host = strtolower(preg_replace("/^www\./", "", $p["host"] ?? "localhost"));
      $slug = implode("-", array_filter(explode(".", $host)));
      $path = trim($p["path"] ?? "", "/");
      if ($path !== "") {
        $pathSlug = trim(preg_replace("/[^a-z0-9]+/", "-", strtolower($path)), "-");
        if ($pathSlug !== "") $slug .= "-".$pathSlug;
      }
      echo preg_replace("/^-+|-+$/", "", $slug) ?: "localhost-staff";
    ' 2>/dev/null || echo "localhost-staff"
  )"
fi
STAFF_DATA_ROOT="${STAFF_DATA_ROOT:-${STAFF_HOST_DATA_ROOT}/${STAFF_SITE_ID}}"
export STAFF_DATA_ROOT STAFF_SITE_ID STAFF_HOST_DATA_ROOT
export STAFF_USE_HOST_STORAGE=true
export STAFF_PORTAL_UPLOADS_ROOT="${STAFF_PORTAL_UPLOADS_ROOT:-${STAFF_DATA_ROOT}/ci}"
export STAFF_APM_FILES_ROOT="${STAFF_APM_FILES_ROOT:-${STAFF_DATA_ROOT}/apm}"
export STAFF_HELPDESK_FILES_ROOT="${STAFF_HELPDESK_FILES_ROOT:-${STAFF_DATA_ROOT}/helpdesk}"
export STAFF_PORTAL_MODULE_FILES_ROOT="${STAFF_PORTAL_MODULE_FILES_ROOT:-${STAFF_DATA_ROOT}/staff-portal}"

apply_storage_keys() {
  local env_file="$1"
  [[ -f "$env_file" ]] || return 0
  dotenv_set "$env_file" STAFF_DATA_ROOT "$STAFF_DATA_ROOT"
  dotenv_set "$env_file" STAFF_USE_HOST_STORAGE "true"
  dotenv_set "$env_file" STAFF_SITE_ID "$STAFF_SITE_ID"
  shift
  local key
  for key in "$@"; do
    local val="${!key:-}"
    [[ -n "$val" ]] && dotenv_set "$env_file" "$key" "$val"
  done
  echo "[shared-storage] Updated $env_file"
}

echo "==> Shared storage → ${STAFF_DATA_ROOT}"
chmod +x "$STORAGE_SCRIPTS"/*.sh 2>/dev/null || true

bash "$STORAGE_SCRIPTS/fix-staff-storage-permissions.sh"

bash "$STORAGE_SCRIPTS/migrate-apm-uploads.sh"

if [[ "${MIGRATE_HELPDESK_STORAGE:-true}" == "true" ]]; then
  bash "$STORAGE_SCRIPTS/migrate-helpdesk-uploads.sh" || true
fi
if [[ "${MIGRATE_STAFF_PORTAL_STORAGE:-true}" == "true" ]]; then
  bash "$STORAGE_SCRIPTS/migrate-staff-portal-uploads.sh" || true
fi

apply_storage_keys "$STAFF_ROOT/.env" STAFF_PORTAL_UPLOADS_ROOT
apply_storage_keys "$PORTAL_ROOT/backend/.env" STAFF_PORTAL_UPLOADS_ROOT STAFF_PORTAL_MODULE_FILES_ROOT
apply_storage_keys "$STAFF_ROOT/modules/apm/.env" STAFF_APM_FILES_ROOT STAFF_PORTAL_UPLOADS_ROOT
if [[ -f "$STAFF_ROOT/modules/helpdesk/backend/.env" ]]; then
  apply_storage_keys "$STAFF_ROOT/modules/helpdesk/backend/.env" STAFF_HELPDESK_FILES_ROOT STAFF_PORTAL_UPLOADS_ROOT
fi

if [[ -f "$STAFF_ROOT/modules/apm/artisan" ]]; then
  (cd "$STAFF_ROOT/modules/apm" && php artisan storage:unlink --no-interaction 2>/dev/null) || true
  rm -f "$STAFF_ROOT/modules/apm/public/storage" 2>/dev/null || true
  (cd "$STAFF_ROOT/modules/apm" && php artisan storage:link --force --no-interaction 2>/dev/null) || true
fi
if [[ -f "$PORTAL_ROOT/backend/artisan" ]]; then
  (cd "$PORTAL_ROOT/backend" && php artisan storage:unlink --no-interaction 2>/dev/null) || true
  rm -f "$PORTAL_ROOT/backend/public/storage" 2>/dev/null || true
  (cd "$PORTAL_ROOT/backend" && php artisan storage:link --force --no-interaction 2>/dev/null) || true
fi

echo "[shared-storage] Done. Files are managed under ${STAFF_DATA_ROOT} (outside the git tree)."
