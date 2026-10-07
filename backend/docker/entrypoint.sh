#!/bin/bash
set -e

# Default Render PORT to 80 if not defined
PORT="${PORT:-80}"
echo "==> Configuring Nginx on Port: $PORT"

# Replace ${PORT} in Nginx template
envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/sites-available/default
ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

# Ensure storage directories exist
mkdir -p /var/www/html/storage/app/media_temp \
         /var/www/html/storage/framework/cache \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

# If SQLite is configured and database file missing, initialize it
if [ "${DB_CONNECTION:-}" = "sqlite" ] && [ ! -f "/var/www/html/database/database.sqlite" ]; then
    touch /var/www/html/database/database.sqlite
    chown www-data:www-data /var/www/html/database/database.sqlite
fi

# Fix ownership and permissions for Laravel storage
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Run database migrations if database is connected
echo "==> Running Laravel Migrations..."
php /var/www/html/artisan migrate --force || echo "Warning: Migration failed or database not ready yet."

# Cache configuration, routes, and views for maximum performance
echo "==> Caching Laravel configuration..."
php /var/www/html/artisan config:cache || true
php /var/www/html/artisan route:cache || true
php /var/www/html/artisan view:cache || true

echo "==> Starting Supervisord (PHP-FPM, Nginx, Queue Worker)..."
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
