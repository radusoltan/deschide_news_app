# Performance Tests - Index

## Directory Structure

```
__tests__/performance/
├── README.md                     # Comprehensive testing guide
├── QUICK_START.md               # Quick reference for running tests
├── TEST_EXECUTION_REPORT.md     # Detailed test execution results
├── INDEX.md                     # This file
├── core-web-vitals.spec.ts      # Core Web Vitals tests (6 tests)
├── page-load-time.spec.ts       # Page load performance tests (5 tests)
└── resource-loading.spec.ts     # Resource analysis tests (10 tests)
```

## Test Files

### 1. core-web-vitals.spec.ts
**Purpose**: Measure Core Web Vitals metrics  
**Tests**: 6  
**Metrics**: LCP, FCP, TTFB, CLS, Total Load Time  
**Pages**: Homepage (ro/en/ru), Article pages, Category pages

**Run command**:
```bash
pnpm test:performance:cwv
```

### 2. page-load-time.spec.ts
**Purpose**: Analyze page load performance and navigation speed  
**Tests**: 5  
**Metrics**: DOMContentLoaded, Load Complete, Navigation timing  
**Features**: Locale switching, Load consistency testing

**Run command**:
```bash
pnpm test:performance:load
```

### 3. resource-loading.spec.ts
**Purpose**: Monitor resource loading and optimization  
**Tests**: 10  
**Analysis**: JS bundles, CSS bundles, Images, HTTP requests, Render-blocking resources, Fonts, Third-party resources

**Run command**:
```bash
pnpm test:performance:resources
```

## Documentation Files

### README.md
- **Comprehensive testing guide**
- Detailed explanation of all tests
- Running instructions
- Understanding results
- Common performance issues
- Best practices
- Troubleshooting guide

### QUICK_START.md
- **Quick reference guide**
- Basic commands
- Current test status
- Key findings
- Performance metrics
- Next steps

### TEST_EXECUTION_REPORT.md
- **Detailed test execution report**
- Executive summary
- Test results by category
- Performance issues identified
- Recommendations (immediate, short-term, long-term)
- Production targets
- Test infrastructure details

## NPM Scripts

```json
{
  "test:performance": "Run all performance tests",
  "test:performance:cwv": "Run Core Web Vitals tests only",
  "test:performance:load": "Run page load time tests only",
  "test:performance:resources": "Run resource loading tests only"
}
```

## Test Statistics

- **Total Tests**: 21
- **Passing**: 16 ✅
- **Skipped**: 5 ⚠️
- **Failing**: 0 ❌
- **Success Rate**: 100% (of executable tests)

## Performance Thresholds

### Development Environment (Current)
| Metric | Threshold |
|--------|-----------|
| TTFB | < 1500ms |
| FCP | < 3500ms |
| LCP | < 3500ms |
| CLS | < 0.1 |
| Total Load | < 6000ms |
| JS Bundle | 500KB (warning) |
| Total Requests | < 50 |

### Production Environment (Target)
| Metric | Threshold |
|--------|-----------|
| TTFB | < 600ms |
| FCP | < 1800ms |
| LCP | < 2500ms |
| CLS | < 0.1 |
| Total Load | < 4000ms |
| JS Bundle | < 300KB |
| Total Requests | < 30 |

## Quick Access

- 🚀 **[Start Here: QUICK_START.md](QUICK_START.md)** - For quick testing
- 📖 **[Full Guide: README.md](README.md)** - For detailed information
- 📊 **[Test Results: TEST_EXECUTION_REPORT.md](TEST_EXECUTION_REPORT.md)** - For latest results

## Related Documentation

- **CLAUDE.md** (project root) - Project overview and commands
- **__tests__/e2e/** - E2E functional tests
- **__tests__/integration/** - Integration tests
- **__tests__/smoke/** - Smoke tests
- **__tests__/unit/** - Unit tests

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2025-12-02 | Initial implementation with 21 tests |

---

**Last Updated**: December 2, 2025  
**Maintained By**: Frontend Team  
**Framework**: Playwright 1.57.0
