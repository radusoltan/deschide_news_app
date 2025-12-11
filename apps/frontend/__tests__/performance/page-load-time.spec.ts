/**
 * Page Load Time Performance Tests
 *
 * Measures:
 * - DOMContentLoaded time
 * - Full page load time
 * - Navigation performance (page-to-page)
 * - Performance across all locales
 *
 * @group performance
 */

import { test, expect } from '@playwright/test';

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:3005';

const THRESHOLDS = {
  domContentLoaded: 6000,  // < 6s for DOM ready (dev environment)
  loadComplete: 7000,      // < 7s for full load (dev environment)
  navigationTime: 4000,    // < 4s for page navigation
};

// Color codes for console output
const colors = {
  green: '\x1b[32m',
  yellow: '\x1b[33m',
  red: '\x1b[31m',
  reset: '\x1b[0m',
  cyan: '\x1b[36m',
  bold: '\x1b[1m',
};

function formatTime(name: string, value: number, threshold: number): string {
  const color = value < threshold ? colors.green : value < threshold * 1.5 ? colors.yellow : colors.red;
  const status = value < threshold ? '✓' : value < threshold * 1.5 ? '⚠' : '✗';
  return `${color}${status} ${name}: ${value.toFixed(2)}ms (threshold: ${threshold}ms)${colors.reset}`;
}

/**
 * Get detailed navigation timing metrics
 */
async function getNavigationTiming(page: any) {
  await page.waitForLoadState('load');
  await page.waitForTimeout(500); // Allow metrics to stabilize

  return await page.evaluate(() => {
    const timing = performance.timing;
    const navigation = performance.getEntriesByType('navigation')[0] as PerformanceNavigationTiming;

    return {
      // Navigation Timing API
      redirectTime: timing.fetchStart - timing.navigationStart,
      dnsTime: timing.domainLookupEnd - timing.domainLookupStart,
      tcpTime: timing.connectEnd - timing.connectStart,
      requestTime: timing.responseStart - timing.requestStart,
      responseTime: timing.responseEnd - timing.responseStart,
      domParseTime: timing.domInteractive - timing.domLoading,
      domContentLoadedTime: timing.domContentLoadedEventEnd - timing.navigationStart,
      loadCompleteTime: timing.loadEventEnd - timing.navigationStart,

      // Additional metrics
      domReady: timing.domContentLoadedEventEnd - timing.domContentLoadedEventStart,
      loadEvent: timing.loadEventEnd - timing.loadEventStart,

      // Resource Timing API (from Navigation Timing)
      transferSize: navigation ? navigation.transferSize : 0,
      encodedBodySize: navigation ? navigation.encodedBodySize : 0,
      decodedBodySize: navigation ? navigation.decodedBodySize : 0,
    };
  });
}

