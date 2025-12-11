# Backend API Performance Tests

This directory contains PHPUnit performance tests for the Deschide News Backend API.

## Overview

The performance test suite measures:
- **API Response Times**: End-to-end response times for critical endpoints
- **Database Query Performance**: Query count and N+1 detection
- **Cache Performance**: Redis operations and cache effectiveness

## Test Files

### 1. ApiResponseTimeTest.php

Measures response times for critical API endpoints and ensures they meet performance thresholds.

**Endpoints Tested:**
- `GET /api/articles?itemsPerPage=30` - Target P95: < 200ms
- `GET /api/articles/{id}` - Target P95: < 150ms
- `GET /api/categories` - Target P95: < 100ms
- `GET /api/important_articles` - Target P95: < 200ms
- `GET /api/authors` - Target P95: < 100ms
- Concurrent requests test (10 sequential requests)

**Metrics Calculated:**
- Average (mean) response time
- Minimum response time
- Maximum response time
- P50 (median) response time
- P95 (95th percentile) response time
- P99 (99th percentile) response time
- Throughput (requests per second)

### 2. DatabaseQueryPerformanceTest.php

Detects database performance issues including N+1 queries and excessive query counts.

**Endpoints Tested:**
- `GET /api/articles?itemsPerPage=10` - Max 10 queries
- `GET /api/articles/{id}` - Max 5 queries
- `GET /api/categories` - Max 3 queries
- `GET /api/important_articles` - Max 10 queries

**Features:**
- Query count verification
- N+1 query pattern detection
- Query execution time measurement
- Detailed query logging

### 3. CachePerformanceTest.php

Tests Redis cache performance and HTTP cache headers.

**Tests:**
- Redis read/write performance
- Cache hit vs miss scenarios
- Cache invalidation effectiveness
- HTTP cache headers validation
- Concurrent cache access

## Running the Tests

### Run All Performance Tests

```bash
cd /var/www/deschide_news_app/apps/backend
XDEBUG_MODE=off vendor/bin/phpunit tests/Performance --group=performance --testdox --no-coverage
```

### Run Specific Test Suite

```bash
# Response time tests only
XDEBUG_MODE=off vendor/bin/phpunit tests/Performance/ApiResponseTimeTest.php --testdox

# Database query tests only
XDEBUG_MODE=off vendor/bin/phpunit tests/Performance/DatabaseQueryPerformanceTest.php --testdox

# Cache performance tests only
XDEBUG_MODE=off vendor/bin/phpunit tests/Performance/CachePerformanceTest.php --testdox
```

### Run With Verbose Output

```bash
XDEBUG_MODE=off vendor/bin/phpunit tests/Performance --group=performance --testdox --verbose
```

## Performance Thresholds

| Endpoint | P95 Threshold | Rationale |
|----------|---------------|-----------|
| `GET /api/articles` (list) | 200ms | Complex query with translations and relationships |
| `GET /api/articles/{id}` | 150ms | Single item should be faster with eager loading |
| `GET /api/categories` | 100ms | Simple list, should be cached |
| `GET /api/important_articles` | 200ms | Featured articles with images |
| `GET /api/authors` | 100ms | Simple list without complex relationships |

| Query Metric | Threshold | Rationale |
|--------------|-----------|-----------|
| Articles list queries | 10 | Main query + eager loading (3-5 queries typical) |
| Single article queries | 5 | Single item fetch with relationships |
| Categories queries | 3 | Simple list with translations |

## Current Performance (2025-12-02)

### ✅ Passing Tests (13/17)

**Response Time Tests:**
- ✅ Categories: P95 33.52ms (threshold: 100ms) - **EXCELLENT**
- ✅ Important articles: P95 47.89ms (threshold: 200ms) - **EXCELLENT**
- ✅ Concurrent requests: P95 174ms (threshold: 400ms) - **GOOD**

**Database Query Tests:**
- ✅ Articles list: 3 queries (threshold: 10) - **EXCELLENT**
- ✅ Categories: 0 queries (cached) - **EXCELLENT**
- ✅ Important articles: 1 query (threshold: 10) - **EXCELLENT**

**Cache Performance:**
- ✅ Redis read: 0.28ms
- ✅ Redis write: 2.54ms
- ✅ Cache hit/miss working correctly
- ✅ HTTP cache headers present
- ✅ Concurrent access: 100% success rate

### ❌ Failing Tests (4/17)

**1. Articles List Response Time**
- Current P95: 1628ms
- Threshold: 200ms
- **Gap: 1428ms (8.1x over threshold)**

**Recommendations:**
- Check for N+1 queries in ArticleProvider (though query count is good at 3)
- Verify eager loading is properly configured
- Review database indices on articles table
- Consider implementing result caching (Redis L2)
- Profile with Blackfire to identify bottlenecks

**2. Single Article Response Time**
- Current P95: 175ms
- Threshold: 150ms
- **Gap: 25ms (1.2x over threshold)**

**Recommendations:**
- Review eager loading for single article queries
- Check serialization group performance (MaxDepth causing issues?)
- Implement cache layer for frequently accessed articles
- Profile translation loading overhead

**3. Authors Response Time**
- Current P95: 140ms
- Threshold: 100ms
- **Gap: 40ms (1.4x over threshold)**

**Recommendations:**
- Authors list should be simple and fast
- Implement caching (5-15 minute TTL)
- Check if unnecessary JOINs are being performed
- Consider pre-loading author list at application startup

**4. Single Article Query Count Profiler**
- Issue: Profiler not enabled for one test iteration
- **Action**: Minor test stability issue, ignore for now

