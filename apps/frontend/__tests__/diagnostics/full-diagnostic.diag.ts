/**
 * Full Diagnostic Script
 *
 * Comprehensive diagnostic that combines:
 * - Console message capture (errors, warnings, logs)
 * - Network traffic capture (API calls, status codes)
 * - Performance metrics (timing, resource loading)
 * - Visual indicators (error states, loading spinners)
 *
 * This is the primary diagnostic tool for the automated testing agent.
 * Generates a complete report with all debugging information.
 *
 * Usage: npx playwright test __tests__/diagnostics/full-diagnostic.diag.ts --headed
 */

import { test, expect, Page, ConsoleMessage, Request, Response } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

interface FullDiagnosticResult {
  metadata: {
    timestamp: string;
    pageUrl: string;
    pageName: string;
    userAgent: string;
    viewport: { width: number; height: number };
  };
  console: {
    messages: { type: string; text: string; timestamp: string }[];
    errors: number;
    warnings: number;
  };
  network: {
    requests: { url: string; method: string; status?: number; timing?: number }[];
    apiCalls: number;
    apiErrors: number;
    failedRequests: string[];
  };
  performance: {
    loadTime?: number;
    domContentLoaded?: number;
    firstContentfulPaint?: number;
    largestContentfulPaint?: number;
  };
  visual: {
    errorIndicators: string[];
    loadingIndicators: string[];
    emptyStates: string[];
    screenshots: string[];
  };
  verdict: 'PASS' | 'WARNING' | 'FAIL';
  issues: string[];
}

// Test scenarios covering all major areas
const DIAGNOSTIC_SCENARIOS = [
  // Public frontend
  { name: 'Public Homepage RO', url: '/ro', category: 'public' },
  { name: 'Public Homepage EN', url: '/en', category: 'public' },
  { name: 'Public Archive 2024', url: '/ro/archive/2024', category: 'public' },
  { name: 'Public Archive 2023/06', url: '/ro/archive/2023/06', category: 'public' },

  // Admin panel
  { name: 'Admin Login Page', url: '/ro/admin', category: 'admin' },
  { name: 'Admin Dashboard', url: '/ro/admin/dashboard', category: 'admin', requiresAuth: true },
  { name: 'Admin Articles List', url: '/ro/admin/articles', category: 'admin', requiresAuth: true },
  { name: 'Admin Article Create', url: '/ro/admin/articles/new', category: 'admin', requiresAuth: true },
  { name: 'Admin Images List', url: '/ro/admin/images', category: 'admin', requiresAuth: true },
  { name: 'Admin Categories', url: '/ro/admin/categories', category: 'admin', requiresAuth: true },
  { name: 'Admin Authors', url: '/ro/admin/authors', category: 'admin', requiresAuth: true },
  { name: 'Admin LiveText List', url: '/ro/admin/live-text', category: 'admin', requiresAuth: true },
  { name: 'Admin Tags', url: '/ro/admin/tags', category: 'admin', requiresAuth: true },
];

// Visual indicators to check
const ERROR_SELECTORS = [
  '[class*="error"]',
  '[class*="Error"]',
  '[data-error]',
  '.text-red-500',
  '.text-red-600',
  '.bg-red-100',
  '[role="alert"]',
  '.alert-danger',
  '.notification-error',
];

const LOADING_SELECTORS = [
  '[class*="loading"]',
  '[class*="Loading"]',
  '[class*="spinner"]',
  '[class*="Spinner"]',
  '.animate-spin',
  '[data-loading="true"]',
  '[aria-busy="true"]',
];

const EMPTY_STATE_SELECTORS = [
  '[class*="empty"]',
  '[class*="Empty"]',
  '[class*="no-data"]',
  '[class*="no-results"]',
  'text=Nu există date',
  'text=No data',
  'text=Нет данных',
];

const OUTPUT_DIR = path.join(__dirname, '../../test-results/diagnostics');

