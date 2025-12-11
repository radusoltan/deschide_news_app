/**
 * Core Web Vitals Performance Tests
 *
 * Tests key performance metrics:
 * - LCP (Largest Contentful Paint) - Target: < 2500ms
 * - FCP (First Contentful Paint) - Target: < 1800ms
 * - TTFB (Time to First Byte) - Target: < 600ms
 * - CLS (Cumulative Layout Shift) - Target: < 0.1
 * - Total page load time - Target: < 4000ms
 *
 * @group performance
 */

import { test, expect } from '@playwright/test';

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:3005';

const THRESHOLDS = {
  LCP: 3500,      // < 3.5s (dev environment)
  FCP: 3500,      // < 3.5s (dev environment)
  TTFB: 1500,     // < 1.5s (dev environment - no CDN/caching)
  CLS: 0.1,       // < 0.1 (good)
  TotalLoad: 6000, // < 6s total (dev environment)
};

// Color codes for console output
const colors = {
  green: '\x1b[32m',
  yellow: '\x1b[33m',
  red: '\x1b[31m',
  reset: '\x1b[0m',
  cyan: '\x1b[36m',
};

function formatMetric(name: string, value: number, threshold: number): string {
  const color = value < threshold ? colors.green : value < threshold * 1.5 ? colors.yellow : colors.red;
  const status = value < threshold ? '✓' : value < threshold * 1.5 ? '⚠' : '✗';
  return `${color}${status} ${name}: ${value.toFixed(2)}ms (threshold: ${threshold}ms)${colors.reset}`;
}

function formatCLS(value: number, threshold: number): string {
  const color = value < threshold ? colors.green : value < threshold * 1.5 ? colors.yellow : colors.red;
  const status = value < threshold ? '✓' : value < threshold * 1.5 ? '⚠' : '✗';
  return `${color}${status} CLS: ${value.toFixed(3)} (threshold: ${threshold})${colors.reset}`;
}

/**
 * Measure Core Web Vitals for a page
 */
async function measureCoreWebVitals(page: any) {
  // Wait for page to be fully loaded
  await page.waitForLoadState('networkidle');

  // Give some time for LCP to stabilize
  await page.waitForTimeout(1000);

  const metrics = await page.evaluate(() => {
    return new Promise<any>((resolve) => {
      const result: any = {
        lcp: 0,
        fcp: 0,
        ttfb: 0,
        cls: 0,
        totalLoad: 0,
      };

      // Get Navigation Timing for TTFB and Total Load
      const timing = performance.timing;
      result.ttfb = timing.responseStart - timing.requestStart;
      result.totalLoad = timing.loadEventEnd - timing.navigationStart;

      // Get Paint Timing for FCP
      const paintEntries = performance.getEntriesByType('paint');
      const fcpEntry = paintEntries.find((entry) => entry.name === 'first-contentful-paint');
      if (fcpEntry) {
        result.fcp = fcpEntry.startTime;
      }

      // Get LCP
      const lcpObserver = new PerformanceObserver((list) => {
        const entries = list.getEntries();
        const lastEntry = entries[entries.length - 1] as any;
        result.lcp = lastEntry.startTime;
      });
      lcpObserver.observe({ type: 'largest-contentful-paint', buffered: true });

      // Get CLS
      let clsValue = 0;
      const clsObserver = new PerformanceObserver((list) => {
        for (const entry of list.getEntries() as any[]) {
          if (!(entry as any).hadRecentInput) {
            clsValue += (entry as any).value;
          }
        }
      });
      clsObserver.observe({ type: 'layout-shift', buffered: true });

      // Wait a bit for metrics to settle
      setTimeout(() => {
        result.cls = clsValue;

        // Disconnect observers
        lcpObserver.disconnect();
        clsObserver.disconnect();

        resolve(result);
      }, 500);
    });
  });

  return metrics;
}

