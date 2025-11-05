#!/bin/bash

# Automated Varnish Installation Script
# For Ubuntu 24.04 LTS

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
echo -e "${BLUE}║     Varnish Cache Installation - Deschide News            ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Configuration
VARNISH_PORT=6081
VARNISH_ADMIN_PORT=6082
CACHE_SIZE="512M"
VCL_SOURCE="/var/www/deschide_news_app/deschide_backend/varnish.vcl"
VCL_DEST="/etc/varnish/default.vcl"

# ============================================================================
# Step 1: Update package index
# ============================================================================

echo -e "${YELLOW}[Step 1/6]${NC} Updating package index..."
apt update -qq
echo -e "${GREEN}✓${NC} Package index updated"
echo ""

# ============================================================================
# Step 2: Install Varnish
# ============================================================================

echo -e "${YELLOW}[Step 2/6]${NC} Installing Varnish..."

if command -v varnishd &> /dev/null; then
    VARNISH_VERSION=$(varnishd -V 2>&1 | head -1)
    echo -e "${GREEN}✓${NC} Varnish already installed: ${VARNISH_VERSION}"
else
    apt install -y varnish > /dev/null 2>&1
    VARNISH_VERSION=$(varnishd -V 2>&1 | head -1)
    echo -e "${GREEN}✓${NC} Varnish installed: ${VARNISH_VERSION}"
fi

echo ""

# ============================================================================
# Step 3: Backup original configuration
# ============================================================================

echo -e "${YELLOW}[Step 3/6]${NC} Backing up original configuration..."

if [ -f "${VCL_DEST}" ]; then
    if [ ! -f "${VCL_DEST}.backup" ]; then
        cp "${VCL_DEST}" "${VCL_DEST}.backup"
        echo -e "${GREEN}✓${NC} Original config backed up to ${VCL_DEST}.backup"
    else
        echo -e "${YELLOW}⚠${NC} Backup already exists"
    fi
fi

echo ""

# ============================================================================
# Step 4: Install VCL configuration
# ============================================================================

echo -e "${YELLOW}[Step 4/6]${NC} Installing VCL configuration..."

if [ -f "${VCL_SOURCE}" ]; then
    cp "${VCL_SOURCE}" "${VCL_DEST}"
    echo -e "${GREEN}✓${NC} VCL configuration installed"

    # Verify VCL syntax
    if varnishd -C -f "${VCL_DEST}" > /dev/null 2>&1; then
        echo -e "${GREEN}✓${NC} VCL syntax is valid"
    else
        echo -e "${RED}✗${NC} VCL syntax error!"
        varnishd -C -f "${VCL_DEST}"
        exit 1
    fi
else
    echo -e "${RED}✗${NC} VCL source file not found: ${VCL_SOURCE}"
    exit 1
fi

echo ""

# ============================================================================
# Step 5: Configure Varnish service
# ============================================================================

echo -e "${YELLOW}[Step 5/6]${NC} Configuring Varnish service..."

# Create systemd override
mkdir -p /etc/systemd/system/varnish.service.d

cat > /etc/systemd/system/varnish.service.d/override.conf << EOF
[Service]
ExecStart=
ExecStart=/usr/sbin/varnishd \\
    -F \\
    -a :${VARNISH_PORT} \\
    -T localhost:${VARNISH_ADMIN_PORT} \\
    -f ${VCL_DEST} \\
    -s malloc,${CACHE_SIZE} \\
    -p feature=+esi_ignore_https \\
    -p feature=+esi_disable_xml_check \\
    -p default_ttl=3600 \\
    -p default_grace=21600
EOF

echo -e "${GREEN}✓${NC} Varnish service configured"
echo "  - Listen port: ${VARNISH_PORT}"
echo "  - Admin port: ${VARNISH_ADMIN_PORT}"
echo "  - Cache size: ${CACHE_SIZE}"
echo "  - Default TTL: 3600s (1 hour)"
echo "  - Grace period: 21600s (6 hours)"

# Reload systemd
systemctl daemon-reload
echo -e "${GREEN}✓${NC} Systemd configuration reloaded"

echo ""

# ============================================================================
# Step 6: Start Varnish service
# ============================================================================

echo -e "${YELLOW}[Step 6/6]${NC} Starting Varnish service..."

# Stop if running
if systemctl is-active --quiet varnish; then
    systemctl stop varnish
    echo "  Stopped existing Varnish service"
fi

# Start service
systemctl start varnish
sleep 2

# Verify it's running
if systemctl is-active --quiet varnish; then
    echo -e "${GREEN}✓${NC} Varnish service started successfully"
else
    echo -e "${RED}✗${NC} Failed to start Varnish service"
    systemctl status varnish
    exit 1
fi

# Enable on boot
systemctl enable varnish > /dev/null 2>&1
echo -e "${GREEN}✓${NC} Varnish enabled to start on boot"

# Verify port binding
sleep 1
if ss -tulpn | grep -q ":${VARNISH_PORT}"; then
    echo -e "${GREEN}✓${NC} Varnish is listening on port ${VARNISH_PORT}"
else
    echo -e "${RED}✗${NC} Varnish is NOT listening on port ${VARNISH_PORT}"
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
echo -e "${GREEN}Varnish Cache is now running!${NC}"
echo ""
echo "Configuration:"
echo "  - Varnish URL:     http://127.0.0.1:${VARNISH_PORT}"
echo "  - Backend URL:     http://127.0.0.1:8081 (Symfony)"
echo "  - Config file:     ${VCL_DEST}"
echo "  - Cache size:      ${CACHE_SIZE}"
echo "  - Admin port:      ${VARNISH_ADMIN_PORT}"
echo ""
echo "Quick commands:"
echo "  - Test cache:      /var/www/deschide_news_app/deschide_backend/test-varnish.sh"
echo "  - View stats:      sudo varnishstat"
echo "  - View logs:       sudo varnishlog"
echo "  - Restart:         sudo systemctl restart varnish"
echo "  - Status:          sudo systemctl status varnish"
echo ""
echo "Next steps:"
echo "  1. Ensure Symfony is running: symfony serve -d --port=8081"
echo "  2. Run test suite: ./test-varnish.sh"
echo "  3. Monitor performance: sudo varnishstat"
echo ""
echo -e "${YELLOW}Important:${NC} This installation uses port ${VARNISH_PORT} for testing."
echo "For production, edit /etc/systemd/system/varnish.service.d/override.conf"
echo "and change port to 80, then: sudo systemctl daemon-reload && sudo systemctl restart varnish"
echo ""
