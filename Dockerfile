# syntax=docker/dockerfile:1.7
# ---------- Stage 1: composer deps ----------
FROM composer:2 AS composer_deps
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev --prefer-dist --no-scripts --no-autoloader \
        --no-interaction --no-progress

# ---------- Stage 2: node build ----------
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci --no-audit --no-fund
COPY . .
RUN npm run build

# ---------- Stage 3: runtime (php-fpm) ----------
FROM php:8.4-fpm-alpine AS runtime

# System deps
RUN apk add --no-cache \
        bash git curl unzip icu-libs libzip libpng libjpeg-turbo freetype \
        oniguruma libxml2 tzdata mysql-client redis su-exec supervisor nginx \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS icu-dev libzip-dev libpng-dev libjpeg-turbo-dev \
        freetype-dev oniguruma-dev libxml2-dev linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath gd intl mbstring opcache pcntl pdo_mysql zip xml \
    && pecl install redis && docker-php-ext-enable redis \
    && apk del .build-deps

# Composer for post-copy autoload dump
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# PHP + FPM + Nginx configs
COPY docker/php/php.ini       /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/www.conf      /usr/local/etc/php-fpm.d/zz-www.conf
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf  /etc/supervisord.conf
COPY docker/entrypoint.sh     /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# App source
WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --from=composer_deps --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets        --chown=www-data:www-data /app/public/build ./public/build

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER root
EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["web"]
