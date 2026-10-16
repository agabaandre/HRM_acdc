#!/usr/bin/env bash
# shellcheck shell=bash
# Ensure a Laravel app dir is writable for Composer + artisan (vendor/, bootstrap/cache, storage/).
# Fixes "file_put_contents(.../vendor/composer/installed.php): Failed to open stream" after sudo installs.
#
# Usage:
#   source scripts/setup/ensure-writable-app-dirs.sh
#   ensure_writable_app_dirs /path/to/modules/it/backend
#   ensure_writable_app_dirs /path/to/modules/it/backend --gui   # macOS admin dialog OK

ensure_writable_app_dirs() {
  local app="$1"
  local allow_gui="${2:-}"
  local owner group
  local targets=()
  local d

  [[ -n "$app" && -d "$app" ]] || return 0

  owner="${ENSURE_APP_OWNER:-${SUDO_USER:-$(id -un)}}"
  if [[ "$owner" == "root" && -n "${SUDO_USER:-}" && "$SUDO_USER" != "root" ]]; then
    owner="$SUDO_USER"
  fi
  if [[ "$(uname -s)" == "Darwin" ]]; then
    group="${ENSURE_APP_GROUP:-_www}"
  else
    group="${ENSURE_APP_GROUP:-www-data}"
  fi

  for d in \
    vendor \
    vendor/composer \
    bootstrap/cache \
    storage \
    storage/app \
    storage/app/public \
    storage/framework \
    storage/framework/cache \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs
  do
    mkdir -p "${app}/${d}" 2>/dev/null || true
    targets+=("${app}/${d}")
  done

  _ewad_writable() {
    local p
    for p in "${app}/vendor" "${app}/bootstrap/cache"; do
      [[ -d "$p" ]] || return 1
      [[ -w "$p" ]] || return 1
      if ! printf 'ok\n' >"${p}/.cbp-write-probe.$$" 2>/dev/null; then
        rm -f "${p}/.cbp-write-probe.$$" 2>/dev/null || true
        return 1
      fi
      rm -f "${p}/.cbp-write-probe.$$" 2>/dev/null || true
    done
    return 0
  }

  _ewad_needs_chown() {
    local p owner_now
    for p in "${app}/vendor" "${app}/bootstrap/cache" "${app}/storage"; do
      [[ -e "$p" ]] || continue
      if [[ "$(uname -s)" == "Darwin" ]]; then
        owner_now="$(stat -f '%Su' "$p" 2>/dev/null || true)"
      else
        owner_now="$(stat -c '%U' "$p" 2>/dev/null || true)"
      fi
      if [[ -n "$owner_now" && "$owner_now" != "$owner" ]]; then
        return 0
      fi
      if find "$p" -user root 2>/dev/null | head -1 | grep -q .; then
        return 0
      fi
    done
    return 1
  }

  _ewad_run() {
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
    if [[ "$allow_gui" == "--gui" || "${ALLOW_INTERACTIVE_SUDO:-0}" == "1" ]] \
      && [[ "$(uname -s)" == "Darwin" ]] && command -v osascript >/dev/null 2>&1; then
      local quoted=() arg joined
      for arg in "$@"; do
        quoted+=("$(printf '%q' "$arg")")
      done
      joined="${quoted[*]}"
      joined="${joined//\\/\\\\}"
      joined="${joined//\"/\\\"}"
      osascript -e "do shell script \"${joined}\" with administrator privileges" >/dev/null 2>&1 && return 0
    fi
    return 1
  }

  if _ewad_writable && ! _ewad_needs_chown; then
    return 0
  fi

  echo "    ensuring writable app dirs → ${app} (${owner}:${group})"
  if ! _ewad_run chown -R "${owner}:${group}" "${app}/vendor" "${app}/bootstrap/cache" "${app}/storage"; then
    echo "    warning: could not chown ${app}/{vendor,bootstrap/cache,storage}" >&2
    echo "             run: sudo chown -R ${owner}:${group} ${app}/vendor ${app}/bootstrap/cache ${app}/storage" >&2
  fi
  _ewad_run chmod -R ug+rwX "${app}/vendor" "${app}/bootstrap/cache" "${app}/storage" || true
  chmod -R ug+rwX "${app}/vendor" "${app}/bootstrap/cache" "${app}/storage" 2>/dev/null || true

  if ! _ewad_writable; then
    echo "    warning: ${app}/vendor still not writable for $(id -un) — composer install may fail" >&2
    return 1
  fi
  return 0
}
