#!/usr/bin/env bash
set -euo pipefail

ENV_FILE="${STAFF_PORTAL_ENV_FILE:-/etc/staff-portal/staff-portal.env}"
if [[ -f "$ENV_FILE" ]]; then
  # shellcheck source=/dev/null
  set -a && source "$ENV_FILE" && set +a
fi

: "${STAFF_PORTAL_ROOT:?STAFF_PORTAL_ROOT is not set}"
: "${PHP_BIN:=/usr/bin/php}"

cd "$STAFF_PORTAL_ROOT"
exec "$PHP_BIN" artisan schedule:run --no-interaction
