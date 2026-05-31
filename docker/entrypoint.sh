#!/bin/sh
set -e

# Sync built assets from backup to the mounted public volume
echo "Syncing public assets to Caddy volume..."
mkdir -p /var/www/public
cp -rp /var/www_backup/public/. /var/www/public/

# Verify directory permissions
echo "Checking directory permissions..."
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Create storage symlink if it doesn't exist
if [ ! -L /var/www/public/storage ]; then
    echo "Creating storage symlink in public directory..."
    ln -sf /var/www/storage/app/public /var/www/public/storage
fi

# Cache configurations and routes for production speed
echo "Caching configurations..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run database migrations
echo "Running database migrations..."
php artisan migrate --force

# Execute the main container command (FPM or custom cmd)
echo "Starting application..."
exec "$@"
