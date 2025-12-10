# k6 Load Testing Suite Overview

## Complete Test Suite for Deschide News App

This directory contains a comprehensive load testing suite using Grafana k6 to evaluate the performance, reliability, and scalability of the Deschide News App.

## Test Files

| File | Type | Duration | Max VUs | Purpose |
|------|------|----------|---------|---------|
| `load-test.js` | Load Test | ~10 min | 100 | General performance baseline |
| `stress-test.js` | Stress Test | ~20 min | 500 | Find breaking point |
| `soak-test.js` | Soak Test | ~65 min | 50 | Memory leaks, stability |
| `scenarios/spike-test.js` | Spike Test | ~5 min | 500 | Traffic burst handling |

## Quick Start

### Prerequisites

```bash
# Check if k6 is installed
k6 version

# If not installed (Ubuntu/Debian)
sudo apt-get update
sudo apt-get install k6
```

### Running Tests

```bash
cd /var/www/deschide_news_app/k6

# 1. Load Test (recommended to start)
k6 run load-test.js

# 2. Stress Test (find limits)
k6 run stress-test.js

# 3. Soak Test (1 hour - run overnight)
k6 run soak-test.js

# 4. Spike Test (traffic bursts)
k6 run scenarios/spike-test.js
```

## Test Descriptions

### 1. Load Test (`load-test.js`)

**Purpose**: Baseline performance testing with realistic user behavior.

**What it tests**:
- Articles API endpoint (list + detail)
- Categories API endpoint
- Frontend homepage (all locales)
- Mixed read operations

**Load Pattern**:
```
Warm up:     30s → 10 VUs
Ramp up:     1m  → 25 VUs
Ramp up:     2m  → 50 VUs
Steady:      3m  @ 50 VUs
Peak:        1m  → 100 VUs
Steady peak: 2m  @ 100 VUs
Ramp down:   1m  → 0 VUs
```

**Thresholds**:
- Articles P95 < 300ms
- Categories P95 < 150ms
- Homepage P95 < 2s
- Error rate < 5%

**Use this test**:
- Daily/weekly regression testing
- After deployments to verify performance
- Baseline comparison before optimizations

### 2. Stress Test (`stress-test.js`)

**Purpose**: Find the breaking point of the system.

**What it tests**:
- Articles API endpoint under extreme load
- System behavior at 5x-10x normal load
- Recovery after stress

**Load Pattern**:
```
Normal:       2m  → 50 VUs
Above normal: 2m  → 100 VUs
High load:    2m  → 200 VUs
Stress:       3m  → 300 VUs
Breaking:     3m  → 500 VUs
Sustain max:  3m  @ 500 VUs
Recovery:     2m  → 100 VUs
Ramp down:    2m  → 0 VUs
```

**Thresholds** (more lenient):
- P95 < 2s
- P99 < 5s
- Error rate < 15%

**What to look for**:
- At what VU count does P95 exceed 2s?
- When does error rate spike above 10%?
- Does system recover after load reduction?

**Use this test**:
- Capacity planning
- Infrastructure scaling decisions
- Before major traffic events (viral content)

### 3. Soak Test (`soak-test.js`)

**Purpose**: Detect memory leaks and performance degradation over time.

**What it tests**:
- Sustained moderate load for 1 hour
- Memory leak detection
- Resource exhaustion over time
- Multiple endpoints rotation

**Load Pattern**:
```
Ramp up: 5m  → 50 VUs
Soak:    55m @ 50 VUs
Ramp down: 5m → 0 VUs
```

**Thresholds**:
- P95 < 500ms
- P99 < 1s
- Error rate < 1%

**What to look for**:
- Response time increase over time (>50% = problem)
- Memory growth in backend processes
- Connection pool exhaustion
- Redis memory growth

**Monitoring during soak**:
```bash
# Terminal 1: Run test
k6 run soak-test.js

# Terminal 2: Monitor PHP memory
watch -n 60 'ps aux | grep php-fpm | awk "{sum+=\$6} END {print sum/1024 \" MB\"}"'

# Terminal 3: Monitor Redis
watch -n 60 'redis-cli INFO memory | grep used_memory_human'

# Terminal 4: Monitor PostgreSQL
watch -n 60 'psql -U deschide_user -d deschide_news -c "SELECT count(*) FROM pg_stat_activity;"'
```

**Use this test**:
- Weekly overnight runs
- After major backend changes
- Before production releases

### 4. Spike Test (`scenarios/spike-test.js`)

**Purpose**: Test system behavior under sudden traffic spikes.

**Scenario**: A breaking news article goes viral on social media.

