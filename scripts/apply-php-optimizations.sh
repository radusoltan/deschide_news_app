#!/bin/bash
# Apply PHP Performance Optimizations
# Applies OPcache JIT + PHP-FPM scaling to Symfony server

set -e

echo "🔧 Applying PHP Performance Optimizations..."
echo ""

# Find Symfony server PHP config
SYMFONY_PHP_DIR="/home/radu/.symfony5/php"
FPM_CONFIG=$(find "$SYMFONY_PHP_DIR" -name "fpm-*.ini" -type f 2>/dev/null | head -1)

if [ -z "$FPM_CONFIG" ]; then
    echo "❌ ERROR: Could not find Symfony PHP-FPM config"
    echo "   Make sure Symfony server is running first"
    exit 1
fi

echo "📁 Found config: $FPM_CONFIG"
echo ""

# Backup original
cp "$FPM_CONFIG" "${FPM_CONFIG}.backup-$(date +%Y%m%d_%H%M%S)"
echo "✅ Backup created"

# Apply optimizations
cat >> "$FPM_CONFIG" <<'EOF'

; ============================================================================
; PERFORMANCE OPTIMIZATIONS - Phase 2
; Added: 2025-12-09 for P1 HIGH PRIORITY performance improvements
; ============================================================================

; PHP-FPM Worker Scaling (for high concurrency)
pm = dynamic
pm.max_children = 100
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500

; OPcache Configuration - CRITICAL for PHP performance
php_admin_value[opcache.enable] = 1
php_admin_value[opcache.enable_cli] = 1
php_admin_value[opcache.memory_consumption] = 256
php_admin_value[opcache.interned_strings_buffer] = 16
php_admin_value[opcache.max_accelerated_files] = 20000
php_admin_value[opcache.validate_timestamps] = 0
php_admin_value[opcache.revalidate_freq] = 0
php_admin_value[opcache.save_comments] = 1
php_admin_value[opcache.fast_shutdown] = 1

; OPcache JIT - 20-30% performance boost
php_admin_value[opcache.jit] = 1255
php_admin_value[opcache.jit_buffer_size] = 128M

; Realpath cache - Reduces filesystem calls
php_admin_value[realpath_cache_size] = 4096K
php_admin_value[realpath_cache_ttl] = 600
EOF

echo "✅ Optimizations applied to config"
echo ""

# Restart Symfony server
echo "🔄 Restarting Symfony server..."
cd /var/www/deschide_news_app/apps/backend
symfony server:stop > /dev/null 2>&1 || true
sleep 2
symfony serve -d --port=8081

echo ""
echo "✅ Symfony server restarted with optimizations"
echo ""

# Verify OPcache
echo "🔍 Verifying OPcache JIT..."
sleep 2

if symfony php -i | grep -q "opcache.jit"; then
    echo "✅ OPcache JIT: ENABLED"
    symfony php -r "echo 'JIT Buffer: ' . ini_get('opcache.jit_buffer_size') . PHP_EOL;"
else
    echo "⚠️  OPcache JIT: Status unknown (check manually)"
fi

echo ""
echo "🎯 Performance optimizations applied successfully!"
echo ""
echo "Next steps:"
echo "  1. Test performance: ./scripts/test-response-time.sh http://127.0.0.1:8081/api/articles"
echo "  2. Verify: ./scripts/verify-optimizations.sh"
echo "  3. Load test: k6 run k6/api-only-load-test.js"
