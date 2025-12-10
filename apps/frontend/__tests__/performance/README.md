# Performance Tests

Comprehensive performance testing suite for the Deschide News App frontend, measuring Core Web Vitals and resource loading metrics.

## Test Files

### 1. `core-web-vitals.spec.ts`
Tests Core Web Vitals metrics across all pages and locales.

**Metrics Tested:**
- **LCP** (Largest Contentful Paint) - Target: < 2500ms
- **FCP** (First Contentful Paint) - Target: < 1800ms
- **TTFB** (Time to First Byte) - Target: < 600ms
- **CLS** (Cumulative Layout Shift) - Target: < 0.1
- **Total Load Time** - Target: < 4000ms

**Pages Tested:**
- Homepage (ro, en, ru)
- Article pages
- Category pages

### 2. `page-load-time.spec.ts`
Measures page load performance and navigation speed.

**Tests:**
- DOMContentLoaded timing
- Full page load time
- Navigation performance (page-to-page)
- Locale switching performance
- Load time consistency

**Thresholds:**
- DOM Content Loaded: < 2000ms
- Load Complete: < 4000ms
- Navigation Time: < 3000ms

### 3. `resource-loading.spec.ts`
Analyzes resource loading and optimization opportunities.

**Analysis:**
- JavaScript bundle size (warning at 500KB)
- CSS bundle size (warning at 100KB)
- Image loading performance
- Total HTTP requests (max 50)
- Render-blocking resources (max 5)
- Font loading
- Third-party resources

## Running Tests

### Run All Performance Tests
```bash
pnpm test:performance
```

### Run Specific Test Suites
```bash
# Core Web Vitals only
pnpm test:performance:cwv

# Page load time only
pnpm test:performance:load

# Resource loading only
pnpm test:performance:resources
```

### Run with UI Mode (for debugging)
```bash
pnpm exec playwright test __tests__/performance --project=chromium --ui
```

### Run with Headed Browser (visual inspection)
```bash
pnpm exec playwright test __tests__/performance --project=chromium --headed
```

## Understanding Results

### Console Output Colors
- 🟢 **Green (✓)** - Metric passes threshold
- 🟡 **Yellow (⚠)** - Metric exceeds threshold but < 1.5x
- 🔴 **Red (✗)** - Metric significantly exceeds threshold

### Sample Output
```
=== Testing Homepage (Romanian) ===
✓ TTFB: 345.67ms (threshold: 600ms)
✓ FCP: 1234.56ms (threshold: 1800ms)
✓ LCP: 2100.45ms (threshold: 2500ms)
✓ CLS: 0.045 (threshold: 0.1)
✓ Total Load: 3456.78ms (threshold: 4000ms)
```

## Performance Thresholds

| Metric | Good | Needs Improvement | Poor |
|--------|------|-------------------|------|
| **LCP** | < 2.5s | 2.5s - 4s | > 4s |
| **FCP** | < 1.8s | 1.8s - 3s | > 3s |
| **TTFB** | < 600ms | 600ms - 800ms | > 800ms |
| **CLS** | < 0.1 | 0.1 - 0.25 | > 0.25 |
| **Total Load** | < 4s | 4s - 6s | > 6s |

## Common Performance Issues

### High TTFB (> 600ms)
**Causes:**
- Slow server response
- Database query performance
- No server-side caching

**Solutions:**
- Implement Redis caching
- Use CDN
- Optimize database queries
- Enable Varnish cache

### High LCP (> 2500ms)
**Causes:**
- Large hero images
- Unoptimized images
- Slow image loading

**Solutions:**
- Use WebP/AVIF format
- Implement responsive images (srcset)
- Optimize image sizes
- Use CDN for images
- Preload critical images

### High CLS (> 0.1)
**Causes:**
- Images without dimensions
- Dynamic content insertion
- Web fonts loading

**Solutions:**
- Add width/height to all images
- Reserve space for dynamic content
- Use font-display: swap
- Avoid injecting content above existing content

### Large JavaScript Bundle (> 500KB)
**Causes:**
- Large dependencies
- No code splitting
- Including unused code

**Solutions:**
- Use dynamic imports
- Implement route-based code splitting
- Remove unused dependencies
- Enable tree shaking
- Use bundle analyzer

### Many HTTP Requests (> 50)
**Causes:**
- Multiple small assets
- No asset bundling
- Too many third-party scripts

**Solutions:**
- Bundle CSS/JS files
- Use CSS sprites
- Enable HTTP/2
- Remove unnecessary third-party scripts
- Use CDN

## Monitoring in Production

### Lighthouse CI Integration
```bash
# Install Lighthouse CI
npm install -g @lhci/cli

# Run audit
lhci autorun --collect.url=http://localhost:3005
```

### Web Vitals Monitoring
The application uses `web-vitals` library to report metrics:

```typescript
import { getCLS, getFID, getFCP, getLCP, getTTFB } from 'web-vitals';

getCLS(console.log);
getFID(console.log);
getFCP(console.log);
getLCP(console.log);
getTTFB(console.log);
```

### Google PageSpeed Insights
- Test URL: https://pagespeed.web.dev/
- Provides real-world performance data
- Mobile and desktop metrics

## CI/CD Integration

### GitHub Actions Example
```yaml
- name: Run Performance Tests
  run: |
    pnpm install
    pnpm test:performance
  env:
    CI: true
```

### Performance Budget Enforcement
Set performance budgets in `playwright.config.ts`:

```typescript
// Example budget enforcement
const PERFORMANCE_BUDGETS = {
  LCP: 2500,
  FCP: 1800,
  TTFB: 600,
  CLS: 0.1,
};
```

## Best Practices

1. **Run tests regularly** - Include in CI/CD pipeline
2. **Test multiple devices** - Desktop, mobile, tablet
3. **Test multiple connections** - Slow 3G, Fast 3G, 4G
4. **Monitor trends** - Track performance over time
5. **Set realistic goals** - Based on content and features
6. **Fix regressions quickly** - Don't let performance degrade

## Debugging Performance Issues

### Using Chrome DevTools
1. Open DevTools (F12)
2. Go to Performance tab
3. Click Record
4. Interact with page
5. Stop recording
6. Analyze timeline

### Using Playwright Trace Viewer
```bash
pnpm exec playwright test __tests__/performance --trace on
pnpm exec playwright show-trace
```

### Using Lighthouse
```bash
# Install Lighthouse
npm install -g lighthouse

# Run audit
lighthouse http://localhost:3005 --view
```

## Resources

- [Web Vitals](https://web.dev/vitals/)
- [Playwright Performance Testing](https://playwright.dev/docs/api/class-performance)
- [Next.js Performance](https://nextjs.org/docs/advanced-features/measuring-performance)
- [Google PageSpeed Insights](https://pagespeed.web.dev/)
- [WebPageTest](https://www.webpagetest.org/)

## Troubleshooting

### Tests Failing Due to Timeout
Increase timeout in `playwright.config.ts`:
```typescript
timeout: 120 * 1000, // 120 seconds
```

### Frontend Not Running
Ensure frontend is running:
```bash
pnpm dev
```

### Inconsistent Results
Run tests multiple times and average results:
```bash
for i in {1..5}; do pnpm test:performance; done
```

### Network Issues
Set up network throttling:
```typescript
await page.route('**/*', route => route.continue());
```

## Contact

For questions or issues with performance tests, contact the frontend team or open an issue in the repository.
