#!/bin/bash
#
# Configure PHP-FPM Scaling for Symfony Local Server
# This script updates the Symfony CLI PHP-FPM configuration for high concurrency
#

set -e

# Find the current PHP-FPM config for Symfony
FPM_CONFIG=$(find /home/radu/.symfony5/php -name "fpm-8.4.*.ini" -type f -printf '%T@ %p\n' | sort -n | tail -1 | cut -d' ' -f2-)

if [ -z "$FPM_CONFIG" ]; then
  echo "ERROR: Could not find Symfony PHP-FPM configuration"
  exit 1
fi

echo "=========================================="
echo "PHP-FPM Scaling Configuration"
echo "=========================================="
echo "Config file: $FPM_CONFIG"
echo ""

# Backup original config
BACKUP="${FPM_CONFIG}.backup.$(date +%Y%m%d_%H%M%S)"
cp "$FPM_CONFIG" "$BACKUP"
echo "Backup created: $BACKUP"
echo ""

# Update PHP-FPM settings
echo "Updating PHP-FPM settings for high concurrency..."

# Check if already configured
if grep -q "pm.max_children = 100" "$FPM_CONFIG"; then
  echo "✅ Configuration already optimal (max_children=100)"
  exit 0
fi

# Update configuration
sed -i 's/pm.max_children = [0-9]*/pm.max_children = 100/' "$FPM_CONFIG"
sed -i 's/pm.start_servers = [0-9]*/pm.start_servers = 10/' "$FPM_CONFIG"
sed -i 's/pm.min_spare_servers = [0-9]*/pm.min_spare_servers = 5/' "$FPM_CONFIG"
sed -i 's/pm.max_spare_servers = [0-9]*/pm.max_spare_servers = 20/' "$FPM_CONFIG"

# Add max_requests if not present
if ! grep -q "pm.max_requests" "$FPM_CONFIG"; then
  sed -i '/pm.max_spare_servers/a pm.max_requests = 500' "$FPM_CONFIG"
fi

echo "✅ Configuration updated:"
grep "pm\." "$FPM_CONFIG"
echo ""

# Restart Symfony server
echo "Restarting Symfony server..."
cd /var/www/deschide_news_app/apps/backend

if symfony server:status | grep -q "listening"; then
  symfony server:stop
  sleep 2
fi

symfony server:start -d --port=8081

echo ""
echo "✅ Symfony server restarted with new configuration"
echo ""
symfony server:status
echo ""

echo "=========================================="
echo "Verification"
echo "=========================================="
echo ""

# Verify configuration
MAX_CHILDREN=$(grep "pm.max_children" "$FPM_CONFIG" | awk '{print $3}')
echo "pm.max_children: $MAX_CHILDREN (target: 100)"

if [ "$MAX_CHILDREN" == "100" ]; then
  echo "✅ SUCCESS: PHP-FPM configured for 1000+ concurrent users"
else
  echo "❌ WARNING: Configuration may have been reset by Symfony CLI"
  echo "   This is expected behavior with Symfony local server"
  echo "   For production, configure system PHP-FPM directly"
fi

echo ""
echo "NOTE: Symfony CLI may regenerate this config file"
echo "Run this script again after server restart if needed"
echo ""
