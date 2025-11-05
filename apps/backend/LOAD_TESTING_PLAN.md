# Week 2 - Day 4: Load Testing and Cache Validation Plan

**Project**: Deschide News Backend - HTTP Cache Layer Optimization
**Date**: November 4, 2025
**Status**: 🚧 In Progress

---

## 📋 Objectives

### Primary Goals
1. Validate Varnish cache performance under load
2. Verify PgBouncer connection pooling efficiency
3. Measure end-to-end API performance
4. Identify bottlenecks and optimization opportunities
5. Establish performance baselines for production

### Success Criteria
- Cache hit rate > 85% for cached endpoints
- Response time p95 < 50ms for cached requests
- Response time p95 < 300ms for uncached requests
- System stable under 1000 concurrent requests
- No connection pool exhaustion
- No server errors under load

---

## 🛠️ Tools and Setup

### Load Testing Tools

**1. Apache Bench (ab)** - Simple HTTP load testing
```bash
# Install
sudo apt-get install -y apache2-utils

# Usage
ab -n 10000 -c 100 http://127.0.0.1:6081/api/articles
```

**2. wrk** - Modern HTTP benchmarking tool
```bash
# Install
sudo apt-get install -y wrk

# Usage
wrk -t4 -c100 -d30s --latency http://127.0.0.1:6081/api/articles
```

**3. wrk2** - wrk with accurate latency recording
```bash
# Install from source
git clone https://github.com/giltene/wrk2.git
cd wrk2
make
sudo cp wrk /usr/local/bin/wrk2

# Usage
wrk2 -t4 -c100 -d30s -R1000 --latency http://127.0.0.1:6081/api/articles
```

**4. vegeta** - HTTP load testing tool
```bash
# Install
wget https://github.com/tsenart/vegeta/releases/download/v12.11.1/vegeta_12.11.1_linux_amd64.tar.gz
tar xvf vegeta_12.11.1_linux_amd64.tar.gz
sudo mv vegeta /usr/local/bin/

# Usage
echo "GET http://127.0.0.1:6081/api/articles" | vegeta attack -duration=30s -rate=100 | vegeta report
```

**5. PostgreSQL pgbench** - Database benchmarking
```bash
# Already installed with PostgreSQL

# Usage
pgbench -h 127.0.0.1 -p 6432 -U deschide_admin -c 10 -j 2 -t 1000 deschide
```

---

## 📊 Test Scenarios

### Scenario 1: Baseline Performance (No Load)
**Purpose**: Establish baseline metrics

**Test**:
```bash
# Single request to cold cache
curl -w "@curl-format.txt" http://127.0.0.1:6081/api/articles

# Single request to warm cache
curl -w "@curl-format.txt" http://127.0.0.1:6081/api/articles
```

**Metrics**:
- Cold cache response time
- Warm cache response time
- Cache hit/miss headers

### Scenario 2: Light Load (100 concurrent users)
**Purpose**: Test normal traffic conditions

**Test**:
```bash
ab -n 10000 -c 100 http://127.0.0.1:6081/api/articles
wrk -t4 -c100 -d60s --latency http://127.0.0.1:6081/api/articles
```

**Expected**:
- p95 < 50ms (cached)
- Cache hit rate > 90%
- No errors

### Scenario 3: Medium Load (500 concurrent users)
**Purpose**: Test peak traffic conditions

**Test**:
```bash
ab -n 50000 -c 500 http://127.0.0.1:6081/api/articles
wrk -t8 -c500 -d60s --latency http://127.0.0.1:6081/api/articles
```

**Expected**:
- p95 < 100ms (cached)
- Cache hit rate > 85%
- No errors
- Connection pool stable

### Scenario 4: Heavy Load (1000 concurrent users)
**Purpose**: Stress test system limits

**Test**:
```bash
ab -n 100000 -c 1000 http://127.0.0.1:6081/api/articles
wrk -t12 -c1000 -d60s --latency http://127.0.0.1:6081/api/articles
```

**Expected**:
- p95 < 200ms (cached)
- Cache hit rate > 80%
- < 1% error rate
- Connection pool handling load

### Scenario 5: Cache Efficiency Test
**Purpose**: Validate cache hit rates

**Test**:
```bash
# Test multiple endpoints
for i in {1..1000}; do
  curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:6081/api/articles
  curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:6081/api/categories
  curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:6081/api/authors
done

# Check Varnish stats
varnishstat -1 | grep -E "cache_hit|cache_miss"
```

