#!/bin/bash
set -e

# Default Render PORT to 80 if not defined
PORT="${PORT:-80}"
echo "==> Configuring Nginx on Port: $PORT"

# Replace ${PORT} in Nginx template
envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/sites-available/default
ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

# Ensure storage and database directories exist
mkdir -p /var/www/html/storage/app/media_temp \
         /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache \
         /var/www/html/database

# Ensure database.sqlite file exists
touch /var/www/html/database/database.sqlite

# Fix ownership and permissions for Laravel storage, bootstrap cache, and database
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

# Run database migrations if database is connected
echo "==> Running Laravel Migrations..."
php /var/www/html/artisan migrate --force || echo "Warning: Migration failed or database not ready yet."
chown -R www-data:www-data /var/www/html/database /var/www/html/storage
chmod -R 775 /var/www/html/database /var/www/html/storage

# Cache configuration, routes, and views for maximum performance
echo "==> Caching Laravel configuration..."
php /var/www/html/artisan config:cache || true
php /var/www/html/artisan route:cache || true
php /var/www/html/artisan view:cache || true

echo "==> Starting Supervisord (PHP-FPM, Nginx, Queue Worker)..."
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
