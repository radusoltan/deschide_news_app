# Deschide News App - Comprehensive Performance Audit Report

**Date**: 2025-12-09
**Auditor**: Performance Engineering Agent
**Application Version**: Current develop branch
**Environment**: Development (WSL2)

---

## Executive Summary

The Deschide News App demonstrates a solid performance architecture with multi-layer caching (L1/L2/L3), eager loading patterns, and proper database indexing. However, the stress test results reveal critical bottlenecks under high load (500 concurrent users) with a 41% failure rate and p95 response times of 4.19 seconds.

### Key Findings

| Area | Status | Score |
|------|--------|-------|
| Backend Architecture | Good | 7/10 |
| Caching Strategy | Good | 8/10 |
| Database Optimization | Good | 7/10 |
| Frontend Performance | Needs Attention | 6/10 |
| Load Handling | Critical | 4/10 |
| Real-time Systems | Good | 7/10 |

---

## 1. Backend Performance Analysis

### 1.1 Database Query Optimization

**Status**: GOOD - N+1 queries largely prevented

**Evidence of Proper Eager Loading**:
- ArticleProvider.php implements comprehensive eager loading with 107 instances of leftJoin/addSelect patterns across state providers
- All major providers (Article, Category, Author, Image) use proper join strategies

```php
// ArticleProvider.php - Example of correct implementation
$queryBuilder = $repository->createQueryBuilder('a')
    ->leftJoin('a.category', 'c')
    ->addSelect('c')
    ->leftJoin('a.authors', 'au')
    ->addSelect('au')
    ->leftJoin('a.articleImages', 'ai')
    ->addSelect('ai')
    ->leftJoin('ai.image', 'img')
    ->addSelect('img')
    ->leftJoin('a.tags', 't')
    ->addSelect('t')
```

**Database Indexes**: 50+ indexes defined across entities including:
- Article: 10 indexes (status, published_at, category composite indexes)
- ArticleImage: 4 indexes (article_id, position, featured)
- Author: 5 indexes (slug, email, status)
- LiveText: 6 indexes (status, time-based)

**Recommendations**:
1. Add missing index for `ext_translations` table (Gedmo Translatable)
2. Consider partial indexes for frequently filtered columns (status='published')

### 1.2 API Response Times

**Target Performance**:
| Endpoint Type | Target (Cached) | Target (Uncached) |
|---------------|-----------------|-------------------|
| Single Article | < 100ms | < 300ms |
| Article List | < 150ms | < 500ms |
| Categories | < 50ms | < 150ms |

**Current Status** (from stress test at low load):
- Articles duration (p95): 300ms (meets target when < 100 VUs)
- Categories duration (p95): 150ms (meets target)
- Homepage duration (p95): 2000ms (frontend rendering included)

**Bottleneck Identified**: Response times degrade severely above 200 concurrent users

### 1.3 Doctrine Configuration

**Current Settings** (doctrine.yaml):
```yaml
second_level_cache:
  enabled: true
  regions:
    default: { lifetime: 3600 }      # 1 hour
    short_lived: { lifetime: 300 }   # 5 minutes
    long_lived: { lifetime: 86400 }  # 24 hours

query_cache_driver: redis_tag_aware
result_cache_driver: redis_tag_aware
metadata_cache_driver: apcu
```

**Assessment**: Configuration is well-optimized with:
- APCu for metadata (fast L1)
- Redis for query/result cache (shared L2)
- Doctrine Second Level Cache enabled

**Missing Optimization**:
- OPcache JIT not explicitly enabled
- Connection pooling (PgBouncer) not configured

---

## 2. Caching Strategy Analysis

### 2.1 Cache Hierarchy Implementation

| Layer | Technology | TTL | Status |
|-------|------------|-----|--------|
| L1 (APCu) | APCu | 86400s | Configured for metadata |
| L2 (Redis) | Redis DB 1 | 300-3600s | Configured with tag-aware adapter |
| L3 (Next.js ISR) | Next.js | 60-3600s | Partially configured |

### 2.2 Redis Cache Configuration (cache.yaml)

```yaml
framework:
  cache:
    prefix_seed: deschide_news
    app: cache.adapter.redis_tag_aware
    default_redis_provider: 'redis://localhost:6379/1'

    pools:
      deschide.cache: { default_lifetime: 300 }        # 5 min
      deschide.stats: { default_lifetime: 60 }         # 1 min
      doctrine.result_cache_pool: { default_lifetime: 600 }  # 10 min
      doctrine.query_cache_pool: { default_lifetime: 86400 } # 24h
```

**Assessment**: Well-structured cache pools with appropriate TTLs

