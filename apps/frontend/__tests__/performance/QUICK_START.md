# Performance Tests - Quick Start Guide

## Running Tests

### Run All Performance Tests
```bash
cd /var/www/deschide_news_app/apps/frontend
pnpm test:performance
```

### Run Specific Test Suites
```bash
# Core Web Vitals only (LCP, FCP, TTFB, CLS)
pnpm test:performance:cwv

# Page load time only
pnpm test:performance:load

# Resource loading only (bundle sizes, images, requests)
pnpm test:performance:resources
```

### Run with UI Mode (Recommended for debugging)
```bash
pnpm exec playwright test __tests__/performance --project=chromium --ui
```

## Current Test Status

✅ **16/21 tests passing** (5 skipped due to empty content)

### Passing Tests
- ✅ Homepage performance (all 3 locales: ro, en, ru)
- ✅ Core Web Vitals (TTFB, FCP, LCP, CLS)
- ✅ Page load times
- ✅ Locale switching performance
- ✅ JavaScript bundle analysis (846KB detected)
- ✅ CSS bundle analysis (21.5KB - optimal)
- ✅ HTTP request count (29 requests)
- ✅ Render-blocking resources detection (27 resources)
- ✅ Font loading analysis (66KB)
- ✅ Third-party resources check (none found - good!)

### Skipped Tests
- ⚠️ Article page performance (no articles on homepage yet)
- ⚠️ Category page performance (no categories in navigation yet)
- ⚠️ Image loading performance (no images detected)
- ⚠️ Page-to-page navigation (requires content)

## Performance Metrics (Current)

| Page | TTFB | FCP | LCP | CLS | Load Time |
|------|------|-----|-----|-----|-----------|
| Homepage (ro) | 446ms | 2120ms | 2120ms | 0.000 | 2244ms |
| Homepage (en) | 1124ms | 1132ms | 1132ms | 0.000 | 1255ms |
| Homepage (ru) | 319ms | 440ms | 440ms | 0.000 | 585ms |

All metrics are within acceptable ranges for development environment!

## Key Findings

### ✅ Strengths
- Excellent CLS score (0.000 - perfect!)
- No third-party dependencies
- Small CSS bundle (21.5KB)
- Optimal font loading (66KB WOFF2)
- Low HTTP request count (29)

### ⚠️ Areas for Improvement
- JavaScript bundle size: 846KB (expected in dev mode)
- 27 render-blocking resources
- High load time variance (131.7%)
- DOM parse time: 3.8s

### 💡 Recommendations
1. Add `font-display: swap` for better font loading
2. Implement code splitting for large components
3. Defer non-critical JavaScript
4. Inline critical CSS
5. Monitor performance in production build

## Next Steps

1. **Add sample content** to enable article/category tests
2. **Run tests in production** with `pnpm build && pnpm start`
3. **Integrate with CI/CD** for continuous monitoring
4. **Set performance budgets** based on production metrics
5. **Monitor trends** over time

## Documentation

- 📖 **README.md** - Comprehensive testing guide
- 📊 **TEST_EXECUTION_REPORT.md** - Detailed test results
- 🚀 **QUICK_START.md** - This guide

## Troubleshooting

**Frontend not running?**
```bash
pnpm dev
```

**Tests timing out?**
```bash
# Increase timeout
pnpm exec playwright test __tests__/performance --timeout=120000
```

**Need to debug a test?**
```bash
pnpm exec playwright test __tests__/performance/core-web-vitals.spec.ts --debug
```

## Resources

- [Web Vitals](https://web.dev/vitals/)
- [Playwright Docs](https://playwright.dev/docs/intro)
- [Next.js Performance](https://nextjs.org/docs/advanced-features/measuring-performance)

---

**Last Updated**: December 2, 2025
