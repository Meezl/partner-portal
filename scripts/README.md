# Server scripts

## `setup-queue-worker.sh`

Installs the queue worker as a systemd service.

Every notification in this app implements `ShouldQueue`, so **without a running
worker they are written to the queue and never delivered** — approvals, payment
rejections, session decisions and password confirmations all go quiet, with no
error raised anywhere. `failed_jobs` stays empty because nothing fails; the jobs
simply wait.

Run once on the server, from the application root:

```bash
sudo bash scripts/setup-queue-worker.sh
```

It works out the app directory, PHP binary, web user and queue driver rather
than assuming them, and refuses to install if `QUEUE_CONNECTION=database` but
the `jobs` table is missing — which would otherwise leave the worker in a crash
loop. Re-running is safe.

### Checking it afterwards

```bash
systemctl status ahaic-queue
```

```bash
php artisan queue:monitor database:default
```

A growing backlog means jobs are arriving but nothing is draining them.

```bash
tail -f storage/logs/queue-worker.log
```

### One thing that looks like a fault but is not

`--max-time=3600` makes the worker exit every hour on purpose, to release any
memory it has leaked, and systemd restarts it. Regular restarts in the log are
expected — judge health by the queue backlog, not by uptime.

### After a deploy

The worker holds application code in memory, so it must be restarted whenever
code changes or it will keep running the old version:

```bash
sudo systemctl restart ahaic-queue
```

Add that to your deploy step.
