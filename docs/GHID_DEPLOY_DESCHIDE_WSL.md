# Ghid Complet de Deployment - Deschide News App pe WSL Ubuntu 24.04

**Versiune**: 1.0  
**Data**: Decembrie 2025  
**Target**: Mașină virtuală WSL Ubuntu 24.04  
**Aplicație**: Deschide News App (Symfony 7.3 + Next.js 16)

---

## 📋 Cuprins

1. [Introducere și Arhitectură](#1-introducere-și-arhitectură)
2. [Pregătirea Mediului WSL](#2-pregătirea-mediului-wsl)
3. [Instalarea și Configurarea Serviciilor](#3-instalarea-și-configurarea-serviciilor)
4. [Setup Backend Symfony](#4-setup-backend-symfony)
5. [Setup Frontend Next.js](#5-setup-frontend-nextjs)
6. [Configurare Nginx și SSL](#6-configurare-nginx-și-ssl)
7. [Deployment Workflow](#7-deployment-workflow)
8. [Automatizări și Cron Jobs](#8-automatizări-și-cron-jobs)
9. [Backup și Recovery](#9-backup-și-recovery)
10. [Monitoring și Maintenance](#10-monitoring-și-maintenance)
11. [Troubleshooting](#11-troubleshooting)

---

## 1. Introducere și Arhitectură

### 1.1 Overview Aplicație

**Deschide News** este o platformă media multilinguală (română, engleză, rusă) cu arhitectură modernă headless:

- **Backend**: Symfony 7.3 (PHP 8.4) + API Platform
- **Frontend**: Next.js 16 (App Router) + React 19.2
- **Database**: PostgreSQL 17
- **Cache**: Redis 8.0
- **Search**: Elasticsearch 8.x
- **Queue**: RabbitMQ 3.12+ (optional)
- **Real-time**: Mercure Hub
- **CDN**: Static assets server

### 1.2 Topologia Sistemului

```
┌────────────────────────────────────────────────────────┐
│                     WINDOWS HOST                       │
│                  \\wsl.localhost\                       │
└──────────────────────┬─────────────────────────────────┘
                       │
┌──────────────────────▼─────────────────────────────────┐
│              WSL Ubuntu 24.04                          │
│                                                        │
│  ┌──────────────────────────────────────────────┐    │
│  │  Nginx (Gateway & SSL Termination)           │    │
│  │  - Port 80/443 (HTTP/HTTPS)                  │    │
│  │  - Reverse Proxy                             │    │
│  └────┬──────────────────────────┬────────────┘      │
│       │                          │                    │
│  ┌────▼───────────┐         ┌────▼────────────┐      │
│  │  Next.js       │         │  Symfony API    │      │
│  │  Port: 3005    │         │  Port: 8081     │      │
│  │  (PM2 Cluster) │         │  (PHP-FPM 8.4)  │      │
│  └────────────────┘         └────┬────────────┘      │
│                                   │                    │
│  ┌──────────────┬──────────────┬──┴───┬────────┐     │
│  │ PostgreSQL   │   Redis      │  ES  │ RabbitMQ│     │
│  │ Port: 5432   │   Port: 6379 │ 9200 │  5672   │     │
│  └──────────────┴──────────────┴──────┴─────────┘     │
│                                                        │
│  ┌──────────────┐         ┌─────────────┐            │
│  │ Mercure Hub  │         │  CDN Server │            │
│  │ Port: 3000   │         │  Port: 8082 │            │
│  └──────────────┘         └─────────────┘            │
└────────────────────────────────────────────────────────┘
```

### 1.3 Porturi și Servicii

| Serviciu | Port | Access | URL |
|----------|------|--------|-----|
| **Nginx HTTP** | 80 | Public | http://deschide.local |
| **Nginx HTTPS** | 443 | Public | https://deschide.local |
| **Frontend (Next.js)** | 3005 | Internal | http://localhost:3005 |
| **Backend API** | 8081 | Internal | http://127.0.0.1:8081 |
| **CDN Static** | 8082 | Internal | http://127.0.0.1:8082 |
| **Mercure Hub** | 3000 | Internal | http://localhost:3000 |
| **PostgreSQL** | 5432 | Internal | localhost:5432 |
| **Redis** | 6379 | Internal | localhost:6379 |
| **Elasticsearch** | 9200 | Internal | localhost:9200 |
| **RabbitMQ** | 5672 | Internal | localhost:5672 |
| **RabbitMQ Admin** | 15672 | Internal | http://localhost:15672 |

---

## 2. Pregătirea Mediului WSL

### 2.1 Verificare și Update WSL

```bash
# Verifică versiunea WSL (din PowerShell Windows)
wsl --version

# Update WSL la ultima versiune
wsl --update

# Verifică distribuțiile instalate
wsl --list --verbose

# Setează Ubuntu 24.04 ca default (dacă ai multiple distros)
wsl --set-default Ubuntu-24.04
```

### 2.2 Configurare Inițială Ubuntu

```bash
# Update și upgrade sistem
sudo apt update && sudo apt upgrade -y

# Instalează tool-uri esențiale
sudo apt install -y \
    build-essential \
    software-properties-common \
    apt-transport-https \
    ca-certificates \
    curl \
    wget \
    git \
    vim \
    nano \
    unzip \
    zip \
    htop \
    net-tools \
    dnsutils \
    iputils-ping \
    lsb-release \
    gnupg2
```

### 2.3 Configurare Locale-uri pentru Multilingvism

**IMPORTANT**: Aplicația suportă 3 limbi (ro, en, ru). Trebuie instalate locale-urile corespunzătoare.

```bash
# Verifică locale-urile disponibile
locale -a

# Instalează pachetul de locale-uri
sudo apt install -y locales

# Generează locale-urile necesare pentru aplicație
sudo locale-gen en_US.UTF-8
sudo locale-gen ro_RO.UTF-8
sudo locale-gen ru_RU.UTF-8

# Setează default locale
sudo update-locale LANG=en_US.UTF-8 LC_ALL=en_US.UTF-8

# Verifică instalarea
locale -a | grep -E 'en_US|ro_RO|ru_RU'

# Trebuie să afișeze:
# en_US.utf8
# ro_RO.utf8
# ru_RU.utf8

# Aplică schimbările
source /etc/default/locale
```

**Verificare finală locale-uri:**

```bash
# Trebuie să returneze UTF-8 pentru toate
locale

# LANG=en_US.UTF-8
# LC_CTYPE="en_US.UTF-8"
# LC_NUMERIC=en_US.UTF-8
# LC_TIME=en_US.UTF-8
# LC_COLLATE="en_US.UTF-8"
# LC_MONETARY=en_US.UTF-8
# LC_MESSAGES="en_US.UTF-8"
# LC_PAPER=en_US.UTF-8
# LC_NAME=en_US.UTF-8
# LC_ADDRESS=en_US.UTF-8
# LC_TELEPHONE=en_US.UTF-8
# LC_MEASUREMENT=en_US.UTF-8
# LC_IDENTIFICATION=en_US.UTF-8
# LC_ALL=en_US.UTF-8
```

---

### 2.4 Optimizare Kernel și Rețea

Creează fișierul `/etc/sysctl.d/99-deschide-news.conf`:

```bash
sudo nano /etc/sysctl.d/99-deschide-news.conf
```

Adaugă următoarele optimizări:

```ini
# Network Performance Tuning pentru Deschide News App
# Optimizat pentru comunicare internă intensă (Nginx ↔ Node ↔ PHP)

# TCP Connection Queue
net.core.somaxconn = 65535
net.core.netdev_max_backlog = 65535

# TCP Connection Reuse (critical pentru proxy intern)
net.ipv4.tcp_tw_reuse = 1
net.ipv4.tcp_fin_timeout = 30

# File Descriptors (pentru conexiuni multiple)
fs.file-max = 2097152
fs.inotify.max_user_watches = 524288
fs.inotify.max_user_instances = 512

# Memory Management
vm.swappiness = 10
vm.dirty_ratio = 15
vm.dirty_background_ratio = 5

# Shared Memory (pentru PostgreSQL și Redis)
kernel.shmmax = 17179869184
kernel.shmall = 4194304

# Semaphores (pentru PostgreSQL)
kernel.sem = 250 32000 100 128
```

Aplică configurația:

```bash
sudo sysctl -p /etc/sysctl.d/99-deschide-news.conf

# Verifică valorile aplicate
sudo sysctl net.core.somaxconn
sudo sysctl vm.swappiness
```

### 2.5 Configurare Limită Fișiere

Editează `/etc/security/limits.conf`:

```bash
sudo nano /etc/security/limits.conf
```

Adaugă la sfârșit:

```
# Limits pentru Deschide News App
*               soft    nofile          65535
*               hard    nofile          65535
*               soft    nproc           32768
*               hard    nproc           32768
```

### 2.6 Crearea Structurii de Directoare

```bash
# Directorul principal al aplicației
sudo mkdir -p /var/www/deschide_news_app

# Directoare pentru logs
sudo mkdir -p /var/log/deschide/{nginx,php,symfony,nextjs,cron}

# Directoare pentru backups
sudo mkdir -p /var/backups/deschide/{database,uploads,configs}

# Directoare pentru cache
sudo mkdir -p /var/cache/deschide/{symfony,nextjs}

# Directoare pentru uploads
sudo mkdir -p /var/www/deschide_news_app/uploads/{images,documents}

# Directoare pentru SSL certificates (local)
sudo mkdir -p /etc/ssl/deschide

# Setează ownership și permissions
sudo chown -R $USER:$USER /var/www/deschide_news_app
sudo chown -R $USER:$USER /var/log/deschide
sudo chown -R $USER:$USER /var/backups/deschide

# Permissions specifice pentru uploads
sudo chmod 755 /var/www/deschide_news_app/uploads
sudo chmod 775 /var/www/deschide_news_app/uploads/images
```

---

## 3. Instalarea și Configurarea Serviciilor

### 3.1 PostgreSQL 17

#### 3.1.1 Instalare

```bash
# Adaugă repository oficial PostgreSQL
sudo sh -c 'echo "deb http://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list'
wget --quiet -O - https://www.postgresql.org/media/keys/ACCC4CF8.asc | sudo apt-key add -

# Update și instalare
sudo apt update
sudo apt install -y postgresql-17 postgresql-contrib-17 postgresql-client-17

# Verifică instalarea
psql --version
# Trebuie să afișeze: psql (PostgreSQL) 17.x
```

#### 3.1.2 Configurare Performanță

Editează `/etc/postgresql/17/main/postgresql.conf`:

```bash
sudo nano /etc/postgresql/17/main/postgresql.conf
```

**Configurații critice** (ajustează în funcție de RAM disponibil):

```ini
# Connections
max_connections = 200
superuser_reserved_connections = 5

# Memory Configuration (pentru sistem cu 16GB RAM)
shared_buffers = 4GB                    # 25% din RAM
effective_cache_size = 12GB             # 75% din RAM
work_mem = 16MB                         # Per operation
maintenance_work_mem = 512MB            # Pentru VACUUM și reindex
effective_io_concurrency = 200          # Pentru SSD

# WAL Configuration
wal_buffers = 16MB
min_wal_size = 1GB
max_wal_size = 4GB
checkpoint_completion_target = 0.9

# Query Planner
random_page_cost = 1.1                  # Pentru SSD
default_statistics_target = 100

# Logging (pentru development/debugging)
logging_collector = on
log_directory = '/var/log/postgresql'
log_filename = 'postgresql-%Y-%m-%d_%H%M%S.log'
log_rotation_age = 1d
log_rotation_size = 100MB
log_min_duration_statement = 1000       # Log queries > 1s
log_line_prefix = '%t [%p]: user=%u,db=%d,app=%a,client=%h '
log_checkpoints = on
log_connections = on
log_disconnections = on
log_lock_waits = on

# Autovacuum (critical pentru performanță long-term)
autovacuum = on
autovacuum_max_workers = 3
autovacuum_naptime = 30s
autovacuum_vacuum_threshold = 50
autovacuum_analyze_threshold = 50
autovacuum_vacuum_scale_factor = 0.02
autovacuum_analyze_scale_factor = 0.01

# Locale și encoding
lc_messages = 'en_US.UTF-8'
lc_monetary = 'ro_RO.UTF-8'
lc_numeric = 'ro_RO.UTF-8'
lc_time = 'ro_RO.UTF-8'
default_text_search_config = 'pg_catalog.english'
```

Editează `/etc/postgresql/17/main/pg_hba.conf` pentru acces local:

```bash
sudo nano /etc/postgresql/17/main/pg_hba.conf
```

Asigură-te că există:

```
# IPv4 local connections:
host    all             all             127.0.0.1/32            scram-sha-256
host    all             all             ::1/128                 scram-sha-256

# Unix socket local connections:
local   all             all                                     peer
```

#### 3.1.3 Creare Database și Utilizator

```bash
# Restart PostgreSQL pentru a aplica configurările
sudo systemctl restart postgresql
sudo systemctl enable postgresql

# Verifică status
sudo systemctl status postgresql

# Conectează-te ca utilizator postgres
sudo -u postgres psql

# În consola PostgreSQL, execută:
```

```sql
-- Creează utilizatorul aplicației
CREATE USER deschide_admin WITH PASSWORD 'your_strong_password_here';

-- Creează database-ul cu encoding corect pentru multilingvism
CREATE DATABASE deschide_news
    WITH OWNER = deschide_admin
    ENCODING = 'UTF8'
    LC_COLLATE = 'en_US.UTF-8'
    LC_CTYPE = 'en_US.UTF-8'
    TEMPLATE = template0;

-- Grant permissions
GRANT ALL PRIVILEGES ON DATABASE deschide_news TO deschide_admin;

-- Conectează-te la database
\c deschide_news

-- Grant permissions pe schema public
GRANT ALL ON SCHEMA public TO deschide_admin;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO deschide_admin;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO deschide_admin;

-- Verifică
\l
\q
```

#### 3.1.4 Testare Conexiune

```bash
# Test conexiune cu user aplicației
psql -h localhost -U deschide_admin -d deschide_news

# În psql:
SELECT version();
\dt
\q
```

### 3.2 Redis 8.0

#### 3.2.1 Instalare

```bash
# Adaugă repository oficial Redis
curl -fsSL https://packages.redis.io/gpg | sudo gpg --dearmor -o /usr/share/keyrings/redis-archive-keyring.gpg
echo "deb [signed-by=/usr/share/keyrings/redis-archive-keyring.gpg] https://packages.redis.io/deb $(lsb_release -cs) main" | sudo tee /etc/apt/sources.list.d/redis.list

# Instalează Redis
sudo apt update
sudo apt install -y redis-server redis-tools

# Verifică versiunea
redis-server --version
```

#### 3.2.2 Configurare

Editează `/etc/redis/redis.conf`:

```bash
sudo nano /etc/redis/redis.conf
```

**Configurații pentru Deschide News**:

```ini
# Bind only local
bind 127.0.0.1 ::1
protected-mode yes

# Port
port 6379

# Daemonize
daemonize yes
supervised systemd
pidfile /var/run/redis/redis-server.pid

# Logging
loglevel notice
logfile /var/log/redis/redis-server.log

# Database
databases 16

# Persistence (pentru cache, disable; pentru queues, enable)
# CACHE DATABASE (DB 0): No persistence
save ""
stop-writes-on-bgsave-error no
rdbcompression yes
rdbchecksum yes
dbfilename dump.rdb
dir /var/lib/redis

# AOF Persistence (disable pentru cache pur)
appendonly no

# Memory Management
maxmemory 2gb                           # Ajustează în funcție de RAM
maxmemory-policy allkeys-lru            # LRU eviction pentru cache
maxmemory-samples 5

# Lazy Freeing (pentru performanță)
lazyfree-lazy-eviction yes
lazyfree-lazy-expire yes
lazyfree-lazy-server-del yes
replica-lazy-flush yes

# Performance
tcp-backlog 511
timeout 300
tcp-keepalive 300

# Snapshotting (doar pentru DB 1 - sessions/queues)
# Dacă folosești Redis pentru sessions și queues, comentează "save """" de mai sus și adaugă:
# save 900 1
# save 300 10
# save 60 10000
```

**Creează configurație separată pentru Queue DB** (dacă folosești RabbitMQ, acest pas e opțional):

```bash
sudo nano /etc/redis/redis-queue.conf
```

```ini
# Include config principal
include /etc/redis/redis.conf

# Override settings pentru persistence
save 900 1
save 300 10
save 60 10000
appendonly yes
appendfilename "appendonly.aof"
dir /var/lib/redis-queue
port 6380
pidfile /var/run/redis/redis-queue.pid
logfile /var/log/redis/redis-queue.log
```

#### 3.2.3 Start și Enable

```bash
# Start Redis cache (DB 0-15)
sudo systemctl start redis-server
sudo systemctl enable redis-server

# Verifică status
sudo systemctl status redis-server

# Test conexiune
redis-cli ping
# Trebuie să returneze: PONG

# Test basic operations
redis-cli
> SET test "Hello Deschide"
> GET test
> DEL test
> SELECT 1
> INFO
> EXIT
```

### 3.3 Elasticsearch 8.x

#### 3.3.1 Instalare

```bash
# Import Elasticsearch GPG Key
wget -qO - https://artifacts.elastic.co/GPG-KEY-elasticsearch | sudo gpg --dearmor -o /usr/share/keyrings/elasticsearch-keyring.gpg

# Adaugă repository
echo "deb [signed-by=/usr/share/keyrings/elasticsearch-keyring.gpg] https://artifacts.elastic.co/packages/8.x/apt stable main" | sudo tee /etc/apt/sources.list.d/elastic-8.x.list

# Instalează Elasticsearch
sudo apt update
sudo apt install -y elasticsearch

# Salvează parola generată automat (va fi afișată la instalare)
# IMPORTANT: Notează această parolă!
```

#### 3.3.2 Configurare

Editează `/etc/elasticsearch/elasticsearch.yml`:

```bash
sudo nano /etc/elasticsearch/elasticsearch.yml
```

```yaml
# Cluster & Node
cluster.name: deschide-news-cluster
node.name: deschide-node-1
node.roles: [ master, data, ingest ]

# Paths
path.data: /var/lib/elasticsearch
path.logs: /var/log/elasticsearch

# Network
network.host: 127.0.0.1
http.port: 9200

# Discovery (single node)
discovery.type: single-node

# Memory
bootstrap.memory_lock: true

# Security (pentru development local)
xpack.security.enabled: true
xpack.security.transport.ssl.enabled: false
xpack.security.http.ssl.enabled: false

# Indices
action.auto_create_index: +deschide_*,-*
```

Setează memory lock în `/etc/elasticsearch/jvm.options.d/heap.options`:

```bash
sudo nano /etc/elasticsearch/jvm.options.d/heap.options
```

```
# Heap size (setează la 50% din RAM disponibil pentru ES, max 32GB)
-Xms2g
-Xmx2g
```

Configurează systemd pentru memory lock:

```bash
sudo mkdir -p /etc/systemd/system/elasticsearch.service.d/
sudo nano /etc/systemd/system/elasticsearch.service.d/override.conf
```

```ini
[Service]
LimitMEMLOCK=infinity
```

#### 3.3.3 Start și Configurare Inițială

```bash
# Reload systemd
sudo systemctl daemon-reload

# Start Elasticsearch
sudo systemctl start elasticsearch
sudo systemctl enable elasticsearch

# Așteaptă 30 secunde pentru pornire completă
sleep 30

# Verifică status
sudo systemctl status elasticsearch

# Reset password pentru user elastic (dacă e nevoie)
sudo /usr/share/elasticsearch/bin/elasticsearch-reset-password -u elastic -i

# Testează conexiunea (folosește -k pentru a ignora SSL self-signed cert)
curl -k -u elastic:your_password -X GET "https://localhost:9200"

# Trebuie să returneze JSON cu cluster info
```

#### 3.3.4 Creare Utilizator pentru Aplicație

```bash
# Creează role pentru aplicație
curl -k -u elastic:your_password -X POST "https://localhost:9200/_security/role/deschide_role" -H 'Content-Type: application/json' -d'
{
  "cluster": ["monitor"],
  "indices": [
    {
      "names": ["deschide_*"],
      "privileges": ["all"]
    }
  ]
}'

# Creează utilizator
curl -k -u elastic:your_password -X POST "https://localhost:9200/_security/user/deschide_user" -H 'Content-Type: application/json' -d'
{
  "password": "your_deschide_es_password",
  "roles": ["deschide_role"],
  "full_name": "Deschide Application User"
}'
```

### 3.4 RabbitMQ (Optional, pentru Message Queue)

#### 3.4.1 Instalare

```bash
# Instalează Erlang (dependency pentru RabbitMQ)
curl -fsSL https://packages.erlang-solutions.com/ubuntu/erlang_solutions.asc | sudo gpg --dearmor -o /usr/share/keyrings/erlang-archive-keyring.gpg
echo "deb [signed-by=/usr/share/keyrings/erlang-archive-keyring.gpg] https://packages.erlang-solutions.com/ubuntu $(lsb_release -cs) contrib" | sudo tee /etc/apt/sources.list.d/erlang.list

# Instalează RabbitMQ
curl -fsSL https://github.com/rabbitmq/signing-keys/releases/download/3.0/rabbitmq-release-signing-key.asc | sudo gpg --dearmor -o /usr/share/keyrings/rabbitmq-archive-keyring.gpg
echo "deb [signed-by=/usr/share/keyrings/rabbitmq-archive-keyring.gpg] https://packagecloud.io/rabbitmq/rabbitmq-server/ubuntu/ $(lsb_release -cs) main" | sudo tee /etc/apt/sources.list.d/rabbitmq.list

sudo apt update
sudo apt install -y erlang rabbitmq-server

# Start și enable
sudo systemctl start rabbitmq-server
sudo systemctl enable rabbitmq-server

# Enable management plugin
sudo rabbitmq-plugins enable rabbitmq_management

# Verifică status
sudo systemctl status rabbitmq-server
```

#### 3.4.2 Configurare

```bash
# Creează admin user
sudo rabbitmqctl add_user deschide_admin your_rabbitmq_password
sudo rabbitmqctl set_user_tags deschide_admin administrator
sudo rabbitmqctl set_permissions -p / deschide_admin ".*" ".*" ".*"

# Creare vhost pentru aplicație
sudo rabbitmqctl add_vhost deschide_news
sudo rabbitmqctl set_permissions -p deschide_news deschide_admin ".*" ".*" ".*"

# Verifică
sudo rabbitmqctl list_users
sudo rabbitmqctl list_vhosts
```

Accesează Management UI: `http://localhost:15672`
- Username: `deschide_admin`
- Password: `your_rabbitmq_password`

### 3.5 CDN Server pentru Static Assets (Port 8082)

CDN-ul servește imaginile și fișierele statice uploadate, separate de aplicația principală pentru performanță optimă.

#### 3.5.1 Configurare Nginx pentru CDN

Creează `/etc/nginx/sites-available/cdn.deschide.local`:

```bash
sudo nano /etc/nginx/sites-available/cdn.deschide.local
```

```nginx
# CDN Server pentru Deschide News - Static Assets
# Port: 8082 (internal)

server {
    listen 8082;
    listen [::]:8082;
    server_name 127.0.0.1 localhost;

    # Root directory pentru uploads
    root /var/www/deschide_news_app/apps/backend/public;

    # Logging
    access_log /var/log/deschide/nginx/cdn-access.log;
    error_log /var/log/deschide/nginx/cdn-error.log warn;

    # Security Headers
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Access-Control-Allow-Origin "*" always;
    add_header Cache-Control "public, max-age=31536000, immutable";

    # Gzip pentru imagini text-based (SVG)
    gzip on;
    gzip_types image/svg+xml application/json;

    # ============================================
    # Uploads - Images și Thumbnails
    # ============================================
    location /uploads {
        alias /var/www/deschide_news_app/apps/backend/public/uploads;

        # Cache headers pentru assets statice
        expires 365d;
        add_header Cache-Control "public, max-age=31536000, immutable";

        # Disable access logs pentru performance
        access_log off;

        # MIME types pentru imagini
        types {
            image/webp webp;
            image/avif avif;
            image/jpeg jpg jpeg;
            image/png png;
            image/gif gif;
            image/svg+xml svg svgz;
        }

        # Security: Disable PHP execution
        location ~ \.php$ {
            deny all;
            return 403;
        }

        # Security: Deny hidden files
        location ~ /\. {
            deny all;
            return 403;
        }
    }

    # ============================================
    # Images subfolder
    # ============================================
    location /uploads/images {
        alias /var/www/deschide_news_app/apps/backend/public/uploads/images;
        expires 365d;
        add_header Cache-Control "public, max-age=31536000, immutable";
        access_log off;

        # Try file, fallback to 404
        try_files $uri =404;
    }

    # ============================================
    # Thumbnails subfolder (WebP optimized)
    # ============================================
    location /uploads/thumbnails {
        alias /var/www/deschide_news_app/apps/backend/public/uploads/thumbnails;
        expires 365d;
        add_header Cache-Control "public, max-age=31536000, immutable";
        access_log off;

        try_files $uri =404;
    }

    # ============================================
    # Health Check
    # ============================================
    location /health {
        access_log off;
        return 200 "CDN OK\n";
        add_header Content-Type text/plain;
    }

    # ============================================
    # Deny everything else
    # ============================================
    location / {
        return 403;
    }
}
```

#### 3.5.2 Enable CDN Site

```bash
# Enable site
sudo ln -s /etc/nginx/sites-available/cdn.deschide.local /etc/nginx/sites-enabled/

# Test configurație
sudo nginx -t

# Reload Nginx
sudo systemctl reload nginx

# Test CDN
curl -I http://127.0.0.1:8082/health
# Trebuie să returneze: HTTP/1.1 200 OK

# Test imagine (dacă există)
curl -I http://127.0.0.1:8082/uploads/images/test.jpg
```

#### 3.5.3 Setare Permisiuni Uploads

```bash
# Creează directoarele pentru uploads
sudo mkdir -p /var/www/deschide_news_app/apps/backend/public/uploads/{images,thumbnails}

# Setează ownership pentru www-data (PHP-FPM)
sudo chown -R www-data:www-data /var/www/deschide_news_app/apps/backend/public/uploads

# Setează permissions
sudo chmod -R 755 /var/www/deschide_news_app/apps/backend/public/uploads
sudo chmod -R 775 /var/www/deschide_news_app/apps/backend/public/uploads/images
sudo chmod -R 775 /var/www/deschide_news_app/apps/backend/public/uploads/thumbnails

# Verifică
ls -la /var/www/deschide_news_app/apps/backend/public/uploads/
```

---

### 3.6 Mercure Hub (Real-time Push)

#### 3.6.1 Instalare

```bash
# Download latest Mercure binary
cd /tmp
wget https://github.com/dunglas/mercure/releases/latest/download/mercure_Linux_x86_64.tar.gz
tar -xzf mercure_Linux_x86_64.tar.gz

# Move binary
sudo mv mercure /usr/local/bin/
sudo chmod +x /usr/local/bin/mercure

# Verifică
mercure version
```

#### 3.6.2 Configurare

Creează fișier de configurare `/etc/mercure/Caddyfile`:

```bash
sudo mkdir -p /etc/mercure
sudo nano /etc/mercure/Caddyfile
```

```caddyfile
{
    # Global options
    auto_https off
    admin off
}

:3000 {
    route {
        encode gzip

        mercure {
            # Publisher JWT key (trebuie să fie același cu JWT_SECRET din Symfony)
            publisher_jwt !ChangeThisMercureHubJWTSecretKey!
            
            # Subscriber JWT key
            subscriber_jwt !ChangeThisMercureHubJWTSecretKey!

            # Allow anonymous subscribers (pentru development)
            anonymous

            # CORS
            cors_origins http://localhost:3005 https://deschide.local
            
            # Subscriptions (limite)
            subscriptions
        }

        respond /healthz 200
        respond "Not Found" 404
    }
}
```

#### 3.6.3 Creare Systemd Service

```bash
sudo nano /etc/systemd/system/mercure.service
```

```ini
[Unit]
Description=Mercure Hub
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
ExecStart=/usr/local/bin/mercure run --config /etc/mercure/Caddyfile
Restart=on-failure
RestartSec=5s
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

```bash
# Start și enable
sudo systemctl daemon-reload
sudo systemctl start mercure
sudo systemctl enable mercure

# Verifică status
sudo systemctl status mercure

# Test
curl http://localhost:3000/.well-known/mercure
```

---

## 4. Setup Backend Symfony

### 4.1 Instalare PHP 8.4 și Extensii

```bash
# Adaugă PPA Ondřej Surý (pentru versiuni recente PHP)
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Instalează PHP 8.4 și extensii necesare
sudo apt install -y \
    php8.4-fpm \
    php8.4-cli \
    php8.4-common \
    php8.4-pgsql \
    php8.4-redis \
    php8.4-mbstring \
    php8.4-xml \
    php8.4-curl \
    php8.4-zip \
    php8.4-intl \
    php8.4-gd \
    php8.4-bcmath \
    php8.4-opcache \
    php8.4-apcu \
    php8.4-imagick \
    php8.4-amqp              # Pentru RabbitMQ (Symfony Messenger)

# Verifică versiunea
php -v
# Trebuie: PHP 8.4.x

# Verifică extensiile instalate
php -m | grep -E 'amqp|redis|pgsql|imagick'
```

### 4.2 Configurare PHP-FPM 8.4

#### 4.2.1 Configurare Pool FPM

Editează `/etc/php/8.4/fpm/pool.d/www.conf`:

```bash
sudo nano /etc/php/8.4/fpm/pool.d/www.conf
```

**Configurații critice**:

```ini
[www]
user = www-data
group = www-data

# Socket Unix (mai rapid decât TCP)
listen = /run/php/php8.4-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

# Process Manager
pm = dynamic
pm.max_children = 50                    # Max procese PHP
pm.start_servers = 10                   # Procese la start
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500                   # Restart după X requests (memory leak prevention)
pm.process_idle_timeout = 10s

# Performance
pm.status_path = /fpm-status
ping.path = /fpm-ping

# Logging
php_admin_value[error_log] = /var/log/php/fpm-error.log
php_admin_flag[log_errors] = on
php_value[session.save_handler] = redis
php_value[session.save_path] = "tcp://127.0.0.1:6379?database=1&prefix=deschide_session:"

# Security
php_admin_value[open_basedir] = /var/www/deschide_news_app:/tmp:/var/tmp:/dev/urandom
php_admin_value[upload_max_filesize] = 20M
php_admin_value[post_max_size] = 25M
php_admin_value[memory_limit] = 256M
php_admin_value[max_execution_time] = 60
```

#### 4.2.2 Configurare php.ini (Production)

Editează `/etc/php/8.4/fpm/php.ini`:

```bash
sudo nano /etc/php/8.4/fpm/php.ini
```

**Secțiuni importante**:

```ini
[PHP]
engine = On
short_open_tag = Off
precision = 14
output_buffering = 4096
zlib.output_compression = Off
implicit_flush = Off
serialize_precision = -1
disable_functions = pcntl_alarm,pcntl_fork,pcntl_waitpid,pcntl_wait,pcntl_wifexited,pcntl_wifstopped,pcntl_wifsignaled,pcntl_wifcontinued,pcntl_wexitstatus,pcntl_wtermsig,pcntl_wstopsig,pcntl_signal,pcntl_signal_get_handler,pcntl_signal_dispatch,pcntl_get_last_error,pcntl_strerror,pcntl_sigprocmask,pcntl_sigwaitinfo,pcntl_sigtimedwait,pcntl_exec,pcntl_getpriority,pcntl_setpriority,pcntl_async_signals,system,exec,shell_exec,passthru
max_execution_time = 60
max_input_time = 60
memory_limit = 256M
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /var/log/php/error.log
ignore_repeated_errors = Off
report_memleaks = On
variables_order = "GPCS"
request_order = "GP"
register_argc_argv = Off
auto_globals_jit = On
post_max_size = 25M
default_mimetype = "text/html"
default_charset = "UTF-8"
upload_max_filesize = 20M
max_file_uploads = 20
allow_url_fopen = On
allow_url_include = Off

[Date]
date.timezone = Europe/Chisinau

[Session]
session.save_handler = redis
session.save_path = "tcp://127.0.0.1:6379?database=1&prefix=deschide_session:"
session.use_strict_mode = 1
session.use_cookies = 1
session.use_only_cookies = 1
session.name = DESCHIDE_SESSID
session.cookie_secure = 1
session.cookie_httponly = 1
session.cookie_samesite = Lax
session.gc_maxlifetime = 86400

[opcache]
opcache.enable = 1
opcache.enable_cli = 0
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 64
opcache.max_accelerated_files = 30000
opcache.validate_timestamps = 0          # CRITICAL: Set to 0 in production!
opcache.revalidate_freq = 0
opcache.save_comments = 1
opcache.fast_shutdown = 1
opcache.enable_file_override = 1
opcache.preload = /var/www/deschide_news_app/apps/backend/config/preload.php
opcache.preload_user = www-data

; JIT Configuration (PHP 8.4)
opcache.jit_buffer_size = 128M
opcache.jit = tracing                    # Mode: tracing (1254)

[APCu]
apc.enabled = 1
apc.shm_size = 128M
apc.ttl = 7200
apc.enable_cli = 0
```

**IMPORTANT**: Pentru OPcache preload, creează fișierul:

```bash
# Va fi creat automat de Symfony, sau poți crea manual
# sudo nano /var/www/deschide_news_app/apps/backend/config/preload.php
```

Conținut:

```php
<?php
// config/preload.php

if (file_exists(__DIR__.'/../var/cache/prod/App_KernelProdContainer.preload.php')) {
    require __DIR__.'/../var/cache/prod/App_KernelProdContainer.preload.php';
}
```

#### 4.2.3 Start și Enable PHP-FPM

```bash
# Creează directorul pentru loguri PHP
sudo mkdir -p /var/log/php
sudo chown www-data:www-data /var/log/php

# Test configurație
sudo php-fpm8.4 -t

# Restart și enable
sudo systemctl restart php8.4-fpm
sudo systemctl enable php8.4-fpm

# Verifică status
sudo systemctl status php8.4-fpm

# Verifică socket
ls -lah /run/php/php8.4-fpm.sock
```

### 4.3 Instalare Composer

```bash
# Download și instalare Composer 2
cd /tmp
curl -sS https://getcomposer.org/installer -o composer-setup.php
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php

# Verifică versiunea
composer --version
# Trebuie: Composer version 2.x
```

### 4.4 Instalare Symfony CLI

```bash
# Instalare Symfony CLI
curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | sudo -E bash
sudo apt install symfony-cli

# Verifică
symfony version

# Check system requirements
symfony check:requirements
```

### 4.5 Clone și Setup Backend

```bash
# Navigate to app directory
cd /var/www/deschide_news_app

# Clone backend (dacă nu e deja prezent)
# git clone <backend-repo-url> apps/backend

# Sau sincronizează din Windows
# cp -r /mnt/c/path/to/backend apps/backend

# Navigate to backend
cd apps/backend

# Install dependencies (production mode)
composer install --no-dev --optimize-autoloader --classmap-authoritative

# Sau pentru development
# composer install

# Verifică structura
ls -la
```

### 4.6 Configurare Environment (.env.local)

```bash
cd /var/www/deschide_news_app/apps/backend
nano .env.local
```

**Configurație Production**:

```bash
###> symfony/framework-bundle ###
APP_ENV=prod
APP_SECRET=YOUR_SECURE_RANDOM_SECRET_HERE_MINIMUM_32_CHARS
APP_DEBUG=0
###< symfony/framework-bundle ###

###> doctrine/doctrine-bundle ###
DATABASE_URL="postgresql://deschide_admin:your_password@127.0.0.1:5432/deschide_news?serverVersion=17&charset=utf8"
###< doctrine/doctrine-bundle ###

###> lexik/jwt-authentication-bundle ###
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your_jwt_passphrase_here
JWT_TTL=3600
###< lexik/jwt-authentication-bundle ###

###> gesdinet/jwt-refresh-token-bundle ###
JWT_REFRESH_TOKEN_TTL=2592000
###< gesdinet/jwt-refresh-token-bundle ###

###> nelmio/cors-bundle ###
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1|deschide\.local)(:[0-9]+)?$'
###< nelmio/cors-bundle ###

###> symfony/messenger ###
# Pentru Doctrine DBAL
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0

# Sau pentru RabbitMQ
# MESSENGER_TRANSPORT_DSN=amqp://deschide_admin:your_password@localhost:5672/deschide_news/messages
###< symfony/messenger ###

###> elasticsearch ###
# IMPORTANT: Folosește https pentru Elasticsearch 8.x cu security enabled
ELASTICSEARCH_HOST=https://localhost:9200
ELASTICSEARCH_USER=deschide_user
ELASTICSEARCH_PASSWORD=your_deschide_es_password
ELASTICSEARCH_INDEX_PREFIX=deschide_
# Pentru a ignora SSL verification în development (self-signed cert)
ELASTICSEARCH_SSL_VERIFY=false
###< elasticsearch ###

###> redis ###
REDIS_URL=redis://localhost:6379/0
REDIS_CACHE_DSN=redis://localhost:6379/0?prefix=deschide_cache:
REDIS_SESSION_DSN=redis://localhost:6379/1?prefix=deschide_session:
###< redis ###

###> mercure ###
MERCURE_URL=http://localhost:3000/.well-known/mercure
MERCURE_PUBLIC_URL=https://deschide.local/.well-known/mercure
MERCURE_JWT_SECRET=!ChangeThisMercureHubJWTSecretKey!
###< mercure ###

###> cdn/uploads ###
CDN_BASE_URL=http://127.0.0.1:8082
UPLOADS_PATH=/var/www/deschide_news_app/uploads
###< cdn/uploads ###

###> mailer (optional) ###
# MAILER_DSN=smtp://localhost:1025
###< mailer ###

###> sentry (optional - pentru error tracking) ###
# SENTRY_DSN=https://...
###< sentry ###
```

### 4.7 Generate JWT Keys

```bash
cd /var/www/deschide_news_app/apps/backend

# Generate JWT keypair
symfony console lexik:jwt:generate-keypair

# Verifică keys
ls -la config/jwt/
# Trebuie să existe: private.pem și public.pem

# Set correct permissions
chmod 600 config/jwt/private.pem
chmod 644 config/jwt/public.pem
```

### 4.8 Database Setup

```bash
cd /var/www/deschide_news_app/apps/backend

# Verifică conexiunea
symfony console dbal:run-sql "SELECT version()"

# Creează schema (dacă nu există)
# symfony console doctrine:database:create

# Run migrations
symfony console doctrine:migrations:migrate --no-interaction

# Verifică tabele
symfony console dbal:run-sql "SELECT tablename FROM pg_tables WHERE schemaname = 'public'"

# (Optional) Load fixtures pentru development
# symfony console doctrine:fixtures:load --no-interaction
```

### 4.9 Cache Warmup și Optimizare

```bash
cd /var/www/deschide_news_app/apps/backend

# Clear și warmup cache (production)
APP_ENV=prod composer dump-env prod
APP_ENV=prod symfony console cache:clear --no-warmup
APP_ENV=prod symfony console cache:warmup

# Verifică cache directory
ls -la var/cache/prod/

# Set correct permissions
sudo chown -R www-data:www-data var/
sudo chmod -R 775 var/cache var/log
```

### 4.10 Indexare Elasticsearch

```bash
cd /var/www/deschide_news_app/apps/backend

# Creează index pentru articole (toate locale-urile: ro, en, ru)
symfony console app:elasticsearch:create-index

# Creează index pentru imagini
symfony console app:elasticsearch:create-image-index

# Index articles (dacă există date în DB)
symfony console app:elasticsearch:index-articles

# Index images (dacă există date în DB)
symfony console app:elasticsearch:index-images

# Verifică indices (IMPORTANT: folosește https, nu http!)
curl -k -u deschide_user:your_password "https://localhost:9200/_cat/indices?v"

# Verifică cluster health
curl -k -u deschide_user:your_password "https://localhost:9200/_cluster/health?pretty"
```

### 4.11 Symfony Messenger Workers (Async Processing)

Pentru procesarea asincronă (generare thumbnails, indexare Elasticsearch, notificări), trebuie să configurezi workers Messenger.

#### 4.11.1 Creează Systemd Service pentru Messenger

```bash
sudo nano /etc/systemd/system/deschide-messenger.service
```

```ini
[Unit]
Description=Deschide News Symfony Messenger Worker
After=network.target redis-server.service postgresql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/deschide_news_app/apps/backend

# Command pentru a rula worker-ul
ExecStart=/usr/bin/php /var/www/deschide_news_app/apps/backend/bin/console messenger:consume async --time-limit=3600 --memory-limit=256M -vv

# Restart automat
Restart=always
RestartSec=10

# Environment
Environment=APP_ENV=prod

# Logging
StandardOutput=append:/var/log/deschide/symfony/messenger.log
StandardError=append:/var/log/deschide/symfony/messenger-error.log

# Security
NoNewPrivileges=true
PrivateTmp=true

[Install]
WantedBy=multi-user.target
```

#### 4.11.2 Creează Multiple Workers (Optional)

Pentru throughput mai mare, poți rula mai mulți workers:

```bash
sudo nano /etc/systemd/system/deschide-messenger@.service
```

```ini
[Unit]
Description=Deschide News Symfony Messenger Worker %i
After=network.target redis-server.service postgresql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/deschide_news_app/apps/backend
ExecStart=/usr/bin/php /var/www/deschide_news_app/apps/backend/bin/console messenger:consume async --time-limit=3600 --memory-limit=256M -vv
Restart=always
RestartSec=10
Environment=APP_ENV=prod
StandardOutput=append:/var/log/deschide/symfony/messenger-%i.log
StandardError=append:/var/log/deschide/symfony/messenger-%i-error.log
NoNewPrivileges=true
PrivateTmp=true

[Install]
WantedBy=multi-user.target
```

#### 4.11.3 Enable și Start Workers

```bash
# Creează director pentru loguri
sudo mkdir -p /var/log/deschide/symfony
sudo chown www-data:www-data /var/log/deschide/symfony

# Reload systemd
sudo systemctl daemon-reload

# Enable și start worker singular
sudo systemctl enable deschide-messenger
sudo systemctl start deschide-messenger

# SAU: Enable și start multiple workers (3 instanțe)
sudo systemctl enable deschide-messenger@{1..3}
sudo systemctl start deschide-messenger@{1..3}

# Verifică status
sudo systemctl status deschide-messenger
# sau
sudo systemctl status 'deschide-messenger@*'

# Verifică logs
sudo tail -f /var/log/deschide/symfony/messenger.log
```

#### 4.11.4 Verificare Failed Messages

```bash
cd /var/www/deschide_news_app/apps/backend

# Listează mesaje eșuate
symfony console messenger:failed:show

# Retry mesaje eșuate
symfony console messenger:failed:retry

# Șterge toate mesajele eșuate
symfony console messenger:failed:remove --all
```

### 4.12 Symfony Scheduler (Scheduled Tasks)

Aplicația folosește Symfony Scheduler pentru task-uri programate (publicare articole, cleanup).

#### 4.12.1 Creează Systemd Service pentru Scheduler

```bash
sudo nano /etc/systemd/system/deschide-scheduler.service
```

```ini
[Unit]
Description=Deschide News Symfony Scheduler
After=network.target postgresql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/deschide_news_app/apps/backend

# Rulează scheduler-ul
ExecStart=/usr/bin/php /var/www/deschide_news_app/apps/backend/bin/console messenger:consume scheduler_default --time-limit=3600 -vv

Restart=always
RestartSec=30
Environment=APP_ENV=prod

StandardOutput=append:/var/log/deschide/symfony/scheduler.log
StandardError=append:/var/log/deschide/symfony/scheduler-error.log

NoNewPrivileges=true
PrivateTmp=true

[Install]
WantedBy=multi-user.target
```

#### 4.12.2 Enable și Start Scheduler

```bash
# Enable și start
sudo systemctl daemon-reload
sudo systemctl enable deschide-scheduler
sudo systemctl start deschide-scheduler

# Verifică status
sudo systemctl status deschide-scheduler

# Verifică scheduled tasks
cd /var/www/deschide_news_app/apps/backend
symfony console debug:scheduler
```

#### 4.12.3 Alternativă: Cron Jobs pentru Scheduled Articles

Dacă preferi cron în loc de Symfony Scheduler:

```bash
# Editează crontab
crontab -e

# Adaugă:
# Publish scheduled articles (every 5 minutes)
*/5 * * * * cd /var/www/deschide_news_app/apps/backend && APP_ENV=prod /usr/bin/php bin/console app:publish-scheduled-articles >> /var/log/deschide/cron/scheduled-articles.log 2>&1

# Cleanup expired article locks (every hour)
0 * * * * cd /var/www/deschide_news_app/apps/backend && APP_ENV=prod /usr/bin/php bin/console app:cleanup-expired-locks >> /var/log/deschide/cron/cleanup-locks.log 2>&1
```

---

### 4.13 Test Backend API

```bash
# Start temporary Symfony server (pentru test)
cd /var/www/deschide_news_app/apps/backend
symfony serve -d --port=8081 --no-tls

# Test API
curl http://127.0.0.1:8081/api

# Trebuie să returneze: {
#   "@context": "/api/contexts/Entrypoint",
#   "@id": "/api",
#   "@type": "Entrypoint",
#   ...
# }

# Stop Symfony server (vor folosi Nginx + PHP-FPM)
symfony server:stop
```

---

## 5. Setup Frontend Next.js

### 5.1 Instalare Node.js 20 LTS

```bash
# Instalare prin NodeSource
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Verifică versiuni
node --version
npm --version

# Trebuie: Node v20.x și npm v10.x
```

### 5.2 Instalare pnpm

```bash
# Instalare pnpm
sudo npm install -g pnpm

# Verifică versiunea
pnpm --version

# Configurare store location (optional)
pnpm config set store-dir /var/cache/pnpm
```

### 5.3 Instalare PM2 (Process Manager)

```bash
# Instalare PM2
sudo npm install -g pm2

# Verifică
pm2 --version

# Setup PM2 startup (pentru auto-restart la reboot)
pm2 startup systemd -u $USER --hp /home/$USER
# Copiază și rulează comanda afișată

# Salvează configurația
pm2 save
```

### 5.4 Clone și Setup Frontend

```bash
cd /var/www/deschide_news_app

# Clone frontend (dacă nu e deja prezent)
# git clone <frontend-repo-url> apps/frontend

# Sau sincronizează din Windows
# cp -r /mnt/c/path/to/frontend apps/frontend

# Navigate to frontend
cd apps/frontend

# Instalare dependencies
pnpm install --frozen-lockfile

# Verifică structura
ls -la
```

### 5.5 Configurare Environment (.env.local)

```bash
cd /var/www/deschide_news_app/apps/frontend
nano .env.local
```

**Configurație Production**:

```bash
# Server Configuration
NODE_ENV=production
PORT=3005

# API Configuration
NEXT_PUBLIC_API_URL=http://127.0.0.1:8081
NEXT_PUBLIC_API_ENTRYPOINT=/api

# CDN Configuration
NEXT_PUBLIC_CDN_URL=http://127.0.0.1:8082

# Mercure Real-time
NEXT_PUBLIC_MERCURE_URL=https://deschide.local/.well-known/mercure
NEXT_PUBLIC_MERCURE_HUB_URL=http://localhost:3000/.well-known/mercure

# Localization
NEXT_PUBLIC_DEFAULT_LOCALE=ro
NEXT_PUBLIC_AVAILABLE_LOCALES=ro,en,ru
NEXT_PUBLIC_FALLBACK_LOCALE=ro

# Site Configuration
NEXT_PUBLIC_SITE_NAME="Deschide News"
NEXT_PUBLIC_SITE_URL=https://deschide.local
NEXT_PUBLIC_SITE_DESCRIPTION="Portal de știri multilingual"

# Feature Flags
NEXT_PUBLIC_ENABLE_LIVE_TEXT=true
NEXT_PUBLIC_ENABLE_COMMENTS=true
NEXT_PUBLIC_ENABLE_SHARE=true

# Analytics (Optional)
# NEXT_PUBLIC_GA_ID=G-XXXXXXXXXX
# NEXT_PUBLIC_GTM_ID=GTM-XXXXXXX

# Sentry (Optional)
# SENTRY_DSN=https://...
# SENTRY_AUTH_TOKEN=...
```

### 5.6 Build Frontend (Standalone Mode)

Verifică `next.config.mjs`:

```bash
nano next.config.mjs
```

Asigură-te că există:

```javascript
/** @type {import('next').NextConfig} */
const nextConfig = {
  output: 'standalone',
  
  // Disable static optimization pentru SSR
  generateBuildId: async () => {
    return 'deschide-' + Date.now()
  },
  
  // Image optimization
  images: {
    domains: ['127.0.0.1', 'deschide.local'],
    formats: ['image/avif', 'image/webp'],
    deviceSizes: [640, 750, 828, 1080, 1200, 1920],
    imageSizes: [16, 32, 48, 64, 96, 128, 256, 384],
  },
  
  // Experimental features (Next.js 16)
  experimental: {
    serverActions: {
      allowedOrigins: ['deschide.local', 'localhost:3005'],
    },
  },
  
  // Compiler options
  compiler: {
    removeConsole: process.env.NODE_ENV === 'production',
  },
}

export default nextConfig
```

**Build aplicația**:

```bash
cd /var/www/deschide_news_app/apps/frontend

# Build production
pnpm run build

# Verifică build output
ls -la .next/

# Verifică standalone output
ls -la .next/standalone/
```

**Copiază fișiere statice** (IMPORTANT pentru standalone mode):

```bash
cd /var/www/deschide_news_app/apps/frontend

# Copiază public folder
cp -r public .next/standalone/

# Copiază .next/static
cp -r .next/static .next/standalone/.next/

# Verifică
ls -la .next/standalone/public/
ls -la .next/standalone/.next/static/
```

### 5.7 Configurare PM2 Ecosystem

Creează `ecosystem.config.js`:

```bash
cd /var/www/deschide_news_app/apps/frontend
nano ecosystem.config.js
```

```javascript
module.exports = {
  apps: [
    {
      name: 'deschide-frontend',
      script: '.next/standalone/server.js',
      cwd: '/var/www/deschide_news_app/apps/frontend',
      instances: 'max',              // Folosește toate CPU cores
      exec_mode: 'cluster',           // Cluster mode pentru load balancing
      max_memory_restart: '1G',       // Restart dacă depășește 1GB RAM
      env: {
        NODE_ENV: 'production',
        PORT: 3005,
        HOSTNAME: '127.0.0.1',
      },
      error_file: '/var/log/deschide/nextjs/error.log',
      out_file: '/var/log/deschide/nextjs/out.log',
      log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
      merge_logs: true,
      autorestart: true,
      watch: false,
      max_restarts: 10,
      min_uptime: '10s',
    },
  ],
}
```

### 5.8 Start Frontend cu PM2

```bash
# Creează director pentru loguri Next.js
sudo mkdir -p /var/log/deschide/nextjs
sudo chown -R $USER:$USER /var/log/deschide/nextjs

# Start aplicația
cd /var/www/deschide_news_app/apps/frontend
pm2 start ecosystem.config.js

# Verifică status
pm2 status
pm2 logs deschide-frontend --lines 50

# Salvează configurația pentru auto-restart
pm2 save

# Test frontend
curl http://localhost:3005

# Verifică că toate instanțele rulează
pm2 list
```

---

## 6. Configurare Nginx și SSL

### 6.1 Instalare Nginx

```bash
# Instalare Nginx
sudo apt install -y nginx

# Verifică versiunea
nginx -v

# Start și enable
sudo systemctl start nginx
sudo systemctl enable nginx

# Verifică status
sudo systemctl status nginx
```

### 6.2 Configurare SSL Local (mkcert)

Pentru development local cu HTTPS:

```bash
# Instalare mkcert
cd /tmp
wget https://github.com/FiloSottile/mkcert/releases/latest/download/mkcert-v*-linux-amd64
sudo mv mkcert-v*-linux-amd64 /usr/local/bin/mkcert
sudo chmod +x /usr/local/bin/mkcert

# Instalare CA local
mkcert -install

# Generare certificate pentru deschide.local
cd /etc/ssl/deschide
sudo mkcert -cert-file deschide.local.pem -key-file deschide.local-key.pem deschide.local "*.deschide.local" localhost 127.0.0.1

# Verifică certificate
ls -la /etc/ssl/deschide/
```

**Adaugă deschide.local în /etc/hosts**:

**1. În WSL Ubuntu** (OBLIGATORIU):

```bash
# Editează /etc/hosts în WSL
sudo nano /etc/hosts

# Adaugă la sfârșit:
127.0.0.1 deschide.local
127.0.0.1 api.deschide.local
127.0.0.1 cdn.deschide.local
```

**2. În Windows** (pentru acces din browser Windows):

```
# Editează C:\Windows\System32\drivers\etc\hosts (ca Administrator în Notepad)
# Adaugă:
127.0.0.1 deschide.local
127.0.0.1 api.deschide.local
127.0.0.1 cdn.deschide.local
```

**3. Verificare configurare hosts:**

```bash
# Din WSL - verifică că rezolvă corect
ping -c 1 deschide.local
# Trebuie să returneze: 127.0.0.1

# Test DNS resolution
getent hosts deschide.local
# Trebuie să returneze: 127.0.0.1 deschide.local
```

**NOTĂ**: În WSL2, fișierul `/etc/hosts` poate fi suprascris automat de Windows. Pentru a preveni asta:

```bash
# Editează /etc/wsl.conf
sudo nano /etc/wsl.conf

# Adaugă:
[network]
generateHosts = false
```

După această modificare, trebuie să repornești WSL:
```powershell
# Din PowerShell Windows (ca Administrator)
wsl --shutdown
# Apoi pornește din nou WSL
```

---

### 6.3 Configurare Nginx - Site Principal

Creează `/etc/nginx/sites-available/deschide.local`:

```bash
sudo nano /etc/nginx/sites-available/deschide.local
```

```nginx
# Upstream pentru Next.js (PM2 Cluster)
upstream nextjs_backend {
    server 127.0.0.1:3005;
    keepalive 64;
}

# Upstream pentru Symfony API (PHP-FPM)
upstream symfony_backend {
    server unix:/run/php/php8.4-fpm.sock;
}

# Rate limiting zones
limit_req_zone $binary_remote_addr zone=api_limit:10m rate=10r/s;
limit_req_zone $binary_remote_addr zone=general_limit:10m rate=30r/s;

# Redirect HTTP -> HTTPS
server {
    listen 80;
    listen [::]:80;
    server_name deschide.local *.deschide.local;
    
    # Redirect all HTTP to HTTPS
    return 301 https://$host$request_uri;
}

# Main HTTPS Server
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name deschide.local;

    # SSL Configuration
    ssl_certificate /etc/ssl/deschide/deschide.local.pem;
    ssl_certificate_key /etc/ssl/deschide/deschide.local-key.pem;
    
    # SSL Optimization
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers 'ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384';
    ssl_prefer_server_ciphers on;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;
    ssl_stapling on;
    ssl_stapling_verify on;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Logging
    access_log /var/log/deschide/nginx/access.log;
    error_log /var/log/deschide/nginx/error.log warn;

    # Client body size
    client_max_body_size 20M;

    # Gzip Compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml text/javascript 
               application/json application/javascript application/xml+rss 
               application/rss+xml font/truetype font/opentype 
               application/vnd.ms-fontobject image/svg+xml;

    # ============================================
    # 1. API Backend (Symfony) - /api
    # ============================================
    location ^~ /api {
        # Rate limiting
        limit_req zone=api_limit burst=20 nodelay;
        
        # Timeout settings pentru API
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
        
        # Try file first, then pass to PHP
        try_files $uri /api/index.php$is_args$args;
        
        location ~ ^/api/.*\.php(/|$) {
            fastcgi_pass symfony_backend;
            fastcgi_split_path_info ^(.+\.php)(/.*)$;
            include fastcgi_params;
            
            fastcgi_param SCRIPT_FILENAME /var/www/deschide_news_app/apps/backend/public$fastcgi_script_name;
            fastcgi_param DOCUMENT_ROOT /var/www/deschide_news_app/apps/backend/public;
            fastcgi_param PATH_INFO $fastcgi_path_info;
            
            # Buffering pentru răspunsuri JSON mari
            fastcgi_buffer_size 128k;
            fastcgi_buffers 4 256k;
            fastcgi_busy_buffers_size 256k;
            
            # Timeouts
            fastcgi_connect_timeout 60s;
            fastcgi_send_timeout 60s;
            fastcgi_read_timeout 60s;
            
            internal;
        }
    }

    # ============================================
    # 2. Mercure Hub - /.well-known/mercure
    # ============================================
    location /.well-known/mercure {
        proxy_pass http://localhost:3000;
        proxy_http_version 1.1;
        proxy_set_header Connection "";
        
        # SSE specific
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
        
        # Disable buffering for SSE
        proxy_buffering off;
        proxy_cache off;
        
        # Timeouts (long-lived connections)
        proxy_connect_timeout 1h;
        proxy_send_timeout 1h;
        proxy_read_timeout 1h;
    }

    # ============================================
    # 3. Static Assets - /uploads
    # ============================================
    location /uploads {
        alias /var/www/deschide_news_app/uploads;
        expires 365d;
        add_header Cache-Control "public, max-age=31536000, immutable";
        access_log off;
        
        # Securitate: disable script execution
        location ~ \.php$ {
            deny all;
        }
    }

    # ============================================
    # 4. Next.js Static Assets - /_next/static
    # ============================================
    location /_next/static {
        alias /var/www/deschide_news_app/apps/frontend/.next/standalone/.next/static;
        expires 365d;
        add_header Cache-Control "public, max-age=31536000, immutable";
        access_log off;
    }

    # ============================================
    # 5. Next.js Public Folder - /images, /fonts, etc.
    # ============================================
    location ~ ^/(images|fonts|icons|favicon\.ico|robots\.txt|sitemap\.xml) {
        root /var/www/deschide_news_app/apps/frontend/.next/standalone/public;
        expires 30d;
        add_header Cache-Control "public, max-age=2592000";
        access_log off;
    }

    # ============================================
    # 6. Health Checks
    # ============================================
    location /health {
        access_log off;
        return 200 "OK\n";
        add_header Content-Type text/plain;
    }

    # ============================================
    # 7. Frontend (Next.js) - Default
    # ============================================
    location / {
        # Rate limiting
        limit_req zone=general_limit burst=50 nodelay;
        
        proxy_pass http://nextjs_backend;
        proxy_http_version 1.1;
        
        # Headers
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Port $server_port;
        
        # Caching
        proxy_cache_bypass $http_upgrade;
        
        # Timeouts
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }

    # ============================================
    # 8. Security: Deny dotfiles
    # ============================================
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }
}
```

### 6.4 Enable Site și Test Nginx

```bash
# Creează directoare pentru loguri
sudo mkdir -p /var/log/deschide/nginx
sudo chown -R www-data:www-data /var/log/deschide/nginx

# Enable site
sudo ln -s /etc/nginx/sites-available/deschide.local /etc/nginx/sites-enabled/

# Disable default site
sudo rm -f /etc/nginx/sites-enabled/default

# Test configurație
sudo nginx -t

# Trebuie să returneze: 
# nginx: the configuration file /etc/nginx/nginx.conf syntax is ok
# nginx: configuration file /etc/nginx/nginx.conf test is successful

# Reload Nginx
sudo systemctl reload nginx

# Verifică status
sudo systemctl status nginx
```

### 6.5 Test Complet

```bash
# Test HTTP -> HTTPS redirect
curl -I http://deschide.local

# Test HTTPS homepage (Next.js)
curl -k https://deschide.local

# Test API (Symfony)
curl -k https://deschide.local/api

# Test static uploads
# (asigură-te că ai ceva în /uploads)

# Test Mercure
curl -k https://deschide.local/.well-known/mercure

# Check în browser
# https://deschide.local
```

---

## 7. Deployment Workflow

### 7.1 Script de Deployment

Creează script de deployment automatizat:

```bash
sudo nano /var/www/deschide_news_app/scripts/deploy.sh
```

```bash
#!/bin/bash

##############################################
# Deschide News App - Deployment Script
# Version: 1.0
# Usage: ./deploy.sh [backend|frontend|all]
##############################################

set -e  # Exit on error

# Colors pentru output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
PROJECT_ROOT="/var/www/deschide_news_app"
BACKEND_DIR="${PROJECT_ROOT}/apps/backend"
FRONTEND_DIR="${PROJECT_ROOT}/apps/frontend"
BACKUP_DIR="/var/backups/deschide"
DATE=$(date +%Y%m%d_%H%M%S)

# Logging
LOG_FILE="/var/log/deschide/deploy-${DATE}.log"

# Functions
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1" | tee -a "$LOG_FILE"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1" | tee -a "$LOG_FILE"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE"
}

# Backup database
backup_database() {
    log_info "Creating database backup..."
    
    mkdir -p "${BACKUP_DIR}/database"
    
    pg_dump -h localhost -U deschide_admin -d deschide_news \
        --format=custom \
        --file="${BACKUP_DIR}/database/deschide_${DATE}.dump"
    
    if [ $? -eq 0 ]; then
        log_info "Database backup created: ${BACKUP_DIR}/database/deschide_${DATE}.dump"
    else
        log_error "Database backup failed!"
        exit 1
    fi
}

# Deploy Backend
deploy_backend() {
    log_info "=========================================="
    log_info "Starting Backend Deployment"
    log_info "=========================================="
    
    cd "$BACKEND_DIR"
    
    # Git pull (dacă folosești git)
    # log_info "Pulling latest code..."
    # git pull origin main
    
    # Install dependencies
    log_info "Installing Composer dependencies..."
    composer install --no-dev --optimize-autoloader --classmap-authoritative
    
    # Database migrations
    log_info "Running database migrations..."
    symfony console doctrine:migrations:migrate --no-interaction
    
    # Cache clear
    log_info "Clearing cache..."
    APP_ENV=prod composer dump-env prod
    APP_ENV=prod symfony console cache:clear --no-warmup
    APP_ENV=prod symfony console cache:warmup
    
    # Set permissions
    log_info "Setting permissions..."
    sudo chown -R www-data:www-data var/
    sudo chmod -R 775 var/cache var/log
    
    # Restart PHP-FPM
    log_info "Restarting PHP-FPM..."
    sudo systemctl restart php8.4-fpm
    
    # Elasticsearch reindex (optional, poate dura mult)
    read -p "Reindex Elasticsearch? (y/n): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        log_info "Reindexing Elasticsearch..."
        symfony console app:elasticsearch:index-articles --batch-size=100
    fi
    
    log_info "Backend deployment completed successfully!"
}

# Deploy Frontend
deploy_frontend() {
    log_info "=========================================="
    log_info "Starting Frontend Deployment"
    log_info "=========================================="
    
    cd "$FRONTEND_DIR"
    
    # Git pull (dacă folosești git)
    # log_info "Pulling latest code..."
    # git pull origin main
    
    # Install dependencies
    log_info "Installing pnpm dependencies..."
    pnpm install --frozen-lockfile
    
    # Build
    log_info "Building Next.js application..."
    pnpm run build
    
    # Copy static files pentru standalone mode
    log_info "Copying static files..."
    cp -r public .next/standalone/
    cp -r .next/static .next/standalone/.next/
    
    # Restart PM2
    log_info "Restarting PM2 application..."
    pm2 restart ecosystem.config.js
    pm2 save
    
    # Wait for startup
    sleep 5
    
    # Check PM2 status
    pm2 status deschide-frontend
    
    log_info "Frontend deployment completed successfully!"
}

# Main deployment logic
main() {
    # Check if log directory exists
    mkdir -p "$(dirname "$LOG_FILE")"
    
    log_info "=========================================="
    log_info "Deschide News App - Deployment Script"
    log_info "Date: ${DATE}"
    log_info "=========================================="
    
    # Determine what to deploy
    DEPLOY_TARGET="${1:-all}"
    
    case "$DEPLOY_TARGET" in
        backend)
            backup_database
            deploy_backend
            ;;
        frontend)
            deploy_frontend
            ;;
        all)
            backup_database
            deploy_backend
            deploy_frontend
            ;;
        *)
            log_error "Invalid deployment target: $DEPLOY_TARGET"
            echo "Usage: $0 [backend|frontend|all]"
            exit 1
            ;;
    esac
    
    # Reload Nginx
    log_info "Reloading Nginx..."
    sudo nginx -t && sudo systemctl reload nginx
    
    log_info "=========================================="
    log_info "Deployment Completed Successfully!"
    log_info "=========================================="
    log_info "Log file: ${LOG_FILE}"
}

# Run main function
main "$@"
```

Fă scriptul executabil:

```bash
sudo chmod +x /var/www/deschide_news_app/scripts/deploy.sh
```

### 7.2 Folosirea Scriptului de Deployment

```bash
# Deploy tot (backend + frontend)
cd /var/www/deschide_news_app
./scripts/deploy.sh all

# Deploy doar backend
./scripts/deploy.sh backend

# Deploy doar frontend
./scripts/deploy.sh frontend
```

---

## 8. Automatizări și Cron Jobs

### 8.1 Setup Arhivare Articole Vechi

Scriptul deja există în `/var/www/deschide_news_app/scripts/cron/archive-old-articles.sh`

Verifică și asigură-te că e executabil:

```bash
ls -lah /var/www/deschide_news_app/scripts/cron/archive-old-articles.sh
sudo chmod +x /var/www/deschide_news_app/scripts/cron/archive-old-articles.sh
```

### 8.2 Configurare Crontab

```bash
# Editează crontab
crontab -e

# Adaugă următoarele linii:
```

```cron
# Deschide News App - Cron Jobs

# 1. Archive old articles (Monthly, 1st day at 02:00 AM)
0 2 1 * * /var/www/deschide_news_app/scripts/cron/archive-old-articles.sh

# 2. Database backup (Daily at 03:00 AM)
0 3 * * * /var/www/deschide_news_app/scripts/backup/backup-database.sh

# 3. Clean old logs (Weekly, Sunday at 04:00 AM)
0 4 * * 0 /var/www/deschide_news_app/scripts/maintenance/clean-old-logs.sh

# 4. Elasticsearch reindex (Weekly, Sunday at 05:00 AM)
0 5 * * 0 /var/www/deschide_news_app/scripts/maintenance/reindex-elasticsearch.sh

# 5. Clear old sessions (Daily at 06:00 AM)
0 6 * * * redis-cli -h 127.0.0.1 -p 6379 -n 1 EVAL "for _,k in ipairs(redis.call('keys','deschide_session:*')) do redis.call('del',k) end" 0

# 6. Symfony cache clear (Daily at 02:30 AM)
30 2 * * * cd /var/www/deschide_news_app/apps/backend && APP_ENV=prod symfony console cache:pool:clear cache.app

# 7. PM2 log rotation (Daily at 01:00 AM)
0 1 * * * pm2 flush
```

### 8.3 Script de Backup Database

Creează `/var/www/deschide_news_app/scripts/backup/backup-database.sh`:

```bash
sudo mkdir -p /var/www/deschide_news_app/scripts/backup
sudo nano /var/www/deschide_news_app/scripts/backup/backup-database.sh
```

```bash
#!/bin/bash

##############################################
# Database Backup Script pentru Deschide News
##############################################

set -e

# Configuration
BACKUP_DIR="/var/backups/deschide/database"
LOG_DIR="/var/log/deschide"
DB_NAME="deschide_news"
DB_USER="deschide_admin"
DB_HOST="localhost"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=30

# Log file
LOG_FILE="${LOG_DIR}/backup-${DATE}.log"

# Functions
log_info() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] [INFO] $1" | tee -a "$LOG_FILE"
}

log_error() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] [ERROR] $1" | tee -a "$LOG_FILE"
}

