#!/bin/bash

# Baseline Performance Test
# Tests single request performance with cold and warm cache

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
VARNISH_URL="http://127.0.0.1:6081"
SYMFONY_URL="http://127.0.0.1:8081"
TEST_ENDPOINT="/api/articles"
CURL_FORMAT="curl-format.txt"

echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║            Baseline Performance Test                      ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

# ============================================================================
# Test 1: Cold Cache (Direct Symfony - Bypass Varnish)
# ============================================================================

echo -e "${YELLOW}[Test 1]${NC} Testing COLD CACHE (Direct Symfony)..."
echo ""

# Clear Varnish cache first
curl -s -X PURGE "${VARNISH_URL}${TEST_ENDPOINT}" > /dev/null 2>&1 || true

echo "Request to Symfony (port 8081) - No caching:"
SYMFONY_TIME=$(curl -s -w "%{time_total}" -o /dev/null "${SYMFONY_URL}${TEST_ENDPOINT}")
echo -e "  Response time: ${GREEN}${SYMFONY_TIME}s${NC}"
echo ""

# ============================================================================
# Test 2: First Request Through Varnish (Cache MISS)
# ============================================================================

echo -e "${YELLOW}[Test 2]${NC} Testing CACHE MISS (First request through Varnish)..."
echo ""

# Clear Varnish cache
curl -s -X PURGE "${VARNISH_URL}${TEST_ENDPOINT}" > /dev/null 2>&1 || true
sleep 1

echo "Request through Varnish (port 6081) - Should be MISS:"
VARNISH_MISS_RESPONSE=$(curl -s -I "${VARNISH_URL}${TEST_ENDPOINT}")
VARNISH_MISS_TIME=$(curl -s -w "%{time_total}" -o /dev/null "${VARNISH_URL}${TEST_ENDPOINT}")

CACHE_STATUS=$(echo "$VARNISH_MISS_RESPONSE" | grep -i "X-Cache:" | awk '{print $2}' | tr -d '\r')
AGE=$(echo "$VARNISH_MISS_RESPONSE" | grep -i "Age:" | awk '{print $2}' | tr -d '\r')

echo -e "  Response time: ${YELLOW}${VARNISH_MISS_TIME}s${NC}"
echo -e "  Cache status: ${YELLOW}${CACHE_STATUS}${NC}"
echo -e "  Age: ${AGE:-0}s"
echo ""

# ============================================================================
# Test 3: Second Request Through Varnish (Cache HIT)
# ============================================================================

echo -e "${YELLOW}[Test 3]${NC} Testing CACHE HIT (Second request through Varnish)..."
echo ""

sleep 1

echo "Request through Varnish (port 6081) - Should be HIT:"
VARNISH_HIT_RESPONSE=$(curl -s -I "${VARNISH_URL}${TEST_ENDPOINT}")
VARNISH_HIT_TIME=$(curl -s -w "%{time_total}" -o /dev/null "${VARNISH_URL}${TEST_ENDPOINT}")

CACHE_STATUS=$(echo "$VARNISH_HIT_RESPONSE" | grep -i "X-Cache:" | awk '{print $2}' | tr -d '\r')
AGE=$(echo "$VARNISH_HIT_RESPONSE" | grep -i "Age:" | awk '{print $2}' | tr -d '\r')

echo -e "  Response time: ${GREEN}${VARNISH_HIT_TIME}s${NC}"
echo -e "  Cache status: ${GREEN}${CACHE_STATUS}${NC}"
echo -e "  Age: ${AGE}s"
echo ""

# ============================================================================
# Test 4: Multiple Cached Requests (Consistency Check)
# ============================================================================

echo -e "${YELLOW}[Test 4]${NC} Testing CACHE CONSISTENCY (10 requests)..."
echo ""

TOTAL_TIME=0
HIT_COUNT=0
MISS_COUNT=0

for i in {1..10}; do
    RESPONSE=$(curl -s -I "${VARNISH_URL}${TEST_ENDPOINT}")
    TIME=$(curl -s -w "%{time_total}" -o /dev/null "${VARNISH_URL}${TEST_ENDPOINT}")
    CACHE_STATUS=$(echo "$RESPONSE" | grep -i "X-Cache:" | awk '{print $2}' | tr -d '\r')

    TOTAL_TIME=$(echo "$TOTAL_TIME + $TIME" | bc)

    if [ "$CACHE_STATUS" = "HIT" ]; then
        HIT_COUNT=$((HIT_COUNT + 1))
    else
        MISS_COUNT=$((MISS_COUNT + 1))
    fi
done

AVG_TIME=$(echo "scale=4; $TOTAL_TIME / 10" | bc)
HIT_RATE=$(echo "scale=1; ($HIT_COUNT / 10) * 100" | bc)

