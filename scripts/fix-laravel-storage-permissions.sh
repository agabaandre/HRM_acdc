#!/usr/bin/env bash
# Fix Laravel storage + bootstrap/cache write access for every app under staff/.
# Targets macOS Homebrew Apache (_www) and Linux www-data.
#
# Usage (from repo root):
#   ./scripts/fix-laravel-storage-permissions.sh
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

OWNER="${SUDO_USER:-${USER:-$(id -un)}}"
if [[ -z "$OWNER" || "$OWNER" == "root" ]]; then
  OWNER="$(id -un)"
fi

if [[ "$(uname -s)" == "Darwin" ]]; then
  WEB_GROUP="${LARAVEL_WEB_GROUP:-_www}"
else
  WEB_GROUP="${LARAVEL_WEB_GROUP:-www-data}"
fi

APPS=(
  "apm"
  "finance"
  "helpdesk/backend"
  "staff-portal/backend"
)

ensure_dirs() {
  local base="$1"
  local d
  for d in \
    storage \
    storage/app \
    storage/app/public \
    storage/framework \
    storage/framework/cache \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache; do
    mkdir -p "${base}/${d}"
  done
}

fix_app() {
  local rel="$1"
  local base="${ROOT}/${rel}"

  if [[ ! -f "${base}/artisan" ]]; then
    echo "skip (no artisan): ${rel}"
    return 0
  fi

  echo "==> ${rel}"
  ensure_dirs "$base"

  # Drop compiled views/caches so they regenerate with correct ownership.
  find "${base}/storage/framework/views" -type f -name '*.php' -delete 2>/dev/null || true
  find "${base}/storage/framework/cache" -type f ! -name '.gitignore' -delete 2>/dev/null || true
  find "${base}/bootstrap/cache" -type f \( -name 'routes*.php' -o -name 'config.php' -o -name 'services.php' -o -name 'packages.php' \) -delete 2>/dev/null || true

  # Strip inherited ACLs that lock files to the deploy user only (macOS).
  if command -v chmod >/dev/null 2>&1; then
    chmod -RN "${base}/storage" "${base}/bootstrap/cache" 2>/dev/null || true
  fi

  # Owner can CLI; web group can write via Apache/php-fpm.
  chown -R "${OWNER}:${WEB_GROUP}" "${base}/storage" "${base}/bootstrap/cache"
  chmod -R ug+rwX,o+rX "${base}/storage" "${base}/bootstrap/cache"

  # New files inherit the web group.
  find "${base}/storage" "${base}/bootstrap/cache" -type d -exec chmod g+s {} +

  # Explicit ACL so both deploy user and web group can read/write (macOS).
  if [[ "$(uname -s)" == "Darwin" ]] && command -v chmod >/dev/null 2>&1; then
    chmod -R +a "group:${WEB_GROUP} allow list,add_file,search,add_subdirectory,delete_child,read,write,execute,delete,append" \
      "${base}/storage" "${base}/bootstrap/cache" 2>/dev/null || true
    chmod -R +a "user:${OWNER} allow list,add_file,search,add_subdirectory,delete_child,read,write,execute,delete,append" \
      "${base}/storage" "${base}/bootstrap/cache" 2>/dev/null || true
  fi

  # Keep placeholder gitignore files if present.
  for f in \
    storage/app/.gitignore \
    storage/framework/.gitignore \
    storage/framework/cache/.gitignore \
    storage/framework/sessions/.gitignore \
    storage/framework/testing/.gitignore \
    storage/framework/views/.gitignore \
    storage/logs/.gitignore \
    bootstrap/cache/.gitignore; do
    [[ -f "${base}/${f}" ]] || continue
    chown "${OWNER}:${WEB_GROUP}" "${base}/${f}" 2>/dev/null || true
  done

  echo "    ok (${OWNER}:${WEB_GROUP})"
}

echo "Fixing Laravel writable dirs under ${ROOT}"
echo "Owner/group: ${OWNER}:${WEB_GROUP}"
echo

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Elevating with sudo for chown/chmod…"
  exec sudo -E OWNER="$OWNER" WEB_GROUP="$WEB_GROUP" bash "$0"
fi

for rel in "${APPS[@]}"; do
  fix_app "$rel"
done

echo
echo "Done. Hard-refresh the app (or php artisan view:clear in each app)."