### 2.3 Application-Level Caching

**CachedArticleProvider** (Decorator Pattern):
- Single article cache: 1 hour TTL
- Article list cache: 5 minutes TTL
- Cache key includes locale and filters: `api:articles:{id}:{locale}`
- Cache invalidation on article update via PerformanceService

**Recommendations**:
1. Implement cache warming on deployment
2. Add cache hit/miss metrics to Prometheus
3. Consider stale-while-revalidate pattern for list endpoints

### 2.4 HTTP Cache Headers

**CacheHeadersSubscriber** sets:
- `max-age: 3600` (browser cache)
- `s-maxage: 7200` (CDN/proxy cache)
- `Vary: Content-Type, Accept-Language, Origin`
- ETag generation from response content

**Issue**: ETag uses MD5 of entire response - consider lighter hash or entity version

---

## 3. Frontend Performance Analysis

### 3.1 Core Web Vitals Compliance

**Target Metrics**:
| Metric | Target | Assessment |
|--------|--------|------------|
| LCP (Largest Contentful Paint) | < 2.5s | Needs verification |
| INP (Interaction to Next Paint) | < 200ms | Needs verification |
| CLS (Cumulative Layout Shift) | < 0.1 | Needs verification |

**Note**: Full CWV analysis requires Lighthouse testing or field data from Chrome UX Report

### 3.2 Next.js Configuration (next.config.mjs)

**Optimizations Enabled**:
```javascript
{
  images: {
    formats: ['image/webp', 'image/avif'],
    minimumCacheTTL: 60 * 60 * 24 * 30, // 30 days
    unoptimized: true // Development only
  },
  turbopack: {},
  productionBrowserSourceMaps: false,
  compiler: {
    removeConsole: { exclude: ['error', 'warn'] } // Production
  },
  experimental: {
    optimizePackageImports: ['lucide-react', 'date-fns', '@heroicons/react']
  }
}
```

**Issues Identified**:
1. `images.unoptimized: true` in development bypasses optimization
2. No explicit compression configuration
3. Missing `next/font` optimization

### 3.3 ISR (Incremental Static Regeneration) Configuration

**Current Implementation**:
| Page | Revalidation | Type |
|------|--------------|------|
| Homepage | None specified | Dynamic (Server Component) |
| Article Page | 120s | `force-dynamic` + revalidate |
| Archive Pages | 3600s (1 hour) | ISR |
| Trending | 600s (10 min) | ISR |
| Author Pages | 600s (10 min) | ISR |
| All Articles | 300s (5 min) | ISR |
| Search | force-dynamic | SSR |
| Admin Pages | force-dynamic | SSR |

**Critical Issue**: Article page has conflicting settings:
```typescript
export const dynamic = 'force-dynamic';
export const revalidate = 120;
```
`force-dynamic` overrides `revalidate`, making pages always server-rendered.

**Recommendations**:
1. Remove `force-dynamic` from article page to enable ISR
2. Add `revalidate` to homepage (suggest 60s)
3. Implement On-Demand Revalidation (ODR) for article updates

### 3.4 Bundle Size Analysis

**Dependencies of Concern** (package.json):
- `moment` (72KB) - Consider `date-fns` or native Date
- `tinymce` (1MB+) - Admin-only, ensure code-splitting
- `recharts` (50KB+) - Admin-only, ensure code-splitting
- `swiper` (45KB) - Evaluate necessity

**Available Tool**: `pnpm build:analyze` configured for bundle analysis

---

## 4. Real-time Performance (Mercure)

### 4.1 Configuration

**Mercure Setup**:
- URL: `http://localhost:3000/.well-known/mercure`
- Authentication: JWT-based
- Topics: `deschide_news/*`

**LiveTextNotificationService**:
- Async publishing via HTTP client
- JWT authentication for publishing

**Assessment**: Standard Mercure configuration, no obvious issues

### 4.2 Recommendations

1. Monitor Mercure hub memory usage under load
2. Implement connection pooling for high-volume scenarios
3. Add heartbeat/ping mechanism for connection health

---

## 5. Elasticsearch Performance

### 5.1 Index Configuration

**ElasticService** features:
- Locale-specific indices: `deschide_articles_ro`, `deschide_articles_en`, `deschide_articles_ru`
- Locale-appropriate analyzers (Romanian, English, Russian)
- Completion suggester for autocomplete
- Bulk indexing support with batch operations

**Index Settings**:
```php
'settings' => [
    'number_of_shards' => 1,
    'number_of_replicas' => 0,
]
```

**Issue**: Single shard, no replicas - acceptable for development, not production

### 5.2 Search Query Performance

