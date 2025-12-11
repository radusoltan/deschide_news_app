# k6 Load Testing - Index

**Project:** Deschide News App  
**Date Created:** 2024-12-02  
**Status:** Production Ready

## Quick Navigation

### Getting Started (5 minutes)
1. Read [QUICK_START.md](QUICK_START.md) - Minimal setup
2. Run: `k6 run load-test.js`
3. View results

### Full Documentation (20 minutes)
- [README.md](README.md) - Complete guide with installation, usage, CI/CD
- [TEST_SUITE_OVERVIEW.md](TEST_SUITE_OVERVIEW.md) - Test details and selection

### Reference Materials (As Needed)
- [COMMANDS_REFERENCE.md](COMMANDS_REFERENCE.md) - All commands and options
- [VERIFICATION_REPORT.md](VERIFICATION_REPORT.md) - Implementation verification
- [IMPLEMENTATION_SUMMARY.md](IMPLEMENTATION_SUMMARY.md) - Technical details

## File Structure

```
k6/
├── Test Scripts (8 files)
│   ├── config.js                    # Shared configuration
│   ├── load-test.js                 # Standard load test (10 min)
│   ├── stress-test.js               # Stress test (14 min)
│   ├── soak-test.js                 # Endurance test (70 min)
│   └── scenarios/
│       ├── api-endpoints.js         # API-only test (5 min)
│       ├── homepage.js              # Homepage test (5 min)
│       ├── user-journey.js          # Full journey (10 min)
│       └── spike-test.js            # Spike test (8 min)
│
├── Documentation (7 files)
│   ├── INDEX.md                     # This file
│   ├── QUICK_START.md               # Fast setup guide
│   ├── README.md                    # Comprehensive guide
│   ├── TEST_SUITE_OVERVIEW.md       # Test suite details
│   ├── COMMANDS_REFERENCE.md        # Command reference
│   ├── IMPLEMENTATION_SUMMARY.md    # Technical summary
│   └── VERIFICATION_REPORT.md       # Verification details
│
└── results/                         # Test results directory
```

## Test Scripts Quick Reference

| Script | Duration | VUs | Purpose | Command |
|--------|----------|-----|---------|---------|
| **load-test.js** | 10 min | 10-100 | Standard load testing | `k6 run load-test.js` |
| **stress-test.js** | 14 min | 100-500 | Find breaking point | `k6 run stress-test.js` |
| **soak-test.js** | 70 min | 50 | Memory leak detection | `k6 run soak-test.js` |
| **api-endpoints.js** | 5 min | 50 | API-only testing | `k6 run scenarios/api-endpoints.js` |
| **homepage.js** | 5 min | 30 | Frontend homepage | `k6 run scenarios/homepage.js` |
| **user-journey.js** | 10 min | 20 | Full user flow | `k6 run scenarios/user-journey.js` |
| **spike-test.js** | 8 min | 50-500 | Sudden load spikes | `k6 run scenarios/spike-test.js` |

## Documentation Quick Reference

| Document | When to Read | Time |
|----------|--------------|------|
| **QUICK_START.md** | First time setup | 5 min |
| **README.md** | Full understanding | 20 min |
| **TEST_SUITE_OVERVIEW.md** | Test selection | 10 min |
| **COMMANDS_REFERENCE.md** | Looking up commands | As needed |
| **IMPLEMENTATION_SUMMARY.md** | Technical details | 15 min |
| **VERIFICATION_REPORT.md** | Verification proof | 10 min |

## Common Use Cases

### 1. Quick Smoke Test (30 seconds)
```bash
k6 run --vus 1 --duration 30s load-test.js
```

### 2. Standard Load Test (10 minutes)
```bash
k6 run load-test.js
```

### 3. Find Breaking Point (14 minutes)
```bash
k6 run stress-test.js
```

### 4. Test API Only (5 minutes)
```bash
k6 run scenarios/api-endpoints.js
```

### 5. Full User Journey (10 minutes)
```bash
k6 run scenarios/user-journey.js
```

### 6. Save Results to File
```bash
k6 run --out json=results/$(date +%Y%m%d-%H%M%S).json load-test.js
```

### 7. Custom Backend URL
```bash
k6 run -e BACKEND_URL=http://api.example.com load-test.js
```

## Performance Targets

### Backend API (Symfony)
- Articles: **p(95) < 300ms**
- Categories: **p(95) < 150ms**
- Single article: **p(95) < 200ms**

### Frontend (Next.js)
- Homepage: **p(95) < 2000ms**
- Category pages: **p(95) < 1500ms**
- Article pages: **p(95) < 1500ms**

### System-wide
- Error rate: **< 5%** (normal load)
- Error rate: **< 15%** (stress test)
- No degradation over 1 hour

## Workflow Recommendations

### Daily Development
```bash
# Quick smoke test before commit
k6 run --vus 10 --duration 1m load-test.js
```

### Before Deploy
```bash
# Full load test
k6 run load-test.js
```

### Weekly QA
```bash
# Stress test
k6 run stress-test.js
```

### Monthly Performance Audit
```bash
# Soak test
k6 run soak-test.js
```

### Before Major Release
```bash
# All tests
k6 run load-test.js
k6 run stress-test.js
k6 run scenarios/spike-test.js
```

## Environment Variables

```bash
# Override backend URL
export BACKEND_URL=http://api.deschide.local

# Override frontend URL
export FRONTEND_URL=http://deschide.local

# Or inline
k6 run -e BACKEND_URL=http://127.0.0.1:8081 load-test.js
```

## CI/CD Integration

### GitHub Actions
See [README.md#cicd-integration](README.md#cicd-integration) for full example.

### GitLab CI
See [README.md#cicd-integration](README.md#cicd-integration) for full example.

## Support & Resources

### Internal Documentation
- Main README: [README.md](README.md)
- Project docs: `/var/www/deschide_news_app/CLAUDE.md`
- Backend docs: `/var/www/deschide_news_app/apps/backend/README.md`
- Frontend docs: `/var/www/deschide_news_app/apps/frontend/README.md`

### External Resources
- k6 Official Docs: https://k6.io/docs/
- k6 Examples: https://k6.io/docs/examples/
- k6 Cloud: https://app.k6.io/

### Quick Help
```bash
# k6 help
k6 --help
k6 run --help

# Validate test
k6 inspect load-test.js

# Check k6 version
k6 version
```

## Version History

- **v1.0.0** (2024-12-02): Initial release
  - Load test implementation
  - Stress test implementation
  - Soak test implementation
  - 4 scenario tests
  - Complete documentation

## License

Part of Deschide News App project.

## Contributors

- Claude Code (Implementation)
- Development Team (Requirements & Review)

---

**Need help?** Start with [QUICK_START.md](QUICK_START.md) for immediate usage, or read [README.md](README.md) for comprehensive guidance.
