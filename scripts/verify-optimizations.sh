#!/bin/bash
#
# Verification Script for P1 Performance Optimizations
# Checks all optimization configurations
#

set -e

echo "=========================================="
echo "P1 Performance Optimizations Verification"
echo "=========================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

check_pass() {
  echo -e "${GREEN}✅ PASS${NC}: $1"
}

check_fail() {
  echo -e "${RED}❌ FAIL${NC}: $1"
}

check_info() {
  echo -e "${YELLOW}ℹ INFO${NC}: $1"
}

echo "=========================================="
echo "Phase 1: Infrastructure Checks"
echo "=========================================="
echo ""

# Fix 1: OPcache JIT
echo "[1/6] Checking OPcache JIT configuration..."
JIT_MODE=$(php -r "echo ini_get('opcache.jit');")
JIT_BUFFER=$(php -r "echo ini_get('opcache.jit_buffer_size');")

if [ "$JIT_MODE" == "1255" ]; then
  check_pass "OPcache JIT enabled with mode 1255 (optimal)"
else
  check_fail "OPcache JIT mode: $JIT_MODE (expected: 1255)"
  echo "   → Run: sudo nano /etc/php/8.4/mods-available/opcache.ini"
  echo "   → Set: opcache.jit=1255"
fi

if [ "$JIT_BUFFER" == "134217728" ] || [ "$JIT_BUFFER" == "128M" ]; then
  check_pass "OPcache JIT buffer: 128M (optimal)"
else
  check_fail "OPcache JIT buffer: $JIT_BUFFER (expected: 128M)"
fi
echo ""

# Fix 2: Redis Memory Configuration
echo "[2/6] Checking Redis memory configuration..."
REDIS_MAXMEM=$(redis-cli CONFIG GET maxmemory | tail -1)
REDIS_POLICY=$(redis-cli CONFIG GET maxmemory-policy | tail -1)
REDIS_SAMPLES=$(redis-cli CONFIG GET maxmemory-samples | tail -1)

if [ "$REDIS_MAXMEM" == "536870912" ]; then
  check_pass "Redis maxmemory: 512MB (optimal)"
else
  check_fail "Redis maxmemory: $REDIS_MAXMEM (expected: 536870912)"
fi

if [ "$REDIS_POLICY" == "volatile-lru" ]; then
  check_pass "Redis eviction policy: volatile-lru (optimal)"
else
  check_fail "Redis eviction policy: $REDIS_POLICY (expected: volatile-lru)"
fi

if [ "$REDIS_SAMPLES" == "5" ]; then
  check_pass "Redis LRU samples: 5 (optimal)"
else
  check_info "Redis LRU samples: $REDIS_SAMPLES (recommended: 5)"
fi

# Check Redis memory usage
REDIS_USED=$(redis-cli INFO memory | grep "used_memory_human:" | cut -d':' -f2 | tr -d '\r')
check_info "Redis current memory usage: $REDIS_USED"
echo ""

# Fix 3: PHP-FPM Scaling
echo "[3/6] Checking PHP-FPM configuration..."
FPM_CONFIG="/home/radu/.symfony5/php/32ddae0a98131804102ded73181a2de396ecd7ac/fpm-8.4.14.ini"

if [ -f "$FPM_CONFIG" ]; then
  MAX_CHILDREN=$(grep "pm.max_children" "$FPM_CONFIG" | awk '{print $3}')
  START_SERVERS=$(grep "pm.start_servers" "$FPM_CONFIG" | awk '{print $3}')

  if [ "$MAX_CHILDREN" == "100" ]; then
    check_pass "PHP-FPM max_children: 100 (optimal for 1000+ users)"
  else
    check_fail "PHP-FPM max_children: $MAX_CHILDREN (expected: 100)"
  fi

  if [ "$START_SERVERS" == "10" ]; then
    check_pass "PHP-FPM start_servers: 10 (optimal)"
  else
    check_info "PHP-FPM start_servers: $START_SERVERS (recommended: 10)"
  fi
else
  check_fail "PHP-FPM config not found at: $FPM_CONFIG"