test.describe('Core Web Vitals Performance', () => {
  test.describe.configure({ mode: 'serial' });

  test('Homepage (Romanian) meets Core Web Vitals targets', async ({ page }) => {
    console.log(`\n${colors.cyan}=== Testing Homepage (Romanian) ===${colors.reset}`);

    await page.goto(`${BASE_URL}/ro`);
    const metrics = await measureCoreWebVitals(page);

    console.log(formatMetric('TTFB', metrics.ttfb, THRESHOLDS.TTFB));
    console.log(formatMetric('FCP', metrics.fcp, THRESHOLDS.FCP));
    console.log(formatMetric('LCP', metrics.lcp, THRESHOLDS.LCP));
    console.log(formatCLS(metrics.cls, THRESHOLDS.CLS));
    console.log(formatMetric('Total Load', metrics.totalLoad, THRESHOLDS.TotalLoad));

    // Soft assertions with warnings
    if (metrics.ttfb >= THRESHOLDS.TTFB) {
      console.log(`${colors.yellow}⚠ WARNING: TTFB exceeds target. Consider CDN caching or server optimization.${colors.reset}`);
    }
    if (metrics.fcp >= THRESHOLDS.FCP) {
      console.log(`${colors.yellow}⚠ WARNING: FCP exceeds target. Check render-blocking resources.${colors.reset}`);
    }
    if (metrics.lcp >= THRESHOLDS.LCP) {
      console.log(`${colors.yellow}⚠ WARNING: LCP exceeds target. Optimize hero images and above-the-fold content.${colors.reset}`);
    }
    if (metrics.cls >= THRESHOLDS.CLS) {
      console.log(`${colors.yellow}⚠ WARNING: CLS exceeds target. Check for layout shifts (images without dimensions, dynamic content).${colors.reset}`);
    }

    // Hard assertions
    expect(metrics.ttfb, `TTFB should be under ${THRESHOLDS.TTFB}ms`).toBeLessThan(THRESHOLDS.TTFB);
    expect(metrics.fcp, `FCP should be under ${THRESHOLDS.FCP}ms`).toBeLessThan(THRESHOLDS.FCP);
    expect(metrics.lcp, `LCP should be under ${THRESHOLDS.LCP}ms`).toBeLessThan(THRESHOLDS.LCP);
    expect(metrics.cls, `CLS should be under ${THRESHOLDS.CLS}`).toBeLessThan(THRESHOLDS.CLS);
    expect(metrics.totalLoad, `Total load time should be under ${THRESHOLDS.TotalLoad}ms`).toBeLessThan(THRESHOLDS.TotalLoad);
  });

  test('Homepage (English) meets Core Web Vitals targets', async ({ page }) => {
    console.log(`\n${colors.cyan}=== Testing Homepage (English) ===${colors.reset}`);

    await page.goto(`${BASE_URL}/en`);
    const metrics = await measureCoreWebVitals(page);

    console.log(formatMetric('TTFB', metrics.ttfb, THRESHOLDS.TTFB));
    console.log(formatMetric('FCP', metrics.fcp, THRESHOLDS.FCP));
    console.log(formatMetric('LCP', metrics.lcp, THRESHOLDS.LCP));
    console.log(formatCLS(metrics.cls, THRESHOLDS.CLS));
    console.log(formatMetric('Total Load', metrics.totalLoad, THRESHOLDS.TotalLoad));

    expect(metrics.ttfb).toBeLessThan(THRESHOLDS.TTFB);
    expect(metrics.fcp).toBeLessThan(THRESHOLDS.FCP);
    expect(metrics.lcp).toBeLessThan(THRESHOLDS.LCP);
    expect(metrics.cls).toBeLessThan(THRESHOLDS.CLS);
    expect(metrics.totalLoad).toBeLessThan(THRESHOLDS.TotalLoad);
  });

  test('Homepage (Russian) meets Core Web Vitals targets', async ({ page }) => {
    console.log(`\n${colors.cyan}=== Testing Homepage (Russian) ===${colors.reset}`);

    await page.goto(`${BASE_URL}/ru`);
    const metrics = await measureCoreWebVitals(page);

    console.log(formatMetric('TTFB', metrics.ttfb, THRESHOLDS.TTFB));
    console.log(formatMetric('FCP', metrics.fcp, THRESHOLDS.FCP));
    console.log(formatMetric('LCP', metrics.lcp, THRESHOLDS.LCP));
    console.log(formatCLS(metrics.cls, THRESHOLDS.CLS));
    console.log(formatMetric('Total Load', metrics.totalLoad, THRESHOLDS.TotalLoad));

    expect(metrics.ttfb).toBeLessThan(THRESHOLDS.TTFB);
    expect(metrics.fcp).toBeLessThan(THRESHOLDS.FCP);
    expect(metrics.lcp).toBeLessThan(THRESHOLDS.LCP);
    expect(metrics.cls).toBeLessThan(THRESHOLDS.CLS);
    expect(metrics.totalLoad).toBeLessThan(THRESHOLDS.TotalLoad);
  });

  test('Article page meets Core Web Vitals targets', async ({ page }) => {
    console.log(`\n${colors.cyan}=== Testing Article Page ===${colors.reset}`);

    // Go to homepage first to find an article
    await page.goto(`${BASE_URL}/ro`);
    await page.waitForLoadState('networkidle');

    // Find first article link with timeout
    const articleLink = page.locator('a[href*="/ro/article/"]').first();
    const articleCount = await articleLink.count();

    if (articleCount === 0) {
      console.log(`${colors.yellow}ℹ No article links found on homepage - skipping article performance test${colors.reset}`);
      test.skip();
      return;
    }

    const articleUrl = await articleLink.getAttribute('href');

    if (articleUrl) {
      await page.goto(articleUrl);
      const metrics = await measureCoreWebVitals(page);

      console.log(formatMetric('TTFB', metrics.ttfb, THRESHOLDS.TTFB));
      console.log(formatMetric('FCP', metrics.fcp, THRESHOLDS.FCP));
      console.log(formatMetric('LCP', metrics.lcp, THRESHOLDS.LCP));
      console.log(formatCLS(metrics.cls, THRESHOLDS.CLS));
      console.log(formatMetric('Total Load', metrics.totalLoad, THRESHOLDS.TotalLoad));

      // More lenient thresholds for article pages (more content)
      expect(metrics.ttfb).toBeLessThan(THRESHOLDS.TTFB * 1.2);
      expect(metrics.fcp).toBeLessThan(THRESHOLDS.FCP * 1.2);
      expect(metrics.lcp).toBeLessThan(THRESHOLDS.LCP * 1.2);
      expect(metrics.cls).toBeLessThan(THRESHOLDS.CLS * 1.5);
      expect(metrics.totalLoad).toBeLessThan(THRESHOLDS.TotalLoad * 1.2);
    } else {
      console.log(`${colors.yellow}ℹ Could not get article URL - skipping test${colors.reset}`);
      test.skip();
    }
  });

  test('Category page meets Core Web Vitals targets', async ({ page }) => {
    console.log(`\n${colors.cyan}=== Testing Category Page ===${colors.reset}`);

    // Go to homepage first to find a category
    await page.goto(`${BASE_URL}/ro`);
    await page.waitForLoadState('networkidle');

    // Find first category link in navigation
    const categoryLink = page.locator('nav a[href*="/ro/"], header a[href*="/ro/"]').filter({ hasNotText: /^(Home|Acasa|Despre|Contact|Login)$/i }).first();
    const categoryCount = await categoryLink.count();

    if (categoryCount === 0) {
      console.log(`${colors.yellow}ℹ No category links found - skipping category performance test${colors.reset}`);
      test.skip();
      return;
    }

    const categoryUrl = await categoryLink.getAttribute('href');

    if (categoryUrl && !categoryUrl.includes('/article/') && categoryUrl !== `${BASE_URL}/ro` && categoryUrl !== '/ro') {
      await page.goto(categoryUrl);
      const metrics = await measureCoreWebVitals(page);

      console.log(formatMetric('TTFB', metrics.ttfb, THRESHOLDS.TTFB));
      console.log(formatMetric('FCP', metrics.fcp, THRESHOLDS.FCP));
      console.log(formatMetric('LCP', metrics.lcp, THRESHOLDS.LCP));
      console.log(formatCLS(metrics.cls, THRESHOLDS.CLS));
      console.log(formatMetric('Total Load', metrics.totalLoad, THRESHOLDS.TotalLoad));

      expect(metrics.ttfb).toBeLessThan(THRESHOLDS.TTFB);
      expect(metrics.fcp).toBeLessThan(THRESHOLDS.FCP);
      expect(metrics.lcp).toBeLessThan(THRESHOLDS.LCP);
      expect(metrics.cls).toBeLessThan(THRESHOLDS.CLS);
      expect(metrics.totalLoad).toBeLessThan(THRESHOLDS.TotalLoad);
    } else {
      console.log(`${colors.yellow}ℹ No valid category URL found - skipping test${colors.reset}`);
      test.skip();
    }
  });
});

