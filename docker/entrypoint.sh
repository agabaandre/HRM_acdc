#!/usr/bin/env bash
set -euo pipefail

STAFF_ROOT="${STAFF_ROOT:-/var/www/staff}"
REDIS_HOST="${REDIS_HOST:-redis}"
REDIS_PORT="${REDIS_PORT:-6379}"

# Only fix Laravel writable dirs — never chmod the whole bind-mount tree (slow on macOS).
fix_laravel_writable() {
    local app_root="$1"
    if [[ -d "${app_root}/storage" ]]; then
        mkdir -p "${app_root}/storage" "${app_root}/bootstrap/cache"
        chown -R www-data:www-data "${app_root}/storage" "${app_root}/bootstrap/cache" 2>/dev/null || true
        chmod -R ug+rwx "${app_root}/storage" "${app_root}/bootstrap/cache" 2>/dev/null || true
    fi
}

fix_laravel_writable "${STAFF_ROOT}/modules/apm"
fix_laravel_writable "${STAFF_ROOT}/modules/finance/backend"
fix_laravel_writable "${STAFF_ROOT}/modules/helpdesk/backend"
fix_laravel_writable "${STAFF_ROOT}/modules/staff-portal/backend"
fix_laravel_writable "${STAFF_ROOT}/modules/risk-register/backend"

# Supervisor worker logs (compose profile workers)
for _log_root in \
    "${STAFF_ROOT}/modules/apm/storage/logs" \
    "${STAFF_ROOT}/modules/finance/backend/storage/logs" \
    "${STAFF_ROOT}/modules/helpdesk/backend/storage/logs" \
    "${STAFF_ROOT}/modules/staff-portal/backend/storage/logs" \
    "${STAFF_ROOT}/modules/risk-register/backend/storage/logs"
do
    mkdir -p "${_log_root}" 2>/dev/null || true
    chown www-data:www-data "${_log_root}" 2>/dev/null || true
done
mkdir -p /var/log/supervisor /var/run 2>/dev/null || true

# Optional one-shot: STAFF_CHMOD_TREE=1 chmod -R a+rX (avoid unless you hit 403s).
if [[ "${STAFF_CHMOD_TREE:-0}" == "1" && -d "${STAFF_ROOT}" ]]; then
    echo "staff-entrypoint: STAFF_CHMOD_TREE=1 — chmod -R a+rX on ${STAFF_ROOT}..."
    chmod -R a+rX "${STAFF_ROOT}" 2>/dev/null || true
fi

if [[ "${SKIP_REDIS_WAIT:-0}" != "1" ]]; then
    echo "staff-entrypoint: waiting for Redis at ${REDIS_HOST}:${REDIS_PORT}..."
    for _ in $(seq 1 60); do
        if redis-cli -h "${REDIS_HOST}" -p "${REDIS_PORT}" ping 2>/dev/null | grep -qi PONG; then
            echo "staff-entrypoint: Redis is up"
            break
        fi
        sleep 1
    done
fi

# Start php-fpm only when serving HTTP via Apache (not for supervisord workers).
start_php_fpm_if_needed() {
    case "${1:-}" in
        apache2-foreground|apachectl|apache2)
            ;;
        *)
            return 0
            ;;
    esac
    mkdir -p /var/run/php
    chown www-data:www-data /var/run/php 2>/dev/null || true
    if ! pgrep -x php-fpm >/dev/null 2>&1; then
        echo "staff-entrypoint: starting php-fpm..."
        php-fpm --daemonize
    fi
    local i
    for i in $(seq 1 50); do
        if [[ -S /var/run/php/php-fpm.sock ]]; then
            echo "staff-entrypoint: php-fpm socket ready"
            return 0
        fi
        sleep 0.2
    done
    echo "staff-entrypoint: error — php-fpm socket missing" >&2
    return 1
}

start_php_fpm_if_needed "${1:-}"

# Workers / one-shots skip Apache configtest when CMD is not apache.
if [[ "${1:-}" == "apache2-foreground" ]] || [[ "${1:-}" == "apachectl" ]] || [[ "${1:-}" == "apache2" ]]; then
    apache2ctl configtest
fi

exec "$@"
