#!/usr/bin/env bash
# Ensure Staff Portal Laravel storage, bootstrap/cache, and host public disk are writable.
# Fixes "bootstrap/cache must be present and writable" and broken public/storage links.
#
# Usage (from staff-portal/):
#   ./fix-storage-permissions.sh
#   STAFF_PORTAL_USER=www-data STAFF_PORTAL_GROUP=www-data ./fix-storage-permissions.sh
#
# Called by setup.sh / setup-production.sh.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND="$ROOT/backend"
STAFF_ROOT="$(cd "$ROOT/.." && pwd)"
cd "$BACKEND"

# shellcheck source=scripts/lib/dotenv.sh
source "$ROOT/scripts/lib/dotenv.sh"

WEB_GROUP="_www"
WEB_USER=""
if [[ "$(uname -s)" != "Darwin" ]]; then
  WEB_GROUP="www-data"
  WEB_USER="www-data"
fi

WEB_GROUP="${STAFF_PORTAL_GROUP:-${WEB_GROUP}}"
WEB_USER="${STAFF_PORTAL_USER:-${WEB_USER}}"

DEPLOY_USER="$(whoami)"
if [[ "$(id -u)" -eq 0 && -n "$WEB_USER" ]]; then
  DEPLOY_USER="$WEB_USER"
fi

BACKEND_ENV="$BACKEND/.env"
SETUP_ENV="${STAFF_PORTAL_SETUP_ENV:-$ROOT/setup.env}"

env_get() {
  local key="$1" val=""
  if [[ -f "$BACKEND_ENV" ]]; then
    val="$(dotenv_get "$BACKEND_ENV" "$key" 2>/dev/null || true)"
  fi
  if [[ -z "$val" && -f "$SETUP_ENV" ]]; then
    val="$(dotenv_get "$SETUP_ENV" "$key" 2>/dev/null || true)"
  fi
  if [[ -z "$val" && -f "$STAFF_ROOT/.env" ]]; then
    val="$(dotenv_get "$STAFF_ROOT/.env" "$key" 2>/dev/null || true)"
  fi
  printf '%s' "$val"
}

resolve_public_root() {
  local explicit data_root use_host resolved
  explicit="$(env_get STAFF_PORTAL_MODULE_FILES_ROOT)"
  if [[ -n "$explicit" ]]; then
    resolved="$(cd "${explicit%/}" 2>/dev/null && pwd -P || printf '%s' "${explicit%/}")"
    printf '%s' "$resolved"
    return 0
  fi
  data_root="$(env_get STAFF_DATA_ROOT)"
  if [[ -n "$data_root" ]]; then
    resolved="$(mkdir -p "${data_root%/}/staff-portal" 2>/dev/null; cd "${data_root%/}/staff-portal" 2>/dev/null && pwd -P || printf '%s' "${data_root%/}/staff-portal")"
    printf '%s' "$resolved"
    return 0
  fi
  use_host="$(env_get STAFF_USE_HOST_STORAGE | tr '[:upper:]' '[:lower:]')"
  if [[ "$use_host" == "true" || "$use_host" == "1" || "$use_host" == "yes" ]]; then
    local site host_base
    site="$(env_get STAFF_SITE_ID)"
    host_base="$(env_get STAFF_HOST_DATA_ROOT)"
    [[ -n "$host_base" ]] || host_base="/var/staffdata"
    [[ -n "$site" ]] || site="localhost-staff"
    resolved="$(mkdir -p "${host_base%/}/${site}/staff-portal" 2>/dev/null; cd "${host_base%/}/${site}/staff-portal" 2>/dev/null && pwd -P || printf '%s' "${host_base%/}/${site}/staff-portal")"
    printf '%s' "$resolved"
    return 0
  fi
  resolved="$(cd "$BACKEND/storage/app/public" 2>/dev/null && pwd -P || printf '%s' "$BACKEND/storage/app/public")"
  printf '%s' "$resolved"
}

