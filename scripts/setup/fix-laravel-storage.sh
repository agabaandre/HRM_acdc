#!/usr/bin/env bash
# shellcheck shell=bash
# Laravel storage dirs, permissions, and public/storage unlink+relink for all apps.

# Generate APP_KEY in dir/.env when missing (ensures APP_KEY= line exists first).
setup_ensure_laravel_app_key() {
  local dir="$1"
  local helper="${ROOT:-}/scripts/setup/ensure-app-key.sh"
  if [[ ! -f "$helper" ]]; then
    helper="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/ensure-app-key.sh"
  fi
  # shellcheck source=ensure-app-key.sh
  source "$helper"
  if ! ensure_app_key_in_env "$dir"; then
    setup_warn "could not generate APP_KEY in $dir"
  fi
}

setup_ensure_it_registry_keys_and_perms() {
  local mod
  for mod in it registry; do
    setup_ensure_laravel_app_key "$ROOT/modules/$mod"
    setup_ensure_laravel_app_key "$ROOT/modules/$mod/backend"
  done
}

setup_laravel_storage_needs_sudo() {
  local base owner
  for base in \
    "$ROOT/modules/apm" \
    "$ROOT/modules/finance" \
    "$ROOT/modules/finance/backend" \
    "$ROOT/modules/helpdesk/backend" \
    "$ROOT/modules/staff-portal/backend" \
    "$ROOT/modules/risk-register/backend" \
    "$ROOT/modules/it" \
    "$ROOT/modules/it/backend" \
    "$ROOT/modules/registry" \
    "$ROOT/modules/registry/backend"
  do
    [[ -d "$base/storage" ]] || continue
    if [[ "$(uname -s)" == "Darwin" ]]; then
      owner="$(stat -f '%Su' "$base/storage" 2>/dev/null || true)"
    else
      owner="$(stat -c '%U' "$base/storage" 2>/dev/null || true)"
    fi
    if [[ "$owner" == "root" ]] || find "$base/storage" -user root 2>/dev/null | head -1 | grep -q .; then
      return 0
    fi
  done
  return 1
}

setup_fix_laravel_storage() {
  local script app_script host_script
  local prev_sudo="${ALLOW_INTERACTIVE_SUDO:-0}"
  echo
  echo "==> Laravel storage (permissions + unlink/relink public/storage)"

  # Root-owned trees from prior sudo artisan/setup cannot be fixed without elevation.
  if setup_laravel_storage_needs_sudo; then
    if [[ "${ALLOW_INTERACTIVE_SUDO:-0}" != "1" ]]; then
      echo "    root-owned storage detected — enabling interactive sudo for ownership repair"
      export ALLOW_INTERACTIVE_SUDO=1
    fi
    export FORCE_CHOWN=1
  fi

  script="$ROOT/scripts/fix-laravel-storage-permissions.sh"
  chmod +x "$script" 2>/dev/null || true
  if [[ -x "$script" ]] || [[ -f "$script" ]]; then
    if bash "$script"; then
      echo "    shared Laravel dirs + storage:link OK"
    else
      setup_warn "Laravel storage permission fix failed — uploads/views may 500 until fixed"
      setup_warn "run: sudo chown -R \"\$(whoami):_www\" modules/*/storage modules/*/backend/storage modules/*/bootstrap/cache modules/*/backend/bootstrap/cache"
    fi
  fi

  # Per-app scripts relink public/storage to host STAFF_* disks when configured.
  for app_script in \
    "$ROOT/modules/staff-portal/fix-storage-permissions.sh" \
    "$ROOT/modules/helpdesk/fix-storage-permissions.sh" \
    "$ROOT/modules/finance/fix-storage-permissions.sh" \
    "$ROOT/modules/risk-register/fix-storage-permissions.sh" \
    "$ROOT/modules/it/fix-storage-permissions.sh" \
    "$ROOT/modules/registry/fix-storage-permissions.sh" \
    "$ROOT/modules/apm/fix-storage-permissions.sh"
  do
    [[ -f "$app_script" ]] || continue
    chmod +x "$app_script" 2>/dev/null || true
    if bash "$app_script"; then
      echo "    $(basename "$(dirname "$app_script")") storage OK"
    else
      setup_warn "$(basename "$(dirname "$app_script")") storage fix failed"
    fi
  done

  host_script="$ROOT/scripts/storage/fix-staff-storage-permissions.sh"
  if [[ -f "$host_script" && -n "${STAFF_DATA_ROOT:-}" ]]; then
    chmod +x "$host_script" "$ROOT/scripts/storage/"*.sh 2>/dev/null || true
    if STAFF_STORAGE_GROUP="${LARAVEL_WEB_GROUP:-}" bash "$host_script"; then
      echo "    host staffdata permissions OK (${STAFF_DATA_ROOT})"
    else
      setup_warn "host staffdata permission fix failed (${STAFF_DATA_ROOT})"
    fi
  fi

  export ALLOW_INTERACTIVE_SUDO="$prev_sudo"
  unset FORCE_CHOWN 2>/dev/null || true
}