# Main
log_info "Starting database backup..."

# Create backup directory
mkdir -p "$BACKUP_DIR"

# Backup filename
BACKUP_FILE="${BACKUP_DIR}/deschide_${DATE}.dump"

# Perform backup
log_info "Backing up database: ${DB_NAME}"
pg_dump -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" \
    --format=custom \
    --compress=9 \
    --file="$BACKUP_FILE"

if [ $? -eq 0 ]; then
    log_info "Backup completed: ${BACKUP_FILE}"
    
    # Get file size
    SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
    log_info "Backup size: ${SIZE}"
    
    # Compress with gzip pentru extra compression
    gzip "$BACKUP_FILE"
    log_info "Compressed backup: ${BACKUP_FILE}.gz"
else
    log_error "Backup failed!"
    exit 1
fi

# Clean old backups
log_info "Cleaning backups older than ${RETENTION_DAYS} days..."
find "$BACKUP_DIR" -name "deschide_*.dump.gz" -mtime +${RETENTION_DAYS} -delete
log_info "Old backups cleaned"

log_info "Backup process completed successfully!"
```

Fă scriptul executabil:

```bash
sudo chmod +x /var/www/deschide_news_app/scripts/backup/backup-database.sh
```

### 8.4 Script de Curățare Loguri Vechi

Creează `/var/www/deschide_news_app/scripts/maintenance/clean-old-logs.sh`:

```bash
sudo mkdir -p /var/www/deschide_news_app/scripts/maintenance
sudo nano /var/www/deschide_news_app/scripts/maintenance/clean-old-logs.sh
```

```bash
#!/bin/bash

