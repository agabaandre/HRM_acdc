# syntax=docker/dockerfile:1
#
# Fast default runtime: PHP 8.2 + Apache + extensions via prebuilt
# install-php-extensions (avoids compiling from source).
# Heavy APM PDF/Office tools are OFF by default (INSTALL_PDF_TOOLS=1).
#
#   docker compose --env-file docker/.env up -d --build

FROM php:8.2-apache-bookworm AS runtime

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
        unzip \
        git \
        curl \
        mariadb-client \
        redis-tools; \
    if [ "$INSTALL_PDF_TOOLS" = "1" ]; then \
        apt-get install -y --no-install-recommends \
            ghostscript \
            poppler-utils \
            libreoffice-writer \
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
        redis

RUN { \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.validate_timestamps=1'; \
    echo 'upload_max_filesize=64M'; \
    echo 'post_max_size=64M'; \
    echo 'memory_limit=512M'; \
} > /usr/local/etc/php/conf.d/staff.ini

RUN a2enmod rewrite headers

COPY docker/apache/000-staff.conf /etc/apache2/sites-available/000-staff.conf
RUN a2dissite 000-default.conf 2>/dev/null || true \
    && a2ensite 000-staff.conf

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

# Production bake: copy tree into the image (no bind-mount).
FROM runtime AS prod
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