**Expected**:
- Cache hit ratio > 85%
- Consistent hit rate across endpoints

### Scenario 6: Connection Pool Stress Test
**Purpose**: Test PgBouncer under load

**Test**:
```bash
# Simulate many concurrent database operations
for i in {1..50}; do
  (PGPASSWORD=sr324395 psql -h 127.0.0.1 -p 6432 -U deschide_admin -d deschide -c "SELECT pg_sleep(2)" &)
done

# Monitor pool status
watch -n 1 'psql -h 127.0.0.1 -p 6432 -U deschide_admin -d pgbouncer -Atc "SHOW POOLS" | grep deschide'
```

**Expected**:
- No client waiting (cl_waiting = 0)
- Pool size adjusts dynamically
- No connection errors

### Scenario 7: Mixed Endpoint Load
**Purpose**: Test realistic traffic patterns

**Test**:
```bash
# Create vegeta targets file with multiple endpoints
cat > targets.txt << EOF
GET http://127.0.0.1:6081/api/articles
GET http://127.0.0.1:6081/api/categories
GET http://127.0.0.1:6081/api/authors
GET http://127.0.0.1:6081/api/articles?page=2
GET http://127.0.0.1:6081/api/articles?itemsPerPage=30
EOF

# Run mixed load
vegeta attack -duration=60s -rate=200 -targets=targets.txt | vegeta report
```

**Expected**:
- Consistent performance across endpoints
- Cache working for all endpoints
- No degradation over time

### Scenario 8: Cache Invalidation Test
**Purpose**: Test cache behavior on content updates

**Test**:
```bash
# 1. Warm cache
ab -n 1000 -c 100 http://127.0.0.1:6081/api/articles

# 2. Trigger cache invalidation (simulate content update)
curl -X POST http://127.0.0.1:8081/api/articles \
  -H "Content-Type: application/ld+json" \
  -H "Authorization: Bearer TOKEN" \
  -d '{"title": "Test Article"}'

# 3. Verify cache miss on next request
curl -I http://127.0.0.1:6081/api/articles | grep "X-Cache"

# 4. Verify cache rebuilt
curl -I http://127.0.0.1:6081/api/articles | grep "X-Cache"
```

**Expected**:
- First request after invalidation: X-Cache: MISS
- Second request: X-Cache: HIT
- Cache invalidation working correctly

---

## 📈 Metrics to Collect

### Response Time Metrics
- **Average (mean)**: Overall average response time
- **Median (p50)**: 50th percentile
- **p95**: 95th percentile (target: <50ms cached, <300ms uncached)
- **p99**: 99th percentile (target: <100ms cached, <500ms uncached)
- **Max**: Worst case response time

### Throughput Metrics
- **Requests per second (RPS)**: Target: >1000 RPS cached
- **Transfer rate**: MB/s
- **Successful requests**: Should be >99%
- **Failed requests**: Should be <1%

### Cache Metrics
- **Cache hit rate**: Target: >85%
- **Cache miss rate**: Should be <15%
- **Cache hit ratio**: cache_hit / (cache_hit + cache_miss)

### Connection Pool Metrics
- **Active clients (cl_active)**: Current active connections
- **Waiting clients (cl_waiting)**: Should be 0 or minimal
- **Active servers (sv_active)**: Database connections in use
- **Idle servers (sv_idle)**: Available database connections
- **Pool efficiency**: cl_active / sv_active (target: >10:1)

### System Metrics
- **CPU usage**: Should stay <80%
- **Memory usage**: Should stay <80%
- **Network bandwidth**: Monitor saturation
- **Disk I/O**: Monitor database operations

---

## 🔬 Test Execution Plan

### Phase 1: Setup and Baseline (15 minutes)
1. ✅ Install load testing tools
2. ✅ Create test scripts
3. ✅ Verify all services running (Varnish, PgBouncer, PostgreSQL, Symfony)
4. ✅ Establish baseline metrics (single request)
5. ✅ Warm up cache

### Phase 2: Light Load Testing (20 minutes)
1. Run Scenario 2 (100 concurrent users)
2. Collect metrics
3. Analyze cache hit rates
4. Check for errors or warnings

### Phase 3: Medium Load Testing (20 minutes)
1. Run Scenario 3 (500 concurrent users)
2. Monitor system resources
3. Check connection pool status
4. Analyze performance degradation

