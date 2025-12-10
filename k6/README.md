# k6 Load Testing - Deschide News App

This directory contains k6 load testing scripts for the Deschide News App, a multilingual news platform built with Symfony 7.3 (backend) and Next.js 16 (frontend).

## Table of Contents

- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Test Scripts](#test-scripts)
- [Environment Variables](#environment-variables)
- [Interpreting Results](#interpreting-results)
- [CI/CD Integration](#cicd-integration)
- [Best Practices](#best-practices)

## Prerequisites

- **k6**: Load testing tool
- **Backend**: Symfony API running on http://127.0.0.1:8081
- **Frontend**: Next.js app running on http://localhost:3005
- **Database**: PostgreSQL with test data
- **Services**: Redis, Elasticsearch (optional but recommended)

## Installation

### Install k6

**Ubuntu/Debian:**
```bash
sudo gpg -k
sudo gpg --no-default-keyring --keyring /usr/share/keyrings/k6-archive-keyring.gpg --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D69
echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" | sudo tee /etc/apt/sources.list.d/k6.list
sudo apt-get update
sudo apt-get install k6
```

**macOS:**
```bash
brew install k6
```

**Windows:**
```powershell
choco install k6
```

**Verify installation:**
```bash
k6 version
```

## Quick Start

1. **Ensure services are running:**
```bash
# Terminal 1 - Backend
cd /var/www/deschide_news_app/apps/backend
symfony serve -d --port=8081

# Terminal 2 - Frontend
cd /var/www/deschide_news_app/apps/frontend
pnpm dev
```

2. **Run a simple test:**
```bash
cd /var/www/deschide_news_app/k6
k6 run load-test.js
```

3. **View results in real-time:**
```bash
k6 run --out json=results.json load-test.js
```

## Test Scripts

### 1. Load Test (`load-test.js`)

**Purpose:** Standard load test to verify system performance under normal conditions.

**Profile:**
- Duration: ~10 minutes
- Users: 10 → 100 (peak)
- Realistic user behavior simulation

**Run:**
```bash
k6 run load-test.js
```

**Thresholds:**
- Articles API: p(95) < 300ms
- Categories API: p(95) < 150ms
- Homepage: p(95) < 2000ms
- Error rate: < 5%

### 2. Stress Test (`stress-test.js`)

**Purpose:** Find the breaking point of the system under extreme load.

**Profile:**
- Duration: ~14 minutes
- Users: 100 → 500 (breaking point)
- Reduced think time for increased stress

**Run:**
```bash
k6 run stress-test.js
```

**Thresholds:**
- Response time: p(95) < 2000ms
- Error rate: < 15% (relaxed)

**Use case:** Identify maximum capacity and failure modes.

### 3. Soak Test (`soak-test.js`)

**Purpose:** Detect memory leaks and performance degradation over time.

**Profile:**
- Duration: ~70 minutes (1 hour + ramp)
- Users: 50 (constant)
- Normal load for extended period

**Run:**
```bash
k6 run soak-test.js
```

**Thresholds:**
- Same as load test
- Watch for gradual performance degradation

**Use case:** Production readiness, memory leak detection.

### 4. Scenarios

#### API Endpoints (`scenarios/api-endpoints.js`)

**Purpose:** Focus exclusively on API performance without frontend overhead.

**Run:**
```bash
k6 run scenarios/api-endpoints.js
```

**Tests:**
- All API endpoints (articles, categories, authors, images, important)
- JSON-LD format validation
- Hydra specification compliance

#### Homepage (`scenarios/homepage.js`)

**Purpose:** Test frontend homepage performance across all locales.

**Run:**
```bash
k6 run scenarios/homepage.js
```

**Tests:**
- Homepage load time
- HTML content validation
- Multilingual support (ro, en, ru)

#### User Journey (`scenarios/user-journey.js`)

**Purpose:** Simulate realistic user behavior end-to-end.

**Run:**
```bash
k6 run scenarios/user-journey.js
```

**Journey:**
1. Visit homepage
2. Browse categories
3. Read 2-3 articles
4. Switch language
5. Verify multilingual functionality

## Environment Variables

Override default URLs with environment variables:

```bash
# Custom backend URL
k6 run -e BACKEND_URL=http://api.deschide.local load-test.js

# Custom frontend URL
k6 run -e FRONTEND_URL=http://deschide.local load-test.js

# Both
k6 run -e BACKEND_URL=http://api.deschide.local -e FRONTEND_URL=http://deschide.local load-test.js
```

**Available variables:**
- `BACKEND_URL`: Symfony API endpoint (default: http://127.0.0.1:8081)
- `FRONTEND_URL`: Next.js frontend (default: http://localhost:3005)

## Interpreting Results

### Key Metrics

**HTTP Request Duration:**
- `avg`: Average response time
- `min`: Fastest response
- `med`: Median (50th percentile)
- `max`: Slowest response
- `p(90)`: 90% of requests faster than this
- `p(95)`: 95% of requests faster than this
- `p(99)`: 99% of requests faster than this

**Custom Metrics:**
- `articles_duration`: API article endpoint performance
- `categories_duration`: API category endpoint performance
- `homepage_duration`: Frontend homepage load time
- `error_rate`: Percentage of failed requests
- `total_requests`: Total number of requests made

### Success Criteria

**Load Test:**
- ✅ p(95) < 500ms for all endpoints
- ✅ Error rate < 5%
- ✅ No server errors (5xx)

**Stress Test:**
- ✅ System recovers after load reduction
- ✅ Identifies breaking point (max VUs)
- ⚠️ Error rate < 15% at peak

**Soak Test:**
- ✅ No performance degradation over time
- ✅ Memory usage remains stable
- ✅ Error rate < 1% throughout

### Example Output

```
     ✓ articles status is 200
     ✓ categories status is 200
     ✓ homepage loaded

     checks.........................: 98.50% ✓ 2955    ✗ 45
     data_received..................: 15 MB  25 kB/s
     data_sent......................: 1.2 MB 2.0 kB/s
     http_req_duration..............: avg=245ms  min=45ms  med=200ms  max=1.2s p(95)=450ms
     http_req_failed................: 1.50%  ✓ 45      ✗ 2955
     iterations.....................: 750    1.25/s
     vus............................: 100    min=0     max=100
```

### Troubleshooting

**High error rates:**
- Check if services are running
- Verify database connectivity
- Check server logs for errors
- Reduce load (lower VUs)

**Slow response times:**
- Check database query performance
- Review API endpoint optimization
- Verify caching is enabled (Redis)
- Check Elasticsearch performance

**Connection timeouts:**
- Increase timeout in test scripts
- Check network connectivity
- Verify firewall rules
- Check service health

## CI/CD Integration

### GitHub Actions Example

```yaml
name: Load Testing

on:
  push:
    branches: [develop, main]
  pull_request:
    branches: [develop]

jobs:
  load-test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Install k6
        run: |
          sudo gpg --no-default-keyring --keyring /usr/share/keyrings/k6-archive-keyring.gpg --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D69
          echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" | sudo tee /etc/apt/sources.list.d/k6.list
          sudo apt-get update
          sudo apt-get install k6

      - name: Start services
        run: |
          docker-compose up -d
          sleep 30

      - name: Run load test
        run: |
          cd k6
          k6 run --out json=results.json load-test.js

      - name: Upload results
        uses: actions/upload-artifact@v3
        with:
          name: k6-results
          path: k6/results.json
```

### GitLab CI Example

```yaml
load-test:
  stage: test
  image: grafana/k6:latest
  script:
    - cd k6
    - k6 run --out json=results.json load-test.js
  artifacts:
    paths:
      - k6/results.json
    expire_in: 1 week
  only:
    - develop
    - main
```

### k6 Cloud (Optional)

For advanced metrics and visualization:

```bash
# Sign up at https://app.k6.io/
k6 login cloud

# Run test with cloud output
k6 run --out cloud load-test.js
```

## Best Practices

### Before Running Tests

1. **Ensure stable environment:**
   - All services running (backend, frontend, database, cache)
   - No other intensive processes
   - Sufficient system resources

2. **Prepare test data:**
   - Database populated with realistic data
   - At least 1000+ articles for meaningful tests
   - Multiple categories, authors, images

3. **Baseline metrics:**
   - Run a single-user test first
   - Document baseline performance
   - Compare subsequent tests to baseline

### During Tests

1. **Monitor system resources:**
   ```bash
   # CPU and memory
   htop
   
   # Database connections
   pg_top
   
   # Redis memory
   redis-cli info memory
   ```

2. **Check logs:**
   ```bash
   # Backend logs
   tail -f /var/www/deschide_news_app/apps/backend/var/log/dev.log
   
   # Frontend logs
   pm2 logs deschide_frontend
   ```

3. **Real-time metrics:**
   - Grafana dashboards (if configured)
   - Prometheus metrics
   - Application monitoring tools

### After Tests

1. **Analyze results:**
   - Review p(95) and p(99) percentiles
   - Identify bottlenecks
   - Check error patterns

2. **Document findings:**
   - Save results to `k6/results/` directory
   - Create performance reports
   - Track improvements over time

3. **Optimize:**
   - Database queries (indexes, N+1 problems)
   - API response size (pagination, serialization)
   - Cache configuration (TTL, invalidation)
   - Frontend performance (bundle size, SSR)

## Support

For issues or questions:
- Check k6 documentation: https://k6.io/docs/
- Review project documentation: `/var/www/deschide_news_app/CLAUDE.md`
- Contact development team

## Version History

- **v1.0.0** (2024-12-02): Initial k6 setup with load, stress, and soak tests
