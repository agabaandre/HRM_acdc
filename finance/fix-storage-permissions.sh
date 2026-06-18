#!/usr/bin/env bash
# Ensure Laravel storage + bootstrap/cache are writable by the deploy user and web server.
# Fixes "rename(...storage/framework/views/...): Permission denied" after sudo setup or view:cache.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

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
  local targets=(storage bootstrap/cache database)
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
  echo "Need sudo to set ownership. Run:"
  echo "  sudo chown -R ${DEPLOY_USER}:${WEB_GROUP} storage bootstrap/cache database"
  if [[ -f database/database.sqlite ]]; then
    echo "  sudo chown ${DEPLOY_USER}:${WEB_GROUP} database/database.sqlite"
  fi
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
    echo "warning: could not chmod ${targets[*]} — run: sudo chmod -R ug+rwx storage bootstrap/cache" >&2
    return 1
  fi
  chmod 775 database 2>/dev/null || true
  if [[ -f database/database.sqlite ]]; then
    chmod 664 database/database.sqlite 2>/dev/null || true
  fi
  # Compiled Blade views: web server must create/rename *.php temp files here.
  chmod ug+rwx storage/framework/views 2>/dev/null || true
  if command -v sudo >/dev/null 2>&1; then
    sudo chmod ug+rwx storage/framework/views 2>/dev/null || true
  fi
}

# Drop root-owned compiled views from a previous sudo artisan view:cache.
scrub_root_owned_views() {
  local bad=0
  if [[ -d storage/framework/views ]]; then
    while IFS= read -r -d '' f; do
      bad=1
      rm -f "$f" 2>/dev/null || sudo rm -f "$f" 2>/dev/null || true
    done < <(find storage/framework/views -type f ! -user "$DEPLOY_USER" -print0 2>/dev/null || true)
  fi
  if [[ "$bad" -eq 1 ]]; then
    echo "Removed compiled views not owned by ${DEPLOY_USER} (stale root/sudo cache)."
  fi
}

ensure_dirs
scrub_root_owned_views
run_chown
run_chmod
echo "Permissions OK (${DEPLOY_USER}:${WEB_GROUP}): storage/, bootstrap/cache/, database/ (+ database.sqlite)."