**Load Pattern**:
```
Baseline:  1m   @ 10 VUs
Spike:     10s  → 500 VUs  (50x increase!)
Sustain:   1m   @ 500 VUs
Scale down: 10s → 10 VUs
Recovery:  2m   @ 10 VUs
Ramp down: 10s  → 0 VUs
```

**Thresholds** (most lenient):
- P95 < 3s
- Error rate < 30% (some failures expected)

**What to look for**:
- Does the system handle the spike without crashing?
- Are there rate limiting responses (429)?
- How quickly does it recover?
- Are requests queued or dropped?

**Use this test**:
- Test rate limiting
- Validate auto-scaling (if configured)
- Verify queue handling

## Configuration

### Environment Variables

Override defaults using environment variables:

```bash
# Custom backend URL
k6 run -e BACKEND_URL=http://api.deschide.local load-test.js

# Custom frontend URL
k6 run -e FRONTEND_URL=http://deschide.local load-test.js

# Both
k6 run -e BACKEND_URL=http://api.deschide.local -e FRONTEND_URL=http://deschide.local load-test.js
```

### Shared Configuration (`config.js`)

All tests import shared configuration:

```javascript
export const CONFIG = {
  BACKEND_URL: __ENV.BACKEND_URL || 'http://127.0.0.1:8081',
  FRONTEND_URL: __ENV.FRONTEND_URL || 'http://localhost:3005',
  LOCALES: ['ro', 'en', 'ru'],
};
```

## Test Results

### Output Locations

All test results are saved to `results/` directory:

```
k6/results/
├── load-test-results.json
├── stress-test-results.json
├── soak-test-results.json
└── spike-test-results.json
```

### Interpreting Results

#### Response Time Metrics

| Metric | Good | Warning | Critical |
|--------|------|---------|----------|
| **P50 (Median)** | < 100ms | 100-300ms | > 300ms |
| **P95** | < 500ms | 500-2000ms | > 2000ms |
| **P99** | < 1s | 1-5s | > 5s |

#### Error Rates

| Metric | Good | Warning | Critical |
|--------|------|---------|----------|
| **Error Rate** | < 0.1% | 0.1-1% | > 1% |
| **Stress Test** | < 5% | 5-15% | > 15% |
| **Spike Test** | < 10% | 10-30% | > 30% |

#### Sample Output

```
═══════════════════════════════════════════════════════
         LOAD TEST SUMMARY - DESCHIDE NEWS APP
═══════════════════════════════════════════════════════

Test Duration: 600.25s

VUs (max): 100
Iterations: 2450

HTTP Request Duration:
  avg: 185.32ms
  min: 42.15ms
  med: 156.78ms
  max: 2341.89ms
  p(90): 298.45ms
  p(95): 412.67ms
  p(99): 856.23ms

Articles Duration (p95): 278.45ms
Categories Duration (p95): 142.89ms
Homepage Duration (p95): 1245.67ms

Error Rate: 0.82%
Total Requests: 9800

═══════════════════════════════════════════════════════
```

**Analysis**:
- ✅ P95 within thresholds (412ms < 500ms)
- ✅ Error rate acceptable (0.82% < 1%)
- ⚠️ Homepage P95 slightly high (1245ms, target < 2000ms but aim for < 1000ms)
- 🎯 System handles 100 VUs with ~10k requests successfully

## Best Practices

### Before Running Tests

1. **Ensure services are running**:
   ```bash
   # Backend
   symfony server:status
   curl http://127.0.0.1:8081/api

   # Frontend
   curl http://localhost:3005

   # Redis
   redis-cli ping

   # PostgreSQL
   psql -U deschide_user -d deschide_news -c "SELECT 1;"
   ```

2. **Clear cache** (optional, for consistent results):
   ```bash
   redis-cli FLUSHDB
   symfony console cache:clear
   ```

3. **Baseline measurement**: Take note of current resource usage
   ```bash
   free -h
   ps aux | grep php-fpm
   ```

### During Tests

1. **Monitor resources**:
   ```bash
   # CPU usage
   top

   # Memory
   watch -n 5 free -h

   # Network
   nethogs

   # Logs
   tail -f /var/www/deschide_news_app/apps/backend/var/log/prod.log
   ```

2. **Watch for errors**:
   ```bash
   # Backend logs
   symfony server:log

   # PostgreSQL slow queries
   tail -f /var/log/postgresql/postgresql-17-main.log
   ```

### After Tests

1. **Review results**: Check `results/*.json` files
2. **Compare trends**: Is performance improving or degrading?
3. **Document findings**: Record breaking points and bottlenecks
4. **Take action**: Optimize bottlenecks or scale infrastructure

## Testing Schedule

### Daily (Automated CI/CD)

```yaml
# Run basic load test after deployments
- Load Test (10 minutes)
```

### Weekly

