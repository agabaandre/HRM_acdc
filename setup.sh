#!/usr/bin/env bash
# Interactive CBP root setup: env for all modules + optional installers + systemd.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

if [[ ! -t 0 ]]; then
  echo "error: ./setup.sh requires an interactive TTY (see docs/SETUP.md)" >&2
  exit 1
fi

# shellcheck source=scripts/setup/env-upsert.sh
source "$ROOT/scripts/setup/env-upsert.sh"
# shellcheck source=scripts/setup/prompt.sh
source "$ROOT/scripts/setup/prompt.sh"
# shellcheck source=scripts/setup/map-urls.sh
source "$ROOT/scripts/setup/map-urls.sh"

echo "=== Africa CDC CBP setup ==="
echo "Repo: $ROOT"
echo

prompt_choice INSTALL_TYPE "Install type" "1) New installation  2) Existing installation" "1"
prompt_choice DEPLOY_CHOICE "Deploy target" "1) Host Apache  2) Docker Compose" "1"
[[ "$DEPLOY_CHOICE" == "2" ]] && DEPLOY_MODE=docker || DEPLOY_MODE=host

DB_DEFAULT=1
[[ "$INSTALL_TYPE" == "2" ]] && DB_DEFAULT=3
prompt_choice DB_CHOICE "Database" "1) Docker bundled MySQL  2) External MySQL  3) Keep current" "$DB_DEFAULT"
case "$DB_CHOICE" in
  1) DB_MODE=bundled ;;
  2) DB_MODE=external ;;
  *) DB_MODE=keep ;;
esac

ROOT_ENV="$ROOT/.env"
env_ensure_file "$ROOT_ENV" "$ROOT/scripts/setup/templates/root.env.example"

DEFAULT_BASE="$(env_get "$ROOT_ENV" BASE_URL)"
DEFAULT_BASE="${DEFAULT_BASE%/}"
if [[ -z "$DEFAULT_BASE" ]]; then
  if [[ "$DEPLOY_MODE" == "docker" ]]; then
    DEFAULT_BASE="http://localhost:8088/staff"
  else
    DEFAULT_BASE="http://localhost/staff"
  fi
fi

prompt_value PUBLIC_BASE "Public staff base URL (…/staff)" "$DEFAULT_BASE"
setup_map_urls

JWT_DEFAULT="$(env_get "$ROOT_ENV" JWT_SECRET)"
if [[ -z "$JWT_DEFAULT" && "$INSTALL_TYPE" == "1" ]]; then
  JWT_DEFAULT="$(openssl rand -hex 32 2>/dev/null || true)"
fi
prompt_secret JWT_SECRET "JWT_SECRET (shared SSO)" "$JWT_DEFAULT"

prompt_value STAFF_API_USERNAME "STAFF_API_USERNAME" "$(env_get "$ROOT_ENV" STAFF_API_USERNAME)"
prompt_secret STAFF_API_PASSWORD "STAFF_API_PASSWORD" "$(env_get "$ROOT_ENV" STAFF_API_PASSWORD)"
prompt_value STAFF_API_TOKEN "STAFF_API_TOKEN" "$(env_get "$ROOT_ENV" STAFF_API_TOKEN)"

if [[ "$DEPLOY_MODE" == "docker" ]]; then
  REDIS_DEF="$REDIS_HOST_DEFAULT"
else
  REDIS_DEF="$(env_get "$ROOT_ENV" REDIS_HOST)"
  REDIS_DEF="${REDIS_DEF:-$REDIS_HOST_DEFAULT}"
fi
prompt_value REDIS_HOST "REDIS_HOST" "$REDIS_DEF"
_rport="$(env_get "$ROOT_ENV" REDIS_PORT)"
prompt_value REDIS_PORT "REDIS_PORT" "${_rport:-6379}"
prompt_secret REDIS_PASSWORD "REDIS_PASSWORD" "$(env_get "$ROOT_ENV" REDIS_PASSWORD)"

DB_HOST="$(env_get "$ROOT_ENV" DB_HOST)"
DB_PORT="$(env_get "$ROOT_ENV" DB_PORT)"
DB_USER="$(env_get "$ROOT_ENV" DB_USER)"
DB_PASS="$(env_get "$ROOT_ENV" DB_PASS)"
DB_NAME="$(env_get "$ROOT_ENV" DB_NAME)"

if [[ "$DB_MODE" == "bundled" ]]; then
  DB_HOST=mysql
  prompt_value DB_PORT "DB_PORT" "${DB_PORT:-3306}"
  prompt_value DB_USER "DB_USER" "${DB_USER:-staff}"
  prompt_secret DB_PASS "DB_PASS" "${DB_PASS:-staff}"
  prompt_value DB_NAME "Root DB_NAME" "${DB_NAME:-staff}"
