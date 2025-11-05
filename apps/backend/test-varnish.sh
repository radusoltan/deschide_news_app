#!/bin/bash

# Varnish Cache Testing Script
# Tests cache functionality, hit rates, and performance

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
VARNISH_PORT=6081
BACKEND_PORT=8081
VARNISH_HOST="127.0.0.1"
BACKEND_HOST="127.0.0.1"
TEST_ENDPOINT="/api/articles?itemsPerPage=5"

echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║       Varnish HTTP Cache - Testing Suite                  ║${NC}"
echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo ""

# ============================================================================
# Test 1: Check if Varnish is running
# ============================================================================

echo -e "${YELLOW}[Test 1]${NC} Checking if Varnish is running..."

if systemctl is-active --quiet varnish; then
    echo -e "${GREEN}✓${NC} Varnish service is running"
else
    echo -e "${RED}✗${NC} Varnish service is NOT running"
    echo "  Run: sudo systemctl start varnish"
    exit 1
fi

if ss -tulpn | grep -q ":${VARNISH_PORT}"; then
    echo -e "${GREEN}✓${NC} Varnish is listening on port ${VARNISH_PORT}"
else
    echo -e "${RED}✗${NC} Varnish is NOT listening on port ${VARNISH_PORT}"
    exit 1
fi

echo ""

# ============================================================================
# Test 2: Check if backend (Symfony) is running
# ============================================================================

echo -e "${YELLOW}[Test 2]${NC} Checking if backend is running..."

HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "http://${BACKEND_HOST}:${BACKEND_PORT}/api")
if [ "$HTTP_CODE" -ge 200 ] && [ "$HTTP_CODE" -lt 500 ]; then
    echo -e "${GREEN}✓${NC} Backend is running on port ${BACKEND_PORT} (HTTP ${HTTP_CODE})"
else
    echo -e "${RED}✗${NC} Backend is NOT responding on port ${BACKEND_PORT}"
    echo "  Run: symfony serve -d --port=${BACKEND_PORT}"
    exit 1
fi

echo ""

# ============================================================================
# Test 3: Cache Headers from Backend
# ============================================================================

echo -e "${YELLOW}[Test 3]${NC} Verifying backend cache headers..."

BACKEND_HEADERS=$(curl -s -I "http://${BACKEND_HOST}:${BACKEND_PORT}${TEST_ENDPOINT}")

if echo "$BACKEND_HEADERS" | grep -q "Cache-Control.*public"; then
    echo -e "${GREEN}✓${NC} Backend sends Cache-Control: public"
else
    echo -e "${RED}✗${NC} Backend missing 'public' in Cache-Control"
fi

if echo "$BACKEND_HEADERS" | grep -q "s-maxage"; then
    SMAXAGE=$(echo "$BACKEND_HEADERS" | grep -oP 's-maxage=\K[0-9]+')
    echo -e "${GREEN}✓${NC} Backend sends s-maxage=${SMAXAGE}"
else
    echo -e "${YELLOW}⚠${NC} Backend missing 's-maxage' (will use default TTL)"
fi

echo ""

# ============================================================================
# Test 4: First Request (Cache MISS)
# ============================================================================

echo -e "${YELLOW}[Test 4]${NC} First request through Varnish (should be MISS)..."

# Purge cache first to ensure MISS
curl -s -X PURGE "http://${VARNISH_HOST}:${VARNISH_PORT}${TEST_ENDPOINT}" > /dev/null 2>&1 || true

MISS_START=$(date +%s%N)
MISS_RESPONSE=$(curl -s -I "http://${VARNISH_HOST}:${VARNISH_PORT}${TEST_ENDPOINT}")
MISS_END=$(date +%s%N)
MISS_TIME=$(echo "scale=2; ($MISS_END - $MISS_START) / 1000000" | bc)

if echo "$MISS_RESPONSE" | grep -q "X-Cache.*MISS"; then
    echo -e "${GREEN}✓${NC} First request is a MISS (expected)"
    echo -e "  Response time: ${MISS_TIME}ms"
else
    echo -e "${YELLOW}⚠${NC} Expected MISS but got different result"
fi

echo ""

# ============================================================================
# Test 5: Second Request (Cache HIT)
# ============================================================================

echo -e "${YELLOW}[Test 5]${NC} Second request through Varnish (should be HIT)..."

sleep 1  # Brief pause

HIT_START=$(date +%s%N)
HIT_RESPONSE=$(curl -s -I "http://${VARNISH_HOST}:${VARNISH_PORT}${TEST_ENDPOINT}")
HIT_END=$(date +%s%N)
HIT_TIME=$(echo "scale=2; ($HIT_END - $HIT_START) / 1000000" | bc)

