# ─────────────────────────────────────────────
# Stage 1: Composer dependencies
# ─────────────────────────────────────────────
FROM composer:2.7 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --ignore-platform-reqs

COPY . .

RUN composer dump-autoload \
    --no-dev \
    --classmap-authoritative \
    --ignore-platform-reqs


# ─────────────────────────────────────────────
# Stage 2: Node / Vite build (frontend assets)
# Skip this stage if you have no frontend yet
# ─────────────────────────────────────────────
FROM node:20-alpine AS frontend

WORKDIR /app

COPY package*.json ./
RUN npm ci --ignore-scripts

COPY . .
COPY --from=vendor /app/vendor ./vendor

RUN npm run build


# ─────────────────────────────────────────────
# Stage 3: Final PHP-FPM image
# ─────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS app

# System deps
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
    redis \
    shadow \
    supervisor \
    unzip \
    zip

# PHP extensions
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

# Redis PHP extension (PECL)
RUN pecl install redis && docker-php-ext-enable redis

# PHP ini tweaks for production
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini

# Create a non-root user that nginx can share
RUN addgroup -g 1000 -S www && adduser -u 1000 -S www -G www

WORKDIR /var/www

# Copy app code
COPY --chown=www:www . .

# Vendor from Stage 1
COPY --chown=www:www --from=vendor /app/vendor ./vendor

# Built assets from Stage 2 (comment out if no frontend yet)
# COPY --chown=www:www --from=frontend /app/public/build ./public/build

# Directories Laravel needs to write to
RUN mkdir -p storage/framework/{cache,sessions,views} \
             storage/logs \
             bootstrap/cache \
 && chown -R www:www storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

# Supervisor manages php-fpm + horizon in a single container
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

USER www

EXPOSE 9000

CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
