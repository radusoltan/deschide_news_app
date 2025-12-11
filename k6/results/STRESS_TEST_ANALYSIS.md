# Stress Test Analysis Report

**Date**: 2025-12-02
**Test Duration**: ~14 minutes
**Max VUs**: 500

## Results Summary

| Metric | Value | Threshold | Status |
|--------|-------|-----------|--------|
| HTTP Request Duration (avg) | 2.25s | - | Warning |
| HTTP Request Duration (p95) | 4.19s | <2s | FAILED |
| HTTP Request Failed Rate | 41.18% | <15% | FAILED |
| Total Requests | 68,456 | - | OK |
| Throughput | 81.13 req/s | - | OK |
| Max VUs | 500 | - | OK |

## Breaking Point Analysis

The system starts degrading significantly around **200-300 concurrent users**.

At 500 VUs:
- 41% of requests fail (28,194 out of 68,456)
- Response times increase to 4+ seconds (p95)
- Maximum response time reached 6.58s

## Identified Bottlenecks

### 1. PHP-FPM Workers
Current typical configuration allows ~50-100 concurrent PHP processes.
At 500 VUs, requests queue up waiting for available workers.

**Recommendation:**
```ini
# /etc/php/8.4/fpm/pool.d/www.conf
pm = dynamic
pm.max_children = 100
pm.start_servers = 20
pm.min_spare_servers = 10
pm.max_spare_servers = 50
pm.max_requests = 500
```

### 2. Database Connections
PostgreSQL default max_connections is typically 100.

**Recommendation:**
```sql
-- postgresql.conf
max_connections = 200
shared_buffers = 256MB
work_mem = 4MB
```

### 3. Symfony Performance
- Enable OPcache with JIT
- Warm up cache before high load
- Consider using connection pooling (PgBouncer)

**Recommendation:**
```ini
# php.ini
opcache.enable=1
opcache.jit=1255
opcache.jit_buffer_size=100M
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
```

### 4. Redis/Cache Layer
Ensure Redis is properly utilized for:
- Session storage
- Query result caching
- Rate limiting state

### 5. Nginx/Web Server
```nginx
# nginx.conf
worker_processes auto;
worker_connections 4096;

upstream php-fpm {
    server unix:/var/run/php/php8.4-fpm.sock;
    keepalive 32;
}
```

## Capacity Planning

| Load Level | VUs | Expected Performance |
|------------|-----|---------------------|
| Normal | 1-50 | p95 < 300ms |
| High | 50-100 | p95 < 500ms |
| Peak | 100-200 | p95 < 1s |
| Overload | 200-300 | p95 < 2s (degraded) |
| Critical | 300+ | Failures expected |

## Action Items

1. [ ] Increase PHP-FPM workers (pm.max_children)
2. [ ] Enable OPcache JIT compilation
3. [ ] Increase PostgreSQL max_connections
4. [ ] Consider PgBouncer for connection pooling
5. [ ] Add Varnish/CDN for static content
6. [ ] Implement rate limiting for API endpoints
7. [ ] Add horizontal scaling (load balancer)

## Next Steps

1. Run load test (100 VUs) to establish baseline
2. Apply optimizations
3. Re-run stress test to measure improvement
4. Consider auto-scaling for production

---

**Files:**
- JSON Results: `k6/results/stress-test_20251202_142754.json`
- Log File: `k6/results/stress-test_20251202_142754.log`
