# P1 Performance Optimization - Quick Start Guide

**For**: System Administrators
**Time**: 30 minutes - 6 hours (depending on optimizations chosen)
**Goal**: Achieve p95 < 500ms at 1000+ concurrent users

---

## 🚀 Quick Status Check

```bash
cd /var/www/deschide_news_app

# Check current configuration
./scripts/verify-optimizations.sh

# Test current performance (single user)
./scripts/test-response-time.sh http://127.0.0.1:8081/api/articles
```

---

## ✅ Phase 1: Already Complete

The following optimizations are **already implemented**:

- ✅ Redis memory limits (512MB + LRU eviction)
- ✅ PHP-FPM scaling (100 workers)
- ✅ Doctrine query caching (Redis + APCu)
- ✅ Performance test scripts

**Current Performance**:
- Single user: **30ms p95** ✅
- 500 users: **3550ms p95** ❌ (needs Phase 2)

---

## 🔧 Phase 2: Critical Optimizations (Required)

### Option 1: Quick Win (30 minutes) 🟢

**Install PgBouncer for Database Connection Pooling**

Expected Impact: **p95: 3550ms → ~500-800ms**

```bash
# 1. Install PgBouncer
sudo apt-get update
sudo apt-get install -y pgbouncer

# 2. Configure PgBouncer
sudo nano /etc/pgbouncer/pgbouncer.ini
```

Add configuration:
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
```

```bash
# 3. Create authentication file
sudo nano /etc/pgbouncer/userlist.txt
# Add: "deschide_user" "md5<password_hash>"

# 4. Start PgBouncer
sudo systemctl enable pgbouncer
sudo systemctl start pgbouncer

# 5. Update Symfony database URL
nano apps/backend/.env.local
# Change: postgresql://...@127.0.0.1:5432/...
# To:     postgresql://...@127.0.0.1:6432/...

# 6. Clear cache and test
cd apps/backend
symfony console cache:clear
symfony console doctrine:query:sql "SELECT 1"
```

**Verify**:
```bash
./scripts/test-response-time.sh http://127.0.0.1:8081/api/articles
# Expected: p95 < 800ms (much better than 3550ms)
```

---

### Option 2: Maximum Performance (5 minutes) 🟡

**Enable OPcache JIT**

Expected Impact: **+20-30% performance boost**

```bash
# 1. Edit OPcache configuration
sudo nano /etc/php/8.4/mods-available/opcache.ini
```

Change:
```ini
# BEFORE:
opcache.jit=off
opcache.jit_buffer_size=0

# AFTER:
opcache.jit=1255
opcache.jit_buffer_size=128M
```

```bash
# 2. Restart PHP-FPM
sudo systemctl restart php8.4-fpm

# 3. Restart Symfony server
cd apps/backend
symfony server:stop
symfony server:start -d --port=8081

# 4. Verify JIT is enabled
php -i | grep -E "opcache.jit|opcache.jit_buffer_size"
# Expected:
# opcache.jit => 1255 => 1255
# opcache.jit_buffer_size => 134217728 => 134217728
```

---

### Option 3: Frontend Optimization (2-4 hours) 🟠

**Fix Homepage Performance Bottleneck**

Current Issue: **p95 = 7918ms** at 100 users

**Quick Fixes**:

1. **Enable Next.js ISR** (Incremental Static Regeneration)
   ```typescript
   // apps/frontend/app/[locale]/page.tsx
   export const revalidate = 60; // Revalidate every 60 seconds
   ```

2. **Reduce API Calls**
   - Create single aggregated endpoint for homepage
   - Cache API responses in frontend

3. **Add Frontend Caching**
   ```typescript
   // Use React Query or SWR for client-side caching
   import { useQuery } from '@tanstack/react-query';

   const { data } = useQuery({
     queryKey: ['homepage'],
     queryFn: fetchHomepageData,
     staleTime: 60000, // 60 seconds
   });
   ```

4. **Optimize Images**
   - Use Next.js Image component
   - Implement lazy loading
   - Add proper sizing hints

---

## 🧪 Testing & Validation

### After Each Optimization

```bash
# 1. Test response time
./scripts/test-response-time.sh http://127.0.0.1:8081/api/articles

# 2. Verify configuration
./scripts/verify-optimizations.sh

# 3. Run load test (API only)
cd k6
VUS=500 DURATION=60s k6 run api-only-load-test.js
```

### Final Validation (After All Optimizations)

```bash
# Test with 1000 concurrent users
cd k6
VUS=1000 DURATION=60s k6 run api-only-load-test.js

# Check results:
# - p95 < 500ms ✅
# - Error rate < 1% ✅
# - Throughput > 500 req/s ✅
```

---

## 📊 Expected Results

| Optimization | Time | p95 Improvement | Total p95 |
|--------------|------|-----------------|-----------|
| **Baseline** | - | - | 3550ms |
| **+ PgBouncer** | 30 min | -77% | ~800ms |
| **+ OPcache JIT** | 5 min | -25% | ~600ms |
| **+ Query Optimization** | 1-2 hours | -20% | ~480ms |
| **= TOTAL** | 2-3 hours | **-86%** | **< 500ms** ✅ |

---

## 🎯 Recommended Path

### Fast Track (30-40 minutes)

1. ✅ Install PgBouncer (30 min)
2. ✅ Enable OPcache JIT (5 min)
3. ✅ Test with 500 VUs (5 min)

**Expected Result**: p95 ~600ms (close to 500ms target)

---

### Complete Optimization (4-6 hours)

1. ✅ Install PgBouncer (30 min)
2. ✅ Enable OPcache JIT (5 min)
3. ✅ Optimize Frontend Homepage (2-4 hours)
4. ✅ Review Slow Queries (1-2 hours)
5. ✅ Final Load Test with 1000 VUs (30 min)

**Expected Result**: p95 < 500ms at 1000+ concurrent users ✅

---

## 🆘 Troubleshooting

### PgBouncer Connection Failed

```bash
# Check PgBouncer status
sudo systemctl status pgbouncer

# View logs
sudo journalctl -u pgbouncer -n 50

# Test direct connection
psql -h 127.0.0.1 -p 6432 -U deschide_user pgbouncer -c "SHOW POOLS;"
```

### Symfony Server Not Starting

```bash
# Check port availability
ss -tulpn | grep -E ":(3005|8081)"

# Stop and restart
symfony server:stop
symfony server:start -d --port=8081

# Check logs
symfony server:log
```

### Performance Tests Failing

```bash
# Ensure backend is running
curl http://127.0.0.1:8081/api

# Check Redis
redis-cli ping
# Expected: PONG

# Check PostgreSQL
psql -U deschide_user -d deschide_news -c "SELECT 1;"
# Expected: 1
```

---

## 📚 Documentation

- **Full Implementation Guide**: `P1_PERFORMANCE_OPTIMIZATIONS.md`
- **Detailed Report**: `P1_PERFORMANCE_OPTIMIZATION_REPORT.md`
- **Quick Summary**: `P1_OPTIMIZATION_SUMMARY.md`
- **This Guide**: `P1_QUICK_START.md`

---

## 📞 Next Steps

1. **Choose your path**: Fast Track (40 min) or Complete (4-6 hours)
2. **Implement optimizations** following this guide
3. **Test and verify** using provided scripts
4. **Document results** for production deployment

---

**Last Updated**: 2025-12-09
**Status**: Ready to implement Phase 2
**Estimated Time to Target**: 40 minutes - 6 hours
