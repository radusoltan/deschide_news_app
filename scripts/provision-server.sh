#!/usr/bin/env bash
# =============================================================================
# Deschide News — Server Provisioning Script
# =============================================================================
# Automates full setup of a fresh Ubuntu 24.04 LTS server.
# Run ONCE on a clean VPS to install all services and configure the platform.
#
# Usage:
#   sudo bash provision-server.sh --staging       # Staging environment
#   sudo bash provision-server.sh --production    # Production environment
#   sudo bash provision-server.sh --staging --dry-run   # Print without executing
#
# Requirements:
#   - Ubuntu 24.04 LTS (fresh install)
#   - Root or sudo access
#   - Internet connectivity
#   - At least 4 GB RAM, 20 GB disk
# =============================================================================

set -euo pipefail

# =============================================================================
# Configuration
# =============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROVISION_START=$(date +%s)

APP_DIR="/var/www/deschide-news"
APP_USER="www-data"
APP_GROUP="www-data"
REPO_URL="https://github.com/deschide/deschide_news_app.git"
REPO_BRANCH="main"

# Versions
PHP_VERSION="8.5"
PG_VERSION="18"
NODE_VERSION="20"
MERCURE_VERSION="0.16.3"

# Database
DB_NAME="deschide_news"
DB_USER="deschide_user"
DB_PASS=""  # Generated at runtime if empty

# Environment
ENV="staging"
DRY_RUN=false

# Domains (set by environment flag)
DOMAIN=""
API_DOMAIN=""
FRONTEND_PORT=3005

# =============================================================================
# Parse Arguments
# =============================================================================

for arg in "$@"; do
    case "$arg" in
        --staging)
            ENV="staging"
            ;;
        --production)
            ENV="production"
            ;;
        --dry-run)
            DRY_RUN=true
            ;;
        --help|-h)
            echo "Usage: sudo bash $0 [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --staging       Provision for staging environment (default)"
            echo "  --production    Provision for production environment"
            echo "  --dry-run       Print commands without executing"
            echo "  --help, -h      Show this help message"
            echo ""
            echo "Environment variables (override defaults):"
            echo "  REPO_URL        Git repository URL"
            echo "  REPO_BRANCH     Git branch to clone (default: main)"
            echo "  DB_PASS         Database password (auto-generated if empty)"
            echo "  DOMAIN          Frontend domain (e.g., deschide.md)"
            echo "  API_DOMAIN      API domain (e.g., api.deschide.md)"
            exit 0
            ;;
        *)
            echo "Unknown option: $arg"
            echo "Use --help for usage information."
            exit 1
            ;;
    esac
done

# Set domain defaults based on environment
if [ "$ENV" = "production" ]; then
    DOMAIN="${DOMAIN:-deschide.md}"
    API_DOMAIN="${API_DOMAIN:-api.deschide.md}"
else
    DOMAIN="${DOMAIN:-staging.deschide.md}"
    API_DOMAIN="${API_DOMAIN:-api.staging.deschide.md}"
    FRONTEND_PORT=3006
fi

# Generate DB password if not provided
if [ -z "$DB_PASS" ]; then
    DB_PASS=$(openssl rand -base64 24 | tr -d '/+=' | head -c 32)
fi

# =============================================================================
# Colors & Logging
# =============================================================================

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

log_info()    { echo -e "${BLUE}[INFO]${NC}    $(date '+%H:%M:%S') $1"; }
log_success() { echo -e "${GREEN}[OK]${NC}      $(date '+%H:%M:%S') $1"; }
log_warning() { echo -e "${YELLOW}[WARN]${NC}    $(date '+%H:%M:%S') $1"; }
log_error()   { echo -e "${RED}[ERROR]${NC}   $(date '+%H:%M:%S') $1"; }
log_step()    { echo -e "\n${CYAN}${BOLD}[STEP]${NC}    $(date '+%H:%M:%S') $1\n"; }
log_dry()     { echo -e "${YELLOW}[DRY-RUN]${NC} $1"; }

# Execute or print (dry-run aware)
run_cmd() {
    if [ "$DRY_RUN" = true ]; then
        log_dry "$*"
    else
        "$@"
    fi
}

run_shell() {
    if [ "$DRY_RUN" = true ]; then
        log_dry "$*"
    else
        eval "$@"
    fi
}

# =============================================================================
# Pre-flight Checks
# =============================================================================

echo ""
echo -e "${BLUE}============================================================================${NC}"
echo -e "${BLUE}   DESCHIDE NEWS — SERVER PROVISIONING${NC}"
echo -e "${BLUE}============================================================================${NC}"
echo ""
echo -e "  Environment:  ${BOLD}$ENV${NC}"
echo -e "  Domain:       ${BOLD}$DOMAIN${NC}"
echo -e "  API Domain:   ${BOLD}$API_DOMAIN${NC}"
echo -e "  App Dir:      ${BOLD}$APP_DIR${NC}"
echo -e "  PHP:          ${BOLD}$PHP_VERSION${NC}"
echo -e "  PostgreSQL:   ${BOLD}$PG_VERSION${NC}"
echo -e "  Node.js:      ${BOLD}$NODE_VERSION${NC}"
echo -e "  Dry Run:      ${BOLD}$DRY_RUN${NC}"
echo ""
echo -e "${BLUE}----------------------------------------------------------------------------${NC}"

# Must run as root
if [ "$DRY_RUN" = false ] && [ "$(id -u)" -ne 0 ]; then
    log_error "This script must be run as root (use sudo)."
    exit 1
