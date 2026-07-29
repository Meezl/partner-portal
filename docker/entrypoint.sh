#!/usr/bin/env bash
# Container entrypoint. Commands:
#   web       run nginx + php-fpm (default)
#   queue     run `artisan queue:work`
#   scheduler run `artisan schedule:work`
#   migrate   run migrations then exit (used as a one-shot compose service)
#   artisan … forward args to `php artisan`
#   *         exec whatever was passed
set -euo pipefail

APP_DIR=/var/www/html
cd "$APP_DIR"

wait_for_db() {
    # Only try if DB env is present
    [[ -n "${DB_HOST:-}" ]] || return 0
    echo "→ waiting for MySQL at ${DB_HOST}:${DB_PORT:-3306}"
    for _ in $(seq 1 60); do
        if mysqladmin ping -h "$DB_HOST" -P "${DB_PORT:-3306}" \
                             -u "$DB_USERNAME" -p"${DB_PASSWORD:-}" --silent 2>/dev/null; then
            echo "→ MySQL is up"
            return 0
        fi
        sleep 2
    done
    echo "!! MySQL did not become available in time" >&2
    exit 1
}

prime_app() {
    # Storage + cache dirs writable
    mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache
    chown -R www-data:www-data storage bootstrap/cache

    # Public storage symlink (idempotent)
    php artisan storage:link || true

    # Config/route/view caches — rebuilt every start so container is stateless
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
}

case "${1:-web}" in
    web)
        wait_for_db
        prime_app
        # Only the web container runs migrations. Set RUN_MIGRATIONS=false on
        # queue/scheduler services to avoid concurrent migration runs.
        if [[ "${RUN_MIGRATIONS:-true}" == "true" ]]; then
            php artisan migrate --force
        fi
        exec supervisord -c /etc/supervisord.conf
        ;;
    queue)
        wait_for_db
        exec su-exec www-data php artisan queue:work redis \
            --sleep=3 --tries=3 --max-time=3600 --backoff=5
        ;;
    scheduler)
        wait_for_db
        exec su-exec www-data php artisan schedule:work
        ;;
    migrate)
        wait_for_db
        exec php artisan migrate --force
        ;;
    artisan)
        shift
        exec su-exec www-data php artisan "$@"
        ;;
    *)
        exec "$@"
        ;;
esac