##############################################
# Clean Old Logs Script
##############################################

set -e

# Configuration
LOG_BASE="/var/log/deschide"
RETENTION_DAYS=30

echo "[$(date)] Starting log cleanup..."

# Clean Symfony logs
find "${LOG_BASE}/symfony" -name "*.log" -mtime +${RETENTION_DAYS} -delete

# Clean PHP logs
find "${LOG_BASE}/php" -name "*.log" -mtime +${RETENTION_DAYS} -delete

# Clean Nginx logs
find "${LOG_BASE}/nginx" -name "*.log" -mtime +${RETENTION_DAYS} -delete

# Clean cron logs
find "${LOG_BASE}/cron" -name "*.log" -mtime +${RETENTION_DAYS} -delete

# Compress remaining logs older than 7 days
find "${LOG_BASE}" -name "*.log" -mtime +7 ! -name "*.gz" -exec gzip {} \;

echo "[$(date)] Log cleanup completed"
```

Fă executabil:

```bash
sudo chmod +x /var/www/deschide_news_app/scripts/maintenance/clean-old-logs.sh
```

---

## 9. Backup și Recovery

### 9.1 Backup Complet

Script pentru backup complet (DB + uploads + configs):

```bash
sudo nano /var/www/deschide_news_app/scripts/backup/full-backup.sh
```

```bash
#!/bin/bash

