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
# shellcheck source=scripts/setup/update-htaccess.sh
source "$ROOT/scripts/setup/update-htaccess.sh"
# shellcheck source=scripts/setup/systemd-cleanup.sh
source "$ROOT/scripts/setup/systemd-cleanup.sh"

echo "=== Africa CDC CBP setup ==="
echo "Repo: $ROOT"
echo

DEFAULT_WEB_ROOT="$(basename "$ROOT")"
if [[ ! "$DEFAULT_WEB_ROOT" =~ ^[A-Za-z0-9_-]+$ ]]; then
  DEFAULT_WEB_ROOT=staff
fi
export DEFAULT_WEB_ROOT

SITE_KIND_DEFAULT=1
case "$DEFAULT_WEB_ROOT" in
  *demo*|demo_*|Demo*) SITE_KIND_DEFAULT=2 ;;
esac
prompt_choice SITE_KIND_CHOICE "Site role" "1) Production  2) Demo" "$SITE_KIND_DEFAULT"
if [[ "$SITE_KIND_CHOICE" == "2" ]]; then
  SITE_KIND=demo
else
  SITE_KIND=production
fi
echo "    Site role: $SITE_KIND"
if [[ "$SITE_KIND" == "demo" ]]; then
  echo "    Demo: systemd queue/scheduler workers will NOT be enabled."
fi

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

SETUP_ERRORS=0
setup_warn() {
  echo "warn: $*" >&2
  SETUP_ERRORS=$((SETUP_ERRORS + 1))
  return 0
}

DEFAULT_BASE="$(env_get "$ROOT_ENV" BASE_URL)"
DEFAULT_BASE="${DEFAULT_BASE%/}"
if [[ -z "$DEFAULT_BASE" ]]; then
  if [[ "$DEPLOY_MODE" == "docker" ]]; then
    DEFAULT_BASE="http://localhost:8088/${DEFAULT_WEB_ROOT}"
  else
    DEFAULT_BASE="http://localhost/${DEFAULT_WEB_ROOT}"
  fi
fi

prompt_value PUBLIC_BASE "Public base URL (…/staff, …/cbp, …/demo_cbp)" "$DEFAULT_BASE"
setup_map_urls
echo "    Web folder / Alias: /${WEB_ROOT}"

# CI3 / shared uploads site id — must match this deploy (avoid migrating into another site's tree)
STAFF_SITE_ID_DEFAULT="$(env_get "$ROOT_ENV" STAFF_SITE_ID)"
if [[ -z "$STAFF_SITE_ID_DEFAULT" ]]; then
  STAFF_SITE_ID_DEFAULT="$(setup_derive_site_id "$BASE_URL")"
fi
prompt_value STAFF_SITE_ID "STAFF_SITE_ID (CI3 uploads /var/staffdata/{id})" "$STAFF_SITE_ID_DEFAULT"
STAFF_SITE_ID="${STAFF_SITE_ID:-$STAFF_SITE_ID_DEFAULT}"
setup_apply_storage_paths
echo "    Uploads root: ${STAFF_PORTAL_UPLOADS_ROOT}"
echo "    Data root:    ${STAFF_DATA_ROOT}"

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
env_set "$ROOT_ENV" SITE_KIND "$SITE_KIND"
env_set "$ROOT_ENV" BASE_URL "$BASE_URL"
env_set "$ROOT_ENV" CI_BASE_URL "$CI_BASE_URL"
env_set "$ROOT_ENV" APM_BASE_URL "$APM_BASE_URL"
env_set "$ROOT_ENV" WEB_ROOT "$WEB_ROOT"
env_set "$ROOT_ENV" STAFF_SITE_ID "$STAFF_SITE_ID"
env_set "$ROOT_ENV" STAFF_HOST_DATA_ROOT "$STAFF_HOST_DATA_ROOT"
env_set "$ROOT_ENV" STAFF_DATA_ROOT "$STAFF_DATA_ROOT"
env_set "$ROOT_ENV" STAFF_USE_HOST_STORAGE "true"
env_set "$ROOT_ENV" STAFF_PORTAL_UPLOADS_ROOT "$STAFF_PORTAL_UPLOADS_ROOT"
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
  env_set "$file" DB_HOST "${DB_HOST:-}" || return 1
  env_set "$file" DB_PORT "${DB_PORT:-}" || return 1
  env_set "$file" "$user_key" "${DB_USER:-}" || return 1
  env_set "$file" "$pass_key" "${DB_PASS:-}" || return 1
  return 0
}