async function runFullDiagnostic(page: Page, url: string, name: string): Promise<FullDiagnosticResult> {
  const result: FullDiagnosticResult = {
    metadata: {
      timestamp: new Date().toISOString(),
      pageUrl: url,
      pageName: name,
      userAgent: '',
      viewport: { width: 0, height: 0 },
    },
    console: {
      messages: [],
      errors: 0,
      warnings: 0,
    },
    network: {
      requests: [],
      apiCalls: 0,
      apiErrors: 0,
      failedRequests: [],
    },
    performance: {},
    visual: {
      errorIndicators: [],
      loadingIndicators: [],
      emptyStates: [],
      screenshots: [],
    },
    verdict: 'PASS',
    issues: [],
  };

  // Capture console
  page.on('console', (msg: ConsoleMessage) => {
    result.console.messages.push({
      type: msg.type(),
      text: msg.text(),
      timestamp: new Date().toISOString(),
    });
    if (msg.type() === 'error') result.console.errors++;
    if (msg.type() === 'warning') result.console.warnings++;
  });

  page.on('pageerror', (error) => {
    result.console.messages.push({
      type: 'pageerror',
      text: error.message,
      timestamp: new Date().toISOString(),
    });
    result.console.errors++;
  });

  // Capture network
  const requestTimings = new Map<string, number>();

  page.on('request', (request: Request) => {
    requestTimings.set(request.url(), Date.now());
  });

  page.on('response', (response: Response) => {
    const timing = requestTimings.get(response.url());
    const reqData = {
      url: response.url(),
      method: response.request().method(),
      status: response.status(),
      timing: timing ? Date.now() - timing : undefined,
    };
    result.network.requests.push(reqData);

    if (response.url().includes('/api/') || response.url().includes(':8081')) {
      result.network.apiCalls++;
      if (response.status() >= 400) {
        result.network.apiErrors++;
      }
    }
  });

  page.on('requestfailed', (request: Request) => {
    result.network.failedRequests.push(request.url());
  });

  // Navigate
  const startTime = Date.now();
  try {
    await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 });
    result.performance.loadTime = Date.now() - startTime;
  } catch (error) {
    result.issues.push(`Navigation failed: ${error instanceof Error ? error.message : String(error)}`);
  }

  // Get viewport and user agent
  const viewport = page.viewportSize();
  result.metadata.viewport = viewport || { width: 0, height: 0 };
  result.metadata.userAgent = await page.evaluate(() => navigator.userAgent);

  // Get performance metrics
  try {
    const perfMetrics = await page.evaluate(() => {
      const perf = performance.getEntriesByType('navigation')[0] as PerformanceNavigationTiming;
      const paint = performance.getEntriesByType('paint');
      return {
        domContentLoaded: perf?.domContentLoadedEventEnd - perf?.startTime,
        fcp: paint.find(p => p.name === 'first-contentful-paint')?.startTime,
      };
    });
    result.performance.domContentLoaded = perfMetrics.domContentLoaded;
    result.performance.firstContentfulPaint = perfMetrics.fcp;
  } catch {
    // Performance API might not be available
  }

  // Wait for dynamic content
  await page.waitForTimeout(2000);

  // Check visual indicators
  for (const selector of ERROR_SELECTORS) {
    try {
      const elements = await page.locator(selector).all();
      if (elements.length > 0) {
        const texts = await Promise.all(elements.slice(0, 3).map(el => el.textContent().catch(() => '')));
        result.visual.errorIndicators.push(`${selector}: ${texts.filter(Boolean).join(', ').substring(0, 100)}`);
      }
    } catch {
      // Selector might be invalid
    }
  }

  for (const selector of LOADING_SELECTORS) {
    try {
      const count = await page.locator(selector).count();
      if (count > 0) {
        result.visual.loadingIndicators.push(`${selector}: ${count} elements still loading`);
      }
    } catch {
      // Selector might be invalid
    }
  }

  for (const selector of EMPTY_STATE_SELECTORS) {
    try {
      const count = await page.locator(selector).count();
      if (count > 0) {
        result.visual.emptyStates.push(`${selector}: ${count} empty state indicators`);
      }
    } catch {
      // Selector might be invalid
    }
  }

  // Take screenshot
  const screenshotPath = path.join(OUTPUT_DIR, `diag-${name.replace(/\s+/g, '-')}-${Date.now()}.png`);
  try {
    await page.screenshot({ path: screenshotPath, fullPage: true });
    result.visual.screenshots.push(screenshotPath);
  } catch {
    // Screenshot might fail
  }

  // Determine verdict
  if (result.console.errors > 5 || result.network.apiErrors > 0 || result.network.failedRequests.length > 0) {
    result.verdict = 'FAIL';
  } else if (result.console.errors > 0 || result.console.warnings > 3 || result.visual.errorIndicators.length > 0) {
    result.verdict = 'WARNING';
  }

  // Collect issues
  if (result.console.errors > 0) {
    result.issues.push(`${result.console.errors} console errors detected`);
  }
  if (result.network.apiErrors > 0) {
    result.issues.push(`${result.network.apiErrors} API errors (4xx/5xx responses)`);
  }
  if (result.network.failedRequests.length > 0) {
    result.issues.push(`${result.network.failedRequests.length} failed network requests`);
  }
  if (result.visual.errorIndicators.length > 0) {
    result.issues.push(`Visual error indicators found on page`);
  }
  if (result.visual.loadingIndicators.length > 0 && (result.performance.loadTime || 0) > 5000) {
    result.issues.push(`Page still showing loading indicators after ${result.performance.loadTime}ms`);
  }

  return result;
}