##############################################
# Full System Backup pentru Deschide News
##############################################

set -e

# Configuration
BACKUP_ROOT="/var/backups/deschide"
PROJECT_ROOT="/var/www/deschide_news_app"
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_NAME="deschide_full_${DATE}"
BACKUP_DIR="${BACKUP_ROOT}/${BACKUP_NAME}"

# Create backup directory
mkdir -p "$BACKUP_DIR"

echo "=========================================="
echo "Full Backup - ${DATE}"
echo "=========================================="

# 1. Database Backup
echo "[1/4] Backing up database..."
pg_dump -h localhost -U deschide_admin -d deschide_news \
    --format=custom \
    --compress=9 \
    --file="${BACKUP_DIR}/database.dump"

# 2. Uploads Backup
echo "[2/4] Backing up uploads..."
tar -czf "${BACKUP_DIR}/uploads.tar.gz" \
    -C "${PROJECT_ROOT}" uploads

# 3. Config Backup
echo "[3/4] Backing up configs..."
mkdir -p "${BACKUP_DIR}/configs"
cp "${PROJECT_ROOT}/apps/backend/.env.local" "${BACKUP_DIR}/configs/backend.env"
cp "${PROJECT_ROOT}/apps/frontend/.env.local" "${BACKUP_DIR}/configs/frontend.env"
cp /etc/nginx/sites-available/deschide.local "${BACKUP_DIR}/configs/nginx.conf"

