/**
 * Console Capture Diagnostic Script
 *
 * This script captures all console messages (errors, warnings, logs) during page interaction.
 * Used by the automated testing agent to diagnose issues without direct Console access.
 *
 * Usage: npx playwright test __tests__/diagnostics/console-capture.diag.ts --headed
 */

import { test, expect, Page, ConsoleMessage } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

interface ConsoleCaptureResult {
  timestamp: string;
  url: string;
  messages: {
    type: string;
    text: string;
    location?: string;
    timestamp: string;
  }[];
  summary: {
    errors: number;
    warnings: number;
    logs: number;
    info: number;
    total: number;
  };
}

// Configure the pages to test
const PAGES_TO_TEST = [
  { name: 'Homepage RO', url: '/ro' },
  { name: 'Homepage EN', url: '/en' },
  { name: 'Homepage RU', url: '/ru' },
  { name: 'Admin Login', url: '/ro/admin' },
  { name: 'Admin Dashboard', url: '/ro/admin/dashboard', requiresAuth: true },
  { name: 'Admin Articles', url: '/ro/admin/articles', requiresAuth: true },
  { name: 'Admin Images', url: '/ro/admin/images', requiresAuth: true },
  { name: 'Admin LiveText', url: '/ro/admin/live-text', requiresAuth: true },
  { name: 'Archive 2024', url: '/ro/archive/2024' },
  { name: 'Archive 2023', url: '/ro/archive/2023' },
];

// Output directory for reports
const OUTPUT_DIR = path.join(__dirname, '../../test-results/diagnostics');

async function captureConsoleLogs(page: Page, pageUrl: string, pageName: string): Promise<ConsoleCaptureResult> {
  const messages: ConsoleCaptureResult['messages'] = [];
  const summary = { errors: 0, warnings: 0, logs: 0, info: 0, total: 0 };

  // Listen to console messages
  page.on('console', (msg: ConsoleMessage) => {
    const type = msg.type();
    messages.push({
      type,
      text: msg.text(),
      location: msg.location()?.url,
      timestamp: new Date().toISOString(),
    });

    summary.total++;
    if (type === 'error') summary.errors++;
    else if (type === 'warning') summary.warnings++;
    else if (type === 'log') summary.logs++;
    else if (type === 'info') summary.info++;
  });

  // Listen to page errors
  page.on('pageerror', (error) => {
    messages.push({
      type: 'pageerror',
      text: error.message,
      timestamp: new Date().toISOString(),
    });
    summary.errors++;
    summary.total++;
  });

  // Navigate and wait for network idle
  try {
    await page.goto(pageUrl, { waitUntil: 'networkidle', timeout: 30000 });
    await page.waitForTimeout(2000); // Wait for any delayed console messages
  } catch (error) {
    messages.push({
      type: 'navigation-error',
      text: `Navigation failed: ${error instanceof Error ? error.message : String(error)}`,
      timestamp: new Date().toISOString(),
    });
    summary.errors++;
    summary.total++;
  }

  return {
    timestamp: new Date().toISOString(),
    url: pageUrl,
    messages,
    summary,
  };
}

function saveReport(results: Record<string, ConsoleCaptureResult>) {
  // Ensure output directory exists
  if (!fs.existsSync(OUTPUT_DIR)) {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });
  }

  const reportPath = path.join(OUTPUT_DIR, `console-capture-${Date.now()}.json`);
  fs.writeFileSync(reportPath, JSON.stringify(results, null, 2));
  console.log(`Report saved to: ${reportPath}`);

  // Generate summary
  let totalErrors = 0;
  let totalWarnings = 0;
  const pagesSummary: string[] = [];

  for (const [pageName, result] of Object.entries(results)) {
    totalErrors += result.summary.errors;
    totalWarnings += result.summary.warnings;

    const status = result.summary.errors > 0 ? '❌' : result.summary.warnings > 0 ? '⚠️' : '✅';
    pagesSummary.push(`${status} ${pageName}: ${result.summary.errors} errors, ${result.summary.warnings} warnings`);
  }

  console.log('\n========== CONSOLE CAPTURE SUMMARY ==========');
  console.log(`Total Errors: ${totalErrors}`);
  console.log(`Total Warnings: ${totalWarnings}`);
  console.log('\nPer-page summary:');
  pagesSummary.forEach(line => console.log(line));
  console.log('==============================================\n');

  // Save human-readable summary
  const summaryPath = path.join(OUTPUT_DIR, `console-summary-${Date.now()}.txt`);
  fs.writeFileSync(summaryPath, [
    'CONSOLE CAPTURE DIAGNOSTIC REPORT',
    `Generated: ${new Date().toISOString()}`,
    '',
    `Total Errors: ${totalErrors}`,
    `Total Warnings: ${totalWarnings}`,
    '',
    'Per-page summary:',
    ...pagesSummary,
    '',
    'Detailed errors:',
    ...Object.entries(results).flatMap(([pageName, result]) =>
      result.messages
        .filter(m => m.type === 'error' || m.type === 'pageerror')
        .map(m => `[${pageName}] ${m.text}`)
    ),
  ].join('\n'));
  console.log(`Summary saved to: ${summaryPath}`);
}

test.describe('Console Capture Diagnostic', () => {
  const results: Record<string, ConsoleCaptureResult> = {};

  test.afterAll(async () => {
    saveReport(results);
  });

  for (const pageConfig of PAGES_TO_TEST) {
    test(`Capture console for: ${pageConfig.name}`, async ({ page }) => {
      const baseUrl = process.env.BASE_URL || 'http://localhost:3005';
      const fullUrl = `${baseUrl}${pageConfig.url}`;

      // If page requires auth, login first
      if (pageConfig.requiresAuth) {
        await page.goto(`${baseUrl}/ro/admin`);
        // Try to login - adjust selectors as needed
        try {
          await page.fill('input[name="email"], input[type="email"]', 'admin@deschide.md');
          await page.fill('input[name="password"], input[type="password"]', 'admin123');
          await page.click('button[type="submit"]');
          await page.waitForTimeout(2000);
        } catch {
          console.log(`Auth might already be set or login form not found for ${pageConfig.name}`);
        }
      }

      const result = await captureConsoleLogs(page, fullUrl, pageConfig.name);
      results[pageConfig.name] = result;

      // Take screenshot if there are errors
      if (result.summary.errors > 0) {
        const screenshotPath = path.join(OUTPUT_DIR, `error-${pageConfig.name.replace(/\s+/g, '-')}-${Date.now()}.png`);
        await page.screenshot({ path: screenshotPath, fullPage: true });
        console.log(`Error screenshot saved: ${screenshotPath}`);
      }

      // Log immediate results
      console.log(`[${pageConfig.name}] Errors: ${result.summary.errors}, Warnings: ${result.summary.warnings}`);

      // Soft assertion - we capture all, don't fail the test
      expect(result.summary.errors, `Page ${pageConfig.name} has console errors`).toBeLessThanOrEqual(10);
    });
  }
});

// Export for use in other scripts
export { captureConsoleLogs, ConsoleCaptureResult };
