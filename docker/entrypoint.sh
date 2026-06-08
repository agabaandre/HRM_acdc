#!/usr/bin/env bash
set -e

if [[ -d /var/www/html ]]; then
    echo "staff-entrypoint: chmod -R a+rX on /var/www/html (bind mount; first run may take ~30s)..."
    chmod -R a+rX /var/www/html 2>/dev/null || true
fi

for storage_path in \
    /var/www/html/apm/storage \
    /var/www/html/apm/bootstrap/cache \
    /var/www/html/finance/storage \
    /var/www/html/finance/bootstrap/cache \
    /var/www/html/helpdesk/backend/storage \
    /var/www/html/helpdesk/backend/bootstrap/cache \
    /var/www/html/staff-portal/storage \
    /var/www/html/staff-portal/bootstrap/cache
do
    if [[ -d "${storage_path}" ]]; then
        chown -R www-data:www-data "${storage_path}" 2>/dev/null || true
        chmod -R ug+rwx "${storage_path}" 2>/dev/null || true
    fi
done

if [[ "${AUTO_SYNC_MODULE_ENV:-1}" == "1" && -x /usr/local/bin/sync-module-env.sh && -f /var/www/html/.env ]]; then
    /usr/local/bin/sync-module-env.sh || echo "staff-entrypoint: sync-module-env skipped (missing module .env files?)"
fi

apache2ctl configtest
exec apache2-foreground
