#!/bin/bash

# Medium Load Test - 500 Concurrent Users
# Tests peak traffic conditions

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
CONCURRENT=500
REQUESTS=50000
TEST_DURATION=100

echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║        Medium Load Test - 500 Concurrent Users            ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

echo "Test Configuration:"
echo "  URL: ${FULL_URL}"
echo "  Concurrent users: ${CONCURRENT}"
echo "  Total requests: ${REQUESTS}"
echo "  Expected duration: ~${TEST_DURATION}s"
echo ""
echo -e "${YELLOW}⚠ Warning: This test will generate significant load${NC}"
echo "  Press Ctrl+C within 5 seconds to cancel..."
sleep 5
echo ""

# ============================================================================
# Pre-test: Warm up cache and record baseline
# ============================================================================

echo -e "${YELLOW}[Pre-test]${NC} Warming up cache and recording baseline..."

for i in {1..20}; do
    curl -s -o /dev/null "${FULL_URL}"
done

# Get baseline Varnish stats
CACHE_HIT_BEFORE=$(varnishstat -1 -f MAIN.cache_hit | awk '{print $2}')
CACHE_MISS_BEFORE=$(varnishstat -1 -f MAIN.cache_miss | awk '{print $2}')

echo -e "${GREEN}✓${NC} Baseline recorded"
echo ""

# ============================================================================
# Test 1: Apache Bench Load Test
# ============================================================================

echo -e "${YELLOW}[Test 1]${NC} Running Apache Bench load test..."
echo "  This may take ~${TEST_DURATION} seconds..."
echo ""

START_TIME=$(date +%s)

AB_OUTPUT=$(ab -n ${REQUESTS} -c ${CONCURRENT} -q "${FULL_URL}" 2>&1)

END_TIME=$(date +%s)
ACTUAL_DURATION=$((END_TIME - START_TIME))

echo "$AB_OUTPUT"
echo ""

# Extract key metrics
REQUESTS_PER_SEC=$(echo "$AB_OUTPUT" | grep "Requests per second:" | awk '{print $4}')
TIME_PER_REQUEST=$(echo "$AB_OUTPUT" | grep "Time per request:" | head -1 | awk '{print $4}')
TRANSFER_RATE=$(echo "$AB_OUTPUT" | grep "Transfer rate:" | awk '{print $3}')
FAILED_REQUESTS=$(echo "$AB_OUTPUT" | grep "Failed requests:" | awk '{print $3}')
TOTAL_TIME=$(echo "$AB_OUTPUT" | grep "Time taken for tests:" | awk '{print $5}')
COMPLETE_REQUESTS=$(echo "$AB_OUTPUT" | grep "Complete requests:" | awk '{print $3}')

# Get percentile times
P50=$(echo "$AB_OUTPUT" | grep "50%" | awk '{print $2}')
P75=$(echo "$AB_OUTPUT" | grep "75%" | awk '{print $2}')
P90=$(echo "$AB_OUTPUT" | grep "90%" | awk '{print $2}')
P95=$(echo "$AB_OUTPUT" | grep "95%" | awk '{print $2}')
P99=$(echo "$AB_OUTPUT" | grep "99%" | awk '{print $2}')
MAX=$(echo "$AB_OUTPUT" | grep "100%" | awk '{print $2}')

# ============================================================================
# Test 2: Varnish Statistics Analysis
# ============================================================================

echo -e "${YELLOW}[Test 2]${NC} Analyzing Varnish cache performance..."
echo ""

sleep 2

CACHE_HIT_AFTER=$(varnishstat -1 -f MAIN.cache_hit | awk '{print $2}')
CACHE_MISS_AFTER=$(varnishstat -1 -f MAIN.cache_miss | awk '{print $2}')
CACHE_HITPASS=$(varnishstat -1 -f MAIN.cache_hitpass | awk '{print $2}')
CLIENT_REQ=$(varnishstat -1 -f MAIN.client_req | awk '{print $2}')
BACKEND_CONN=$(varnishstat -1 -f MAIN.backend_conn | awk '{print $2}')
BACKEND_REQ=$(varnishstat -1 -f MAIN.backend_req | awk '{print $2}')

