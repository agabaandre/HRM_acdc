#!/usr/bin/env bash
# shellcheck shell=bash
# Stop/disable/remove systemd units so reinstalls do not leave duplicate workers.

systemd_retire_units() {
  local u path
  if ! command -v systemctl >/dev/null 2>&1; then
    return 0
  fi
  for u in "$@"; do
    [[ -n "$u" ]] || continue
    systemctl stop "$u" 2>/dev/null || true
    systemctl disable "$u" 2>/dev/null || true
    # Drop drop-ins and unit files from /etc (not vendor units under /lib).
    for path in \
      "/etc/systemd/system/${u}" \
      "/etc/systemd/system/${u}.d" \
      "/etc/systemd/system/multi-user.target.wants/${u}" \
      "/etc/systemd/system/timers.target.wants/${u}"
    do
      rm -rf "$path" 2>/dev/null || true
    done
    echo "    retired $u"
  done
  systemctl daemon-reload 2>/dev/null || true
  systemctl reset-failed 2>/dev/null || true
}