elif [[ "$DB_MODE" == "external" ]]; then
  _dh="$DB_HOST"
  [[ -z "$_dh" && "$DEPLOY_MODE" == "docker" ]] && _dh=host.docker.internal
  [[ -z "$_dh" ]] && _dh=127.0.0.1
  prompt_value DB_HOST "DB_HOST" "$_dh"
  prompt_value DB_PORT "DB_PORT" "${DB_PORT:-3306}"
  prompt_value DB_USER "DB_USER" "${DB_USER:-root}"
  prompt_secret DB_PASS "DB_PASS" "$DB_PASS"
  prompt_value DB_NAME "Root DB_NAME" "${DB_NAME:-staff}"
else
  echo "==> Keeping current DB_* (host=${DB_HOST:-unset})"
fi

DB_USERNAME="${DB_USER:-}"
DB_PASSWORD="${DB_PASS:-}"

echo
echo "==> Writing root .env"
env_set "$ROOT_ENV" BASE_URL "$BASE_URL"
env_set "$ROOT_ENV" CI_BASE_URL "$CI_BASE_URL"
env_set "$ROOT_ENV" APM_BASE_URL "$APM_BASE_URL"
env_set "$ROOT_ENV" JWT_SECRET "$JWT_SECRET"
env_set "$ROOT_ENV" STAFF_API_USERNAME "$STAFF_API_USERNAME"
env_set "$ROOT_ENV" STAFF_API_PASSWORD "$STAFF_API_PASSWORD"
env_set "$ROOT_ENV" STAFF_API_TOKEN "$STAFF_API_TOKEN"
if [[ "$DB_MODE" != "keep" ]]; then
  env_set "$ROOT_ENV" DB_HOST "$DB_HOST"
  env_set "$ROOT_ENV" DB_PORT "$DB_PORT"
  env_set "$ROOT_ENV" DB_USER "$DB_USER"
  env_set "$ROOT_ENV" DB_PASS "$DB_PASS"
  env_set "$ROOT_ENV" DB_NAME "$DB_NAME"
fi

apply_db_to_file() {
  local file="$1" user_key="${2:-DB_USERNAME}" pass_key="${3:-DB_PASSWORD}"
  [[ "$DB_MODE" == "keep" ]] && return 0
  env_set "$file" DB_HOST "$DB_HOST"
  env_set "$file" DB_PORT "$DB_PORT"
  env_set "$file" "$user_key" "$DB_USER"
  env_set "$file" "$pass_key" "$DB_PASS"
}

apply_redis_to_file() {
  local file="$1"
  env_set "$file" REDIS_HOST "$REDIS_HOST"
  env_set "$file" REDIS_PORT "$REDIS_PORT"
  [[ -n "${REDIS_PASSWORD:-}" ]] && env_set "$file" REDIS_PASSWORD "$REDIS_PASSWORD"
}

# ----- staff-portal -----
echo
echo "==> staff-portal"
SP_SETUP="$ROOT/modules/staff-portal/setup.env"
SP_ENV="$ROOT/modules/staff-portal/backend/.env"
env_ensure_file "$SP_SETUP" "$ROOT/modules/staff-portal/setup.env.example"
env_ensure_file "$SP_ENV" "$ROOT/modules/staff-portal/backend/.env.example"
prompt_value SP_DB "staff-portal DB_DATABASE" "$(env_get "$SP_SETUP" DB_DATABASE)"
SP_DB="${SP_DB:-staff}"
for f in "$SP_SETUP" "$SP_ENV"; do
  env_set "$f" APP_URL "$STAFF_PORTAL_APP_URL"
  env_set "$f" STAFF_PORTAL_BASE_URL "$STAFF_PORTAL_APP_URL"
  env_set "$f" STAFF_PORTAL_SPA_URL "$STAFF_PORTAL_SPA_URL"
  env_set "$f" STAFF_PORTAL_SPA_ENABLED "true"
  env_set "$f" BASE_URL "$BASE_URL"
  env_set "$f" JWT_SECRET "$JWT_SECRET"
  env_set "$f" DB_DATABASE "$SP_DB"
  apply_db_to_file "$f" DB_USERNAME DB_PASSWORD
  apply_redis_to_file "$f"
done
env_set "$SP_SETUP" VITE_STAFF_PORTAL_API_BASE_URL "/staff/backend"
env_set "$SP_SETUP" VITE_STAFF_PORTAL_BASE_PATH "/staff/"
if "$ROOT/modules/staff-portal/scripts/configure-env.sh"; then
  echo "    configure-env OK"
