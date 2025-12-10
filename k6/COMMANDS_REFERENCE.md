# k6 Commands Quick Reference

## Essential Commands

### Run Tests

```bash
# Basic execution
k6 run load-test.js

# With custom VUs and duration
k6 run --vus 50 --duration 5m load-test.js

# With environment variables
k6 run -e BACKEND_URL=http://api.example.com load-test.js

# With output to JSON
k6 run --out json=results/test-$(date +%Y%m%d-%H%M%S).json load-test.js

# With cloud output (requires k6 Cloud account)
k6 run --out cloud load-test.js
```

### Test Suite Commands

```bash
# Standard load test (10 minutes)
k6 run load-test.js

# Stress test to find breaking point (14 minutes)
k6 run stress-test.js

# Soak test for memory leaks (70 minutes)
k6 run soak-test.js

# API-only test (5 minutes)
k6 run scenarios/api-endpoints.js

# Homepage test (5 minutes)
k6 run scenarios/homepage.js

# Full user journey (10 minutes)
k6 run scenarios/user-journey.js

# Spike test (8 minutes)
k6 run scenarios/spike-test.js
```

### Validation

```bash
# Validate test syntax
k6 inspect load-test.js

# Show test configuration
k6 inspect load-test.js | jq .

# Validate all tests
for test in *.js scenarios/*.js; do
  echo "Validating $test..."
  k6 inspect "$test" > /dev/null && echo "✓ Valid" || echo "✗ Invalid"
done
```

### Quick Smoke Tests

```bash
# 1 VU for 10 seconds (smoke test)
k6 run --vus 1 --duration 10s load-test.js

# 10 VUs for 30 seconds (quick test)
k6 run --vus 10 --duration 30s load-test.js

# 5 iterations only (functional test)
k6 run --iterations 5 load-test.js
```

## Output Options

### File Outputs

```bash
# JSON output
k6 run --out json=results.json load-test.js

# CSV output
k6 run --out csv=results.csv load-test.js

# InfluxDB output
k6 run --out influxdb=http://localhost:8086/mydb load-test.js

# Multiple outputs
k6 run --out json=results.json --out influxdb=http://localhost:8086/mydb load-test.js
```

### Cloud & Services

```bash
# k6 Cloud
k6 login cloud
k6 run --out cloud load-test.js

# Datadog
k6 run --out datadog load-test.js

# StatsD
k6 run --out statsd load-test.js
```

## Environment Variables

```bash
# Custom backend URL
k6 run -e BACKEND_URL=http://192.168.1.100:8081 load-test.js

# Custom frontend URL
k6 run -e FRONTEND_URL=http://192.168.1.100:3005 load-test.js

# Both
k6 run \
  -e BACKEND_URL=http://api.deschide.local \
  -e FRONTEND_URL=http://deschide.local \
  load-test.js
```

## Advanced Options

### Execution Control

```bash
# Limit max VUs
k6 run --max-vus 200 load-test.js

# Run specific stage
k6 run --stage 2m:100 load-test.js

# Multiple stages
k6 run --stage 1m:50 --stage 3m:100 --stage 1m:0 load-test.js

# Pause between iterations
k6 run --linger load-test.js
```

### Debugging

```bash
# Show HTTP logs
k6 run --http-debug load-test.js

# Show all logs
k6 run --log-output=stdout load-test.js

# Verbose mode
k6 run --verbose load-test.js

# Very verbose
k6 run -v --http-debug load-test.js
```

### Performance

```bash
# Disable color output (faster)
k6 run --no-color load-test.js

# Quiet mode (minimal output)
k6 run --quiet load-test.js

# Summary only
k6 run --summary-export=summary.json load-test.js
```

## Monitoring During Tests

### System Resources

```bash
# Terminal 1: Run test
k6 run load-test.js

# Terminal 2: Monitor CPU/Memory
watch -n 1 'ps aux | grep -E "(symfony|node|php)" | grep -v grep'

# Terminal 3: Monitor connections
watch -n 1 'netstat -an | grep -E ":(8081|3005)" | wc -l'

# Terminal 4: Monitor logs
tail -f /var/www/deschide_news_app/apps/backend/var/log/dev.log
```

### Database

```bash
# PostgreSQL connections
watch -n 1 "psql -U deschide_user -d deschide_news -c \"SELECT count(*) FROM pg_stat_activity;\""

# Database size
psql -U deschide_user -d deschide_news -c "\l+"

# Slow queries
psql -U deschide_user -d deschide_news -c "SELECT * FROM pg_stat_statements ORDER BY total_time DESC LIMIT 10;"
```

