#!/usr/bin/env bash
set -euo pipefail

# Resolve site-scoped env: explicit var → /etc/…/{slug}.env → legacy flat path.
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

: "${STAFF_PORTAL_ROOT:?STAFF_PORTAL_ROOT is not set (see $ENV_FILE)}"
: "${PHP_BIN:=/usr/bin/php}"

cd "$STAFF_PORTAL_ROOT"
exec "$PHP_BIN" artisan queue:work database \
  --queue=default \
  --sleep=3 \
  --tries=3 \
  --max-time=3600 \
  --no-interaction
