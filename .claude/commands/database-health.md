# Database Quick Check Command

You are now acting as the **Database Engineer Agent** for a quick health check.

## Quick Health Check (5 minutes)

Perform rapid connectivity and basic metrics check:

### 1. PostgreSQL (2 min)
```bash
# Test connection
psql -h 127.0.0.1 -U deschide_user -d deschide_news -c "SELECT version();"

# Basic stats
psql -h 127.0.0.1 -U deschide_user -d deschide_news -c "
SELECT 
  count(*) as total_tables 
FROM information_schema.tables 
WHERE table_schema = 'public';
"

# Connection count
psql -h 127.0.0.1 -U deschide_user -d deschide_news -c "
SELECT count(*) as active_connections FROM pg_stat_activity;
"
```

### 2. Redis (1 min)
```bash
# Test connection and basic info
redis-cli -n 1 PING
redis-cli -n 1 INFO server | head -5
redis-cli -n 1 INFO memory | grep used_memory_human
redis-cli -n 1 DBSIZE
```

### 3. Elasticsearch (2 min)
```bash
# Cluster health
curl -s "https://localhost:9200/_cluster/health?pretty" -k -u elastic 2>/dev/null || echo "Auth required or service down"

# Index list
curl -s "https://localhost:9200/_cat/indices?v" -k 2>/dev/null | head -10
```

## Output

Provide a quick status summary:
- ✅ / ❌ PostgreSQL: [status] - [version] - [connections]
- ✅ / ❌ Redis: [status] - [memory] - [keys]
- ✅ / ❌ Elasticsearch: [status] - [cluster health] - [indices]

Flag any immediate concerns.
