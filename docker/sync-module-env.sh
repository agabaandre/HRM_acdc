#!/usr/bin/env bash
# Sync shared staff DB + Docker URLs from repo root .env into all Laravel module .env files.
set -euo pipefail

ROOT="${STAFF_ROOT:-/var/www/html}"
ENV_FILE="${ROOT}/.env"

if [[ ! -f "${ENV_FILE}" ]]; then
    echo "sync-module-env: ${ENV_FILE} not found — copy docker/compose.env.example to .env first."
    exit 1
fi

# shellcheck disable=SC1090
set -a
source <(grep -E '^[A-Z_]+=' "${ENV_FILE}" | sed 's/\r$//')
set +a

if [[ -n "${STAFF_ROOT:-}" ]]; then
    # Host Apache / Valet — connect to local MySQL, not Docker bridge.
    DB_HOST="${DB_HOST:-127.0.0.1}"
    if [[ "${DB_HOST}" == "host.docker.internal" ]]; then
        DB_HOST="127.0.0.1"
    fi
else
    DB_HOST="${DB_HOST:-host.docker.internal}"
    # Inside Docker: 127.0.0.1 is the container, not the host MySQL.
    if [[ "${DB_HOST}" == "127.0.0.1" || "${DB_HOST}" == "localhost" ]]; then
        DB_HOST="host.docker.internal"
    fi
fi
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
STAFF_DB="${DB_NAME:-staff}"
# All modules share the staff schema; APM_DB_NAME is the CI3 second connection (same DB).
APM_DB="${APM_DB_NAME:-${STAFF_DB}}"
APP_PORT="${APP_PORT:-8080}"
DOCKER_BASE_URL="${DOCKER_BASE_URL:-http://localhost:${APP_PORT}}"
JWT_SECRET="${JWT_SECRET:-}"

set_env_key() {
    local file="$1"
    local key="$2"
    local val="$3"

    if [[ ! -f "${file}" ]]; then
        echo "sync-module-env: skip missing ${file}"
        return 0
    fi

    local tmp="${file}.tmp.$$"
    awk -v key="${key}" -v val="${val}" '
        BEGIN { found = 0 }
        $0 ~ "^" key "=" {
            print key "=" val
            found = 1
            next
        }
        { print }
        END {
            if (!found) {
                print key "=" val
            }
        }
    ' "${file}" > "${tmp}"
    mv "${tmp}" "${file}"
}

upsert_env() {
    local file="$1"
    shift
    local pairs=("$@")
    for pair in "${pairs[@]}"; do
        local key="${pair%%=*}"
        local val="${pair#*=}"
        set_env_key "${file}" "${key}" "${val}"
    done
}

upsert_env "${ROOT}/apm/.env" \
    "DB_CONNECTION=mysql" \
    "DB_HOST=${DB_HOST}" \
    "DB_PORT=${DB_PORT}" \
    "DB_DATABASE=${STAFF_DB}" \
    "DB_USERNAME=${DB_USER}" \
    "DB_PASSWORD=${DB_PASS}" \
    "DB_MIGRATIONS_TABLE=apm_migrations" \
    "DB_QUEUE_TABLE=apm_queue_jobs" \
    "DB_QUEUE_BATCHES_TABLE=apm_job_batches" \
    "DB_QUEUE_FAILED_TABLE=apm_failed_jobs" \
    "STAFF_DB_HOST=${DB_HOST}" \
    "STAFF_DB_PORT=${DB_PORT}" \
    "STAFF_DB_DATABASE=${STAFF_DB}" \
    "STAFF_DB_USERNAME=${DB_USER}" \
    "STAFF_DB_PASSWORD=${DB_PASS}" \
    "APP_URL=${DOCKER_BASE_URL}/apm" \
    "BASE_URL=${DOCKER_BASE_URL}/" \
    "CI_BASE_URL=${DOCKER_BASE_URL}"

