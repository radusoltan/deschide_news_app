# P1 HIGH PRIORITY Performance Optimizations Implementation Guide

**Date**: 2025-12-09
**Objective**: API p95 < 500ms, Support 1000+ concurrent users, Production-ready Redis

## Pre-Implementation Checklist

- [x] Backup configurations created
- [ ] All tests passing (verify: 539 tests)
- [ ] Current baseline metrics recorded
- [ ] Rollback plan documented

## Phase 1: Infrastructure Optimization (15 minutes)

### Fix 1: OPcache JIT Configuration ⚡ CRITICAL

**File**: `/etc/php/8.4/mods-available/opcache.ini`

**Backup command**:
```bash
sudo cp /etc/php/8.4/mods-available/opcache.ini /etc/php/8.4/mods-available/opcache.ini.backup
```

**Changes**:
```bash
sudo nano /etc/php/8.4/mods-available/opcache.ini
```

Replace lines 27-29:
```ini
# BEFORE:
opcache.jit=off
opcache.jit_buffer_size=0

# AFTER:
opcache.jit=1255
opcache.jit_buffer_size=128M
```

**Additional optimization** (optional, for development - set revalidate_freq=0 for faster revalidation):
```ini
# Line 19 - for development (instant revalidation)
opcache.revalidate_freq=0

# Line 18 - for production (disable timestamp checks)
# opcache.validate_timestamps=0
```

**Restart PHP-FPM**:
```bash
# For system PHP-FPM
sudo systemctl restart php8.4-fpm

# For Symfony local server (automatically picks up changes)
cd /var/www/deschide_news_app/apps/backend
symfony server:stop
symfony server:start -d --port=8081
```

**Verify**:
```bash
php -i | grep -E "opcache.jit|opcache.jit_buffer_size"
# Expected output:
# opcache.jit => 1255 => 1255
# opcache.jit_buffer_size => 134217728 => 134217728
```

**Expected Impact**: 20-30% performance boost on PHP execution

---

### Fix 2: Redis Memory Configuration ⚡ CRITICAL

**File**: `/etc/redis/redis.conf`

**Backup command**:
```bash
sudo cp /etc/redis/redis.conf /etc/redis/redis.conf.backup
```

**Changes**:
```bash
sudo nano /etc/redis/redis.conf
```

Add/modify these lines (search for existing settings or add at the end):
```conf
# Maximum memory allocation (512MB for development, adjust for production)
maxmemory 512mb

# Eviction policy: remove least recently used keys when memory limit reached
maxmemory-policy allkeys-lru

# Number of samples for LRU algorithm (higher = more accurate, but slower)
maxmemory-samples 5

# Save to disk periodically (optional, for persistence)
save 900 1
save 300 10
save 60 10000
```

**Current status**:
```bash
redis-cli CONFIG GET maxmemory
# Current: 536870912 (512MB already set ✓)

redis-cli CONFIG GET maxmemory-policy
# Check current policy
```

**Apply changes without restart** (for immediate effect):
```bash
redis-cli CONFIG SET maxmemory-policy allkeys-lru
redis-cli CONFIG SET maxmemory-samples 5
redis-cli CONFIG REWRITE
```

**Restart Redis** (to load from config file):
```bash
sudo systemctl restart redis-server
```

**Verify**:
```bash
redis-cli INFO memory | grep -E "maxmemory|maxmemory_policy"
redis-cli CONFIG GET maxmemory-policy
# Expected: allkeys-lru
```

**Expected Impact**: Prevent Redis crashes when memory full, automatic cache eviction

---

### Fix 3: PHP-FPM Scaling for Concurrency ⚡ HIGH PRIORITY

**File**: `/home/radu/.symfony5/php/32ddae0a98131804102ded73181a2de396ecd7ac/fpm-8.4.14.ini`

**Backup command**:
```bash
cp /home/radu/.symfony5/php/32ddae0a98131804102ded73181a2de396ecd7ac/fpm-8.4.14.ini /home/radu/.symfony5/php/32ddae0a98131804102ded73181a2de396ecd7ac/fpm-8.4.14.ini.backup
```

**Changes**:
```bash
nano /home/radu/.symfony5/php/32ddae0a98131804102ded73181a2de396ecd7ac/fpm-8.4.14.ini
```

