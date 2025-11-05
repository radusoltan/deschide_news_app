#!/bin/bash

# Simple Metrics Collection Script
# Collects metrics from Varnish, PgBouncer and system for monitoring

OUTPUT_FILE="/tmp/deschide_metrics.txt"

cat > "$OUTPUT_FILE" << 'EOF'
# HELP deschide_cache_hit_rate Varnish cache hit rate (0-1)
# TYPE deschide_cache_hit_rate gauge
EOF

# Get Varnish cache hit rate
CACHE_HIT=$(varnishstat -1 -f MAIN.cache_hit 2>/dev/null | awk '{print $2}' || echo "0")
CACHE_MISS=$(varnishstat -1 -f MAIN.cache_miss 2>/dev/null | awk '{print $2}' || echo "0")
CACHE_TOTAL=$((CACHE_HIT + CACHE_MISS))

if [ $CACHE_TOTAL -gt 0 ]; then
    HIT_RATE=$(echo "scale=4; $CACHE_HIT / $CACHE_TOTAL" | bc)
else
    HIT_RATE="0"
fi

echo "deschide_cache_hit_rate $HIT_RATE" >> "$OUTPUT_FILE"

cat >> "$OUTPUT_FILE" << 'EOF'

# HELP deschide_cache_requests_total Total cache requests
# TYPE deschide_cache_requests_total counter
EOF

echo "deschide_cache_requests_total{type=\"hit\"} $CACHE_HIT" >> "$OUTPUT_FILE"
echo "deschide_cache_requests_total{type=\"miss\"} $CACHE_MISS" >> "$OUTPUT_FILE"

# Get PgBouncer pool stats
cat >> "$OUTPUT_FILE" << 'EOF'

# HELP deschide_pgbouncer_clients_active Active client connections
# TYPE deschide_pgbouncer_clients_active gauge
EOF

POOL_STATS=$(PGPASSWORD=sr324395 psql -h 127.0.0.1 -p 6432 -U deschide_admin -d pgbouncer -Atc "SHOW POOLS" 2>/dev/null | grep "^deschide|" || echo "")

if [ -n "$POOL_STATS" ]; then
    CL_ACTIVE=$(echo "$POOL_STATS" | cut -d'|' -f3)
    CL_WAITING=$(echo "$POOL_STATS" | cut -d'|' -f4)
    SV_ACTIVE=$(echo "$POOL_STATS" | cut -d'|' -f5)
    SV_IDLE=$(echo "$POOL_STATS" | cut -d'|' -f6)

    echo "deschide_pgbouncer_clients_active $CL_ACTIVE" >> "$OUTPUT_FILE"

    cat >> "$OUTPUT_FILE" << EOF

# HELP deschide_pgbouncer_clients_waiting Clients waiting for connections
# TYPE deschide_pgbouncer_clients_waiting gauge
deschide_pgbouncer_clients_waiting $CL_WAITING

# HELP deschide_pgbouncer_servers_active Active server connections
# TYPE deschide_pgbouncer_servers_active gauge
deschide_pgbouncer_servers_active $SV_ACTIVE

# HELP deschide_pgbouncer_servers_idle Idle server connections
# TYPE deschide_pgbouncer_servers_idle gauge
deschide_pgbouncer_servers_idle $SV_IDLE
EOF
fi

# Test API response time
cat >> "$OUTPUT_FILE" << 'EOF'

# HELP deschide_api_response_time_seconds API response time in seconds
# TYPE deschide_api_response_time_seconds gauge
EOF

API_TIME=$(curl -s -o /dev/null -w "%{time_total}" http://127.0.0.1:6081/api/articles 2>/dev/null || echo "0")
echo "deschide_api_response_time_seconds $API_TIME" >> "$OUTPUT_FILE"

# Output metrics
cat "$OUTPUT_FILE"
