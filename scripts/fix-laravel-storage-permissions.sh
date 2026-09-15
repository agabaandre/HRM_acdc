#!/usr/bin/env bash
# Fix Laravel storage + bootstrap/cache write access for every app under the checkout.
# Always unlinks and recreates public/storage (storage:link).
#
# Usage (from repo root):
#   ./scripts/fix-laravel-storage-permissions.sh
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

OWNER="${OWNER:-${SUDO_USER:-${USER:-$(id -un)}}}"
if [[ -z "$OWNER" || "$OWNER" == "root" ]]; then
  OWNER="$(id -un)"
  [[ "$(id -u)" -eq 0 && -n "${SUDO_USER:-}" && "$SUDO_USER" != "root" ]] && OWNER="$SUDO_USER"
fi

if [[ "$(uname -s)" == "Darwin" ]]; then
  WEB_GROUP="${LARAVEL_WEB_GROUP:-${WEB_GROUP:-_www}}"
else
  WEB_GROUP="${LARAVEL_WEB_GROUP:-${WEB_GROUP:-www-data}}"
fi

APPS=(
  "modules/apm"
  "modules/finance"
  "modules/helpdesk/backend"
  "modules/staff-portal/backend"
)

run_priv() {
  if "$@" 2>/dev/null; then
    return 0
  fi
  if [[ "$(id -u)" -eq 0 ]]; then
    "$@"
    return $?
  fi
  if command -v sudo >/dev/null 2>&1; then
    sudo -n "$@" 2>/dev/null && return 0
    sudo "$@" 2>/dev/null && return 0
  fi
  return 1
}

ensure_dirs() {
  local base="$1"
  local d
  for d in \
    storage \
    storage/app \
    storage/app/public \
    storage/app/private \
    storage/framework \
    storage/framework/cache \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    public; do
    mkdir -p "${base}/${d}" 2>/dev/null || run_priv mkdir -p "${base}/${d}" || true
  done
}

relink_storage() {
  local base="$1"
  local php_bin="${PHP_BIN:-php}"
  local link="${base}/public/storage"

  mkdir -p "${base}/public" "${base}/storage/app/public" 2>/dev/null || true

  if command -v "$php_bin" >/dev/null 2>&1 && [[ -f "${base}/artisan" ]]; then
    (cd "$base" && "$php_bin" artisan storage:unlink --no-interaction) >/dev/null 2>&1 || true
  fi

  if [[ -L "$link" ]]; then
    rm -f "$link" 2>/dev/null || run_priv rm -f "$link" || true
  elif [[ -d "$link" && ! -L "$link" ]]; then
    local bak="${link}.bak.$(date +%Y%m%d%H%M%S)"
    echo "    public/storage is a directory — moving to ${bak}"
    mv "$link" "$bak" 2>/dev/null || run_priv mv "$link" "$bak" || true
  elif [[ -e "$link" ]]; then
    rm -f "$link" 2>/dev/null || run_priv rm -f "$link" || true
  fi

  if command -v "$php_bin" >/dev/null 2>&1 && [[ -f "${base}/artisan" ]]; then
    (cd "$base" && "$php_bin" artisan storage:link --force --no-interaction) >/dev/null 2>&1 || true
    (cd "$base" && "$php_bin" artisan storage:link --no-interaction) >/dev/null 2>&1 || true
  fi

  if [[ ! -e "$link" && ! -L "$link" ]]; then
    ln -sfn "${base}/storage/app/public" "$link" 2>/dev/null \
      || run_priv ln -sfn "${base}/storage/app/public" "$link" || true
  fi

  if [[ -L "$link" ]]; then
    echo "    public/storage → $(readlink "$link")"
  elif [[ -e "$link" ]]; then
    echo "    public/storage exists ($(file -b "$link" 2>/dev/null || echo file))"
  else
    echo "    warning: could not create public/storage" >&2
    return 1
  fi
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

  find "${base}/storage/framework/views" -type f -name '*.php' -delete 2>/dev/null || true
  find "${base}/storage/framework/cache" -type f ! -name '.gitignore' -delete 2>/dev/null || true
  find "${base}/bootstrap/cache" -type f \( -name 'routes*.php' -o -name 'config.php' -o -name 'services.php' -o -name 'packages.php' \) -delete 2>/dev/null || true

  if command -v chmod >/dev/null 2>&1; then
    chmod -RN "${base}/storage" "${base}/bootstrap/cache" 2>/dev/null || true
  fi

  local targets=("${base}/storage" "${base}/bootstrap/cache" "${base}/public")
  if [[ -d "${base}/database" ]]; then
    targets+=("${base}/database")
  fi

  if run_priv chown -R "${OWNER}:${WEB_GROUP}" "${targets[@]}"; then
    :
  else
    echo "    warning: chown ${OWNER}:${WEB_GROUP} failed — run with sudo if Apache cannot write" >&2
  fi

  run_priv chmod -R ug+rwX,o+rX "${base}/storage" "${base}/bootstrap/cache" \
    || chmod -R ug+rwX,o+rX "${base}/storage" "${base}/bootstrap/cache" 2>/dev/null || true
  run_priv chmod -R a+rX "${base}/public" || chmod -R a+rX "${base}/public" 2>/dev/null || true
  find "${base}/storage" "${base}/bootstrap/cache" -type d -exec chmod ug+rwx,g+s {} + 2>/dev/null || true

  if [[ -f "${base}/database/database.sqlite" ]]; then
    run_priv chown "${OWNER}:${WEB_GROUP}" "${base}/database/database.sqlite" || true
    run_priv chmod 664 "${base}/database/database.sqlite" || true
  fi

  if [[ "$(uname -s)" == "Darwin" ]] && command -v chmod >/dev/null 2>&1; then
    chmod -R +a "group:${WEB_GROUP} allow list,add_file,search,add_subdirectory,delete_child,read,write,execute,delete,append" \
      "${base}/storage" "${base}/bootstrap/cache" 2>/dev/null || true
    chmod -R +a "user:${OWNER} allow list,add_file,search,add_subdirectory,delete_child,read,write,execute,delete,append" \
      "${base}/storage" "${base}/bootstrap/cache" 2>/dev/null || true
  fi

  relink_storage "$base" || true
  echo "    ok (${OWNER}:${WEB_GROUP})"
}

echo "Fixing Laravel writable dirs + storage:link under ${ROOT}"
echo "Owner/group: ${OWNER}:${WEB_GROUP}"
echo

for rel in "${APPS[@]}"; do
  fix_app "$rel"
done

echo
echo "Done. Laravel storage/, bootstrap/cache/, and public/storage relinked."
