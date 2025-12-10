#!/bin/bash
# Cache Performance Monitoring Script for Deschide News App
# Tests and reports on Redis (L2) cache performance

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║         Deschide News - Cache Performance Report              ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Check if Redis is running
echo -n "Checking Redis connectivity... "
if redis-cli -n 1 PING > /dev/null 2>&1; then
    echo -e "${GREEN}✓ Connected${NC}"
else
    echo -e "${RED}✗ Failed${NC}"
    echo "Error: Cannot connect to Redis. Please ensure Redis is running."
    exit 1
fi
echo ""

# Redis Stats
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📦 Redis (L2 Cache) - DB 1"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Get Redis stats
HITS=$(redis-cli -n 1 INFO stats 2>/dev/null | grep keyspace_hits | cut -d: -f2 | tr -d '\r')
MISSES=$(redis-cli -n 1 INFO stats 2>/dev/null | grep keyspace_misses | cut -d: -f2 | tr -d '\r')
MEMORY=$(redis-cli -n 1 INFO memory 2>/dev/null | grep used_memory_human | cut -d: -f2 | tr -d '\r')
KEYS=$(redis-cli -n 1 DBSIZE 2>/dev/null | awk '{print $2}')
EVICTED=$(redis-cli -n 1 INFO stats 2>/dev/null | grep evicted_keys | cut -d: -f2 | tr -d '\r')
EXPIRED=$(redis-cli -n 1 INFO stats 2>/dev/null | grep expired_keys | cut -d: -f2 | tr -d '\r')

# Calculate hit rate
if [ -n "$HITS" ] && [ -n "$MISSES" ]; then
    TOTAL=$((HITS + MISSES))
    if [ "$TOTAL" -gt 0 ]; then
        HIT_RATE=$(echo "scale=2; $HITS * 100 / $TOTAL" | bc)

        # Color code hit rate
        if (( $(echo "$HIT_RATE >= 80" | bc -l) )); then
            COLOR=$GREEN
        elif (( $(echo "$HIT_RATE >= 60" | bc -l) )); then
            COLOR=$YELLOW
        else
            COLOR=$RED
        fi

        echo -e "Hit Rate:     ${COLOR}${HIT_RATE}%${NC} (target: ≥80%)"
    else
        echo "Hit Rate:     N/A (no operations yet)"
    fi
    echo "Hits:         $(printf "%'d" "$HITS")"
    echo "Misses:       $(printf "%'d" "$MISSES")"
else
    echo "Hit Rate:     N/A"
fi

echo "Memory Used:  ${MEMORY:-N/A}"
echo "Total Keys:   ${KEYS:-0}"
echo "Evicted Keys: ${EVICTED:-0}"
echo "Expired Keys: ${EXPIRED:-0}"
echo ""

# Test Redis response time
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "⚡ Response Time Test"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

ITERATIONS=10
echo "Running ${ITERATIONS} write + read operations..."

START=$(date +%s%N)
for i in $(seq 1 $ITERATIONS); do
    redis-cli -n 1 SET "perf_test_$i" "value_$i" EX 60 > /dev/null 2>&1
    redis-cli -n 1 GET "perf_test_$i" > /dev/null 2>&1
done
END=$(date +%s%N)

DURATION=$(( (END - START) / 1000000 ))
TOTAL_OPS=$((ITERATIONS * 2))
AVG=$(echo "scale=2; $DURATION / $TOTAL_OPS" | bc)

# Color code response time
if (( $(echo "$AVG < 5" | bc -l) )); then
    COLOR=$GREEN
elif (( $(echo "$AVG < 10" | bc -l) )); then
    COLOR=$YELLOW
else
    COLOR=$RED
fi

echo -e "Total Time:   ${DURATION}ms for ${TOTAL_OPS} operations"
echo -e "Average:      ${COLOR}${AVG}ms${NC} per operation (target: <5ms)"
echo ""

# Cleanup test keys
for i in $(seq 1 $ITERATIONS); do
    redis-cli -n 1 DEL "perf_test_$i" > /dev/null 2>&1
done

# Key size distribution
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📊 Key Distribution"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Get all keys and analyze patterns
TOTAL_KEYS=$(redis-cli -n 1 DBSIZE 2>/dev/null | awk '{print $2}')

if [ -n "$TOTAL_KEYS" ] && [ "$TOTAL_KEYS" -gt 0 ]; then
    # Count keys by prefix (first part before colon)
    echo "Keys by prefix:"
    redis-cli -n 1 KEYS "*" 2>/dev/null | sed 's/:.*//' | sort | uniq -c | sort -rn | head -5 | while read count prefix; do
        echo "  $prefix: $count keys"
    done
else
    echo "No keys found in cache"
fi

echo ""