# 4. Create final archive
echo "[4/4] Creating final archive..."
cd "$BACKUP_ROOT"
tar -czf "${BACKUP_NAME}.tar.gz" "${BACKUP_NAME}"
rm -rf "${BACKUP_NAME}"

echo "=========================================="
echo "Backup completed: ${BACKUP_ROOT}/${BACKUP_NAME}.tar.gz"
echo "Size: $(du -h ${BACKUP_ROOT}/${BACKUP_NAME}.tar.gz | cut -f1)"
echo "=========================================="
```

### 9.2 Restore Database

```bash
# Restore database din backup
pg_restore -h localhost -U deschide_admin -d deschide_news \
    --clean --if-exists \
    /var/backups/deschide/database/deschide_20250101_030000.dump

# Sau din full backup
cd /var/backups/deschide
tar -xzf deschide_full_20250101_030000.tar.gz
pg_restore -h localhost -U deschide_admin -d deschide_news \
    --clean --if-exists \
    deschide_full_20250101_030000/database.dump
```

---

## 10. Monitoring și Maintenance

### 10.1 Health Check Script

Creează `/var/www/deschide_news_app/scripts/monitoring/health-check.sh`:

```bash
sudo mkdir -p /var/www/deschide_news_app/scripts/monitoring
sudo nano /var/www/deschide_news_app/scripts/monitoring/health-check.sh
```

```bash
#!/bin/bash

