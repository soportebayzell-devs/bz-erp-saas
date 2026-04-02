# ─────────────────────────────────────────────
# Stage 1: Composer dependencies
# ─────────────────────────────────────────────
FROM composer:2.7 AS vendor

WORKDIR /app

# Copy composer files — lock is optional (generated on first build if missing)
COPY composer.json ./
COPY composer.lock* ./

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
# Stage 2: Final PHP-FPM image
# (Frontend/Vite stage omitted — add back when
#  React frontend is scaffolded in Phase 1)
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

# PHP ini tweaks
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini

# Non-root user
RUN addgroup -g 1000 -S www && adduser -u 1000 -S www -G www

WORKDIR /var/www

# Copy full app code from build context
COPY --chown=www:www . .

# Vendor from Stage 1
COPY --chown=www:www --from=vendor /app/vendor ./vendor

# Ensure all required Laravel directories exist
RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
        public \
 && chown -R www:www storage bootstrap/cache public \
 && chmod -R 775 storage bootstrap/cache

# Ensure public/index.php exists (Laravel entry point)
# If it was not in the repo, create a placeholder that explains the issue clearly
RUN if [ ! -f public/index.php ]; then \
        echo "<?php echo 'Laravel bootstrap missing — run setup.sh first.'; die(1);" > public/index.php; \
    fi

# Supervisor: php-fpm + horizon
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

USER www

EXPOSE 9000

CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
