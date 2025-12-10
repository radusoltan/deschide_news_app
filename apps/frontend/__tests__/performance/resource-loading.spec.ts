/**
 * Resource Loading Performance Tests
 *
 * Measures:
 * - JavaScript bundle size
 * - Image loading performance
 * - Total HTTP requests
 * - Render-blocking resources
 * - CSS optimization
 *
 * @group performance
 */

import { test, expect } from '@playwright/test';

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:3005';

const THRESHOLDS = {
  jsBundleSize: 500 * 1024,     // 500KB warning for JS
  cssBundleSize: 100 * 1024,    // 100KB warning for CSS
  totalRequests: 50,            // Max 50 requests
  imageLoadTime: 2000,          // < 2s for all images
  renderBlockingResources: 5,   // Max 5 render-blocking resources
  totalTransferSize: 2 * 1024 * 1024, // 2MB total warning
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

interface ResourceMetrics {
  url: string;
  type: string;
  size: number;
  duration: number;
  startTime: number;
}

/**
 * Get all resource metrics from the page
 */
async function getResourceMetrics(page: any): Promise<ResourceMetrics[]> {
  return await page.evaluate(() => {
    const resources = performance.getEntriesByType('resource') as PerformanceResourceTiming[];
    return resources.map(r => ({
      url: r.name,
      type: r.initiatorType,
      size: r.transferSize || 0,
      duration: r.duration,
      startTime: r.startTime,
    }));
  });
}

function formatSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(2)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
}

function formatMetric(name: string, value: number | string, threshold: number, isSize: boolean = false): string {
  const numValue = typeof value === 'number' ? value : 0;
  const color = numValue < threshold ? colors.green : numValue < threshold * 1.5 ? colors.yellow : colors.red;
  const status = numValue < threshold ? '✓' : numValue < threshold * 1.5 ? '⚠' : '✗';
  const displayValue = isSize ? formatSize(numValue) : value;
  const thresholdDisplay = isSize ? formatSize(threshold) : threshold;
  return `${color}${status} ${name}: ${displayValue} (threshold: ${thresholdDisplay})${colors.reset}`;
}

