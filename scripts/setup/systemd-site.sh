#!/usr/bin/env bash
# shellcheck shell=bash
# Shared systemd site slug (matches WEB_ROOT / public folder name).

setup_systemd_site_slug() {
  local raw="${1:-${WEB_ROOT:-staff}}"
  raw="$(printf '%s' "$raw" | tr '[:upper:]' '[:lower:]' | sed -E 's/[^a-z0-9_-]+/-/g; s/^-+//; s/-+$//')"
  printf '%s' "${raw:-staff}"
}