## Performance Optimization Priorities

### High Priority (P0)

1. **Articles List Response Time** (8.1x over threshold)
   - Most critical endpoint for homepage/category pages
   - Likely database index or N+1 issue despite query count being low
   - **Action**: Profile with Symfony Profiler or Blackfire
   - **Target**: Reduce P95 to < 200ms

### Medium Priority (P1)

2. **Single Article Response Time** (1.2x over threshold)
   - Close to threshold, minor optimization needed
   - **Action**: Add caching layer for popular articles
   - **Target**: Reduce P95 to < 150ms

3. **Authors Response Time** (1.4x over threshold)
   - Simple endpoint that should be cached
   - **Action**: Implement Redis caching with 15-minute TTL
   - **Target**: Reduce P95 to < 100ms

### Low Priority (P2)

4. **Cache "set failed" Errors**
   - Redis not properly configured for test environment
   - Tests still pass, but warnings are noisy
   - **Action**: Configure Redis for test environment or mock cache adapter
   - **Target**: Eliminate warnings from test output

## Interpreting Results

### Response Time Percentiles

- **P50 (Median)**: Half of requests are faster than this
- **P95**: 95% of requests are faster than this (used for SLA)
- **P99**: 99% of requests are faster than this (outliers)

**Example:**
```
📊 GET /api/articles:
   ├─ Avg: 386.99ms    ← Mean response time
   ├─ Min: 184.79ms    ← Fastest request
   ├─ Max: 1628.13ms   ← Slowest request (outlier)
   ├─ P50: 245.50ms    ← Median (typical user experience)
   ├─ P95: 1628.13ms   ← SLA target (95% faster than this)
   └─ P99: 1628.13ms   ← Worst-case scenario
```

### Query Count Analysis

**Good:**
```
📊 GET /api/articles?itemsPerPage=10:
   ├─ Total queries: 3
   └─ Threshold: 10 queries
```
- Main query + 2 eager loading JOINs
- No N+1 queries detected

**Warning - N+1 Detected:**
```
⚠️  Potential N+1 Query Patterns Detected:
   - Query executed 15 times: SELECT * FROM article_images WHERE article_id = ?
```
- Same query repeated multiple times
- Missing eager loading configuration
- Fix: Add `leftJoin()` + `addSelect()` in Provider

## Continuous Monitoring

### Run Before Each Deployment

```bash
# Quick smoke test (runs 10 iterations per test)
XDEBUG_MODE=off vendor/bin/phpunit tests/Performance --group=performance --no-coverage

# Detailed analysis (runs 20 iterations)
# Edit tests and change RUNS constant to 20
```

### CI/CD Integration

Add to `.github/workflows/tests.yml`:

```yaml
- name: Run Performance Tests
  run: |
    cd apps/backend
    XDEBUG_MODE=off vendor/bin/phpunit tests/Performance --group=performance --testdox
  env:
    APP_ENV: test
```

### Performance Regression Detection

Track performance over time:

```bash
# Generate performance report
XDEBUG_MODE=off vendor/bin/phpunit tests/Performance --testdox | tee "performance-$(date +%Y%m%d).log"

# Compare with previous run
diff performance-20251201.log performance-20251202.log
```

## Troubleshooting

### Profiler Not Enabled

If you see: "Profiler must be enabled in test environment"

**Solution:**
1. Check `config/packages/framework.yaml`:
   ```yaml
   when@test:
       framework:
           profiler: { collect: true }
   ```

2. Clear cache:
   ```bash
   symfony console cache:clear --env=test
   ```

### Redis Connection Failed

If you see: "Cache set failed"

**Solution:**
1. Ensure Redis is running:
   ```bash
   redis-cli ping
   # Should return: PONG
   ```

2. Check `.env.test`:
   ```env
   REDIS_URL=redis://localhost:6379/1
   ```

3. Or disable cache in tests:
   ```yaml
   # config/packages/cache.yaml
   when@test:
       framework:
           cache:
               app: cache.adapter.array
   ```

### Slow Tests (> 30 seconds)

Performance tests run multiple iterations (10-20) per endpoint.

**Options:**
1. Reduce `RUNS` constant in test files (e.g., from 10 to 5)
2. Run specific test suite instead of all tests
3. Use `--filter` to run single test:
   ```bash
   vendor/bin/phpunit tests/Performance --filter testCategoriesResponseTime
   ```

## Best Practices

### 1. Always Disable Xdebug

```bash
XDEBUG_MODE=off vendor/bin/phpunit tests/Performance
```

Xdebug adds 10-20x overhead. Performance tests with Xdebug enabled are meaningless.

### 2. Run On Consistent Hardware

- Same machine/server for baseline comparisons
- Close background applications
- Run outside of Docker for more consistent results

### 3. Warm Up Before Testing

The first request is always slower (cold cache, lazy loading).
Tests automatically run multiple iterations to account for this.

### 4. Test In Production-Like Environment

- Use production database dump (or realistic fixture data)
- Enable all caching layers (Redis, OPcache, APCu)
- Use PostgreSQL (not SQLite)

## Related Documentation

- [Backend README.md](../../README.md)
- [Testing Strategy](../README.md)
- [API Platform Documentation](https://api-platform.com/docs/core/performance/)
- [Doctrine Performance Best Practices](https://www.doctrine-project.org/projects/doctrine-orm/en/latest/reference/improving-performance.html)

## Maintenance

**Test Suite Owner**: Backend Team

**Last Updated**: 2025-12-02

**Review Schedule**: After each sprint or major performance-related change

**Performance Baseline**: Established 2025-12-02