Replace lines 12-16:
```ini
# BEFORE:
pm = dynamic
pm.max_children = 30
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3

# AFTER (for 1000+ concurrent users):
pm = dynamic
pm.max_children = 100
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500
```

**Restart Symfony server**:
```bash
cd /var/www/deschide_news_app/apps/backend
symfony server:stop
symfony server:start -d --port=8081
symfony server:status
```

**Verify**:
```bash
symfony server:log | grep "max_children"
# or
ps aux | grep php-fpm | wc -l
# Should show more PHP-FPM worker processes
```

**Expected Impact**: Handle 4x more concurrent requests (200-300 → 1000+)

---

## Phase 2: Database Optimization (20 minutes)

### Fix 4: PostgreSQL Connection Pooling (PgBouncer) ⚡ CRITICAL

**Installation**:
```bash
sudo apt-get update
sudo apt-get install -y pgbouncer
```

**Configuration file**: `/etc/pgbouncer/pgbouncer.ini`

**Backup**:
```bash
sudo cp /etc/pgbouncer/pgbouncer.ini /etc/pgbouncer/pgbouncer.ini.backup
```

**Configure**:
```bash
sudo nano /etc/pgbouncer/pgbouncer.ini
```

**Settings**:
```ini
[databases]
deschide_news = host=127.0.0.1 port=5432 dbname=deschide_news user=deschide_user

[pgbouncer]
listen_addr = 127.0.0.1
listen_port = 6432
auth_type = md5
auth_file = /etc/pgbouncer/userlist.txt
pool_mode = transaction
max_client_conn = 1000
default_pool_size = 25
min_pool_size = 5
reserve_pool_size = 5
reserve_pool_timeout = 3
max_db_connections = 100
max_user_connections = 100
server_idle_timeout = 600
server_lifetime = 3600
server_connect_timeout = 15
query_wait_timeout = 120
```

**Create userlist** (for authentication):
```bash
sudo nano /etc/pgbouncer/userlist.txt
```

Add (get password hash from PostgreSQL):
```
"deschide_user" "md5<password_hash>"
```

To generate password hash:
```bash
# In PostgreSQL:
psql -U postgres -d deschide_news -c "SELECT 'md5' || md5('password' || 'deschide_user');"
# Replace 'password' with actual password
```

**Start PgBouncer**:
```bash
sudo systemctl enable pgbouncer
sudo systemctl start pgbouncer
sudo systemctl status pgbouncer
```

**Update Symfony database connection**:

File: `/var/www/deschide_news_app/apps/backend/.env.local`

```bash
# BEFORE:
DATABASE_URL="postgresql://deschide_user:password@127.0.0.1:5432/deschide_news"

# AFTER (use PgBouncer port 6432):
DATABASE_URL="postgresql://deschide_user:password@127.0.0.1:6432/deschide_news"
```

**Test connection**:
```bash
cd /var/www/deschide_news_app/apps/backend
symfony console doctrine:query:sql "SELECT 1"
# Should return: 1
```

**Verify PgBouncer stats**:
```bash
psql -h 127.0.0.1 -p 6432 -U deschide_user pgbouncer -c "SHOW POOLS;"
psql -h 127.0.0.1 -p 6432 -U deschide_user pgbouncer -c "SHOW STATS;"
```

**Expected Impact**: Reduce database connection overhead by 60-80%, support 4x more users

---

### Fix 5: Doctrine Query Result Caching ⚡ HIGH PRIORITY

**Files to modify**:
1. `/var/www/deschide_news_app/apps/backend/config/packages/doctrine.yaml`
2. `/var/www/deschide_news_app/apps/backend/config/packages/cache.yaml`

**Already implemented** - See generated configuration files in this directory.

**Apply changes**:
```bash
cd /var/www/deschide_news_app/apps/backend

# Clear cache
symfony console cache:clear

# Verify configuration
symfony console debug:config doctrine
symfony console debug:config framework cache
```

**Test caching**:
```bash
# First request (cache MISS)
time curl -s http://127.0.0.1:8081/api/articles?itemsPerPage=30 > /dev/null

# Second request (cache HIT - should be faster)
time curl -s http://127.0.0.1:8081/api/articles?itemsPerPage=30 > /dev/null
```

