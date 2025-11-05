#!/bin/bash

# Light Load Test - 100 Concurrent Users
# Tests normal traffic conditions

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
VARNISH_URL="http://127.0.0.1:6081"
TEST_ENDPOINT="/api/articles"
FULL_URL="${VARNISH_URL}${TEST_ENDPOINT}"

# Test parameters
CONCURRENT=100
REQUESTS=10000
TEST_DURATION=60

echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║         Light Load Test - 100 Concurrent Users            ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

echo "Test Configuration:"
echo "  URL: ${FULL_URL}"
echo "  Concurrent users: ${CONCURRENT}"
echo "  Total requests: ${REQUESTS}"
echo "  Expected duration: ~${TEST_DURATION}s"
echo ""

# ============================================================================
# Pre-test: Warm up cache
# ============================================================================

echo -e "${YELLOW}[Pre-test]${NC} Warming up cache..."
for i in {1..10}; do
    curl -s -o /dev/null "${FULL_URL}"
done
echo -e "${GREEN}✓${NC} Cache warmed up"
echo ""

# ============================================================================
# Test 1: Apache Bench - Detailed Metrics
# ============================================================================

echo -e "${YELLOW}[Test 1]${NC} Running Apache Bench load test..."
echo "  Command: ab -n ${REQUESTS} -c ${CONCURRENT} ${FULL_URL}"
echo ""

AB_OUTPUT=$(ab -n ${REQUESTS} -c ${CONCURRENT} -q "${FULL_URL}" 2>&1)

echo "$AB_OUTPUT"
echo ""

# Extract key metrics
REQUESTS_PER_SEC=$(echo "$AB_OUTPUT" | grep "Requests per second:" | awk '{print $4}')
TIME_PER_REQUEST=$(echo "$AB_OUTPUT" | grep "Time per request:" | head -1 | awk '{print $4}')
TRANSFER_RATE=$(echo "$AB_OUTPUT" | grep "Transfer rate:" | awk '{print $3}')
FAILED_REQUESTS=$(echo "$AB_OUTPUT" | grep "Failed requests:" | awk '{print $3}')
TOTAL_TIME=$(echo "$AB_OUTPUT" | grep "Time taken for tests:" | awk '{print $5}')

# Get percentile times
P50=$(echo "$AB_OUTPUT" | grep "50%" | awk '{print $2}')
P95=$(echo "$AB_OUTPUT" | grep "95%" | awk '{print $2}')
P99=$(echo "$AB_OUTPUT" | grep "99%" | awk '{print $2}')
MAX=$(echo "$AB_OUTPUT" | grep "100%" | awk '{print $2}')

# ============================================================================
# Test 2: Varnish Statistics During Load
# ============================================================================

echo -e "${YELLOW}[Test 2]${NC} Checking Varnish cache statistics..."
echo ""

CACHE_HIT=$(varnishstat -1 -f MAIN.cache_hit | awk '{print $2}')
CACHE_MISS=$(varnishstat -1 -f MAIN.cache_miss | awk '{print $2}')
CACHE_HITPASS=$(varnishstat -1 -f MAIN.cache_hitpass | awk '{print $2}')
CLIENT_REQ=$(varnishstat -1 -f MAIN.client_req | awk '{print $2}')

TOTAL_CACHE_REQUESTS=$((CACHE_HIT + CACHE_MISS + CACHE_HITPASS))

if [ $TOTAL_CACHE_REQUESTS -gt 0 ]; then
    HIT_RATIO=$(echo "scale=2; ($CACHE_HIT / $TOTAL_CACHE_REQUESTS) * 100" | bc)
else
    HIT_RATIO="0"
fi

echo "  Cache hits:       ${CACHE_HIT}"
echo "  Cache misses:     ${CACHE_MISS}"
echo "  Cache hitpass:    ${CACHE_HITPASS}"
echo "  Total requests:   ${CLIENT_REQ}"
echo -e "  ${GREEN}Hit ratio:        ${HIT_RATIO}%${NC}"
echo ""

# ============================================================================
# Test 3: PgBouncer Pool Status
# ============================================================================

echo -e "${YELLOW}[Test 3]${NC} Checking PgBouncer connection pool..."
echo ""

POOL_STATUS=$(psql -h 127.0.0.1 -p 6432 -U deschide_admin -d pgbouncer -Atc "SHOW POOLS" 2>/dev/null | grep "^deschide|")

