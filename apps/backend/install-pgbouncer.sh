#!/bin/bash

# Automated PgBouncer Installation Script
# For Ubuntu 24.04 LTS with PostgreSQL 17.6

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Error: This script must be run as root (use sudo)${NC}"
    exit 1
fi

echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║     PgBouncer Installation - Deschide News Backend        ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Configuration
DB_USER="deschide_admin"
DB_PASSWORD="sr324395"
CONFIG_SOURCE="/var/www/deschide_news_app/deschide_backend/pgbouncer.ini"
CONFIG_DEST="/etc/pgbouncer/pgbouncer.ini"
USERLIST_FILE="/etc/pgbouncer/userlist.txt"

# ============================================================================
# Step 1: Install PgBouncer
# ============================================================================

echo -e "${YELLOW}[Step 1/6]${NC} Installing PgBouncer..."

if command -v pgbouncer &> /dev/null; then
    PGBOUNCER_VERSION=$(pgbouncer --version 2>&1 | head -1)
    echo -e "${GREEN}✓${NC} PgBouncer already installed: ${PGBOUNCER_VERSION}"
else
    apt update -qq
    apt install -y pgbouncer > /dev/null 2>&1
    PGBOUNCER_VERSION=$(pgbouncer --version 2>&1 | head -1)
    echo -e "${GREEN}✓${NC} PgBouncer installed: ${PGBOUNCER_VERSION}"
fi

echo ""

# ============================================================================
# Step 2: Backup original configuration
# ============================================================================

echo -e "${YELLOW}[Step 2/6]${NC} Backing up original configuration..."

if [ -f "${CONFIG_DEST}" ]; then
    if [ ! -f "${CONFIG_DEST}.backup" ]; then
        cp "${CONFIG_DEST}" "${CONFIG_DEST}.backup"
        echo -e "${GREEN}✓${NC} Original config backed up to ${CONFIG_DEST}.backup"
    else
        echo -e "${YELLOW}⚠${NC} Backup already exists"
    fi
fi

echo ""

# ============================================================================
# Step 3: Install PgBouncer configuration
# ============================================================================

echo -e "${YELLOW}[Step 3/6]${NC} Installing PgBouncer configuration..."

if [ -f "${CONFIG_SOURCE}" ]; then
    cp "${CONFIG_SOURCE}" "${CONFIG_DEST}"
    echo -e "${GREEN}✓${NC} Configuration installed"

    # Set correct permissions
    chown postgres:postgres "${CONFIG_DEST}"
    chmod 640 "${CONFIG_DEST}"
    echo -e "${GREEN}✓${NC} Permissions set (640, postgres:postgres)"
else
    echo -e "${RED}✗${NC} Configuration source file not found: ${CONFIG_SOURCE}"
    exit 1
fi

echo ""

# ============================================================================
# Step 4: Create userlist.txt with MD5 authentication
# ============================================================================

echo -e "${YELLOW}[Step 4/6]${NC} Creating authentication file..."

# Generate MD5 hash for user password
# Format: md5 + md5(password + username)
MD5_HASH=$(echo -n "${DB_PASSWORD}${DB_USER}" | md5sum | awk '{print "md5"$1}')

# Create userlist.txt
cat > "${USERLIST_FILE}" << EOF
"${DB_USER}" "${MD5_HASH}"
"postgres" "md5$(echo -n "postgrespassword" | md5sum | awk '{print $1}')"
EOF

# Set correct permissions
chown postgres:postgres "${USERLIST_FILE}"
chmod 600 "${USERLIST_FILE}"

echo -e "${GREEN}✓${NC} Authentication file created"
echo -e "${GREEN}✓${NC} User added: ${DB_USER}"
echo -e "${GREEN}✓${NC} Permissions set (600, postgres:postgres)"

echo ""

# ============================================================================
# Step 5: Create log file
# ============================================================================

echo -e "${YELLOW}[Step 5/6]${NC} Setting up logging..."

LOG_FILE="/var/log/postgresql/pgbouncer.log"
touch "${LOG_FILE}"
chown postgres:postgres "${LOG_FILE}"
chmod 640 "${LOG_FILE}"

echo -e "${GREEN}✓${NC} Log file created: ${LOG_FILE}"

echo ""

# ============================================================================
# Step 6: Start PgBouncer service
# ============================================================================

echo -e "${YELLOW}[Step 6/6]${NC} Starting PgBouncer service..."

# Stop if running
if systemctl is-active --quiet pgbouncer; then
    systemctl stop pgbouncer
    echo "  Stopped existing PgBouncer service"
fi

# Enable on boot
systemctl enable pgbouncer > /dev/null 2>&1

# Start service
systemctl start pgbouncer
sleep 2

# Verify it's running
if systemctl is-active --quiet pgbouncer; then
    echo -e "${GREEN}✓${NC} PgBouncer service started successfully"
else
    echo -e "${RED}✗${NC} Failed to start PgBouncer service"
    echo "  Check logs: sudo journalctl -u pgbouncer -n 50"
    exit 1
fi

# Verify port binding
sleep 1
if ss -tulpn | grep -q ":6432"; then
    echo -e "${GREEN}✓${NC} PgBouncer is listening on port 6432"
else
    echo -e "${RED}✗${NC} PgBouncer is NOT listening on port 6432"
    exit 1
fi

echo ""

# ============================================================================
# Installation Complete
# ============================================================================

echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║              Installation Complete! ✓                      ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${GREEN}PgBouncer is now running!${NC}"
echo ""
echo "Configuration:"
echo "  - PgBouncer port:  6432"
echo "  - PostgreSQL port: 5432"
echo "  - Database:        deschide"
echo "  - User:            ${DB_USER}"
echo "  - Pool mode:       transaction"
echo "  - Pool size:       25 connections"
echo "  - Max clients:     1000"
echo ""
echo "Quick commands:"
echo "  - Test connection: PGPASSWORD=${DB_PASSWORD} psql -h 127.0.0.1 -p 6432 -U ${DB_USER} -d deschide -c 'SELECT 1'"
echo "  - Admin console:   psql -h 127.0.0.1 -p 6432 -U postgres -d pgbouncer"
echo "  - Show pools:      psql -h 127.0.0.1 -p 6432 -U postgres -d pgbouncer -c 'SHOW POOLS'"
echo "  - View logs:       sudo tail -f /var/log/postgresql/pgbouncer.log"
echo "  - Restart:         sudo systemctl restart pgbouncer"
echo "  - Status:          sudo systemctl status pgbouncer"
echo ""
echo "Next steps:"
echo "  1. Test PgBouncer connection (see command above)"
echo "  2. Update Symfony .env.local to use port 6432"
echo "  3. Clear Symfony cache: symfony console cache:clear"
echo "  4. Test Symfony connection: symfony console dbal:run-sql 'SELECT 1'"
echo "  5. Monitor pools: psql -h 127.0.0.1 -p 6432 -U postgres -d pgbouncer -c 'SHOW POOLS'"
echo ""
