# Plan Detaliat: Separare Medii DEV și PROD

**Data**: 5 Noiembrie 2025
**Proiect**: Deschide News App
**Scop**: Configurare medii separate dev/prod pe WSL2 fără Docker

---

## 📋 Cuprins

1. [Viziune Generală](#1-viziune-generală)
2. [Arhitectură Țintă](#2-arhitectură-țintă)
3. [Schema de Porturi](#3-schema-de-porturi)
4. [Servicii Partajate - Configurare](#4-servicii-partajate---configurare)
5. [Backend Symfony - Configurare](#5-backend-symfony---configurare)
6. [Frontend Next.js - Configurare](#6-frontend-nextjs---configurare)
7. [Nginx - Configurare](#7-nginx---configurare)
8. [Scripturi Management](#8-scripturi-management)
9. [Workflow Zilnic](#9-workflow-zilnic)
10. [Monitorizare și Debugging](#10-monitorizare-și-debugging)
11. [Checklist Implementare](#11-checklist-implementare)
12. [OPTIONAL: Expunere Publică cu Ngrok](#12-optional-expunere-publică-cu-ngrok)

---

## 1. Viziune Generală

### 1.1 Obiective

✅ **Separare clară între medii**:
- **DEV**: Dezvoltare locală cu debug, hot reload, rapid iteration
- **PROD**: Environment production-like pe WSL cu optimizări și monitoring complet

✅ **Fără Docker**: Rulare nativă pe WSL2 folosind serviciile systemd

✅ **Acces local**: Ambele medii accesibile din localhost/LAN

✅ **Servicii partajate**: PostgreSQL, Redis, Elasticsearch, RabbitMQ, Mercure, Prometheus, Grafana folosite de ambele medii cu namespace separation

### 1.2 Ce Vrem să Obținem

```
┌─────────────────────────────────────────────────────────┐
│                    WSL2 Environment                     │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  ┌──────────────────┐        ┌──────────────────┐     │
│  │   DEV PROFILE    │        │   PROD PROFILE   │     │
│  ├──────────────────┤        ├──────────────────┤     │
│  │ Backend: 8081    │        │ Backend: FPM     │     │
│  │ Frontend: 3005   │        │ Frontend: 3006   │     │
│  │ CDN: 8082        │        │ Nginx: 80        │     │
│  │                  │        │ CDN: 8083        │     │
│  │ APP_ENV=dev      │        │ APP_ENV=prod     │     │
│  │ APP_DEBUG=1      │        │ APP_DEBUG=0      │     │
│  └────────┬─────────┘        └────────┬─────────┘     │
│           │                           │                │
│           └───────────┬───────────────┘                │
│                       │                                │
│              ┌────────▼─────────┐                      │
│              │ Shared Services  │                      │
│              ├──────────────────┤                      │
│              │ PostgreSQL: 5432 │ (DB: deschide_dev, deschide_prod)
│              │ Redis: 6379      │ (DB: 1=dev, 2=prod)  │
│              │ Elasticsearch    │ (index: deschide_*_dev, deschide_*_prod)
│              │ RabbitMQ: 5672   │ (vhost: /dev, /prod) │
│              │ Mercure: 3000    │ (topic prefix)       │
│              │ Prometheus: 9090 │                      │
│              │ Grafana: 3002    │                      │
│              └──────────────────┘                      │
│                                                         │
└─────────────────────────────────────────────────────────┘

Acces:
- DEV Backend:  http://localhost:8081
- DEV Frontend: http://localhost:3005
- PROD Backend:  http://api.deschide.local
- PROD Frontend: http://deschide.local
```

---

## 2. Arhitectură Țintă

### 2.1 Separare Logică

| Aspect | DEV | PROD |
|--------|-----|------|
| **Backend Server** | Symfony CLI (8081) | PHP-FPM + Nginx |
| **Backend URL** | `http://localhost:8081` | `http://api.deschide.local` |
| **Frontend Server** | Next.js dev (3005) | Next.js build + start (3006) |
| **Frontend URL** | `http://localhost:3005` | `http://deschide.local` |
| **CDN Server** | Simple HTTP (8082) | Simple HTTP (8083) |
| **Environment** | `APP_ENV=dev` | `APP_ENV=prod` |
| **Debug Mode** | `APP_DEBUG=1` | `APP_DEBUG=0` |
| **Cache** | Disabled/Minimal | Full caching |
| **Asset Build** | Hot reload | Optimized bundles |
| **Database** | `deschide_dev` | `deschide_prod` |
| **Redis DB** | DB 1 | DB 2 |
| **ES Index** | `deschide_*_dev` | `deschide_*_prod` |
| **RabbitMQ Vhost** | `/dev` | `/prod` |
| **Mercure Topic** | `dev/*` | `prod/*` |
| **CORS Origin** | `localhost:*` | `*.deschide.local` |

### 2.2 Flux de Lucru

**Development (DEV)**:
```
Developer → localhost:3005 → Next.js Dev Server → localhost:8081 → Symfony CLI → Services
```

**Production-like Testing (PROD)**:
```
Developer → deschide.local → Nginx:80 → Next.js:3006 → api.deschide.local → Nginx:80 → PHP-FPM → Services
```

---

## 3. Schema de Porturi

### 3.1 Porturi Aplicație

| Serviciu | DEV Port | PROD Port | Acces |
|----------|----------|-----------|-------|
| **Backend Symfony** | 8081 | Unix socket `/run/php/php8.4-fpm-prod.sock` | Nginx proxy |
| **Frontend Next.js** | 3005 | 3006 | Direct/Nginx |
| **CDN Static Assets** | 8082 | 8083 | Direct |
| **Nginx (PROD only)** | - | 80 | Gateway PROD |

### 3.2 Porturi Servicii Partajate (Shared)

| Serviciu | Port | Namespace/Separare |
|----------|------|--------------------|
| PostgreSQL | 5432 | Database: `deschide_dev`, `deschide_prod` |
| PgBouncer | 6432 | Connection pooling (shared) |
| Redis | 6379 | DB 1 (dev), DB 2 (prod) |
| Elasticsearch | 9200 | Indices: `deschide_*_dev`, `deschide_*_prod` |
| RabbitMQ AMQP | 5672 | Vhost: `/dev`, `/prod` |
| RabbitMQ Management | 15672 | Web UI (shared) |
| Mercure Hub | 3000 | Topic prefix: `dev/*`, `prod/*` |
| Prometheus | 9090 | Metrics (job labels: dev/prod) |
| Grafana | 3002 | Dashboards (both environments) |

### 3.3 Verificare Disponibilitate Porturi

```bash
# Check if ports are available
ss -tulpn | grep -E ":(80|3005|3006|8081|8082|8083|5432|6379|9200|5672|15672|3000|9090|3002)"
```

**Status actual**:
- ✅ Port 80: LIBER (pentru Nginx prod)
- ✅ Port 3005: În uz (DEV frontend - OK)
- ✅ Port 3006: LIBER (pentru PROD frontend)
- ✅ Port 8081: În uz (DEV backend - OK)
- ✅ Port 8082: În uz (DEV CDN - OK)
- ✅ Port 8083: LIBER (pentru PROD CDN)
- ✅ Servicii shared: Toate rulează și disponibile

### 3.4 Configurare Domenii Locale (PROD)

Pentru a accesa aplicația PROD prin domenii locale, trebuie să adăugăm intrări în fișierul `/etc/hosts`.

**Pe WSL** (fișier `/etc/hosts`):

```bash
# Editare /etc/hosts
sudo nano /etc/hosts

# Adaugă următoarele linii:
127.0.0.1   deschide.local
127.0.0.1   api.deschide.local
```

**Pe Windows** (opțional, pentru acces din browser Windows):

Fișier: `C:\Windows\System32\drivers\etc\hosts` (necesită drepturi de Administrator)

```
127.0.0.1   deschide.local
127.0.0.1   api.deschide.local
```

**Verificare configurare**:

```bash
# Test din WSL
ping -c 2 deschide.local
ping -c 2 api.deschide.local

# Test DNS resolution
nslookup deschide.local
nslookup api.deschide.local
```

**Note**:
- Domeniile `.local` sunt rezolvate local via `/etc/hosts`
- Nu este nevoie de DNS server extern
- Funcționează doar pe mașina unde sunt configurate
- Pentru acces din alte device-uri în LAN, vezi secțiunea 9.5

---

## 4. Servicii Partajate - Configurare

### 4.1 PostgreSQL - Două Database-uri

**Creare database-uri separate**:

```bash
# Connect as admin
psql -U deschide_admin -h 127.0.0.1 -d postgres

-- Create DEV database
CREATE DATABASE deschide_dev WITH OWNER deschide_admin ENCODING 'UTF8';

-- Create PROD database
CREATE DATABASE deschide_prod WITH OWNER deschide_admin ENCODING 'UTF8';

-- Grant permissions
GRANT ALL PRIVILEGES ON DATABASE deschide_dev TO deschide_admin;
GRANT ALL PRIVILEGES ON DATABASE deschide_prod TO deschide_admin;

-- Verify
\l deschide*
```

**Migrare date existente** (opțional):

```bash
# Dump existing data from current 'deschide' database
pg_dump -U deschide_admin -h 127.0.0.1 deschide > /tmp/deschide_backup.sql

# Restore to DEV
psql -U deschide_admin -h 127.0.0.1 -d deschide_dev < /tmp/deschide_backup.sql

# Restore to PROD (optional - sau folosește fixtures)
psql -U deschide_admin -h 127.0.0.1 -d deschide_prod < /tmp/deschide_backup.sql
```

### 4.2 Redis - Două Database-uri

**Configurare**: Redis suportă multiple databases (0-15 by default)

- **DB 1**: Development (namespace: `deschide_dev:*`)
- **DB 2**: Production (namespace: `deschide_prod:*`)

**Testare conexiune**:

```bash
# DEV
redis-cli -n 1 PING
redis-cli -n 1 KEYS "deschide_dev:*"

# PROD
redis-cli -n 2 PING
redis-cli -n 2 KEYS "deschide_prod:*"
```

**Configurare în Symfony** (vezi secțiunea 5):
```env
# DEV
REDIS_URL=redis://localhost:6379/1

# PROD
REDIS_URL=redis://localhost:6379/2
```

### 4.3 Elasticsearch - Indices Separate

**Strategy**: Prefix indices cu environment

- **DEV**: `deschide_articles_ro_dev`, `deschide_articles_en_dev`, `deschide_articles_ru_dev`
- **PROD**: `deschide_articles_ro_prod`, `deschide_articles_en_prod`, `deschide_articles_ru_prod`

**Creare indices**:

```bash
# DEV
APP_ENV=dev symfony console app:elasticsearch:create-index --env=dev
APP_ENV=dev symfony console app:elasticsearch:create-image-index --env=dev

# PROD
APP_ENV=prod symfony console app:elasticsearch:create-index --env=prod
APP_ENV=prod symfony console app:elasticsearch:create-image-index --env=prod
```

**Configurare în cod** (`src/Service/ElasticSearchService.php`):

```php
// Use environment-based index name
$indexName = 'deschide_articles_' . $locale . '_' . $_ENV['APP_ENV'];
```

### 4.4 RabbitMQ - Virtual Hosts Separate

**Creare vhosts**:

```bash
# Create virtual hosts
sudo rabbitmqctl add_vhost /dev
sudo rabbitmqctl add_vhost /prod

# Set permissions for guest user (or create dedicated users)
sudo rabbitmqctl set_permissions -p /dev guest ".*" ".*" ".*"
sudo rabbitmqctl set_permissions -p /prod guest ".*" ".*" ".*"

# Verify
sudo rabbitmqctl list_vhosts
```

**Configurare în Symfony**:

```env
# DEV
MESSENGER_TRANSPORT_DSN=amqp://guest:guest@localhost:5672/%2fdev/messages

# PROD
MESSENGER_TRANSPORT_DSN=amqp://guest:guest@localhost:5672/%2fprod/messages
```

**Note**: `%2f` = URL encoded `/`

**Management UI**: http://localhost:15672 (username: guest, password: guest)

### 4.5 Mercure Hub - Topic Prefixes

**Configurare**: Mercure nu necesită separare la nivel de server, folosim topic prefixes

- **DEV topics**: `dev/deschide_news/*`
- **PROD topics**: `prod/deschide_news/*`

**Publicare cu prefix**:

```php
// În MercurePublisher service
$topicPrefix = $_ENV['APP_ENV'] === 'prod' ? 'prod/' : 'dev/';
$topic = $topicPrefix . 'deschide_news/live_texts/' . $id;

$this->mercure->publish([
    'topics' => [$topic],
    'data' => json_encode($data)
]);
```

**Frontend subscription**:

```typescript
// Use env-based topic prefix
const topicPrefix = process.env.NODE_ENV === 'production' ? 'prod/' : 'dev/';
const topic = `${topicPrefix}deschide_news/live_texts/${id}`;

const eventSource = new EventSource(
  `${MERCURE_URL}?topic=${encodeURIComponent(topic)}`
);
```

### 4.6 Prometheus - Job Labels

**Configurare** (`/etc/prometheus/prometheus.yml`):

```yaml
global:
  scrape_interval: 15s
  evaluation_interval: 15s

scrape_configs:
  # Backend DEV
  - job_name: 'deschide_backend_dev'
    static_configs:
      - targets: ['127.0.0.1:8081']
        labels:
          environment: 'dev'
          app: 'backend'

  # Backend PROD
  - job_name: 'deschide_backend_prod'
    static_configs:
      - targets: ['127.0.0.1:9091']  # PHP-FPM metrics exporter port
        labels:
          environment: 'prod'
          app: 'backend'

  # Frontend DEV
  - job_name: 'deschide_frontend_dev'
    static_configs:
      - targets: ['127.0.0.1:3005']
        labels:
          environment: 'dev'
          app: 'frontend'

  # Frontend PROD
  - job_name: 'deschide_frontend_prod'
    static_configs:
      - targets: ['127.0.0.1:3006']
        labels:
          environment: 'prod'
          app: 'frontend'

  # Shared services (labeled as shared)
  - job_name: 'postgresql'
    static_configs:
      - targets: ['127.0.0.1:9187']  # postgres_exporter port
        labels:
          service: 'postgresql'

  - job_name: 'redis'
    static_configs:
      - targets: ['127.0.0.1:9121']  # redis_exporter port
        labels:
          service: 'redis'

  - job_name: 'elasticsearch'
    static_configs:
      - targets: ['127.0.0.1:9114']  # elasticsearch_exporter port
        labels:
          service: 'elasticsearch'

  - job_name: 'rabbitmq'
    static_configs:
      - targets: ['127.0.0.1:15692']  # RabbitMQ Prometheus plugin port
        labels:
          service: 'rabbitmq'

  - job_name: 'nginx'
    static_configs:
      - targets: ['127.0.0.1:9113']  # nginx_exporter port
        labels:
          service: 'nginx'
```

**Reload Prometheus**:

```bash
# After editing config
sudo systemctl reload prometheus

# Or send HUP signal
sudo pkill -HUP prometheus
```

### 4.7 Grafana - Dashboards pentru Ambele Medii

**Configurare**: Grafana va avea dashboards care filtrează după label `environment`

**Dashboards recomandate**:

1. **Overview Dashboard**: View both dev/prod side-by-side
2. **DEV Dashboard**: Filtered by `environment="dev"`
3. **PROD Dashboard**: Filtered by `environment="prod"`
4. **Services Dashboard**: Monitor shared services

**Exemplu query în Grafana**:

```promql
# Total requests - DEV only
sum(rate(symfony_requests_total{environment="dev"}[5m]))

# Total requests - PROD only
sum(rate(symfony_requests_total{environment="prod"}[5m]))

# Compare DEV vs PROD
sum by (environment) (rate(symfony_requests_total[5m]))
```

**Access**: http://localhost:3002 (admin/admin by default)

---

## 5. Backend Symfony - Configurare

### 5.1 Structură Fișiere Environment

```
deschide_backend/
├── .env                    # ❌ DELETED (nu mai este nevoie)
├── .env.example           # ✅ Template cu placeholder values
├── .env.local             # ✅ DEV configuration (NOT in git)
├── .env.prod.local        # ✅ PROD configuration (NOT in git)
├── .env.test              # ✅ Test environment (in git)
└── config/
    └── packages/
        ├── dev/           # DEV-specific configs
        ├── prod/          # PROD-specific configs
        └── test/          # TEST-specific configs
```

### 5.2 Configurare `.env.local` (DEV)

**Creare fișier**: `/var/www/deschide_news_app/deschide_backend/.env.local`

```env
###############################################################################
# DEVELOPMENT ENVIRONMENT CONFIGURATION
# This file is for LOCAL DEVELOPMENT ONLY and should NOT be committed to git
###############################################################################

###> symfony/framework-bundle ###
APP_ENV=dev
APP_DEBUG=1
APP_SECRET=dev_secret_change_in_production_32_chars_min
###< symfony/framework-bundle ###

###> symfony/routing ###
DEFAULT_URI=http://localhost:8081
###< symfony/routing ###

###> doctrine/doctrine-bundle ###
# DEV Database via PgBouncer
DATABASE_URL="postgresql://deschide_admin:sr324395@127.0.0.1:6432/deschide_dev?serverVersion=17&charset=utf8"

# Newscoop import (same for both environments)
NEWSCOOP_DATABASE_URL="mysql://root:sr324395@127.0.0.1:3306/newscoop?charset=utf8mb4&serverVersion=10.11.13-MariaDB"
###< doctrine/doctrine-bundle ###

###> redis ###
# Redis DB 1 for DEV
REDIS_URL=redis://localhost:6379/1
REDIS_PREFIX=deschide_dev
###< redis ###

###> nelmio/cors-bundle ###
# Allow DEV frontend
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'
###< nelmio/cors-bundle ###

###> lexik/jwt-authentication-bundle ###
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=28de3a1596c5ac006fd91b175a99c59a99f4fd12fab1e8979e2a51261437345e
JWT_TOKEN_TTL=3600
###< lexik/jwt-authentication-bundle ###

###> symfony/messenger ###
# RabbitMQ vhost: /dev
MESSENGER_TRANSPORT_DSN=amqp://guest:guest@localhost:5672/%2fdev/messages
###< symfony/messenger ###

###> elasticsearch ###
ELASTICSEARCH_HOST="https://localhost:9200"
ELASTICSEARCH_USER=elastic
ELASTICSEARCH_PASSWORD=WsAEcDWAbQjb5XGUnpvk
ELASTICSEARCH_API_KEY=SlZkLUxwb0JNSE0wbG55ZjJaTlA6MEhLaWFiRndma0RsclhYaHNad1VYUQ==
ELASTICSEARCH_VERIFY_SSL=0
ELASTICSEARCH_INDEX_PREFIX=deschide_dev
###< elasticsearch ###

###> symfony/mercure-bundle ###
# Mercure with DEV topic prefix
MERCURE_URL=http://localhost:3000/.well-known/mercure
MERCURE_PUBLIC_URL=http://localhost:3000/.well-known/mercure
MERCURE_JWT_SECRET=!ChangeThisMercureHubJWTSecretKey!
MERCURE_TOPIC_PREFIX=dev/deschide_news
###< symfony/mercure-bundle ###

###> frontend-integration ###
# DEV Frontend URL
FRONTEND_URL=http://localhost:3005
CDN_URL=http://127.0.0.1:8082
###< frontend-integration ###

###> varnish-cache ###
# Disabled in DEV
VARNISH_ENABLED=false
###< varnish-cache ###

###> cloudflare-cdn ###
# Disabled in DEV
CLOUDFLARE_ENABLED=false
###< cloudflare-cdn ###

###> symfony/mailer ###
# Use local SMTP or log to file in DEV
MAILER_DSN=smtp://localhost:1025
# Or: MAILER_DSN=null://null (discard emails)
###< symfony/mailer ###

###> monitoring ###
PROMETHEUS_ENABLED=true
###< monitoring ###
```

### 5.3 Configurare `.env.prod.local` (PROD)

**Creare fișier**: `/var/www/deschide_news_app/deschide_backend/.env.prod.local`

```env
###############################################################################
# PRODUCTION ENVIRONMENT CONFIGURATION (WSL Production-like)
# This file is for PRODUCTION TESTING on WSL and should NOT be committed to git
###############################################################################

###> symfony/framework-bundle ###
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=prod_secret_CHANGE_THIS_TO_RANDOM_STRING_32_CHARS_MIN
###< symfony/framework-bundle ###

###> symfony/routing ###
DEFAULT_URI=http://api.deschide.local
###< symfony/routing ###

###> doctrine/doctrine-bundle ###
# PROD Database via PgBouncer
DATABASE_URL="postgresql://deschide_admin:sr324395@127.0.0.1:6432/deschide_prod?serverVersion=17&charset=utf8"

# Newscoop import (same for both environments)
NEWSCOOP_DATABASE_URL="mysql://root:sr324395@127.0.0.1:3306/newscoop?charset=utf8mb4&serverVersion=10.11.13-MariaDB"
###< doctrine/doctrine-bundle ###

###> redis ###
# Redis DB 2 for PROD
REDIS_URL=redis://localhost:6379/2
REDIS_PREFIX=deschide_prod
###< redis ###

###> nelmio/cors-bundle ###
# Allow PROD frontend (deschide.local)
CORS_ALLOW_ORIGIN='^https?://(deschide\.local|api\.deschide\.local)(:[0-9]+)?$'
###< nelmio/cors-bundle ###

###> symfony/trust-proxies ###
# Trust Nginx proxy
TRUSTED_PROXIES=127.0.0.1,REMOTE_ADDR
TRUSTED_HEADERS=x-forwarded-for,x-forwarded-proto,x-forwarded-port,x-forwarded-host
###< symfony/trust-proxies ###

###> lexik/jwt-authentication-bundle ###
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=28de3a1596c5ac006fd91b175a99c59a99f4fd12fab1e8979e2a51261437345e
JWT_TOKEN_TTL=3600
###< lexik/jwt-authentication-bundle ###

###> symfony/messenger ###
# RabbitMQ vhost: /prod
MESSENGER_TRANSPORT_DSN=amqp://guest:guest@localhost:5672/%2fprod/messages
###< symfony/messenger ###

###> elasticsearch ###
ELASTICSEARCH_HOST="https://localhost:9200"
ELASTICSEARCH_USER=elastic
ELASTICSEARCH_PASSWORD=WsAEcDWAbQjb5XGUnpvk
ELASTICSEARCH_API_KEY=SlZkLUxwb0JNSE0wbG55ZjJaTlA6MEhLaWFiRndma0RsclhYaHNad1VYUQ==
ELASTICSEARCH_VERIFY_SSL=0
ELASTICSEARCH_INDEX_PREFIX=deschide_prod
###< elasticsearch ###

###> symfony/mercure-bundle ###
# Mercure with PROD topic prefix
MERCURE_URL=http://localhost:3000/.well-known/mercure
MERCURE_PUBLIC_URL=http://localhost:3000/.well-known/mercure
MERCURE_JWT_SECRET=!ChangeThisMercureHubJWTSecretKey!
MERCURE_TOPIC_PREFIX=prod/deschide_news
###< symfony/mercure-bundle ###

###> frontend-integration ###
# PROD Frontend URL
FRONTEND_URL=http://deschide.local
CDN_URL=http://127.0.0.1:8083
###< frontend-integration ###

###> varnish-cache ###
# Can be enabled in PROD if needed
VARNISH_ENABLED=false
VARNISH_HOST=127.0.0.1
VARNISH_PORT=6081
###< varnish-cache ###

###> cloudflare-cdn ###
# Can be enabled in PROD if needed
CLOUDFLARE_ENABLED=false
###< cloudflare-cdn ###

###> symfony/mailer ###
# Use real SMTP in PROD or service like SendGrid/SES
MAILER_DSN=smtp://smtp.example.com:587?username=user&password=pass
###< symfony/mailer ###

###> monitoring ###
PROMETHEUS_ENABLED=true
###< monitoring ###
```

### 5.4 Comenzi Backend Management

**DEV**:

```bash
cd /var/www/deschide_news_app/deschide_backend

# Start DEV server
symfony serve -d --port=8081

# Check status
symfony server:status

# View logs
symfony server:log

# Stop
symfony server:stop

# Clear cache
APP_ENV=dev symfony console cache:clear

# Run migrations
APP_ENV=dev symfony console doctrine:migrations:migrate

# Start messenger workers (DEV queue)
APP_ENV=dev symfony console messenger:consume async -vv
```

**PROD**:

```bash
cd /var/www/deschide_news_app/deschide_backend

# Clear and warmup cache
APP_ENV=prod APP_DEBUG=0 symfony console cache:clear
APP_ENV=prod APP_DEBUG=0 symfony console cache:warmup

# Run migrations
APP_ENV=prod symfony console doctrine:migrations:migrate --no-interaction

# Start messenger workers (PROD queue)
APP_ENV=prod symfony console messenger:consume async -vv --limit=100

# PHP-FPM is managed by systemd (see section 7.2)
```

---

## 6. Frontend Next.js - Configurare

### 6.1 Structură Fișiere Environment

```
deschide_frontend/
├── .env.local              # ✅ DEV configuration (NOT in git)
├── .env.production         # ✅ PROD configuration (NOT in git)
├── .env.example            # ✅ Template (in git)
└── next.config.js          # Load env vars
```

### 6.2 Configurare `.env.local` (DEV)

**Fișier**: `/var/www/deschide_news_app/deschide_frontend/.env.local`

```env
###############################################################################
# FRONTEND - DEVELOPMENT ENVIRONMENT
###############################################################################

NODE_ENV=development
PORT=3005

###> backend-api ###
# DEV Backend API
NEXT_PUBLIC_API_URL=http://127.0.0.1:8081
NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8081/api
###< backend-api ###

###> cdn ###
# DEV CDN for images/thumbnails
NEXT_PUBLIC_CDN_URL=http://127.0.0.1:8082
###< cdn ###

###> mercure ###
# Mercure Hub (DEV topic prefix handled in code)
NEXT_PUBLIC_MERCURE_URL=http://localhost:3000/.well-known/mercure
NEXT_PUBLIC_MERCURE_TOPIC_PREFIX=dev/deschide_news
###< mercure ###

###> app-configuration ###
NEXT_PUBLIC_APP_NAME="Deschide News"
NEXT_PUBLIC_DEFAULT_LOCALE=ro
NEXT_PUBLIC_AVAILABLE_LOCALES=ro,en,ru
###< app-configuration ###

###> site-urls ###
# Development site URL
NEXT_PUBLIC_SITE_URL=http://localhost:3005
###< site-urls ###

###> authentication ###
# Session secret (used for encrypting cookies)
SESSION_SECRET=dev_session_secret_change_in_production_at_least_32_chars_long
###< authentication ###

###> feature-flags ###
# Feature flags for development
NEXT_PUBLIC_ENABLE_SEARCH=true
NEXT_PUBLIC_ENABLE_REALTIME=true
NEXT_PUBLIC_ENABLE_ANALYTICS=false
###< feature-flags ###

###> analytics ###
NEXT_PUBLIC_TRACK_ENDPOINT=/api/track
###< analytics ###

###> monitoring ###
# Prometheus metrics endpoint
NEXT_PUBLIC_PROMETHEUS_ENABLED=true
###< monitoring ###
```

### 6.3 Configurare `.env.production` (PROD)

**Fișier**: `/var/www/deschide_news_app/deschide_frontend/.env.production`

```env
###############################################################################
# FRONTEND - PRODUCTION ENVIRONMENT (WSL Production-like)
###############################################################################

NODE_ENV=production
PORT=3006

###> backend-api ###
# PROD Backend API (via Nginx at api.deschide.local)
NEXT_PUBLIC_API_URL=http://api.deschide.local
NEXT_PUBLIC_API_BASE_URL=http://api.deschide.local/api
###< backend-api ###

###> cdn ###
# PROD CDN for images/thumbnails
NEXT_PUBLIC_CDN_URL=http://127.0.0.1:8083
# Or via Nginx: http://api.deschide.local/uploads
###< cdn ###

###> mercure ###
# Mercure Hub (PROD topic prefix handled in code)
NEXT_PUBLIC_MERCURE_URL=http://localhost:3000/.well-known/mercure
# Or via Nginx: http://api.deschide.local/.well-known/mercure
NEXT_PUBLIC_MERCURE_TOPIC_PREFIX=prod/deschide_news
###< mercure ###

###> app-configuration ###
NEXT_PUBLIC_APP_NAME="Deschide News"
NEXT_PUBLIC_DEFAULT_LOCALE=ro
NEXT_PUBLIC_AVAILABLE_LOCALES=ro,en,ru
###< app-configuration ###

###> site-urls ###
# Production site URL (deschide.local)
NEXT_PUBLIC_SITE_URL=http://deschide.local
###< site-urls ###

###> authentication ###
# Session secret (MUST be different from DEV)
SESSION_SECRET=prod_session_secret_MUST_CHANGE_THIS_TO_STRONG_RANDOM_STRING_MIN_32_CHARS
###< authentication ###

###> feature-flags ###
# Feature flags for production
NEXT_PUBLIC_ENABLE_SEARCH=true
NEXT_PUBLIC_ENABLE_REALTIME=true
NEXT_PUBLIC_ENABLE_ANALYTICS=true
###< feature-flags ###

###> analytics ###
NEXT_PUBLIC_TRACK_ENDPOINT=/api/track
NEXT_PUBLIC_GA_TRACKING_ID=
NEXT_PUBLIC_GTM_ID=
###< analytics ###

###> monitoring ###
# Prometheus metrics endpoint
NEXT_PUBLIC_PROMETHEUS_ENABLED=true
###< monitoring ###

###> security ###
# Security headers
NEXT_PUBLIC_CSP_ENABLED=true
###< security ###
```

### 6.4 Update `next.config.js`

**Fișier**: `/var/www/deschide_news_app/deschide_frontend/next.config.js`

```javascript
/** @type {import('next').NextConfig} */
const nextConfig = {
  // Enable React strict mode
  reactStrictMode: true,

  // Environment-based configuration
  env: {
    // These will be available in both server and client
    NEXT_PUBLIC_API_URL: process.env.NEXT_PUBLIC_API_URL,
    NEXT_PUBLIC_CDN_URL: process.env.NEXT_PUBLIC_CDN_URL,
    NEXT_PUBLIC_MERCURE_URL: process.env.NEXT_PUBLIC_MERCURE_URL,
  },

  // Image optimization
  images: {
    domains: ['127.0.0.1', 'localhost'],
    remotePatterns: [
      {
        protocol: 'http',
        hostname: '127.0.0.1',
        port: '8082', // DEV CDN
        pathname: '/uploads/**',
      },
      {
        protocol: 'http',
        hostname: '127.0.0.1',
        port: '8083', // PROD CDN
        pathname: '/uploads/**',
      },
      {
        protocol: 'http',
        hostname: 'localhost',
        pathname: '/uploads/**',
      },
    ],
  },

  // Internationalization
  i18n: {
    locales: ['ro', 'en', 'ru'],
    defaultLocale: 'ro',
    localeDetection: true,
  },

  // Production optimizations
  compiler: {
    removeConsole: process.env.NODE_ENV === 'production' ? {
      exclude: ['error', 'warn'],
    } : false,
  },

  // Turbopack for faster builds (Next.js 16)
  experimental: {
    turbo: {
      rules: {
        '*.svg': {
          loaders: ['@svgr/webpack'],
          as: '*.js',
        },
      },
    },
  },

  // Headers (security)
  async headers() {
    return [
      {
        source: '/:path*',
        headers: [
          {
            key: 'X-DNS-Prefetch-Control',
            value: 'on'
          },
          {
            key: 'X-Frame-Options',
            value: 'SAMEORIGIN'
          },
          {
            key: 'X-Content-Type-Options',
            value: 'nosniff'
          },
          {
            key: 'Referrer-Policy',
            value: 'origin-when-cross-origin'
          },
        ],
      },
    ];
  },

  // Rewrites (proxy API in production if needed)
  async rewrites() {
    // Only for production - proxy API requests to api.deschide.local
    if (process.env.NODE_ENV === 'production') {
      return [
        {
          source: '/api/:path*',
          destination: 'http://api.deschide.local/api/:path*',
        },
      ];
    }
    return [];
  },
};

module.exports = nextConfig;
```

### 6.5 Comenzi Frontend Management

**DEV**:

```bash
cd /var/www/deschide_news_app/deschide_frontend

# Install dependencies (first time)
pnpm install

# Start DEV server
pnpm dev

# Access at http://localhost:3005
```

**PROD**:

```bash
cd /var/www/deschide_news_app/deschide_frontend

# Build for production
NODE_ENV=production pnpm build

# Start production server
NODE_ENV=production PORT=3006 pnpm start

# Or use PM2 (recommended for production)
pm2 start ecosystem.config.js --only deschide_frontend_prod
pm2 logs deschide_frontend_prod
pm2 stop deschide_frontend_prod
```

### 6.6 PM2 Configuration pentru PROD

**Fișier**: `/var/www/deschide_news_app/deschide_frontend/ecosystem.config.js`

```javascript
module.exports = {
  apps: [
    // DEV instance
    {
      name: 'deschide_frontend_dev',
      script: 'pnpm',
      args: 'dev',
      cwd: '/var/www/deschide_news_app/deschide_frontend',
      watch: false,
      env: {
        NODE_ENV: 'development',
        PORT: 3005,
      },
    },
    // PROD instance
    {
      name: 'deschide_frontend_prod',
      script: 'pnpm',
      args: 'start',
      cwd: '/var/www/deschide_news_app/deschide_frontend',
      instances: 2, // Cluster mode for production
      exec_mode: 'cluster',
      watch: false,
      max_memory_restart: '500M',
      env: {
        NODE_ENV: 'production',
        PORT: 3006,
      },
      error_file: '/var/www/deschide_news_app/logs/frontend_prod_error.log',
      out_file: '/var/www/deschide_news_app/logs/frontend_prod_out.log',
      log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
    },
  ],
};
```

---

## 7. Nginx - Configurare

### 7.1 Arhitectură Nginx pentru PROD

```
┌─────────────────────────────────────────────────┐
│           Nginx (Port 80)                       │
│                                                 │
│  ┌─────────────────────────────────────────┐   │
│  │  Server Block (Production)              │   │
│  │                                         │   │
│  │  location / {                           │   │
│  │    → Proxy to Next.js (3006)           │   │
│  │  }                                      │   │
│  │                                         │   │
│  │  location /api {                        │   │
│  │    → Proxy to PHP-FPM (Unix socket)    │   │
│  │  }                                      │   │
│  │                                         │   │
│  │  location /uploads {                    │   │
│  │    → Serve static files (CDN)          │   │
│  │    → Or proxy to CDN server (8083)     │   │
│  │  }                                      │   │
│  │                                         │   │
│  │  location /.well-known/mercure {        │   │
│  │    → Proxy to Mercure (3000)           │   │
│  │  }                                      │   │
│  └─────────────────────────────────────────┘   │
└─────────────────────────────────────────────────┘
                    ▲
                    │
              localhost:80
```

### 7.2 Configurare PHP-FPM pentru PROD

**Creare pool separat**: `/etc/php/8.4/fpm/pool.d/deschide_prod.conf`

```ini
[deschide_prod]
user = www-data
group = www-data

; Unix socket
listen = /run/php/php8.4-fpm-prod.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

; Process management
pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 6
pm.max_requests = 500

; Environment variables
env[APP_ENV] = prod
env[APP_DEBUG] = 0

; PHP settings
php_admin_value[error_log] = /var/log/php8.4-fpm-prod.log
php_admin_flag[log_errors] = on
php_admin_value[memory_limit] = 256M
php_value[upload_max_filesize] = 20M
php_value[post_max_size] = 20M
```

**Restart PHP-FPM**:

```bash
sudo systemctl restart php8.4-fpm
sudo systemctl status php8.4-fpm
```

### 7.3 Configurare Nginx PROD

**Fișier**: `/etc/nginx/sites-available/deschide_prod`

```nginx
# Upstream definitions
upstream nextjs_prod {
    server 127.0.0.1:3006;
    keepalive 32;
}

upstream mercure_hub {
    server 127.0.0.1:3000;
    keepalive 8;
}

# CDN server (optional - poate fi și static files direct)
upstream cdn_prod {
    server 127.0.0.1:8083;
}

# ============================================================
# BACKEND API - api.deschide.local
# ============================================================
server {
    listen 80;
    server_name api.deschide.local;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;

    # Max upload size
    client_max_body_size 20M;

    # Logging
    access_log /var/log/nginx/deschide_prod_access.log combined;
    error_log /var/log/nginx/deschide_prod_error.log warn;

    # Root directory for static files (backend uploads)
    root /var/www/deschide_news_app/deschide_backend/public;

    # === BACKEND API === (Symfony via PHP-FPM)
    location ~ ^/(api|_profiler|_wdt) {
        try_files $uri /index.php$is_args$args;
    }

    # PHP-FPM handler
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm-prod.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;

        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_param APP_ENV prod;
        fastcgi_param APP_DEBUG 0;

        # Timeouts
        fastcgi_read_timeout 300;
        fastcgi_send_timeout 300;

        # Forward original request info
        fastcgi_param HTTP_X_FORWARDED_FOR $proxy_add_x_forwarded_for;
        fastcgi_param HTTP_X_FORWARDED_PROTO $scheme;
        fastcgi_param HTTP_X_FORWARDED_HOST $host;

        internal;
    }

    # === STATIC ASSETS / CDN ===
    location /uploads {
        alias /var/www/deschide_news_app/deschide_backend/public/uploads;
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;

        # Enable CORS for images
        add_header Access-Control-Allow-Origin "*";
        add_header Access-Control-Allow-Methods "GET, OPTIONS";
    }

    # === MERCURE HUB ===
    location /.well-known/mercure {
        proxy_pass http://mercure_hub;
        proxy_http_version 1.1;
        proxy_set_header Connection "";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # SSE specific
        proxy_buffering off;
        proxy_cache off;
        proxy_read_timeout 24h;
        proxy_connect_timeout 5s;

        # Enable chunked transfer encoding
        chunked_transfer_encoding on;
    }

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }
}

# ============================================================
# FRONTEND - deschide.local
# ============================================================
server {
    listen 80;
    server_name deschide.local;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;

    # Logging
    access_log /var/log/nginx/deschide_frontend_access.log combined;
    error_log /var/log/nginx/deschide_frontend_error.log warn;

    # === FRONTEND (Next.js) ===
    location / {
        proxy_pass http://nextjs_prod;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
        proxy_cache_bypass $http_upgrade;

        # Timeouts
        proxy_read_timeout 60s;
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
    }

    # Next.js static assets
    location /_next/static {
        proxy_pass http://nextjs_prod;
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }
}
```

**Activare site**:

```bash
# Create symlink
sudo ln -s /etc/nginx/sites-available/deschide_prod /etc/nginx/sites-enabled/

# Test configuration
sudo nginx -t

# Reload Nginx
sudo systemctl reload nginx

# Check status
sudo systemctl status nginx
```

### 7.4 Configurare CDN Server Separat pentru PROD

**Locație**: Port 8083 pentru PROD CDN

**Opțiune 1: Folosește Nginx** (recomandat)

**Fișier**: `/etc/nginx/sites-available/deschide_cdn_prod`

```nginx
server {
    listen 8083;
    server_name _;

    root /var/www/deschide_news_app/deschide_backend/public;

    # Logging
    access_log /var/log/nginx/cdn_prod_access.log combined;
    error_log /var/log/nginx/cdn_prod_error.log warn;

    # Serve static files
    location /uploads {
        alias /var/www/deschide_news_app/deschide_backend/public/uploads;
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;

        # CORS
        add_header Access-Control-Allow-Origin "*";
        add_header Access-Control-Allow-Methods "GET, OPTIONS";

        # Security
        add_header X-Content-Type-Options "nosniff";
    }

    # Deny everything else
    location / {
        deny all;
    }
}
```

**Activare**:

```bash
sudo ln -s /etc/nginx/sites-available/deschide_cdn_prod /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

**Opțiune 2: Folosește Python SimpleHTTPServer**

```bash
# Start CDN server on port 8083
cd /var/www/deschide_news_app/deschide_backend/public
python3 -m http.server 8083 &
```

---

## 8. Scripturi Management

### 8.1 Script: Start DEV Environment

**Fișier**: `/var/www/deschide_news_app/scripts/start-dev.sh`

```bash
#!/bin/bash
set -e

echo "=========================================="
echo "  Starting DEV Environment"
echo "=========================================="

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

PROJECT_ROOT="/var/www/deschide_news_app"

# Check services
echo -e "${YELLOW}Checking shared services...${NC}"
services=("postgresql" "redis-server" "elasticsearch" "rabbitmq-server" "mercure" "prometheus" "grafana-server")

for service in "${services[@]}"; do
    if systemctl is-active --quiet $service 2>/dev/null; then
        echo -e "${GREEN}✓${NC} $service is running"
    else
        echo -e "${YELLOW}⚠${NC} $service is not running, attempting to start..."
        sudo systemctl start $service || echo "Failed to start $service"
    fi
done

# Start Backend DEV
echo -e "\n${YELLOW}Starting Backend DEV (port 8081)...${NC}"
cd "$PROJECT_ROOT/deschide_backend"
symfony server:start -d --port=8081
echo -e "${GREEN}✓${NC} Backend DEV started on http://127.0.0.1:8081"

# Start CDN DEV
echo -e "\n${YELLOW}Starting CDN DEV (port 8082)...${NC}"
if ! lsof -ti:8082 > /dev/null 2>&1; then
    cd "$PROJECT_ROOT/deschide_backend/public"
    nohup python3 -m http.server 8082 > /var/log/cdn_dev.log 2>&1 &
    echo $! > /tmp/cdn_dev.pid
    echo -e "${GREEN}✓${NC} CDN DEV started on http://127.0.0.1:8082"
else
    echo -e "${GREEN}✓${NC} CDN DEV already running on port 8082"
fi

# Start Frontend DEV
echo -e "\n${YELLOW}Starting Frontend DEV (port 3005)...${NC}"
cd "$PROJECT_ROOT/deschide_frontend"
pm2 start ecosystem.config.js --only deschide_frontend_dev
echo -e "${GREEN}✓${NC} Frontend DEV started on http://localhost:3005"

# Start Messenger Workers DEV (optional)
echo -e "\n${YELLOW}Start Messenger workers for DEV? (y/n)${NC}"
read -r start_workers
if [[ "$start_workers" == "y" ]]; then
    cd "$PROJECT_ROOT/deschide_backend"
    nohup symfony console messenger:consume async -vv --env=dev > /var/log/messenger_dev.log 2>&1 &
    echo $! > /tmp/messenger_dev.pid
    echo -e "${GREEN}✓${NC} Messenger workers started (DEV)"
fi

echo -e "\n${GREEN}=========================================="
echo "  DEV Environment Started Successfully!"
echo "==========================================${NC}"
echo ""
echo "Access points:"
echo "  - Frontend: http://localhost:3005"
echo "  - Backend API: http://127.0.0.1:8081"
echo "  - API Docs: http://127.0.0.1:8081/api"
echo "  - CDN: http://127.0.0.1:8082/uploads/"
echo ""
echo "Monitoring:"
echo "  - Prometheus: http://localhost:9090"
echo "  - Grafana: http://localhost:3002"
echo "  - RabbitMQ: http://localhost:15672"
echo ""
```

**Execuție**:

```bash
chmod +x /var/www/deschide_news_app/scripts/start-dev.sh
/var/www/deschide_news_app/scripts/start-dev.sh
```

### 8.2 Script: Start PROD Environment

**Fișier**: `/var/www/deschide_news_app/scripts/start-prod.sh`

```bash
#!/bin/bash
set -e

echo "=========================================="
echo "  Starting PROD Environment"
echo "=========================================="

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

PROJECT_ROOT="/var/www/deschide_news_app"

# Check services
echo -e "${YELLOW}Checking shared services...${NC}"
services=("postgresql" "redis-server" "elasticsearch" "rabbitmq-server" "mercure" "prometheus" "grafana-server" "nginx" "php8.4-fpm")

for service in "${services[@]}"; do
    if systemctl is-active --quiet $service 2>/dev/null; then
        echo -e "${GREEN}✓${NC} $service is running"
    else
        echo -e "${YELLOW}⚠${NC} $service is not running, attempting to start..."
        sudo systemctl start $service || echo -e "${RED}Failed to start $service${NC}"
    fi
done

# Build and start Frontend PROD
echo -e "\n${YELLOW}Building Frontend PROD...${NC}"
cd "$PROJECT_ROOT/deschide_frontend"
NODE_ENV=production pnpm build

echo -e "${YELLOW}Starting Frontend PROD (port 3006)...${NC}"
pm2 start ecosystem.config.js --only deschide_frontend_prod
echo -e "${GREEN}✓${NC} Frontend PROD started on http://localhost:3006"

# Clear cache Backend PROD
echo -e "\n${YELLOW}Clearing Backend PROD cache...${NC}"
cd "$PROJECT_ROOT/deschide_backend"
APP_ENV=prod APP_DEBUG=0 symfony console cache:clear
APP_ENV=prod APP_DEBUG=0 symfony console cache:warmup
echo -e "${GREEN}✓${NC} Backend cache cleared and warmed up"

# Start CDN PROD
echo -e "\n${YELLOW}Starting CDN PROD (port 8083)...${NC}"
if ! lsof -ti:8083 > /dev/null 2>&1; then
    cd "$PROJECT_ROOT/deschide_backend/public"
    nohup python3 -m http.server 8083 > /var/log/cdn_prod.log 2>&1 &
    echo $! > /tmp/cdn_prod.pid
    echo -e "${GREEN}✓${NC} CDN PROD started on http://127.0.0.1:8083"
else
    echo -e "${GREEN}✓${NC} CDN PROD already running on port 8083"
fi

# Start Messenger Workers PROD
echo -e "\n${YELLOW}Starting Messenger workers PROD...${NC}"
cd "$PROJECT_ROOT/deschide_backend"
nohup symfony console messenger:consume async -vv --env=prod --limit=100 > /var/log/messenger_prod.log 2>&1 &
echo $! > /tmp/messenger_prod.pid
echo -e "${GREEN}✓${NC} Messenger workers started (PROD)"

# Check if Nginx is configured and serving port 80
echo -e "\n${YELLOW}Verifying Nginx configuration...${NC}"
if sudo nginx -t 2>&1 | grep -q "successful"; then
    echo -e "${GREEN}✓${NC} Nginx configuration is valid"
    sudo systemctl reload nginx
else
    echo -e "${RED}✗${NC} Nginx configuration has errors"
    exit 1
fi

echo -e "\n${GREEN}=========================================="
echo "  PROD Environment Started Successfully!"
echo "==========================================${NC}"
echo ""
echo "Access points:"
echo "  - Frontend: http://deschide.local"
echo "  - Backend API: http://api.deschide.local"
echo "  - API Docs: http://api.deschide.local/api"
echo "  - Frontend (direct): http://localhost:3006"
echo "  - CDN: http://127.0.0.1:8083/uploads/"
echo ""
echo "Monitoring:"
echo "  - Prometheus: http://localhost:9090"
echo "  - Grafana: http://localhost:3002"
echo "  - RabbitMQ: http://localhost:15672"
echo ""
echo "Note: Make sure /etc/hosts is configured with deschide.local and api.deschide.local"
echo "Tip: To expose PROD publicly, see section 12 for ngrok setup."
echo ""
```

**Execuție**:

```bash
chmod +x /var/www/deschide_news_app/scripts/start-prod.sh
/var/www/deschide_news_app/scripts/start-prod.sh
```

### 8.3 Script: Stop DEV Environment

**Fișier**: `/var/www/deschide_news_app/scripts/stop-dev.sh`

```bash
#!/bin/bash

echo "Stopping DEV Environment..."

GREEN='\033[0;32m'
NC='\033[0m'

# Stop Backend DEV
cd /var/www/deschide_news_app/deschide_backend
symfony server:stop
echo -e "${GREEN}✓${NC} Backend DEV stopped"

# Stop Frontend DEV
pm2 stop deschide_frontend_dev
echo -e "${GREEN}✓${NC} Frontend DEV stopped"

# Stop CDN DEV
if [ -f /tmp/cdn_dev.pid ]; then
    kill $(cat /tmp/cdn_dev.pid) 2>/dev/null || true
    rm /tmp/cdn_dev.pid
    echo -e "${GREEN}✓${NC} CDN DEV stopped"
fi

# Stop Messenger workers DEV
if [ -f /tmp/messenger_dev.pid ]; then
    kill $(cat /tmp/messenger_dev.pid) 2>/dev/null || true
    rm /tmp/messenger_dev.pid
    echo -e "${GREEN}✓${NC} Messenger workers DEV stopped"
fi

echo -e "${GREEN}DEV Environment stopped${NC}"
```

### 8.4 Script: Stop PROD Environment

**Fișier**: `/var/www/deschide_news_app/scripts/stop-prod.sh`

```bash
#!/bin/bash

echo "Stopping PROD Environment..."

GREEN='\033[0;32m'
NC='\033[0m'

# Stop Frontend PROD
pm2 stop deschide_frontend_prod
echo -e "${GREEN}✓${NC} Frontend PROD stopped"

# Stop CDN PROD
if [ -f /tmp/cdn_prod.pid ]; then
    kill $(cat /tmp/cdn_prod.pid) 2>/dev/null || true
    rm /tmp/cdn_prod.pid
    echo -e "${GREEN}✓${NC} CDN PROD stopped"
fi

# Stop Messenger workers PROD
if [ -f /tmp/messenger_prod.pid ]; then
    kill $(cat /tmp/messenger_prod.pid) 2>/dev/null || true
    rm /tmp/messenger_prod.pid
    echo -e "${GREEN}✓${NC} Messenger workers PROD stopped"
fi

echo -e "${GREEN}PROD Environment stopped${NC}"
echo ""
echo "Note: Nginx and PHP-FPM are still running (system services)"
echo "To stop them: sudo systemctl stop nginx php8.4-fpm"
```

### 8.5 Script: Status Check

**Fișier**: `/var/www/deschide_news_app/scripts/status.sh`

```bash
#!/bin/bash

echo "=========================================="
echo "  Environment Status Check"
echo "=========================================="

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

check_port() {
    if lsof -ti:$1 > /dev/null 2>&1; then
        echo -e "${GREEN}✓${NC} Port $1: $2"
    else
        echo -e "${RED}✗${NC} Port $1: $2 (not running)"
    fi
}

check_service() {
    if systemctl is-active --quiet $1 2>/dev/null; then
        echo -e "${GREEN}✓${NC} $1 is running"
    else
        echo -e "${RED}✗${NC} $1 is not running"
    fi
}

echo -e "\n${YELLOW}=== Shared Services ===${NC}"
check_service postgresql
check_service redis-server
check_service elasticsearch
check_service rabbitmq-server
check_service mercure
check_service prometheus
check_service grafana-server
check_service nginx
check_service php8.4-fpm

echo -e "\n${YELLOW}=== DEV Environment ===${NC}"
check_port 8081 "Backend DEV (Symfony)"
check_port 3005 "Frontend DEV (Next.js)"
check_port 8082 "CDN DEV"

echo -e "\n${YELLOW}=== PROD Environment ===${NC}"
check_port 80 "Nginx (PROD gateway)"
check_port 3006 "Frontend PROD (Next.js)"
check_port 8083 "CDN PROD"

echo -e "\n${YELLOW}=== Monitoring ===${NC}"
check_port 9090 "Prometheus"
check_port 3002 "Grafana"
check_port 15672 "RabbitMQ Management"

echo -e "\n${YELLOW}=== PM2 Status ===${NC}"
pm2 list

echo ""
```

**Execuție**:

```bash
chmod +x /var/www/deschide_news_app/scripts/*.sh
/var/www/deschide_news_app/scripts/status.sh
```

---

## 9. Workflow Zilnic

### 9.1 Dezvoltare Normală (DEV)

```bash
# Dimineața - Start DEV environment
~/deschide_news_app/scripts/start-dev.sh

# Lucrezi pe:
# - http://localhost:3005 (Frontend)
# - http://127.0.0.1:8081 (Backend API)

# Check status oricând
~/deschide_news_app/scripts/status.sh

# Seara - Stop DEV environment
~/deschide_news_app/scripts/stop-dev.sh
```

### 9.2 Testare Production-like (PROD)

```bash
# 1. Verifică configurarea /etc/hosts
cat /etc/hosts | grep deschide.local

# 2. Pornește PROD environment
~/deschide_news_app/scripts/start-prod.sh

# 3. Testează aplicația
# - Frontend: http://deschide.local
# - Backend API: http://api.deschide.local
# - Direct frontend: http://localhost:3006

# 4. Monitor logs
pm2 logs deschide_frontend_prod
tail -f /var/log/nginx/deschide_frontend_access.log
tail -f /var/log/nginx/deschide_prod_access.log

# 5. Când termini testarea
~/deschide_news_app/scripts/stop-prod.sh
```

### 9.3 Rulare Simultană DEV + PROD

```bash
# Este posibil să rulezi ambele în paralel

# Start DEV
~/deschide_news_app/scripts/start-dev.sh

# Start PROD
~/deschide_news_app/scripts/start-prod.sh

# Acum ai:
# - DEV Frontend: http://localhost:3005
# - DEV Backend: http://localhost:8081
# - PROD Frontend: http://deschide.local
# - PROD Backend: http://api.deschide.local

# Check status
~/deschide_news_app/scripts/status.sh
```

**Resurse consumate**: Ambele medii vor folosi resursele sistemului, deci asigură-te că ai suficient RAM/CPU.

### 9.4 Migrare Date între DEV și PROD

**Din DEV în PROD**:

```bash
# 1. Dump DEV database
pg_dump -U deschide_admin -h 127.0.0.1 deschide_dev > /tmp/dev_to_prod.sql

# 2. Restore în PROD database
psql -U deschide_admin -h 127.0.0.1 -d deschide_prod < /tmp/dev_to_prod.sql

# 3. Reindex Elasticsearch PROD
cd /var/www/deschide_news_app/deschide_backend
APP_ENV=prod symfony console app:elasticsearch:index-articles

# 4. Clear PROD cache
APP_ENV=prod symfony console cache:clear
redis-cli -n 2 FLUSHDB
```

**Din PROD în DEV**:

```bash
# Similar, dar invers
pg_dump -U deschide_admin -h 127.0.0.1 deschide_prod > /tmp/prod_to_dev.sql
psql -U deschide_admin -h 127.0.0.1 -d deschide_dev < /tmp/prod_to_dev.sql
APP_ENV=dev symfony console app:elasticsearch:index-articles
redis-cli -n 1 FLUSHDB
```

### 9.5 Acces din LAN (alte device-uri)

**Obține IP-ul calculatorului**:

```bash
# Pe WSL
ip addr show eth0 | grep "inet\b" | awk '{print $2}' | cut -d/ -f1

# Sau pe Windows
ipconfig
# Caută IP-ul la "IPv4 Address" pentru adaptorul de rețea activ
```

**Acces din alte device-uri în rețeaua locală**:

```
# DEV Frontend
http://<IP-calculatorului>:3005

# DEV Backend
http://<IP-calculatorului>:8081

# PROD - Opțiunea 1: Prin IP direct (Nginx nu rezolvă domenii locale)
http://<IP-calculatorului>:3006  # Frontend direct
# ATENȚIE: Aplicația va încerca să se conecteze la api.deschide.local
# care nu va funcționa din alte device-uri fără configurare suplimentară

# PROD - Opțiunea 2: Configurare /etc/hosts pe device-ul remote
# Pe device-ul remote (ex: phone, altă mașină), editează hosts:
# <IP-calculatorului>   deschide.local
# <IP-calculatorului>   api.deschide.local
# Apoi accesează:
http://deschide.local  # Frontend
http://api.deschide.local  # Backend
```

**Note**:
- Firewall-ul Windows trebuie să permită conexiuni pe porturile respective
- Pentru PROD, cel mai simplu este să accesezi direct portul 3006 pentru frontend
- Pentru acces complet funcțional PROD din LAN, configurează /etc/hosts pe device-ul remote
- Alternativ, folosește ngrok (vezi secțiunea 12) pentru acces complet din exterior

---

## 10. Monitorizare și Debugging

### 10.1 Logs Locations

| Serviciu | Log File |
|----------|----------|
| **Backend DEV** | `symfony server:log` (in-memory) |
| **Backend PROD** | `/var/log/php8.4-fpm-prod.log` |
| **Frontend DEV** | PM2: `pm2 logs deschide_frontend_dev` |
| **Frontend PROD** | PM2: `pm2 logs deschide_frontend_prod` |
| **Nginx Access** | `/var/log/nginx/deschide_prod_access.log` |
| **Nginx Error** | `/var/log/nginx/deschide_prod_error.log` |
| **CDN DEV** | `/var/log/cdn_dev.log` |
| **CDN PROD** | `/var/log/cdn_prod.log` |
| **Messenger DEV** | `/var/log/messenger_dev.log` |
| **Messenger PROD** | `/var/log/messenger_prod.log` |
| **PostgreSQL** | `/var/log/postgresql/postgresql-17-main.log` |
| **Redis** | `/var/log/redis/redis-server.log` |
| **Elasticsearch** | `/var/log/elasticsearch/elasticsearch.log` |
| **RabbitMQ** | `/var/log/rabbitmq/` |

### 10.2 Quick Log Commands

```bash
# Backend logs (DEV)
symfony server:log

# Backend logs (PROD)
tail -f /var/log/php8.4-fpm-prod.log

# Frontend logs
pm2 logs deschide_frontend_dev
pm2 logs deschide_frontend_prod

# Nginx logs
tail -f /var/log/nginx/deschide_prod_access.log
tail -f /var/log/nginx/deschide_prod_error.log

# All errors
sudo tail -f /var/log/nginx/error.log /var/log/php8.4-fpm-prod.log

# Messenger workers
tail -f /var/log/messenger_dev.log
tail -f /var/log/messenger_prod.log

# Services
sudo journalctl -u postgresql -f
sudo journalctl -u redis-server -f
sudo journalctl -u elasticsearch -f
sudo journalctl -u rabbitmq-server -f
```

### 10.3 Prometheus Queries

**Access**: http://localhost:9090

**Queries utile**:

```promql
# Request rate DEV vs PROD
sum by (environment) (rate(http_requests_total[5m]))

# Error rate
sum by (environment) (rate(http_requests_total{status=~"5.."}[5m]))

# Database connections
pg_stat_database_numbackends{datname=~"deschide.*"}

# Redis memory
redis_memory_used_bytes

# Queue depth
rabbitmq_queue_messages{vhost=~"/dev|/prod"}

# PHP-FPM processes
phpfpm_active_processes
```

### 10.4 Grafana Dashboards

**Access**: http://localhost:3002 (admin/admin)

**Dashboards recomandate**:

1. **DEV vs PROD Overview**
   - Request rate per environment
   - Error rate per environment
   - Response time percentiles

2. **Backend Performance**
   - Doctrine query count
   - Cache hit rate
   - Symfony profiler data

3. **Frontend Performance**
   - Next.js SSR time
   - Client-side errors
   - Page load time

4. **Infrastructure Health**
   - PostgreSQL connections, transactions
   - Redis memory, operations
   - Elasticsearch indexing rate
   - RabbitMQ queue depth

### 10.5 Health Checks

**Endpoints**:

```bash
# Backend DEV
curl http://localhost:8081/health

# Backend PROD (via Nginx)
curl http://api.deschide.local/health
curl http://api.deschide.local/api

# Frontend DEV
curl http://localhost:3005

# Frontend PROD (via Nginx)
curl http://deschide.local

# Frontend PROD (direct)
curl http://localhost:3006

# Services
curl http://localhost:9200/_cluster/health # Elasticsearch
curl http://localhost:15672/api/healthchecks/node # RabbitMQ
redis-cli PING # Redis
psql -U deschide_admin -h 127.0.0.1 -c "SELECT 1" # PostgreSQL
```

### 10.6 Debugging CORS Issues

**Simptom**: Frontend nu poate face request la backend

**Check**:

```bash
# Test CORS headers DEV
curl -I -X OPTIONS \
  -H "Origin: http://localhost:3005" \
  -H "Access-Control-Request-Method: GET" \
  http://localhost:8081/api/articles

# Test CORS headers PROD
curl -I -X OPTIONS \
  -H "Origin: http://deschide.local" \
  -H "Access-Control-Request-Method: GET" \
  http://api.deschide.local/api/articles
```

**Solution**: Verifică `CORS_ALLOW_ORIGIN` în `.env.local` / `.env.prod.local`

**Common issues**:
- `/etc/hosts` nu este configurat corect
- Nginx nu este pornit sau configurat greșit
- Browser cache - încearcă în incognito mode

---

## 11. Checklist Implementare

### 11.1 Pregătire Inițială

- [ ] **Backup date existente**
  ```bash
  pg_dump -U deschide_admin -h 127.0.0.1 deschide > /tmp/backup_before_split.sql
  ```

- [ ] **Configurare /etc/hosts pentru domenii locale PROD**
  ```bash
  # Pe WSL
  sudo nano /etc/hosts
  # Adaugă:
  # 127.0.0.1   deschide.local
  # 127.0.0.1   api.deschide.local

  # Verificare
  ping -c 2 deschide.local
  ping -c 2 api.deschide.local
  ```

- [ ] **Configurare /etc/hosts pe Windows** (opțional)
  ```
  # Fișier: C:\Windows\System32\drivers\etc\hosts
  # Adaugă (cu drepturi Administrator):
  127.0.0.1   deschide.local
  127.0.0.1   api.deschide.local
  ```

- [ ] **Verificare servicii**
  ```bash
  systemctl status postgresql redis-server elasticsearch rabbitmq-server
  ```

- [ ] **Creare directoare logs**
  ```bash
  sudo mkdir -p /var/www/deschide_news_app/logs
  sudo chown -R $USER:$USER /var/www/deschide_news_app/logs
  ```

### 11.2 Configurare Servicii Partajate

- [ ] **PostgreSQL**
  - [ ] Create `deschide_dev` database
  - [ ] Create `deschide_prod` database
  - [ ] Restore data to DEV
  - [ ] Test connections

- [ ] **Redis**
  - [ ] Test connection to DB 1 (DEV)
  - [ ] Test connection to DB 2 (PROD)
  - [ ] Verify namespaces

- [ ] **Elasticsearch**
  - [ ] Create DEV indices
  - [ ] Create PROD indices
  - [ ] Index existing data

- [ ] **RabbitMQ**
  - [ ] Create `/dev` vhost
  - [ ] Create `/prod` vhost
  - [ ] Set permissions
  - [ ] Test connections

- [ ] **Prometheus**
  - [ ] Update `prometheus.yml` with job labels
  - [ ] Reload Prometheus
  - [ ] Verify scraping

- [ ] **Grafana**
  - [ ] Create dashboards for DEV
  - [ ] Create dashboards for PROD
  - [ ] Test queries

### 11.3 Configurare Backend

- [ ] **Environment Files**
  - [ ] Create `.env.local` (DEV)
  - [ ] Create `.env.prod.local` (PROD)
  - [ ] Update `.env.example`
  - [ ] Verify all variables set

- [ ] **PHP-FPM**
  - [ ] Create PROD pool config
  - [ ] Restart PHP-FPM
  - [ ] Test socket

- [ ] **Test Backend DEV**
  ```bash
  cd /var/www/deschide_news_app/deschide_backend
  symfony serve -d --port=8081
  curl http://127.0.0.1:8081/api
  ```

- [ ] **Test Backend PROD**
  ```bash
  APP_ENV=prod symfony console cache:clear
  # Test via Nginx (after Nginx config)
  ```

### 11.4 Configurare Frontend

- [ ] **Environment Files**
  - [ ] Create `.env.local` (DEV)
  - [ ] Create `.env.production` (PROD)
  - [ ] Update `next.config.js`

- [ ] **PM2 Configuration**
  - [ ] Create/update `ecosystem.config.js`
  - [ ] Test PM2 startup

- [ ] **Test Frontend DEV**
  ```bash
  cd /var/www/deschide_news_app/deschide_frontend
  pnpm dev
  # Access http://localhost:3005
  ```

- [ ] **Test Frontend PROD**
  ```bash
  NODE_ENV=production pnpm build
  NODE_ENV=production PORT=3006 pnpm start
  # Access http://localhost:3006
  ```

### 11.5 Configurare Nginx

- [ ] **Create Config Files**
  - [ ] `/etc/nginx/sites-available/deschide_prod`
  - [ ] `/etc/nginx/sites-available/deschide_cdn_prod`

- [ ] **Enable Sites**
  ```bash
  sudo ln -s /etc/nginx/sites-available/deschide_prod /etc/nginx/sites-enabled/
  sudo ln -s /etc/nginx/sites-available/deschide_cdn_prod /etc/nginx/sites-enabled/
  ```

- [ ] **Test Configuration**
  ```bash
  sudo nginx -t
  sudo systemctl reload nginx
  ```

- [ ] **Test Nginx Routing**
  ```bash
  # Test frontend
  curl http://deschide.local/ # Should reach Next.js

  # Test backend API
  curl http://api.deschide.local/api # Should reach Symfony

  # Test static files
  curl http://api.deschide.local/uploads/ # Should serve static files

  # Test in browser
  # Open: http://deschide.local
  # Open: http://api.deschide.local/api
  ```

### 11.6 Scripturi Management

- [ ] **Create scripts directory**
  ```bash
  mkdir -p /var/www/deschide_news_app/scripts
  ```

- [ ] **Create scripts**
  - [ ] `start-dev.sh`
  - [ ] `start-prod.sh`
  - [ ] `stop-dev.sh`
  - [ ] `stop-prod.sh`
  - [ ] `status.sh`

- [ ] **Make executable**
  ```bash
  chmod +x /var/www/deschide_news_app/scripts/*.sh
  ```

- [ ] **Test scripts**
  ```bash
  ./scripts/status.sh
  ./scripts/start-dev.sh
  ./scripts/status.sh
  ./scripts/stop-dev.sh
  ```

### 11.7 Testare End-to-End

**DEV Environment**:
- [ ] Start DEV environment
- [ ] Access frontend: http://localhost:3005
- [ ] Login to admin
- [ ] Create test article
- [ ] Upload image
- [ ] Check Elasticsearch indexing
- [ ] Check RabbitMQ messages
- [ ] Check Prometheus metrics
- [ ] Stop DEV environment

**PROD Environment**:
- [ ] Start PROD environment
- [ ] Access frontend: http://deschide.local
- [ ] Access backend: http://api.deschide.local
- [ ] Login to admin
- [ ] Create test article
- [ ] Check all functionality
- [ ] Monitor logs
- [ ] Stop PROD environment

### 11.8 Documentație

- [ ] **Update CLAUDE.md** with new setup
- [ ] **Update README.md** with quick start
- [ ] **Document env variables** in `.env.example`
- [ ] **Add troubleshooting section** for common issues

### 11.9 Backup și Recovery

- [ ] **Backup configurații**
  ```bash
  tar -czf /tmp/deschide_config_backup.tar.gz \
    /var/www/deschide_news_app/deschide_backend/.env.local \
    /var/www/deschide_news_app/deschide_backend/.env.prod.local \
    /var/www/deschide_news_app/deschide_frontend/.env.local \
    /var/www/deschide_news_app/deschide_frontend/.env.production \
    /etc/nginx/sites-available/deschide_* \
    /etc/php/8.4/fpm/pool.d/deschide_prod.conf
  ```

- [ ] **Test recovery procedure**
- [ ] **Document rollback steps**

---

## 12. OPTIONAL: Expunere Publică cu Ngrok

Dacă ai nevoie să expui aplicația PROD pentru accesare din internet (testare pe mobile, demo pentru clienți, webhooks externe), poți folosi ngrok.

### 12.1 Instalare Ngrok pe WSL

```bash
# Download ngrok
curl -s https://ngrok-agent.s3.amazonaws.com/ngrok.asc | sudo tee /etc/apt/trusted.gpg.d/ngrok.asc >/dev/null
echo "deb https://ngrok-agent.s3.amazonaws.com buster main" | sudo tee /etc/apt/sources.list.d/ngrok.list
sudo apt update
sudo apt install ngrok

# Verify installation
ngrok version
```

### 12.2 Configurare Ngrok

**Obține authtoken**: https://dashboard.ngrok.com/get-started/your-authtoken

```bash
# Set authtoken
ngrok config add-authtoken YOUR_AUTH_TOKEN_HERE
```

### 12.3 Start Ngrok Tunnel

**Comandă simplă**:

```bash
# Start tunnel to port 80
ngrok http 80

# Output:
# Forwarding  https://abc123.ngrok.app -> http://localhost:80
```

**Background mode**:

```bash
# Run in background
nohup ngrok http 80 > /var/log/ngrok.log 2>&1 &
```

### 12.4 Update Configurări cu URL Ngrok

După pornirea ngrok, vei primi un URL de forma: `https://abc123.ngrok.app`

**Actualizare necesară**:

1. **Backend `.env.prod.local`**:
   ```env
   DEFAULT_URI=https://abc123.ngrok.app
   CORS_ALLOW_ORIGIN='^https://abc123\.ngrok\.app$'
   FRONTEND_URL=https://abc123.ngrok.app
   ```

2. **Frontend `.env.production`**:
   ```env
   NEXT_PUBLIC_API_URL=https://abc123.ngrok.app
   NEXT_PUBLIC_SITE_URL=https://abc123.ngrok.app
   ```

3. **Rebuild și restart**:
   ```bash
   # Frontend
   cd /var/www/deschide_news_app/deschide_frontend
   NODE_ENV=production pnpm build
   pm2 restart deschide_frontend_prod

   # Backend
   cd /var/www/deschide_news_app/deschide_backend
   APP_ENV=prod symfony console cache:clear
   ```

### 12.5 Domeniu Rezervat (Plan Plătit)

Pentru plan plătit, poți rezerva domeniu fix:

1. Dashboard: https://dashboard.ngrok.com/cloud-edge/domains
2. Rezervă domeniu: ex. `deschide-news.ngrok.app`
3. Update config (`~/.config/ngrok/ngrok.yml`):

```yaml
version: "2"
authtoken: YOUR_AUTH_TOKEN

tunnels:
  deschide-prod:
    proto: http
    addr: 80
    hostname: deschide-news.ngrok.app
```

4. Start:
```bash
ngrok start deschide-prod
```

Astfel, URL-ul rămâne fix și nu trebuie să actualizezi env vars la fiecare restart.

---

## 📝 Note Finale

### Avantaje Abordare (Fără Ngrok)

✅ **Simplitate**: Mai puține componente, setup mai rapid
✅ **Separare clară**: DEV și PROD complet izolate
✅ **Fără Docker**: Mai simplu, rulare nativă pe WSL
✅ **Servicii partajate optimizate**: Un singur set de servicii, namespace separation
✅ **Monitoring complet**: Prometheus + Grafana pentru ambele medii
✅ **Easy debugging**: Logs separate, dashboards separate
✅ **Cost-effective**: Rulează tot pe WSL, fără costuri suplimentare
✅ **Acces LAN**: Testare pe alte device-uri în rețeaua locală

### Când să Folosești Ngrok (Optional)

🔹 **Demo pentru clienți externi**
🔹 **Testare pe mobile devices** (din alte rețele)
🔹 **Webhook testing** de la servicii externe (Stripe, PayPal, etc.)
🔹 **Remote access** pentru colaboratori

### Limitări

⚠️ **Resurse**: Rularea ambelor medii simultan consumă RAM/CPU
⚠️ **WSL performance**: Mai lent decât native Linux pentru unele operații
⚠️ **Single point of failure**: Toate serviciile pe un singur sistem
⚠️ **Acces public**: Necesită ngrok pentru expunere în internet

### Recomandări Production Reală

Pentru deployment real în producție (nu WSL), considerați:

1. **Separate servers**: Backend, Frontend, Database pe servere dedicate
2. **Load balancing**: Multiple instances behind load balancer
3. **CDN real**: Cloudflare, AWS CloudFront, etc.
4. **Managed services**: RDS pentru PostgreSQL, ElastiCache pentru Redis
5. **Container orchestration**: Kubernetes sau ECS dacă vrei Docker
6. **CI/CD pipeline**: GitLab CI, GitHub Actions pentru deploy automat
7. **SSL certificates**: Let's Encrypt pentru HTTPS
8. **Backup strategy**: Automated daily backups cu retention
9. **Disaster recovery plan**: Documented procedures pentru recovery

---

**Document creat**: 5 Noiembrie 2025
**Versiune**: 2.0 (Simplified - fără ngrok dependency)
**Autor**: Claude Code AI Assistant
**Status**: ✅ Ready for Implementation

**Next Steps**: Review → Confirm → Execute Checklist → Test → Deploy
