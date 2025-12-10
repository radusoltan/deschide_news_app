# Load Test Runner - Quick Reference

A comprehensive bash script for running k6 load tests against the Deschide News App with an intuitive menu-driven interface.

## Quick Start

```bash
cd /var/www/deschide_news_app

# Interactive menu
./scripts/run-load-tests.sh

# Or run a specific test directly
./scripts/run-load-tests.sh quick    # 2 min quick test
./scripts/run-load-tests.sh load     # 10 min standard load test
./scripts/run-load-tests.sh stress   # 17 min stress test
./scripts/run-load-tests.sh api      # 5 min API-only test
```

## Files Created

| File | Location | Purpose |
|------|----------|---------|
| **Main Script** | `/var/www/deschide_news_app/scripts/run-load-tests.sh` | Interactive load test runner |
| **Guide** | `/var/www/deschide_news_app/scripts/LOAD_TEST_RUNNER_GUIDE.md` | Comprehensive documentation |
| **New Test** | `/var/www/deschide_news_app/k6/scenarios/api-endpoints.js` | API isolation test suite |

## Features

- Interactive menu with 6 test types
- Pre-flight system validation (k6, backend, test files)
- Automatic result file generation with timestamps
- Colored terminal output
- Environment variable configuration
- Comprehensive help system
- Error detection and remediation
- JSON + log file outputs

## Test Types

1. **Load Test** (10 min) - Standard production load simulation
2. **Stress Test** (17 min) - Find breaking point
3. **Soak Test** (65 min) - Memory leak & stability detection
4. **Spike Test** (5 min) - Sudden traffic burst
5. **API-Only Test** (5 min) - API endpoint isolation
6. **Quick Test** (2 min) - Pre-deployment validation

## Results

All results saved to: `/var/www/deschide_news_app/k6/results/`

Each test generates:
- `{test-name}_{timestamp}.json` - Detailed metrics data
- `{test-name}_{timestamp}.log` - Human-readable output

## Documentation

For full documentation, see:
- `/var/www/deschide_news_app/scripts/LOAD_TEST_RUNNER_GUIDE.md`
- Run: `./scripts/run-load-tests.sh --help`

## Environment Configuration

```bash
# Default (development)
./scripts/run-load-tests.sh load

# Custom backend URL
BACKEND_URL=http://api.example.com ./scripts/run-load-tests.sh load

# Custom frontend URL
FRONTEND_URL=http://app.example.com ./scripts/run-load-tests.sh load

# Both custom
BACKEND_URL=http://api.prod.com FRONTEND_URL=http://app.prod.com ./scripts/run-load-tests.sh stress
```

## Troubleshooting

**k6 not installed?**
```bash
# Ubuntu/Debian
sudo apt-get update && sudo apt-get install k6

# macOS
brew install k6
```

**Backend not running?**
```bash
cd /var/www/deschide_news_app/apps/backend
symfony serve -d --port=8081
```

**Need help?**
```bash
./scripts/run-load-tests.sh --help
```

## Performance Targets

| Metric | Target |
|--------|--------|
| p95 Response Time | < 500ms |
| p99 Response Time | < 1000ms |
| Error Rate | < 1% |
| Throughput | 50+ req/s |

---

For detailed information, see: `/var/www/deschide_news_app/scripts/LOAD_TEST_RUNNER_GUIDE.md`