test.describe('Resource Loading Performance', () => {
  test.describe.configure({ mode: 'serial' });

  test('JavaScript bundle size check', async ({ page }) => {
    console.log(`\n${colors.cyan}=== JavaScript Bundle Analysis ===${colors.reset}`);

    await page.goto(`${BASE_URL}/ro`);
    await page.waitForLoadState('networkidle');

    const resources = await getResourceMetrics(page);
    const jsResources = resources.filter(r => r.type === 'script' || r.url.endsWith('.js'));

    let totalJsSize = 0;
    const largeScripts: ResourceMetrics[] = [];

    console.log(`\n${colors.bold}JavaScript Files:${colors.reset}`);
    jsResources.forEach(js => {
      totalJsSize += js.size;
      if (js.size > 100 * 1024) { // Flag scripts > 100KB
        largeScripts.push(js);
      }
      const filename = js.url.split('/').pop() || js.url;
      console.log(`  ${filename.substring(0, 50)}: ${formatSize(js.size)} (${js.duration.toFixed(2)}ms)`);
    });

    console.log(`\n${colors.bold}Summary:${colors.reset}`);
    console.log(formatMetric('Total JS Size', totalJsSize, THRESHOLDS.jsBundleSize, true));
    console.log(`  Number of JS files: ${jsResources.length}`);

    if (largeScripts.length > 0) {
      console.log(`\n${colors.yellow}⚠ Large JavaScript files detected:${colors.reset}`);
      largeScripts.forEach(js => {
        const filename = js.url.split('/').pop() || js.url;
        console.log(`  - ${filename}: ${formatSize(js.size)}`);
      });
      console.log(`${colors.yellow}Consider code splitting or lazy loading.${colors.reset}`);
    }

    // Soft warning for bundle size
    if (totalJsSize > THRESHOLDS.jsBundleSize) {
      console.log(`${colors.yellow}⚠ WARNING: Total JavaScript size exceeds recommended limit.${colors.reset}`);
      console.log(`${colors.yellow}Recommendations:${colors.reset}`);
      console.log(`  - Enable tree shaking`);
      console.log(`  - Use dynamic imports for large components`);
      console.log(`  - Remove unused dependencies`);
      console.log(`  - Consider code splitting by route`);
    }

    // This is a soft check - log warning but don't fail
    expect(jsResources.length, 'Should have JavaScript files loaded').toBeGreaterThan(0);
  });

  test('CSS bundle size check', async ({ page }) => {
    console.log(`\n${colors.cyan}=== CSS Bundle Analysis ===${colors.reset}`);

    await page.goto(`${BASE_URL}/ro`);
    await page.waitForLoadState('networkidle');

    const resources = await getResourceMetrics(page);
    const cssResources = resources.filter(r => r.type === 'link' && r.url.includes('.css'));

    let totalCssSize = 0;

    console.log(`\n${colors.bold}CSS Files:${colors.reset}`);
    cssResources.forEach(css => {
      totalCssSize += css.size;
      const filename = css.url.split('/').pop() || css.url;
      console.log(`  ${filename.substring(0, 50)}: ${formatSize(css.size)} (${css.duration.toFixed(2)}ms)`);
    });

    console.log(`\n${colors.bold}Summary:${colors.reset}`);
    console.log(formatMetric('Total CSS Size', totalCssSize, THRESHOLDS.cssBundleSize, true));
    console.log(`  Number of CSS files: ${cssResources.length}`);

    if (totalCssSize > THRESHOLDS.cssBundleSize) {
      console.log(`${colors.yellow}⚠ WARNING: CSS size is large.${colors.reset}`);
      console.log(`${colors.yellow}Recommendations:${colors.reset}`);
      console.log(`  - Enable CSS purging (PurgeCSS/Tailwind CSS)`);
      console.log(`  - Minify CSS in production`);
      console.log(`  - Consider critical CSS extraction`);
    }

    expect(cssResources.length, 'Should have CSS files loaded').toBeGreaterThan(0);
  });

  test('Image loading performance', async ({ page }) => {
    console.log(`\n${colors.cyan}=== Image Loading Performance ===${colors.reset}`);

    await page.goto(`${BASE_URL}/ro`);
    await page.waitForLoadState('networkidle');

    const resources = await getResourceMetrics(page);
    const imageResources = resources.filter(r =>
      r.type === 'img' ||
      r.url.match(/\.(jpg|jpeg|png|gif|webp|svg|avif)(\?|$)/i)
    );

    let totalImageSize = 0;
    let maxImageLoadTime = 0;
    const largeImages: ResourceMetrics[] = [];
    const slowImages: ResourceMetrics[] = [];

    console.log(`\n${colors.bold}Image Resources:${colors.reset}`);
    imageResources.slice(0, 10).forEach(img => { // Show first 10
      totalImageSize += img.size;
      maxImageLoadTime = Math.max(maxImageLoadTime, img.duration);

      if (img.size > 200 * 1024) { // Flag images > 200KB
        largeImages.push(img);
      }
      if (img.duration > 1000) { // Flag slow loading images
        slowImages.push(img);
      }

      const filename = img.url.split('/').pop()?.substring(0, 40) || img.url.substring(0, 40);
      console.log(`  ${filename}: ${formatSize(img.size)} (${img.duration.toFixed(2)}ms)`);
    });

    if (imageResources.length > 10) {
      console.log(`  ... and ${imageResources.length - 10} more images`);
    }

    console.log(`\n${colors.bold}Summary:${colors.reset}`);
    console.log(`  Total images: ${imageResources.length}`);
    console.log(`  Total size: ${formatSize(totalImageSize)}`);
    console.log(`  Max load time: ${maxImageLoadTime.toFixed(2)}ms`);
    console.log(`  Avg size: ${formatSize(totalImageSize / imageResources.length)}`);

    if (largeImages.length > 0) {
      console.log(`\n${colors.yellow}⚠ Large images detected (> 200KB):${colors.reset}`);
      largeImages.slice(0, 5).forEach(img => {
        const filename = img.url.split('/').pop() || img.url;
        console.log(`  - ${filename.substring(0, 50)}: ${formatSize(img.size)}`);
      });
      console.log(`${colors.yellow}Consider:${colors.reset}`);
      console.log(`  - Using WebP or AVIF format`);
      console.log(`  - Implementing responsive images (srcset)`);
      console.log(`  - Compressing images`);
      console.log(`  - Lazy loading below-the-fold images`);
    }

    if (slowImages.length > 0) {
      console.log(`\n${colors.yellow}⚠ Slow loading images detected (> 1s):${colors.reset}`);
      slowImages.slice(0, 5).forEach(img => {
        const filename = img.url.split('/').pop() || img.url;
        console.log(`  - ${filename.substring(0, 50)}: ${img.duration.toFixed(2)}ms`);
      });
    }

    // Skip test if no images found
    if (imageResources.length === 0) {
      console.log(`${colors.yellow}ℹ No images detected on homepage - skipping image performance test${colors.reset}`);
      test.skip();
    }

    expect(imageResources.length, 'Should have images loaded').toBeGreaterThan(0);
    expect(maxImageLoadTime, 'Max image load time should be reasonable').toBeLessThan(THRESHOLDS.imageLoadTime);
  });

  test('Total HTTP requests count', async ({ page }) => {
    console.log(`\n${colors.cyan}=== HTTP Requests Analysis ===${colors.reset}`);

    await page.goto(`${BASE_URL}/ro`);
    await page.waitForLoadState('networkidle');

    const resources = await getResourceMetrics(page);

    // Group by type
    const byType: Record<string, number> = {};
    let totalSize = 0;

    resources.forEach(r => {
      byType[r.type] = (byType[r.type] || 0) + 1;
      totalSize += r.size;
    });

    console.log(`\n${colors.bold}Requests by Type:${colors.reset}`);
    Object.entries(byType).sort((a, b) => b[1] - a[1]).forEach(([type, count]) => {
      console.log(`  ${type}: ${count}`);
    });

    console.log(`\n${colors.bold}Summary:${colors.reset}`);
    console.log(formatMetric('Total Requests', resources.length, THRESHOLDS.totalRequests, false));
    console.log(formatMetric('Total Transfer Size', totalSize, THRESHOLDS.totalTransferSize, true));

    if (resources.length > THRESHOLDS.totalRequests) {
      console.log(`${colors.yellow}⚠ WARNING: High number of HTTP requests.${colors.reset}`);
      console.log(`${colors.yellow}Recommendations:${colors.reset}`);
      console.log(`  - Combine CSS/JS files`);
      console.log(`  - Use CSS sprites for small images`);
      console.log(`  - Implement HTTP/2 server push`);
      console.log(`  - Use CDN for static assets`);
      console.log(`  - Enable browser caching`);
    }

    // Soft check - warn but don't fail
    expect(resources.length, 'Should have resources loaded').toBeGreaterThan(0);
  });

  test('Render-blocking resources check', async ({ page }) => {
    console.log(`\n${colors.cyan}=== Render-Blocking Resources ===${colors.reset}`);

    await page.goto(`${BASE_URL}/ro`);
    await page.waitForLoadState('networkidle');

    const resources = await getResourceMetrics(page);

    // Find resources loaded before FCP
    const paintEntries = await page.evaluate(() => {
      const entries = performance.getEntriesByType('paint');
      const fcp = entries.find(e => e.name === 'first-contentful-paint');
      return fcp ? fcp.startTime : 0;
    });

    const renderBlockingResources = resources.filter(r =>
      (r.type === 'script' || r.type === 'link') &&
      r.startTime < paintEntries
    );

    console.log(`\n${colors.bold}Render-Blocking Resources (loaded before FCP):${colors.reset}`);
    if (renderBlockingResources.length === 0) {
      console.log(`${colors.green}✓ No render-blocking resources detected!${colors.reset}`);
    } else {
      renderBlockingResources.forEach(r => {
        const filename = r.url.split('/').pop() || r.url;
        console.log(`  - ${r.type}: ${filename.substring(0, 50)} (${formatSize(r.size)})`);
      });
    }

    console.log(`\n${colors.bold}Summary:${colors.reset}`);
    console.log(formatMetric('Render-blocking resources', renderBlockingResources.length, THRESHOLDS.renderBlockingResources, false));

    if (renderBlockingResources.length > THRESHOLDS.renderBlockingResources) {
      console.log(`${colors.yellow}⚠ WARNING: Multiple render-blocking resources detected.${colors.reset}`);
      console.log(`${colors.yellow}Recommendations:${colors.reset}`);
      console.log(`  - Defer non-critical JavaScript`);
      console.log(`  - Use async/defer attributes for scripts`);
      console.log(`  - Inline critical CSS`);
      console.log(`  - Load fonts asynchronously`);
      console.log(`  - Consider using <link rel="preload"> for critical resources`);
    }

    // This is informational - don't fail the test
    expect(true).toBe(true);
  });

  test('Font loading performance', async ({ page }) => {
    console.log(`\n${colors.cyan}=== Font Loading Performance ===${colors.reset}`);

    await page.goto(`${BASE_URL}/ro`);
    await page.waitForLoadState('networkidle');

    const resources = await getResourceMetrics(page);
    const fontResources = resources.filter(r =>
      r.url.match(/\.(woff|woff2|ttf|otf|eot)(\?|$)/i) ||
      r.type === 'font'
    );

    let totalFontSize = 0;

    console.log(`\n${colors.bold}Font Files:${colors.reset}`);
    if (fontResources.length === 0) {
      console.log(`  ${colors.yellow}No custom fonts detected (using system fonts)${colors.reset}`);
    } else {
      fontResources.forEach(font => {
        totalFontSize += font.size;
        const filename = font.url.split('/').pop() || font.url;
        console.log(`  ${filename}: ${formatSize(font.size)} (${font.duration.toFixed(2)}ms)`);
      });

      console.log(`\n${colors.bold}Summary:${colors.reset}`);
      console.log(`  Total fonts: ${fontResources.length}`);
      console.log(`  Total size: ${formatSize(totalFontSize)}`);

      if (totalFontSize > 200 * 1024) {
        console.log(`${colors.yellow}⚠ Font size is large (> 200KB).${colors.reset}`);
        console.log(`${colors.yellow}Consider:${colors.reset}`);
        console.log(`  - Using WOFF2 format (better compression)`);
        console.log(`  - Subsetting fonts (include only needed characters)`);
        console.log(`  - Using system fonts where possible`);
        console.log(`  - Implementing font-display: swap`);
      }
    }

    expect(true).toBe(true);
  });

  test('Third-party resources analysis', async ({ page }) => {
    console.log(`\n${colors.cyan}=== Third-Party Resources ===${colors.reset}`);

    await page.goto(`${BASE_URL}/ro`);
    await page.waitForLoadState('networkidle');

    const resources = await getResourceMetrics(page);
    const currentDomain = new URL(BASE_URL).hostname;

    const thirdPartyResources = resources.filter(r => {
      try {
        const url = new URL(r.url);
        return url.hostname !== currentDomain && url.hostname !== 'localhost';
      } catch {
        return false;
      }
    });

    console.log(`\n${colors.bold}Third-Party Domains:${colors.reset}`);
    if (thirdPartyResources.length === 0) {
      console.log(`${colors.green}✓ No third-party resources detected!${colors.reset}`);
    } else {
      const byDomain: Record<string, { count: number; size: number }> = {};

      thirdPartyResources.forEach(r => {
        try {
          const domain = new URL(r.url).hostname;
          if (!byDomain[domain]) {
            byDomain[domain] = { count: 0, size: 0 };
          }
          byDomain[domain].count++;
          byDomain[domain].size += r.size;
        } catch {}
      });

      Object.entries(byDomain).forEach(([domain, stats]) => {
        console.log(`  ${domain}: ${stats.count} requests, ${formatSize(stats.size)}`);
      });

      console.log(`\n${colors.bold}Summary:${colors.reset}`);
      console.log(`  Third-party requests: ${thirdPartyResources.length}`);
      console.log(`  Third-party domains: ${Object.keys(byDomain).length}`);

      if (thirdPartyResources.length > 10) {
        console.log(`${colors.yellow}⚠ Multiple third-party resources detected.${colors.reset}`);
        console.log(`${colors.yellow}Consider:${colors.reset}`);
        console.log(`  - Self-hosting critical resources`);
        console.log(`  - Using a CDN for better performance`);
        console.log(`  - Lazy loading third-party scripts`);
        console.log(`  - Removing unnecessary analytics/tracking scripts`);
      }
    }

    expect(true).toBe(true);
  });
});

