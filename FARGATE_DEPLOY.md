# AHAIC Partner Portal — AWS Fargate Deployment (Copilot CLI)

Deploy the app to **AWS Fargate** with the same "Railway-style" experience:
`git push main` → new image → traffic shifts to it → DB, files, and Redis are
untouched. Uses **AWS Copilot CLI** to manage the ECS Fargate cluster, ALB,
ACM cert, ECR registry, VPC, RDS, S3, ElastiCache, and CodePipeline as one
opinionated bundle.

> **Read `DEPLOY.md` first if you want the EC2 + Docker Compose alternative.**
> This file is a **complete replacement path**, not additive.

---

## Table of contents

1. [What Copilot builds for you](#1-what-copilot-builds-for-you)
2. [Prerequisites](#2-prerequisites)
3. [Install Copilot & configure AWS](#3-install-copilot--configure-aws)
4. [Codebase prep — one small entrypoint change](#4-codebase-prep--one-small-entrypoint-change)
5. [Initialize the Copilot app](#5-initialize-the-copilot-app)
6. [Create the `production` environment](#6-create-the-production-environment)
7. [Web service (Nginx + PHP-FPM)](#7-web-service-nginx--php-fpm)
8. [Worker services (queue, scheduler)](#8-worker-services-queue-scheduler)
9. [Addons — RDS MySQL, S3, ElastiCache Redis](#9-addons--rds-mysql-s3-elasticache-redis)
10. [Wire environment variables + secrets](#10-wire-environment-variables--secrets)
11. [Migration job (safe, race-free)](#11-migration-job-safe-race-free)
12. [Custom domain + TLS](#12-custom-domain--tls)
13. [First deploy](#13-first-deploy)
14. [CI/CD — Copilot pipeline (git push → deploy)](#14-cicd--copilot-pipeline-git-push--deploy)
15. [Ongoing deploys, rollback, secrets rotation](#15-ongoing-deploys-rollback-secrets-rotation)
16. [Operations cheatsheet](#16-operations-cheatsheet)
17. [Cost estimate](#17-cost-estimate)
18. [Troubleshooting](#18-troubleshooting)
19. [Appendix — manifest files](#19-appendix--manifest-files)

---

## 1. What Copilot builds for you

One `copilot deploy --all` provisions:

```
GitHub ──push─▶ CodePipeline
                    │
                    ▼
             ┌─────────────┐
             │   CodeBuild │  docker build → ECR push
             └──────┬──────┘
                    │
                    ▼
   ┌──────────────────────────────────────┐
   │ Production environment (VPC)         │
   │   ┌──────┐  ACM ┌──────────────────┐ │
   │   │ ALB  │──────│ web (Fargate ×N) │ │  ← rolling deploy, health-checked
   │   │ :443 │      │  Nginx+PHP-FPM   │ │
   │   └──────┘      └──────────────────┘ │
   │                       │ │            │
   │              ┌────────┘ └─────────┐  │
   │              ▼                    ▼  │
   │      ┌──────────────┐    ┌──────────────┐
   │      │ queue        │    │ scheduler    │
   │      │ (Backend Svc)│    │ (Backend Svc)│
   │      └──────────────┘    └──────────────┘
   │                                        │
   │  Addons (CloudFormation):              │
   │   • RDS MySQL 8 (Aurora Serverless v2  │
   │     or db.t4g)                         │
   │   • S3 bucket (versioned)              │
   │   • ElastiCache Redis                  │
   │   • Secrets Manager for DB password    │
   │                                        │
   └────────────────────────────────────────┘
```

**What lives where** — same three-way separation Railway uses:

| Data | Location | Survives every deploy | Backups |
|---|---|---|---|
| MySQL rows | RDS / Aurora | ✅ | RDS 14-day automated + snapshots |
| Uploads | S3 bucket | ✅ | S3 Versioning |
| Sessions / queue / cache | ElastiCache Redis | ✅ | RDB snapshots (opt-in) |
| App code | ECR image (immutable per SHA) | ✅ (rollback = flip image tag) | ECR image retention policy |

Fargate tasks are **ephemeral by design**. The three storage services above are the source of truth; nothing else is.

---

## 2. Prerequisites

You need:

- **AWS account** with an IAM user or SSO profile that can create IAM roles, VPCs, RDS, ECS, ECR, CloudFormation stacks, Secrets Manager entries, and CodePipeline. Easiest: give it `AdministratorAccess` for the initial bootstrap, then narrow later.
- **A domain you control** with DNS you can edit (Route 53 or elsewhere). Copilot will request an ACM cert and ask you to add a validation `CNAME`.
- **Docker Desktop** (or any local Docker) — Copilot builds images locally on first deploy before switching to CodeBuild.
- **`git` remote** on GitHub — Copilot's pipeline reads from GitHub via CodeStar Connections.
- **The current repo**, which already has [`Dockerfile`](Dockerfile), [`docker/entrypoint.sh`](docker/entrypoint.sh), etc.

Estimated bootstrap time: **45–60 minutes** end-to-end.

---

## 3. Install Copilot & configure AWS

### 3.1 Install Copilot CLI

**macOS:**
```bash
brew install aws/tap/copilot-cli
copilot --version
```

**Linux:**
```bash
curl -Lo /tmp/copilot \
  https://github.com/aws/copilot-cli/releases/latest/download/copilot-linux \
  && chmod +x /tmp/copilot \
  && sudo mv /tmp/copilot /usr/local/bin/copilot
copilot --version
```

### 3.2 Install & configure AWS CLI

```bash
# macOS
brew install awscli
# Linux
curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o awscliv2.zip
unzip awscliv2.zip && sudo ./aws/install
```

```bash
aws configure
# AWS Access Key ID:     AKIA...
# AWS Secret Access Key: ...
# Default region:        eu-west-1
# Default output:        json
aws sts get-caller-identity        # sanity check
```

If your org uses SSO, `aws configure sso` + `export AWS_PROFILE=<name>` also works — Copilot reads whatever the CLI is currently using.

---

## 4. Codebase prep — already done

`docker/entrypoint.sh` **no longer migrates on `web` start**. On Fargate,
multiple tasks start in parallel during a rolling deploy, so migrating from
`web` would mean N tasks racing on the same migration. Migrations now run as a
dedicated one-shot Copilot job instead — see §11. There is nothing to change
here; `RUN_MIGRATIONS` no longer exists.

The entrypoint also expands the Aurora `DB_SECRET` JSON blob
(`{host, port, dbname, username, password}`) into the discrete `DB_*` variables
Laravel expects. Values already set in a manifest win, so the secret only fills
in what is missing — in practice `DB_HOST` and `DB_PASSWORD`.

---

## 5. Initialize the Copilot app

From the repo root:

```bash
cd /path/to/ahaic-partner-portal
copilot app init ahaic
```

- **`ahaic`** is the application name; it prefixes every AWS resource so you can host multiple apps in the same account.
- Copilot creates a tiny CloudFormation stack (`ahaic-infrastructure-roles`) with the IAM roles it needs, and a hidden `copilot/` directory in the repo.

Verify:
```bash
copilot app show ahaic
ls copilot/
# → .workspace
```

---

## 6. Create the `production` environment

An environment = one VPC + one ECS cluster + shared networking. Create it in the same region you configured the CLI for.

```bash
copilot env init \
  --name production \
  --profile default \
  --default-config
```

- `--default-config` = "give me the standard VPC layout" (2 public + 2 private subnets, NAT gateway).
- If you have an existing VPC, use `--import-vpc-id vpc-xxxx --import-public-subnets subnet-a,subnet-b --import-private-subnets subnet-c,subnet-d` instead.

Then deploy the environment (this actually creates the VPC, NAT, ECS cluster, etc.):

```bash
copilot env deploy --name production
```

Wait ~5 minutes. When it finishes:

```bash
copilot env show --name production --resources
```

You'll see the VPC ID, subnets, and cluster ARN — save nothing, Copilot tracks it all.

**Cost floor:** the NAT gateway alone is ~$32/mo. If you want to skip NAT and put tasks in public subnets (dev only, not recommended for prod), pass `--internal` on env init.

---

## 7. Web service (Nginx + PHP-FPM)

```bash
copilot svc init \
  --app ahaic \
  --name web \
  --svc-type "Load Balanced Web Service" \
  --dockerfile ./Dockerfile \
  --port 80
```

Copilot generates `copilot/web/manifest.yml`. Open it and adjust for this app:

```yaml
# copilot/web/manifest.yml
name: web
type: Load Balanced Web Service

image:
  build: Dockerfile
  port: 80

http:
  path: '/'
  healthcheck:
    path: '/up'
    healthy_threshold: 2
    unhealthy_threshold: 3
    interval: 15s
    timeout: 5s
    grace_period: 45s
  # Optional but recommended — enforce HTTPS at the ALB
  redirect_to_https: true

cpu:    512      # 0.5 vCPU
memory: 1024     # 1 GB
count:
  range: 2-6
  cpu_percentage: 70
exec: true       # allow `copilot svc exec` for shell access

command: ["web"]

environments:
  production:
    count:
      range: 2-6
```

Notes:
- `command: ["web"]` matches the `web` case in [docker/entrypoint.sh](docker/entrypoint.sh) (default anyway).
- `count.range: 2-6` gives you a rolling deploy without downtime: Fargate spins up new tasks, waits for them to pass the ALB health check on `/up`, then drains old tasks.
- `exec: true` requires SSM Session Manager under the hood; Copilot flips the flag on the task role for you.

---

## 8. Worker services (queue, scheduler)

Both are the same image, different `command`.

```bash
copilot svc init --app ahaic --name queue     --svc-type "Backend Service" --dockerfile ./Dockerfile --port 80
copilot svc init --app ahaic --name scheduler --svc-type "Backend Service" --dockerfile ./Dockerfile --port 80
```

Edit `copilot/queue/manifest.yml`:

```yaml
name: queue
type: Backend Service

image:
  build: Dockerfile

command: ["queue"]
cpu:    256
memory: 512
count:  1
exec:   true

environments:
  production:
    count: 2       # two workers in prod
```

Edit `copilot/scheduler/manifest.yml`:

```yaml
name: scheduler
type: Backend Service

image:
  build: Dockerfile

command: ["scheduler"]
cpu:    256
memory: 512
count:  1          # only ever one scheduler
exec:   true
```

Backend Services are **not** exposed to the internet — they sit in the private subnets and talk to Redis/RDS only. No ALB, no cost overhead per service.

---

## 9. Addons — RDS MySQL, S3, ElastiCache Redis

### 9.1 S3 bucket for uploads

```bash
copilot storage init \
  --storage-type S3 \
  --name uploads \
  --workload web \
  --lifecycle environment
```

`--lifecycle environment` puts the bucket in the **environment stack**, not
the service stack — this is what you want in production so `copilot svc delete
web` can't destroy the files. (Use `workload` only for throwaway per-service
storage.)

Copilot writes `copilot/environments/addons/uploads.yml` — a CloudFormation
snippet that creates the bucket with public access blocked and SSE-S3
encryption. Copilot **does not** add versioning or object-lifecycle rules
itself; open the file and merge these into the `AWS::S3::Bucket` resource:

```yaml
      VersioningConfiguration:
        Status: Enabled
      LifecycleConfiguration:
        Rules:
          - Id: expire-noncurrent-versions
            Status: Enabled
            NoncurrentVersionExpiration:
              NoncurrentDays: 90
          - Id: abort-incomplete-multipart
            Status: Enabled
            AbortIncompleteMultipartUpload:
              DaysAfterInitiation: 7
      PublicAccessBlockConfiguration:
        BlockPublicAcls: true
        BlockPublicPolicy: true
        IgnorePublicAcls: true
        RestrictPublicBuckets: true
```

Deploy the addon:

```bash
copilot env deploy --name production
```

Because the addon is env-scoped, `web`, `queue` and `scheduler` all get the
`UPLOADS_NAME` env var and read/write IAM permissions automatically — no
per-service copying needed (§10.3 no longer applies).

### 9.2 ElastiCache Redis

Copilot has no first-class Redis addon; write one. Create the file:

```bash
mkdir -p copilot/environments/addons
```

`copilot/environments/addons/redis.yml`:

```yaml
Parameters:
  App:
    Type: String
  Env:
    Type: String

Resources:
  RedisSubnetGroup:
    Type: AWS::ElastiCache::SubnetGroup
    Properties:
      Description: Redis subnets for ${App}-${Env}
      SubnetIds:
        - Fn::ImportValue: !Sub ${App}-${Env}-PrivateSubnets

  RedisSG:
    Type: AWS::EC2::SecurityGroup
    Properties:
      GroupDescription: !Sub Redis SG for ${App}-${Env}
      VpcId:
        Fn::ImportValue: !Sub ${App}-${Env}-VpcId
      SecurityGroupIngress:
        - IpProtocol: tcp
          FromPort: 6379
          ToPort: 6379
          SourceSecurityGroupId:
            Fn::ImportValue: !Sub ${App}-${Env}-EnvironmentSecurityGroup

  Redis:
    Type: AWS::ElastiCache::ReplicationGroup
    Properties:
      ReplicationGroupDescription: !Sub ${App}-${Env} redis
      Engine: redis
      EngineVersion: "7.1"
      CacheNodeType: cache.t4g.micro
      NumCacheClusters: 1                # bump to 2 with MultiAZ for prod HA
      AutomaticFailoverEnabled: false
      TransitEncryptionEnabled: false
      AtRestEncryptionEnabled: true
      CacheSubnetGroupName: !Ref RedisSubnetGroup
      SecurityGroupIds: [!Ref RedisSG]

Outputs:
  RedisEndpoint:
    Value: !GetAtt Redis.PrimaryEndPoint.Address
    Export:
      Name: !Sub ${App}-${Env}-RedisEndpoint
```

This runs at **environment scope** (once per env, shared across services).
Redeploy the env to create it:

```bash
copilot env deploy --name production
```

Note the endpoint from the CloudFormation output: `ahaic-production-RedisEndpoint`.

### 9.3 RDS MySQL

Copilot's built-in `storage init --storage-type Aurora` is the easiest path and gives you Aurora MySQL Serverless v2 with autoscaling capacity units:

```bash
copilot storage init \
  --storage-type Aurora \
  --name ahaicdb \
  --workload web \
  --engine MySQL \
  --initial-db ahaic_portal \
  --lifecycle environment
```

`--lifecycle environment` is doubly important here — it prevents
`copilot svc delete web` from ever tearing down your production database.

Copilot:
- Writes `copilot/web/addons/ahaicdb.yml` (Aurora cluster + Secrets Manager entry)
- Puts DB creds in **AWS Secrets Manager** (never in your `.env`)
- Injects the connection URL as env var `AHAICDB_SECRET` (a JSON string containing username/password/host/port/dbname)

If you'd rather use classic RDS MySQL (single instance, cheaper for very low load), skip Copilot's init and drop this into `copilot/environments/addons/rds.yml` instead — it uses a `db.t4g.small`:

```yaml
Parameters:
  App:
    Type: String
  Env:
    Type: String

Resources:
  DBSubnetGroup:
    Type: AWS::RDS::DBSubnetGroup
    Properties:
      DBSubnetGroupDescription: !Sub ${App}-${Env} DB subnets
      SubnetIds:
        - Fn::ImportValue: !Sub ${App}-${Env}-PrivateSubnets

  DBSecret:
    Type: AWS::SecretsManager::Secret
    Properties:
      Name: !Sub /copilot/${App}/${Env}/secrets/DB_URL
      GenerateSecretString:
        SecretStringTemplate: '{"username":"ahaic"}'
        GenerateStringKey: password
        ExcludePunctuation: true
        PasswordLength: 32

  DBSG:
    Type: AWS::EC2::SecurityGroup
    Properties:
      GroupDescription: !Sub DB SG for ${App}-${Env}
      VpcId:
        Fn::ImportValue: !Sub ${App}-${Env}-VpcId
      SecurityGroupIngress:
        - IpProtocol: tcp
          FromPort: 3306
          ToPort: 3306
          SourceSecurityGroupId:
            Fn::ImportValue: !Sub ${App}-${Env}-EnvironmentSecurityGroup

  Database:
    Type: AWS::RDS::DBInstance
    DeletionPolicy: Snapshot
    UpdateReplacePolicy: Snapshot
    Properties:
      DBInstanceIdentifier: !Sub ${App}-${Env}-mysql
      DBName: ahaic_portal
      Engine: mysql
      EngineVersion: "8.0"
      DBInstanceClass: db.t4g.small
      AllocatedStorage: 20
      StorageType: gp3
      StorageEncrypted: true
      MasterUsername: ahaic
      MasterUserPassword: !Join ["", ["{{resolve:secretsmanager:", !Ref DBSecret, ":SecretString:password}}"]]
      DBSubnetGroupName: !Ref DBSubnetGroup
      VPCSecurityGroups: [!Ref DBSG]
      BackupRetentionPeriod: 14
      DeletionProtection: true
      MultiAZ: false                     # flip to true for prod HA (~2× cost)
      PubliclyAccessible: false
      AutoMinorVersionUpgrade: true

Outputs:
  DBEndpoint:
    Value: !GetAtt Database.Endpoint.Address
    Export:
      Name: !Sub ${App}-${Env}-DBEndpoint
  DBSecretArn:
    Value: !Ref DBSecret
    Export:
      Name: !Sub ${App}-${Env}-DBSecretArn
```

The `DeletionPolicy: Snapshot` + `DeletionProtection: true` combo is why you don't lose data if someone tries to destroy the stack.

`copilot env deploy --name production` creates the DB (~8 min the first time).

---

## 10. Wire environment variables + secrets

Copilot service manifests have three ways to set env:

- **`variables:`** — plain env vars
- **`secrets:`** — resolved from SSM Parameter Store or Secrets Manager at task-start time
- **Addon-injected vars** — from the CloudFormation `Outputs` in your addon files

Update `copilot/web/manifest.yml` (repeat the block in `queue` and `scheduler`):

```yaml
variables:
  APP_ENV: production
  APP_DEBUG: "false"
  APP_URL: "https://portal.example.com"
  ASSET_URL: "https://portal.example.com"
  LOG_CHANNEL: stderr
  LOG_LEVEL: info
  FILESYSTEM_DISK: s3
  CACHE_STORE: redis
  SESSION_DRIVER: redis
  QUEUE_CONNECTION: redis
  DB_CONNECTION: mysql
  DB_PORT: "3306"
  DB_DATABASE: ahaic_portal
  DB_USERNAME: ahaic
  REDIS_PORT: "6379"
  MAIL_MAILER: ses
  MAIL_FROM_ADDRESS: "no-reply@example.com"

  # Wired from addon Outputs — Copilot exposes them as CFN parameters
  DB_HOST:
    from_cfn: ahaic-production-DBEndpoint
  REDIS_HOST:
    from_cfn: ahaic-production-RedisEndpoint
  AWS_BUCKET:
    from_cfn: ahaic-production-uploadsName    # from copilot storage init

secrets:
  APP_KEY: /copilot/ahaic/production/secrets/APP_KEY
  DB_PASSWORD:
    secretsmanager: 'ahaic-production-DBSecretArn:password::'
```

### 10.1 Put `APP_KEY` in Parameter Store

Generate once and store it:

```bash
KEY=$(docker run --rm ghcr.io/laravel/php:8.4 sh -c '
  composer create-project laravel/laravel /tmp/x --quiet
  php /tmp/x/artisan key:generate --show
' 2>/dev/null)

aws ssm put-parameter \
  --name "/copilot/ahaic/production/secrets/APP_KEY" \
  --type SecureString \
  --value "$KEY"
```

(You only ever need to do this once per environment.)

### 10.2 SES sender identity + IAM

If you plan to send email via SES, verify the sender in SES console **or** move to a verified domain in Route 53. The task role Copilot creates doesn't have SES permissions by default — add a service addon `copilot/web/addons/ses.yml`:

```yaml
Parameters: { App: {Type: String}, Env: {Type: String}, Name: {Type: String} }
Resources:
  SESSendPolicy:
    Type: AWS::IAM::ManagedPolicy
    Properties:
      PolicyDocument:
        Version: "2012-10-17"
        Statement:
          - Effect: Allow
            Action: ["ses:SendEmail","ses:SendRawEmail"]
            Resource: "*"
Outputs:
  SESSendPolicyArn:
    Value: !Ref SESSendPolicy
```

Copilot auto-attaches Managed Policies from addons to the task role.

### 10.3 Share the S3 bucket across `queue` and `scheduler`

**Not needed if you used `--lifecycle environment` in §9.1.** Env-scoped
addons are automatically visible to every workload in the environment.

Only needed if you accidentally created the bucket at workload scope; in that
case copy the addon into each service's `addons/` directory or (better) delete
and recreate the bucket with `--lifecycle environment` after moving the data.

---

## 11. Migration job (safe, race-free)

Migrations run as a committed Copilot **Scheduled Job** with `schedule: none`,
defined in `copilot/migrate/manifest.yml`. `none` creates the job with its
EventBridge rule disabled, so it never fires on its own — you invoke it
deliberately.

It pulls the database host and password from the Aurora secret via the
`ahaic-production-ahaicdbAuroraSecret` CloudFormation export, so there is no
untracked `.env` file to keep in sync, and it attaches
`ahaic-production-ahaicdbSecurityGroup` for ingress to the cluster.

Deploy it once, then invoke it on each release:

```bash
copilot job deploy --name migrate --env production
```

```bash
copilot job run --name migrate --env production
```

```bash
copilot job logs --name migrate --env production --follow
```

Run it **before** rolling `web`, so the schema is ahead of the code. The job
runs `php artisan migrate --force --isolated`; `--isolated` takes a Redis lock,
so a double invocation cannot produce two concurrent migration runs. `retries`
is deliberately `0` — a partially applied migration wants a human, not an
automatic retry.

The same one-shot pattern exists for the EC2/compose deploy, in the `migrate`
profile of `docker-compose.prod.yml`:

```bash
docker compose -f docker-compose.prod.yml run --rm migrate
```

---

## 12. Custom domain + TLS

Two paths depending on where DNS lives.

### 12.1 Route 53 (simplest)

At env creation time, pass:

```bash
copilot env init --name production --domain example.com
```

If you already ran `env init` without `--domain`, delete and re-init the env
(only feasible pre-first-deploy). Copilot creates the ACM cert, adds the
validation records, and after `svc deploy` you get an `A` record at
`web.production.ahaic.example.com` automatically.

To use the shorter `portal.example.com`, add an alias to the web manifest:

```yaml
http:
  alias: portal.example.com
```

then `copilot svc deploy --name web`. Copilot creates the ACM cert, then asks
you to add the ACM validation `CNAME` to whatever DNS you have — do that in
Route 53 (or wherever) and re-run the deploy.

### 12.2 External DNS (Cloudflare, GoDaddy, etc.)

- Skip `--domain` on env init.
- Manually request an ACM cert in the same region:
  ```bash
  aws acm request-certificate --domain-name portal.example.com \
    --validation-method DNS --key-algorithm EC_prime256v1
  ```
- Add the `_acme-...` validation `CNAME` in your DNS.
- Pass the cert ARN in the env manifest:
  ```yaml
  # copilot/environments/production/manifest.yml
  http:
    public:
      certificates:
        - arn:aws:acm:eu-west-1:123456789012:certificate/xxxxx
  ```
- Redeploy env: `copilot env deploy --name production`.
- Set a `CNAME` for `portal.example.com` to the ALB DNS name printed by
  `copilot svc show --name web`.

Either way, you get automatic TLS with HTTP→HTTPS redirect.

---

## 13. First deploy

Order matters — services need the addons to exist before they can boot.

```bash
# 1. Environment (VPC, cluster, ALB, addons/rds.yml, addons/redis.yml)
copilot env deploy --name production

# 2. Web (creates ECR repo on first run, builds image locally, pushes)
copilot svc deploy --name web --env production

# 3. Migrations — one-off, before workers come up
copilot task run --app ahaic --env production \
  --image $(aws ecr describe-repositories --repository-names ahaic/web \
            --query 'repositories[0].repositoryUri' --output text):latest \
  --command "artisan migrate --force" \
  --secrets DB_PASSWORD=$(aws cloudformation describe-stacks \
                          --stack-name ahaic-production-web \
                          --query "Stacks[0].Outputs[?OutputKey=='ahaicdbSecret'].OutputValue" \
                          --output text) \
  --env-vars "DB_HOST=$(aws cloudformation list-exports \
                        --query \"Exports[?Name=='ahaic-production-DBEndpoint'].Value\" \
                        --output text),DB_DATABASE=ahaic_portal,DB_USERNAME=ahaic"

# 4. Workers
copilot svc deploy --name queue     --env production
copilot svc deploy --name scheduler --env production
```

When `svc deploy` finishes, the ALB URL is printed. Point your DNS at it (§12), then hit `https://portal.example.com/up` — expect **200 OK**.

Seed if this is a green-field env:

```bash
copilot svc exec --name web --env production \
  --command "php artisan db:seed --force"
```

---

## 14. CI/CD — Copilot pipeline (git push → deploy)

Copilot can generate a CodePipeline that watches GitHub, builds the image in
CodeBuild, deploys `web/queue/scheduler` in order, and runs migrations in
between.

### 14.1 Generate the pipeline files

```bash
copilot pipeline init \
  --name main \
  --url https://github.com/YOUR-ORG/ahaic-partner-portal \
  --git-branch main \
  --environments production \
  --pipeline-type Workloads
```

This writes:
- `copilot/pipelines/main/manifest.yml` — which envs to deploy to, in what order
- `copilot/pipelines/main/buildspec.yml` — how to build (invokes `copilot svc package` + `docker build`)

### 14.2 Add the migration step to `buildspec.yml`

Copilot's default buildspec builds and pushes the image but doesn't run
migrations. Edit `copilot/pipelines/main/buildspec.yml` — inside the
`post_build:` block, after Copilot writes the deploy artifacts, prepend a
`copilot task run` for migrations. Simplest reliable pattern: run migrations
in the `pre_deploy` action of the pipeline manifest:

Edit `copilot/pipelines/main/manifest.yml`:

```yaml
name: main
source:
  provider: GitHub
  properties:
    branch: main
    repository: https://github.com/YOUR-ORG/ahaic-partner-portal

build:
  image: aws/codebuild/amazonlinux-x86_64-standard:corretto21

stages:
  - name: production
    # run migrations first, THEN roll web + workers
    pre_deployments:
      db_migrate:
        buildspec: copilot/pipelines/main/migrate.buildspec.yml
    deployments:
      web: {}
      queue:
        depends_on: web
      scheduler:
        depends_on: web
```

Create `copilot/pipelines/main/migrate.buildspec.yml`:

```yaml
version: 0.2
phases:
  install:
    runtime-versions: { ruby: 3.2 }
    commands:
      - curl -Lo /usr/local/bin/copilot https://github.com/aws/copilot-cli/releases/latest/download/copilot-linux
      - chmod +x /usr/local/bin/copilot
  build:
    commands:
      - IMAGE=$(aws ecr describe-repositories --repository-names ahaic/web --query 'repositories[0].repositoryUri' --output text):latest
      - DB_HOST=$(aws cloudformation list-exports --query "Exports[?Name=='ahaic-production-DBEndpoint'].Value" --output text)
      - DB_SECRET=$(aws cloudformation describe-stacks --stack-name ahaic-production-web --query "Stacks[0].Outputs[?OutputKey=='ahaicdbSecret'].OutputValue" --output text)
      - >
        copilot task run --app ahaic --env production
        --image "$IMAGE"
        --command "artisan migrate --force"
        --secrets DB_PASSWORD="$DB_SECRET"
        --env-vars "DB_HOST=$DB_HOST,DB_DATABASE=ahaic_portal,DB_USERNAME=ahaic"
        --follow
```

### 14.3 Deploy the pipeline

```bash
copilot pipeline deploy --name main
```

Copilot walks you through the **GitHub CodeStar connection** the first time:
opens the AWS console, you click **Install a new app** to authorize the
`aws-codestar-connections` GitHub app on your org/repo, then finish.

From now on: **every push to `main` → new image built by CodeBuild → migrations run → web rolls, workers follow**. Watch it live:

```bash
copilot pipeline status --name main
```

---

## 15. Ongoing deploys, rollback, secrets rotation

### 15.1 Deploys

Push to `main`. Or trigger from CLI:
```bash
copilot deploy --all           # web + queue + scheduler
copilot svc deploy --name web  # single service
```

Fargate uses a **rolling update** with `minimumHealthyPercent=100`, `maximumPercent=200`: new tasks come up, ALB health-checks them on `/up`, old tasks drain (default 30 s), then get killed. Zero-downtime by default.

### 15.2 Rollback

Every deploy tags the image `ahaic/web:<git-sha>`. To roll back:

```bash
# List recent tags
aws ecr describe-images --repository-name ahaic/web \
  --query 'sort_by(imageDetails,& imagePushedAt)[-10:].[imageTags[0],imagePushedAt]' \
  --output table

# Roll back
copilot svc deploy --name web --env production --tag abcdef1234
```

For a DB-schema rollback: restore RDS to a point-in-time snapshot **first**,
then roll the image back:

```bash
aws rds restore-db-instance-to-point-in-time \
  --source-db-instance-identifier ahaic-production-mysql \
  --target-db-instance-identifier ahaic-production-mysql-restore \
  --restore-time 2027-02-25T14:30:00Z
# swap the endpoint (edit the addon or point DB_HOST manually) and redeploy env
```

### 15.3 Rotate a secret

```bash
# Change APP_KEY
aws ssm put-parameter \
  --name /copilot/ahaic/production/secrets/APP_KEY \
  --type SecureString --overwrite --value "base64:..."

# Redeploy so tasks pick up the new value
copilot svc deploy --name web --env production
```

DB password rotation: use Secrets Manager rotation (the Aurora addon
provisions a rotation Lambda if you tick the box during `storage init`).

---

## 16. Operations cheatsheet

```bash
# Live logs
copilot svc logs --name web       --follow
copilot svc logs --name queue     --follow --since 30m

# Shell in a running task
copilot svc exec --name web
# inside: `php artisan tinker` / `php artisan cache:clear` / etc.

# Ad-hoc artisan
copilot svc exec --name web --command "php artisan queue:failed"

# List running tasks
copilot svc status --name web --env production

# Scale manually
copilot svc deploy --name web --env production
# ...after editing count.range in manifest.yml — Copilot applies it

# All resources for an env
copilot env show --name production --resources --json | jq

# Delete a workload cleanly (keeps addons like RDS with Snapshot policy)
copilot svc delete --name queue --env production
```

---

## 17. Cost estimate

Rough monthly bill for a **small production install** (2 web, 1 queue, 1 scheduler, small RDS + Redis, moderate traffic):

| Line item | ~USD |
|---|---|
| Fargate compute (2× web @ 0.5 vCPU/1 GB + 2× workers @ 0.25/0.5) | $45 |
| ALB | $18 |
| NAT gateway + data transfer | $35 |
| RDS `db.t4g.small` MySQL (single-AZ) | $26 |
| ElastiCache `cache.t4g.micro` | $12 |
| S3 (uploads, backups) | $2 |
| CloudWatch Logs (retention 14d) | $3 |
| CodePipeline + CodeBuild (a few builds/day) | $2 |
| **Total** | **~$143/mo** |

Compare to the EC2 path in `DEPLOY.md`: ~$25/mo. You're paying ~$120/mo for
"never patch a server, zero-downtime deploys, managed DB with PITR, managed
Redis". Worth it if your time costs more than $10/hr saved per month.

**Two big cost knobs:**
- Drop the NAT (put tasks in public subnets on a hardened SG) → save $32/mo. Only do this if you understand the security trade-off.
- Multi-AZ RDS doubles the DB line item. Turn it on for real prod.

---

## 18. Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| `svc deploy` hangs on **"Waiting for service to become stable"** | ALB health check failing | `copilot svc logs --name web --follow` — usually `SQLSTATE 2002` (Redis/DB env wrong) or app crashes on boot |
| `Task failed to start: ResourceInitializationError` | Task can't pull image, or SG blocks NAT egress | Check ECR permissions on the execution role, and that the private subnet has a NAT route |
| App boots but hits `The MAC is invalid` on session decrypt | `APP_KEY` differs between tasks | You forgot to store it in Parameter Store; every task generated its own |
| Migration task exits 1 with `SQLSTATE[42S01]` | Migration ran twice concurrently (race) | Only the `migrate` job should migrate; `web` no longer does. Check nothing else calls `artisan migrate` |
| `migrate` job exits 1 with `DB_SECRET is not valid JSON` | `DB_SECRET` not wired, or points at a non-JSON secret | Confirm the `ahaic-production-ahaicdbAuroraSecret` export exists and is the Aurora-managed JSON secret |
| `migrate` job hangs at `waiting for MySQL` | Task cannot reach Aurora | Confirm `ahaic-production-ahaicdbSecurityGroup` is attached under `network.vpc.security_groups` |
| ALB returns 502 randomly | PHP-FPM crashed, task not yet unhealthy | `copilot svc exec --name web` and check `/dev/stderr`. Also raise `pm.max_children` in `docker/php/www.conf` |
| S3 uploads 403 | Task role missing `s3:PutObject` | The addon should attach it; check `copilot/web/addons/uploads.yml` outputs `AccessPolicyArn` |
| `copilot pipeline deploy` fails on GitHub connection | You skipped the "Install app" step | Open the CodeStar Connection in the AWS console, click **Update pending connection** |
| Deploy blocked by "IN_PROGRESS" stack | A previous deploy died mid-way | `aws cloudformation continue-update-rollback --stack-name ahaic-production-web`, then retry |

Where to look first:

```bash
copilot svc logs   --name web   --since 15m
copilot task logs  --group      /copilot/ahaic-production-migrate
aws cloudformation describe-stack-events --stack-name ahaic-production-web \
  --query 'StackEvents[?ResourceStatus==`CREATE_FAILED`||ResourceStatus==`UPDATE_FAILED`]'
```

---

## 19. Appendix — manifest files

For reference, the full set of Copilot files this guide produces:

```
copilot/
├── .workspace
├── environments/
│   ├── production/
│   │   └── manifest.yml
│   └── addons/
│       ├── redis.yml
│       └── rds.yml               # only if you skipped `storage init Aurora`
├── web/
│   ├── manifest.yml
│   └── addons/
│       ├── uploads.yml
│       ├── ses.yml
│       └── ahaicdb.yml           # only if you used `storage init Aurora`
├── queue/
│   ├── manifest.yml
│   └── addons/
│       └── uploads.yml
├── scheduler/
│   ├── manifest.yml
│   └── addons/
│       └── uploads.yml
└── pipelines/
    └── main/
        ├── manifest.yml
        ├── buildspec.yml
        └── migrate.buildspec.yml
```

Everything is committed to git — infra-as-code for the whole stack.

---

## Where to go from here

- **Add a staging environment**: `copilot env init --name staging` then `copilot env deploy` and `copilot pipeline` with `--environments staging,production` — pipeline auto-promotes.
- **Blue/green deploys**: switch the web service's `deployment.rolling` setting to `default` and enable CodeDeploy blue/green in the manifest for true zero-touch cutover with automatic rollback on alarms.
- **Reverb (Phase 9)**: add a fourth service, backend type, `port: 8080`, and wire an ALB path rule in the web manifest.
- **Multi-region DR**: `copilot env init --name production-dr --region eu-central-1`, add cross-region RDS read replica, front both regions with Route 53 failover.

You now have Railway-style ergonomics on AWS — with backups, autoscaling, TLS,
rollback, and multi-service workloads all managed as code.
