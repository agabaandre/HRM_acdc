#!/usr/bin/env bash
set -euo pipefail

ENV_FILE="${STAFF_PORTAL_ENV_FILE:-/etc/staff-portal/staff-portal.env}"
if [[ -f "$ENV_FILE" ]]; then
  # shellcheck source=/dev/null
  set -a && source "$ENV_FILE" && set +a
fi

: "${STAFF_PORTAL_HEALTH_URL:?STAFF_PORTAL_HEALTH_URL is not set}"

exec /usr/bin/curl -fsS --max-time 15 "$STAFF_PORTAL_HEALTH_URL"