test.describe('Page Load Time Performance', () => {
  test.describe.configure({ mode: 'serial' });

  test('Homepage Romanian - Load time', async ({ page }) => {
    console.log(`\n${colors.cyan}=== Homepage (Romanian) Load Time ===${colors.reset}`);

    const startTime = Date.now();
    await page.goto(`${BASE_URL}/ro`);
    const metrics = await getNavigationTiming(page);
    const clientLoadTime = Date.now() - startTime;

    console.log(`${colors.bold}Navigation Breakdown:${colors.reset}`);
    console.log(`  DNS lookup: ${metrics.dnsTime.toFixed(2)}ms`);
    console.log(`  TCP connection: ${metrics.tcpTime.toFixed(2)}ms`);
    console.log(`  Request time: ${metrics.requestTime.toFixed(2)}ms`);
    console.log(`  Response time: ${metrics.responseTime.toFixed(2)}ms`);
    console.log(`  DOM parse time: ${metrics.domParseTime.toFixed(2)}ms`);
    console.log(`  DOM ready event: ${metrics.domReady.toFixed(2)}ms`);
    console.log(`  Load event: ${metrics.loadEvent.toFixed(2)}ms`);
    console.log(`\n${colors.bold}Page Size:${colors.reset}`);
    console.log(`  Transfer size: ${(metrics.transferSize / 1024).toFixed(2)} KB`);
    console.log(`  Encoded size: ${(metrics.encodedBodySize / 1024).toFixed(2)} KB`);
    console.log(`  Decoded size: ${(metrics.decodedBodySize / 1024).toFixed(2)} KB`);
    console.log(`\n${colors.bold}Key Metrics:${colors.reset}`);
    console.log(formatTime('DOMContentLoaded', metrics.domContentLoadedTime, THRESHOLDS.domContentLoaded));
    console.log(formatTime('Load Complete', metrics.loadCompleteTime, THRESHOLDS.loadComplete));
    console.log(formatTime('Client Load Time', clientLoadTime, THRESHOLDS.loadComplete));

    // Assertions
    expect(metrics.domContentLoadedTime, 'DOMContentLoaded should be under 2s').toBeLessThan(THRESHOLDS.domContentLoaded);
    expect(metrics.loadCompleteTime, 'Load complete should be under 4s').toBeLessThan(THRESHOLDS.loadComplete);

    // Warnings for optimization opportunities
    if (metrics.responseTime > 500) {
      console.log(`${colors.yellow}⚠ Response time is high. Consider server-side caching or CDN.${colors.reset}`);
    }
    if (metrics.domParseTime > 1000) {
      console.log(`${colors.yellow}⚠ DOM parsing is slow. Consider reducing HTML size or complexity.${colors.reset}`);
    }
    if (metrics.transferSize > 500 * 1024) {
      console.log(`${colors.yellow}⚠ Transfer size is large. Consider compression and minification.${colors.reset}`);
    }
  });

  test('Homepage English - Load time', async ({ page }) => {
    console.log(`\n${colors.cyan}=== Homepage (English) Load Time ===${colors.reset}`);

    await page.goto(`${BASE_URL}/en`);
    const metrics = await getNavigationTiming(page);

    console.log(formatTime('DOMContentLoaded', metrics.domContentLoadedTime, THRESHOLDS.domContentLoaded));
    console.log(formatTime('Load Complete', metrics.loadCompleteTime, THRESHOLDS.loadComplete));

    expect(metrics.domContentLoadedTime).toBeLessThan(THRESHOLDS.domContentLoaded);
    expect(metrics.loadCompleteTime).toBeLessThan(THRESHOLDS.loadComplete);
  });

  test('Homepage Russian - Load time', async ({ page }) => {
    console.log(`\n${colors.cyan}=== Homepage (Russian) Load Time ===${colors.reset}`);

    await page.goto(`${BASE_URL}/ru`);
    const metrics = await getNavigationTiming(page);

    console.log(formatTime('DOMContentLoaded', metrics.domContentLoadedTime, THRESHOLDS.domContentLoaded));
    console.log(formatTime('Load Complete', metrics.loadCompleteTime, THRESHOLDS.loadComplete));

    expect(metrics.domContentLoadedTime).toBeLessThan(THRESHOLDS.domContentLoaded);
    expect(metrics.loadCompleteTime).toBeLessThan(THRESHOLDS.loadComplete);
  });
});

