#!/usr/bin/env bash
set -euo pipefail

resolve_staff_portal_env_file() {
  if [[ -n "${STAFF_PORTAL_ENV_FILE:-}" && -f "$STAFF_PORTAL_ENV_FILE" ]]; then
    printf '%s' "$STAFF_PORTAL_ENV_FILE"
    return 0
  fi
  local script_dir site etc_env
  script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
  site="$(basename "$(dirname "$script_dir")")"
  etc_env="/etc/staff-portal/${site}.env"
  if [[ -f "$etc_env" ]]; then
    printf '%s' "$etc_env"
    return 0
  fi
  if [[ -f /etc/staff-portal/staff-portal.env ]]; then
    printf '%s' /etc/staff-portal/staff-portal.env
    return 0
  fi
  printf '%s' "${STAFF_PORTAL_ENV_FILE:-/etc/staff-portal/staff-portal.env}"
}

ENV_FILE="$(resolve_staff_portal_env_file)"
if [[ -f "$ENV_FILE" ]]; then
  # shellcheck source=/dev/null
  set -a && source "$ENV_FILE" && set +a
fi

: "${STAFF_PORTAL_HEALTH_URL:?STAFF_PORTAL_HEALTH_URL is not set}"

exec /usr/bin/curl -fsS --max-time 15 "$STAFF_PORTAL_HEALTH_URL"