##############################################
# System Health Check pentru Deschide News
##############################################

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo "=========================================="
echo "Deschide News App - Health Check"
echo "$(date)"
echo "=========================================="

# Function to check service
check_service() {
    if systemctl is-active --quiet "$1"; then
        echo -e "${GREEN}✓${NC} $2 is running"
        return 0
    else
        echo -e "${RED}✗${NC} $2 is NOT running"
        return 1
    fi
}

# Function to check port
check_port() {
    if nc -z localhost "$1" 2>/dev/null; then
        echo -e "${GREEN}✓${NC} Port $1 ($2) is open"
        return 0
    else
        echo -e "${RED}✗${NC} Port $1 ($2) is NOT accessible"
        return 1
    fi
}

# Check system services
echo ""
echo "System Services:"
check_service postgresql "PostgreSQL"
check_service redis-server "Redis"
check_service elasticsearch "Elasticsearch"
check_service rabbitmq-server "RabbitMQ"
check_service php8.4-fpm "PHP-FPM"
check_service nginx "Nginx"
check_service mercure "Mercure Hub"

# Check ports
echo ""
echo "Network Ports:"
check_port 5432 "PostgreSQL"
check_port 6379 "Redis"
check_port 9200 "Elasticsearch"
check_port 5672 "RabbitMQ"
check_port 80 "Nginx HTTP"
check_port 443 "Nginx HTTPS"
check_port 3000 "Mercure Hub"
check_port 3005 "Next.js Frontend"