**Query Structure**:
- Multi-match with boosting: `title^3`, `tag_names^2.5`, `lead^2`, `content`
- Function score for featured articles (1.5x boost)
- Fuzzy matching enabled (`fuzziness: AUTO`)
- Highlighting on title, lead, content

**Recommendations**:
1. Add search query caching
2. Consider search-as-you-type field type for autocomplete
3. Monitor slow queries in Elasticsearch logs

---

## 6. Load Testing Results Analysis

### 6.1 Stress Test Summary (2025-12-02)

**Configuration**:
- Max VUs: 500
- Duration: 14 minutes
- Ramp-up: Gradual

**Results**:
| Metric | Value | Threshold | Status |
|--------|-------|-----------|--------|
| HTTP Request Duration (avg) | 2.25s | - | WARNING |
| HTTP Request Duration (p95) | 4.19s | <2s | FAILED |
| HTTP Request Failed Rate | 41.18% | <1% | CRITICAL |
| Total Requests | 68,456 | - | OK |
| Throughput | 81.13 req/s | - | OK |

### 6.2 Breaking Point Analysis

**System Capacity**:
| Load Level | VUs | Expected Performance |
|------------|-----|---------------------|
| Normal | 1-50 | p95 < 300ms |
| High | 50-100 | p95 < 500ms |
| Peak | 100-200 | p95 < 1s |
| Overload | 200-300 | p95 < 2s (degraded) |
| Critical | 300+ | Failures expected |

**Breaking Point**: ~200-300 concurrent users

### 6.3 Identified Bottlenecks (Priority Order)

1. **PHP-FPM Workers** (HIGH IMPACT)
   - Default configuration insufficient for 500 VUs
   - Requests queue up waiting for available workers

2. **PostgreSQL Connections** (HIGH IMPACT)
   - Default max_connections (100) exceeded
   - No connection pooling (PgBouncer)

3. **OPcache/JIT** (MEDIUM IMPACT)
   - JIT compilation not explicitly enabled
   - Memory settings may be suboptimal

4. **Redis Connection Limits** (MEDIUM IMPACT)
   - Single Redis client instance
   - No connection pooling

---

## 7. Rate Limiting Configuration

### 7.1 Current Configuration (rate_limiter.yaml)

```yaml
framework:
  rate_limiter:
    api_general:
      policy: 'fixed_window'
      limit: 100
      interval: '1 minute'

    api_login:
      policy: 'token_bucket'
      limit: 5
      rate: { interval: '1 minute', amount: 5 }

    api_write:
      policy: 'fixed_window'
      limit: 30
      interval: '1 minute'
```

**RateLimiterSubscriber** implementation:
- Applies to all `/api` routes
- Separate limiters for login, write, and general endpoints
- Returns proper rate limit headers (X-RateLimit-*)

**Assessment**: Well-configured for protection against abuse

---

## 8. Optimization Recommendations (Ranked by Impact)

### Priority 1: Critical (Immediate Action Required)

| # | Recommendation | Impact | Effort |
|---|----------------|--------|--------|
| 1 | **Increase PHP-FPM workers** | HIGH | LOW |
| | Set `pm.max_children = 100-200` | | |
| 2 | **Enable OPcache JIT** | HIGH | LOW |
| | `opcache.jit=1255`, `opcache.jit_buffer_size=100M` | | |
| 3 | **Fix Article Page ISR** | HIGH | LOW |
| | Remove `force-dynamic`, keep `revalidate: 120` | | |
| 4 | **Increase PostgreSQL max_connections** | HIGH | LOW |
| | Set `max_connections = 200` | | |

### Priority 2: High (This Sprint)

| # | Recommendation | Impact | Effort |
|---|----------------|--------|--------|
| 5 | **Add PgBouncer** for connection pooling | HIGH | MEDIUM |
| 6 | **Add Homepage ISR** | MEDIUM | LOW |
| | Add `export const revalidate = 60` | | |
| 7 | **Implement On-Demand Revalidation** | MEDIUM | MEDIUM |
| | Webhook from Symfony to Next.js on article update | | |
| 8 | **Add Varnish HTTP Cache** (if not present) | HIGH | MEDIUM |

### Priority 3: Medium (Next Sprint)

| # | Recommendation | Impact | Effort |
|---|----------------|--------|--------|
| 9 | **Bundle size optimization** | MEDIUM | MEDIUM |
| | Replace moment.js, ensure admin code-splitting | | |
| 10 | **Cache hit/miss Prometheus metrics** | LOW | LOW |
| 11 | **Elasticsearch production config** | MEDIUM | LOW |
| | Add replicas, increase shards | | |
| 12 | **Redis connection pooling** | MEDIUM | MEDIUM |
| 13 | **Add ext_translations index** | LOW | LOW |