fi

# Check Ubuntu version
if [ "$DRY_RUN" = false ]; then
    if ! grep -q "24.04" /etc/os-release 2>/dev/null; then
        log_warning "This script is designed for Ubuntu 24.04. Your OS may differ."
        read -rp "Continue anyway? [y/N] " confirm
        if [ "$confirm" != "y" ] && [ "$confirm" != "Y" ]; then
            exit 1
        fi
    fi
fi

# =============================================================================
# STEP 1: System Setup
# =============================================================================

log_step "[1/13] System setup"

log_info "Updating package lists..."
run_cmd apt-get update -y

log_info "Upgrading existing packages..."
run_cmd apt-get upgrade -y

log_info "Installing essential packages..."
run_cmd apt-get install -y \
    curl \
    wget \
    git \
    unzip \
    zip \
    software-properties-common \
    apt-transport-https \
    ca-certificates \
    gnupg \
    lsb-release \
    htop \
    nano \
    jq \
    acl \
    build-essential

log_info "Setting timezone to Europe/Chisinau..."
run_cmd timedatectl set-timezone Europe/Chisinau

log_info "Configuring swap (2 GB)..."
if [ "$DRY_RUN" = false ]; then
    if [ ! -f /swapfile ]; then
        fallocate -l 2G /swapfile
        chmod 600 /swapfile
        mkswap /swapfile
        swapon /swapfile
        # Persist across reboots
        if ! grep -q '/swapfile' /etc/fstab; then
            echo '/swapfile none swap sw 0 0' >> /etc/fstab
        fi
        # Optimize swappiness for a web server
        sysctl vm.swappiness=10
        if ! grep -q 'vm.swappiness' /etc/sysctl.conf; then
            echo 'vm.swappiness=10' >> /etc/sysctl.conf
        fi
        log_success "Swap file created and enabled (2 GB)"
    else
        log_info "Swap file already exists, skipping."
    fi
else
    log_dry "fallocate -l 2G /swapfile && mkswap && swapon"
fi

log_info "Configuring UFW firewall..."
run_cmd apt-get install -y ufw
if [ "$DRY_RUN" = false ]; then
    ufw default deny incoming
    ufw default allow outgoing
    ufw allow 22/tcp comment 'SSH'
    ufw allow 80/tcp comment 'HTTP'
    ufw allow 443/tcp comment 'HTTPS'
    ufw --force enable
    log_success "UFW enabled (ports 22, 80, 443)"
else
    log_dry "ufw allow 22,80,443 && ufw --force enable"
fi

log_info "Installing and configuring fail2ban..."
run_cmd apt-get install -y fail2ban
if [ "$DRY_RUN" = false ]; then
    # Create local config to avoid overwriting on package updates
    cat > /etc/fail2ban/jail.local <<'FAIL2BAN'
[DEFAULT]
bantime  = 3600
findtime = 600
maxretry = 5
backend  = systemd

[sshd]
enabled = true
port    = ssh
filter  = sshd
maxretry = 3
FAIL2BAN
    systemctl enable fail2ban
    systemctl restart fail2ban
    log_success "fail2ban configured and running"
else
    log_dry "Configure fail2ban with SSH jail"
fi

log_success "System setup complete"

# =============================================================================
# STEP 2: PHP 8.5
# =============================================================================

log_step "[2/13] PHP ${PHP_VERSION}"

log_info "Adding ondrej/php PPA..."
run_cmd add-apt-repository -y ppa:ondrej/php
run_cmd apt-get update -y

log_info "Installing PHP ${PHP_VERSION} and extensions..."
run_cmd apt-get install -y \
    "php${PHP_VERSION}-fpm" \
    "php${PHP_VERSION}-pgsql" \
    "php${PHP_VERSION}-redis" \
    "php${PHP_VERSION}-intl" \
    "php${PHP_VERSION}-gd" \
    "php${PHP_VERSION}-mbstring" \
    "php${PHP_VERSION}-xml" \
    "php${PHP_VERSION}-curl" \
    "php${PHP_VERSION}-zip" \
    "php${PHP_VERSION}-apcu" \
    "php${PHP_VERSION}-opcache" \
    "php${PHP_VERSION}-amqp" \
    "php${PHP_VERSION}-bcmath" \
    "php${PHP_VERSION}-cli"

log_info "Creating PHP log directory..."
run_cmd mkdir -p /var/log/php
run_cmd chown "$APP_USER:$APP_GROUP" /var/log/php

log_info "Installing PHP-FPM pool configuration..."
if [ "$DRY_RUN" = false ]; then
    POOL_DIR="/etc/php/${PHP_VERSION}/fpm/pool.d"
    # Disable default pool
    if [ -f "${POOL_DIR}/www.conf" ]; then
        mv "${POOL_DIR}/www.conf" "${POOL_DIR}/www.conf.disabled"
    fi
    # Copy Deschide pool
    if [ -f "${SCRIPT_DIR}/php-fpm/deschide.conf" ]; then
        cp "${SCRIPT_DIR}/php-fpm/deschide.conf" "${POOL_DIR}/deschide.conf"
        log_success "PHP-FPM pool copied from scripts/php-fpm/deschide.conf"
    else
        log_warning "Pool config not found at ${SCRIPT_DIR}/php-fpm/deschide.conf"
        log_info "Generating default pool config..."
        cat > "${POOL_DIR}/deschide.conf" <<'PHPFPM'