**Monitor Redis cache**:
```bash
redis-cli MONITOR
# Make API requests and watch cache operations
```

**Expected Impact**: Reduce database queries by 60-80% for cached results

---

### Fix 6: Review Slow Queries ⚡ MEDIUM PRIORITY

**Enable Symfony profiler** (already enabled in dev):

File: `/var/www/deschide_news_app/apps/backend/config/packages/web_profiler.yaml`

**Test and identify slow queries**:
```bash
# Make test requests
curl http://127.0.0.1:8081/api/articles
curl http://127.0.0.1:8081/api/categories
curl http://127.0.0.1:8081/api/important_articles

# Check profiler (open in browser):
# http://127.0.0.1:8081/_profiler/
# Look for "Doctrine" section to see query times
```

**Enable PostgreSQL slow query log**:
```bash
sudo nano /etc/postgresql/17/main/postgresql.conf
```

Add/modify:
```conf
log_min_duration_statement = 100  # Log queries > 100ms
log_line_prefix = '%t [%p]: [%l-1] user=%u,db=%d,app=%a,client=%h '
log_statement = 'none'
log_duration = on
```

Restart PostgreSQL:
```bash
sudo systemctl restart postgresql
```

**Check slow queries**:
```bash
sudo tail -f /var/log/postgresql/postgresql-17-main.log | grep "duration:"
```

**Expected Impact**: Identify queries > 100ms for targeted optimization

---

## Phase 3: Advanced Optimization (Optional - 15 minutes)

### Fix 7: RabbitMQ Async Processing (If needed)

**File**: `/var/www/deschide_news_app/apps/backend/config/packages/messenger.yaml`

**Uncomment async transport**:
```yaml
framework:
    messenger:
        transports:
            async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    exchange:
                        name: deschide_messages
                        type: topic
                retry_strategy:
                    max_retries: 3
                    delay: 1000
                    multiplier: 2

        routing:
            # Route messages to async transport
            'App\Message\GenerateThumbnailMessage': async
            'App\Message\SendEmailMessage': async
```

**Start workers**:
```bash
cd /var/www/deschide_news_app/apps/backend
symfony console messenger:consume async -vv
```

**Monitor queue**:
```bash
# RabbitMQ management UI
http://localhost:15672
# Login: guest/guest
```

---

## Phase 4: Verification (20 minutes)

### Test 1: Load Testing with k6

**Prerequisites**:
```bash
# Check if k6 is installed
k6 version

# If not installed:
# curl https://github.com/grafana/k6/releases/download/v0.48.0/k6-v0.48.0-linux-amd64.tar.gz -L | tar xvz
# sudo cp k6-v0.48.0-linux-amd64/k6 /usr/local/bin
```

**Run load test**:
```bash
cd /var/www/deschide_news_app
./scripts/run-load-tests.sh

# Or manually:
cd k6
k6 run --vus 1000 --duration 60s tests/api-load-test.js
```

**Success criteria**:
- ✅ Error rate < 1%
- ✅ p95 response time < 500ms
- ✅ Throughput > 500 req/s

---

### Test 2: Response Time Validation

**Script**: `/var/www/deschide_news_app/scripts/test-response-time.sh`

```bash
#!/bin/bash
echo "Testing API response times (20 requests)..."

ENDPOINT="http://127.0.0.1:8081/api/articles"
RESULTS_FILE="/tmp/response_times.txt"

> $RESULTS_FILE

for i in {1..20}; do
  TIME=$(curl -w "%{time_total}" -s -o /dev/null $ENDPOINT)
  echo "$TIME" >> $RESULTS_FILE
  echo "Request $i: ${TIME}s"
done

# Calculate p95
echo ""
echo "Calculating p95..."
sort -n $RESULTS_FILE | awk '{all[NR] = $0} END{print "p95: " all[int(NR*0.95)]}'

# Calculate average
awk '{sum+=$1} END {print "Average: " sum/NR}' $RESULTS_FILE
```

**Run**:
```bash
cd /var/www/deschide_news_app
chmod +x scripts/test-response-time.sh
./scripts/test-response-time.sh
```

**Success criteria**:
- ✅ p95 < 500ms (0.500s)
- ✅ Average < 300ms

---

### Test 3: Regression Testing

