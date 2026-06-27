#!/bin/bash
set -e

if ! php artisan about --no-ansi > /dev/null 2>&1; then
    echo "ERROR: Application failed to boot. Ensure APP_KEY is set in the environment or .env file." >&2
    exit 1
fi

php artisan config:cache --no-ansi 2>/dev/null || true
php artisan route:cache --no-ansi 2>/dev/null || true
php artisan view:cache --no-ansi 2>/dev/null || true

cron

exec apache2-foreground
