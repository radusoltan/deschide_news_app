# Performance Testing Guide

## Quick Start: Verify Homepage Optimization

### Prerequisites
```bash
cd /var/www/deschide_news_app/apps/frontend
pnpm install
```

### 1. Build the Application
```bash
# Clean build to see optimized bundle sizes
rm -rf .next
pnpm build
```

**Expected Output:**
```
Route (app)                              Size     First Load JS
┌ ○ /                                    ???      ??? kB
├ ○ /[locale]                            ???      ??? kB
└ ○ /[locale]/(public)                   ???      ??? kB (target: < 350 kB)
```

### 2. Start Production Server
```bash
pnpm start
# Server runs on http://localhost:3005
```

### 3. Run Lighthouse Audit

#### Using Chrome DevTools
1. Open Chrome/Edge browser
2. Navigate to `http://localhost:3005/ro`
3. Open DevTools (F12)
4. Go to "Lighthouse" tab
5. Select:
   - ✅ Performance
   - ✅ Desktop
   - ✅ Clear storage
6. Click "Analyze page load"

#### Using CLI
```bash
# Install Lighthouse globally (if not installed)
npm install -g lighthouse

# Run audit for Romanian homepage
lighthouse http://localhost:3005/ro \
  --output=html \
  --output-path=./lighthouse-report-ro.html \
  --view

# Run audit for English homepage
lighthouse http://localhost:3005/en \
  --output=html \
  --output-path=./lighthouse-report-en.html \
  --view
```

### 4. Run Playwright Performance Tests
```bash
# Core Web Vitals test
pnpm test:performance:cwv

# Page load time test
pnpm test:performance:load

# Resource loading test
pnpm test:performance:resources

# All performance tests
pnpm test:performance
```

---

## Performance Metrics Targets

### Core Web Vitals
| Metric | Target | Good | Acceptable | Poor |
|--------|--------|------|------------|------|
| **LCP** (Largest Contentful Paint) | < 2.5s | < 2.5s | 2.5s - 4s | > 4s |
| **INP** (Interaction to Next Paint) | < 200ms | < 200ms | 200ms - 500ms | > 500ms |
| **CLS** (Cumulative Layout Shift) | < 0.1 | < 0.1 | 0.1 - 0.25 | > 0.25 |

### Additional Metrics
| Metric | Target | Description |
|--------|--------|-------------|
| **FCP** (First Contentful Paint) | < 1.8s | When first content appears |
| **TTI** (Time to Interactive) | < 3.8s | When page is fully interactive |
| **TBT** (Total Blocking Time) | < 200ms | Sum of blocking time |
| **Speed Index** | < 3.4s | How quickly content is visually displayed |

### Bundle Size Targets
| Bundle | Target | Current Goal |
|--------|--------|--------------|
| **Initial JS** | < 350 kB | ~305 kB |
| **Initial CSS** | < 50 kB | ~40 kB |
| **Total First Load** | < 400 kB | ~345 kB |

---

## Troubleshooting

### Build Fails
```bash
# Error: Type error in routes.d.ts
rm -rf .next
pnpm build
```

### Images Not Optimizing
```bash
# Check next.config.mjs
# Ensure: unoptimized: false

# Restart dev server
pnpm dev
```

### ISR Not Working
```bash
# Check page.tsx has:
export const revalidate = 60;

# Verify in browser:
# - First load: slow (cache miss)
# - Subsequent loads within 60s: fast (cache hit)
```

### Dynamic Imports Not Working
```bash
# Check browser console for errors
# Verify network tab shows separate chunk files:
# - NewsSlider chunk: ~80KB
# - TrendingArticles chunk: ~15KB
```

---

## Bundle Analysis

### Generate Bundle Report
```bash
# Set ANALYZE environment variable
ANALYZE=true pnpm build

# Opens browser with interactive bundle visualization
# Look for:
# - Largest packages
# - Duplicate dependencies
# - Unused code
```