apply_redis_to_file() {
  local file="$1"
  env_set "$file" REDIS_HOST "${REDIS_HOST:-}" || return 1
  env_set "$file" REDIS_PORT "${REDIS_PORT:-}" || return 1
  # Important: do not let a failed [[ ]] be the function's last status under set -e
  # when REDIS_PASSWORD is empty (that previously aborted ./setup.sh mid-module).
  if [[ -n "${REDIS_PASSWORD:-}" ]]; then
    env_set "$file" REDIS_PASSWORD "$REDIS_PASSWORD" || return 1
  fi
  return 0
}

# CI3 / host uploads identity — same site id on every module .env
apply_storage_to_file() {
  local file="$1"
  env_set "$file" STAFF_SITE_ID "$STAFF_SITE_ID" || return 1
  env_set "$file" STAFF_HOST_DATA_ROOT "$STAFF_HOST_DATA_ROOT" || return 1
  env_set "$file" STAFF_DATA_ROOT "$STAFF_DATA_ROOT" || return 1
  env_set "$file" STAFF_USE_HOST_STORAGE "true" || return 1
  env_set "$file" STAFF_PORTAL_UPLOADS_ROOT "$STAFF_PORTAL_UPLOADS_ROOT" || return 1
  return 0
}

echo
echo "==> Updating .htaccess public path → /${WEB_ROOT}/"
setup_update_htaccess_tree "$ROOT" "$WEB_ROOT"

# ----- staff-portal -----
echo
echo "==> staff-portal"
SP_SETUP="$ROOT/modules/staff-portal/setup.env"
SP_ENV="$ROOT/modules/staff-portal/backend/.env"
env_ensure_file "$SP_SETUP" "$ROOT/modules/staff-portal/setup.env.example" \
  || setup_warn "staff-portal setup.env missing/unwritable"
env_ensure_file "$SP_ENV" "$ROOT/modules/staff-portal/backend/.env.example" \
  || setup_warn "staff-portal backend/.env missing/unwritable"
prompt_value SP_DB "staff-portal DB_DATABASE" "$(env_get "$SP_SETUP" DB_DATABASE)"
SP_DB="${SP_DB:-staff}"
write_staff_portal_env() {
  local f
  for f in "$SP_SETUP" "$SP_ENV"; do
    env_set "$f" APP_URL "$STAFF_PORTAL_APP_URL" || return 1
    env_set "$f" STAFF_PORTAL_BASE_URL "$STAFF_PORTAL_APP_URL" || return 1
    env_set "$f" STAFF_PORTAL_SPA_URL "$STAFF_PORTAL_SPA_URL" || return 1
    env_set "$f" STAFF_PORTAL_SPA_ENABLED "true" || return 1
    env_set "$f" BASE_URL "$BASE_URL" || return 1
    env_set "$f" APM_BASE_URL "$APM_BASE_URL" || return 1
    env_set "$f" JWT_SECRET "${JWT_SECRET:-}" || return 1
    env_set "$f" DB_DATABASE "$SP_DB" || return 1
    apply_storage_to_file "$f" || return 1
    env_set "$f" STAFF_PORTAL_MODULE_FILES_ROOT "$STAFF_PORTAL_MODULE_FILES_ROOT" || return 1
    apply_db_to_file "$f" DB_USERNAME DB_PASSWORD || return 1
    apply_redis_to_file "$f" || return 1
  done
  env_set "$SP_SETUP" VITE_STAFF_PORTAL_API_BASE_URL "$VITE_STAFF_PORTAL_API_BASE_URL" || return 1
  env_set "$SP_SETUP" VITE_STAFF_PORTAL_BASE_PATH "$VITE_STAFF_PORTAL_BASE_PATH" || return 1
  env_set "$SP_SETUP" SITE_KIND "$SITE_KIND" || return 1
}
if write_staff_portal_env; then
  if "$ROOT/modules/staff-portal/scripts/configure-env.sh"; then
    echo "    configure-env OK"
  else
    setup_warn "staff-portal configure-env failed"
  fi
  write_staff_portal_env || setup_warn "staff-portal force env rewrite failed"
  echo "    forced URLs/JWT/Redis on setup.env + backend/.env"
