# Deployment — AWS EC2 + Docker + GitHub Actions

CI/CD path: **push to `main` → GitHub Actions builds a Docker image → pushes to
GHCR → SSHes into the EC2 host → `docker compose pull && up -d`**. Caddy on the
host terminates TLS (Let's Encrypt) and reverse-proxies to the `app` container.

---

## 1. Provision the EC2 host

**AMI:** Ubuntu 24.04 LTS (Canonical, owner `099720109477`). Anything else and
the PHP layer's system libraries won't match.

**Instance type:** `t3.small` fine for staging, `t3.medium`+ for prod.

**Security group inbound:** 22 (your IP only), 80, 443.

**DNS:** point `portal.example.com` `A` record at the elastic IP.

## 2. Bootstrap the host (run once, as `ubuntu`)

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y ca-certificates curl gnupg ufw fail2ban
```

```bash
# Firewall
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw --force enable
sudo systemctl enable --now fail2ban
```

```bash
# Docker Engine + compose plugin (official repo)
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg \
  | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
  https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo $VERSION_CODENAME) stable" \
  | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo systemctl enable --now docker
sudo usermod -aG docker ubuntu   # re-login for group to apply
```

```bash
# Deploy user for CI SSH (does not need sudo)
sudo adduser --disabled-password --gecos "" deploy
sudo usermod -aG docker deploy
sudo install -d -m 700 -o deploy -g deploy /home/deploy/.ssh
sudo tee /home/deploy/.ssh/authorized_keys >/dev/null <<'EOF'
ssh-ed25519 AAAA...paste-CI-public-key... deploy@github-actions
EOF
sudo chown deploy:deploy /home/deploy/.ssh/authorized_keys
sudo chmod 600 /home/deploy/.ssh/authorized_keys
```

## 3. App directory + `.env`

```bash
sudo install -d -o deploy -g deploy /opt/ahaic-partner-portal
sudo -u deploy git clone https://github.com/YOUR-ORG/ahaic-partner-portal.git /opt/ahaic-partner-portal
cd /opt/ahaic-partner-portal
```

Create `/opt/ahaic-partner-portal/.env` (owned by `deploy`, mode 600). Replace
every placeholder:

```bash
sudo -u deploy tee /opt/ahaic-partner-portal/.env >/dev/null <<'EOF'
IMAGE=ghcr.io/YOUR-ORG/ahaic-partner-portal:latest

APP_DOMAIN=portal.example.com
APP_KEY=                              # fill after first `key:generate` below

DB_HOST=your-rds-endpoint.rds.amazonaws.com
DB_PORT=3306
DB_DATABASE=ahaic_portal
DB_USERNAME=ahaic
DB_PASSWORD=change-me

REDIS_HOST=redis                       # or your ElastiCache endpoint
REDIS_PORT=6379

FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=eu-west-1
AWS_BUCKET=ahaic-portal-uploads

MAIL_MAILER=ses
MAIL_FROM_ADDRESS=no-reply@example.com
EOF
sudo chmod 600 /opt/ahaic-partner-portal/.env
```

## 4. GitHub → GHCR → EC2 credentials

**GitHub Environment secrets** (`Settings → Environments → production`):

| Name              | Value                                                            |
| ----------------- | ---------------------------------------------------------------- |
| `EC2_HOST`        | Public DNS or IP of the instance                                 |
| `EC2_USER`        | `deploy`                                                         |
| `EC2_SSH_KEY`     | Private key matching the pubkey in `~deploy/.ssh/authorized_keys` |
| `EC2_PORT`        | `22` (optional)                                                  |
| `GHCR_USER`       | Any GitHub user/org with **read** on the package                 |
| `GHCR_READ_TOKEN` | Classic PAT with `read:packages` scope                           |

`GITHUB_TOKEN` (used to *push* the image) is provided automatically to the
workflow because `packages: write` is granted in `deploy.yml`.

## 5. First deploy (one-off, on the host)

Generate an `APP_KEY` using the image itself:

```bash
sudo -u deploy bash -c 'echo "${GHCR_READ_TOKEN}" | docker login ghcr.io -u "${GHCR_USER}" --password-stdin'
sudo -u deploy docker pull ghcr.io/YOUR-ORG/ahaic-partner-portal:latest

APP_KEY=$(sudo -u deploy docker run --rm ghcr.io/YOUR-ORG/ahaic-partner-portal:latest \
          artisan key:generate --show)
sudo -u deploy sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" /opt/ahaic-partner-portal/.env
```

Bring up the stack (migrations run automatically on `app` boot):

```bash
cd /opt/ahaic-partner-portal
sudo -u deploy docker compose --env-file .env -f docker-compose.prod.yml up -d
sudo -u deploy docker compose -f docker-compose.prod.yml ps
sudo -u deploy docker compose -f docker-compose.prod.yml logs -f app
```

Seed the demo data once (optional):

```bash
sudo -u deploy docker compose -f docker-compose.prod.yml exec app artisan db:seed --force
```

Health check:

```bash
curl -I https://portal.example.com/up      # expect 200 + Caddy TLS
```

## 6. Ongoing deploys

Push to `main` — that's the whole flow.

Manual re-deploy of a specific SHA:

```bash
gh workflow run deploy.yml --ref main
```

Rollback to a previous image tag (SHA-based):

```bash
cd /opt/ahaic-partner-portal
sudo -u deploy sed -i 's|^IMAGE=.*|IMAGE=ghcr.io/YOUR-ORG/ahaic-partner-portal:abcdef123456|' .env
sudo -u deploy docker compose --env-file .env -f docker-compose.prod.yml up -d
```

## 7. Operations cheatsheet

```bash
# Tail logs
docker compose -f docker-compose.prod.yml logs -f app queue scheduler caddy

# Open a shell inside the app container
docker compose -f docker-compose.prod.yml exec app bash

# Run a one-off artisan command
docker compose -f docker-compose.prod.yml exec app artisan tinker

# Force a migration (bypasses the RUN_MIGRATIONS gate)
docker compose -f docker-compose.prod.yml run --rm app migrate

# Clean up dangling images after several deploys
docker image prune -f
```

## 8. Reverb (Phase 9 live dashboard)

When the live-dashboard work lands, add a `reverb` service to
`docker-compose.prod.yml` (same image, `command: ["artisan","reverb:start"]`,
exposed on port 8080 to Caddy). Add a matching `handle /reverb/*` block in
`Caddyfile` proxying to `reverb:8080` with `handle_path` + WebSocket upgrade
headers.

## 9. Notes / gotchas

- **Concurrent migrations** — only the `app` container has `RUN_MIGRATIONS=true`.
  `queue` and `scheduler` inherit the image but skip migrations so they don't
  race the schema.
- **Config cache & env** — the entrypoint runs `config:cache` every start, so
  changing env values on the host requires `docker compose restart app` (not
  just editing `.env`).
- **File uploads** — `FILESYSTEM_DISK=s3` is strongly recommended in prod. If
  you keep it on `local`, the named `app-storage` volume already persists them
  across container replacements (all three app containers mount it), but you
  lose that on host replacement.
- **RDS / ElastiCache** — swap `DB_HOST` and `REDIS_HOST` in `.env`. If you use
  ElastiCache, remove the `redis:` service from `docker-compose.prod.yml` or
  keep it as a local fallback.
- **Ports 80/443 in use** — Caddy needs them. If something else already binds
  them, `docker compose up -d caddy` will fail — free them first.