# Check PM2
echo ""
echo "PM2 Applications:"
pm2 status | grep deschide-frontend

# Check disk space
echo ""
echo "Disk Usage:"
df -h /var/www/deschide_news_app | tail -1
df -h /var/log/deschide | tail -1
df -h /var/backups/deschide | tail -1

# Check database
echo ""
echo "Database Connection:"
if psql -h localhost -U deschide_admin -d deschide_news -c "SELECT 1" >/dev/null 2>&1; then
    echo -e "${GREEN}✓${NC} Database connection successful"
else
    echo -e "${RED}✗${NC} Database connection failed"
fi

# Check Redis
echo ""
echo "Redis Connection:"
if redis-cli ping >/dev/null 2>&1; then
    echo -e "${GREEN}✓${NC} Redis connection successful"
else
    echo -e "${RED}✗${NC} Redis connection failed"
fi

# Check Elasticsearch
echo ""
echo "Elasticsearch Status:"
curl -k -s -u deschide_user:your_password https://localhost:9200/_cluster/health?pretty | grep status

# Check API
echo ""
echo "API Health:"
if curl -k -s https://deschide.local/api >/dev/null; then
    echo -e "${GREEN}✓${NC} API is responding"
else
    echo -e "${RED}✗${NC} API is not responding"
fi

# Check Frontend
echo ""
echo "Frontend Health:"
if curl -k -s https://deschide.local >/dev/null; then
    echo -e "${GREEN}✓${NC} Frontend is responding"
else
    echo -e "${RED}✗${NC} Frontend is not responding"
fi