fi
echo ""

echo "=========================================="
echo "Phase 2: Database & Caching Checks"
echo "=========================================="
echo ""

# Fix 4: PostgreSQL Connection (check if PgBouncer is configured)
echo "[4/6] Checking PostgreSQL connection..."
cd /var/www/deschide_news_app/apps/backend

if [ -f ".env.local" ]; then
  DB_URL=$(grep "DATABASE_URL" .env.local | cut -d'=' -f2)

  if echo "$DB_URL" | grep -q ":6432"; then
    check_pass "PgBouncer configured (port 6432 detected)"

    # Test PgBouncer connection
    if psql -h 127.0.0.1 -p 6432 -U deschide_user pgbouncer -c "SHOW POOLS;" 2>/dev/null | grep -q "deschide_news"; then
      check_pass "PgBouncer is running and accessible"
    else
      check_fail "PgBouncer not responding (may not be installed/running)"
      echo "   → Install: sudo apt-get install pgbouncer"
      echo "   → Configure: See P1_PERFORMANCE_OPTIMIZATIONS.md"
    fi
  else
    check_info "Direct PostgreSQL connection (port 5432)"
    echo "   → Consider PgBouncer for production (4x connection capacity)"
    echo "   → See: P1_PERFORMANCE_OPTIMIZATIONS.md - Fix 4"
  fi
else
  check_fail ".env.local not found"
fi
echo ""

# Fix 5: Doctrine Query Caching
echo "[5/6] Checking Doctrine cache configuration..."
if [ -f "config/packages/doctrine.yaml" ]; then
  if grep -q "result_cache_driver" config/packages/doctrine.yaml; then
    check_pass "Doctrine result cache configured"
  else
    check_fail "Doctrine result cache not configured"
  fi

  if grep -q "query_cache_driver" config/packages/doctrine.yaml; then
    check_pass "Doctrine query cache configured"
  else
    check_fail "Doctrine query cache not configured"
  fi

  if grep -q "second_level_cache" config/packages/doctrine.yaml; then
    check_pass "Doctrine second level cache enabled"
  else
    check_info "Doctrine second level cache not enabled (optional)"
  fi
else
  check_fail "Doctrine configuration not found"
fi
echo ""

# Fix 6: Symfony Server Status
echo "[6/6] Checking Symfony server status..."
if symfony server:status | grep -q "listening"; then
  SERVER_STATUS=$(symfony server:status | grep "Web server")
  check_pass "Symfony server is running"
  echo "   $SERVER_STATUS"
else
  check_fail "Symfony server is not running"
  echo "   → Start: symfony server:start -d --port=8081"
fi
echo ""

echo "=========================================="
echo "Summary"
echo "=========================================="
echo ""

# Test API connectivity
echo "Testing API connectivity..."
if curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8081/api | grep -q "200"; then
  check_pass "API is responding (HTTP 200)"

  # Quick response time test
  RESPONSE_TIME=$(curl -w "%{time_total}" -s -o /dev/null http://127.0.0.1:8081/api/articles)
  RESPONSE_MS=$(echo "$RESPONSE_TIME * 1000" | bc)
  check_info "Quick test response time: ${RESPONSE_MS}ms"

  if (( $(echo "$RESPONSE_TIME < 0.500" | bc -l) )); then
    check_pass "Response time < 500ms target"
  else
    check_info "Response time >= 500ms (run full tests for accurate p95)"
  fi
else
  check_fail "API is not responding"
fi
echo ""

echo "=========================================="
echo "Next Steps"
echo "=========================================="
echo ""
echo "1. Run response time tests:"
echo "   ./scripts/test-response-time.sh"
echo ""
echo "2. Run load tests (1000+ users):"
echo "   ./scripts/run-load-tests.sh"
echo ""
echo "3. Run regression tests:"
echo "   cd apps/backend && vendor/bin/phpunit"
echo "   cd apps/frontend && pnpm test"
echo ""
echo "4. See full implementation guide:"
echo "   cat P1_PERFORMANCE_OPTIMIZATIONS.md"
echo ""
