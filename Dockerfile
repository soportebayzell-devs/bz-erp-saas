# ─────────────────────────────────────────────
# Stage 1: Composer dependencies
# ─────────────────────────────────────────────
FROM composer:2.7 AS vendor

WORKDIR /app

COPY composer.json ./
COPY composer.lock* ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --ignore-platform-reqs

COPY . .

# bootstrap/cache must exist before dump-autoload triggers package:discover
RUN mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/logs

RUN composer dump-autoload \
    --no-dev \
    --classmap-authoritative \
    --no-scripts \
    --ignore-platform-reqs


# ─────────────────────────────────────────────
# Stage 2: Final PHP-FPM image
# ─────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS app

RUN apk add --no-cache \
    bash \
    curl \
    git \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    libzip-dev \
    oniguruma-dev \
    postgresql-dev \
    shadow \
    supervisor \
    unzip \
    zip

RUN docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install -j$(nproc) \
        bcmath \
        gd \
        mbstring \
        opcache \
        pdo \
        pdo_pgsql \
        pcntl \
        zip

RUN apk add --no-cache --virtual .phpize-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .phpize-deps

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini

RUN addgroup -g 1000 -S www && adduser -u 1000 -S www -G www

WORKDIR /var/www

COPY --chown=www:www . .

COPY --chown=www:www --from=vendor /app/vendor ./vendor

RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
        public \
        /var/log/supervisor \
 && chown -R www:www storage bootstrap/cache public \
 && chmod -R 775 storage bootstrap/cache

RUN if [ ! -f public/index.php ]; then \
        printf '<?php\nuse Illuminate\\Http\\Request;\ndefine("LARAVEL_START", microtime(true));\nif (file_exists($maintenance = __DIR__."/../storage/framework/maintenance.php")) { require $maintenance; }\nrequire __DIR__."/../vendor/autoload.php";\n(require_once __DIR__."/../bootstrap/app.php")->handleRequest(Request::capture());\n' > public/index.php; \
    fi

COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

USER www

EXPOSE 9000

CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
