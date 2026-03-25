#!/bin/bash
# =============================================================================
# DSR System — cPanel Deployment Script
# Server: dsr.pinnaclekenyaprojects.com
# Run this via cPanel Terminal or SSH as the tosnaxan user
# =============================================================================

set -e  # Exit immediately on any error

APP_DIR="/home/tosnaxan/dsr/backend"
WEB_ROOT="/home/tosnaxan/public_html/dsr.pinnaclekenyaprojects.com"

# Use PHP 8.2 if available (cPanel EA4 path), otherwise default php
if [ -f "/usr/local/bin/ea-php82" ]; then
    PHP="/usr/local/bin/ea-php82"
elif [ -f "/opt/cpanel/ea-php82/root/usr/bin/php" ]; then
    PHP="/opt/cpanel/ea-php82/root/usr/bin/php"
else
    PHP="php"
fi
echo "==> PHP binary: $PHP ($($PHP -r 'echo PHP_VERSION;'))"

echo "==> Checking app directory..."
if [ ! -d "$APP_DIR" ]; then
    echo "ERROR: $APP_DIR does not exist."
    echo "Upload the backend/ folder to /home/tosnaxan/dsr/ first, then re-run."
    exit 1
fi

cd "$APP_DIR"

# -----------------------------------------------------------------------------
# 1. PHP dependencies
# -----------------------------------------------------------------------------
echo ""
echo "==> Installing PHP dependencies (production, no dev)..."

# Find composer — try common cPanel locations
if command -v composer &>/dev/null; then
    COMPOSER="composer"
elif [ -f "/usr/local/bin/composer" ]; then
    COMPOSER="/usr/local/bin/composer"
elif [ -f "$HOME/composer.phar" ]; then
    COMPOSER="$PHP $HOME/composer.phar"
else
    echo "==> Composer not found. Downloading composer.phar..."
    curl -sS https://getcomposer.org/installer | $PHP -- --install-dir="$HOME" --filename=composer.phar
    COMPOSER="$PHP $HOME/composer.phar"
fi

echo "==> Using: $COMPOSER"
$COMPOSER install --no-dev --optimize-autoloader --no-interaction

# -----------------------------------------------------------------------------
# 2. .env check
# -----------------------------------------------------------------------------
echo ""
echo "==> Checking .env..."
if [ ! -f "$APP_DIR/.env" ]; then
    echo "ERROR: .env file not found."
    echo "Create it at $APP_DIR/.env with your production values, then re-run."
    exit 1
fi

# -----------------------------------------------------------------------------
# 3. App key (only generate if not already set)
# -----------------------------------------------------------------------------
APP_KEY_VALUE=$(grep "^APP_KEY=" "$APP_DIR/.env" | cut -d'=' -f2)
if [ -z "$APP_KEY_VALUE" ]; then
    echo ""
    echo "==> Generating app key..."
    $PHP artisan key:generate --force
else
    echo ""
    echo "==> App key already set, skipping."
fi

# -----------------------------------------------------------------------------
# 4. Migrations
# -----------------------------------------------------------------------------
echo ""
echo "==> Running database migrations..."
$PHP artisan migrate --force

# -----------------------------------------------------------------------------
# 5. Permissions
# -----------------------------------------------------------------------------
echo ""
echo "==> Setting storage and cache permissions..."
chmod -R 775 storage bootstrap/cache

# -----------------------------------------------------------------------------
# 6. Storage symlink (points into web root)
# -----------------------------------------------------------------------------
STORAGE_LINK="$WEB_ROOT/storage"
if [ -L "$STORAGE_LINK" ]; then
    echo ""
    echo "==> Storage symlink already exists, skipping."
else
    echo ""
    echo "==> Creating storage symlink..."
    ln -s "$APP_DIR/storage/app/public" "$STORAGE_LINK"
fi

# -----------------------------------------------------------------------------
# 7. Artisan caches (production performance)
# -----------------------------------------------------------------------------
echo ""
echo "==> Caching config, routes, and views..."
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache

# -----------------------------------------------------------------------------
# 8. Done
# -----------------------------------------------------------------------------
echo ""
echo "============================================================"
echo " Deployment complete!"
echo " Visit: https://dsr.pinnaclekenyaprojects.com"
echo ""
echo " If you see errors, check:"
echo "   tail -50 $APP_DIR/storage/logs/laravel.log"
echo "============================================================"