canonical_path() {
  local path="$1"
  mkdir -p "$path" 2>/dev/null || true
  (cd "$path" 2>/dev/null && pwd -P) || printf '%s' "$path"
}

run_as_priv() {
  if "$@" 2>/dev/null; then
    return 0
  fi
  if [[ "$(id -u)" -eq 0 ]]; then
    "$@"
    return $?
  fi
  if command -v sudo >/dev/null 2>&1; then
    if sudo -n "$@" 2>/dev/null; then
      return 0
    fi
    sudo "$@" 2>/dev/null || return 1
  fi
  return 1
}

ensure_laravel_dirs() {
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
    mkdir -p "$d"
  done
}

ensure_public_disk_dirs() {
  local root="$1"
  mkdir -p "$root" 2>/dev/null || run_as_priv mkdir -p "$root"
}

ensure_storage_link() {
  local public_root="$1"
  local link="$BACKEND/public/storage"
  local php_bin="${PHP_BIN:-php}"
  local want resolved target need_recreate=0

  want="$(canonical_path "$public_root")"
  mkdir -p "$(dirname "$link")"

  if [[ -L "$link" ]]; then
    target="$(readlink "$link" || true)"
    if [[ ! -e "$link" ]]; then
      echo "Removing broken public/storage → ${target}"
      need_recreate=1
    else
      resolved="$(canonical_path "$target")"
      if [[ "$resolved" != "$want" ]]; then
        echo "public/storage points at ${resolved}, expected ${want} — recreating link"
        need_recreate=1
      fi
    fi
  elif [[ -d "$link" && ! -L "$link" ]]; then
    echo "public/storage is a real directory (not a symlink) — moving aside"
    local bak="${link}.bak.$(date +%Y%m%d%H%M%S)"
    mv "$link" "$bak" 2>/dev/null || run_as_priv mv "$link" "$bak"
    need_recreate=1
  elif [[ -e "$link" && ! -L "$link" ]]; then
    rm -f "$link" 2>/dev/null || run_as_priv rm -f "$link"
    need_recreate=1
  elif [[ ! -e "$link" ]]; then
    need_recreate=1
  fi

  if [[ "$need_recreate" -eq 1 ]]; then
    rm -f "$link" 2>/dev/null || run_as_priv rm -f "$link" || true
    if command -v "$php_bin" >/dev/null 2>&1 && [[ -f "$BACKEND/artisan" ]]; then
      (cd "$BACKEND" && "$php_bin" artisan config:clear --no-interaction) 2>/dev/null || true
      (cd "$BACKEND" && "$php_bin" artisan storage:link --force --no-interaction) 2>/dev/null \
        || (cd "$BACKEND" && "$php_bin" artisan storage:link --no-interaction) 2>/dev/null \
        || true
    fi
    if [[ -L "$link" ]]; then
      resolved="$(canonical_path "$(readlink "$link")")"
      if [[ "$resolved" != "$want" ]]; then
        rm -f "$link" 2>/dev/null || run_as_priv rm -f "$link" || true
      fi
    fi
    if [[ ! -L "$link" ]]; then
      ln -sfn "$want" "$link" 2>/dev/null || run_as_priv ln -sfn "$want" "$link"
    fi
  fi

  if [[ -L "$link" || -e "$link" ]]; then
    echo "public/storage → $(readlink "$link" 2>/dev/null || echo "$link")"
  else
    echo "warning: could not create public/storage link" >&2
    return 1
  fi
}

scrub_root_owned_cache() {
  local bad=0 dir f
  for dir in storage/framework/views bootstrap/cache; do
    [[ -d "$dir" ]] || continue
    while IFS= read -r -d '' f; do
      bad=1
      rm -f "$f" 2>/dev/null || run_as_priv rm -f "$f" 2>/dev/null || true
    done < <(find "$dir" -type f ! -user "$DEPLOY_USER" -print0 2>/dev/null || true)
  done
  if [[ "$bad" -eq 1 ]]; then
    echo "Removed compiled cache files not owned by ${DEPLOY_USER} (stale root/sudo cache)."
  fi
}