[deschide]
user = www-data
group = www-data
listen = /run/php/php-fpm-deschide.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500
pm.process_idle_timeout = 10s

php_admin_value[error_log] = /var/log/php/deschide-fpm-error.log
php_admin_value[memory_limit] = 256M
php_admin_value[max_execution_time] = 30
php_admin_value[upload_max_filesize] = 15M
php_admin_value[post_max_size] = 20M
php_admin_value[expose_php] = Off

php_flag[display_errors] = Off
php_flag[log_errors] = On

env[APP_ENV] = prod
env[DATABASE_URL] = $DATABASE_URL
PHPFPM
    fi
else
    log_dry "Copy PHP-FPM pool config to /etc/php/${PHP_VERSION}/fpm/pool.d/"
fi

log_info "Installing OPcache production configuration..."
if [ "$DRY_RUN" = false ]; then
    OPCACHE_DIR="/etc/php/${PHP_VERSION}/mods-available"
    if [ -f "${SCRIPT_DIR}/php-fpm/opcache-prod.ini" ]; then
        cp "${SCRIPT_DIR}/php-fpm/opcache-prod.ini" "${OPCACHE_DIR}/opcache-deschide.ini"
        # Enable the module
        ln -sf "${OPCACHE_DIR}/opcache-deschide.ini" "/etc/php/${PHP_VERSION}/fpm/conf.d/99-opcache-deschide.ini"
        log_success "OPcache config copied from scripts/php-fpm/opcache-prod.ini"
    else
        log_warning "OPcache config not found, writing defaults..."
        cat > "${OPCACHE_DIR}/opcache-deschide.ini" <<'OPCACHE'
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.interned_strings_buffer=16
opcache.max_wasted_percentage=10
opcache.revalidate_freq=0
opcache.save_comments=1
opcache.enable_file_override=1
OPCACHE
        ln -sf "${OPCACHE_DIR}/opcache-deschide.ini" "/etc/php/${PHP_VERSION}/fpm/conf.d/99-opcache-deschide.ini"
    fi
else
    log_dry "Copy OPcache config to /etc/php/${PHP_VERSION}/mods-available/"
fi

log_info "Enabling and starting PHP-FPM..."
run_cmd systemctl enable "php${PHP_VERSION}-fpm"
run_cmd systemctl restart "php${PHP_VERSION}-fpm"

log_success "PHP ${PHP_VERSION} installed and configured"

# =============================================================================
# STEP 3: PostgreSQL 18
# =============================================================================

log_step "[3/13] PostgreSQL ${PG_VERSION}"

log_info "Adding PostgreSQL official repository..."
if [ "$DRY_RUN" = false ]; then
    install -d /usr/share/postgresql-common/pgdg
    curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc \
        | gpg --dearmor -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc
    echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] \
https://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" \
        > /etc/apt/sources.list.d/pgdg.list
    apt-get update -y
else
    log_dry "Add PostgreSQL APT repository"
fi

log_info "Installing PostgreSQL ${PG_VERSION}..."
run_cmd apt-get install -y "postgresql-${PG_VERSION}" "postgresql-client-${PG_VERSION}"

log_info "Creating database user and database..."
if [ "$DRY_RUN" = false ]; then
    # Create user if not exists
    sudo -u postgres psql -tc "SELECT 1 FROM pg_roles WHERE rolname='${DB_USER}'" \
        | grep -q 1 \
        || sudo -u postgres psql -c "CREATE USER ${DB_USER} WITH PASSWORD '${DB_PASS}';"

    # Create database if not exists
    sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname='${DB_NAME}'" \
        | grep -q 1 \
        || sudo -u postgres psql -c "CREATE DATABASE ${DB_NAME} OWNER ${DB_USER} ENCODING 'UTF8' LC_COLLATE='en_US.UTF-8' LC_CTYPE='en_US.UTF-8' TEMPLATE template0;"

    # Grant privileges
    sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE ${DB_NAME} TO ${DB_USER};"
    sudo -u postgres psql -d "${DB_NAME}" -c "GRANT ALL ON SCHEMA public TO ${DB_USER};"

    log_success "Database '${DB_NAME}' created with user '${DB_USER}'"
else
    log_dry "CREATE USER ${DB_USER}; CREATE DATABASE ${DB_NAME};"
fi

log_info "Configuring pg_hba.conf for local connections..."
if [ "$DRY_RUN" = false ]; then
    PG_HBA="/etc/postgresql/${PG_VERSION}/main/pg_hba.conf"
    # Ensure md5 auth for local TCP connections
    if ! grep -q "deschide_user" "$PG_HBA" 2>/dev/null; then
        # Add before the first "host" line
        sed -i "/^# IPv4 local connections/a host    ${DB_NAME}    ${DB_USER}    127.0.0.1/32    scram-sha-256" "$PG_HBA"
    fi
    systemctl restart "postgresql@${PG_VERSION}-main"
    log_success "pg_hba.conf configured"
else
    log_dry "Add scram-sha-256 entry to pg_hba.conf"
fi

log_success "PostgreSQL ${PG_VERSION} installed and configured"

# =============================================================================
# STEP 4: Redis 7
# =============================================================================

log_step "[4/13] Redis"

log_info "Installing Redis..."
run_cmd apt-get install -y redis-server

