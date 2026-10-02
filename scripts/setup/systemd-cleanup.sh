#!/usr/bin/env bash
# shellcheck shell=bash
# Stop/disable/remove systemd units so reinstalls do not leave duplicate workers.
# Never prompts (no polkit "Choose identity" dialogs) — root or passwordless sudo only.

systemd_can_manage_units() {
  [[ "$(id -u)" -eq 0 ]] && return 0
  if command -v sudo >/dev/null 2>&1 && sudo -n true 2>/dev/null; then
    return 0
  fi
  return 1
}

# Run systemctl without polkit interactive auth.
systemd_systemctl() {
  # --no-ask-password: fail instead of polkit "Choose identity" prompts.
  if [[ "$(id -u)" -eq 0 ]]; then
    systemctl --no-ask-password "$@"
  elif command -v sudo >/dev/null 2>&1; then
    sudo -n systemctl --no-ask-password "$@"
  else
    return 1
  fi
}

systemd_rm_path() {
  local path="$1"
  if [[ "$(id -u)" -eq 0 ]]; then
    rm -rf "$path" 2>/dev/null || true
  elif command -v sudo >/dev/null 2>&1; then
    sudo -n rm -rf "$path" 2>/dev/null || true
  fi
}

systemd_retire_units() {
  local u path
  if ! command -v systemctl >/dev/null 2>&1; then
    return 0
  fi
  if ! systemd_can_manage_units; then
    echo "    skip systemd retire (need root or passwordless sudo — refusing interactive polkit prompts)" >&2
    echo "    later (as root): systemctl stop/disable staff-portal-*.service helpdesk-*.service" >&2
    return 0
  fi
  for u in "$@"; do
    [[ -n "$u" ]] || continue
    # Only touch units that exist (avoids noisy failures / auth noise).
    if ! systemd_systemctl cat "$u" &>/dev/null; then
      continue
    fi
    systemd_systemctl stop "$u" 2>/dev/null || true
    systemd_systemctl disable "$u" 2>/dev/null || true
    # Drop drop-ins and unit files from /etc (not vendor units under /lib).
    for path in \
      "/etc/systemd/system/${u}" \
      "/etc/systemd/system/${u}.d" \
      "/etc/systemd/system/multi-user.target.wants/${u}" \
      "/etc/systemd/system/timers.target.wants/${u}"
    do
      systemd_rm_path "$path"
    done
    echo "    retired $u"
  done
  systemd_systemctl daemon-reload 2>/dev/null || true
  systemd_systemctl reset-failed 2>/dev/null || true
}
