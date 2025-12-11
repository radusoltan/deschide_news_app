# Phase 2E: Cache Layer Optimization - Technical Plan

**Date**: 2025-12-09
**Status**: In Progress
**Priority**: CRITICAL - Blocking API performance

---

## 🔴 Critical Issue Identified

### Current State (From Phase 2D)
- **Cache Hit Ratio**: 14% (target: >80%)
- **Cache Miss Rate**: 86% (causing every request to hit database)
- **API p95 Latency**: 4.6s at 100 VUs (target: <500ms)
- **Database Performance**: OPTIMAL (query time <1ms)
- **Root Cause**: Broken serialization in `CachedArticleProvider`

### The Problem

**Location**: `/var/www/deschide_news_app/apps/backend/src/State/CachedArticleProvider.php`

```php
// Lines 68-70: BROKEN SERIALIZATION
if ($article) {
    // ❌ FAILS: Can't serialize Doctrine entities with EntityManager references
    $this->performance->setCached($cacheKey, $article, 3600);
}
```

**Why This Fails**:
1. `$article` is a Doctrine entity (proxied object)
2. Entity contains references to `EntityManager`, `UnitOfWork`, etc.
3. `serialize()` in `PerformanceService::setCached()` throws exception
4. Result: **Every cache attempt fails → 86% cache miss rate**

**Error Pattern**:
```
[error] Cache set failed
  key: api:articles:123:ro
  error: Serialization of 'Doctrine\ORM\EntityManager' is not allowed
```

---

## 🎯 Solution: Doctrine Second Level Cache (L2)

### Why Doctrine L2 Cache?

| Approach | Status | Notes |
|----------|--------|-------|
| **Serialization (current)** | ❌ BROKEN | Can't serialize entities with EM references |
| **Array conversion** | ⚠️ COMPLEX | Loses lazy loading, requires manual hydration |
| **Doctrine L2 Cache** | ✅ CORRECT | Built for entity caching, automatic invalidation |

**Doctrine L2 Cache Benefits**:
- ✅ Designed specifically for entity caching
- ✅ Automatic serialization handling (stores scalar values)
- ✅ Built-in invalidation on entity changes
- ✅ Works with relationships and lazy loading
- ✅ Supports Redis backend (already configured)
- ✅ No code changes in providers needed

---

## 📋 Execution Plan

### Step 1: Remove Broken Serialization Cache ⚡ CRITICAL

**Agent**: @cache-sync-specialist
**Files to modify**:
- `src/State/CachedArticleProvider.php`
- `src/Service/PerformanceService.php` (document limitations)

**Actions**:
1. Review current implementation in `CachedArticleProvider.php`
2. Remove serialization attempts (lines 48-56, 68-70, 116-125, 139)
3. Keep method signatures but disable caching temporarily:
   ```php
   // Step 1: Disable broken serialization
   public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
   {
       // Directly delegate to decorated provider
       // L2 cache will handle caching automatically
       return $this->decorated->provide($operation, $uriVariables, $context);
   }
   ```
4. Add comment: "Caching now handled by Doctrine L2 Cache"
5. Document what was removed in commit message

**Verification**:
```bash
# Verify no serialization errors in logs
tail -f /var/www/deschide_news_app/apps/backend/var/log/dev.log | grep "Cache set failed"
```

---

### Step 2: Enable Doctrine L2 Cache 🔧 CONFIGURATION

**Agent**: @database-engineer
**Files to modify**:
- `config/packages/doctrine.yaml`
- `src/Entity/Article.php`
- `src/Entity/Category.php`
- `src/Entity/Author.php`
- `src/Entity/Image.php`

**2.1 Configure L2 Cache in doctrine.yaml**

**Current state** (lines 43-68):
```yaml
# Second Level Cache (L2) for entity caching
second_level_cache:
    enabled: true
    log_enabled: '%kernel.debug%'
    region_cache_driver:
        type: pool
        pool: doctrine.result_cache_pool
    regions:
        # Default region for most entities
        default:
            lifetime: 3600
            cache_driver:
                type: pool
                pool: doctrine.result_cache_pool
        # Short-lived region for frequently changing data
        short_lived:
            lifetime: 300
            cache_driver:
                type: pool
                pool: doctrine.result_cache_pool
        # Long-lived region for rarely changing data
        long_lived:
            lifetime: 86400
            cache_driver:
                type: pool
                pool: doctrine.result_cache_pool
```

✅ **Already configured!** No changes needed.

**2.2 Add Cache Annotations to Entities**

**Article Entity** (`src/Entity/Article.php`):
```php
// Add after class declaration (line 37)
#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ORM\Cache(usage: 'NONSTRICT_READ_WRITE', region: 'short_lived')]  // ← ADD THIS
#[UniqueEntity('slug', message: 'This slug is already in use...')]
```

**Category Entity** (`src/Entity/Category.php`):
```php
#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\Cache(usage: 'NONSTRICT_READ_WRITE', region: 'long_lived')]  // ← ADD THIS
```

**Author Entity** (`src/Entity/Author.php`):
```php
#[ORM\Entity(repositoryClass: AuthorRepository::class)]
#[ORM\Cache(usage: 'NONSTRICT_READ_WRITE', region: 'long_lived')]  // ← ADD THIS
```

**Image Entity** (`src/Entity/Image.php`):
```php
#[ORM\Entity(repositoryClass: ImageRepository::class)]
#[ORM\Cache(usage: 'READ_ONLY', region: 'default')]  // ← ADD THIS (images don't change)
```

**Cache Usage Types**:
- `NONSTRICT_READ_WRITE`: Articles, categories, authors (can be updated)
- `READ_ONLY`: Images (immutable after upload)