# Calculate test-specific stats
TEST_CACHE_HIT=$((CACHE_HIT_AFTER - CACHE_HIT_BEFORE))
TEST_CACHE_MISS=$((CACHE_MISS_AFTER - CACHE_MISS_BEFORE))
TOTAL_CACHE_REQUESTS=$((TEST_CACHE_HIT + TEST_CACHE_MISS))

if [ $TOTAL_CACHE_REQUESTS -gt 0 ]; then
    HIT_RATIO=$(echo "scale=2; ($TEST_CACHE_HIT / $TOTAL_CACHE_REQUESTS) * 100" | bc)
else
    HIT_RATIO="0"
fi

echo "  Cache hits (during test):    ${TEST_CACHE_HIT}"
echo "  Cache misses (during test):  ${TEST_CACHE_MISS}"
echo -e "  ${GREEN}Hit ratio:                   ${HIT_RATIO}%${NC}"
echo "  Backend connections:         ${BACKEND_CONN}"
echo "  Backend requests:            ${BACKEND_REQ}"
echo ""

# Calculate cache efficiency
if [ "$BACKEND_REQ" -gt 0 ] && [ "$COMPLETE_REQUESTS" -gt 0 ]; then
    CACHE_REDUCTION=$(echo "scale=2; (1 - ($BACKEND_REQ / $COMPLETE_REQUESTS)) * 100" | bc)
    echo -e "  ${GREEN}Cache reduced backend load by: ${CACHE_REDUCTION}%${NC}"
else
    CACHE_REDUCTION="N/A"
fi

echo ""

# ============================================================================
# Test 3: PgBouncer Connection Pool Analysis
# ============================================================================

echo -e "${YELLOW}[Test 3]${NC} Analyzing PgBouncer connection pool..."
echo ""

POOL_STATUS=$(psql -h 127.0.0.1 -p 6432 -U deschide_admin -d pgbouncer -Atc "SHOW POOLS" 2>/dev/null | grep "^deschide|")

if [ -n "$POOL_STATUS" ]; then
    CL_ACTIVE=$(echo "$POOL_STATUS" | cut -d'|' -f3)
    CL_WAITING=$(echo "$POOL_STATUS" | cut -d'|' -f4)
    SV_ACTIVE=$(echo "$POOL_STATUS" | cut -d'|' -f5)
    SV_IDLE=$(echo "$POOL_STATUS" | cut -d'|' -f6)
    SV_USED=$(echo "$POOL_STATUS" | cut -d'|' -f7)
    MAXWAIT=$(echo "$POOL_STATUS" | cut -d'|' -f10)

    echo "  Active clients:   ${CL_ACTIVE}"
    echo "  Waiting clients:  ${CL_WAITING}"
    echo "  Active servers:   ${SV_ACTIVE}"
    echo "  Idle servers:     ${SV_IDLE}"
    echo "  Used servers:     ${SV_USED}"
    echo "  Max wait time:    ${MAXWAIT}s"

    if [ "$CL_WAITING" -gt 5 ]; then
        echo -e "  ${RED}✗ WARNING: ${CL_WAITING} clients waiting - consider increasing pool size${NC}"
    elif [ "$CL_WAITING" -gt 0 ]; then
        echo -e "  ${YELLOW}⚠ ${CL_WAITING} clients briefly waited${NC}"
    else
        echo -e "  ${GREEN}✓ No clients waiting - pool size adequate${NC}"
    fi
fi

echo ""

# ============================================================================
# Test 4: System Resource Check
# ============================================================================

echo -e "${YELLOW}[Test 4]${NC} Checking system resources..."
echo ""

# CPU usage
CPU_USAGE=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1)

# Memory usage
MEM_USAGE=$(free | grep Mem | awk '{printf "%.1f", ($3/$2) * 100.0}')

echo -e "  CPU usage:    ${CPU_USAGE}%"
echo -e "  Memory usage: ${MEM_USAGE}%"

if [ $(echo "$CPU_USAGE > 80" | bc) -eq 1 ]; then
    echo -e "  ${YELLOW}⚠ CPU usage high${NC}"