### Manual Bundle Inspection
```bash
# After build, check .next/static/chunks/
ls -lh .next/static/chunks/

# Find largest chunks
du -sh .next/static/chunks/* | sort -h
```

---

## Real-World Performance Testing

### Using k6 Load Testing
```bash
cd /var/www/deschide_news_app/k6

# Light load test
k6 run scripts/homepage-light.js

# Expected results:
# - p95 < 2,000ms
# - p50 < 1,000ms
# - Success rate > 99%
```

### Using curl for Quick Tests
```bash
# Measure server response time (ISR cache)
time curl -s http://localhost:3005/ro > /dev/null

# Expected: ~0.01s (with cache)
```

### Chrome DevTools Performance Recording
1. Open DevTools (F12)
2. Go to "Performance" tab
3. Click Record (Ctrl+E)
4. Reload page (Ctrl+R)
5. Stop recording
6. Analyze:
   - Look for long tasks (> 50ms)
   - Check for layout shifts
   - Verify images load progressively

---

## Verification Checklist

### Before Testing
- [ ] Backend running on http://127.0.0.1:8081
- [ ] CDN server running on http://127.0.0.1:8082
- [ ] Frontend built with `pnpm build`
- [ ] Frontend running with `pnpm start`
- [ ] Database has sample articles with images

### During Testing
- [ ] Clear browser cache before each test
- [ ] Test on both desktop and mobile viewports
- [ ] Test all locales (ro, en, ru)
- [ ] Check Network tab for waterfall
- [ ] Verify WebP/AVIF images are served
- [ ] Confirm lazy loading works (scroll to see images load)

### After Testing
- [ ] p95 load time < 2,000ms ✅
- [ ] LCP < 2.5s ✅
- [ ] INP < 200ms ✅
- [ ] CLS < 0.1 ✅
- [ ] Initial JS bundle < 350 kB ✅
- [ ] Lighthouse score > 90 ✅

---

## Expected Results Summary

### Before Optimization
- p95 Load Time: 7,918ms
- Initial Bundle: ~400KB
- LCP: ~4.0s
- Lighthouse Score: ~60

### After Optimization
- p95 Load Time: < 2,000ms (76% improvement)
- Initial Bundle: ~305KB (24% reduction)
- LCP: < 1.0s (75% improvement)
- Lighthouse Score: > 90 (50% improvement)

---

## Performance Monitoring Dashboard

### Key URLs to Monitor
```bash
# Homepage (Romanian)
http://localhost:3005/ro

# Homepage (English)
http://localhost:3005/en

# Homepage (Russian)
http://localhost:3005/ru

# Article page (sample)
http://localhost:3005/ro/politica/article-slug
```

### Monitoring Commands
```bash
# Continuous monitoring with k6
k6 run --duration 5m scripts/homepage-monitoring.js

# Watch bundle size changes
watch -n 60 'du -sh .next/static/chunks'
```

---

## Report Generation

### Generate Performance Report
```bash
# Run all tests and generate report
npm run test:performance > performance-report.txt

# View report
cat performance-report.txt
```

### Export Lighthouse Report
```bash
# JSON format for CI/CD
lighthouse http://localhost:3005/ro \
  --output=json \
  --output-path=./lighthouse-report.json

# CSV format for spreadsheets
lighthouse http://localhost:3005/ro \
  --output=csv \
  --output-path=./lighthouse-report.csv
```

---

## Next Steps

1. **Run Initial Tests**: Execute all verification steps above
2. **Document Results**: Record actual metrics achieved
3. **Compare to Baseline**: Verify 75% improvement in p95 load time
4. **Deploy to Staging**: Test in staging environment
5. **Monitor Production**: Set up continuous monitoring

For issues or questions, refer to:
- Main optimization report: `docs/HOMEPAGE_PERFORMANCE_OPTIMIZATION.md`
- Design system guide: `../../context/DESIGN_QUICK_REFERENCE.md`
- Project documentation: `../../CLAUDE.md`