else
  setup_warn "staff-portal env write failed — fix ownership (e.g. chown) and re-run"
fi

# ----- APM -----
echo
echo "==> APM"
APM_ENV="$ROOT/modules/apm/.env"
prompt_value APM_DB_DATABASE "APM DB_DATABASE" "$(env_get "$APM_ENV" DB_DATABASE)"
APM_DB_DATABASE="${APM_DB_DATABASE:-apm_local}"
export APM_APP_URL BASE_URL CI_BASE_URL JWT_SECRET
export DB_HOST DB_PORT DB_USER DB_PASS DB_USERNAME DB_PASSWORD APM_DB_DATABASE
export REDIS_HOST REDIS_PORT REDIS_PASSWORD
export STAFF_API_USERNAME STAFF_API_PASSWORD STAFF_API_TOKEN STAFF_API_INTERNAL_BASE_URL
if "$ROOT/scripts/setup/configure-apm-env.sh"; then
  echo "    configure-apm-env OK"
else
  setup_warn "APM configure-apm-env failed"
fi
# Force again so empty-skip in configure-apm cannot leave stale APP_URL
if env_set "$APM_ENV" APP_URL "$APM_APP_URL" \
  && env_set "$APM_ENV" BASE_URL "$BASE_URL" \
  && env_set "$APM_ENV" CI_BASE_URL "$CI_BASE_URL" \
  && env_set "$APM_ENV" APM_BASE_URL "$APM_BASE_URL" \
  && env_set "$APM_ENV" JWT_SECRET "${JWT_SECRET:-}" \
  && env_set "$APM_ENV" DB_DATABASE "$APM_DB_DATABASE" \
  && env_set "$APM_ENV" STAFF_API_USERNAME "${STAFF_API_USERNAME:-}" \
  && env_set "$APM_ENV" STAFF_API_PASSWORD "${STAFF_API_PASSWORD:-}" \
  && env_set "$APM_ENV" STAFF_API_TOKEN "${STAFF_API_TOKEN:-}" \
  && env_set "$APM_ENV" STAFF_API_INTERNAL_BASE_URL "$STAFF_API_INTERNAL_BASE_URL" \
  && apply_storage_to_file "$APM_ENV" \
  && env_set "$APM_ENV" STAFF_APM_FILES_ROOT "$STAFF_APM_FILES_ROOT" \
  && apply_db_to_file "$APM_ENV" DB_USERNAME DB_PASSWORD \
  && apply_redis_to_file "$APM_ENV"
then
  echo "    forced URLs/JWT/Redis/storage on modules/apm/.env"
else
  setup_warn "APM env write failed — fix ownership and re-run"
fi

# ----- finance -----
echo
echo "==> finance"
FN_SETUP="$ROOT/modules/finance/setup.env"
FN_ENV="$ROOT/modules/finance/.env"
env_ensure_file "$FN_SETUP" "$ROOT/modules/finance/setup.env.example" \
  || setup_warn "finance setup.env missing/unwritable"
env_ensure_file "$FN_ENV" "$ROOT/modules/finance/.env.example" \
  || setup_warn "finance .env missing/unwritable"