log_info "Configuring Redis..."
if [ "$DRY_RUN" = false ]; then
    REDIS_CONF="/etc/redis/redis.conf"
    # Set maxmemory
    sed -i 's/^# maxmemory .*/maxmemory 512mb/' "$REDIS_CONF"
    if ! grep -q '^maxmemory 512mb' "$REDIS_CONF"; then
        echo 'maxmemory 512mb' >> "$REDIS_CONF"
    fi
    # Set eviction policy
    sed -i 's/^# maxmemory-policy .*/maxmemory-policy allkeys-lru/' "$REDIS_CONF"
    if ! grep -q '^maxmemory-policy allkeys-lru' "$REDIS_CONF"; then
        echo 'maxmemory-policy allkeys-lru' >> "$REDIS_CONF"
    fi
    # Bind to localhost only
    sed -i 's/^bind .*/bind 127.0.0.1 ::1/' "$REDIS_CONF"

    systemctl enable redis-server
    systemctl restart redis-server
    log_success "Redis configured (512 MB, allkeys-lru)"
else
    log_dry "Configure Redis: maxmemory 512mb, allkeys-lru"
fi

log_success "Redis installed and configured"

# =============================================================================
# STEP 5: Elasticsearch 9.x
# =============================================================================

log_step "[5/13] Elasticsearch"

log_info "Adding Elasticsearch APT repository..."
if [ "$DRY_RUN" = false ]; then
    curl -fsSL https://artifacts.elastic.co/GPG-KEY-elasticsearch \
        | gpg --dearmor -o /usr/share/keyrings/elasticsearch-keyring.gpg
    echo "deb [signed-by=/usr/share/keyrings/elasticsearch-keyring.gpg] \
https://artifacts.elastic.co/packages/9.x/apt stable main" \
        > /etc/apt/sources.list.d/elastic-9.x.list
    apt-get update -y
else
    log_dry "Add Elasticsearch 9.x APT repository"
fi

log_info "Installing Elasticsearch..."
run_cmd apt-get install -y elasticsearch

log_info "Configuring Elasticsearch..."
if [ "$DRY_RUN" = false ]; then
    ES_CONF="/etc/elasticsearch/elasticsearch.yml"
    ES_JVM="/etc/elasticsearch/jvm.options.d/deschide.options"

    # Configure for single-node
    cat > "$ES_CONF" <<'ESCONF'
cluster.name: deschide-news
node.name: deschide-node-1
path.data: /var/lib/elasticsearch
path.logs: /var/log/elasticsearch
network.host: 127.0.0.1
http.port: 9200
discovery.type: single-node
xpack.security.enabled: true
xpack.security.http.ssl.enabled: false
xpack.security.transport.ssl.enabled: false
ESCONF

    # Set JVM heap to 2 GB
    mkdir -p /etc/elasticsearch/jvm.options.d
    cat > "$ES_JVM" <<'ESJVM'
-Xms2g
-Xmx2g
ESJVM

    systemctl enable elasticsearch
    systemctl start elasticsearch
    log_success "Elasticsearch configured (single-node, 2 GB heap)"
else
    log_dry "Configure Elasticsearch: single-node, 2 GB heap"
fi

log_success "Elasticsearch installed and configured"

# =============================================================================
# STEP 6: RabbitMQ
# =============================================================================

log_step "[6/13] RabbitMQ"

log_info "Installing RabbitMQ..."
run_cmd apt-get install -y rabbitmq-server

log_info "Enabling management plugin..."
if [ "$DRY_RUN" = false ]; then
    rabbitmq-plugins enable rabbitmq_management
    systemctl enable rabbitmq-server
    systemctl restart rabbitmq-server
    log_success "RabbitMQ management plugin enabled (http://localhost:15672)"
else
    log_dry "rabbitmq-plugins enable rabbitmq_management"
fi

log_success "RabbitMQ installed and configured"

# =============================================================================
# STEP 7: Node.js 20 + pnpm + PM2
# =============================================================================

log_step "[7/13] Node.js ${NODE_VERSION}, pnpm, PM2"

log_info "Adding NodeSource repository for Node.js ${NODE_VERSION}..."
if [ "$DRY_RUN" = false ]; then
    curl -fsSL "https://deb.nodesource.com/setup_${NODE_VERSION}.x" | bash -
else
    log_dry "Add NodeSource repository for Node.js ${NODE_VERSION}"
fi

log_info "Installing Node.js..."
run_cmd apt-get install -y nodejs

log_info "Installing pnpm globally..."
run_cmd npm install -g pnpm

log_info "Installing PM2 globally..."
run_cmd npm install -g pm2

log_info "Configuring PM2 startup (auto-start on reboot)..."
if [ "$DRY_RUN" = false ]; then
    # PM2 startup generates a systemd unit for the root user
    # which will resurrect saved processes on reboot
    pm2 startup systemd -u root --hp /root 2>/dev/null || true
    log_success "PM2 startup configured"
else
    log_dry "pm2 startup systemd"
fi

log_success "Node.js ${NODE_VERSION}, pnpm, and PM2 installed"

# =============================================================================
# STEP 8: Nginx
# =============================================================================

log_step "[8/13] Nginx"

log_info "Installing Nginx..."
run_cmd apt-get install -y nginx

