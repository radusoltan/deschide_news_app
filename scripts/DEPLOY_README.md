# Deschide News - Deployment & Service Management Guide

Complete installation instructions for deploying Deschide News on Ubuntu 24.04 LTS with native services (no Docker).

## Table of Contents

- [Prerequisites](#prerequisites)
- [Directory Layout](#directory-layout)
- [1. Deployment Script](#1-deployment-script)
- [2. Systemd Service Units](#2-systemd-service-units)
- [3. Logrotate Configuration](#3-logrotate-configuration)
- [4. Complete Fresh Server Setup](#4-complete-fresh-server-setup)
- [5. Operational Runbook](#5-operational-runbook)

---

## Prerequisites

- **OS**: Ubuntu 24.04 LTS
- **PHP**: 8.4+ with extensions: pdo_pgsql, redis, amqp, intl, gd, opcache
- **Node.js**: 22 LTS (via nvm or nodesource)
- **pnpm**: Latest stable
- **PM2**: Process manager for Node.js
- **PostgreSQL**: 17
- **Redis**: 7+
- **RabbitMQ**: 3.13+
- **Elasticsearch**: 8.x
- **Mercure**: Latest binary
- **Composer**: 2.x
- **Nginx**: Latest stable (reverse proxy)

## Directory Layout

```
/var/www/deschide_news_app/
  scripts/
    deploy.sh                    # Main deployment script
    backup-database.sh           # Database backup
    restore-database.sh          # Database restore
    smoke-check.sh               # Post-deploy health check
    start-all-services.sh        # Development: start all services
    systemd/
      deschide-messenger@.service  # Messenger worker (template)
      deschide-scheduler.service   # Scheduled tasks (oneshot)
      deschide-scheduler.timer     # Timer: every 5 minutes
      deschide-mercure.service     # Mercure real-time hub
    logrotate/
      deschide                     # Log rotation config
    cron/
      backup-database.cron         # Daily backup cron job
```

---

## 1. Deployment Script

### Installation

```bash
# The script is already at scripts/deploy.sh
# Make it executable (if not already):
chmod +x /var/www/deschide_news_app/scripts/deploy.sh
```

### Usage

```bash
cd /var/www/deschide_news_app

# Dry run (prints all commands without executing):
./scripts/deploy.sh --dry-run

# Full deployment (backend + frontend):
./scripts/deploy.sh

# Backend only:
./scripts/deploy.sh --backend-only

# Frontend only:
./scripts/deploy.sh --frontend-only

# Skip database backup:
./scripts/deploy.sh --skip-backup

# Custom branch:
GIT_BRANCH=develop ./scripts/deploy.sh

# Custom PHP-FPM service:
PHP_FPM_SERVICE=php8.5-fpm ./scripts/deploy.sh

# Flush Redis cache before migrations (entity-removal deploys):
./scripts/deploy.sh --confirm-flush
```

### What the Deploy Script Does

1. **Pre-deploy checks**: Verifies disk space, running services (PostgreSQL, Redis), required CLI tools
2. **Database backup**: Calls `scripts/backup-database.sh` before any changes
3. **Git pull**: Fetches and pulls the target branch
4. **Backend deploy**:
   - `composer install --no-dev --optimize-autoloader`
   - **Redis FLUSHDB** (only if `--confirm-flush` provided — see "Redis pre-flight" below)
   - `symfony console doctrine:migrations:migrate`
   - `symfony console cache:clear --env=prod`
   - `symfony console cache:warmup --env=prod`
   - `systemctl reload php-fpm`
5. **Frontend deploy**:
   - `pnpm install --frozen-lockfile`
   - `pnpm build`
   - `pm2 reload ecosystem.config.js`
6. **Post-deploy**: Smoke checks on backend API and frontend, deployment summary
7. **Rollback on failure**: Resets git to previous SHA, provides restore instructions

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `GIT_BRANCH` | `main` | Git branch to deploy |
| `PHP_FPM_SERVICE` | `php8.4-fpm` | PHP-FPM systemd service name |
| `BACKEND_URL` | `http://127.0.0.1:8081` | Backend URL for smoke check |
| `FRONTEND_URL` | `http://localhost:3005` | Frontend URL for smoke check |
| `REDIS_HOST` | `127.0.0.1` | Redis host for FLUSHDB pre-flight |
| `REDIS_PORT` | `6379` | Redis port for FLUSHDB pre-flight |
| `REDIS_CACHE_DB` | `1` | Redis DB index to flush (project standard: DB 1) |

### Redis pre-flight

The Doctrine metadata cache for production lives in Redis DB 1 (prefix
`deschide_news:*`, policy `volatile-lru`). When a deploy contains
migrations that drop or rename Doctrine entities, the metadata cache may
still hold class definitions for the removed entities. The first request
after deploy then explodes with a metadata-mapping error referencing a
class that no longer exists.

This is what happened during the Sprint 52 cluster hard-drop (ADR-019):
the `StoryCluster` entity was dropped but the metadata cache held its
mapping; production threw 500s on every API call until the cache was
manually flushed.

**Use `--confirm-flush` whenever the deploy contains entity-removal,
entity-rename, or non-trivial schema-shape migrations.** The flag
performs `redis-cli -h $REDIS_HOST -p $REDIS_PORT -n $REDIS_CACHE_DB FLUSHDB`
before the migrations run, ensuring a clean cache on the first request
after deploy.

**Without `--confirm-flush`**, the deploy script logs a warning but
continues — most deploys (frontend-only, hotfixes, additive migrations
that only ADD columns or tables) do not require a flush, and a routine
flush would discard the application's hot session/cache state.

```bash
# Routine deploy (no entity removals): no flush needed
./scripts/deploy.sh

# Deploy contains entity-removal migrations (e.g. ADR-019, future cleanups):
./scripts/deploy.sh --confirm-flush

# Custom Redis target (e.g. staging cluster):
REDIS_HOST=cache.staging.deschide.md REDIS_CACHE_DB=2 \
  ./scripts/deploy.sh --confirm-flush
```

**When in doubt, use `--confirm-flush`.** The cost is one cold cache
re-warm (a few seconds of slower first requests). The cost of forgetting
when needed is a hard 500 on every API call until manual recovery.

---

## 2. Systemd Service Units

### Install All Units

```bash
# Copy all unit files to systemd directory
sudo cp /var/www/deschide_news_app/scripts/systemd/*.service /etc/systemd/system/
sudo cp /var/www/deschide_news_app/scripts/systemd/*.timer /etc/systemd/system/

# Reload systemd daemon
sudo systemctl daemon-reload
```

### a) Messenger Workers (`deschide-messenger@.service`)

Template unit that runs Symfony Messenger consumers for async task processing (thumbnail generation, notifications, etc.).

```bash
# Enable and start 2 worker instances
sudo systemctl enable deschide-messenger@1 deschide-messenger@2
sudo systemctl start deschide-messenger@1 deschide-messenger@2

# Check status
sudo systemctl status deschide-messenger@1
sudo systemctl status deschide-messenger@2

# View logs
journalctl -u deschide-messenger@1 -f
journalctl -u 'deschide-messenger@*' --since "1 hour ago"

# Restart all workers
sudo systemctl restart 'deschide-messenger@*'

# Stop a specific worker
sudo systemctl stop deschide-messenger@2
```

**Configuration details:**
- Each worker runs for 1 hour (`--time-limit=3600`), then restarts automatically
- Memory limit: 256MB per worker (`--memory-limit=256M`)
- Hard memory cap: 512MB via systemd `MemoryMax`
- CPU quota: 50% per worker
- Auto-restart on failure with 5-second delay
- Runs as `www-data` user with security hardening

### b) Scheduled Tasks (`deschide-scheduler.timer` + `.service`)

Timer-triggered service that runs maintenance commands every 5 minutes.

```bash
# Enable and start the timer
sudo systemctl enable --now deschide-scheduler.timer

# Check timer status
systemctl list-timers deschide-scheduler.timer

# View execution history
journalctl -u deschide-scheduler.service --since "1 hour ago"

# Trigger manually (for testing)
sudo systemctl start deschide-scheduler.service

# Disable timer
sudo systemctl disable --now deschide-scheduler.timer
```

**Commands executed every 5 minutes:**
1. `app:cleanup-expired-locks` - Removes stale article edit locks
2. `app:publish-scheduled-articles` - Publishes articles with scheduled publish dates

### c) Mercure Hub (`deschide-mercure.service`)

Real-time push notifications via Server-Sent Events (SSE).

```bash
# Create Mercure config directory
sudo mkdir -p /etc/mercure

# Create the Caddyfile configuration
sudo tee /etc/mercure/Caddyfile > /dev/null << 'EOF'
{
    order mercure after encode
    admin off
}

:3000 {
    route {
        mercure {
            publisher_jwt !ChangeThisMercureHubJWTSecretKey!
            subscriber_jwt !ChangeThisMercureHubJWTSecretKey!
            anonymous
            cors_origins http://localhost:3005 https://deschide.md
        }
        respond "Mercure Hub"
    }
}
EOF

# Enable and start
sudo systemctl enable --now deschide-mercure.service

# Check status
sudo systemctl status deschide-mercure

# View logs
journalctl -u deschide-mercure -f
```

**Important**: Replace `!ChangeThisMercureHubJWTSecretKey!` with a strong secret in production. Update both the Caddyfile and the Symfony `.env.local` `MERCURE_JWT_SECRET` to match.

### Verify All Services

```bash
# List all Deschide services
systemctl list-units 'deschide-*' --all

# List all Deschide timers
systemctl list-timers 'deschide-*'

# Quick health check
for svc in deschide-messenger@1 deschide-messenger@2 deschide-mercure; do
    status=$(systemctl is-active "$svc" 2>/dev/null || echo "inactive")
    printf "%-35s %s\n" "$svc" "$status"
done

timer_status=$(systemctl is-active deschide-scheduler.timer 2>/dev/null || echo "inactive")
printf "%-35s %s\n" "deschide-scheduler.timer" "$timer_status"
```

---

## 3. Logrotate Configuration

### Installation

```bash
# Copy logrotate configuration
sudo cp /var/www/deschide_news_app/scripts/logrotate/deschide /etc/logrotate.d/deschide

# Set correct permissions
sudo chmod 644 /etc/logrotate.d/deschide
sudo chown root:root /etc/logrotate.d/deschide

# Create log directories if they do not exist
sudo mkdir -p /var/log/php
sudo mkdir -p /var/log/pm2
sudo mkdir -p /var/log/deschide
sudo chown www-data:www-data /var/log/deschide
```

### Verify Configuration

```bash
# Test configuration (dry run, verbose)
sudo logrotate --debug /etc/logrotate.d/deschide

# Force rotation (for testing)
sudo logrotate --force /etc/logrotate.d/deschide

# Check logrotate status
cat /var/lib/logrotate/status | grep deschide
```

### Rotation Policy

| Log Path | Frequency | Retention | Compression |
|----------|-----------|-----------|-------------|
| Backend `var/log/*.log` | Daily | 30 days | gzip (delayed) |
| PHP `/var/log/php/deschide-*.log` | Daily | 30 days | gzip (delayed) |
| PM2 `/var/log/pm2/deschide-*.log` | Daily | 14 days | gzip (delayed) |
| Deploy log `deploy.log` | Monthly | 12 months | gzip (delayed) |
| Messenger `/var/log/deschide/messenger-*.log` | Daily | 14 days | gzip (delayed) |

---

## 4. Complete Fresh Server Setup

Step-by-step guide for a fresh Ubuntu 24.04 LTS server.

### 4.1 System Packages

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install essential tools
sudo apt install -y \
    git curl wget unzip software-properties-common \
    build-essential apt-transport-https ca-certificates gnupg

# Install PHP 8.4
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y \
    php8.4-fpm php8.4-cli php8.4-pgsql php8.4-redis \
    php8.4-amqp php8.4-intl php8.4-gd php8.4-mbstring \
    php8.4-xml php8.4-curl php8.4-zip php8.4-opcache \
    php8.4-apcu php8.4-imagick

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Node.js 22 LTS
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs

# Install pnpm
npm install -g pnpm

# Install PM2
npm install -g pm2

# Install Nginx
sudo apt install -y nginx

# Install PostgreSQL 17
sudo sh -c 'echo "deb http://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list'
wget --quiet -O - https://www.postgresql.org/media/keys/ACCC4CF8.asc | sudo apt-key add -
sudo apt update
sudo apt install -y postgresql-17

# Install Redis
sudo apt install -y redis-server

# Install RabbitMQ
sudo apt install -y rabbitmq-server
sudo rabbitmq-plugins enable rabbitmq_management

# Install Elasticsearch 8.x (follow official Elastic docs)
# https://www.elastic.co/guide/en/elasticsearch/reference/current/deb.html
```

### 4.2 Application Setup

```bash
# Create application user and directories
sudo mkdir -p /var/www/deschide_news_app
sudo chown www-data:www-data /var/www/deschide_news_app

# Clone repository
sudo -u www-data git clone <REPO_URL> /var/www/deschide_news_app

# Backend setup
cd /var/www/deschide_news_app/apps/backend
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data cp .env .env.local
# Edit .env.local with production values (DATABASE_URL, etc.)

# Run migrations
sudo -u www-data php bin/console doctrine:migrations:migrate --no-interaction

# Generate JWT keys
sudo -u www-data php bin/console lexik:jwt:generate-keypair

# Clear and warm cache
sudo -u www-data php bin/console cache:clear --env=prod
sudo -u www-data php bin/console cache:warmup --env=prod

# Frontend setup
cd /var/www/deschide_news_app/apps/frontend
sudo -u www-data pnpm install --frozen-lockfile
sudo -u www-data cp .env.example .env.local
# Edit .env.local with production values

# Build frontend
sudo -u www-data pnpm build

# Start with PM2
sudo -u www-data pm2 start ecosystem.config.js --env production
sudo -u www-data pm2 save
sudo -u www-data pm2 startup
```

### 4.3 Install Systemd Units

```bash
# Copy all service files
sudo cp /var/www/deschide_news_app/scripts/systemd/deschide-messenger@.service /etc/systemd/system/
sudo cp /var/www/deschide_news_app/scripts/systemd/deschide-scheduler.service /etc/systemd/system/
sudo cp /var/www/deschide_news_app/scripts/systemd/deschide-scheduler.timer /etc/systemd/system/
sudo cp /var/www/deschide_news_app/scripts/systemd/deschide-mercure.service /etc/systemd/system/

# Reload systemd
sudo systemctl daemon-reload

# Enable and start Messenger workers (2 instances)
sudo systemctl enable --now deschide-messenger@1
sudo systemctl enable --now deschide-messenger@2

# Enable and start scheduler timer
sudo systemctl enable --now deschide-scheduler.timer

# Set up Mercure config, then enable
sudo mkdir -p /etc/mercure
# Create /etc/mercure/Caddyfile (see section 2c above)
sudo systemctl enable --now deschide-mercure.service
```

### 4.4 Install Logrotate

```bash
sudo cp /var/www/deschide_news_app/scripts/logrotate/deschide /etc/logrotate.d/deschide
sudo chmod 644 /etc/logrotate.d/deschide
sudo chown root:root /etc/logrotate.d/deschide

# Create required log directories
sudo mkdir -p /var/log/php /var/log/pm2 /var/log/deschide
sudo chown www-data:www-data /var/log/deschide

# Verify
sudo logrotate --debug /etc/logrotate.d/deschide
```

### 4.5 Install Cron Jobs

```bash
# Database backup cron
sudo cp /var/www/deschide_news_app/scripts/cron/backup-database.cron /etc/cron.d/deschide-db-backup
sudo chmod 644 /etc/cron.d/deschide-db-backup

# Create backup directory
sudo mkdir -p /var/www/deschide_news_app/backups
sudo chown www-data:www-data /var/www/deschide_news_app/backups
```

### 4.6 Nginx Configuration (Reverse Proxy)

```bash
sudo tee /etc/nginx/sites-available/deschide-api > /dev/null << 'EOF'
server {
    listen 80;
    server_name api.deschide.md;

    root /var/www/deschide_news_app/apps/backend/public;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }

    access_log /var/log/nginx/deschide-api.access.log;
    error_log  /var/log/nginx/deschide-api.error.log;
}
EOF

sudo tee /etc/nginx/sites-available/deschide-frontend > /dev/null << 'EOF'
server {
    listen 80;
    server_name deschide.md www.deschide.md;

    location / {
        proxy_pass http://127.0.0.1:3005;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
    }

    access_log /var/log/nginx/deschide-frontend.access.log;
    error_log  /var/log/nginx/deschide-frontend.error.log;
}
EOF

# Enable sites
sudo ln -sf /etc/nginx/sites-available/deschide-api /etc/nginx/sites-enabled/
sudo ln -sf /etc/nginx/sites-available/deschide-frontend /etc/nginx/sites-enabled/

# Test and reload
sudo nginx -t
sudo systemctl reload nginx
```

### 4.7 Make Deploy Script Executable

```bash
chmod +x /var/www/deschide_news_app/scripts/deploy.sh
```

---

## 5. Operational Runbook

### Deploy

```bash
# Standard production deploy
cd /var/www/deschide_news_app
./scripts/deploy.sh

# Dry run first
./scripts/deploy.sh --dry-run
```

### Rollback

If deployment fails, the script automatically rolls back. For manual rollback:

```bash
# Revert to specific commit
cd /var/www/deschide_news_app
git log --oneline -10                    # Find the target commit
git reset --hard <COMMIT_SHA>

# Redeploy from that state
./scripts/deploy.sh --skip-backup

# Restore database if needed
./scripts/restore-database.sh /var/www/deschide_news_app/backups/<backup_file>.sql.gz
```

### Monitor Services

```bash
# All Deschide services
systemctl list-units 'deschide-*' --all

# Messenger worker logs
journalctl -u 'deschide-messenger@*' -f

# Scheduler execution history
journalctl -u deschide-scheduler.service --since "24 hours ago"

# Mercure hub logs
journalctl -u deschide-mercure -f

# PM2 frontend status
pm2 status
pm2 logs deschide-frontend
```

### Smoke Check

```bash
./scripts/smoke-check.sh
```

### Restart Individual Services

```bash
# Restart messenger workers
sudo systemctl restart 'deschide-messenger@*'

# Restart Mercure
sudo systemctl restart deschide-mercure

# Restart PHP-FPM
sudo systemctl restart php8.4-fpm

# Restart frontend
pm2 reload ecosystem.config.js --env production

# Restart Nginx
sudo systemctl reload nginx
```

### View Logs

```bash
# Symfony production log
tail -f /var/www/deschide_news_app/apps/backend/var/log/prod.log

# All Deschide systemd logs
journalctl -u 'deschide-*' --since "1 hour ago"

# Deployment history
cat /var/www/deschide_news_app/deploy.log

# Nginx access log
tail -f /var/log/nginx/deschide-api.access.log
```

### Emergency: Stop Everything

```bash
# Stop all Deschide systemd services
sudo systemctl stop 'deschide-messenger@*'
sudo systemctl stop deschide-scheduler.timer
sudo systemctl stop deschide-mercure

# Stop frontend
pm2 stop all

# Stop PHP-FPM (stops all PHP apps on server)
sudo systemctl stop php8.4-fpm
```
