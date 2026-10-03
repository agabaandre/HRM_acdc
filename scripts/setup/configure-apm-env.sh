#!/usr/bin/env bash
# Upsert modules/apm/.env from exported wizard variables.
# Shared Microsoft Graph + JWT secrets live in /staff/.env (not copied here).
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
apply STAFF_PORTAL_SPA_URL "${STAFF_PORTAL_SPA_URL:-${BASE_URL:-}}"
apply STAFF_PORTAL_URL "${STAFF_PORTAL_SPA_URL:-${BASE_URL:-}}"
# Site role from root setup (production → APP_DEBUG=false).
if [[ -n "${APP_ENV:-}" ]]; then
  env_set "$ENV_FILE" APP_ENV "$APP_ENV"
fi
if [[ -n "${APP_DEBUG:-}" ]]; then
  env_set "$ENV_FILE" APP_DEBUG "$APP_DEBUG"
fi
# JWT_SECRET / EXCHANGE_* come from $ROOT/.env via shared/load-staff-root-env.php
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

# Per-app OAuth callback paths only (credentials are centralized in /staff/.env)
apply MICROSOFT_REDIRECT_URI "${MICROSOFT_REDIRECT_URI_APM:-}"
apply EXCHANGE_REDIRECT_URI "${EXCHANGE_REDIRECT_URI_APM:-}"

apply MAIL_FROM_NAME "${APM_MAIL_FROM_NAME:-}"
apply MAIL_ENCRYPTION "${MAIL_ENCRYPTION:-}"
apply PHPMailer_HOST "${MAIL_HOST:-}"
apply PHPMailer_PORT "${MAIL_PORT:-}"
apply PHPMailer_USERNAME "${MAIL_USERNAME:-}"
apply PHPMailer_PASSWORD "${MAIL_PASSWORD:-}"
apply PHPMailer_FROM_ADDRESS "${APM_MAIL_FROM_ADDRESS:-}"
apply PHPMailer_FROM_NAME "${APM_MAIL_FROM_NAME:-}"

echo "==> APM .env upserted at $ENV_FILE (Graph/JWT inherited from $ROOT/.env)"
