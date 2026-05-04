#!/bin/sh
set -e

echo "Patching .env for Docker..."
sed -i 's/^DB_HOST=.*/DB_HOST=postgres/' .env
sed -i 's/^DB_USERNAME=.*/DB_USERNAME=insider/' .env
sed -i 's/^DB_PASSWORD=.*/DB_PASSWORD=secret/' .env
sed -i 's/^DB_DATABASE=.*/DB_DATABASE=insider_one/' .env

echo "Clearing all caches..."
php artisan optimize:clear

echo "Running migrations..."
php artisan migrate --force

echo "Running seeders..."
php artisan db:seed --force

echo "Starting application..."
exec "$@"
