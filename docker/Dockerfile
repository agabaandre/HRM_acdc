FROM php:8.3-apache-bookworm

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

LABEL org.opencontainers.image.description="Africa CDC staff portal (CI) + APM (Laravel)"

RUN apt-get update && apt-get install -y --no-install-recommends \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    unzip \
    git \
    curl \
    mariadb-client \
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
    && docker-php-ext-enable opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Recommended PHP limits for Laravel / uploads
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

WORKDIR /var/www/html

COPY docker/entrypoint.sh /usr/local/bin/staff-entrypoint.sh
RUN chmod +x /usr/local/bin/staff-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/staff-entrypoint.sh"]
