# syntax=docker/dockerfile:1

FROM composer:2 AS composer-bin

FROM php:8.2-fpm AS base
WORKDIR /var/www/html
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev unzip git libicu-dev libsqlite3-dev \
    && docker-php-ext-install pdo_mysql pdo_sqlite bcmath intl zip pcntl \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer-bin /usr/bin/composer /usr/bin/composer

# ---------------------------------------------------------------------------
# dev : php artisan serve, code monté en volume (voir docker-compose.yml)
# ---------------------------------------------------------------------------
FROM base AS dev
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-interaction
COPY . .
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

# ---------------------------------------------------------------------------
# build : vendor/ optimisé pour la prod
# ---------------------------------------------------------------------------
FROM base AS build
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --optimize-autoloader
COPY . .

# ---------------------------------------------------------------------------
# prod : nginx + php-fpm dans la même image, sert public/ directement.
# Le conteneur "gateway" du docker-compose prod fait juste un reverse proxy
# HTTP vers le port 80 ici (aucune connaissance du préfixe /planning requise
# côté Laravel).
# ---------------------------------------------------------------------------
FROM php:8.2-fpm AS prod
WORKDIR /var/www/html
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev unzip libicu-dev nginx \
    && docker-php-ext-install pdo_mysql bcmath intl zip pcntl \
    && rm -rf /var/lib/apt/lists/*
COPY --from=build /var/www/html /var/www/html
COPY docker/nginx-site.conf /etc/nginx/sites-available/default
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache
EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]
