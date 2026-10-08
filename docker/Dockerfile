# Production image for the Laravel app itself. Queue worker and scheduler
# containers (see docker-compose.yml) reuse this same image with a
# different command — one build, three roles (web/worker/scheduler) — so
# they can never drift out of sync with each other's code.

FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache \
        icu-dev libzip-dev libpng-dev oniguruma-dev mysql-client \
    && docker-php-ext-install \
        pdo_mysql intl zip gd bcmath opcache \
    && apk del icu-dev libzip-dev libpng-dev oniguruma-dev

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize \
    && php artisan event:cache \
    && php artisan route:cache \
    && php artisan view:cache

# Runs as a non-root user — §43: least-privilege, not convenience-first.
RUN addgroup -g 1000 appuser && adduser -D -u 1000 -G appuser appuser \
    && chown -R appuser:appuser /var/www/html/storage /var/www/html/bootstrap/cache
USER appuser

EXPOSE 9000
CMD ["php-fpm"]