prompt_value FN_DB "finance DB_DATABASE" "$(env_get "$FN_SETUP" DB_DATABASE)"
FN_DB="${FN_DB:-finance}"
write_finance_env() {
  local f
  for f in "$FN_SETUP" "$FN_ENV"; do
    env_set "$f" APP_URL "$FINANCE_APP_URL" || return 1
    env_set "$f" BASE_URL "$BASE_URL" || return 1
    env_set "$f" FINANCE_STAFF_PORTAL_URL "$STAFF_PORTAL_SPA_URL" || return 1
    env_set "$f" VITE_APP_BASE_PATH "$VITE_FINANCE_BASE_PATH" || return 1
    env_set "$f" SESSION_PATH "$FINANCE_SESSION_PATH" || return 1
    env_set "$f" JWT_SECRET "${JWT_SECRET:-}" || return 1
    env_set "$f" STAFF_API_USERNAME "${STAFF_API_USERNAME:-}" || return 1
    env_set "$f" STAFF_API_PASSWORD "${STAFF_API_PASSWORD:-}" || return 1
    env_set "$f" STAFF_API_TOKEN "${STAFF_API_TOKEN:-}" || return 1
    env_set "$f" STAFF_API_INTERNAL_BASE_URL "$STAFF_API_INTERNAL_BASE_URL" || return 1
    env_set "$f" DB_DATABASE "$FN_DB" || return 1
    apply_storage_to_file "$f" || return 1
    apply_db_to_file "$f" DB_USERNAME DB_PASSWORD || return 1
    apply_redis_to_file "$f" || return 1
  done
}
if write_finance_env; then
  if "$ROOT/modules/finance/scripts/configure-env.sh"; then
    echo "    configure-env OK"
  else
    setup_warn "finance configure-env failed"
  fi
  write_finance_env || setup_warn "finance force env rewrite failed"
  echo "    forced URLs/JWT/Redis on setup.env + .env"
else
  setup_warn "finance env write failed — fix ownership and re-run"
fi

# ----- helpdesk -----
echo
echo "==> helpdesk"
HD_SETUP="$ROOT/modules/helpdesk/setup.env"
HD_ENV="$ROOT/modules/helpdesk/backend/.env"
env_ensure_file "$HD_SETUP" "$ROOT/modules/helpdesk/setup.env.example" \
  || setup_warn "helpdesk setup.env missing/unwritable"
env_ensure_file "$HD_ENV" "$ROOT/modules/helpdesk/backend/.env.example" \
  || setup_warn "helpdesk backend/.env missing/unwritable"
prompt_value HD_DB "helpdesk DB_DATABASE" "$(env_get "$HD_SETUP" DB_DATABASE)"
HD_DB="${HD_DB:-helpdesk}"
write_helpdesk_env() {
  local f
  for f in "$HD_SETUP" "$HD_ENV"; do
    env_set "$f" APP_URL "$HELPDESK_APP_URL" || return 1
    env_set "$f" BASE_URL "$BASE_URL" || return 1
    env_set "$f" HELPDESK_FRONTEND_URL "$HELPDESK_FRONTEND_URL" || return 1
    env_set "$f" HELPDESK_STAFF_PORTAL_URL "$STAFF_PORTAL_SPA_URL" || return 1
    env_set "$f" HELPDESK_APM_BASE_URL "$APM_BASE_URL" || return 1
    env_set "$f" JWT_SECRET "${JWT_SECRET:-}" || return 1
    env_set "$f" STAFF_API_USERNAME "${STAFF_API_USERNAME:-}" || return 1
    env_set "$f" STAFF_API_PASSWORD "${STAFF_API_PASSWORD:-}" || return 1
    env_set "$f" STAFF_API_TOKEN "${STAFF_API_TOKEN:-}" || return 1
    env_set "$f" HELPDESK_STAFF_API_INTERNAL_BASE_URL "$HELPDESK_STAFF_API_INTERNAL_BASE_URL" || return 1
    env_set "$f" STAFF_API_INTERNAL_BASE_URL "$STAFF_API_INTERNAL_BASE_URL" || return 1
    env_set "$f" DB_DATABASE "$HD_DB" || return 1
    apply_storage_to_file "$f" || return 1
    env_set "$f" STAFF_HELPDESK_FILES_ROOT "$STAFF_HELPDESK_FILES_ROOT" || return 1
    apply_db_to_file "$f" DB_USERNAME DB_PASSWORD || return 1
    apply_redis_to_file "$f" || return 1
  done
}
if write_helpdesk_env; then
  if "$ROOT/modules/helpdesk/scripts/configure-env.sh"; then
    echo "    configure-env OK"
  else
    setup_warn "helpdesk configure-env failed"
  fi
  write_helpdesk_env || setup_warn "helpdesk force env rewrite failed"
  echo "    forced URLs/JWT/Redis on setup.env + backend/.env"