else
  echo "warn: staff-portal configure-env failed" >&2
fi

# ----- APM -----
echo
echo "==> APM"
prompt_value APM_DB_DATABASE "APM DB_DATABASE" "$(env_get "$ROOT/modules/apm/.env" DB_DATABASE)"
APM_DB_DATABASE="${APM_DB_DATABASE:-apm_local}"
export APM_APP_URL BASE_URL CI_BASE_URL JWT_SECRET
export DB_HOST DB_PORT DB_USER DB_PASS DB_USERNAME DB_PASSWORD APM_DB_DATABASE
export REDIS_HOST REDIS_PORT REDIS_PASSWORD
export STAFF_API_USERNAME STAFF_API_PASSWORD STAFF_API_TOKEN STAFF_API_INTERNAL_BASE_URL
"$ROOT/scripts/setup/configure-apm-env.sh"

# ----- finance -----
echo
echo "==> finance"
FN_SETUP="$ROOT/modules/finance/setup.env"
FN_ENV="$ROOT/modules/finance/.env"
env_ensure_file "$FN_SETUP" "$ROOT/modules/finance/setup.env.example"
env_ensure_file "$FN_ENV" "$ROOT/modules/finance/.env.example"
prompt_value FN_DB "finance DB_DATABASE" "$(env_get "$FN_SETUP" DB_DATABASE)"
FN_DB="${FN_DB:-finance}"
for f in "$FN_SETUP" "$FN_ENV"; do
  env_set "$f" APP_URL "$FINANCE_APP_URL"
  env_set "$f" BASE_URL "$BASE_URL"
  env_set "$f" FINANCE_STAFF_PORTAL_URL "$STAFF_PORTAL_SPA_URL"
  env_set "$f" VITE_APP_BASE_PATH "/staff/finance/"
  env_set "$f" JWT_SECRET "$JWT_SECRET"
  env_set "$f" STAFF_API_USERNAME "$STAFF_API_USERNAME"
  env_set "$f" STAFF_API_PASSWORD "$STAFF_API_PASSWORD"
  env_set "$f" STAFF_API_TOKEN "$STAFF_API_TOKEN"
  env_set "$f" STAFF_API_INTERNAL_BASE_URL "$STAFF_API_INTERNAL_BASE_URL"
  env_set "$f" DB_DATABASE "$FN_DB"
  apply_db_to_file "$f" DB_USERNAME DB_PASSWORD
  apply_redis_to_file "$f"
done
if "$ROOT/modules/finance/scripts/configure-env.sh"; then
  echo "    configure-env OK"
else
  echo "warn: finance configure-env failed" >&2
fi

# ----- helpdesk -----
echo
echo "==> helpdesk"
HD_SETUP="$ROOT/modules/helpdesk/setup.env"
HD_ENV="$ROOT/modules/helpdesk/backend/.env"
env_ensure_file "$HD_SETUP" "$ROOT/modules/helpdesk/setup.env.example"
env_ensure_file "$HD_ENV" "$ROOT/modules/helpdesk/backend/.env.example"
prompt_value HD_DB "helpdesk DB_DATABASE" "$(env_get "$HD_SETUP" DB_DATABASE)"
HD_DB="${HD_DB:-helpdesk}"
for f in "$HD_SETUP" "$HD_ENV"; do
  env_set "$f" APP_URL "$HELPDESK_APP_URL"
  env_set "$f" BASE_URL "$BASE_URL"
  env_set "$f" HELPDESK_FRONTEND_URL "$HELPDESK_FRONTEND_URL"
  env_set "$f" HELPDESK_STAFF_PORTAL_URL "$STAFF_PORTAL_SPA_URL"
  env_set "$f" HELPDESK_APM_BASE_URL "$APM_BASE_URL"
  env_set "$f" JWT_SECRET "$JWT_SECRET"
  env_set "$f" STAFF_API_USERNAME "$STAFF_API_USERNAME"
  env_set "$f" STAFF_API_PASSWORD "$STAFF_API_PASSWORD"
  env_set "$f" STAFF_API_TOKEN "$STAFF_API_TOKEN"
  env_set "$f" HELPDESK_STAFF_API_INTERNAL_BASE_URL "$HELPDESK_STAFF_API_INTERNAL_BASE_URL"
  env_set "$f" STAFF_API_INTERNAL_BASE_URL "$STAFF_API_INTERNAL_BASE_URL"
  env_set "$f" DB_DATABASE "$HD_DB"
  apply_db_to_file "$f" DB_USERNAME DB_PASSWORD
  apply_redis_to_file "$f"
done
if "$ROOT/modules/helpdesk/scripts/configure-env.sh"; then
  echo "    configure-env OK"
