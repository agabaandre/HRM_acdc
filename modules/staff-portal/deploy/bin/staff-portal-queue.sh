#!/usr/bin/env bash
set -euo pipefail

ENV_FILE="${STAFF_PORTAL_ENV_FILE:-/etc/staff-portal/staff-portal.env}"
if [[ -f "$ENV_FILE" ]]; then
  # shellcheck source=/dev/null
  set -a && source "$ENV_FILE" && set +a
fi

: "${STAFF_PORTAL_ROOT:?STAFF_PORTAL_ROOT is not set (see /etc/staff-portal/staff-portal.env)}"
: "${PHP_BIN:=/usr/bin/php}"

cd "$STAFF_PORTAL_ROOT"
exec "$PHP_BIN" artisan queue:work database \
  --queue=default \
  --sleep=3 \
  --tries=3 \
  --max-time=3600 \
  --no-interaction