clear_config_cache() {
  local php_bin="${PHP_BIN:-php}"
  if command -v "$php_bin" >/dev/null 2>&1 && [[ -f "$BACKEND/artisan" ]]; then
    (cd "$BACKEND" && "$php_bin" artisan config:clear --no-interaction) 2>/dev/null || true
  fi
  # Stale config.php owned by _www blocks deploy-user artisan until ownership is fixed.
  if [[ -f bootstrap/cache/config.php ]] && [[ ! -w bootstrap/cache/config.php ]]; then
    run_as_priv rm -f bootstrap/cache/config.php 2>/dev/null || true
  fi
}

apply_ownership() {
  local public_root="$1"
  local targets=("$BACKEND/storage" "$BACKEND/bootstrap/cache")
  if [[ -d "$public_root" ]]; then
    targets+=("$public_root")
  fi

  if run_as_priv chown -R "${DEPLOY_USER}:${WEB_GROUP}" "${targets[@]}" 2>/dev/null; then
    :
  else
    echo "warning: chown ${DEPLOY_USER}:${WEB_GROUP} failed — run with sudo if uploads fail" >&2
  fi

  run_as_priv chmod -R ug+rwX "${targets[@]}" 2>/dev/null || chmod -R ug+rwX "${targets[@]}" 2>/dev/null || true
  find "$BACKEND/storage" "$BACKEND/bootstrap/cache" -type d -exec chmod ug+rwx {} + 2>/dev/null || true
  if [[ -d "$public_root" ]]; then
    find "$public_root" -type d -exec chmod ug+rwx {} + 2>/dev/null || true
  fi
}

probe_writable() {
  local public_root="$1"
  local probe="$public_root/.staff_write_probe_$$"
  if touch "$probe" 2>/dev/null; then
    rm -f "$probe"
    echo "Write probe OK: ${public_root}"
    return 0
  fi
  if run_as_priv touch "$probe" 2>/dev/null; then
    run_as_priv rm -f "$probe"
    echo "Write probe OK (via sudo): ${public_root}"
    return 0
  fi
  echo "warning: cannot write to ${public_root} — uploads may fail until permissions are fixed" >&2
  return 1
}

fix_host_staffdata() {
  local use_host data_root script
  use_host="$(env_get STAFF_USE_HOST_STORAGE | tr '[:upper:]' '[:lower:]')"
  data_root="$(env_get STAFF_DATA_ROOT)"
  if [[ -z "$data_root" && "$use_host" != "true" && "$use_host" != "1" && "$use_host" != "yes" ]]; then
    return 0
  fi
  script="$STAFF_ROOT/scripts/storage/fix-staff-storage-permissions.sh"
  if [[ -x "$script" ]]; then
    echo "==> Host staffdata permissions (${STAFF_ROOT}/scripts/storage)"
    STAFF_STORAGE_GROUP="${WEB_GROUP}" bash "$script" || echo "warning: host staffdata permission fix failed" >&2
  fi
}

echo "==> Staff Portal storage fix (${DEPLOY_USER}:${WEB_GROUP})"
ensure_laravel_dirs
PUBLIC_ROOT="$(resolve_public_root)"
echo "    Public disk root: ${PUBLIC_ROOT}"
ensure_public_disk_dirs "$PUBLIC_ROOT"
apply_ownership "$PUBLIC_ROOT"
scrub_root_owned_cache
clear_config_cache
ensure_storage_link "$PUBLIC_ROOT" || true
apply_ownership "$PUBLIC_ROOT"
probe_writable "$PUBLIC_ROOT" || true
fix_host_staffdata
echo "Permissions OK: Laravel storage/, bootstrap/cache/, and public disk (${PUBLIC_ROOT})."