### Redis

```bash
# Monitor Redis
redis-cli -n 1 MONITOR

# Memory usage
redis-cli -n 1 INFO memory

# Key count
redis-cli -n 1 DBSIZE
```

### HTTP Monitoring

```bash
# Watch HTTP requests
sudo tcpdump -i lo -A 'port 8081' | grep -E "GET|POST|PUT|DELETE"

# Count requests per second
sudo tcpdump -i lo 'port 8081' | pv -l -i 1 > /dev/null
```

## Result Analysis

### JSON Results

```bash
# Run with JSON output
k6 run --out json=results.json load-test.js

# Extract metrics
jq '.metrics' results.json

# Get p95 for http_req_duration
jq -r 'select(.type=="Point" and .metric=="http_req_duration") | .data.value' results.json | \
  awk '{sum+=$1; count++} END {print "avg:", sum/count}'

# Count errors
jq -r 'select(.type=="Point" and .metric=="http_req_failed" and .data.value==1)' results.json | wc -l
```

### Summary Export

```bash
# Export summary
k6 run --summary-export=summary.json load-test.js

# View summary
cat summary.json | jq .

# Get specific metric
cat summary.json | jq '.metrics.http_req_duration.values.["p(95)"]'
```

## CI/CD Integration

### GitHub Actions

```yaml
- name: Run k6 load test
  run: |
    cd k6
    k6 run --out json=results.json load-test.js
  continue-on-error: true

- name: Upload results
  uses: actions/upload-artifact@v3
  with:
    name: k6-results
    path: k6/results.json
```

### GitLab CI

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
```

### Jenkins

```groovy
stage('Load Test') {
  steps {
    sh 'cd k6 && k6 run --out json=results.json load-test.js'
    archiveArtifacts artifacts: 'k6/results.json'
  }
}
```

## Troubleshooting

### Test Fails to Start

```bash
# Check k6 installation
k6 version

# Validate test file
k6 inspect load-test.js

# Check for syntax errors
node --check load-test.js
```

### High Error Rates

```bash
# Check services are running
curl http://127.0.0.1:8081/api
curl http://localhost:3005

# Check database connectivity
psql -U deschide_user -d deschide_news -c "SELECT 1;"

# Reduce load
k6 run --vus 10 --duration 1m load-test.js
```

### Connection Timeouts

```bash
# Increase timeout in test script (edit load-test.js)
# Add: { timeout: '60s' } to http.get() calls

# Check network
ping 127.0.0.1
netstat -an | grep 8081

# Check max connections
ulimit -n
```

## Best Practices

### Pre-Test Checklist

```bash
# 1. Check all services
systemctl status postgresql
systemctl status redis
symfony server:status
pm2 status

# 2. Verify database
psql -U deschide_user -d deschide_news -c "SELECT COUNT(*) FROM articles;"

# 3. Clear caches
symfony console cache:clear
redis-cli -n 1 FLUSHDB

# 4. Baseline test
k6 run --vus 1 --duration 30s load-test.js
```

### Post-Test Checklist

```bash
# 1. Check logs for errors
tail -100 /var/www/deschide_news_app/apps/backend/var/log/dev.log

# 2. Check database performance
psql -U deschide_user -d deschide_news -c "SELECT * FROM pg_stat_statements ORDER BY total_time DESC LIMIT 5;"

# 3. Check cache hit rate
redis-cli -n 1 INFO stats | grep hit_rate

# 4. Archive results
mv results.json results/$(date +%Y%m%d-%H%M%S)-load-test.json
```

## Quick Reference Summary

| Command | Purpose |
|---------|---------|
| `k6 run load-test.js` | Standard load test |
| `k6 run stress-test.js` | Find breaking point |
| `k6 run soak-test.js` | Memory leak detection |
| `k6 run --vus 1 --duration 10s test.js` | Quick smoke test |
| `k6 inspect test.js` | Validate syntax |
| `k6 run --out json=results.json test.js` | Save results |
| `k6 run -e BACKEND_URL=url test.js` | Custom backend |
| `k6 run --http-debug test.js` | Debug HTTP |

## Help & Documentation

```bash
# General help
k6 --help

# Command-specific help
k6 run --help

# Version info
k6 version

# Official docs
xdg-open https://k6.io/docs/
```
