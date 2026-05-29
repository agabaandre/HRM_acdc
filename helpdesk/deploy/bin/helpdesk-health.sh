#!/usr/bin/env bash
set -euo pipefail

ENV_FILE="${HELPDESK_ENV_FILE:-/etc/helpdesk/helpdesk.env}"
if [[ -f "$ENV_FILE" ]]; then
  # shellcheck source=/dev/null
  set -a && source "$ENV_FILE" && set +a
fi

: "${HELPDESK_HEALTH_URL:?HELPDESK_HEALTH_URL is not set}"

exec /usr/bin/curl -fsS --max-time 15 "$HELPDESK_HEALTH_URL"
