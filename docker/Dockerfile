# syntax=docker/dockerfile:1
#
# Runtime: PHP 8.2-FPM + Apache event MPM (proxy_fcgi).
# Heavy APM PDF/Office tools are OFF by default (INSTALL_PDF_TOOLS=1).
#
#   docker compose --env-file docker/.env up -d --build

FROM php:8.2-fpm-bookworm AS runtime

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

LABEL org.opencontainers.image.description="Africa CDC CBP (staff-portal, APM, finance, helpdesk)"

# 0 = fast local/dev image (default). 1 = Ghostscript + Poppler + LibreOffice Writer.
ARG INSTALL_PDF_TOOLS=0

RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt,sharing=locked \
    set -eux; \
    rm -f /etc/apt/apt.conf.d/docker-clean; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        apache2 \
        unzip \
        git \
        curl \
        mariadb-client \
        redis-tools; \
    if [ "$INSTALL_PDF_TOOLS" = "1" ]; then \
        # Headless Writer only (no GNOME/desktop stack). Word→PDF for APM annex.
        apt-get install -y --no-install-recommends \
            ghostscript \
            poppler-utils \
            libreoffice-writer-nogui \
            fonts-liberation \
            fonts-dejavu-core; \
    fi; \
    install-php-extensions \
        gd \
        intl \
        mysqli \
        opcache \
        pdo_mysql \
        pcntl \
        bcmath \
        exif \
        zip \
        redis; \
    a2dismod mpm_prefork mpm_worker 2>/dev/null || true; \
    a2enmod mpm_event proxy proxy_fcgi rewrite headers setenvif; \
    mkdir -p /var/run/php; \
    chown www-data:www-data /var/run/php

RUN rm -f /usr/local/etc/php-fpm.d/www.conf /usr/local/etc/php-fpm.d/www.conf.default 2>/dev/null || true

COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-staff-www.conf
COPY docker/php/staff.ini /usr/local/etc/php/conf.d/staff.ini
COPY docker/apache/mpm_event.conf /etc/apache2/conf-available/cbp-mpm-event.conf
COPY docker/apache/000-staff.conf /etc/apache2/sites-available/000-staff.conf

RUN a2enconf cbp-mpm-event \
    && a2dissite 000-default.conf 2>/dev/null || true \
    && a2ensite 000-staff.conf \
    && printf '%s\n' 'ServerName localhost' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername \
    && printf '%s\n' \
        '#!/bin/bash' \
        'set -e' \
        'rm -f /var/run/apache2/apache2.pid' \
        'exec apachectl -D FOREGROUND' \
        > /usr/local/bin/apache2-foreground \
    && chmod +x /usr/local/bin/apache2-foreground

WORKDIR /var/www/staff

COPY docker/entrypoint.sh /usr/local/bin/staff-entrypoint.sh
RUN chmod +x /usr/local/bin/staff-entrypoint.sh

# Thin layer: Supervisord for compose profile `workers` (keeps PHP extension layer cached).
RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt,sharing=locked \
    apt-get update \
    && apt-get install -y --no-install-recommends supervisor \
    && mkdir -p /var/log/supervisor \
    && rm -rf /var/lib/apt/lists/*

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/staff-entrypoint.sh"]
CMD ["apache2-foreground"]

# Production bake: copy tree into the image (no bind-mount) + prod OPcache.
FROM runtime AS prod
COPY docker/php/staff-prod.ini /usr/local/etc/php/conf.d/z-staff-prod.ini
COPY . /var/www/staff
RUN chown -R www-data:www-data \
    /var/www/staff/modules/apm/storage \
    /var/www/staff/modules/apm/bootstrap/cache \
    /var/www/staff/modules/finance/backend/storage \
    /var/www/staff/modules/finance/backend/bootstrap/cache \
    /var/www/staff/modules/helpdesk/backend/storage \
    /var/www/staff/modules/helpdesk/backend/bootstrap/cache \
    /var/www/staff/modules/staff-portal/backend/storage \
    /var/www/staff/modules/staff-portal/backend/bootstrap/cache \
    /var/www/staff/modules/risk-register/backend/storage \
    /var/www/staff/modules/risk-register/backend/bootstrap/cache \
    2>/dev/null || true