echo -e "  Average response time: ${GREEN}${AVG_TIME}s${NC}"
echo -e "  Cache hits: ${GREEN}${HIT_COUNT}/10${NC}"
echo -e "  Cache misses: ${YELLOW}${MISS_COUNT}/10${NC}"
echo -e "  Hit rate: ${GREEN}${HIT_RATE}%${NC}"
echo ""

# ============================================================================
# Test 5: Cache Headers Verification
# ============================================================================

echo -e "${YELLOW}[Test 5]${NC} Verifying Cache Headers..."
echo ""

HEADERS=$(curl -s -I "${VARNISH_URL}${TEST_ENDPOINT}")

echo "Cache-related headers:"
echo "$HEADERS" | grep -i "Cache-Control:" || echo "  Cache-Control: NOT FOUND"
echo "$HEADERS" | grep -i "X-Cache:" || echo "  X-Cache: NOT FOUND"
echo "$HEADERS" | grep -i "Age:" || echo "  Age: NOT FOUND"
echo "$HEADERS" | grep -i "X-Cache-Hits:" || echo "  X-Cache-Hits: NOT FOUND"
echo "$HEADERS" | grep -i "X-Varnish:" || echo "  X-Varnish: NOT FOUND"
echo ""

# ============================================================================
# Test 6: Varnish Statistics
# ============================================================================

echo -e "${YELLOW}[Test 6]${NC} Varnish Cache Statistics..."
echo ""

CACHE_HIT=$(varnishstat -1 -f MAIN.cache_hit | awk '{print $2}')
CACHE_MISS=$(varnishstat -1 -f MAIN.cache_miss | awk '{print $2}')
CACHE_HITPASS=$(varnishstat -1 -f MAIN.cache_hitpass | awk '{print $2}')

TOTAL_REQUESTS=$((CACHE_HIT + CACHE_MISS + CACHE_HITPASS))

if [ $TOTAL_REQUESTS -gt 0 ]; then
    HIT_RATIO=$(echo "scale=2; ($CACHE_HIT / $TOTAL_REQUESTS) * 100" | bc)
else
    HIT_RATIO="0"
fi

echo -e "  Cache hits:     ${GREEN}${CACHE_HIT}${NC}"
echo -e "  Cache misses:   ${YELLOW}${CACHE_MISS}${NC}"
echo -e "  Cache hitpass:  ${YELLOW}${CACHE_HITPASS}${NC}"
echo -e "  Total requests: ${TOTAL_REQUESTS}"
echo -e "  Hit ratio:      ${GREEN}${HIT_RATIO}%${NC}"
echo ""

# ============================================================================
# Summary
# ============================================================================

echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║                  Baseline Summary                          ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Calculate improvements
if [ $(echo "$SYMFONY_TIME > 0" | bc) -eq 1 ]; then
    CACHE_IMPROVEMENT=$(echo "scale=1; (($SYMFONY_TIME - $VARNISH_HIT_TIME) / $SYMFONY_TIME) * 100" | bc)
    SPEEDUP=$(echo "scale=1; $SYMFONY_TIME / $VARNISH_HIT_TIME" | bc)
else
    CACHE_IMPROVEMENT="N/A"
    SPEEDUP="N/A"
fi

echo "Performance Comparison:"
echo -e "  Direct Symfony (no cache): ${YELLOW}${SYMFONY_TIME}s${NC}"
echo -e "  Varnish MISS (first hit):  ${YELLOW}${VARNISH_MISS_TIME}s${NC}"
echo -e "  Varnish HIT (cached):      ${GREEN}${VARNISH_HIT_TIME}s${NC}"
echo ""
echo -e "  ${GREEN}Cache improvement: ${CACHE_IMPROVEMENT}%${NC}"
echo -e "  ${GREEN}Speedup factor: ${SPEEDUP}x${NC}"
echo ""

echo "Cache Efficiency:"
echo -e "  Overall hit rate: ${GREEN}${HIT_RATIO}%${NC}"
echo -e "  10-request test hit rate: ${GREEN}${HIT_RATE}%${NC}"
echo ""

# Determine pass/fail
if [ $(echo "$HIT_RATIO >= 80" | bc) -eq 1 ] && [ $(echo "$VARNISH_HIT_TIME < 0.1" | bc) -eq 1 ]; then
    echo -e "${GREEN}✓ Baseline test PASSED${NC}"
    echo "  - Cache hit rate > 80%"
    echo "  - Cached response time < 100ms"
else
    echo -e "${YELLOW}⚠ Baseline test needs attention${NC}"
    if [ $(echo "$HIT_RATIO < 80" | bc) -eq 1 ]; then
        echo "  - Cache hit rate below 80%"
    fi
    if [ $(echo "$VARNISH_HIT_TIME >= 0.1" | bc) -eq 1 ]; then
        echo "  - Cached response time >= 100ms"
    fi
fi

echo ""
echo -e "${GREEN}Baseline testing complete!${NC}"
echo ""
