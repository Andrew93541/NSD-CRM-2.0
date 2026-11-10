#!/usr/bin/env bash
set -euo pipefail

APP_DIR="/var/www/html"
cd "${APP_DIR}"

# 1. Bind Apache dynamically to Render's $PORT (defaults to 10000)
LISTEN_PORT="${PORT:-10000}"
sed -ri "s/Listen [0-9]+/Listen ${LISTEN_PORT}/g" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${LISTEN_PORT}>/g" /etc/apache2/sites-available/000-default.conf

# 2. Hard safety check: never allow a Render demo container to point at nsd_crm_2
rm -f "${APP_DIR}/.env"
if [[ "${DB_DATABASE:-}" == "nsd_crm_2" ]] || [[ "${DB_URL:-}" == *"nsd_crm_2"* ]]; then
    echo "CRITICAL SAFETY ABORT: Render demo container must never connect to [nsd_crm_2]." >&2
    exit 1
fi
if [[ "${DB_DATABASE:-}" != "nsd_crm_demo" ]]; then
    echo "CRITICAL SAFETY ABORT: Render demo container requires DB_DATABASE=nsd_crm_demo (got [${DB_DATABASE:-}])." >&2
    exit 1
fi

# 3. Ensure writable runtime directories exist
mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# 4. Require APP_KEY in production/demo deployment
if [[ -z "${APP_KEY:-}" ]]; then
    echo "ERROR: APP_KEY environment variable is not set." >&2
    exit 1
fi

# 5. Cache Laravel configuration, routes, and views for fast cold starts
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction

# 6. Optional schema migration on startup (safe only on isolated demo DB)
if [[ "${RENDER_AUTO_MIGRATE:-false}" == "true" ]]; then
    echo "Running database migrations on isolated demo database [${DB_DATABASE:-}]..."
    php artisan migrate --force --no-interaction
fi

# 7. Optional idempotent synthetic demo seed on startup
if [[ "${RENDER_AUTO_SEED_DEMO:-false}" == "true" ]]; then
    echo "Running idempotent RenderDemoSeeder on [${DB_DATABASE:-}]..."
    php artisan nsd:seed-demo --no-interaction
fi

echo "Starting Apache on port ${LISTEN_PORT}..."
exec apache2-foreground