**Cache Regions**:
- `short_lived` (300s): Articles (change frequently)
- `long_lived` (86400s): Categories, authors (rarely change)
- `default` (3600s): Images (medium frequency)

---

### Step 3: Clear Cache and Verify 🧹

**Agent**: @database-engineer
**Commands**:
```bash
cd /var/www/deschide_news_app/apps/backend

# Clear all caches
symfony console cache:clear

# Verify L2 cache configuration
symfony console debug:config doctrine orm second_level_cache

# Check Redis connection
redis-cli -n 1 PING
# Expected: PONG

# Verify cache pools
symfony console cache:pool:list

# Test API endpoint
curl -H "Accept-Language: ro" http://127.0.0.1:8081/api/articles/1
```

---

### Step 4: Performance Testing 📊

**Agent**: @performance-tester
**Location**: `/var/www/deschide_news_app/apps/backend`

**4.1 Run Load Test**:
```bash
k6 run --duration 30s --vus 100 k6/api-only-load-test.js
```

**4.2 Check Redis Cache Metrics**:
```bash
# Get cache hit/miss ratio
redis-cli -n 1 INFO stats | grep -E "keyspace_hits|keyspace_misses"

# Calculate hit ratio
redis-cli -n 1 INFO stats | awk '/keyspace_hits/{hits=$2} /keyspace_misses/{misses=$2} END{print "Hit Ratio: " (hits/(hits+misses)*100) "%"}'
```

**4.3 Monitor Cache Keys**:
```bash
# List L2 cache keys
redis-cli -n 1 KEYS "*doctrine*" | head -20

# Monitor cache activity in real-time
redis-cli -n 1 MONITOR | grep "doctrine"
```

**Expected Results**:
| Metric | Before | Target | Method |
|--------|--------|--------|--------|
| Cache Hit Ratio | 14% | >85% | Redis INFO stats |
| API p95 Latency | 4.6s | <500ms | k6 summary |
| Database Queries | 100% | <20% | Symfony profiler |
| Redis Memory | ~10MB | <100MB | Redis INFO memory |

---

### Step 5: Cache Invalidation Testing 🧪

**Agent**: @backend-api-tester
**Test Scenarios**:

**5.1 Test Article Update Invalidation**:
```bash
# 1. Fetch article (should cache)
curl -H "Accept-Language: ro" http://127.0.0.1:8081/api/articles/1

# 2. Check Redis cache
redis-cli -n 1 KEYS "*article:1*"

# 3. Update article via API
curl -X PUT http://127.0.0.1:8081/api/articles/1 \
  -H "Content-Type: application/ld+json" \
  -H "Authorization: Bearer TOKEN" \
  -d '{"title": "Updated Title"}'

# 4. Verify cache was invalidated
redis-cli -n 1 KEYS "*article:1*"
# Expected: Empty or new cache entry

# 5. Fetch again (should re-cache)
curl -H "Accept-Language: ro" http://127.0.0.1:8081/api/articles/1
```

**5.2 Test Category Update Invalidation**:
```bash
# Similar flow for category changes affecting related articles
```

---

## 📈 Success Criteria

### Performance Metrics

| Metric | Current | Target | Critical? |
|--------|---------|--------|-----------|
| Cache Hit Ratio | 14% | **>85%** | ✅ YES |
| API p95 Latency | 4.6s | **<500ms** | ✅ YES |
| Database Load | 100% | **<20%** | ✅ YES |
| Error Rate | 0% | **0%** | ✅ YES |

### Functional Verification

- ✅ No serialization errors in logs
- ✅ Redis cache keys populated correctly
- ✅ Cache invalidation on entity update
- ✅ Multi-locale caching working (ro, en, ru)
- ✅ Relationships cached properly (category, author, images)

### Code Quality

- ✅ No broken code left in CachedArticleProvider
- ✅ Entity annotations added correctly
- ✅ Documentation updated
- ✅ Git commit messages clear

---

## 🚨 Rollback Plan

If L2 cache causes issues:

```bash
# 1. Disable L2 cache in doctrine.yaml
sed -i 's/enabled: true/enabled: false/' config/packages/doctrine.yaml

# 2. Remove entity cache annotations
# (can be done quickly with sed if needed)

# 3. Clear cache
symfony console cache:clear

# 4. Restart server
symfony server:stop && symfony server:start -d --port=8081
```

---

## 📚 Technical References

### Doctrine L2 Cache Documentation
- https://www.doctrine-project.org/projects/doctrine-orm/en/latest/reference/second-level-cache.html

### Key Concepts

**Cache Regions**:
- Logical groupings of cached entities
- Different TTLs per region
- Configured in `doctrine.yaml`

**Cache Usage Modes**:
- `READ_ONLY`: Immutable data (images)
- `NONSTRICT_READ_WRITE`: Moderate concurrency (articles, categories)
- `READ_WRITE`: High concurrency (not needed for our use case)

**Cache Invalidation**:
- Automatic on entity persist/update/remove
- Manual via `$em->getCache()->evict(Article::class, $id)`

---

## 🎯 Next Steps After Phase 2E

1. **Phase 3A**: Frontend caching (Next.js ISR)
2. **Phase 3B**: CDN integration (Cloudflare)
3. **Phase 3C**: Varnish cache layer (optional)

---

## 📝 Notes

- L2 cache is already configured in doctrine.yaml (lines 43-68)
- Redis pools are correctly set up in cache.yaml
- Only need to add entity annotations and remove broken serialization
- Estimated time: 45-60 minutes total
- No database migration needed
- No downtime required

---

**Created**: 2025-12-09
**Updated**: 2025-12-09
**Status**: Ready for execution
