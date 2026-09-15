#!/usr/bin/env bash
set -euo pipefail

resolve_helpdesk_env_file() {
  if [[ -n "${HELPDESK_ENV_FILE:-}" && -f "$HELPDESK_ENV_FILE" ]]; then
    printf '%s' "$HELPDESK_ENV_FILE"
    return 0
  fi
  local script_dir site etc_env
  script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
  site="$(basename "$(dirname "$script_dir")")"
  etc_env="/etc/helpdesk/${site}.env"
  if [[ -f "$etc_env" ]]; then
    printf '%s' "$etc_env"
    return 0
  fi
  if [[ -f /etc/helpdesk/helpdesk.env ]]; then
    printf '%s' /etc/helpdesk/helpdesk.env
    return 0
  fi
  printf '%s' "${HELPDESK_ENV_FILE:-/etc/helpdesk/helpdesk.env}"
}

ENV_FILE="$(resolve_helpdesk_env_file)"
if [[ -f "$ENV_FILE" ]]; then
  # shellcheck source=/dev/null
  set -a && source "$ENV_FILE" && set +a
fi

: "${HELPDESK_HEALTH_URL:?HELPDESK_HEALTH_URL is not set}"

exec /usr/bin/curl -fsS --max-time 15 "$HELPDESK_HEALTH_URL"
