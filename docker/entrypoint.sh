#!/usr/bin/env bash
# Container entrypoint. Commands:
#   web       run nginx + php-fpm (default)
#   queue     run `artisan queue:work`
#   scheduler run `artisan schedule:work`
#   migrate   run migrations then exit (one-shot: compose service / Copilot job)
#   artisan … forward args to `php artisan`
#   *         exec whatever was passed
set -euo pipefail

APP_DIR=/var/www/html
cd "$APP_DIR"

hydrate_db_secret() {
    # AWS Secrets Manager hands Aurora credentials over as a single JSON blob
    # ({host, port, dbname, username, password, ...}). Laravel wants discrete
    # DB_* vars, so expand it here. Values already set in the manifest win —
    # this only fills in what is missing (in practice host and password).
    [[ -n "${DB_SECRET:-}" ]] || return 0

    local exports
    if ! exports="$(php -r '
        $secret = json_decode(getenv("DB_SECRET"), true);

        if (! is_array($secret)) {
            fwrite(STDERR, "DB_SECRET is not valid JSON\n");
            exit(1);
        }

        $map = [
            "host" => "DB_HOST",
            "port" => "DB_PORT",
            "dbname" => "DB_DATABASE",
            "username" => "DB_USERNAME",
            "password" => "DB_PASSWORD",
        ];

        foreach ($map as $key => $name) {
            if (! isset($secret[$key]) || $secret[$key] === "") {
                continue;
            }

            if (getenv($name) !== false && getenv($name) !== "") {
                continue;
            }

            printf("export %s=%s\n", $name, escapeshellarg((string) $secret[$key]));
        }
    ')"; then
        echo "!! could not expand DB_SECRET" >&2
        exit 1
    fi

    eval "$exports"
    unset DB_SECRET
    echo "→ DB credentials loaded from DB_SECRET (host=${DB_HOST:-unset})"
}

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
        hydrate_db_secret
        wait_for_db
        prime_app
        # Migrations are deliberately NOT run here. They run as a one-shot task
        # (`copilot job run --name migrate`, or the `migrate` compose service),
        # so that scaling web to N tasks cannot start N concurrent migrations.
        exec supervisord -c /etc/supervisord.conf
        ;;
    queue)
        hydrate_db_secret
        wait_for_db
        exec su-exec www-data php artisan queue:work redis \
            --sleep=3 --tries=3 --max-time=3600 --backoff=5
        ;;
    scheduler)
        hydrate_db_secret
        wait_for_db
        exec su-exec www-data php artisan schedule:work
        ;;
    migrate)
        hydrate_db_secret
        wait_for_db
        # --force: non-interactive, required outside local/dev.
        # --isolated: takes an atomic lock so a second task cannot migrate
        # concurrently if this one is ever invoked twice.
        exec php artisan migrate --force --isolated
        ;;
    artisan)
        hydrate_db_secret
        shift
        exec su-exec www-data php artisan "$@"
        ;;
    *)
        exec "$@"
        ;;
esac
