#!/usr/bin/env bash
# =============================================================================
# Deschide News — Start All Services (WSL2)
# Usage: ./scripts/start-all-services.sh [--stop] [--status]
# =============================================================================

set -euo pipefail

ROOT_DIR="/var/www/deschide_news_app"
BACKEND_DIR="$ROOT_DIR/apps/backend"
FRONTEND_DIR="$ROOT_DIR/apps/frontend"
MERCURE_CADDYFILE="/tmp/Caddyfile.mercure"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status() {
    local name="$1" port="$2" status="$3"
    if [ "$status" = "OK" ]; then
        printf "  ${GREEN}✓${NC} %-25s port %-6s ${GREEN}%s${NC}\n" "$name" "$port" "$status"
    else
        printf "  ${RED}✗${NC} %-25s port %-6s ${RED}%s${NC}\n" "$name" "$port" "$status"
    fi
}

check_port() {
    ss -tulpn 2>/dev/null | grep -q ":$1 " && echo "OK" || echo "DOWN"
}

# --status: just show status
if [ "${1:-}" = "--status" ]; then
    echo ""
    echo -e "${BLUE}╔══════════════════════════════════════════════╗${NC}"
    echo -e "${BLUE}║     DESCHIDE NEWS — SERVICE STATUS           ║${NC}"
    echo -e "${BLUE}╚══════════════════════════════════════════════╝${NC}"
    echo ""
    print_status "PostgreSQL"        "5432"  "$(check_port 5432)"
    print_status "Redis"             "6379"  "$(check_port 6379)"
    print_status "Elasticsearch"     "9200"  "$(check_port 9200)"
    print_status "RabbitMQ"          "5672"  "$(check_port 5672)"
    print_status "RabbitMQ Mgmt"     "15672" "$(check_port 15672)"
    print_status "Symfony Backend"   "8081"  "$(check_port 8081)"
    print_status "Next.js Frontend"  "3005"  "$(check_port 3005)"
    print_status "CDN Static"        "8082"  "$(check_port 8082)"
    print_status "Mercure Hub"       "3000"  "$(check_port 3000)"
    echo ""
    ACTIVE=$(ss -tulpn 2>/dev/null | grep -cE ":(5432|6379|9200|5672|15672|8081|3005|8082|3000) " || true)
    echo -e "  Active: ${GREEN}${ACTIVE}/9${NC} services"
    echo ""
    exit 0
fi

# --stop: stop everything
if [ "${1:-}" = "--stop" ]; then
    echo -e "${YELLOW}Stopping all services...${NC}"

    # Frontend
    pkill -f "next dev" 2>/dev/null && echo "  Stopped Next.js" || true

    # CDN
    pkill -f "http.server 8082" 2>/dev/null && echo "  Stopped CDN" || true

    # Mercure
    pkill -f "mercure run" 2>/dev/null && echo "  Stopped Mercure" || true

    # Symfony
    cd "$BACKEND_DIR" && symfony server:stop 2>/dev/null && echo "  Stopped Symfony" || true

    # System services
    sudo service rabbitmq-server stop 2>/dev/null && echo "  Stopped RabbitMQ" || true
    sudo -u elasticsearch kill "$(cat /tmp/elasticsearch.pid 2>/dev/null)" 2>/dev/null && echo "  Stopped Elasticsearch" || true
    sudo service redis-server stop 2>/dev/null && echo "  Stopped Redis" || true
    sudo service postgresql stop 2>/dev/null && echo "  Stopped PostgreSQL" || true

    echo -e "${GREEN}All services stopped.${NC}"
    exit 0
fi

# Default: start everything
echo ""
echo -e "${BLUE}╔══════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║     DESCHIDE NEWS — STARTING SERVICES        ║${NC}"
echo -e "${BLUE}╚══════════════════════════════════════════════╝${NC}"
echo ""

# 1. PostgreSQL
echo -e "${YELLOW}[1/8] PostgreSQL...${NC}"
if [ "$(check_port 5432)" = "DOWN" ]; then
    sudo service postgresql start >/dev/null 2>&1
    sleep 1