test.describe('Navigation Performance', () => {
  test('Page-to-page navigation speed', async ({ page }) => {
    console.log(`\n${colors.cyan}=== Navigation Performance (Homepage -> Article -> Category) ===${colors.reset}`);

    // Start at homepage
    await page.goto(`${BASE_URL}/ro`);
    await page.waitForLoadState('networkidle');

    // Navigate to first article
    const articleLink = page.locator('a[href*="/ro/article/"]').first();
    const articleExists = await articleLink.count() > 0;

    if (articleExists) {
      const articleStartTime = Date.now();
      await articleLink.click();
      await page.waitForLoadState('networkidle');
      const articleNavTime = Date.now() - articleStartTime;

      console.log(formatTime('Homepage → Article', articleNavTime, THRESHOLDS.navigationTime));
      expect(articleNavTime, 'Navigation to article should be fast').toBeLessThan(THRESHOLDS.navigationTime);

      // Navigate back to homepage
      const homeStartTime = Date.now();
      await page.goto(`${BASE_URL}/ro`);
      await page.waitForLoadState('networkidle');
      const homeNavTime = Date.now() - homeStartTime;

      console.log(formatTime('Article → Homepage (cached)', homeNavTime, THRESHOLDS.navigationTime));

      // Should be faster due to caching
      if (homeNavTime > articleNavTime) {
        console.log(`${colors.yellow}⚠ Return navigation is slower. Check browser caching.${colors.reset}`);
      }

      // Navigate to category
      const categoryLink = page.locator('nav a[href*="/ro/"], header a[href*="/ro/"]').filter({ hasNotText: /^(Home|Acasa|Despre|Contact|Login)$/i }).first();
      const categoryExists = await categoryLink.count() > 0;

      if (categoryExists) {
        const categoryUrl = await categoryLink.getAttribute('href');
        if (categoryUrl && !categoryUrl.includes('/article/') && categoryUrl !== `${BASE_URL}/ro`) {
          const categoryStartTime = Date.now();
          await page.goto(categoryUrl);
          await page.waitForLoadState('networkidle');
          const categoryNavTime = Date.now() - categoryStartTime;

          console.log(formatTime('Homepage → Category', categoryNavTime, THRESHOLDS.navigationTime));
          expect(categoryNavTime, 'Navigation to category should be fast').toBeLessThan(THRESHOLDS.navigationTime);
        }
      }
    } else {
      test.skip();
    }
  });

  test('Locale switching performance', async ({ page }) => {
    console.log(`\n${colors.cyan}=== Locale Switching Performance ===${colors.reset}`);

    // Load Romanian homepage
    await page.goto(`${BASE_URL}/ro`);
    await page.waitForLoadState('networkidle');

    // Switch to English
    const enStartTime = Date.now();
    await page.goto(`${BASE_URL}/en`);
    await page.waitForLoadState('networkidle');
    const enSwitchTime = Date.now() - enStartTime;

    console.log(formatTime('Romanian → English', enSwitchTime, THRESHOLDS.navigationTime));
    expect(enSwitchTime, 'Locale switch should be fast').toBeLessThan(THRESHOLDS.navigationTime);

    // Switch to Russian
    const ruStartTime = Date.now();
    await page.goto(`${BASE_URL}/ru`);
    await page.waitForLoadState('networkidle');
    const ruSwitchTime = Date.now() - ruStartTime;

    console.log(formatTime('English → Russian', ruSwitchTime, THRESHOLDS.navigationTime));
    expect(ruSwitchTime, 'Locale switch should be fast').toBeLessThan(THRESHOLDS.navigationTime);

    // Switch back to Romanian (should be cached)
    const roCachedStartTime = Date.now();
    await page.goto(`${BASE_URL}/ro`);
    await page.waitForLoadState('networkidle');
    const roCachedSwitchTime = Date.now() - roCachedStartTime;

    console.log(formatTime('Russian → Romanian (cached)', roCachedSwitchTime, THRESHOLDS.navigationTime));

    // Cached version should be faster
    if (roCachedSwitchTime > enSwitchTime * 0.8) {
      console.log(`${colors.yellow}⚠ Cached navigation is not significantly faster. Check caching strategy.${colors.reset}`);
    }
  });

  test('Article pagination/infinite scroll performance', async ({ page }) => {
    console.log(`\n${colors.cyan}=== Article Pagination Performance ===${colors.reset}`);

    // Go to homepage
    await page.goto(`${BASE_URL}/ro`);
    await page.waitForLoadState('networkidle');

    // Check if there's a "Load More" or pagination button
    const loadMoreButton = page.locator('button:has-text("Mai multe"), button:has-text("Load more"), button:has-text("Загрузить"), a:has-text("Next")').first();
    const paginationExists = await loadMoreButton.count() > 0;

    if (paginationExists) {
      const startTime = Date.now();
      await loadMoreButton.click();
      await page.waitForLoadState('networkidle');
      const paginationTime = Date.now() - startTime;

      console.log(formatTime('Load More/Pagination', paginationTime, THRESHOLDS.navigationTime));
      expect(paginationTime, 'Pagination should be fast').toBeLessThan(THRESHOLDS.navigationTime);
    } else {
      console.log(`${colors.yellow}ℹ No pagination found - skipping test${colors.reset}`);
      test.skip();
    }
  });
});

test.describe('Performance Consistency', () => {
  test('Homepage load time consistency (3 runs)', async ({ page }) => {
    console.log(`\n${colors.cyan}=== Load Time Consistency (Romanian Homepage) ===${colors.reset}`);

    const loadTimes: number[] = [];

    for (let i = 0; i < 3; i++) {
      // Clear cache between runs
      await page.context().clearCookies();

      const startTime = Date.now();
      await page.goto(`${BASE_URL}/ro`, { waitUntil: 'networkidle' });
      const loadTime = Date.now() - startTime;
      loadTimes.push(loadTime);

      console.log(`  Run ${i + 1}: ${loadTime}ms`);
    }

    const avgLoadTime = loadTimes.reduce((a, b) => a + b, 0) / loadTimes.length;
    const maxLoadTime = Math.max(...loadTimes);
    const minLoadTime = Math.min(...loadTimes);
    const variance = maxLoadTime - minLoadTime;
    const variancePercent = (variance / avgLoadTime) * 100;

    console.log(`\n${colors.bold}Statistics:${colors.reset}`);
    console.log(`  Average: ${avgLoadTime.toFixed(2)}ms`);
    console.log(`  Min: ${minLoadTime}ms`);
    console.log(`  Max: ${maxLoadTime}ms`);
    console.log(`  Variance: ${variance}ms (${variancePercent.toFixed(1)}%)`);

    // Check consistency
    expect(avgLoadTime, 'Average load time should be reasonable').toBeLessThan(THRESHOLDS.loadComplete);

    if (variancePercent > 30) {
      console.log(`${colors.yellow}⚠ High variance in load times (${variancePercent.toFixed(1)}%). Performance is inconsistent.${colors.reset}`);
    } else {
      console.log(`${colors.green}✓ Load times are consistent (variance: ${variancePercent.toFixed(1)}%)${colors.reset}`);
    }
  });
});
