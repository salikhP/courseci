#!/bin/sh
set -eu

if [ ! -f .env ]; then
    cp .env.example .env
fi

if ! grep -Eq '^APP_KEY=.+$' .env; then
    php artisan key:generate --force --no-interaction
fi

php artisan config:clear --no-interaction
php artisan migrate --force --no-interaction

exec "$@"