log_info "Installing Nginx site configuration..."
if [ "$DRY_RUN" = false ]; then
    # Choose correct config based on environment
    if [ "$ENV" = "production" ]; then
        NGINX_SRC="${SCRIPT_DIR}/nginx/deschide.conf"
        NGINX_SITE="deschide.conf"
    else
        NGINX_SRC="${SCRIPT_DIR}/nginx/deschide-staging.conf"
        NGINX_SITE="deschide-staging.conf"
    fi

    if [ -f "$NGINX_SRC" ]; then
        cp "$NGINX_SRC" "/etc/nginx/sites-available/${NGINX_SITE}"
        ln -sf "/etc/nginx/sites-available/${NGINX_SITE}" "/etc/nginx/sites-enabled/${NGINX_SITE}"
        log_success "Nginx config copied from ${NGINX_SRC}"
    else
        log_warning "Nginx config not found at ${NGINX_SRC}; you must configure it manually."
    fi

    # Disable default site
    rm -f /etc/nginx/sites-enabled/default

    # Test configuration (will fail if SSL certs not yet in place, which is expected)
    if nginx -t 2>/dev/null; then
        systemctl enable nginx
        systemctl reload nginx
        log_success "Nginx configuration valid and reloaded"
    else
        log_warning "Nginx config test failed (expected before SSL certificates are obtained)."
        log_info "Nginx will be reloaded after Certbot obtains certificates."
        systemctl enable nginx
    fi
else
    log_dry "Copy Nginx site config and enable"
fi

log_success "Nginx installed and configured"

# =============================================================================
# STEP 9: Certbot (Let's Encrypt)
# =============================================================================

log_step "[9/13] Certbot (Let's Encrypt)"

log_info "Installing Certbot..."
run_cmd apt-get install -y certbot python3-certbot-nginx

if [ "$DRY_RUN" = false ]; then
    echo ""
    log_info "Certbot installed. To obtain SSL certificates, run:"
    echo ""
    echo -e "  ${BOLD}sudo certbot --nginx -d ${DOMAIN} -d ${API_DOMAIN}${NC}"
    echo ""
    if [ "$ENV" = "production" ]; then
        echo -e "  ${BOLD}sudo certbot --nginx -d ${DOMAIN} -d www.${DOMAIN} -d ${API_DOMAIN}${NC}"
    fi
    echo ""
    log_info "Certbot auto-renewal is configured via systemd timer."
else
    log_dry "apt-get install certbot python3-certbot-nginx"
fi

log_success "Certbot installed"

# =============================================================================
# STEP 10: Mercure Hub
# =============================================================================

log_step "[10/13] Mercure Hub"

log_info "Downloading Mercure binary v${MERCURE_VERSION}..."
if [ "$DRY_RUN" = false ]; then
    MERCURE_URL="https://github.com/dunglas/mercure/releases/download/v${MERCURE_VERSION}/mercure_Linux_x86_64.tar.gz"
    MERCURE_TMP=$(mktemp -d)
    curl -fsSL "$MERCURE_URL" -o "${MERCURE_TMP}/mercure.tar.gz"
    tar -xzf "${MERCURE_TMP}/mercure.tar.gz" -C "${MERCURE_TMP}"
    install -m 0755 "${MERCURE_TMP}/mercure" /usr/local/bin/mercure
    rm -rf "$MERCURE_TMP"
    log_success "Mercure binary installed to /usr/local/bin/mercure"
else
    log_dry "Download and install Mercure binary to /usr/local/bin/mercure"
fi

log_info "Creating Mercure configuration directory..."
run_cmd mkdir -p /etc/mercure

log_info "Installing Mercure Caddyfile..."
if [ "$DRY_RUN" = false ]; then
    cat > /etc/mercure/Caddyfile <<MERCURECFG
{
    order mercure after encode
    admin off
}

:3000 {
    mercure {
        publisher_jwt {env.MERCURE_PUBLISHER_JWT_KEY} HS256
        subscriber_jwt {env.MERCURE_SUBSCRIBER_JWT_KEY} HS256
        anonymous
        cors_origins https://${DOMAIN}
    }
    respond /healthz 200
    log {
        output stderr
    }
}
MERCURECFG
    log_success "Mercure Caddyfile written to /etc/mercure/Caddyfile"
else
    log_dry "Write Mercure Caddyfile to /etc/mercure/"
fi

log_info "Installing Mercure systemd service..."
if [ "$DRY_RUN" = false ]; then
    if [ -f "${SCRIPT_DIR}/systemd/deschide-mercure.service" ]; then
        cp "${SCRIPT_DIR}/systemd/deschide-mercure.service" /etc/systemd/system/deschide-mercure.service
        log_success "Mercure service copied from scripts/systemd/"
    else
        log_warning "Mercure service file not found, generating default..."
        cat > /etc/systemd/system/deschide-mercure.service <<'MERCURESVC'
[Unit]
Description=Deschide News Mercure Hub
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
ExecStart=/usr/local/bin/mercure run --config /etc/mercure/Caddyfile --adapter caddyfile
Restart=always
RestartSec=5
Environment=MERCURE_PUBLISHER_JWT_KEY=!ChangeThisMercureHubJWTSecretKey!
Environment=MERCURE_SUBSCRIBER_JWT_KEY=!ChangeThisMercureHubJWTSecretKey!
NoNewPrivileges=true
ProtectSystem=full
ProtectHome=true
PrivateTmp=true
LimitNOFILE=65536
MemoryMax=256M

[Install]
WantedBy=multi-user.target
MERCURESVC
    fi
    systemctl daemon-reload
else
    log_dry "Copy Mercure systemd service"
fi

log_success "Mercure installed and configured"

# =============================================================================
# STEP 11: Composer
# =============================================================================

log_step "[11/13] Composer"

