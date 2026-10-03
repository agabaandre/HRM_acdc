#!/usr/bin/env bash
# Fix Laravel storage + bootstrap/cache write access for every app under the checkout.
# Always unlinks and recreates public/storage (storage:link).
#
# Usage (from repo root):
#   ./scripts/fix-laravel-storage-permissions.sh
#   ./scripts/fix-laravel-storage-permissions.sh modules/apm
#
# Privilege elevation:
#   - Never prompts for a sudo password by default (sudo -n only).
#   - Set ALLOW_INTERACTIVE_SUDO=1 for a new install when interactive sudo is OK.
#   - Skips chown/sudo entirely when storage dirs are already writable.
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

DEFAULT_APPS=(
  "modules/apm"
  # Inertia finance app (module root) and Vue/API app under backend/ (SSO + SPA API).
  "modules/finance"
  "modules/finance/backend"
  "modules/helpdesk/backend"
  "modules/staff-portal/backend"
  "modules/risk-register/backend"
)

if [[ $# -gt 0 ]]; then
  APPS=("$@")
else
  APPS=("${DEFAULT_APPS[@]}")
fi

# Passwordless elevation only, unless ALLOW_INTERACTIVE_SUDO=1 (new install).
run_priv() {
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
    if [[ "${ALLOW_INTERACTIVE_SUDO:-0}" == "1" ]]; then
      sudo "$@" && return 0
    fi
  fi
  return 1
}

# True when the web/deploy user can already create files in key Laravel dirs.
writable_ok() {
  local base="$1" d probe
  for d in \
    storage/logs \
    bootstrap/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache; do
    [[ -d "${base}/${d}" ]] || return 1
    [[ -w "${base}/${d}" ]] || return 1
    probe="${base}/${d}/.cbp-perm-probe.$$"
    if ! printf 'ok\n' >"$probe" 2>/dev/null; then
      rm -f "$probe" 2>/dev/null || true
      return 1
    fi
    rm -f "$probe" 2>/dev/null || return 1
  done
  return 0
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

  if writable_ok "$base"; then
    echo "    permissions already OK — skip chown/sudo"
    chmod -R ug+rwX,o+rX "${base}/storage" "${base}/bootstrap/cache" 2>/dev/null || true
    if [[ "$(uname -s)" == "Darwin" ]]; then
      chmod -R a+rwX "${base}/storage" "${base}/bootstrap/cache" 2>/dev/null || true
    fi
    relink_storage "$base" || true
    echo "    ok (${OWNER}:${WEB_GROUP})"
    return 0
  fi

  local targets=("${base}/storage" "${base}/bootstrap/cache" "${base}/public")
  if [[ -d "${base}/database" ]]; then
    targets+=("${base}/database")
  fi

  if run_priv chown -R "${OWNER}:${WEB_GROUP}" "${targets[@]}"; then
    :
  else
    if [[ "${ALLOW_INTERACTIVE_SUDO:-0}" == "1" ]]; then
      echo "    warning: chown ${OWNER}:${WEB_GROUP} failed — run with sudo if Apache cannot write" >&2
    else
      echo "    warning: storage not fully writable and sudo unavailable (non-interactive)." >&2
      echo "             First install: re-run with sudo, or: sudo chown -R ${OWNER}:${WEB_GROUP} ${base}/storage ${base}/bootstrap/cache" >&2
    fi
  fi

  run_priv chmod -R ug+rwX,o+rX "${base}/storage" "${base}/bootstrap/cache" \
    || chmod -R ug+rwX,o+rX "${base}/storage" "${base}/bootstrap/cache" 2>/dev/null || true
  # Homebrew Apache (_www) often cannot use the checkout owner group; open write
  # access so session/view cache does not fall back to tempnam() → HTTP 500.
  if [[ "$(uname -s)" == "Darwin" ]]; then
    chmod -R a+rwX "${base}/storage" "${base}/bootstrap/cache" 2>/dev/null || true
  fi
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
  if writable_ok "$base"; then
    echo "    ok (${OWNER}:${WEB_GROUP})"
  else
    echo "    warning: still not fully writable for ${OWNER}" >&2
  fi
}

echo "Fixing Laravel writable dirs + storage:link under ${ROOT}"
echo "Owner/group: ${OWNER}:${WEB_GROUP}"
if [[ "${ALLOW_INTERACTIVE_SUDO:-0}" == "1" ]]; then
  echo "Sudo: interactive allowed (new installation)"
else
  echo "Sudo: non-interactive only (skip password prompts when perms are OK)"
fi
echo

for rel in "${APPS[@]}"; do
  fix_app "$rel"
done

echo
echo "Done. Laravel storage/, bootstrap/cache/, and public/storage relinked."