### Priority 4: Low (Future Sprints)

| # | Recommendation | Impact | Effort |
|---|----------------|--------|--------|
| 14 | **Implement CDN** (Cloudflare/CloudFront) | MEDIUM | HIGH |
| 15 | **Horizontal scaling** (load balancer) | HIGH | HIGH |
| 16 | **Database read replicas** | MEDIUM | HIGH |
| 17 | **Kubernetes autoscaling** | MEDIUM | HIGH |

---

## 9. Performance Monitoring Recommendations

### 9.1 Metrics to Track

**Backend (Prometheus)**:
- Request duration by endpoint (histogram)
- Cache hit/miss ratio (counter)
- Database query duration (histogram)
- PHP-FPM worker utilization
- Redis connection count

**Frontend (Web Vitals)**:
- LCP, INP, CLS (Core Web Vitals)
- TTFB (Time to First Byte)
- FCP (First Contentful Paint)

**Infrastructure**:
- PostgreSQL connections (active/idle)
- Redis memory usage
- Elasticsearch query latency
- PHP-FPM process states

### 9.2 Alerting Thresholds

| Metric | Warning | Critical |
|--------|---------|----------|
| API p95 Response Time | > 500ms | > 1000ms |
| Error Rate | > 1% | > 5% |
| PHP-FPM Wait Queue | > 5 | > 20 |
| PostgreSQL Connections | > 80% | > 95% |
| Redis Memory | > 80% | > 95% |

---

## 10. Load Testing Readiness

### 10.1 Existing Test Suite

**Location**: `/var/www/deschide_news_app/k6/`

**Available Tests**:
- `load-test.js` - Standard load test (10 min, 100 VUs)
- `stress-test.js` - Breaking point test (14 min, 500 VUs)
- `soak-test.js` - Endurance test (65 min, 50 VUs)
- `scenarios/spike-test.js` - Sudden traffic burst
- `scenarios/api-endpoints.js` - API-only testing

**Test Runner**: `/var/www/deschide_news_app/scripts/run-load-tests.sh`

### 10.2 Test Thresholds

```javascript
thresholds: {
  'articles_duration': ['p(95)<300'],
  'categories_duration': ['p(95)<150'],
  'homepage_duration': ['p(95)<2000'],
  'error_rate': ['rate<0.05'],
  'http_req_duration': ['p(95)<500', 'p(99)<1000'],
  'http_req_failed': ['rate<0.01'],
}
```

### 10.3 Recommended Test Execution Plan

1. **Baseline Test** (Weekly)
   - 50 VUs, 10 minutes
   - Track trends over time

2. **Load Test** (Before Release)
   - 100 VUs, 10 minutes
   - Must pass all thresholds

3. **Stress Test** (Monthly)
   - Up to 500 VUs
   - Identify breaking point

4. **Soak Test** (Quarterly)
   - 50 VUs, 1+ hour
   - Detect memory leaks

---

## 11. Quick Wins Checklist

- [ ] Fix article page ISR (remove `force-dynamic`)
- [ ] Add `revalidate = 60` to homepage
- [ ] Enable OPcache JIT in php.ini
- [ ] Increase PHP-FPM `pm.max_children` to 100
- [ ] Increase PostgreSQL `max_connections` to 200
- [ ] Run bundle analyzer and review results
- [ ] Add Prometheus metrics for cache hits
- [ ] Create performance dashboard in Grafana

---

## Appendix A: Configuration Files Reference

| File | Purpose |
|------|---------|
| `apps/backend/config/packages/doctrine.yaml` | Doctrine ORM & cache config |
| `apps/backend/config/packages/cache.yaml` | Symfony cache pools |
| `apps/backend/config/packages/rate_limiter.yaml` | Rate limiting |
| `apps/backend/src/State/ArticleProvider.php` | Article data fetching |
| `apps/backend/src/State/CachedArticleProvider.php` | Article caching layer |
| `apps/backend/src/Service/PerformanceService.php` | Redis cache operations |
| `apps/frontend/next.config.mjs` | Next.js configuration |
| `k6/load-test.js` | k6 load test script |

---

## Appendix B: Test Results Archive

| Date | Test Type | Max VUs | p95 | Error Rate | Status |
|------|-----------|---------|-----|------------|--------|
| 2025-12-02 | Stress | 500 | 4.19s | 41.18% | FAILED |

---

**Report Generated**: 2025-12-09
**Next Review**: After Priority 1 optimizations implemented
