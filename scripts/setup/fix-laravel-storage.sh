#!/usr/bin/env bash
# shellcheck shell=bash
# Laravel storage dirs, permissions, and public/storage unlink+relink for all apps.

setup_fix_laravel_storage() {
  local script app_script host_script
  echo
  echo "==> Laravel storage (permissions + unlink/relink public/storage)"

  script="$ROOT/scripts/fix-laravel-storage-permissions.sh"
  chmod +x "$script" 2>/dev/null || true
  if [[ -x "$script" ]] || [[ -f "$script" ]]; then
    if bash "$script"; then
      echo "    shared Laravel dirs + storage:link OK"
    else
      setup_warn "Laravel storage permission fix failed — uploads/views may 500 until fixed"
    fi
  fi

  # Per-app scripts relink public/storage to host STAFF_* disks when configured.
  for app_script in \
    "$ROOT/modules/staff-portal/fix-storage-permissions.sh" \
    "$ROOT/modules/helpdesk/fix-storage-permissions.sh" \
    "$ROOT/modules/finance/fix-storage-permissions.sh"
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
}