fi

if [ $(echo "$MEM_USAGE > 80" | bc) -eq 1 ]; then
    echo -e "  ${YELLOW}⚠ Memory usage high${NC}"
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
echo -e "  Completed requests:   ${COMPLETE_REQUESTS}"
echo -e "  Requests per second:  ${GREEN}${REQUESTS_PER_SEC}${NC}"
echo -e "  Time per request:     ${TIME_PER_REQUEST} ms"
echo -e "  Transfer rate:        ${TRANSFER_RATE} KB/sec"
echo -e "  Total time:           ${TOTAL_TIME} seconds"
echo -e "  Failed requests:      ${FAILED_REQUESTS}"
echo ""

echo "Response Time Distribution:"
echo "  ─────────────────────────────────────────────"
echo -e "  Median (p50):         ${P50} ms"
echo -e "  75th percentile:      ${P75} ms"
echo -e "  90th percentile:      ${P90} ms"
echo -e "  95th percentile:      ${P95} ms"
echo -e "  99th percentile:      ${P99} ms"
echo -e "  Maximum:              ${MAX} ms"
echo ""

echo "Cache Performance:"
echo "  ─────────────────────────────────────────────"
echo -e "  Cache hit ratio:      ${GREEN}${HIT_RATIO}%${NC}"
echo -e "  Backend load reduction: ${CACHE_REDUCTION}%"
echo -e "  Backend requests:     ${BACKEND_REQ}"
echo ""

# ============================================================================
# Pass/Fail Criteria
# ============================================================================

echo "Test Validation (Medium Load):"
echo "  ─────────────────────────────────────────────"

PASS=true

# Check RPS
if [ $(echo "$REQUESTS_PER_SEC >= 1000" | bc) -eq 1 ]; then
    echo -e "  ${GREEN}✓${NC} RPS >= 1000 (${REQUESTS_PER_SEC})"
else
    echo -e "  ${YELLOW}⚠${NC} RPS < 1000 (${REQUESTS_PER_SEC})"
fi

# Check p95
if [ $(echo "$P95 <= 100" | bc) -eq 1 ]; then
    echo -e "  ${GREEN}✓${NC} p95 <= 100ms (${P95}ms)"
else
    echo -e "  ${YELLOW}⚠${NC} p95 > 100ms (${P95}ms)"
fi

# Check cache hit rate
if [ $(echo "$HIT_RATIO >= 85" | bc) -eq 1 ]; then
    echo -e "  ${GREEN}✓${NC} Cache hit rate >= 85% (${HIT_RATIO}%)"
elif [ $(echo "$HIT_RATIO >= 75" | bc) -eq 1 ]; then
    echo -e "  ${YELLOW}⚠${NC} Cache hit rate 75-85% (${HIT_RATIO}%)"
else
    echo -e "  ${RED}✗${NC} Cache hit rate < 75% (${HIT_RATIO}%)"
    PASS=false
fi

# Check failed requests
if [ "$FAILED_REQUESTS" -eq 0 ]; then
    echo -e "  ${GREEN}✓${NC} No failed requests"
else
    ERROR_RATE=$(echo "scale=2; ($FAILED_REQUESTS / $COMPLETE_REQUESTS) * 100" | bc)
    if [ $(echo "$ERROR_RATE < 0.5" | bc) -eq 1 ]; then
        echo -e "  ${GREEN}✓${NC} Error rate < 0.5% (${ERROR_RATE}%)"
    else
        echo -e "  ${RED}✗${NC} Error rate >= 0.5% (${ERROR_RATE}%)"
        PASS=false
    fi
fi

# Check connection pool
if [ "$CL_WAITING" -le 5 ]; then
    echo -e "  ${GREEN}✓${NC} Connection pool handling load well"
else
    echo -e "  ${YELLOW}⚠${NC} Connection pool under pressure"
fi

echo ""

if [ "$PASS" = true ]; then
    echo -e "${GREEN}✓ Medium load test PASSED${NC}"
    exit 0
else
    echo -e "${RED}✗ Medium load test FAILED - Check results above${NC}"
    exit 1
fi
