#!/usr/bin/env bash
# Ensure Helpdesk Laravel storage + public-disk uploads are writable.
# Fixes missing dirs, broken public/storage links, and host STAFF_HELPDESK_FILES_ROOT.
#
# Usage (from helpdesk/):
#   ./fix-storage-permissions.sh
#   HELPDESK_USER=www-data HELPDESK_GROUP=www-data ./fix-storage-permissions.sh
#
# Called by setup-production.sh / setup.sh.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND="$ROOT/backend"
cd "$BACKEND"

# shellcheck source=scripts/lib/dotenv.sh
source "$ROOT/scripts/lib/dotenv.sh"

WEB_GROUP="_www"
WEB_USER=""
if [[ "$(uname -s)" != "Darwin" ]]; then
  WEB_GROUP="www-data"
  WEB_USER="www-data"
fi

WEB_GROUP="${HELPDESK_GROUP:-${WEB_GROUP}}"
WEB_USER="${HELPDESK_USER:-${WEB_USER}}"

DEPLOY_USER="$(whoami)"
if [[ "$(id -u)" -eq 0 && -n "$WEB_USER" ]]; then
  DEPLOY_USER="$WEB_USER"
fi

BACKEND_ENV="$BACKEND/.env"
SETUP_ENV="${HELPDESK_SETUP_ENV:-$ROOT/setup.env}"

env_get() {
  local key="$1" val=""
  if [[ -f "$BACKEND_ENV" ]]; then
    val="$(dotenv_get "$BACKEND_ENV" "$key" 2>/dev/null || true)"
  fi
  if [[ -z "$val" && -f "$SETUP_ENV" ]]; then
    val="$(dotenv_get "$SETUP_ENV" "$key" 2>/dev/null || true)"
  fi
  # Parent staff .env (shared host storage)
  if [[ -z "$val" && -f "$ROOT/../.env" ]]; then
    val="$(dotenv_get "$ROOT/../.env" "$key" 2>/dev/null || true)"
  fi
  printf '%s' "$val"
}

# Resolve Laravel public disk root (must match Staff\Shared\StaffStorage::helpdeskPublicRoot).
resolve_public_root() {
  local explicit data_root use_host
  explicit="$(env_get STAFF_HELPDESK_FILES_ROOT)"
  if [[ -n "$explicit" ]]; then
    printf '%s' "${explicit%/}"
    return 0
  fi
  data_root="$(env_get STAFF_DATA_ROOT)"
  if [[ -n "$data_root" ]]; then
    printf '%s' "${data_root%/}/helpdesk"
    return 0
  fi
  use_host="$(env_get STAFF_USE_HOST_STORAGE | tr '[:upper:]' '[:lower:]')"
  if [[ "$use_host" == "true" || "$use_host" == "1" || "$use_host" == "yes" ]]; then
    local site host_base
    site="$(env_get STAFF_SITE_ID)"
    host_base="$(env_get STAFF_HOST_DATA_ROOT)"
    [[ -n "$host_base" ]] || host_base="/var/staffdata"
    if [[ -z "$site" ]]; then
      site="default"
    fi
    printf '%s' "${host_base%/}/${site}/helpdesk"
    return 0
  fi
  printf '%s' "$BACKEND/storage/app/public"
}

run_as_priv() {
  # Prefer running as current user when possible (avoids sudo password prompts).
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
    # Last resort — may prompt; callers should tolerate failure.
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
  mkdir -p \
    "$root" \
    "$root/helpdesk" \
    "$root/helpdesk/rich-text" \
    "$root/helpdesk/agent-reports" \
    2>/dev/null || run_as_priv mkdir -p \
      "$root" \
      "$root/helpdesk" \
      "$root/helpdesk/rich-text" \
      "$root/helpdesk/agent-reports"
}

# public/storage must be a symlink to the public disk root (storage:link).
ensure_storage_link() {
  local public_root="$1"
  local link="$BACKEND/public/storage"
  local php_bin="${PHP_BIN:-php}"
  local need_recreate=0

  if [[ -L "$link" ]]; then
    local target resolved want
    target="$(readlink "$link" || true)"
    if [[ ! -e "$link" ]]; then
      echo "Removing broken public/storage → ${target}"
      need_recreate=1
    else
      resolved="$(cd "$(dirname "$link")" && cd "$target" 2>/dev/null && pwd -P || true)"
      want="$(mkdir -p "$public_root" 2>/dev/null; cd "$public_root" 2>/dev/null && pwd -P || printf '%s' "$public_root")"
      if [[ -n "$resolved" && -n "$want" && "$resolved" != "$want" ]]; then
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
    # Prefer artisan so links[] from filesystems.php is honored.
    if command -v "$php_bin" >/dev/null 2>&1 && [[ -f "$BACKEND/artisan" ]]; then
      (cd "$BACKEND" && "$php_bin" artisan storage:link --force --no-interaction) 2>/dev/null \
        || (cd "$BACKEND" && "$php_bin" artisan storage:link --no-interaction) 2>/dev/null \
        || true
    fi
    if [[ ! -e "$link" ]]; then
      ln -sfn "$public_root" "$link" 2>/dev/null || run_as_priv ln -sfn "$public_root" "$link"
    fi
  fi

  if [[ -L "$link" || -e "$link" ]]; then
    echo "public/storage → $(readlink "$link" 2>/dev/null || echo "$link")"
  else
    echo "warning: could not create public/storage link" >&2
    return 1
  fi
}

apply_ownership() {
  local public_root="$1"
  local targets=("$BACKEND/storage" "$BACKEND/bootstrap/cache")
  if [[ -d "$public_root" ]]; then
    targets+=("$public_root")
  fi

  if [[ -n "$WEB_USER" ]]; then
    if run_as_priv chown -R "${DEPLOY_USER}:${WEB_GROUP}" "${targets[@]}" 2>/dev/null; then
      :
    else
      echo "warning: chown ${DEPLOY_USER}:${WEB_GROUP} failed for storage paths" >&2
    fi
  else
    # macOS: deploy user + _www group
    if run_as_priv chown -R "${DEPLOY_USER}:${WEB_GROUP}" "${targets[@]}" 2>/dev/null; then
      :
    else
      chmod -R ug+rwX "${targets[@]}" 2>/dev/null || true
    fi
  fi

  run_as_priv chmod -R ug+rwX "${targets[@]}" 2>/dev/null || chmod -R ug+rwX "${targets[@]}" 2>/dev/null || true
  # Ensure group can create files under upload roots
  find "$BACKEND/storage" "$BACKEND/bootstrap/cache" -type d -exec chmod ug+rwx {} + 2>/dev/null || true
  if [[ -d "$public_root" ]]; then
    find "$public_root" -type d -exec chmod ug+rwx {} + 2>/dev/null || true
  fi
}

# Probe: can the web user write a temp file into the public disk?
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
  echo "warning: cannot write to ${public_root} — uploads will fail until permissions are fixed" >&2
  return 1
}

echo "==> Helpdesk storage fix (${DEPLOY_USER}:${WEB_GROUP})"
ensure_laravel_dirs
PUBLIC_ROOT="$(resolve_public_root)"
echo "    Public disk root: ${PUBLIC_ROOT}"
ensure_public_disk_dirs "$PUBLIC_ROOT"
ensure_storage_link "$PUBLIC_ROOT" || true
apply_ownership "$PUBLIC_ROOT"
probe_writable "$PUBLIC_ROOT" || true
echo "Permissions OK: Laravel storage/, bootstrap/cache/, and public disk (${PUBLIC_ROOT})."