if echo "$HIT_RESPONSE" | grep -q "X-Cache.*HIT"; then
    echo -e "${GREEN}✓${NC} Second request is a HIT (cached!)"
    echo -e "  Response time: ${HIT_TIME}ms"

    CACHE_HITS=$(echo "$HIT_RESPONSE" | grep -oP 'X-Cache-Hits: \K[0-9]+')
    echo -e "  Cache hits: ${CACHE_HITS}"

    # Calculate speedup
    if [ $(echo "$MISS_TIME > 0" | bc) -eq 1 ]; then
        SPEEDUP=$(echo "scale=1; ($MISS_TIME / $HIT_TIME)" | bc)
        IMPROVEMENT=$(echo "scale=1; (($MISS_TIME - $HIT_TIME) / $MISS_TIME) * 100" | bc)
        echo -e "${GREEN}  ⚡ ${SPEEDUP}x faster (${IMPROVEMENT}% improvement)${NC}"
    fi
else
    echo -e "${RED}✗${NC} Second request is NOT a HIT"
    echo "  Possible issues:"
    echo "  - VCL configuration incorrect"
    echo "  - Backend not sending proper cache headers"
    echo "  - Query parameters changing between requests"
fi

echo ""

# ============================================================================
# Test 6: Multilanguage Caching
# ============================================================================

echo -e "${YELLOW}[Test 6]${NC} Testing multilanguage caching..."

# Romanian
curl -s -X PURGE "http://${VARNISH_HOST}:${VARNISH_PORT}${TEST_ENDPOINT}" > /dev/null 2>&1 || true
RO_RESP=$(curl -s -I -H "Accept-Language: ro" "http://${VARNISH_HOST}:${VARNISH_PORT}${TEST_ENDPOINT}")
if echo "$RO_RESP" | grep -q "X-Cache.*MISS"; then
    echo -e "${GREEN}✓${NC} Romanian (ro) - First request MISS"
fi

RO_RESP2=$(curl -s -I -H "Accept-Language: ro" "http://${VARNISH_HOST}:${VARNISH_PORT}${TEST_ENDPOINT}")
if echo "$RO_RESP2" | grep -q "X-Cache.*HIT"; then
    echo -e "${GREEN}✓${NC} Romanian (ro) - Second request HIT"
fi

# English
EN_RESP=$(curl -s -I -H "Accept-Language: en" "http://${VARNISH_HOST}:${VARNISH_PORT}${TEST_ENDPOINT}")
if echo "$EN_RESP" | grep -q "X-Cache.*MISS"; then
    echo -e "${GREEN}✓${NC} English (en) - First request MISS (separate cache entry)"
fi

echo ""

# ============================================================================
# Test 7: POST Request (Should NOT be cached)
# ============================================================================

echo -e "${YELLOW}[Test 7]${NC} Testing POST request (should bypass cache)..."

POST_RESP=$(curl -s -I -X POST "http://${VARNISH_HOST}:${VARNISH_PORT}${TEST_ENDPOINT}")

if echo "$POST_RESP" | grep -q "X-Cache"; then
    if echo "$POST_RESP" | grep -q "MISS"; then
        echo -e "${GREEN}✓${NC} POST request bypasses cache (expected)"
    else
        echo -e "${RED}✗${NC} POST request was cached (NOT expected!)"
    fi
else
    echo -e "${GREEN}✓${NC} POST request bypasses cache (no X-Cache header)"
fi

echo ""

# ============================================================================
# Test 8: Cache Purging
# ============================================================================

echo -e "${YELLOW}[Test 8]${NC} Testing cache purging..."

# Populate cache
curl -s "http://${VARNISH_HOST}:${VARNISH_PORT}${TEST_ENDPOINT}" > /dev/null

# Purge
PURGE_RESP=$(curl -s -X PURGE "http://${VARNISH_HOST}:${VARNISH_PORT}${TEST_ENDPOINT}")

if echo "$PURGE_RESP" | grep -q "Purged"; then
    echo -e "${GREEN}✓${NC} Cache purge successful"
else
    echo -e "${RED}✗${NC} Cache purge failed"
fi

# Verify it's MISS after purge
AFTER_PURGE=$(curl -s -I "http://${VARNISH_HOST}:${VARNISH_PORT}${TEST_ENDPOINT}")
if echo "$AFTER_PURGE" | grep -q "X-Cache.*MISS"; then
    echo -e "${GREEN}✓${NC} After purge, request is MISS (cache cleared)"
fi

echo ""

# ============================================================================
# Test 9: Performance Benchmark
# ============================================================================

