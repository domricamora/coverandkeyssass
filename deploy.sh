#!/usr/bin/env bash
# Repeatable deploy for cPanel (SSH / Terminal) or a VPS. Run from the app root:
#   bash deploy.sh
# First install: follow docs/DEPLOYMENT.md, then use this for every update.
set -euo pipefail

PHP="${PHP:-php}"            # cPanel often needs e.g. /opt/cpanel/ea-php83/root/usr/bin/php
COMPOSER="${COMPOSER:-composer}"

echo "==> Maintenance mode (secret bypass link printed below)"
SECRET="$(head -c 16 /dev/urandom | od -An -tx1 | tr -d ' \n')"
$PHP artisan down --retry=30 --secret="$SECRET" || true
echo "    Bypass: ${APP_URL:-https://your-domain.com}/$SECRET"

trap '$PHP artisan up' EXIT

echo "==> Code"
git pull --ff-only

echo "==> PHP dependencies (no dev packages)"
$COMPOSER install --no-dev --prefer-dist --optimize-autoloader --no-interaction

echo "==> Front-end assets"
if command -v npm >/dev/null 2>&1; then
    npm ci && npm run build
else
    echo "    npm not available: upload public/build from a local 'npm run build'."
fi

echo "==> Database"
$PHP artisan migrate --force

echo "==> Caches"
$PHP artisan optimize:clear
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan event:cache

echo "==> Storage link + permissions"
$PHP artisan storage:link 2>/dev/null || true
chmod -R ug+rwX storage bootstrap/cache

echo "==> Restart queue workers (they pick up new code on their next job)"
$PHP artisan queue:restart

echo "==> Done. Health: ${APP_URL:-https://your-domain.com}/up"
