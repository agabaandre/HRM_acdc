#!/usr/bin/env bash
set -euo pipefail

STAFF_ROOT="${STAFF_ROOT:-/var/www/staff}"
REDIS_HOST="${REDIS_HOST:-redis}"
REDIS_PORT="${REDIS_PORT:-6379}"

# Bind mounts from macOS often use restrictive modes; Apache runs as www-data.
if [[ -d "${STAFF_ROOT}" ]]; then
    echo "staff-entrypoint: chmod -R a+rX on ${STAFF_ROOT} (bind mount; first run may take ~30s)..."
    chmod -R a+rX "${STAFF_ROOT}" 2>/dev/null || true
fi

fix_laravel_writable() {
    local app_root="$1"
    if [[ -d "${app_root}/storage" ]]; then
        mkdir -p "${app_root}/storage" "${app_root}/bootstrap/cache"
        chown -R www-data:www-data "${app_root}/storage" "${app_root}/bootstrap/cache" 2>/dev/null || true
        chmod -R ug+rwx "${app_root}/storage" "${app_root}/bootstrap/cache" 2>/dev/null || true
    fi
}

fix_laravel_writable "${STAFF_ROOT}/modules/apm"
fix_laravel_writable "${STAFF_ROOT}/modules/finance"
fix_laravel_writable "${STAFF_ROOT}/modules/helpdesk/backend"
fix_laravel_writable "${STAFF_ROOT}/modules/staff-portal/backend"

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

# Workers / one-shots skip Apache configtest when CMD is not apache.
if [[ "${1:-}" == "apache2-foreground" ]] || [[ "${1:-}" == "apache2ctl" ]]; then
    apache2ctl configtest
fi

exec "$@"
