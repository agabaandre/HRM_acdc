# PHP 8.4-FPM app + queue worker image
# Uses mlocati install-php-extensions (faster/more reliable than pecl on production)

FROM php:8.4-fpm-bookworm

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_AUDIT=false

# Faster, reliable extension installer (pdo_mysql, redis, opcache, …)
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        curl \
        zip \
        ca-certificates \
    && install-php-extensions \
        pdo_mysql \
        mbstring \
        bcmath \
        opcache \
        redis \
    && rm -rf /var/lib/apt/lists/*

RUN mkdir -p /var/log/php /var/log/php-fpm \
    && chown -R www-data:www-data /var/log/php /var/log/php-fpm

COPY deploy/configs/php-production.ini /usr/local/etc/php/conf.d/99-production.ini
COPY deploy/configs/php-fpm-www.conf /usr/local/etc/php-fpm.d/zz-email-server.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/backend

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