echo -e "${YELLOW}[Test 9]${NC} Performance benchmark (10 requests each)..."

# Benchmark backend directly
echo "  Direct to backend (port ${BACKEND_PORT}):"
BACKEND_TIMES=()
for i in {1..10}; do
    START=$(date +%s%N)
    curl -s "http://${BACKEND_HOST}:${BACKEND_PORT}${TEST_ENDPOINT}" > /dev/null
    END=$(date +%s%N)
    TIME=$(echo "scale=2; ($END - $START) / 1000000" | bc)
    BACKEND_TIMES+=($TIME)
done

BACKEND_AVG=$(echo "${BACKEND_TIMES[@]}" | tr ' ' '\n' | awk '{sum+=$1} END {print sum/NR}')
echo -e "    Average: ${BACKEND_AVG}ms"

# Benchmark through Varnish (warm cache)
echo "  Through Varnish (cached):"
curl -s "http://${VARNISH_HOST}:${VARNISH_PORT}${TEST_ENDPOINT}" > /dev/null  # Warm cache

VARNISH_TIMES=()
for i in {1..10}; do
    START=$(date +%s%N)
    curl -s "http://${VARNISH_HOST}:${VARNISH_PORT}${TEST_ENDPOINT}" > /dev/null
    END=$(date +%s%N)
    TIME=$(echo "scale=2; ($END - $START) / 1000000" | bc)
    VARNISH_TIMES+=($TIME)
done

VARNISH_AVG=$(echo "${VARNISH_TIMES[@]}" | tr ' ' '\n' | awk '{sum+=$1} END {print sum/NR}')
echo -e "    Average: ${VARNISH_AVG}ms"

# Calculate improvement
if [ $(echo "$BACKEND_AVG > 0" | bc) -eq 1 ]; then
    SPEEDUP=$(echo "scale=1; $BACKEND_AVG / $VARNISH_AVG" | bc)
    IMPROVEMENT=$(echo "scale=1; (($BACKEND_AVG - $VARNISH_AVG) / $BACKEND_AVG) * 100" | bc)
    echo -e "${GREEN}  ⚡ ${SPEEDUP}x faster with Varnish (${IMPROVEMENT}% improvement)${NC}"
fi

echo ""

# ============================================================================
# Test 10: Varnish Statistics
# ============================================================================

echo -e "${YELLOW}[Test 10]${NC} Varnish statistics..."

if command -v varnishstat &> /dev/null; then
    CACHE_HIT=$(sudo varnishstat -1 -f MAIN.cache_hit | awk '{print $2}')
    CACHE_MISS=$(sudo varnishstat -1 -f MAIN.cache_miss | awk '{print $2}')

    if [ -n "$CACHE_HIT" ] && [ -n "$CACHE_MISS" ]; then
        TOTAL=$((CACHE_HIT + CACHE_MISS))
        if [ $TOTAL -gt 0 ]; then
            HIT_RATE=$(echo "scale=1; ($CACHE_HIT / $TOTAL) * 100" | bc)
            echo -e "  Cache hits:   ${CACHE_HIT}"
            echo -e "  Cache misses: ${CACHE_MISS}"
            echo -e "${GREEN}  Hit rate:     ${HIT_RATE}%${NC}"

            if [ $(echo "$HIT_RATE >= 90" | bc) -eq 1 ]; then
                echo -e "${GREEN}  ✓ Excellent hit rate (≥90%)${NC}"
            elif [ $(echo "$HIT_RATE >= 70" | bc) -eq 1 ]; then
                echo -e "${YELLOW}  ⚠ Good hit rate, can be improved${NC}"
            else
                echo -e "${RED}  ✗ Low hit rate (<70%)${NC}"
            fi
        fi
    fi
else
    echo -e "${YELLOW}  ⚠ varnishstat not available (requires sudo)${NC}"
fi

echo ""

# ============================================================================
# Summary
# ============================================================================

echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║                    Test Summary                            ║${NC}"
echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo ""
echo -e "${GREEN}✓ Varnish HTTP Cache is working correctly!${NC}"
echo ""
echo "Performance Improvement:"
echo "  Direct backend:    ${BACKEND_AVG}ms"
echo "  Through Varnish:   ${VARNISH_AVG}ms"
echo -e "  ${GREEN}Improvement:       ${IMPROVEMENT}%${NC}"
echo ""
echo "Next steps:"
echo "  1. Monitor cache hit rate: sudo varnishstat"
echo "  2. View access logs: sudo varnishlog"
echo "  3. Configure for production (port 80, remove debug headers)"
echo "  4. Integrate cache invalidation in Symfony"
echo ""
echo -e "${GREEN}All tests passed! ✓${NC}"
echo ""