else
  setup_warn "helpdesk env write failed — fix ownership and re-run"
fi

echo
echo "=== Summary ==="
echo "Site=$SITE_KIND  Deploy=$DEPLOY_MODE  DB=$DB_MODE  Base=$PUBLIC_BASE  WebRoot=/${WEB_ROOT}"
echo "STAFF_SITE_ID=$STAFF_SITE_ID"
echo "CI3 uploads=$STAFF_PORTAL_UPLOADS_ROOT"
echo "Share internal=$STAFF_API_INTERNAL_BASE_URL"
echo "Redis=$REDIS_HOST:$REDIS_PORT"
echo "DB host=${DB_HOST:-keep}  databases: portal=$SP_DB apm=$APM_DB_DATABASE finance=$FN_DB helpdesk=$HD_DB"
if [[ "$SETUP_ERRORS" -gt 0 ]]; then
  echo
  echo "Completed with $SETUP_ERRORS warning(s). Fix reported env permissions and re-run ./setup.sh if needed."
fi
if [[ "$DB_MODE" == "bundled" ]]; then
  echo
  echo "Bundled MySQL: docker compose --env-file docker/.env --profile bundled-db up -d"
fi
if [[ "$DEPLOY_MODE" == "docker" ]]; then
  echo "Stack: docker compose --env-file docker/.env up -d --build"
  echo "Workers (Compose): docker compose --env-file docker/.env --profile workers up -d"
fi

INST_PROFILE_DEFAULT=1
[[ "$SITE_KIND" == "production" ]] && INST_PROFILE_DEFAULT=2
prompt_choice RUN_INSTALL "Run module installers now?" "1) Yes  2) No" "2"
prompt_choice INST_PROFILE "Installer profile" "1) development (setup.sh)  2) production (setup-production.sh)" "$INST_PROFILE_DEFAULT"

if [[ "$RUN_INSTALL" == "1" ]]; then
  export STAFF_SITE_ID STAFF_DATA_ROOT STAFF_HOST_DATA_ROOT STAFF_USE_HOST_STORAGE
  export STAFF_PORTAL_UPLOADS_ROOT STAFF_APM_FILES_ROOT STAFF_HELPDESK_FILES_ROOT
  export STAFF_PORTAL_MODULE_FILES_ROOT BASE_URL SITE_KIND
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
if [[ "$SITE_KIND" == "demo" ]]; then
  echo
  echo "==> Demo site: disabling/skipping systemd workers"
  env_set "$SP_SETUP" INSTALL_SYSTEMD "false" 2>/dev/null || true
  env_set "$HD_SETUP" INSTALL_SYSTEMD "false" 2>/dev/null || true
  if [[ "$(uname -s)" == "Linux" ]] && command -v systemctl >/dev/null 2>&1; then
    echo "    Retiring any existing CBP systemd units on this host (demo must not run queues)"
    _retire=(
      staff-portal.target staff-portal-queue.service staff-portal-scheduler.service
      staff-portal-scheduler.timer staff-portal-health.service staff-portal-health.timer
      helpdesk.target helpdesk-queue.service helpdesk-scheduler.service
      helpdesk-scheduler.timer helpdesk-health.service helpdesk-health.timer
      laravel-queue-apm.service laravel-scheduler.service laravel-queue-worker.service
      laravel-queue-cleanup.service laravel12-queue-apm.service
    )
    local_f=""
    for local_f in \
      /etc/systemd/system/staff-portal-*-"${WEB_ROOT}".service \
      /etc/systemd/system/staff-portal-*-"${WEB_ROOT}".timer \
      /etc/systemd/system/staff-portal-"${WEB_ROOT}".target \
      /etc/systemd/system/helpdesk-*-"${WEB_ROOT}".service \
      /etc/systemd/system/helpdesk-*-"${WEB_ROOT}".timer \
      /etc/systemd/system/helpdesk-"${WEB_ROOT}".target \
      /etc/systemd/system/laravel-*-"${WEB_ROOT}".service
    do
      [[ -e "$local_f" ]] || continue
      _retire+=("$(basename "$local_f")")
    done
    systemd_retire_units "${_retire[@]}" || true
  fi
  echo "==> Skipping systemd install (demo)"
