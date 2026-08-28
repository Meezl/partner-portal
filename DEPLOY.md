# AHAIC Partner Portal — GitHub → AWS Deployment Guide

End-to-end reference for shipping this repo to AWS: build in GitHub Actions,
publish the image to GHCR, roll out on an EC2 host running Docker Compose,
with **RDS** for MySQL, **S3** for uploads, and layered backups so nothing is
lost across deploys, host rebuilds, or human mistakes.

> **Read once, then treat as the single source of truth.** Every command here
> is copy-paste ready — placeholders are `YOUR-…`, region defaults to
> `eu-west-1`, and the domain used throughout is `portal.example.com`. Swap
> them for yours before pasting.

---

## Table of contents

1. [Architecture at a glance](#1-architecture-at-a-glance)
2. [Data durability model](#2-data-durability-model)
3. [AWS prerequisites](#3-aws-prerequisites)
4. [Provision the EC2 host](#4-provision-the-ec2-host)
5. [Bootstrap the host (once)](#5-bootstrap-the-host-once)
6. [Configure the app directory + `.env`](#6-configure-the-app-directory--env)
7. [GitHub secrets, environments, tokens](#7-github-secrets-environments-tokens)
8. [First deploy](#8-first-deploy)
9. [Ongoing deploys](#9-ongoing-deploys)
10. [Rollback](#10-rollback)
11. [Backups](#11-backups)
12. [Restore drill](#12-restore-drill)
13. [Operations cheatsheet](#13-operations-cheatsheet)
14. [Troubleshooting](#14-troubleshooting)
15. [Safety guards](#15-safety-guards)
16. [Adding new services (Reverb, etc.)](#16-adding-new-services-reverb-etc)
17. [Appendix — files added by this pipeline](#17-appendix--files-added-by-this-pipeline)

---

## 1. Architecture at a glance

```
Developer ─push─▶ GitHub (main)
                    │
                    ▼
      ┌──────────────────────────────┐
      │ GitHub Actions               │
      │  ├── build image (BuildKit)  │
      │  └── push → ghcr.io/<repo>   │
      └──────────────┬───────────────┘
                     │  SSH (deploy user)
                     ▼
┌────────────────────────────────────────────────┐
│ EC2 (Ubuntu 24.04 LTS)                         │
│  /opt/ahaic-partner-portal                     │
│    ├── docker-compose.prod.yml                 │
│    ├── .env                                    │
│    ├── Caddyfile                               │
│    └── data/                                   │
│         ├── storage/   (Laravel storage)       │
│         ├── redis/     (RDB + AOF)             │
│         ├── caddy/     (TLS cert cache)        │
│         └── backups/   (nightly SQL dumps)     │
│                                                │
│  ┌────────┐  ┌──────┐  ┌───────────┐ ┌──────┐  │
│  │ caddy  │→│ app  │← │ scheduler │ │queue │  │
│  │  :443  │  │:80   │  │           │ │      │  │
│  └────────┘  └──┬───┘  └───────────┘ └──────┘  │
│                 │  Redis (container or ElastiCache)
└─────────────────┼──────────────────────────────┘
                  │
      ┌───────────┴──────────────┐
      ▼                          ▼
  RDS MySQL 8                 S3 bucket
  (Multi-AZ, 14d backups)     (uploads, versioned)
```

**Traffic path:** DNS `portal.example.com` → EC2 elastic IP → Caddy (80/443,
Let's Encrypt) → `app` container (Nginx + PHP-FPM) → RDS + S3 + Redis.

**Roles inside compose:**
- `app` — Nginx + PHP-FPM, runs migrations on start
- `queue` — `artisan queue:work redis`
- `scheduler` — `artisan schedule:work`
- `redis` — session + cache + queue backend (swap to ElastiCache when scaling)
- `caddy` — TLS termination + reverse proxy

---

## 2. Data durability model

Every persistent surface is off-container or off-host. Deploys **never** touch
data.

| Data | Where it lives | Survives deploy | Survives host loss | Backup mechanism |
|---|---|---|---|---|
| MySQL rows | RDS | ✅ | ✅ | RDS automated backups (14 d PITR) + nightly `mysqldump` → S3 |
| Uploaded files (logos, PDFs, payment proofs) | S3 bucket | ✅ | ✅ | S3 Versioning |
| Sessions / cache / queue | Redis (container volume or ElastiCache) | ✅ | ⚠️ container-mode only if EBS survives | Rebuilt from source of truth on loss |
| TLS certificates | `./data/caddy/` bind mount | ✅ | ⚠️ re-issued automatically | Let's Encrypt re-issue |
| `.env`, compose file, `Caddyfile` | `/opt/ahaic-partner-portal/` on EBS | ✅ | ✅ | AWS Backup (EBS snapshots) |

Nothing in the CI or compose commands runs `down -v`, `volume rm`, or
`prune --volumes`; step 15 also installs a shell alias that refuses those
commands.

---

## 3. AWS prerequisites

Do these once per environment before touching the EC2 host.

### 3.1 VPC / networking

Use the default VPC unless you have a stronger reason. You need:

- A **public subnet** for the EC2 host
- (RDS in production) two **private subnets** in different AZs
- One **DB subnet group** covering those private subnets
- A **security group** for EC2 (`sg-web`): inbound 22 (your IP), 80, 443 (0.0.0.0/0)
- A **security group** for RDS (`sg-db`): inbound 3306 from `sg-web` only
- A **security group** for ElastiCache (`sg-cache`): inbound 6379 from `sg-web` only

### 3.2 S3 bucket for uploads

```bash
aws s3api create-bucket \
  --bucket ahaic-portal-uploads \
  --region eu-west-1 \
  --create-bucket-configuration LocationConstraint=eu-west-1

aws s3api put-bucket-versioning \
  --bucket ahaic-portal-uploads \
  --versioning-configuration Status=Enabled

aws s3api put-public-access-block \
  --bucket ahaic-portal-uploads \
  --public-access-block-configuration \
  BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true

aws s3api put-bucket-lifecycle-configuration \
  --bucket ahaic-portal-uploads \
  --lifecycle-configuration '{
    "Rules": [{
      "ID": "expire-old-versions",
      "Status": "Enabled",
      "Filter": {},
      "NoncurrentVersionExpiration": {"NoncurrentDays": 90}
    }]
  }'
```

Create a second bucket for DB dumps (private, versioned):

```bash
aws s3api create-bucket --bucket ahaic-portal-backups --region eu-west-1 \
  --create-bucket-configuration LocationConstraint=eu-west-1
aws s3api put-bucket-versioning --bucket ahaic-portal-backups \
  --versioning-configuration Status=Enabled
aws s3api put-public-access-block --bucket ahaic-portal-backups \
  --public-access-block-configuration \
  BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true
```

### 3.3 IAM user for the app

```bash
aws iam create-user --user-name ahaic-portal-app

aws iam put-user-policy --user-name ahaic-portal-app \
  --policy-name ahaic-portal-app-policy \
  --policy-document '{
    "Version": "2012-10-17",
    "Statement": [
      { "Effect": "Allow",
        "Action": ["s3:GetObject","s3:PutObject","s3:DeleteObject","s3:ListBucket"],
        "Resource": [
          "arn:aws:s3:::ahaic-portal-uploads",
          "arn:aws:s3:::ahaic-portal-uploads/*"
        ] },
      { "Effect": "Allow",
        "Action": ["s3:PutObject","s3:ListBucket"],
        "Resource": [
          "arn:aws:s3:::ahaic-portal-backups",
          "arn:aws:s3:::ahaic-portal-backups/*"
        ] },
      { "Effect": "Allow",
        "Action": ["ses:SendEmail","ses:SendRawEmail"],
        "Resource": "*" }
    ]
  }'

aws iam create-access-key --user-name ahaic-portal-app
# Save AccessKeyId + SecretAccessKey — they go into the host .env
```

### 3.4 RDS MySQL

```bash
aws rds create-db-subnet-group \
  --db-subnet-group-name ahaic-db-subnets \
  --db-subnet-group-description "AHAIC portal private subnets" \
  --subnet-ids subnet-xxxxxxxx subnet-yyyyyyyy

aws rds create-db-instance \
  --db-instance-identifier ahaic-portal \
  --engine mysql --engine-version 8.0 \
  --db-instance-class db.t4g.small \
  --allocated-storage 20 --storage-type gp3 --storage-encrypted \
  --master-username ahaic --master-user-password 'CHANGE-ME-STRONG' \
  --db-name ahaic_portal \
  --vpc-security-group-ids sg-xxxxxxxx \
  --db-subnet-group-name ahaic-db-subnets \
  --backup-retention-period 14 \
  --multi-az \
  --deletion-protection \
  --auto-minor-version-upgrade
```

Wait a few minutes and note the endpoint:

```bash
aws rds describe-db-instances --db-instance-identifier ahaic-portal \
  --query 'DBInstances[0].Endpoint.Address' --output text
```

### 3.5 SES (email) — optional but standard

```bash
aws ses verify-email-identity --email-address no-reply@example.com
# then click the link in the confirmation email
```

Move out of the SES sandbox before going live (request production access in
the SES console).

### 3.6 AWS Backup for EBS (optional but recommended)

```bash
aws backup create-backup-plan --backup-plan '{
  "BackupPlanName": "ahaic-portal-nightly",
  "Rules": [{
    "RuleName": "nightly",
    "TargetBackupVaultName": "Default",
    "ScheduleExpression": "cron(0 3 * * ? *)",
    "StartWindowMinutes": 60,
    "CompletionWindowMinutes": 240,
    "Lifecycle": {"DeleteAfterDays": 30}
  }]
}'
```

Then in the console: **AWS Backup → Backup plans → assign resources** by tag
`Backup=daily`, and tag the EC2 instance with that.

---

## 4. Provision the EC2 host

Use the **official Canonical Ubuntu 24.04 LTS AMI** — any other image (Amazon
Linux, downstream Ubuntu, "resolute" previews) will break the runtime
dependencies of the PHP layer inside the image.

```bash
aws ec2 describe-images \
  --owners 099720109477 \
  --filters 'Name=name,Values=ubuntu/images/hvm-ssd-gp3/ubuntu-noble-24.04-amd64-server-*' \
            'Name=state,Values=available' \
  --query 'sort_by(Images, &CreationDate)[-1].[ImageId,Name]' \
  --output text
```

Launch:

```bash
AMI_ID=ami-xxxxxxxxxxxxxxxxx      # from the query above
KEY_NAME=your-keypair
SG_ID=sg-xxxxxxxxxxxxxxxxx        # sg-web from §3.1
SUBNET_ID=subnet-xxxxxxxxxxxxxxxxx

aws ec2 run-instances \
  --image-id "$AMI_ID" \
  --instance-type t3.small \
  --key-name  "$KEY_NAME" \
  --security-group-ids "$SG_ID" \
  --subnet-id "$SUBNET_ID" \
  --block-device-mappings 'DeviceName=/dev/sda1,Ebs={VolumeSize=30,VolumeType=gp3,DeleteOnTermination=false}' \
  --tag-specifications 'ResourceType=instance,Tags=[{Key=Name,Value=ahaic-portal},{Key=Backup,Value=daily}]'
```

Give it an **Elastic IP** and point your DNS `A` record at it:

```bash
EIP=$(aws ec2 allocate-address --domain vpc --query PublicIp --output text)
aws ec2 associate-address --instance-id i-xxxxxxxx --public-ip "$EIP"
echo "Point portal.example.com A → $EIP"
```

---

## 5. Bootstrap the host (once)

SSH in as `ubuntu`.

### 5.1 System baseline

```bash
sudo apt update && sudo apt upgrade -y
sudo timedatectl set-timezone UTC
sudo apt install -y ca-certificates curl gnupg ufw fail2ban unattended-upgrades
sudo dpkg-reconfigure -f noninteractive unattended-upgrades

sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw --force enable
sudo systemctl enable --now fail2ban
```

### 5.2 Docker Engine + Compose plugin

```bash
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg \
  | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
  https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo $VERSION_CODENAME) stable" \
  | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo systemctl enable --now docker
```

### 5.3 Deploy user for CI SSH

```bash
sudo adduser --disabled-password --gecos "" deploy
sudo usermod -aG docker deploy
sudo install -d -m 700 -o deploy -g deploy /home/deploy/.ssh
sudo tee /home/deploy/.ssh/authorized_keys >/dev/null <<'EOF'
ssh-ed25519 AAAA...PASTE-THE-CI-PUBLIC-KEY... deploy@github-actions
EOF
sudo chown deploy:deploy /home/deploy/.ssh/authorized_keys
sudo chmod 600 /home/deploy/.ssh/authorized_keys
```

Generate the CI keypair from your workstation:

```bash
ssh-keygen -t ed25519 -C "deploy@github-actions" -f ~/.ssh/ahaic_portal_ci
# put ~/.ssh/ahaic_portal_ci      → GitHub secret EC2_SSH_KEY
# put ~/.ssh/ahaic_portal_ci.pub  → authorized_keys above
```

Optionally disable root and password logins:

```bash
sudo sed -i 's/^#\?PermitRootLogin.*/PermitRootLogin no/'         /etc/ssh/sshd_config
sudo sed -i 's/^#\?PasswordAuthentication.*/PasswordAuthentication no/' /etc/ssh/sshd_config
sudo systemctl restart ssh
```

---

## 6. Configure the app directory + `.env`

```bash
sudo install -d -o deploy -g deploy /opt/ahaic-partner-portal
sudo -u deploy git clone https://github.com/YOUR-ORG/ahaic-partner-portal.git /opt/ahaic-partner-portal
cd /opt/ahaic-partner-portal
```

Create data directories (bind mounts referenced by `docker-compose.prod.yml`):

```bash
sudo -u deploy install -d -m 775 \
  data/storage \
  data/storage/app/public \
  data/storage/framework/cache/data \
  data/storage/framework/sessions \
  data/storage/framework/views \
  data/storage/logs \
  data/redis \
  data/caddy \
  data/caddy-config \
  data/backups
# Alpine images run PHP-FPM/nginx as UID 82; own storage & redis to that UID
sudo chown -R 82:82 data/storage data/redis
```

Write `.env` (owned by `deploy`, mode 600):

```bash
sudo -u deploy tee /opt/ahaic-partner-portal/.env >/dev/null <<'EOF'
# ── Deploy metadata ──
IMAGE=ghcr.io/YOUR-ORG/ahaic-partner-portal:latest
APP_DOMAIN=portal.example.com

# ── Laravel ──
APP_KEY=                                # filled in §8 below

# ── RDS ──
DB_HOST=ahaic-portal.xxxxxx.eu-west-1.rds.amazonaws.com
DB_PORT=3306
DB_DATABASE=ahaic_portal
DB_USERNAME=ahaic
DB_PASSWORD=CHANGE-ME-STRONG

# ── Redis (in-cluster or ElastiCache) ──
REDIS_HOST=redis
REDIS_PORT=6379

# ── S3 uploads ──
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=AKIAxxxxxxxx           # from §3.3
AWS_SECRET_ACCESS_KEY=xxxxxxxx
AWS_DEFAULT_REGION=eu-west-1
AWS_BUCKET=ahaic-portal-uploads

# ── Email (SES) ──
MAIL_MAILER=ses
MAIL_FROM_ADDRESS=no-reply@example.com
EOF
sudo chmod 600 /opt/ahaic-partner-portal/.env
```

---

## 7. GitHub secrets, environments, tokens

### 7.1 Personal Access Token for GHCR pulls

The workflow uses the built-in `GITHUB_TOKEN` to **push** the image (works
because `packages: write` is granted in `deploy.yml`). The EC2 host needs to
**pull** it, so create a classic PAT with only `read:packages`:

- GitHub → **Settings → Developer settings → Personal access tokens
  (classic)** → *Generate new token (classic)*
- Scope: `read:packages`
- Expiration: 90 d (rotate on a calendar reminder)
- Copy the token — you'll paste it into `GHCR_READ_TOKEN` below

### 7.2 Environment secrets

In the repo: **Settings → Environments → New environment → `production`**.
Add these secrets (they're scoped to the `production` environment only):

| Secret name | Value |
|---|---|
| `EC2_HOST` | Elastic IP or DNS of the instance |
| `EC2_USER` | `deploy` |
| `EC2_SSH_KEY` | Contents of `~/.ssh/ahaic_portal_ci` (the private key) |
| `EC2_PORT` | `22` (only needed if you changed it) |
| `GHCR_USER` | Any GitHub user with read on the org packages (usually a machine user) |
| `GHCR_READ_TOKEN` | The PAT from §7.1 |

Turn on **Required reviewers** for the `production` environment if you want
manual approval before deploys.

### 7.3 Package visibility

After the first push, go to **Packages** on the repo/org and make the image
**private** unless you want it public. GHCR pulls with the PAT above regardless.

---

## 8. First deploy

Log in to GHCR and pull the initial image on the host (this happens once so
you can generate `APP_KEY`; every subsequent deploy comes from CI):

```bash
sudo -u deploy bash -lc '
  read -s GHCR_READ_TOKEN
  echo "$GHCR_READ_TOKEN" | docker login ghcr.io -u YOUR-GHCR-USER --password-stdin
'
```

Trigger the workflow so an initial image exists in GHCR:

```bash
# On your workstation
git commit --allow-empty -m "chore: trigger first deploy"
git push origin main
```

Once the workflow finishes, on the host:

```bash
cd /opt/ahaic-partner-portal
sudo -u deploy docker pull ghcr.io/YOUR-ORG/ahaic-partner-portal:latest

APP_KEY=$(sudo -u deploy docker run --rm ghcr.io/YOUR-ORG/ahaic-partner-portal:latest \
          artisan key:generate --show)
sudo -u deploy sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" /opt/ahaic-partner-portal/.env

# Apply the schema first — the app container does NOT migrate on start.
sudo -u deploy docker compose --env-file .env -f docker-compose.prod.yml run --rm migrate

sudo -u deploy docker compose --env-file .env -f docker-compose.prod.yml up -d
sudo -u deploy docker compose -f docker-compose.prod.yml ps
sudo -u deploy docker compose -f docker-compose.prod.yml logs -f app
```

Migrations run as the one-shot `migrate` service, never from the `app`
container — so scaling or restarting `app` can never kick off a migration.
Optionally seed reference data (only on a fresh env):

```bash
sudo -u deploy docker compose -f docker-compose.prod.yml exec app artisan db:seed --force
```

Verify:

```bash
curl -I https://portal.example.com/up      # → HTTP/2 200
sudo -u deploy docker compose -f docker-compose.prod.yml ps
sudo -u deploy docker inspect --format '{{.State.Health.Status}}' \
     $(sudo -u deploy docker compose -f docker-compose.prod.yml ps -q app)
# → healthy
```

---

## 9. Ongoing deploys

**Push to `main`**. That is the whole flow.

The workflow (`.github/workflows/deploy.yml`):

1. Builds the multi-stage image with BuildKit + GHA cache
2. Pushes `ghcr.io/<repo>:<12-char sha>` and `:latest`
3. SSHes to the EC2 host as `deploy`
4. Logs in to GHCR, updates `IMAGE=` in `.env` to the new SHA tag
5. Runs `docker compose pull`
6. Runs `docker compose run --rm migrate` — the one-shot migration task. A
   failed migration aborts the deploy here, leaving the previous containers
   serving the old image
7. Runs `up -d --remove-orphans`
8. Polls the `app` container's health check until it's `healthy`
9. Prunes dangling images

Manual re-run of a specific commit:

```bash
gh workflow run deploy.yml --ref main
```

---

## 10. Rollback

Because every deploy pins a SHA tag, rollback is a one-liner on the host:

```bash
cd /opt/ahaic-partner-portal
# Pick a previous SHA from `docker image ls` or the GHCR package page
sudo -u deploy sed -i 's|^IMAGE=.*|IMAGE=ghcr.io/YOUR-ORG/ahaic-partner-portal:abcdef123456|' .env
sudo -u deploy docker compose --env-file .env -f docker-compose.prod.yml pull
sudo -u deploy docker compose --env-file .env -f docker-compose.prod.yml up -d
```

If a bad migration is part of the problem, **restore the DB from the RDS
snapshot for the pre-deploy point-in-time** (see §12) before rolling the image
forward again.

---

## 11. Backups

### 11.1 RDS automated (already on)

- 14-day retention from §3.4
- Point-in-time restore to any second within the retention window
- Manual snapshot before scary changes:
  ```bash
  aws rds create-db-snapshot \
    --db-instance-identifier ahaic-portal \
    --db-snapshot-identifier pre-migration-$(date +%F)
  ```

### 11.2 Nightly logical DB dump → S3

The `app` container ships with `mysql-client`. Install a helper on the host:

```bash
sudo -u deploy tee /opt/ahaic-partner-portal/backup-db.sh >/dev/null <<'EOF'
#!/usr/bin/env bash
set -euo pipefail
cd /opt/ahaic-partner-portal
STAMP=$(date +%F-%H%M)
OUT=/opt/ahaic-partner-portal/data/backups
mkdir -p "$OUT"

docker compose --env-file .env -f docker-compose.prod.yml exec -T app \
  mysqldump --single-transaction --routines --triggers \
            --set-gtid-purged=OFF \
            -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" \
  | gzip -9 > "$OUT/db-$STAMP.sql.gz"

aws s3 cp "$OUT/db-$STAMP.sql.gz" s3://ahaic-portal-backups/db/ \
  --storage-class STANDARD_IA

find "$OUT" -name 'db-*.sql.gz' -mtime +14 -delete
EOF
sudo chmod +x /opt/ahaic-partner-portal/backup-db.sh
```

Schedule via `deploy`'s crontab:

```bash
( sudo -u deploy crontab -l 2>/dev/null; \
  echo "15 2 * * * /opt/ahaic-partner-portal/backup-db.sh >> /opt/ahaic-partner-portal/data/backups/backup.log 2>&1" ) \
| sudo -u deploy crontab -
```

Install the AWS CLI on the host if it isn't there yet:

```bash
sudo apt install -y awscli
sudo -u deploy aws configure   # use the IAM keys from §3.3
```

### 11.3 EBS snapshots (host)

Handled by AWS Backup from §3.6 via the `Backup=daily` tag on the instance.

### 11.4 File uploads

S3 **Versioning** from §3.2 keeps prior versions for 90 days; overwritten or
deleted files can be restored via the S3 console or:

```bash
aws s3api list-object-versions --bucket ahaic-portal-uploads --prefix logos/
```

### 11.5 Entire host tarball (before risky changes)

```bash
sudo tar czf /root/backup-$(date +%F).tgz -C /opt/ahaic-partner-portal data .env docker-compose.prod.yml Caddyfile
```

---

## 12. Restore drill

Do this in staging once a quarter — you don't have a backup if you haven't
restored from it.

### 12.1 Fresh EC2 host

Repeat §4–§7.

### 12.2 Restore files

```bash
sudo tar xzf backup-YYYY-MM-DD.tgz -C /opt/ahaic-partner-portal/
```

### 12.3 Restore DB

**From RDS point-in-time (preferred)**:

```bash
aws rds restore-db-instance-to-point-in-time \
  --source-db-instance-identifier ahaic-portal \
  --target-db-instance-identifier ahaic-portal-restore \
  --restore-time 2027-02-25T14:30:00Z
# Point DB_HOST in .env at the new endpoint
```

**From a logical dump**:

```bash
zcat data/backups/db-YYYY-MM-DD-HHMM.sql.gz \
  | mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE"
```

### 12.4 Restore uploads (if you've been overwriting)

```bash
aws s3api list-object-versions --bucket ahaic-portal-uploads \
  --prefix logos/example.png \
  --query 'Versions[].[VersionId,LastModified]'

aws s3api copy-object \
  --copy-source ahaic-portal-uploads/logos/example.png?versionId=xxxx \
  --bucket ahaic-portal-uploads --key logos/example.png
```

### 12.5 Bring the stack up

```bash
cd /opt/ahaic-partner-portal
sudo -u deploy docker compose --env-file .env -f docker-compose.prod.yml up -d
curl -I https://portal.example.com/up
```

---

## 13. Operations cheatsheet

Run all of these from `/opt/ahaic-partner-portal` as `deploy`.

```bash
# Status
docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml logs -f app queue scheduler caddy

# Shell in a container
docker compose -f docker-compose.prod.yml exec app bash

# One-off artisan
docker compose -f docker-compose.prod.yml exec app artisan tinker
docker compose -f docker-compose.prod.yml exec app artisan cache:clear

# Run migrations (one-shot; the app container never migrates on boot)
docker compose -f docker-compose.prod.yml run --rm migrate

# Rebuild config cache after editing .env (must restart, not just edit)
docker compose -f docker-compose.prod.yml restart app

# Health check
curl -I https://portal.example.com/up

# Housekeeping (safe — never touches volumes)
docker image prune -f
```

---

## 14. Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| `certbot` / Caddy stuck on TLS provisioning | DNS not pointing to EIP, or 80/443 blocked | Verify `dig +short portal.example.com` matches EIP, check `sg-web` |
| CI deploy step: `permission denied (publickey)` | `EC2_SSH_KEY` wrong or key not in `authorized_keys` | Re-copy pubkey; test locally: `ssh -i key deploy@host` |
| `curl /up` returns 502 | `app` container not healthy | `docker compose logs app` — usually DB env wrong or RDS SG blocking |
| `SQLSTATE[HY000] [2002]` | Wrong `DB_HOST` or SG denies 3306 from EC2 | Check RDS SG allows `sg-web` on 3306 |
| PDF/logo uploads fail | IAM policy missing `s3:PutObject` | Re-apply the policy from §3.3 |
| Deploy replaces image but old code shows | OPcache holding on | Restart app: `docker compose restart app` (entrypoint recaches) |
| `ERROR: Unable to satisfy dependencies` during image build | Base image drift | `docker builder prune` on the runner; re-run |
| Nightly backup empty | AWS CLI not configured under `deploy` | `sudo -u deploy aws sts get-caller-identity` |

Logs to check first:

```bash
docker compose -f docker-compose.prod.yml logs --tail 200 app
journalctl -u docker --since '10 min ago'
sudo tail -n 200 /opt/ahaic-partner-portal/data/backups/backup.log
```

---

## 15. Safety guards

Add these on the host once so muscle memory can't destroy state.

### 15.1 Alias that refuses destructive Docker commands

```bash
sudo -u deploy tee -a /home/deploy/.bashrc >/dev/null <<'EOF'
docker() {
  case "$*" in
    *"compose down -v"*|*"system prune"*"--volumes"*|*"volume rm"*|*"volume prune"*)
      echo "⛔ blocked: this command destroys data. Use ./backup-db.sh first, then run 'command docker …' if you really mean it." >&2
      return 1 ;;
  esac
  command docker "$@"
}
EOF
```

### 15.2 EBS root volume: `DeleteOnTermination=false`

Already set in the launch command in §4. Verify:

```bash
aws ec2 describe-instances --instance-ids i-xxxxxxxx \
  --query 'Reservations[].Instances[].BlockDeviceMappings[].[DeviceName,Ebs.DeleteOnTermination]'
```

### 15.3 RDS `DeletionProtection=true` (already set)

Verify:

```bash
aws rds describe-db-instances --db-instance-identifier ahaic-portal \
  --query 'DBInstances[0].DeletionProtection'
```

### 15.4 CI never touches volumes

The workflow only calls `docker compose pull`, `docker compose up -d --remove-orphans`, and `docker image prune -f`. None of those touch volumes or bind mounts.

### 15.5 Branch protection

- **Settings → Branches → main** → *Require a pull request before merging*
- Require the `build` job to pass before merge
- Optionally require the `production` environment reviewer(s) to approve

---

## 16. Adding new services (Reverb, etc.)

When the Phase-9 live-dashboard work lands, add a `reverb` service to
`docker-compose.prod.yml`:

```yaml
  reverb:
    image: ${IMAGE}
    restart: unless-stopped
    command: ["artisan", "reverb:start", "--host=0.0.0.0", "--port=8080"]
    environment:
      <<: *app-env
    depends_on: [redis, app]
    networks: [edge, back]
```

Route via Caddy in `Caddyfile`:

```caddy
{$APP_DOMAIN} {
    encode zstd gzip
    handle /app/* {
        reverse_proxy reverb:8080
    }
    reverse_proxy app:80
}
```

Reload with `docker compose up -d caddy reverb`.

For a totally new managed service (e.g. ElastiCache Redis):
1. Provision it via AWS console/CLI in the same VPC
2. Add its SG rule (`sg-cache` accepts 6379 from `sg-web`)
3. Set `REDIS_HOST=your-elasticache-endpoint` in `.env`
4. Remove the `redis:` service from `docker-compose.prod.yml`
5. `docker compose up -d` — Redis-backed sessions cut over

---

## 17. Appendix — files added by this pipeline

| Path | Purpose |
|---|---|
| [Dockerfile](Dockerfile) | 3-stage build (composer deps + npm build + php-fpm-alpine runtime with Nginx + Supervisor) |
| [.dockerignore](.dockerignore) | Keeps `.env`, `node_modules`, `vendor`, `storage/*`, tests out of build context |
| [docker/entrypoint.sh](docker/entrypoint.sh) | Multi-command entrypoint (`web` / `queue` / `scheduler` / `migrate` / `artisan …`), waits for DB, primes caches |
| [docker/nginx/default.conf](docker/nginx/default.conf) | Nginx server block, Vite `/build/*` immutable caching, 25 MB uploads |
| [docker/php/php.ini](docker/php/php.ini) | Production OPcache + JIT settings |
| [docker/php/www.conf](docker/php/www.conf) | PHP-FPM pool tuning (dynamic, 3→20 workers) |
| [docker/supervisord.conf](docker/supervisord.conf) | Runs `php-fpm` and `nginx` inside the web container |
| [docker-compose.prod.yml](docker-compose.prod.yml) | Compose stack: `app`, `queue`, `scheduler`, `redis`, `caddy` with bind-mounted `./data/*` |
| [Caddyfile](Caddyfile) | Automatic Let's Encrypt TLS, HSTS, compression |
| [.github/workflows/deploy.yml](.github/workflows/deploy.yml) | Build → push GHCR → SSH deploy pipeline |
| `DEPLOY.md` | This document |