**Run all tests**:
```bash
cd /var/www/deschide_news_app/apps/backend
XDEBUG_MODE=off vendor/bin/phpunit tests/ --no-coverage

cd /var/www/deschide_news_app/apps/frontend
pnpm test
```

**Success criteria**:
- ✅ All 539 tests passing (376 backend + 163 frontend)
- ✅ No new errors or warnings
- ✅ No performance regressions

---

## Monitoring and Validation

### Check OPcache Statistics
```bash
# OPcache status
php -r "print_r(opcache_get_status());"

# JIT statistics
php -r "print_r(opcache_get_status()['jit']);"
```

### Check Redis Memory Usage
```bash
redis-cli INFO memory
redis-cli INFO stats
```

### Check PHP-FPM Status
```bash
symfony server:status
ps aux | grep php-fpm | wc -l
```

### Check PgBouncer Pool Status
```bash
psql -h 127.0.0.1 -p 6432 -U deschide_user pgbouncer -c "SHOW POOLS;"
psql -h 127.0.0.1 -p 6432 -U deschide_user pgbouncer -c "SHOW STATS;"
```

### Monitor API Performance
```bash
# Watch response times
while true; do
  curl -w "Time: %{time_total}s\n" -s -o /dev/null http://127.0.0.1:8081/api/articles
  sleep 1
done
```

---

## Rollback Procedures

### Rollback OPcache JIT
```bash
sudo cp /etc/php/8.4/mods-available/opcache.ini.backup /etc/php/8.4/mods-available/opcache.ini
sudo systemctl restart php8.4-fpm
cd /var/www/deschide_news_app/apps/backend && symfony server:stop && symfony server:start -d --port=8081
```

### Rollback Redis Configuration
```bash
sudo cp /etc/redis/redis.conf.backup /etc/redis/redis.conf
sudo systemctl restart redis-server
```

### Rollback PHP-FPM Configuration
```bash
cp /home/radu/.symfony5/php/32ddae0a98131804102ded73181a2de396ecd7ac/fpm-8.4.14.ini.backup /home/radu/.symfony5/php/32ddae0a98131804102ded73181a2de396ecd7ac/fpm-8.4.14.ini
cd /var/www/deschide_news_app/apps/backend && symfony server:stop && symfony server:start -d --port=8081
```

### Rollback PgBouncer
```bash
# Stop PgBouncer
sudo systemctl stop pgbouncer

# Revert DATABASE_URL in .env.local
# Change port from 6432 back to 5432

# Clear cache
cd /var/www/deschide_news_app/apps/backend
symfony console cache:clear
```

### Rollback Doctrine Caching
```bash
# Comment out result_cache_driver and query_cache_driver in doctrine.yaml
cd /var/www/deschide_news_app/apps/backend
symfony console cache:clear
```

---

## Expected Performance Improvements

| Metric | Before | After | Improvement |
|--------|--------|-------|-------------|
| **p95 Response Time** | 753ms | < 500ms | **-33% or better** |
| **Concurrent Users** | 200-300 | 1000+ | **+300%** |
| **Throughput** | ~200 req/s | 500+ req/s | **+150%** |
| **Database Queries** | 100% | 20-40% | **-60-80%** (cached) |
| **PHP Execution** | Baseline | -20-30% | **JIT boost** |
| **Memory Safety** | ⚠️ Unlimited | ✅ 512MB LRU | **Production-ready** |

---

## Post-Implementation Checklist

- [ ] All configuration files backed up
- [ ] OPcache JIT enabled and verified
- [ ] Redis memory limits configured
- [ ] PHP-FPM workers scaled to 100
- [ ] PgBouncer installed and configured
- [ ] Doctrine query caching enabled
- [ ] Slow query logging enabled
- [ ] Load tests pass (1000+ VUs)
- [ ] p95 response time < 500ms
- [ ] All 539 tests still passing
- [ ] Performance metrics documented
- [ ] Production deployment plan created

---

## Next Steps

1. ✅ Implement Phase 1 (Infrastructure)
2. ✅ Implement Phase 2 (Database)
3. ✅ Run verification tests
4. 📝 Document final metrics
5. 🚀 Plan production deployment

---

**Generated**: 2025-12-09
**Status**: Ready for implementation
**Estimated Total Time**: 50-70 minutes
