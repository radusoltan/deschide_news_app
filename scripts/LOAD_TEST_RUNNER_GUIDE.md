# Load Test Runner Script Guide

## Overview

The `run-load-tests.sh` script provides an interactive, user-friendly interface for running k6 load tests against the Deschide News App backend and frontend.

**Location:** `/var/www/deschide_news_app/scripts/run-load-tests.sh`

## Features

- ✅ Interactive menu for selecting test type
- ✅ Automatic validation of k6 installation
- ✅ Backend connectivity checks
- ✅ Pre-flight system validation
- ✅ Test results automatically saved with timestamps
- ✅ Colored terminal output for easy reading
- ✅ Support for environment variable configuration
- ✅ Comprehensive help system
- ✅ Direct test execution via command-line arguments

## Quick Start

### Run Interactive Menu
```bash
cd /var/www/deschide_news_app
./scripts/run-load-tests.sh
```

### Run Specific Test
```bash
# Load test
./scripts/run-load-tests.sh load

# Stress test
./scripts/run-load-tests.sh stress

# Soak test
./scripts/run-load-tests.sh soak

# Spike test
./scripts/run-load-tests.sh spike

# API-only test
./scripts/run-load-tests.sh api

# Quick validation
./scripts/run-load-tests.sh quick
```

### Show Help
```bash
./scripts/run-load-tests.sh --help
# or
./scripts/run-load-tests.sh -h
# or
./scripts/run-load-tests.sh help
```

## Test Types

### 1. Load Test (10 minutes)
**Purpose:** Simulate standard production load

**Configuration:**
- Ramp-up: 1 minute (0-50 users)
- Peak: 3 minutes (50 users)
- Ramp-up: 2 minutes (50-100 users)
- Duration: 3 minutes (100 users)
- Ramp-down: 1 minute (100-0 users)

**Use Case:** Daily performance monitoring, baseline establishment

### 2. Stress Test (17 minutes)
**Purpose:** Find system breaking point by gradually increasing load

**Configuration:**
- Ramp-up: Gradual increase up to 500 users
- Duration: Until system failure or timeout
- Targets: Identify maximum capacity

**Use Case:** Capacity planning, infrastructure validation

### 3. Soak Test (65 minutes)
**Purpose:** Detect memory leaks and performance degradation over time

**Configuration:**
- Steady load: 50 users for 65 minutes
- Monitors: Memory usage, response times, error rates

**Use Case:** Long-running stability validation, memory leak detection

### 4. Spike Test (5 minutes)
**Purpose:** Simulate sudden traffic spikes

**Configuration:**
- Baseline: 20 users
- Spike: Ramp to 300+ users in 30 seconds
- Recovery monitoring

**Use Case:** Black Friday/viral event readiness, sudden load handling

### 5. API-Only Test (5 minutes)
**Purpose:** Test API endpoints in isolation (no frontend)

**Endpoints Tested:**
- GET /api/articles (list)
- GET /api/categories (list)
- GET /api/articles/{id} (single)
- GET /api/tags (list)
- GET /api/authors (list)

**Use Case:** API performance baseline, backend validation

### 6. Quick Validation (2 minutes)
**Purpose:** Quick sanity check with minimal load

**Configuration:**
- 10 virtual users
- 2-minute duration
- Fast feedback loop

**Use Case:** Pre-deployment validation, quick checks

## Configuration

### Backend URL
Default: `http://127.0.0.1:8081`

Override with environment variable:
```bash
BACKEND_URL=http://api.example.com ./scripts/run-load-tests.sh load
```

### Frontend URL
Default: `http://localhost:3005`

Override with environment variable:
```bash
FRONTEND_URL=http://app.example.com ./scripts/run-load-tests.sh load
```

### Both URLs
```bash
BACKEND_URL=http://api.prod.com FRONTEND_URL=http://app.prod.com \
  ./scripts/run-load-tests.sh load
```

## Results

All test results are automatically saved to: `/var/www/deschide_news_app/k6/results/`

Each test generates:

### JSON Results File
Filename: `{test-name}_{timestamp}.json`

Example: `load-test_20241202_140530.json`

Contains:
- Raw metrics data
- Detailed request/response information
- Performance statistics
- Error details

**Usage:** Import into k6 Cloud or analyze locally

### Log File
Filename: `{test-name}_{timestamp}.log`

Example: `load-test_20241202_140530.log`

Contains:
- Human-readable test output
- Real-time metrics summary
- Pass/fail check results
- Error messages

**Usage:** Quick review and debugging

### View Results
```bash
# List all results
ls -lah /var/www/deschide_news_app/k6/results/

# View latest log
tail -50 /var/www/deschide_news_app/k6/results/load-test_*.log

# Analyze JSON results
jq '.metrics | keys' /var/www/deschide_news_app/k6/results/load-test_*.json
```