elif [[ "$DEPLOY_MODE" == "docker" ]]; then
  echo
  echo "Note: Docker deploy usually uses Compose --profile workers instead of host systemd."
  echo "==> Skipping host systemd (Docker)"
else
  SYS_DEFAULT=2
  if [[ "$(uname -s)" == "Linux" ]] && command -v systemctl >/dev/null 2>&1; then
    if [[ "$SITE_KIND" == "production" ]]; then
      if [[ "$INST_PROFILE" == "2" || "$INSTALL_TYPE" == "2" ]]; then
        SYS_DEFAULT=1
      fi
    fi
  fi
  prompt_choice RUN_SYSTEMD "Install systemd background workers (queue/scheduler)?" "1) Yes  2) No" "$SYS_DEFAULT"

  if [[ "$RUN_SYSTEMD" == "1" ]]; then
    PHP_BIN_RESOLVED="$(command -v php || echo /usr/bin/php)"
    SP_BACKEND="$(cd "$ROOT/modules/staff-portal/backend" && pwd)"
    HD_BACKEND="$(cd "$ROOT/modules/helpdesk/backend" && pwd)"
    APM_ABS="$(cd "$ROOT/modules/apm" && pwd)"
    SP_HEALTH="${PUBLIC_BASE}/backend/up"
    HD_HEALTH="${PUBLIC_BASE}/helpdesk/backend/api/v1/health"

    env_set "$SP_SETUP" INSTALL_SYSTEMD "true"
    env_set "$SP_SETUP" STAFF_PORTAL_HEALTH_URL "$SP_HEALTH"
    env_set "$SP_SETUP" PHP_BIN "$PHP_BIN_RESOLVED"
    env_set "$SP_SETUP" WEB_ROOT "$WEB_ROOT"
    env_set "$HD_SETUP" INSTALL_SYSTEMD "true"
    env_set "$HD_SETUP" PHP_BIN "$PHP_BIN_RESOLVED"
    env_set "$HD_SETUP" WEB_ROOT "$WEB_ROOT"
    env_set "$HD_SETUP" HELPDESK_HEALTH_URL "$HD_HEALTH"

    echo "==> staff-portal systemd (ROOT=$SP_BACKEND WEB_ROOT=$WEB_ROOT)"
    if [[ -x "$ROOT/modules/staff-portal/scripts/install-systemd.sh" ]]; then
      WEB_ROOT="$WEB_ROOT" \
      STAFF_PORTAL_ROOT="$SP_BACKEND" \
      STAFF_PORTAL_HEALTH_URL="$SP_HEALTH" \
      PHP_BIN="$PHP_BIN_RESOLVED" \
        "$ROOT/modules/staff-portal/scripts/install-systemd.sh" \
        || echo "warn: staff-portal systemd install failed" >&2
    fi

    echo "==> helpdesk systemd (ROOT=$HD_BACKEND WEB_ROOT=$WEB_ROOT)"
    if [[ -x "$ROOT/modules/helpdesk/scripts/install-systemd.sh" ]]; then
      WEB_ROOT="$WEB_ROOT" \
      HELPDESK_ROOT="$HD_BACKEND" \
      HELPDESK_HEALTH_URL="$HD_HEALTH" \
      PHP_BIN="$PHP_BIN_RESOLVED" \
        "$ROOT/modules/helpdesk/scripts/install-systemd.sh" \
        || echo "warn: helpdesk systemd install failed" >&2
    fi

    echo "==> APM systemd (ROOT=$APM_ABS WEB_ROOT=$WEB_ROOT)"
    WEB_ROOT="$WEB_ROOT" \
    APM_ROOT="$APM_ABS" \
    PHP_BIN="$PHP_BIN_RESOLVED" \
      "$ROOT/scripts/setup/install-apm-systemd.sh" \
      || echo "warn: APM systemd install failed" >&2
  else
    echo "==> Skipping systemd"
  fi
fi

echo
echo "Done. See docs/SETUP.md"
if [[ "$SETUP_ERRORS" -gt 0 ]]; then
  exit 1
fi
exit 0
