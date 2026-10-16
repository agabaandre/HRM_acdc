#!/usr/bin/env bash
# Fix Laravel storage + bootstrap/cache write access for every app under the checkout.
# Always unlinks and recreates public/storage (storage:link).
#
# Usage (from repo root):
#   ./scripts/fix-laravel-storage-permissions.sh
#   ./scripts/fix-laravel-storage-permissions.sh modules/apm
#
# Privilege elevation:
#   - Prefer passwordless sudo (sudo -n).
#   - Set ALLOW_INTERACTIVE_SUDO=1 to allow a password prompt (setup.sh does this
#     for new installs, and when root-owned storage is detected).
#   - FORCE_CHOWN=1 always attempts ownership repair even if dirs look writable.
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/.."
ROOT="$(cd "$ROOT" && pwd)"
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
  "modules/finance"
  "modules/finance/backend"
  "modules/helpdesk/backend"
  "modules/staff-portal/backend"
  "modules/risk-register"
  "modules/risk-register/backend"
  "modules/it"
  "modules/it/backend"
  "modules/registry"
  "modules/registry/backend"
)

if [[ $# -gt 0 ]]; then
  APPS=("$@")
else
  APPS=("${DEFAULT_APPS[@]}")
fi

# macOS GUI elevation (works without a TTY — used by Cursor/CI agents and GUI setup).
run_priv_macos_admin() {
  [[ "$(uname -s)" == "Darwin" ]] || return 1
  command -v osascript >/dev/null 2>&1 || return 1
  local quoted=() arg
  for arg in "$@"; do
    quoted+=("$(printf '%q' "$arg")")
  done
  local joined="${quoted[*]}"
  # Escape for AppleScript double-quoted string.
  joined="${joined//\\/\\\\}"
  joined="${joined//\"/\\\"}"
  osascript -e "do shell script \"${joined}\" with administrator privileges" >/dev/null 2>&1
}

run_priv() {
  local allow_gui="${1:-}"
  # Optional first arg: --gui  → allow macOS admin dialog (chown only).
  if [[ "$allow_gui" == "--gui" ]]; then
    shift
  else
    allow_gui=""
  fi
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
    if [[ "${ALLOW_INTERACTIVE_SUDO:-0}" == "1" ]] && [[ -t 0 || -t 1 ]]; then
      sudo "$@" && return 0
    fi
  fi
  # GUI admin only for ownership repair (avoids a password prompt per chmod).
  if [[ "$allow_gui" == "--gui" ]] \
    && { [[ "${ALLOW_INTERACTIVE_SUDO:-0}" == "1" ]] || [[ "${FORCE_CHOWN:-0}" == "1" ]]; }; then
    if run_priv_macos_admin "$@"; then
      return 0
    fi
  fi
  return 1
}

# True when deploy user can create files in key Laravel dirs.
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

# True when storage/bootstrap/cache ownership is wrong (esp. root-owned).
ownership_wrong() {
  local base="$1" path owner
  for path in "${base}/storage" "${base}/bootstrap/cache"; do
    [[ -e "$path" ]] || continue
    if [[ "$(uname -s)" == "Darwin" ]]; then
      owner="$(stat -f '%Su' "$path" 2>/dev/null || true)"
    else
      owner="$(stat -c '%U' "$path" 2>/dev/null || true)"
    fi
    if [[ -n "$owner" && "$owner" != "$OWNER" ]]; then
      return 0
    fi
    # Any nested root-owned file blocks Apache/deploy writes.
    if find "$path" -user root 2>/dev/null | head -1 | grep -q .; then
      return 0
    fi
  done
  return 1
}

needs_fix() {
  local base="$1"
  [[ "${FORCE_CHOWN:-0}" == "1" ]] && return 0
  ownership_wrong "$base" && return 0
  writable_ok "$base" || return 0
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
    mv "$link" "$bak" 2>/dev/null || run_priv mv "$link" "$bak" || true
  elif [[ -e "$link" && ! -L "$link" ]]; then
    rm -f "$link" 2>/dev/null || run_priv rm -f "$link" || true
  fi

  if command -v "$php_bin" >/dev/null 2>&1 && [[ -f "${base}/artisan" ]]; then
    (cd "$base" && "$php_bin" artisan storage:link --force --no-interaction) >/dev/null 2>&1 \
      || (cd "$base" && "$php_bin" artisan storage:link --no-interaction) >/dev/null 2>&1 \
      || true
  fi
  if [[ ! -e "$link" ]]; then
    ln -sfn "${base}/storage/app/public" "$link" 2>/dev/null \
      || run_priv ln -sfn "${base}/storage/app/public" "$link" || true
  fi
}

apply_mode_and_acl() {
  local base="$1"
  run_priv chmod -R ug+rwX,o+rX "${base}/storage" "${base}/bootstrap/cache" \
    || chmod -R ug+rwX,o+rX "${base}/storage" "${base}/bootstrap/cache" 2>/dev/null || true
  # Homebrew Apache (_www) often cannot use the checkout owner group.
  if [[ "$(uname -s)" == "Darwin" ]]; then
    run_priv chmod -R a+rwX "${base}/storage" "${base}/bootstrap/cache" \
      || chmod -R a+rwX "${base}/storage" "${base}/bootstrap/cache" 2>/dev/null || true
    if command -v chmod >/dev/null 2>&1; then
      run_priv chmod -R +a "group:${WEB_GROUP} allow list,add_file,search,add_subdirectory,delete_child,read,write,execute,delete,append" \
        "${base}/storage" "${base}/bootstrap/cache" 2>/dev/null || true
      run_priv chmod -R +a "user:${OWNER} allow list,add_file,search,add_subdirectory,delete_child,read,write,execute,delete,append" \
        "${base}/storage" "${base}/bootstrap/cache" 2>/dev/null || true
      chmod -R +a "group:${WEB_GROUP} allow list,add_file,search,add_subdirectory,delete_child,read,write,execute,delete,append" \
        "${base}/storage" "${base}/bootstrap/cache" 2>/dev/null || true
      chmod -R +a "user:${OWNER} allow list,add_file,search,add_subdirectory,delete_child,read,write,execute,delete,append" \
        "${base}/storage" "${base}/bootstrap/cache" 2>/dev/null || true
    fi
  fi
  run_priv chmod -R a+rX "${base}/public" || chmod -R a+rX "${base}/public" 2>/dev/null || true
  find "${base}/storage" "${base}/bootstrap/cache" -type d -exec chmod ug+rwx,g+s {} + 2>/dev/null || true
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

  # Drop stale compiled caches that are often root-owned after sudo artisan *.
  find "${base}/storage/framework/views" -type f -name '*.php' -delete 2>/dev/null \
    || run_priv find "${base}/storage/framework/views" -type f -name '*.php' -delete || true
  find "${base}/storage/framework/cache" -type f ! -name '.gitignore' -delete 2>/dev/null \
    || run_priv find "${base}/storage/framework/cache" -type f ! -name '.gitignore' -delete || true
  find "${base}/bootstrap/cache" -type f \( -name 'routes*.php' -o -name 'config.php' -o -name 'services.php' -o -name 'packages.php' \) -delete 2>/dev/null \
    || run_priv find "${base}/bootstrap/cache" -type f \( -name 'routes*.php' -o -name 'config.php' -o -name 'services.php' -o -name 'packages.php' \) -delete || true

  if command -v chmod >/dev/null 2>&1; then
    chmod -RN "${base}/storage" "${base}/bootstrap/cache" 2>/dev/null || true
  fi

  local targets=("${base}/storage" "${base}/bootstrap/cache" "${base}/public")
  if [[ -d "${base}/database" ]]; then
    targets+=("${base}/database")
  fi

  if needs_fix "$base"; then
    if ownership_wrong "$base" || ! writable_ok "$base"; then
      echo "    repairing ownership → ${OWNER}:${WEB_GROUP}"
      # --gui: macOS admin dialog when sudo has no TTY (IDE / Cursor setup).
      if run_priv --gui chown -R "${OWNER}:${WEB_GROUP}" "${targets[@]}"; then
        echo "    chown OK"
      else
        echo "    warning: chown ${OWNER}:${WEB_GROUP} failed for ${rel}" >&2
        echo "             run: sudo chown -R ${OWNER}:${WEB_GROUP} ${base}/storage ${base}/bootstrap/cache" >&2
      fi
    fi
  else
    echo "    ownership/writability already OK — refresh modes"
  fi

  apply_mode_and_acl "$base"

  if [[ -f "${base}/database/database.sqlite" ]]; then
    run_priv chown "${OWNER}:${WEB_GROUP}" "${base}/database/database.sqlite" || true
    run_priv chmod 664 "${base}/database/database.sqlite" || true
  fi

  relink_storage "$base" || true
  if writable_ok "$base" && ! ownership_wrong "$base"; then
    echo "    ok (${OWNER}:${WEB_GROUP})"
  elif writable_ok "$base"; then
    echo "    writable but ownership still not ${OWNER} — Apache may work via world/ACL bits"
  else
    echo "    warning: still not fully writable for ${OWNER}" >&2
    return 1
  fi
  return 0
}

# If any app is root-owned and we have a TTY, allow interactive sudo for this run.
if [[ "${ALLOW_INTERACTIVE_SUDO:-0}" != "1" ]] && [[ -t 0 || -t 1 ]]; then
  for rel in "${APPS[@]}"; do
    if [[ -f "${ROOT}/${rel}/artisan" ]] && ownership_wrong "${ROOT}/${rel}"; then
      echo "Root-owned Laravel storage detected — enabling interactive sudo for ownership repair."
      export ALLOW_INTERACTIVE_SUDO=1
      break
    fi
  done
fi

echo "Fixing Laravel writable dirs + storage:link under ${ROOT}"
echo "Owner/group: ${OWNER}:${WEB_GROUP}"
if [[ "${ALLOW_INTERACTIVE_SUDO:-0}" == "1" ]]; then
  echo "Sudo: interactive allowed"
else
  echo "Sudo: non-interactive only (sudo -n)"
fi
echo

FAILS=0
for rel in "${APPS[@]}"; do
  if ! fix_app "$rel"; then
    FAILS=$((FAILS + 1))
  fi
done

echo
if [[ "$FAILS" -gt 0 ]]; then
  echo "Done with ${FAILS} warning(s). Re-run with: sudo -E env OWNER=${OWNER} ./scripts/fix-laravel-storage-permissions.sh"
  exit 1
fi
echo "Done. Laravel storage/, bootstrap/cache/, and public/storage relinked."