log_info "Installing Composer 2.x..."
if [ "$DRY_RUN" = false ]; then
    COMPOSER_TMP=$(mktemp)
    curl -fsSL https://getcomposer.org/installer -o "$COMPOSER_TMP"
    php "$COMPOSER_TMP" --install-dir=/usr/local/bin --filename=composer
    rm -f "$COMPOSER_TMP"
    log_success "Composer installed: $(composer --version 2>/dev/null | head -1)"
else
    log_dry "Download and install Composer to /usr/local/bin/composer"
fi

log_success "Composer installed"

# =============================================================================
# STEP 12: Application Setup
# =============================================================================

log_step "[12/13] Application setup"

log_info "Creating application directory..."
run_cmd mkdir -p "$(dirname "$APP_DIR")"

log_info "Cloning repository..."
if [ "$DRY_RUN" = false ]; then
    if [ -d "$APP_DIR/.git" ]; then
        log_info "Repository already cloned, pulling latest..."
        git -C "$APP_DIR" fetch origin
        git -C "$APP_DIR" checkout "$REPO_BRANCH"
        git -C "$APP_DIR" pull origin "$REPO_BRANCH"
    else
        git clone --branch "$REPO_BRANCH" "$REPO_URL" "$APP_DIR"
    fi
    log_success "Repository cloned to $APP_DIR"
else
    log_dry "git clone --branch $REPO_BRANCH $REPO_URL $APP_DIR"
fi

log_info "Setting file permissions..."
if [ "$DRY_RUN" = false ]; then
    chown -R "$APP_USER:$APP_GROUP" "$APP_DIR"
    # Ensure writable directories for Symfony
    mkdir -p "$APP_DIR/apps/backend/var"
    mkdir -p "$APP_DIR/apps/backend/public/uploads"
    mkdir -p "$APP_DIR/backups"
    chmod -R 775 "$APP_DIR/apps/backend/var"
    chmod -R 775 "$APP_DIR/apps/backend/public/uploads"
    chmod -R 775 "$APP_DIR/backups"
    log_success "Permissions set (owner: $APP_USER)"
else
    log_dry "chown -R $APP_USER:$APP_GROUP $APP_DIR"
fi

log_info "Creating .env.local for backend..."
if [ "$DRY_RUN" = false ]; then
    BACKEND_ENV="$APP_DIR/apps/backend/.env.local"
    JWT_SECRET=$(openssl rand -hex 32)
    REVALIDATE_SECRET=$(openssl rand -hex 24)

    cat > "$BACKEND_ENV" <<ENVFILE
###> symfony/framework-bundle ###
APP_ENV=prod
APP_SECRET=$(openssl rand -hex 16)
###< symfony/framework-bundle ###

###> doctrine/doctrine-bundle ###
DATABASE_URL="postgresql://${DB_USER}:${DB_PASS}@127.0.0.1:5432/${DB_NAME}?serverVersion=${PG_VERSION}&charset=utf8"
###< doctrine/doctrine-bundle ###

###> lexik/jwt-authentication-bundle ###
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=${JWT_SECRET}
###< lexik/jwt-authentication-bundle ###

###> symfony/messenger ###
MESSENGER_TRANSPORT_DSN=amqp://guest:guest@localhost:5672/%2f/deschide_news_messages
###< symfony/messenger ###

###> redis ###
REDIS_URL=redis://localhost:6379/1
###< redis ###

###> elasticsearch ###
ELASTICSEARCH_URL=https://localhost:9200
###< elasticsearch ###

###> mercure ###
MERCURE_URL=http://localhost:3000/.well-known/mercure
MERCURE_PUBLIC_URL=https://${DOMAIN}/.well-known/mercure
MERCURE_JWT_SECRET=!ChangeThisMercureHubJWTSecretKey!
###< mercure ###

###> nelmio/cors-bundle ###
CORS_ALLOW_ORIGIN='^https://${DOMAIN}$'
###< nelmio/cors-bundle ###

###> frontend revalidation ###
FRONTEND_REVALIDATE_URL=http://localhost:${FRONTEND_PORT}/api/revalidate
FRONTEND_REVALIDATE_SECRET=${REVALIDATE_SECRET}
###< frontend revalidation ###
ENVFILE
    chown "$APP_USER:$APP_GROUP" "$BACKEND_ENV"
    chmod 640 "$BACKEND_ENV"
    log_success "Backend .env.local created"
else
    log_dry "Write backend .env.local"
fi

log_info "Creating .env.local for frontend..."
if [ "$DRY_RUN" = false ]; then
    FRONTEND_ENV="$APP_DIR/apps/frontend/.env.local"
    cat > "$FRONTEND_ENV" <<FENVFILE
PORT=${FRONTEND_PORT}
NEXT_PUBLIC_API_URL=https://${API_DOMAIN}
NEXT_PUBLIC_CDN_URL=https://${API_DOMAIN}
NEXT_PUBLIC_MERCURE_URL=https://${DOMAIN}/.well-known/mercure
NEXT_PUBLIC_DEFAULT_LOCALE=ro
NEXT_PUBLIC_AVAILABLE_LOCALES=ro,en,ru
REVALIDATE_SECRET=${REVALIDATE_SECRET}
FENVFILE
    chown "$APP_USER:$APP_GROUP" "$FRONTEND_ENV"
    chmod 640 "$FRONTEND_ENV"
    log_success "Frontend .env.local created"
else
    log_dry "Write frontend .env.local"
fi

