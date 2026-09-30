#!/usr/bin/env bash
#
# Install the queue worker as a systemd service.
#
# Every notification in this app implements ShouldQueue, so without a worker
# running they are written to the queue and never sent — approvals, payment
# rejections, session decisions and password confirmations all go quiet with no
# error anywhere. This installs a supervised worker so that cannot happen.
#
# Run from the application root on the server:
#
#   sudo bash scripts/setup-queue-worker.sh
#
# Re-running is safe: it rewrites the unit and restarts the service.

set -euo pipefail

SERVICE_NAME="ahaic-queue"
UNIT="/etc/systemd/system/${SERVICE_NAME}.service"

# --- Work out the environment rather than assuming it -----------------------

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if [[ ! -f "${APP_DIR}/artisan" ]]; then
    echo "!! ${APP_DIR} does not look like a Laravel app (no artisan)." >&2
    exit 1
fi

if [[ ! -f "${APP_DIR}/.env" ]]; then
    echo "!! No .env in ${APP_DIR}. The worker needs the same environment as the site." >&2
    exit 1
fi

PHP_BIN="$(command -v php || true)"
[[ -n "${PHP_BIN}" ]] || { echo "!! php not found on PATH." >&2; exit 1; }

# The worker must run as the same user as the web server, or it will create
# cache and log files the site cannot read.
if id -u www-data >/dev/null 2>&1; then
    RUN_USER="www-data"
elif id -u apache >/dev/null 2>&1; then
    RUN_USER="apache"
else
    RUN_USER="$(stat -c '%U' "${APP_DIR}/storage" 2>/dev/null || echo root)"
fi

QUEUE_CONNECTION="$(grep -E '^QUEUE_CONNECTION=' "${APP_DIR}/.env" | tail -1 | cut -d= -f2- | tr -d '"'"'"' ' || true)"
QUEUE_CONNECTION="${QUEUE_CONNECTION:-database}"

echo "About to install ${SERVICE_NAME}:"
echo "  app directory : ${APP_DIR}"
echo "  php binary    : ${PHP_BIN}"
echo "  run as user   : ${RUN_USER}"
echo "  queue driver  : ${QUEUE_CONNECTION}"
echo

# The database driver needs its tables; the worker would crash-loop without them.
if [[ "${QUEUE_CONNECTION}" == "database" ]]; then
    if ! "${PHP_BIN}" "${APP_DIR}/artisan" migrate:status 2>/dev/null | grep -q "create_jobs_table"; then
        echo "!! QUEUE_CONNECTION=database but the jobs table migration has not run."
        echo "   Run 'php artisan migrate --force' first, then re-run this script." >&2
        exit 1
    fi
fi

# --- Install ---------------------------------------------------------------

cat > "${UNIT}" <<UNITFILE
[Unit]
Description=AHAIC Partner Portal queue worker
After=network.target

[Service]
User=${RUN_USER}
Group=${RUN_USER}
Restart=always
# --max-time makes the worker exit hourly on purpose, to release any memory it
# has leaked. Restart=always is what brings it straight back, so a restart in
# the logs is normal and not a sign of failure.
RestartSec=5
WorkingDirectory=${APP_DIR}
ExecStart=${PHP_BIN} ${APP_DIR}/artisan queue:work --sleep=3 --tries=3 --max-time=3600 --backoff=5
StandardOutput=append:${APP_DIR}/storage/logs/queue-worker.log
StandardError=append:${APP_DIR}/storage/logs/queue-worker.log

[Install]
WantedBy=multi-user.target
UNITFILE

touch "${APP_DIR}/storage/logs/queue-worker.log"
chown "${RUN_USER}:${RUN_USER}" "${APP_DIR}/storage/logs/queue-worker.log"

systemctl daemon-reload
systemctl enable "${SERVICE_NAME}" >/dev/null
systemctl restart "${SERVICE_NAME}"

sleep 2

echo "--- status ---"
systemctl --no-pager --lines=0 status "${SERVICE_NAME}" || true

if systemctl is-active --quiet "${SERVICE_NAME}"; then
    echo
    echo "Queue worker is running."
    echo "  logs:    tail -f ${APP_DIR}/storage/logs/queue-worker.log"
    echo "  restart: sudo systemctl restart ${SERVICE_NAME}"
    echo "  backlog: php artisan queue:monitor ${QUEUE_CONNECTION}:default"
else
    echo
    echo "!! Service did not stay running. Check:" >&2
    echo "   journalctl -u ${SERVICE_NAME} -n 50 --no-pager" >&2
    exit 1
fi