else
  echo "warn: helpdesk configure-env failed" >&2
fi

echo
echo "=== Summary ==="
echo "Deploy=$DEPLOY_MODE  DB=$DB_MODE  Base=$PUBLIC_BASE"
echo "Share internal=$STAFF_API_INTERNAL_BASE_URL"
echo "Redis=$REDIS_HOST:$REDIS_PORT"
echo "DB host=${DB_HOST:-keep}  databases: portal=$SP_DB apm=$APM_DB_DATABASE finance=$FN_DB helpdesk=$HD_DB"
if [[ "$DB_MODE" == "bundled" ]]; then
  echo
  echo "Bundled MySQL: docker compose --env-file docker/.env --profile bundled-db up -d"
fi
if [[ "$DEPLOY_MODE" == "docker" ]]; then
  echo "Stack: docker compose --env-file docker/.env up -d --build"
  echo "Workers (Compose): docker compose --env-file docker/.env --profile workers up -d"
fi

prompt_choice RUN_INSTALL "Run module installers now?" "1) Yes  2) No" "2"
prompt_choice INST_PROFILE "Installer profile" "1) development (setup.sh)  2) production (setup-production.sh)" "1"

if [[ "$RUN_INSTALL" == "1" ]]; then
  if [[ "$INST_PROFILE" == "2" ]]; then
    (cd "$ROOT/modules/staff-portal" && ./setup-production.sh) || echo "warn: staff-portal setup-production failed" >&2
    (cd "$ROOT/modules/finance" && ./setup-production.sh) || echo "warn: finance setup-production failed" >&2
    (cd "$ROOT/modules/helpdesk" && ./setup-production.sh) || echo "warn: helpdesk setup-production failed" >&2
  else
    (cd "$ROOT/modules/staff-portal" && ./setup.sh) || echo "warn: staff-portal setup failed" >&2
    (cd "$ROOT/modules/finance" && ./setup.sh) || echo "warn: finance setup failed" >&2
    (cd "$ROOT/modules/helpdesk" && ./setup.sh) || echo "warn: helpdesk setup failed" >&2
  fi
  if [[ -f "$ROOT/modules/apm/artisan" ]]; then
    (
      cd "$ROOT/modules/apm"
      composer install --no-interaction --prefer-dist || true
      php artisan key:generate --force 2>/dev/null || true
      php artisan jwt:secret --force 2>/dev/null || true
    ) || echo "warn: APM bootstrap failed" >&2
  fi
fi

# ----- systemd -----
SYS_DEFAULT=2
if [[ "$(uname -s)" == "Linux" ]] && command -v systemctl >/dev/null 2>&1; then
  [[ "$INST_PROFILE" == "2" || "$INSTALL_TYPE" == "2" ]] && SYS_DEFAULT=1
fi
if [[ "$DEPLOY_MODE" == "docker" ]]; then
  echo
  echo "Note: Docker deploy usually uses Compose --profile workers instead of host systemd."
fi
prompt_choice RUN_SYSTEMD "Install systemd background workers (queue/scheduler)?" "1) Yes  2) No" "$SYS_DEFAULT"

if [[ "$RUN_SYSTEMD" == "1" ]]; then
  env_set "$SP_SETUP" INSTALL_SYSTEMD "true"
  env_set "$SP_SETUP" STAFF_PORTAL_HEALTH_URL "${PUBLIC_BASE}/backend/up"
  env_set "$SP_SETUP" PHP_BIN "$(command -v php || echo /usr/bin/php)"
  env_set "$HD_SETUP" INSTALL_SYSTEMD "true"
  env_set "$HD_SETUP" PHP_BIN "$(command -v php || echo /usr/bin/php)"

  echo "==> staff-portal systemd"
  if [[ -x "$ROOT/modules/staff-portal/scripts/install-systemd.sh" ]]; then
    STAFF_PORTAL_HEALTH_URL="${PUBLIC_BASE}/backend/up" \
      "$ROOT/modules/staff-portal/scripts/install-systemd.sh" \
      || echo "warn: staff-portal systemd install failed" >&2
  fi

  echo "==> helpdesk systemd"
  if [[ -x "$ROOT/modules/helpdesk/scripts/install-systemd.sh" ]]; then
    "$ROOT/modules/helpdesk/scripts/install-systemd.sh" \
      || echo "warn: helpdesk systemd install failed" >&2
  fi

  echo "==> APM systemd"
  PHP_BIN="$(command -v php || echo /usr/bin/php)" \
    "$ROOT/scripts/setup/install-apm-systemd.sh" \
    || echo "warn: APM systemd install failed" >&2
else
  echo "==> Skipping systemd"
fi

echo
echo "Done. See docs/SETUP.md"
