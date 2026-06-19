#!/bin/sh
set -e

if [ -z "$APP_KEY" ]; then
    echo "ERREUR: APP_KEY n'est pas defini." >&2
    echo "Generer une cle avec 'php artisan key:generate --show' et la mettre dans le fichier d'env de docker-compose.prod.yaml." >&2
    exit 1
fi

if [ "$DB_CONNECTION" = "mysql" ] && [ -n "$DB_HOST" ]; then
    echo "Attente de MySQL sur $DB_HOST:${DB_PORT:-3306}..."
    until php -r "new PDO('mysql:host=$DB_HOST;port=${DB_PORT:-3306}', '$DB_USERNAME', '$DB_PASSWORD');" >/dev/null 2>&1; do
        sleep 1
    done
fi

php artisan migrate --force
php artisan config:cache
php artisan route:cache

php-fpm -D
exec nginx -g "daemon off;"
