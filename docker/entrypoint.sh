#!/bin/sh
# Prepares the SQLite database and Laravel caches, then starts the web server.
set -e

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set. Generate one with:" >&2
    echo "  docker compose -f compose.production.yaml run --rm --no-deps --entrypoint php app artisan key:generate --show" >&2
    echo "and put it into .env.production." >&2
    exit 1
fi

DB_FILE="${DB_DATABASE:-/srv/data/database.sqlite}"

if [ ! -w "$(dirname "$DB_FILE")" ]; then
    echo "$(dirname "$DB_FILE") is not writable by uid $(id -u). On the host: sudo chown $(id -u):$(id -g) ./data" >&2
    exit 1
fi

if [ ! -f "$DB_FILE" ]; then
    echo "Creating new database at $DB_FILE"
    touch "$DB_FILE"
fi

php artisan migrate --force --no-interaction
php artisan optimize
php artisan filament:optimize

exec "$@"