### Phase 4: Heavy Load Testing (20 minutes)
1. Run Scenario 4 (1000 concurrent users)
2. Monitor for failures
3. Check connection pool limits
4. Measure maximum throughput

### Phase 5: Specialized Tests (30 minutes)
1. Cache efficiency test (Scenario 5)
2. Connection pool stress test (Scenario 6)
3. Mixed endpoint load (Scenario 7)
4. Cache invalidation test (Scenario 8)

### Phase 6: Analysis and Reporting (30 minutes)
1. Aggregate all metrics
2. Identify bottlenecks
3. Compare against targets
4. Document findings
5. Create recommendations

**Total Duration**: ~2 hours

---

## 📋 Pre-Test Checklist

### Services Status
- [ ] PostgreSQL 17.6 running on port 5432
- [ ] PgBouncer 1.24.1 running on port 6432
- [ ] Varnish 7.1.1 running on port 6081
- [ ] Symfony dev server running on port 8081
- [ ] All services connected and communicating

### Configuration Verification
- [ ] Varnish VCL loaded and compiled
- [ ] PgBouncer pool size configured (25 connections)
- [ ] Symfony using PgBouncer (port 6432)
- [ ] Cache headers configured in Symfony

### Tool Installation
- [ ] Apache Bench (ab) installed
- [ ] wrk installed
- [ ] curl with timing format
- [ ] varnishstat available
- [ ] psql (PostgreSQL client) available

### Baseline Verification
- [ ] Single request responds successfully
- [ ] Cache headers present (X-Cache, Age, Cache-Control)
- [ ] No errors in logs

---

## 🎯 Expected Results

### Performance Targets

| Metric | Light Load (100c) | Medium Load (500c) | Heavy Load (1000c) |
|--------|-------------------|--------------------|--------------------|
| **RPS** | >500 | >1000 | >1500 |
| **p95 (cached)** | <50ms | <100ms | <200ms |
| **p99 (cached)** | <100ms | <200ms | <400ms |
| **Cache Hit Rate** | >90% | >85% | >80% |
| **Error Rate** | <0.1% | <0.5% | <1% |
| **Pool Waiting** | 0 | 0-1 | 0-5 |

### Comparison with Week 1 Results

**Week 1 - No Varnish, No PgBouncer**:
- Response time: 426ms average
- No caching
- Direct PostgreSQL connection overhead: 20-50ms
- Limited concurrency

**Week 2 - With Varnish + PgBouncer**:
- Expected response time (cached): <50ms (90%+ improvement)
- Cache hit rate: >85%
- Connection pool overhead: 1-3ms (10x improvement)
- Support 1000+ concurrent connections

---

## 🚨 Failure Criteria

Tests will be considered **FAILED** if:
- Cache hit rate < 75%
- Error rate > 5%
- p95 response time > 500ms (cached endpoints)
- p95 response time > 2000ms (uncached endpoints)
- System crashes or becomes unresponsive
- Connection pool shows persistent waiting clients (>10)
- Memory or CPU usage reaches 100%

---

## 📝 Test Report Structure

The final report will include:

1. **Executive Summary**
   - Overall performance metrics
   - Pass/fail status
   - Key findings

2. **Baseline Metrics**
   - Single request performance
   - Cold cache vs warm cache

3. **Load Test Results**
   - Light, medium, heavy load results
   - Response time distributions
   - Throughput measurements

4. **Cache Analysis**
   - Hit/miss ratios
   - Cache effectiveness
   - Cache invalidation behavior

5. **Connection Pool Analysis**
   - Pool utilization
   - Efficiency ratios
   - Waiting clients analysis

6. **Bottleneck Identification**
   - System resource usage
   - Limiting factors
   - Optimization opportunities

7. **Recommendations**
   - Configuration tuning
   - Scaling strategies
   - Production readiness

---

## 🔧 Test Scripts

All test scripts will be created in:
- `load-test-light.sh` - 100 concurrent users
- `load-test-medium.sh` - 500 concurrent users
- `load-test-heavy.sh` - 1000 concurrent users
- `test-cache-efficiency.sh` - Cache validation
- `test-pool-stress.sh` - Connection pool stress test
- `run-all-load-tests.sh` - Master test runner

---

**Status**: 📋 Plan Complete - Ready to Execute
**Next**: Tool installation and test execution