```bash
# Run comprehensive suite
1. Load Test
2. Stress Test
3. Document breaking point
```

### Monthly

```bash
# Full suite including soak test
1. Load Test
2. Stress Test
3. Soak Test (overnight)
4. Spike Test
5. Generate performance report
```

### Before Major Releases

```bash
# Complete validation
1. All tests above
2. Compare with previous release
3. Document any regressions
4. Sign-off on performance
```

## Troubleshooting

### Connection Refused Errors

**Problem**: k6 can't connect to backend/frontend.

```bash
# Check if services are running
symfony server:status
curl http://127.0.0.1:8081/api
curl http://localhost:3005

# Start services if needed
cd /var/www/deschide_news_app/apps/backend
symfony serve -d --port=8081

cd /var/www/deschide_news_app/apps/frontend
pnpm dev
```

### High Error Rate (>50%)

**Problem**: System overwhelmed or misconfigured.

1. Check logs: `symfony server:log`
2. Reduce VUs: `k6 run --stage 1m:10 load-test.js`
3. Verify database connections
4. Check Redis: `redis-cli ping`
5. Review PHP-FPM status: `sudo systemctl status php8.4-fpm`

### Out of Memory

**Problem**: k6 or backend runs out of memory.

```bash
# Check memory usage
free -h

# Backend memory
ps aux | grep php | awk '{sum+=$6} END {print sum/1024 "MB"}'

# Reduce VUs or duration
k6 run --stage 1m:50,1m:0 stress-test.js
```

### Inconsistent Results

**Problem**: Results vary widely between runs.

**Causes**:
- Cache state (hot vs cold)
- Background processes
- System load

**Solutions**:
1. Clear cache before tests
2. Run tests multiple times (3-5)
3. Calculate average/median
4. Ensure system is idle (no other load)

## Integration with CI/CD

### GitHub Actions Example

```yaml
name: Weekly Load Test

on:
  schedule:
    - cron: '0 2 * * 0'  # Sunday 2 AM
  workflow_dispatch:       # Manual trigger

jobs:
  load-test:
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:17
        env:
          POSTGRES_PASSWORD: password
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
      redis:
        image: redis:7
        options: >-
          --health-cmd "redis-cli ping"
          --health-interval 10s

    steps:
      - uses: actions/checkout@v4

      - name: Install k6
        run: |
          sudo apt-get update
          sudo apt-get install -y k6

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'

      - name: Install dependencies
        run: |
          cd apps/backend
          composer install --no-dev

      - name: Start backend
        run: |
          cd apps/backend
          symfony server:start -d --port=8081

      - name: Run load test
        run: |
          cd k6
          k6 run --out json=results/load-test-ci.json load-test.js

      - name: Upload results
        uses: actions/upload-artifact@v3
        with:
          name: k6-results
          path: k6/results/

      - name: Comment PR with results
        if: github.event_name == 'pull_request'
        uses: actions/github-script@v6
        with:
          script: |
            const fs = require('fs');
            const results = JSON.parse(fs.readFileSync('k6/results/load-test-ci.json'));
            // Post comment with results...
```

## Advanced Topics

### Custom Scenarios

Create custom test scenarios for specific use cases:

```javascript
// scenarios/breaking-news-spike.js
export const options = {
  scenarios: {
    breaking_news: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '30s', target: 1000 }, // Viral!
        { duration: '2m', target: 1000 },
        { duration: '30s', target: 50 },
      ],
      gracefulRampDown: '30s',
    },
  },
};
```

### Distributed Load Testing

Run tests from multiple geographic locations:

```bash
# Run k6 cloud for distributed testing
k6 cloud stress-test.js

# Or use multiple machines
# Machine 1
k6 run --out json=results1.json stress-test.js

# Machine 2
k6 run --out json=results2.json stress-test.js
```

### Monitoring Integration

**With Prometheus**:
```bash
k6 run --out experimental-prometheus-rw stress-test.js
```

**With Grafana**:
1. Import k6 dashboard (ID: 2587)
2. Configure Prometheus data source
3. View real-time metrics

## Resources

- **k6 Documentation**: https://k6.io/docs/
- **Load Testing Guide**: https://k6.io/docs/testing-guides/api-load-testing/
- **k6 Examples**: https://github.com/grafana/k6-example-test-suites
- **k6 Cloud**: https://k6.io/cloud/
- **Project Docs**: `/var/www/deschide_news_app/docs/`

## Support

For questions or issues:
- Review this documentation
- Check k6 docs: https://k6.io/docs/
- k6 Community: https://community.k6.io/
- Internal docs: `/var/www/deschide_news_app/CLAUDE.md`

---

**Last Updated**: 2025-12-02
**k6 Version**: v1.4.0
**Author**: Deschide News App Team