# Sample cache keys
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🔑 Sample Cache Keys (first 10)"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

SAMPLE_KEYS=$(redis-cli -n 1 KEYS "*" 2>/dev/null | head -10)

if [ -n "$SAMPLE_KEYS" ]; then
    echo "$SAMPLE_KEYS" | while IFS= read -r key; do
        if [ -n "$key" ]; then
            # Get key type and TTL
            TYPE=$(redis-cli -n 1 TYPE "$key" 2>/dev/null)
            TTL=$(redis-cli -n 1 TTL "$key" 2>/dev/null)

            if [ "$TTL" -eq -1 ]; then
                TTL_STR="no expiry"
            elif [ "$TTL" -eq -2 ]; then
                TTL_STR="expired"
            else
                TTL_STR="${TTL}s"
            fi

            echo "  $key"
            echo "    Type: $TYPE | TTL: $TTL_STR"
        fi
    done
else
    echo "  (No keys in cache)"
fi

echo ""

# Memory usage by key patterns (if enough keys exist)
if [ -n "$TOTAL_KEYS" ] && [ "$TOTAL_KEYS" -gt 0 ]; then
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "💾 Memory Usage"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

    # Get memory info
    USED_MEMORY=$(redis-cli -n 1 INFO memory 2>/dev/null | grep "used_memory:" | cut -d: -f2 | tr -d '\r')
    USED_MEMORY_RSS=$(redis-cli -n 1 INFO memory 2>/dev/null | grep "used_memory_rss:" | cut -d: -f2 | tr -d '\r')
    USED_MEMORY_PEAK=$(redis-cli -n 1 INFO memory 2>/dev/null | grep "used_memory_peak:" | cut -d: -f2 | tr -d '\r')

    echo "Used Memory:      $(numfmt --to=iec $USED_MEMORY 2>/dev/null || echo "${USED_MEMORY} bytes")"
    echo "RSS Memory:       $(numfmt --to=iec $USED_MEMORY_RSS 2>/dev/null || echo "${USED_MEMORY_RSS} bytes")"
    echo "Peak Memory:      $(numfmt --to=iec $USED_MEMORY_PEAK 2>/dev/null || echo "${USED_MEMORY_PEAK} bytes")"

    if [ "$TOTAL_KEYS" -gt 0 ]; then
        AVG_KEY_SIZE=$((USED_MEMORY / TOTAL_KEYS))
        echo "Avg per key:      $(numfmt --to=iec $AVG_KEY_SIZE 2>/dev/null || echo "${AVG_KEY_SIZE} bytes")"
    fi

    echo ""
fi

# Performance summary
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📈 Performance Summary"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Evaluate performance
STATUS="${GREEN}✓ GOOD${NC}"
ISSUES=0

if [ -n "$HIT_RATE" ]; then
    if (( $(echo "$HIT_RATE < 80" | bc -l) )); then
        echo -e "${YELLOW}⚠ Hit rate below target (${HIT_RATE}% < 80%)${NC}"
        ISSUES=$((ISSUES + 1))
    fi
fi

if (( $(echo "$AVG >= 10" | bc -l) )); then
    echo -e "${YELLOW}⚠ Average response time above threshold (${AVG}ms >= 10ms)${NC}"
    ISSUES=$((ISSUES + 1))
fi

if [ "$EVICTED" -gt 0 ]; then
    echo -e "${YELLOW}⚠ Keys being evicted (${EVICTED} total) - consider increasing memory${NC}"
    ISSUES=$((ISSUES + 1))
fi

if [ "$ISSUES" -eq 0 ]; then
    echo -e "${GREEN}✓ All metrics within acceptable ranges${NC}"
else
    echo -e "${YELLOW}⚠ ${ISSUES} issue(s) detected${NC}"
fi

echo ""

# Recommendations
if [ "$ISSUES" -gt 0 ]; then
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "💡 Recommendations"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

    if [ -n "$HIT_RATE" ] && (( $(echo "$HIT_RATE < 80" | bc -l) )); then
        echo "• Increase cache TTL for frequently accessed data"
        echo "• Review cache invalidation strategy"
        echo "• Consider pre-warming cache for common queries"
    fi

    if (( $(echo "$AVG >= 10" | bc -l) )); then
        echo "• Check Redis server load and network latency"
        echo "• Consider Redis optimization (persistence settings, etc.)"
    fi

    if [ "$EVICTED" -gt 0 ]; then
        echo "• Increase Redis max memory limit"
        echo "• Review cache eviction policy (current: allkeys-lru recommended)"
        echo "• Consider removing less important cached data"
    fi

    echo ""
fi

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ Cache Performance Report Complete"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "Generated: $(date '+%Y-%m-%d %H:%M:%S')"