fi
print_status "PostgreSQL" "5432" "$(check_port 5432)"

# 2. Redis
echo -e "${YELLOW}[2/8] Redis...${NC}"
if [ "$(check_port 6379)" = "DOWN" ]; then
    sudo service redis-server start >/dev/null 2>&1
    sleep 1
fi
print_status "Redis" "6379" "$(check_port 6379)"

# 3. Elasticsearch
echo -e "${YELLOW}[3/8] Elasticsearch...${NC}"
if [ "$(check_port 9200)" = "DOWN" ]; then
    sudo -u elasticsearch /usr/share/elasticsearch/bin/elasticsearch -d -p /tmp/elasticsearch.pid >/dev/null 2>&1
    # ES takes a few seconds to start
    for i in {1..15}; do
        [ "$(check_port 9200)" = "OK" ] && break
        sleep 2
    done
fi
print_status "Elasticsearch" "9200" "$(check_port 9200)"

# 4. RabbitMQ
echo -e "${YELLOW}[4/8] RabbitMQ...${NC}"
if [ "$(check_port 5672)" = "DOWN" ]; then
    sudo service rabbitmq-server start >/dev/null 2>&1
    sleep 2
fi
print_status "RabbitMQ" "5672" "$(check_port 5672)"

# 5. Symfony Backend
echo -e "${YELLOW}[5/8] Symfony Backend...${NC}"
if [ "$(check_port 8081)" = "DOWN" ]; then
    cd "$BACKEND_DIR" && symfony serve -d --port=8081 >/dev/null 2>&1
    sleep 1
fi
print_status "Symfony Backend" "8081" "$(check_port 8081)"

# 6. Next.js Frontend
echo -e "${YELLOW}[6/8] Next.js Frontend...${NC}"
if [ "$(check_port 3005)" = "DOWN" ]; then
    cd "$FRONTEND_DIR" && nohup pnpm dev > /tmp/nextjs-frontend.log 2>&1 &
    # Wait for Next.js to compile
    for i in {1..20}; do
        [ "$(check_port 3005)" = "OK" ] && break
        sleep 2
    done
fi
print_status "Next.js Frontend" "3005" "$(check_port 3005)"

# 7. CDN Static Server
echo -e "${YELLOW}[7/8] CDN Static Server...${NC}"
if [ "$(check_port 8082)" = "DOWN" ]; then
    cd "$BACKEND_DIR/public" && nohup python3 -m http.server 8082 --bind 127.0.0.1 > /tmp/cdn-static.log 2>&1 &
    sleep 1
fi
print_status "CDN Static" "8082" "$(check_port 8082)"

# 8. Mercure Hub
echo -e "${YELLOW}[8/8] Mercure Hub...${NC}"
if [ "$(check_port 3000)" = "DOWN" ]; then
    # Create Caddyfile if not exists
    if [ ! -f "$MERCURE_CADDYFILE" ]; then
        cat > "$MERCURE_CADDYFILE" << 'CADDY'
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
            cors_origins http://localhost:3005 http://127.0.0.1:3005
        }
        respond "Mercure Hub"
    }
}
CADDY
    fi
    nohup /usr/local/bin/mercure run --config "$MERCURE_CADDYFILE" --adapter caddyfile > /tmp/mercure.log 2>&1 &
    sleep 2
fi
print_status "Mercure Hub" "3000" "$(check_port 3000)"

# Summary
echo ""
echo -e "${BLUE}────────────────────────────────────────────────${NC}"
ACTIVE=$(ss -tulpn 2>/dev/null | grep -cE ":(5432|6379|9200|5672|15672|8081|3005|8082|3000) " || true)
echo -e "  Active: ${GREEN}${ACTIVE}/9${NC} services"
echo ""
echo -e "  ${BLUE}Frontend${NC}:  http://localhost:3005"
echo -e "  ${BLUE}Backend${NC}:   http://127.0.0.1:8081/api"
echo -e "  ${BLUE}CDN${NC}:       http://127.0.0.1:8082/uploads/"
echo -e "  ${BLUE}RabbitMQ${NC}:  http://localhost:15672 (guest/guest)"
echo ""