function generateReport(results: Record<string, FullDiagnosticResult>) {
  if (!fs.existsSync(OUTPUT_DIR)) {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });
  }

  // JSON report
  const jsonPath = path.join(OUTPUT_DIR, `full-diagnostic-${Date.now()}.json`);
  fs.writeFileSync(jsonPath, JSON.stringify(results, null, 2));

  // Generate markdown report
  const mdLines: string[] = [
    '# Full Diagnostic Report',
    '',
    `**Generated:** ${new Date().toISOString()}`,
    '',
    '## Summary',
    '',
    '| Page | Verdict | Console Errors | API Errors | Issues |',
    '|------|---------|----------------|------------|--------|',
  ];

  let totalPass = 0;
  let totalWarning = 0;
  let totalFail = 0;

  for (const [name, result] of Object.entries(results)) {
    const verdictEmoji = result.verdict === 'PASS' ? '✅' : result.verdict === 'WARNING' ? '⚠️' : '❌';
    mdLines.push(
      `| ${name} | ${verdictEmoji} ${result.verdict} | ${result.console.errors} | ${result.network.apiErrors} | ${result.issues.length} |`
    );

    if (result.verdict === 'PASS') totalPass++;
    else if (result.verdict === 'WARNING') totalWarning++;
    else totalFail++;
  }

  mdLines.push('');
  mdLines.push('## Overall Statistics');
  mdLines.push('');
  mdLines.push(`- ✅ Passed: ${totalPass}`);
  mdLines.push(`- ⚠️ Warnings: ${totalWarning}`);
  mdLines.push(`- ❌ Failed: ${totalFail}`);
  mdLines.push('');

  // Detailed issues
  mdLines.push('## Detailed Issues');
  mdLines.push('');

  for (const [name, result] of Object.entries(results)) {
    if (result.issues.length > 0 || result.verdict !== 'PASS') {
      mdLines.push(`### ${name}`);
      mdLines.push('');
      mdLines.push(`**Verdict:** ${result.verdict}`);
      mdLines.push('');

      if (result.issues.length > 0) {
        mdLines.push('**Issues:**');
        result.issues.forEach(issue => mdLines.push(`- ${issue}`));
        mdLines.push('');
      }

      if (result.console.errors > 0) {
        mdLines.push('**Console Errors:**');
        result.console.messages
          .filter(m => m.type === 'error' || m.type === 'pageerror')
          .slice(0, 5)
          .forEach(m => mdLines.push(`- \`${m.text.substring(0, 200)}\``));
        mdLines.push('');
      }

      if (result.network.apiErrors > 0) {
        mdLines.push('**API Errors:**');
        result.network.requests
          .filter(r => (r.status || 0) >= 400)
          .slice(0, 5)
          .forEach(r => mdLines.push(`- ${r.status} ${r.method} ${r.url}`));
        mdLines.push('');
      }

      if (result.visual.screenshots.length > 0) {
        mdLines.push(`**Screenshot:** ${result.visual.screenshots[0]}`);
        mdLines.push('');
      }
    }
  }

  const mdPath = path.join(OUTPUT_DIR, `full-diagnostic-${Date.now()}.md`);
  fs.writeFileSync(mdPath, mdLines.join('\n'));

  console.log('\n========== FULL DIAGNOSTIC COMPLETE ==========');
  console.log(`✅ Passed: ${totalPass}`);
  console.log(`⚠️ Warnings: ${totalWarning}`);
  console.log(`❌ Failed: ${totalFail}`);
  console.log(`\nReports saved to:`);
  console.log(`  JSON: ${jsonPath}`);
  console.log(`  Markdown: ${mdPath}`);
  console.log('================================================\n');
}

test.describe('Full Diagnostic Suite', () => {
  const results: Record<string, FullDiagnosticResult> = {};
  let isAuthenticated = false;

  test.afterAll(async () => {
    generateReport(results);
  });

  for (const scenario of DIAGNOSTIC_SCENARIOS) {
    test(`Diagnose: ${scenario.name}`, async ({ page }) => {
      const baseUrl = process.env.BASE_URL || 'http://localhost:3005';
      const fullUrl = `${baseUrl}${scenario.url}`;

      // Handle authentication for admin pages
      if (scenario.requiresAuth && !isAuthenticated) {
        await page.goto(`${baseUrl}/ro/admin`);
        try {
          await page.fill('input[name="email"], input[type="email"]', 'admin@deschide.md');
          await page.fill('input[name="password"], input[type="password"]', 'admin123');
          await page.click('button[type="submit"]');
          await page.waitForTimeout(3000);
          isAuthenticated = true;
        } catch {
          console.log('Auth setup issue');
        }
      }

      const result = await runFullDiagnostic(page, fullUrl, scenario.name);
      results[scenario.name] = result;

      // Log immediate summary
      const emoji = result.verdict === 'PASS' ? '✅' : result.verdict === 'WARNING' ? '⚠️' : '❌';
      console.log(`${emoji} [${scenario.name}] Verdict: ${result.verdict} | Issues: ${result.issues.length}`);

      // Soft assertion
      expect(result.network.apiErrors, `${scenario.name} has API errors`).toBeLessThanOrEqual(2);
    });
  }
});

export { runFullDiagnostic, FullDiagnosticResult };
