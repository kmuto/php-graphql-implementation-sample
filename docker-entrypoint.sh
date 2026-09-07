#!/bin/bash
set -e

mkdir -p /var/www/data

if [ ! -f /var/www/data/database.sqlite ]; then
    touch /var/www/data/database.sqlite
fi

chown -R www-data:www-data /var/www/data
chmod -R 777 /var/www/storage /var/www/bootstrap/cache 2>/dev/null || true

cd /var/www
php artisan cache:clear
php artisan lighthouse:clear-cache 2>/dev/null || true
php artisan migrate --force
php artisan db:seed --force

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
