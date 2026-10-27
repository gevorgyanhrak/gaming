# syntax=docker/dockerfile:1

# ---- base: PHP runtime + extensions shared by every backend process (fpm, reverb, horizon, scheduler)
FROM php:8.5-fpm-alpine AS base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql redis intl zip pcntl bcmath gd opcache \
    && apk add --no-cache mysql-client

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/api

# ---- dev: source code is bind-mounted by compose.yaml
FROM base AS dev
COPY docker/php/php-dev.ini /usr/local/etc/php/conf.d/zz-app.ini

# ---- prod: immutable image with code and optimized autoloader baked in
FROM base AS prod
COPY docker/php/php-prod.ini /usr/local/etc/php/conf.d/zz-app.ini

COPY apps/api/composer.json apps/api/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY apps/api/ ./
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    # Admin panel CSS/JS/fonts are generated (gitignored); composer scripts are skipped above.
    && php artisan filament:assets \
    && rm -f .env \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
