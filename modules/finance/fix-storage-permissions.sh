#!/usr/bin/env bash
# Ensure Laravel storage + bootstrap/cache are writable (module root + backend API).
# Fixes "rename(...storage/framework/views/...): Permission denied" after sudo setup or view:cache.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

WEB_GROUP="_www"
WEB_USER=""
if [[ "$(uname -s)" != "Darwin" ]]; then
  WEB_GROUP="www-data"
  WEB_USER="www-data"
fi

# setup-production.sh / setup.env may override (Linux production).
WEB_GROUP="${FINANCE_GROUP:-${WEB_GROUP}}"
WEB_USER="${FINANCE_USER:-${WEB_USER}}"

DEPLOY_USER="$(whoami)"
if [[ "$(id -u)" -eq 0 && -n "$WEB_USER" ]]; then
  DEPLOY_USER="$WEB_USER"
fi

fix_app_storage() {
  local APP_ROOT="$1"
  [[ -d "$APP_ROOT" ]] || return 0
  cd "$APP_ROOT"

  ensure_dirs() {
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
      mkdir -p "$d"
    done
  }

  run_chown() {
    local targets=(storage bootstrap/cache)
    [[ -d database ]] && targets+=(database)
    if [[ -f database/database.sqlite ]]; then
      targets+=(database/database.sqlite)
    fi
    if chown -R "${DEPLOY_USER}:${WEB_GROUP}" "${targets[@]}" 2>/dev/null; then
      return 0
    fi
    if [[ -n "${FINANCE_SETUP_SUDO_PASSWORD:-}" ]]; then
      echo "${FINANCE_SETUP_SUDO_PASSWORD}" | sudo -S chown -R "${DEPLOY_USER}:${WEB_GROUP}" "${targets[@]}"
      return 0
    fi
    if sudo -n chown -R "${DEPLOY_USER}:${WEB_GROUP}" "${targets[@]}" 2>/dev/null; then
      return 0
    fi
    echo "Need sudo to set ownership in $APP_ROOT. Run:"
    echo "  sudo chown -R ${DEPLOY_USER}:${WEB_GROUP} storage bootstrap/cache"
    return 1
  }

  run_chmod() {
    local targets=(storage bootstrap/cache)
    if chmod -R ug+rwx "${targets[@]}" 2>/dev/null; then
      :
    elif [[ -n "${FINANCE_SETUP_SUDO_PASSWORD:-}" ]]; then
      echo "${FINANCE_SETUP_SUDO_PASSWORD}" | sudo -S chmod -R ug+rwx "${targets[@]}"
    elif sudo -n chmod -R ug+rwx "${targets[@]}" 2>/dev/null; then
      :
    else
      echo "warning: could not chmod ${targets[*]} in $APP_ROOT" >&2
      return 1
    fi
    chmod 775 database 2>/dev/null || true
    if [[ -f database/database.sqlite ]]; then
      chmod 664 database/database.sqlite 2>/dev/null || true
    fi
    chmod ug+rwx storage/framework/views 2>/dev/null || true
    if command -v sudo >/dev/null 2>&1; then
      sudo -n chmod ug+rwx storage/framework/views 2>/dev/null || true
    fi
  }

  scrub_root_owned_views() {
    local bad=0
    if [[ -d storage/framework/views ]]; then
      while IFS= read -r -d '' f; do
        bad=1
        if ! rm -f "$f" 2>/dev/null; then
          if command -v sudo >/dev/null 2>&1; then
            sudo -n rm -f "$f" 2>/dev/null || true
          fi
        fi
      done < <(find storage/framework/views -type f ! -user "$DEPLOY_USER" -print0 2>/dev/null || true)
    fi
    if [[ "$bad" -eq 1 ]]; then
      echo "Removed compiled views not owned by ${DEPLOY_USER} under $APP_ROOT"
    fi
  }

  ensure_dirs
  scrub_root_owned_views
  run_chown || true
  run_chmod || true

  if [[ -f artisan ]] && command -v php >/dev/null 2>&1; then
    php artisan storage:unlink --no-interaction >/dev/null 2>&1 || true
    rm -f public/storage 2>/dev/null || true
    if [[ -d public/storage && ! -L public/storage ]]; then
      mv public/storage "public/storage.bak.$(date +%Y%m%d%H%M%S)" 2>/dev/null || true
    fi
    php artisan storage:link --force --no-interaction >/dev/null 2>&1 \
      || php artisan storage:link --no-interaction >/dev/null 2>&1 \
      || ln -sfn "$APP_ROOT/storage/app/public" public/storage 2>/dev/null \
      || true
    if [[ -L public/storage ]]; then
      echo "  public/storage → $(readlink public/storage) ($APP_ROOT)"
    fi
  fi

  echo "Permissions OK (${DEPLOY_USER}:${WEB_GROUP}): $APP_ROOT storage/, bootstrap/cache/"
}

fix_app_storage "$ROOT"
fix_app_storage "$ROOT/backend"
