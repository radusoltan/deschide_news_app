#!/bin/bash
#
# Response Time Testing Script
# Tests API response times and calculates p95
#

set -e

ENDPOINT="${1:-http://127.0.0.1:8081/api/articles}"
NUM_REQUESTS="${2:-20}"
RESULTS_FILE="/tmp/response_times_$(date +%s).txt"

echo "=========================================="
echo "API Response Time Test"
echo "=========================================="
echo "Endpoint: $ENDPOINT"
echo "Requests: $NUM_REQUESTS"
echo "Results file: $RESULTS_FILE"
echo ""

# Clear results file
> "$RESULTS_FILE"

echo "Running requests..."
for i in $(seq 1 $NUM_REQUESTS); do
  TIME=$(curl -w "%{time_total}" -s -o /dev/null "$ENDPOINT")
  echo "$TIME" >> "$RESULTS_FILE"
  printf "Request %3d: %s s\n" "$i" "$TIME"
done

echo ""
echo "=========================================="
echo "Results"
echo "=========================================="

# Calculate statistics
sort -n "$RESULTS_FILE" > "${RESULTS_FILE}.sorted"

# p50 (median)
P50=$(awk '{all[NR] = $0} END{print all[int(NR*0.50+0.5)]}' "${RESULTS_FILE}.sorted")
echo "p50 (median): ${P50}s"

# p75
P75=$(awk '{all[NR] = $0} END{print all[int(NR*0.75+0.5)]}' "${RESULTS_FILE}.sorted")
echo "p75:          ${P75}s"

# p90
P90=$(awk '{all[NR] = $0} END{print all[int(NR*0.90+0.5)]}' "${RESULTS_FILE}.sorted")
echo "p90:          ${P90}s"

# p95 (target metric)
P95=$(awk '{all[NR] = $0} END{print all[int(NR*0.95+0.5)]}' "${RESULTS_FILE}.sorted")
echo "p95:          ${P95}s"

# p99
P99=$(awk '{all[NR] = $0} END{print all[int(NR*0.99+0.5)]}' "${RESULTS_FILE}.sorted")
echo "p99:          ${P99}s"

# Average
AVG=$(awk '{sum+=$1} END {print sum/NR}' "$RESULTS_FILE")
echo "Average:      ${AVG}s"

# Min/Max
MIN=$(head -1 "${RESULTS_FILE}.sorted")
MAX=$(tail -1 "${RESULTS_FILE}.sorted")
echo "Min:          ${MIN}s"
echo "Max:          ${MAX}s"

echo ""
echo "=========================================="
echo "Target Validation"
echo "=========================================="

# Check if p95 meets target (< 0.500s = 500ms)
P95_MS=$(echo "$P95 * 1000" | bc)
TARGET_MS=500

if (( $(echo "$P95_MS < $TARGET_MS" | bc -l) )); then
  echo "✅ SUCCESS: p95 ${P95}s (${P95_MS}ms) < ${TARGET_MS}ms target"
  EXIT_CODE=0
else
  echo "❌ FAILED: p95 ${P95}s (${P95_MS}ms) >= ${TARGET_MS}ms target"
  EXIT_CODE=1
fi

echo ""
echo "Results saved to: $RESULTS_FILE"

# Cleanup sorted file
rm "${RESULTS_FILE}.sorted"

exit $EXIT_CODE
