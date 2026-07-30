#!/bin/sh
set -eu

if [ -z "${APP_KEY:-}" ]; then
  echo "APP_KEY is required. Generate it locally with: php artisan key:generate --show" >&2
  exit 1
fi

mkdir -p storage/app/public storage/framework/cache storage/framework/sessions \
  storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

php artisan storage:link --force
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan migrate --force

if [ "${RUN_SEEDER:-false}" = "true" ]; then
  php artisan db:seed --force
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
