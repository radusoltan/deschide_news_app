# k6 Load Testing - Quick Start Guide

## 5-Minute Quickstart

### Step 1: Verify Installation

```bash
k6 version
# Expected: k6 v1.4.0 or higher
```

If not installed:
```bash
sudo apt-get update && sudo apt-get install k6
```

### Step 2: Start Application

```bash
# Terminal 1: Backend
cd /var/www/deschide_news_app/apps/backend
symfony serve -d --port=8081
symfony server:status

# Terminal 2: Frontend (optional for full tests)
cd /var/www/deschide_news_app/apps/frontend
pnpm dev
```

### Step 3: Run Your First Test

```bash
cd /var/www/deschide_news_app/k6

# Start with the load test (10 minutes)
k6 run load-test.js
```

## Test Matrix

| Test | Command | Duration | VUs | Use Case |
|------|---------|----------|-----|----------|
| **Load** | `k6 run load-test.js` | 10 min | 100 | Daily testing |
| **Stress** | `k6 run stress-test.js` | 14 min | 500 | Find limits |
| **Soak** | `k6 run soak-test.js` | 70 min | 50 | Memory leaks |
| **Spike** | `k6 run scenarios/spike-test.js` | 5 min | 500 | Viral traffic |
| **API** | `k6 run scenarios/api-endpoints.js` | 5 min | 50 | API health |
| **Journey** | `k6 run scenarios/user-journey.js` | 10 min | 20 | User flows |

## Common Commands

```bash
# Run test with custom backend URL
k6 run -e BACKEND_URL=http://api.deschide.local load-test.js

# Run test and save results
k6 run --out json=results/test-$(date +%Y%m%d-%H%M%S).json load-test.js

# Run shorter test for quick check
k6 run --duration 1m --vus 10 load-test.js

# Run test with custom stages (override default)
k6 run --stage 1m:50,2m:100,1m:0 stress-test.js

# Show test summary only (no progress)
k6 run --summary-export=results/summary.json load-test.js --quiet
```

## Interpreting Results

### Good Results Example

```
✓ articles status is 200
✓ categories status is 200
✓ homepage status is 200

checks.........................: 98.50% ✓ 9850    ✗ 150
http_req_duration..............: avg=125ms  p(95)=350ms p(99)=680ms
http_req_failed................: 0.15%   ✓ 15      ✗ 9985
http_reqs......................: 10000   16.67/s
```

**Analysis**: System healthy, P95 < 500ms, error rate < 1%

### Problem Results Example

```
✗ articles status is 200
  ↳  45% — ✓ 4500 / ✗ 5500

checks.........................: 45.00% ✓ 4500    ✗ 5500
http_req_duration..............: avg=3250ms  p(95)=8500ms p(99)=12000ms
http_req_failed................: 55.00%  ✓ 5500    ✗ 4500
```

**Analysis**: System overloaded, P95 > 8s, error rate > 50%
**Action**: Scale infrastructure or optimize bottleneck

## Thresholds Reference

| Metric | Target | Warning | Critical |
|--------|--------|---------|----------|
| P95 Response Time | < 500ms | 500-2000ms | > 2000ms |
| P99 Response Time | < 1s | 1-5s | > 5s |
| Error Rate | < 1% | 1-5% | > 5% |
| RPS (Requests/sec) | Stable | Fluctuating | Decreasing |

## Troubleshooting

### "Connection Refused"

```bash
# Check if backend is running
curl http://127.0.0.1:8081/api

# If not, start it
cd /var/www/deschide_news_app/apps/backend
symfony serve -d --port=8081
```

### "High Error Rate"

1. Check backend logs: `symfony server:log`
2. Reduce load: `k6 run --stage 1m:10 load-test.js`
3. Verify Redis: `redis-cli ping`
4. Check PostgreSQL: `psql -U deschide_user -d deschide_news -c "SELECT 1;"`

### "Inconsistent Results"

```bash
# Clear cache before test
redis-cli FLUSHDB
symfony console cache:clear

# Run test 3 times, take average
for i in {1..3}; do k6 run load-test.js; sleep 30; done
```

## Next Steps

1. **Baseline**: Run `load-test.js` and document results
2. **Limits**: Run `stress-test.js` to find breaking point
3. **Stability**: Run `soak-test.js` overnight to check for leaks
4. **Monitor**: Set up regular testing (weekly)
5. **Optimize**: Fix bottlenecks found during tests

## Full Documentation

- **Complete Guide**: [README.md](README.md)
- **Test Suite Overview**: [TEST_SUITE_OVERVIEW.md](TEST_SUITE_OVERVIEW.md)
- **k6 Documentation**: https://k6.io/docs/

---

**Need help?** Check the [README.md](README.md) or [TEST_SUITE_OVERVIEW.md](TEST_SUITE_OVERVIEW.md)