## Pre-flight Checks

The script automatically validates:

1. **k6 Installation**
   - Checks if k6 is available
   - Shows installation instructions if not found

2. **Backend Connectivity**
   - Verifies backend is running at configured URL
   - Shows startup commands if not accessible

3. **Test Files**
   - Confirms all test files exist
   - Lists missing test files

## Installation Requirements

### k6 Installation

#### Ubuntu/Debian
```bash
sudo gpg -k
sudo gpg --no-default-keyring --keyring /usr/share/keyrings/k6-archive-keyring.gpg \
  --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D69

echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" | \
  sudo tee /etc/apt/sources.list.d/k6.list

sudo apt-get update
sudo apt-get install k6
```

#### macOS
```bash
brew install k6
```

#### Snap
```bash
sudo snap install k6
```

#### Verify Installation
```bash
k6 version
```

## Examples

### Basic Load Test
```bash
./scripts/run-load-tests.sh load
```

### Production Testing
```bash
BACKEND_URL=https://api.deschide.ro \
  FRONTEND_URL=https://deschide.ro \
  ./scripts/run-load-tests.sh stress
```

### Staging Validation
```bash
BACKEND_URL=https://api-staging.deschide.ro \
  FRONTEND_URL=https://staging.deschide.ro \
  ./scripts/run-load-tests.sh load
```

### Overnight Soak Test
```bash
# Run in background
nohup ./scripts/run-load-tests.sh soak > soak_test.log 2>&1 &
```

### Quick Validation Before Deploy
```bash
./scripts/run-load-tests.sh quick
```

## Troubleshooting

### k6 Not Found
**Error:** `Error: k6 is not installed`

**Solution:** Install k6 (see Installation Requirements above)

### Backend Not Accessible
**Error:** `Backend is not accessible at http://127.0.0.1:8081`

**Solution:** Start the backend
```bash
cd /var/www/deschide_news_app/apps/backend
symfony serve -d --port=8081
```

### No Test Files Found
**Error:** `No test files found in /var/www/deschide_news_app/k6`

**Solution:** Verify test files exist
```bash
ls -la /var/www/deschide_news_app/k6/*.js
ls -la /var/www/deschide_news_app/k6/scenarios/*.js
```

### Permission Denied
**Error:** `Permission denied: ./scripts/run-load-tests.sh`

**Solution:** Make script executable
```bash
chmod +x /var/www/deschide_news_app/scripts/run-load-tests.sh
```

## Performance Thresholds

The tests include predefined thresholds for pass/fail criteria:

| Metric | Threshold | Purpose |
|--------|-----------|---------|
| p95 Response Time | < 500ms | 95th percentile latency |
| p99 Response Time | < 1000ms | 99th percentile latency |
| Error Rate | < 10% | Maximum acceptable failures |

Failures are reported in the log output.

## Integration with CI/CD

### GitHub Actions Example
```yaml
name: Load Test

on:
  schedule:
    - cron: '0 2 * * *'  # Daily at 2 AM

jobs:
  load-test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Install k6
        run: |
          sudo gpg -k
          sudo gpg --no-default-keyring --keyring /usr/share/keyrings/k6-archive-keyring.gpg \
            --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D69
          echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" | \
            sudo tee /etc/apt/sources.list.d/k6.list
          sudo apt-get update && sudo apt-get install k6

      - name: Run Load Test
        env:
          BACKEND_URL: https://api.deschide.ro
        run: ./scripts/run-load-tests.sh api

      - name: Upload Results
        if: always()
        uses: actions/upload-artifact@v3
        with:
          name: load-test-results
          path: k6/results/
```

## Advanced Usage

### Custom Test Script
Create your own test in `k6/custom-test.js`:

```javascript
import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  vus: 50,
  duration: '5m',
};

export default function() {
  let res = http.get(__ENV.BACKEND_URL + '/api/articles');
  check(res, {
    'status is 200': (r) => r.status === 200,
  });
  sleep(1);
}
```

Then run:
```bash
k6 run /var/www/deschide_news_app/k6/custom-test.js
```

### View Results in k6 Cloud
Push results to k6 Cloud for visualization:

```bash
k6 cloud /var/www/deschide_news_app/k6/load-test.js
```

## Support

For issues or questions:
1. Check the help: `./scripts/run-load-tests.sh --help`
2. Review logs: `tail -f /var/www/deschide_news_app/k6/results/*.log`
3. Verify k6: `k6 version`
4. Check connectivity: `curl http://127.0.0.1:8081/api`

---

**Last Updated:** 2025-12-02  
**k6 Version:** 1.4.0  
**Deschide Backend:** Symfony 7.3  
**Deschide Frontend:** Next.js 16
