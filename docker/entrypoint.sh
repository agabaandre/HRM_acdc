#!/usr/bin/env bash
set -e

# Bind mounts from macOS often use restrictive modes; Apache runs as www-data and
# cannot read files owned by your Mac UID → 403 / "search permissions" errors.
if [[ -d /var/www/html ]]; then
    echo "staff-entrypoint: chmod -R a+rX on /var/www/html (bind mount; first run may take ~30s)..."
    chmod -R a+rX /var/www/html 2>/dev/null || true
fi

# Writable Laravel paths when bind-mounting from the host
if [[ -d /var/www/html/apm/storage ]]; then
    chown -R www-data:www-data /var/www/html/apm/storage /var/www/html/apm/bootstrap/cache 2>/dev/null || true
    chmod -R ug+rwx /var/www/html/apm/storage /var/www/html/apm/bootstrap/cache 2>/dev/null || true
fi

apache2ctl configtest
exec apache2-foreground