log_info "Installing backend dependencies (composer install --no-dev)..."
run_shell "cd '$APP_DIR/apps/backend' && sudo -u $APP_USER composer install --no-dev --optimize-autoloader --no-interaction --classmap-authoritative"

log_info "Generating JWT keypair..."
if [ "$DRY_RUN" = false ]; then
    cd "$APP_DIR/apps/backend"
    sudo -u "$APP_USER" php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction 2>/dev/null || true
    log_success "JWT keypair generated"
else
    log_dry "php bin/console lexik:jwt:generate-keypair"
fi

log_info "Running database migrations..."
run_shell "cd '$APP_DIR/apps/backend' && sudo -u $APP_USER php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration"

log_info "Warming up production cache..."
run_shell "cd '$APP_DIR/apps/backend' && sudo -u $APP_USER php bin/console cache:clear --env=prod --no-debug"
run_shell "cd '$APP_DIR/apps/backend' && sudo -u $APP_USER php bin/console cache:warmup --env=prod --no-debug"

log_info "Installing frontend dependencies (pnpm install)..."
run_shell "cd '$APP_DIR/apps/frontend' && sudo -u $APP_USER pnpm install --frozen-lockfile"

log_info "Building frontend production bundle..."
run_shell "cd '$APP_DIR/apps/frontend' && sudo -u $APP_USER pnpm build"

log_info "Starting frontend with PM2..."
if [ "$DRY_RUN" = false ]; then
    cd "$APP_DIR/apps/frontend"
    if [ -f ecosystem.config.js ]; then
        sudo -u "$APP_USER" pm2 start ecosystem.config.js --env production 2>/dev/null || true
    else
        sudo -u "$APP_USER" pm2 start "pnpm start" --name "deschide-frontend" 2>/dev/null || true
    fi
    pm2 save 2>/dev/null || true
    log_success "Frontend started via PM2"
else
    log_dry "pm2 start ecosystem.config.js --env production"
fi

log_info "Installing systemd service units..."
if [ "$DRY_RUN" = false ]; then
    SYSTEMD_SRC="$APP_DIR/scripts/systemd"
    # Fall back to script directory if repo scripts dir exists
    if [ ! -d "$SYSTEMD_SRC" ]; then
        SYSTEMD_SRC="${SCRIPT_DIR}/systemd"
    fi

    for unit in deschide-messenger@.service deschide-scheduler.service deschide-scheduler.timer deschide-mercure.service; do
        if [ -f "${SYSTEMD_SRC}/${unit}" ]; then
            cp "${SYSTEMD_SRC}/${unit}" "/etc/systemd/system/${unit}"
            log_info "Installed ${unit}"
        fi
    done

    systemctl daemon-reload

    # Enable messenger workers (2 instances)
    systemctl enable deschide-messenger@{1..2}
    systemctl start deschide-messenger@{1..2}

    # Enable scheduler timer
    systemctl enable deschide-scheduler.timer
    systemctl start deschide-scheduler.timer

    # Enable Mercure
    systemctl enable deschide-mercure
    systemctl start deschide-mercure

    log_success "Systemd services enabled and started"
else
    log_dry "Install and enable systemd units (messenger, scheduler, mercure)"
fi

log_info "Installing logrotate configuration..."
if [ "$DRY_RUN" = false ]; then
    LOGROTATE_SRC="$APP_DIR/scripts/logrotate/deschide"
    if [ ! -f "$LOGROTATE_SRC" ]; then
        LOGROTATE_SRC="${SCRIPT_DIR}/logrotate/deschide"
    fi
    if [ -f "$LOGROTATE_SRC" ]; then
        cp "$LOGROTATE_SRC" /etc/logrotate.d/deschide
        log_success "Logrotate config installed"
    else
        log_warning "Logrotate config not found"
    fi
else
    log_dry "Copy logrotate config to /etc/logrotate.d/deschide"
fi

log_info "Installing cron jobs..."
if [ "$DRY_RUN" = false ]; then
    CRON_SRC="$APP_DIR/scripts/cron/backup-database.cron"
    if [ ! -f "$CRON_SRC" ]; then
        CRON_SRC="${SCRIPT_DIR}/cron/backup-database.cron"
    fi
    if [ -f "$CRON_SRC" ]; then
        cp "$CRON_SRC" /etc/cron.d/deschide-db-backup
        chmod 644 /etc/cron.d/deschide-db-backup
        log_success "Database backup cron job installed"
    fi
else
    log_dry "Install cron jobs"
fi

log_info "Creating required log directories..."
run_cmd mkdir -p /var/log/php /var/log/pm2 /var/log/deschide
run_cmd chown "$APP_USER:$APP_GROUP" /var/log/php /var/log/pm2 /var/log/deschide

log_success "Application setup complete"

# =============================================================================
# STEP 13: Verification
# =============================================================================

log_step "[13/13] Verification"

if [ "$DRY_RUN" = true ]; then
    log_dry "Check all services and run smoke tests"