echo ""
echo "=========================================="
echo "Health Check Completed"
echo "=========================================="
```

Fă executabil:

```bash
sudo chmod +x /var/www/deschide_news_app/scripts/monitoring/health-check.sh
```

Rulează:

```bash
/var/www/deschide_news_app/scripts/monitoring/health-check.sh
```

### 10.2 Log Rotation

Configurează logrotate pentru Deschide News:

```bash
sudo nano /etc/logrotate.d/deschide-news
```

```
/var/log/deschide/*/*.log {
    daily
    rotate 30
    compress
    delaycompress
    notifempty
    missingok
    create 0644 www-data www-data
    sharedscripts
    postrotate
        # Reload services dacă e necesar
        systemctl reload nginx >/dev/null 2>&1 || true
        systemctl reload php8.4-fpm >/dev/null 2>&1 || true
    endscript
}

/var/log/deschide/deploy-*.log {
    weekly
    rotate 12
    compress
    delaycompress
    notifempty
    missingok
    create 0644 $USER $USER
}
```

Test logrotate:

```bash
sudo logrotate -d /etc/logrotate.d/deschide-news
sudo logrotate -f /etc/logrotate.d/deschide-news
```

---

### 10.3 Prometheus și Grafana (Optional - Monitoring Avansat)

Pentru monitoring avansat cu metrici și dashboards, poți instala Prometheus și Grafana.

#### 10.3.1 Instalare Prometheus

```bash
# Creează user pentru Prometheus
sudo useradd --no-create-home --shell /bin/false prometheus

# Download Prometheus
cd /tmp
wget https://github.com/prometheus/prometheus/releases/download/v2.48.0/prometheus-2.48.0.linux-amd64.tar.gz
tar -xzf prometheus-2.48.0.linux-amd64.tar.gz
cd prometheus-2.48.0.linux-amd64

# Move binaries
sudo mv prometheus promtool /usr/local/bin/
sudo mkdir -p /etc/prometheus /var/lib/prometheus
sudo mv consoles console_libraries /etc/prometheus/

# Set ownership
sudo chown -R prometheus:prometheus /etc/prometheus /var/lib/prometheus
```

#### 10.3.2 Configurare Prometheus

```bash
sudo nano /etc/prometheus/prometheus.yml
```

```yaml
global:
  scrape_interval: 15s
  evaluation_interval: 15s

alerting:
  alertmanagers:
    - static_configs:
        - targets: []

rule_files: []

scrape_configs:
  - job_name: 'prometheus'
    static_configs:
      - targets: ['localhost:9090']

  - job_name: 'node'
    static_configs:
      - targets: ['localhost:9100']

  - job_name: 'php-fpm'
    static_configs:
      - targets: ['localhost:9253']

  - job_name: 'nginx'
    static_configs:
      - targets: ['localhost:9113']

  - job_name: 'postgresql'
    static_configs:
      - targets: ['localhost:9187']

  - job_name: 'redis'
    static_configs:
      - targets: ['localhost:9121']
```

#### 10.3.3 Systemd Service pentru Prometheus

```bash
sudo nano /etc/systemd/system/prometheus.service
```

```ini
[Unit]
Description=Prometheus
Wants=network-online.target
After=network-online.target

[Service]
User=prometheus
Group=prometheus
Type=simple
ExecStart=/usr/local/bin/prometheus \
    --config.file /etc/prometheus/prometheus.yml \
    --storage.tsdb.path /var/lib/prometheus/ \
    --web.console.templates=/etc/prometheus/consoles \
    --web.console.libraries=/etc/prometheus/console_libraries \
    --web.listen-address=0.0.0.0:9090

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable prometheus
sudo systemctl start prometheus

# Verifică
curl http://localhost:9090/api/v1/status/config
```

#### 10.3.4 Instalare Grafana

```bash
# Add Grafana repository
sudo apt install -y apt-transport-https software-properties-common
wget -q -O - https://packages.grafana.com/gpg.key | sudo apt-key add -
echo "deb https://packages.grafana.com/oss/deb stable main" | sudo tee /etc/apt/sources.list.d/grafana.list

# Install
sudo apt update
sudo apt install -y grafana

# Start și enable
sudo systemctl enable grafana-server
sudo systemctl start grafana-server

# Accesează Grafana la http://localhost:3002
# Default credentials: admin/admin
```

**Notă**: Grafana rulează pe portul 3002 pentru a evita conflicte cu Mercure (port 3000).

Editează `/etc/grafana/grafana.ini` pentru a schimba portul:

```ini
[server]
http_port = 3002
```

---

### 10.4 ImageMagick Policy (pentru procesare imagini)

ImageMagick are politici de securitate care pot bloca procesarea anumitor tipuri de imagini. Pentru a permite aplicației să proceseze imagini corect:

#### 10.4.1 Verifică și Actualizează Policy

```bash
# Verifică locația policy.xml
convert -list policy

# Editează policy.xml
sudo nano /etc/ImageMagick-6/policy.xml
# SAU pentru ImageMagick 7:
sudo nano /etc/ImageMagick-7/policy.xml
```

#### 10.4.2 Configurare Policy pentru Deschide News

Modifică sau adaugă următoarele setări în `policy.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE policymap [
<!ELEMENT policymap (policy)+>
<!ELEMENT policy (#PCDATA)>
<!ATTLIST policy domain (delegate|coder|filter|path|resource) #IMPLIED>
<!ATTLIST policy name CDATA #IMPLIED>
<!ATTLIST policy rights CDATA #IMPLIED>
<!ATTLIST policy pattern CDATA #IMPLIED>
<!ATTLIST policy value CDATA #IMPLIED>
]>
<policymap>
  <!-- Resurse -->
  <policy domain="resource" name="memory" value="512MiB"/>
  <policy domain="resource" name="map" value="1024MiB"/>
  <policy domain="resource" name="width" value="16KP"/>
  <policy domain="resource" name="height" value="16KP"/>
  <policy domain="resource" name="area" value="128MP"/>
  <policy domain="resource" name="disk" value="2GiB"/>
  <policy domain="resource" name="file" value="768"/>
  <policy domain="resource" name="thread" value="4"/>
  <policy domain="resource" name="time" value="300"/>

  <!-- Permite formatele necesare pentru news app -->
  <policy domain="coder" rights="read|write" pattern="PNG" />
  <policy domain="coder" rights="read|write" pattern="JPEG" />
  <policy domain="coder" rights="read|write" pattern="GIF" />
  <policy domain="coder" rights="read|write" pattern="WEBP" />
  <policy domain="coder" rights="read|write" pattern="SVG" />
  <policy domain="coder" rights="read|write" pattern="PDF" />

  <!-- IMPORTANT: Permite path-urile pentru uploads -->
  <policy domain="path" rights="read|write" pattern="/var/www/deschide_news_app/apps/backend/public/uploads/*" />
  <policy domain="path" rights="read|write" pattern="/tmp/*" />
</policymap>
```

#### 10.4.3 Verificare Configurare

```bash
# Testează că ImageMagick funcționează
convert --version

# Testează conversie simplă
convert -size 100x100 xc:red /tmp/test.png
convert /tmp/test.png /tmp/test.webp

# Verifică rezultatul
ls -la /tmp/test.*

# Cleanup
rm /tmp/test.*
```

**IMPORTANT pentru Imagick PHP extension:**

Dacă folosești extensia `imagick` PHP (cum e cazul în acest proiect), verifică că funcționează:

```bash
# Testează din PHP CLI
php -r "echo (new \Imagick())->getVersion()['versionString'] . PHP_EOL;"

# Trebuie să afișeze versiunea ImageMagick
```

---

## 11. Troubleshooting

### 11.1 Probleme Comune și Soluții

#### 11.1.1 Nginx: 502 Bad Gateway

**Cauze posibile**:
- PHP-FPM nu rulează
- Socket PHP-FPM nu există
- Permissions greșite pe socket

**Soluții**:

```bash
# Verifică status PHP-FPM
sudo systemctl status php8.4-fpm

# Verifică socket
ls -la /run/php/php8.4-fpm.sock

# Verifică logs
sudo tail -f /var/log/php/fpm-error.log
sudo tail -f /var/log/deschide/nginx/error.log

# Restart PHP-FPM
sudo systemctl restart php8.4-fpm
```

#### 11.1.2 Next.js: Application not responding

**Cauze posibile**:
- PM2 crashed
- Out of memory
- Port 3005 ocupat

**Soluții**:

```bash
# Check PM2 status
pm2 status

# Check PM2 logs
pm2 logs deschide-frontend --lines 100

# Restart PM2
pm2 restart deschide-frontend

# Check port
sudo lsof -i :3005

# Check memory
pm2 monit
```

#### 11.1.3 Database: Connection refused

**Cauze**:
- PostgreSQL nu rulează
- Credențiale greșite
- pg_hba.conf restrictions

**Soluții**:

```bash
# Check PostgreSQL status
sudo systemctl status postgresql

# Check connections
sudo -u postgres psql -c "SELECT * FROM pg_stat_activity"

# Check pg_hba.conf
sudo nano /etc/postgresql/17/main/pg_hba.conf

# Restart PostgreSQL
sudo systemctl restart postgresql
```

#### 11.1.4 Elasticsearch: Cluster unhealthy

**Cauze**:
- Insufficient memory
- Disk full
- Index corruption

**Soluții**:

```bash
# Check cluster health
curl -k -u deschide_user:password https://localhost:9200/_cluster/health?pretty

# Check indices
curl -k -u deschide_user:password https://localhost:9200/_cat/indices?v

# Check disk space
df -h

# Restart Elasticsearch
sudo systemctl restart elasticsearch
```

### 11.2 Debugging Tools

```bash
# Check all service statuses
systemctl status postgresql redis-server elasticsearch nginx php8.4-fpm mercure

# Check all ports
sudo netstat -tulpn | grep LISTEN

# Check system resources
htop

# Check disk I/O
iostat -x 1

# Check network connections
sudo ss -tunap | grep -E ':(80|443|3005|8081|5432|6379|9200)'

# Check logs în timp real
tail -f /var/log/deschide/nginx/error.log \
        /var/log/php/fpm-error.log \
        /var/log/deschide/nextjs/error.log
```

---

## 12. Checklist Final

### Pre-Production Checklist

- [ ] Toate serviciile sunt running și enabled
- [ ] SSL certificates configurate corect
- [ ] Firewall (dacă e configurat) permite trafic necesar
- [ ] Database backup funcționează
- [ ] Cron jobs configurate și testate
- [ ] Logs rotează corect
- [ ] Performance testing (opcache, Redis, Nginx cache)
- [ ] Security headers configurate în Nginx
- [ ] Error handling și logging configurate
- [ ] Monitoring script funcționează

### Post-Deployment Checklist

- [ ] https://deschide.local funcționează
- [ ] https://deschide.local/api returnează JSON valid
- [ ] Login funcționează (frontend + backend)
- [ ] Upload imagini funcționează
- [ ] Search funcționează (Elasticsearch)
- [ ] Real-time updates funcționează (Mercure)
- [ ] Multilingvism funcționează (ro, en, ru)
- [ ] PM2 cluster mode active (multiple workers)
- [ ] Verifică logs pentru errors

---

## 13. Resurse și Documentație

### Documentație Internă

- `ARCHITECTURE.md` - Arhitectura aplicației
- `SETUP.md` - Setup guide pentru development
- `docs/CRON_SETUP.md` - Configurare cron jobs
- `docs/DATABASE_BACKUP_GUIDE.md` - Ghid backup database
- `CLAUDE.md` - Context pentru AI agents

### Documentație Externă

- [Symfony Documentation](https://symfony.com/doc/current/index.html)
- [Next.js Documentation](https://nextjs.org/docs)
- [PostgreSQL Documentation](https://www.postgresql.org/docs/17/)
- [Nginx Documentation](https://nginx.org/en/docs/)
- [PM2 Documentation](https://pm2.keymetrics.io/docs/)

---

## 14. Support și Maintenance

### Comenzi Utile Zilnice

```bash
# Quick status check
/var/www/deschide_news_app/scripts/monitoring/health-check.sh

# Check logs
sudo tail -f /var/log/deschide/nginx/error.log

# Restart services
sudo systemctl restart php8.4-fpm nginx
pm2 restart deschide-frontend

# Clear cache
cd /var/www/deschide_news_app/apps/backend
APP_ENV=prod symfony console cache:clear

# Database queries
psql -h localhost -U deschide_admin -d deschide
```

### Contact și Suport

Pentru întrebări sau probleme:
1. Verifică logs în `/var/log/deschide/`
2. Rulează health check script
3. Consultă documentația
4. Contact echipa de development

---

**Document creat**: Decembrie 2025
**Versiune**: 1.1
**Ultima actualizare**: 11 Decembrie 2025
**Autor**: Radu Soltan - Software Architect
**Status**: Production Ready ✅

### Changelog v1.1
- ✅ Corectate comenzi Elasticsearch (`app:elasticsearch:create-index`, `app:elasticsearch:create-image-index`)
- ✅ Corectate URL-uri Elasticsearch (https în loc de http)
- ✅ Corectat database name (`deschide_news` în loc de `deschide`)
- ✅ Adăugată secțiune CDN Server (port 8082) cu configurare Nginx
- ✅ Adăugată secțiune Symfony Messenger Workers (systemd service)
- ✅ Adăugată secțiune Symfony Scheduler pentru task-uri programate
- ✅ Adăugată configurare Ubuntu locales (ro_RO, en_US, ru_RU)
- ✅ Adăugată extensie `php8.4-amqp` pentru RabbitMQ
- ✅ Adăugată configurare completă /etc/hosts (WSL + Windows)
- ✅ Adăugată secțiune Prometheus și Grafana (monitoring avansat)
- ✅ Adăugată configurare ImageMagick policy pentru procesare imagini

---

*Acest ghid este menținut activ și va fi actualizat pe măsură ce aplicația evoluează.*