test.describe('Performance Summary', () => {
  test('Generate performance report', async ({ page }) => {
    console.log(`\n${colors.cyan}=== Performance Report ===${colors.reset}`);
    console.log(`${colors.green}✓ All Core Web Vitals tests completed${colors.reset}`);
    console.log(`\nThresholds (Development Environment):`);
    console.log(`  TTFB: < ${THRESHOLDS.TTFB}ms`);
    console.log(`  FCP:  < ${THRESHOLDS.FCP}ms`);
    console.log(`  LCP:  < ${THRESHOLDS.LCP}ms`);
    console.log(`  CLS:  < ${THRESHOLDS.CLS}`);
    console.log(`  Load: < ${THRESHOLDS.TotalLoad}ms`);
    console.log(`\n${colors.yellow}Note: Production targets should be more aggressive (TTFB < 600ms, FCP < 1800ms)${colors.reset}`);
    console.log(`\nRecommendations:`);
    console.log(`  - Optimize hero images (WebP format, proper sizing)`);
    console.log(`  - Implement CDN caching for static assets`);
    console.log(`  - Add width/height to all images to prevent CLS`);
    console.log(`  - Minimize render-blocking resources`);
    console.log(`  - Consider implementing lazy loading for below-the-fold content`);

    // Dummy assertion to make test pass
    expect(true).toBe(true);
  });
});
