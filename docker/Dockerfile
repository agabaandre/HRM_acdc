# syntax=docker/dockerfile:1

FROM php:8.2-apache-bookworm AS runtime

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

LABEL org.opencontainers.image.description="Africa CDC CBP (staff-portal, APM, finance, helpdesk)"

# PDF annex (APM): Ghostscript + Poppler; LibreOffice for Word attachments.
# redis-tools: entrypoint health wait via redis-cli.
RUN apt-get update && apt-get install -y --no-install-recommends \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    $PHPIZE_DEPS \
    unzip \
    git \
    curl \
    mariadb-client \
    redis-tools \
    ghostscript \
    poppler-utils \
    libreoffice-writer \
    fonts-liberation \
    fonts-dejavu-core \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        gd \
        intl \
        mysqli \
        opcache \
        pdo_mysql \
        pcntl \
        bcmath \
        exif \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis opcache \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

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

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/staff-entrypoint.sh"]
CMD ["apache2-foreground"]

# Production bake: copy tree into the image (no bind-mount).
FROM runtime AS prod
COPY . /var/www/staff
RUN chown -R www-data:www-data \
    /var/www/staff/modules/apm/storage \
    /var/www/staff/modules/apm/bootstrap/cache \
    /var/www/staff/modules/finance/storage \
    /var/www/staff/modules/finance/bootstrap/cache \
    /var/www/staff/modules/helpdesk/backend/storage \
    /var/www/staff/modules/helpdesk/backend/bootstrap/cache \
    /var/www/staff/modules/staff-portal/backend/storage \
    /var/www/staff/modules/staff-portal/backend/bootstrap/cache \
    2>/dev/null || true