if [ -n "$POOL_STATUS" ]; then
    CL_ACTIVE=$(echo "$POOL_STATUS" | cut -d'|' -f3)
    CL_WAITING=$(echo "$POOL_STATUS" | cut -d'|' -f4)
    SV_ACTIVE=$(echo "$POOL_STATUS" | cut -d'|' -f5)
    SV_IDLE=$(echo "$POOL_STATUS" | cut -d'|' -f6)

    echo "  Active clients:   ${CL_ACTIVE}"
    echo "  Waiting clients:  ${CL_WAITING}"
    echo "  Active servers:   ${SV_ACTIVE}"
    echo "  Idle servers:     ${SV_IDLE}"

    if [ "$CL_WAITING" -gt 0 ]; then
        echo -e "  ${YELLOW}⚠ Warning: ${CL_WAITING} clients waiting for connections${NC}"
    else
        echo -e "  ${GREEN}✓ No clients waiting${NC}"
    fi
fi

echo ""

# ============================================================================
# Summary and Analysis
# ============================================================================

echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║                  Test Results Summary                      ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

echo "Performance Metrics:"
echo "  ─────────────────────────────────────────────"
echo -e "  Requests per second:  ${GREEN}${REQUESTS_PER_SEC}${NC}"
echo -e "  Time per request:     ${TIME_PER_REQUEST} ms"
echo -e "  Transfer rate:        ${TRANSFER_RATE} KB/sec"
echo -e "  Total time:           ${TOTAL_TIME} seconds"
echo -e "  Failed requests:      ${FAILED_REQUESTS}"
echo ""

echo "Response Time Distribution:"
echo "  ─────────────────────────────────────────────"
echo -e "  Median (p50):         ${P50} ms"
echo -e "  95th percentile:      ${P95} ms"
echo -e "  99th percentile:      ${P99} ms"
echo -e "  Maximum:              ${MAX} ms"
echo ""

echo "Cache Performance:"
echo "  ─────────────────────────────────────────────"
echo -e "  Cache hit ratio:      ${GREEN}${HIT_RATIO}%${NC}"
echo -e "  Total cache requests: ${TOTAL_CACHE_REQUESTS}"
echo ""

# ============================================================================
# Pass/Fail Criteria
# ============================================================================

echo "Test Validation:"
echo "  ─────────────────────────────────────────────"

PASS=true

# Check RPS
if [ $(echo "$REQUESTS_PER_SEC >= 500" | bc) -eq 1 ]; then
    echo -e "  ${GREEN}✓${NC} RPS >= 500 (${REQUESTS_PER_SEC})"
else
    echo -e "  ${RED}✗${NC} RPS < 500 (${REQUESTS_PER_SEC})"
    PASS=false
fi

# Check p95
if [ $(echo "$P95 <= 50" | bc) -eq 1 ]; then
    echo -e "  ${GREEN}✓${NC} p95 <= 50ms (${P95}ms)"
else
    echo -e "  ${YELLOW}⚠${NC} p95 > 50ms (${P95}ms)"
fi

# Check cache hit rate
if [ $(echo "$HIT_RATIO >= 85" | bc) -eq 1 ]; then
    echo -e "  ${GREEN}✓${NC} Cache hit rate >= 85% (${HIT_RATIO}%)"
else
    echo -e "  ${YELLOW}⚠${NC} Cache hit rate < 85% (${HIT_RATIO}%)"
fi

# Check failed requests
if [ "$FAILED_REQUESTS" -eq 0 ]; then
    echo -e "  ${GREEN}✓${NC} No failed requests"
else
    ERROR_RATE=$(echo "scale=2; ($FAILED_REQUESTS / $REQUESTS) * 100" | bc)
    if [ $(echo "$ERROR_RATE < 0.1" | bc) -eq 1 ]; then
        echo -e "  ${GREEN}✓${NC} Error rate < 0.1% (${ERROR_RATE}%)"
    else
        echo -e "  ${RED}✗${NC} Error rate >= 0.1% (${ERROR_RATE}%)"
        PASS=false
    fi
fi

echo ""

if [ "$PASS" = true ]; then
    echo -e "${GREEN}✓ Light load test PASSED${NC}"
    exit 0
else
    echo -e "${RED}✗ Light load test FAILED${NC}"
    exit 1
fi
