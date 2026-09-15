#!/usr/bin/env bash
# Upsert modules/apm/.env from exported wizard variables.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
# shellcheck source=env-upsert.sh
source "$ROOT/scripts/setup/env-upsert.sh"

APM_DIR="$ROOT/modules/apm"
ENV_FILE="$APM_DIR/.env"
EXAMPLE="$APM_DIR/.env.example"

env_ensure_file "$ENV_FILE" "$EXAMPLE"

apply() {
  local key="$1" val="${2:-}"
  [[ -n "$val" ]] || return 0
  env_set "$ENV_FILE" "$key" "$val"
}

apply APP_URL "${APM_APP_URL:-}"
apply BASE_URL "${BASE_URL:-}"
apply CI_BASE_URL "${CI_BASE_URL:-}"
apply JWT_SECRET "${JWT_SECRET:-}"
apply DB_HOST "${DB_HOST:-}"
apply DB_PORT "${DB_PORT:-}"
apply DB_DATABASE "${APM_DB_DATABASE:-}"
apply DB_USERNAME "${DB_USERNAME:-${DB_USER:-}}"
apply DB_PASSWORD "${DB_PASSWORD:-${DB_PASS:-}}"
apply REDIS_HOST "${REDIS_HOST:-}"
apply REDIS_PORT "${REDIS_PORT:-}"
apply REDIS_PASSWORD "${REDIS_PASSWORD:-}"
apply STAFF_API_USERNAME "${STAFF_API_USERNAME:-}"
apply STAFF_API_PASSWORD "${STAFF_API_PASSWORD:-}"
apply STAFF_API_TOKEN "${STAFF_API_TOKEN:-}"
apply STAFF_API_INTERNAL_BASE_URL "${STAFF_API_INTERNAL_BASE_URL:-}"

# Microsoft SSO (mobile + web oauth) — same Azure app as staff-portal
apply TENANT_ID "${TENANT_ID:-}"
apply CLIENT_ID "${CLIENT_ID:-}"
apply CLIENT_SEC_VALUE "${CLIENT_SEC_VALUE:-}"
apply CLIENT_SEC_ID "${CLIENT_SEC_ID:-}"
apply MICROSOFT_TENANT_ID "${TENANT_ID:-}"
apply MICROSOFT_CLIENT_ID "${CLIENT_ID:-}"
apply MICROSOFT_CLIENT_SECRET "${CLIENT_SEC_VALUE:-}"
apply MICROSOFT_REDIRECT_URI "${MICROSOFT_REDIRECT_URI_APM:-}"

# Optional Graph mail from same Azure app
if [[ "${EXCHANGE_FROM_MS:-0}" == "1" ]]; then
  apply EXCHANGE_TENANT_ID "${TENANT_ID:-}"
  apply EXCHANGE_CLIENT_ID "${CLIENT_ID:-}"
  apply EXCHANGE_CLIENT_SECRET "${CLIENT_SEC_VALUE:-}"
  apply EXCHANGE_REDIRECT_URI "${EXCHANGE_REDIRECT_URI_APM:-}"
fi

echo "==> APM .env upserted at $ENV_FILE"