if [[ -n "${JWT_SECRET}" ]]; then
    upsert_env "${ROOT}/apm/.env" "JWT_SECRET=${JWT_SECRET}"
fi

upsert_env "${ROOT}/finance/.env" \
    "DB_CONNECTION=mysql" \
    "DB_HOST=${DB_HOST}" \
    "DB_PORT=${DB_PORT}" \
    "DB_DATABASE=${STAFF_DB}" \
    "DB_USERNAME=${DB_USER}" \
    "DB_PASSWORD=${DB_PASS}" \
    "DB_MIGRATIONS_TABLE=finance_migrations" \
    "DB_QUEUE_TABLE=finance_queue_jobs" \
    "DB_QUEUE_BATCHES_TABLE=finance_job_batches" \
    "DB_QUEUE_FAILED_TABLE=finance_failed_jobs" \
    "APP_URL=${DOCKER_BASE_URL}/finance" \
    "BASE_URL=${DOCKER_BASE_URL}/" \
    "VITE_APP_BASE_PATH=/finance/"

if [[ -n "${JWT_SECRET}" ]]; then
    upsert_env "${ROOT}/finance/.env" "JWT_SECRET=${JWT_SECRET}"
fi

upsert_env "${ROOT}/helpdesk/backend/.env" \
    "DB_CONNECTION=mysql" \
    "DB_HOST=${DB_HOST}" \
    "DB_PORT=${DB_PORT}" \
    "DB_DATABASE=${STAFF_DB}" \
    "DB_USERNAME=${DB_USER}" \
    "DB_PASSWORD=${DB_PASS}" \
    "DB_MIGRATIONS_TABLE=helpdesk_migrations" \
    "DB_QUEUE_TABLE=helpdesk_queue_jobs" \
    "DB_QUEUE_BATCHES_TABLE=helpdesk_job_batches" \
    "DB_QUEUE_FAILED_TABLE=helpdesk_failed_jobs" \
    "APP_URL=${DOCKER_BASE_URL}/helpdesk/backend" \
    "HELPDESK_FRONTEND_URL=${DOCKER_BASE_URL}/helpdesk" \
    "HELPDESK_STAFF_PORTAL_URL=${DOCKER_BASE_URL}/" \
    "HELPDESK_APM_BASE_URL=${DOCKER_BASE_URL}/apm" \
    "BASE_URL=${DOCKER_BASE_URL}/" \
    "VITE_HELPDESK_API_BASE_URL=/helpdesk/backend"

if [[ -n "${JWT_SECRET}" ]]; then
    upsert_env "${ROOT}/helpdesk/backend/.env" "JWT_SECRET=${JWT_SECRET}"
fi

upsert_env "${ROOT}/staff-portal/.env" \
    "DB_CONNECTION=mysql" \
    "DB_HOST=${DB_HOST}" \
    "DB_PORT=${DB_PORT}" \
    "DB_DATABASE=${STAFF_DB}" \
    "DB_USERNAME=${DB_USER}" \
    "DB_PASSWORD=${DB_PASS}" \
    "DB_MIGRATIONS_TABLE=staff_portal_migrations" \
    "DB_QUEUE_TABLE=sp_queue_jobs" \
    "DB_QUEUE_BATCHES_TABLE=sp_job_batches" \
    "DB_QUEUE_FAILED_TABLE=sp_failed_jobs" \
    "APP_URL=${DOCKER_BASE_URL}/staff-portal" \
    "STAFF_PORTAL_BASE_URL=${DOCKER_BASE_URL}/staff-portal/" \
    "BASE_URL=${DOCKER_BASE_URL}/"

if [[ -n "${JWT_SECRET}" ]]; then
    upsert_env "${ROOT}/staff-portal/.env" "JWT_SECRET=${JWT_SECRET}"
fi

echo "sync-module-env: all modules → database '${STAFF_DB}' on ${DB_HOST} (apm_migrations, finance_migrations, helpdesk_migrations, staff_portal_migrations)."
