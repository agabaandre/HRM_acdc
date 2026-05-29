#!/usr/bin/env bash
set -euo pipefail

ENV_FILE="${HELPDESK_ENV_FILE:-/etc/helpdesk/helpdesk.env}"
if [[ -f "$ENV_FILE" ]]; then
  # shellcheck source=/dev/null
  set -a && source "$ENV_FILE" && set +a
fi

: "${HELPDESK_ROOT:?HELPDESK_ROOT is not set (see /etc/helpdesk/helpdesk.env)}"
: "${PHP_BIN:=/usr/bin/php}"

cd "$HELPDESK_ROOT"
exec "$PHP_BIN" artisan queue:work database \
  --sleep=3 \
  --tries=3 \
  --max-time=3600 \
  --no-interaction