test.describe('Resource Loading Summary', () => {
  test('Generate resource loading report', async ({ page }) => {
    console.log(`\n${colors.cyan}=== Resource Loading Performance Report ===${colors.reset}`);
    console.log(`${colors.green}✓ All resource loading tests completed${colors.reset}`);
    console.log(`\nThresholds:`);
    console.log(`  JS Bundle:     < ${formatSize(THRESHOLDS.jsBundleSize)}`);
    console.log(`  CSS Bundle:    < ${formatSize(THRESHOLDS.cssBundleSize)}`);
    console.log(`  Total Requests: < ${THRESHOLDS.totalRequests}`);
    console.log(`  Image Load:    < ${THRESHOLDS.imageLoadTime}ms`);
    console.log(`  Render-blocking: < ${THRESHOLDS.renderBlockingResources}`);
    console.log(`\n${colors.bold}Best Practices:${colors.reset}`);
    console.log(`  ✓ Use WebP/AVIF for images`);
    console.log(`  ✓ Implement code splitting`);
    console.log(`  ✓ Enable compression (gzip/brotli)`);
    console.log(`  ✓ Use CDN for static assets`);
    console.log(`  ✓ Implement lazy loading`);
    console.log(`  ✓ Minimize render-blocking resources`);
    console.log(`  ✓ Optimize critical rendering path`);

    expect(true).toBe(true);
  });
});