else
    PASS=0
    FAIL=0

    check_running() {
        local name="$1"
        local check="$2"
        if eval "$check" > /dev/null 2>&1; then
            log_success "$name is running"
            ((PASS++))
        else
            log_error "$name is NOT running"
            ((FAIL++))
        fi
    }

    check_running "PHP-FPM"       "systemctl is-active php${PHP_VERSION}-fpm"
    check_running "PostgreSQL"     "pg_isready -h localhost -p 5432"
    check_running "Redis"          "redis-cli -p 6379 PING 2>/dev/null | grep -q PONG"
    check_running "Elasticsearch"  "systemctl is-active elasticsearch"
    check_running "RabbitMQ"       "systemctl is-active rabbitmq-server"
    check_running "Nginx"          "systemctl is-active nginx"
    check_running "Mercure"        "systemctl is-active deschide-mercure"
    check_running "Messenger @1"   "systemctl is-active deschide-messenger@1"
    check_running "Messenger @2"   "systemctl is-active deschide-messenger@2"
    check_running "Scheduler"      "systemctl is-active deschide-scheduler.timer"

    # PM2 check
    if pm2 list 2>/dev/null | grep -q "online"; then
        log_success "PM2 frontend is running"
        ((PASS++))
    else
        log_error "PM2 frontend is NOT running"
        ((FAIL++))
    fi

    echo ""
    log_info "Service check results: ${PASS} running, ${FAIL} failed"

    # Smoke test API (only if Nginx is configured with SSL)
    echo ""
    log_info "Attempting smoke tests..."

    # Test backend via localhost
    HTTP_CODE=$(curl -sf -o /dev/null -w "%{http_code}" --max-time 10 \
        "http://127.0.0.1:${FRONTEND_PORT}" 2>/dev/null || echo "000")
    if [ "$HTTP_CODE" = "200" ]; then
        log_success "Frontend smoke test passed (HTTP ${HTTP_CODE})"
    else
        log_warning "Frontend smoke test: HTTP ${HTTP_CODE} (may need Certbot/DNS first)"
    fi
fi

# =============================================================================
# Summary
# =============================================================================

PROVISION_END=$(date +%s)
PROVISION_DURATION=$((PROVISION_END - PROVISION_START))
PROVISION_MINUTES=$((PROVISION_DURATION / 60))
PROVISION_SECONDS=$((PROVISION_DURATION % 60))

echo ""
echo -e "${BLUE}============================================================================${NC}"
echo -e "${BLUE}   PROVISIONING SUMMARY${NC}"
echo -e "${BLUE}============================================================================${NC}"
echo ""
echo -e "  Status:       ${GREEN}${BOLD}COMPLETE${NC}"
echo -e "  Environment:  ${BOLD}$ENV${NC}"
echo -e "  Duration:     ${BOLD}${PROVISION_MINUTES}m ${PROVISION_SECONDS}s${NC}"
echo ""
echo -e "  ${BOLD}Installed Services:${NC}"
echo -e "    PHP:            ${PHP_VERSION}-fpm"
echo -e "    PostgreSQL:     ${PG_VERSION}"
echo -e "    Redis:          7.x (512 MB, allkeys-lru)"
echo -e "    Elasticsearch:  9.x (2 GB heap, single-node)"
echo -e "    RabbitMQ:       with management plugin"
echo -e "    Node.js:        ${NODE_VERSION}.x + pnpm + PM2"
echo -e "    Nginx:          reverse proxy"
echo -e "    Mercure:        v${MERCURE_VERSION}"
echo -e "    Composer:       2.x"
echo -e "    Certbot:        installed (run manually)"
echo ""
echo -e "  ${BOLD}Application:${NC}"
echo -e "    Directory:      ${APP_DIR}"
echo -e "    Frontend Port:  ${FRONTEND_PORT}"
echo -e "    Domain:         ${DOMAIN}"
echo -e "    API Domain:     ${API_DOMAIN}"
echo ""
echo -e "  ${BOLD}Database:${NC}"
echo -e "    Name:           ${DB_NAME}"
echo -e "    User:           ${DB_USER}"
echo -e "    Password:       ${DB_PASS}"
echo ""
echo -e "  ${YELLOW}${BOLD}IMPORTANT: Save the database password above!${NC}"
echo ""
echo -e "${BLUE}----------------------------------------------------------------------------${NC}"
echo ""
echo -e "  ${BOLD}Next Steps:${NC}"
echo ""
echo -e "  1. Point DNS for ${DOMAIN} and ${API_DOMAIN} to this server"
echo ""
echo -e "  2. Obtain SSL certificates:"
if [ "$ENV" = "production" ]; then
echo -e "     ${CYAN}sudo certbot --nginx -d ${DOMAIN} -d www.${DOMAIN} -d ${API_DOMAIN}${NC}"
else
echo -e "     ${CYAN}sudo certbot --nginx -d ${DOMAIN} -d ${API_DOMAIN}${NC}"
fi
echo ""
echo -e "  3. Reload Nginx after certificate installation:"
echo -e "     ${CYAN}sudo nginx -t && sudo systemctl reload nginx${NC}"
echo ""
echo -e "  4. Update Mercure JWT secrets in:"
echo -e "     ${CYAN}/etc/systemd/system/deschide-mercure.service${NC}"
echo -e "     ${CYAN}${APP_DIR}/apps/backend/.env.local${NC}"
echo ""
echo -e "  5. Create Elasticsearch indices:"
echo -e "     ${CYAN}cd ${APP_DIR}/apps/backend${NC}"
echo -e "     ${CYAN}sudo -u www-data php bin/console app:elasticsearch:create-index${NC}"
echo -e "     ${CYAN}sudo -u www-data php bin/console app:elasticsearch:create-image-index${NC}"
echo ""
echo -e "  6. Run deploy.sh for future updates:"
echo -e "     ${CYAN}${APP_DIR}/scripts/deploy.sh${NC}"
echo ""
echo -e "${BLUE}============================================================================${NC}"
echo ""

exit 0
