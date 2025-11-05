#!/bin/bash

# PgBouncer Authentication Fix Script
# Switches from MD5 to SCRAM-SHA-256 authentication for PostgreSQL 17

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
echo -e "${BLUE}║     PgBouncer Authentication Fix - SCRAM-SHA-256         ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Configuration
USERLIST_FILE="/etc/pgbouncer/userlist.txt"
CONFIG_FILE="/etc/pgbouncer/pgbouncer.ini"
DB_USER="deschide_admin"
DB_PASSWORD="sr324395"

# ============================================================================
# Step 1: Extract SCRAM-SHA-256 hashes from PostgreSQL
# ============================================================================

echo -e "${YELLOW}[Step 1/5]${NC} Extracting password hashes from PostgreSQL..."

# Extract hashes for deschide_admin and postgres users
TEMP_USERLIST="/tmp/pgbouncer_userlist_scram.txt"

sudo -u postgres psql -Atc "
    SELECT '\"' || usename || '\" \"' || passwd || '\"'
    FROM pg_shadow
    WHERE usename IN ('deschide_admin', 'postgres')
" > "$TEMP_USERLIST" 2>/dev/null

if [ ! -s "$TEMP_USERLIST" ]; then
    echo -e "${RED}✗${NC} Failed to extract password hashes"
    echo "  Trying alternative method..."

    # Alternative: Query pg_authid instead
    sudo -u postgres psql -Atc "
        SELECT '\"' || rolname || '\" \"' || rolpassword || '\"'
        FROM pg_authid
        WHERE rolname IN ('deschide_admin', 'postgres')
    " > "$TEMP_USERLIST" 2>/dev/null
fi

if [ ! -s "$TEMP_USERLIST" ]; then
    echo -e "${RED}✗${NC} Could not extract password hashes from PostgreSQL"
    echo "  Manual extraction needed"
    exit 1
fi

# Verify SCRAM format
if grep -q "SCRAM-SHA-256" "$TEMP_USERLIST"; then
    echo -e "${GREEN}✓${NC} SCRAM-SHA-256 hashes extracted successfully"
    echo "  Preview:"
    head -2 "$TEMP_USERLIST" | sed 's/SCRAM-SHA-256\$[^"]*/"SCRAM-SHA-256$***"/'
else
    echo -e "${RED}✗${NC} Hashes are not in SCRAM-SHA-256 format"
    echo "  Check PostgreSQL password_encryption setting"
    exit 1
fi

echo ""

# ============================================================================
# Step 2: Backup current userlist.txt
# ============================================================================

echo -e "${YELLOW}[Step 2/5]${NC} Backing up current userlist..."

if [ -f "$USERLIST_FILE" ]; then
    cp "$USERLIST_FILE" "${USERLIST_FILE}.md5.backup"
    echo -e "${GREEN}✓${NC} Current userlist backed up to ${USERLIST_FILE}.md5.backup"
else
    echo -e "${YELLOW}⚠${NC} No existing userlist found"
fi

echo ""

# ============================================================================
# Step 3: Install new userlist with SCRAM hashes
# ============================================================================

echo -e "${YELLOW}[Step 3/5]${NC} Installing new userlist with SCRAM-SHA-256 hashes..."

cp "$TEMP_USERLIST" "$USERLIST_FILE"
chown postgres:postgres "$USERLIST_FILE"
chmod 600 "$USERLIST_FILE"

echo -e "${GREEN}✓${NC} New userlist installed"
echo -e "${GREEN}✓${NC} Permissions set (600, postgres:postgres)"

# Cleanup
rm -f "$TEMP_USERLIST"

echo ""

# ============================================================================
# Step 4: Update pgbouncer.ini auth_type
# ============================================================================

echo -e "${YELLOW}[Step 4/5]${NC} Updating PgBouncer configuration..."

# Backup original config
if [ ! -f "${CONFIG_FILE}.backup" ]; then
    cp "$CONFIG_FILE" "${CONFIG_FILE}.backup"
    echo -e "${GREEN}✓${NC} Configuration backed up"
fi

# Change auth_type from md5 to scram-sha-256
if grep -q "auth_type = md5" "$CONFIG_FILE"; then
    sed -i 's/auth_type = md5/auth_type = scram-sha-256/' "$CONFIG_FILE"
    echo -e "${GREEN}✓${NC} Changed auth_type: md5 → scram-sha-256"
else
    echo -e "${YELLOW}⚠${NC} auth_type already set (not md5)"
fi

# Verify change
CURRENT_AUTH=$(grep "^auth_type" "$CONFIG_FILE" | awk '{print $3}')
echo "  Current auth_type: $CURRENT_AUTH"

echo ""

# ============================================================================
# Step 5: Restart PgBouncer service
# ============================================================================

echo -e "${YELLOW}[Step 5/5]${NC} Restarting PgBouncer service..."

systemctl restart pgbouncer
sleep 2

# Verify it's running
if systemctl is-active --quiet pgbouncer; then
    echo -e "${GREEN}✓${NC} PgBouncer service restarted successfully"
else
    echo -e "${RED}✗${NC} Failed to restart PgBouncer"
    echo "  Check logs: sudo journalctl -u pgbouncer -n 50"
    exit 1
fi

# Verify port binding
if ss -tulpn | grep -q ":6432"; then
    echo -e "${GREEN}✓${NC} PgBouncer is listening on port 6432"
else
    echo -e "${RED}✗${NC} PgBouncer is NOT listening on port 6432"
    exit 1
fi

echo ""

# ============================================================================
# Test Connection
# ============================================================================

echo -e "${YELLOW}[Test]${NC} Testing PgBouncer connection with SCRAM-SHA-256..."

if PGPASSWORD=${DB_PASSWORD} psql -h 127.0.0.1 -p 6432 -U ${DB_USER} -d deschide -c "SELECT 1" > /dev/null 2>&1; then
    echo -e "${GREEN}✓${NC} Connection successful! SCRAM-SHA-256 authentication working"
else
    echo -e "${RED}✗${NC} Connection failed"
    echo "  Check logs: sudo tail -n 50 /var/log/postgresql/pgbouncer.log"
    exit 1
fi

echo ""

# ============================================================================
# Success Summary
# ============================================================================

echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║         Authentication Fix Complete! ✓                    ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${GREEN}PgBouncer is now using SCRAM-SHA-256 authentication!${NC}"
echo ""
echo "Changes made:"
echo "  - Extracted SCRAM-SHA-256 hashes from PostgreSQL"
echo "  - Updated /etc/pgbouncer/userlist.txt"
echo "  - Changed auth_type: md5 → scram-sha-256"
echo "  - Restarted PgBouncer service"
echo ""
echo "Backups created:"
echo "  - ${USERLIST_FILE}.md5.backup (old userlist)"
echo "  - ${CONFIG_FILE}.backup (original config)"
echo ""
echo "Test connection:"
echo "  PGPASSWORD=${DB_PASSWORD} psql -h 127.0.0.1 -p 6432 -U ${DB_USER} -d deschide -c 'SELECT 1'"
echo ""
echo "Next steps:"
echo "  1. Update Symfony .env.local: Change port 5432 → 6432"
echo "  2. Clear Symfony cache: symfony console cache:clear"
echo "  3. Test Symfony connection: symfony console dbal:run-sql 'SELECT 1'"
echo "  4. Run full test suite: ./test-pgbouncer.sh"
echo ""
