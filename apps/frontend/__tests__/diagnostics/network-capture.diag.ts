/**
 * Network Capture Diagnostic Script
 *
 * This script captures all network requests and responses during page interaction.
 * Specifically designed to capture API calls and their status codes.
 * Used by the automated testing agent to diagnose issues without direct Network tab access.
 *
 * Usage: npx playwright test __tests__/diagnostics/network-capture.diag.ts --headed
 */

import { test, expect, Page, Request, Response } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

interface NetworkRequest {
  url: string;
  method: string;
  resourceType: string;
  timestamp: string;
  headers?: Record<string, string>;
  postData?: string;
}

interface NetworkResponse {
  url: string;
  method: string;
  status: number;
  statusText: string;
  timing?: number;
  headers?: Record<string, string>;
  body?: string;
  timestamp: string;
}

interface NetworkCaptureResult {
  timestamp: string;
  pageUrl: string;
  requests: NetworkRequest[];
  responses: NetworkResponse[];
  failedRequests: NetworkRequest[];
  summary: {
    total: number;
    successful: number;
    clientErrors: number; // 4xx
    serverErrors: number; // 5xx
    failed: number;
    apiCalls: number;
    apiErrors: number;
  };
}

// API patterns to specifically track
const API_PATTERNS = [
  /\/api\//,
  /127\.0\.0\.1:8081/,
  /localhost:8081/,
  /api\.deschide/,
];

// Configure the pages/actions to test
const TEST_SCENARIOS = [
  { name: 'Homepage Load', url: '/ro', actions: [] },
  { name: 'Admin Dashboard', url: '/ro/admin/dashboard', requiresAuth: true, actions: [] },
  {
    name: 'Admin Articles List',
    url: '/ro/admin/articles',
    requiresAuth: true,
    actions: [
      { type: 'wait', duration: 3000 },
    ],
  },
  {
    name: 'Admin Create Article Form',
    url: '/ro/admin/articles/new',
    requiresAuth: true,
    actions: [
      { type: 'wait', duration: 2000 },
    ],
  },
  {
    name: 'Admin Images List',
    url: '/ro/admin/images',
    requiresAuth: true,
    actions: [
      { type: 'wait', duration: 3000 },
    ],
  },
  {
    name: 'Admin LiveText List',
    url: '/ro/admin/live-text',
    requiresAuth: true,
    actions: [
      { type: 'wait', duration: 3000 },
    ],
  },
  { name: 'Archive 2024', url: '/ro/archive/2024', actions: [] },
  { name: 'Archive 2023/06', url: '/ro/archive/2023/06', actions: [] },
];

// Output directory for reports
const OUTPUT_DIR = path.join(__dirname, '../../test-results/diagnostics');

function isApiCall(url: string): boolean {
  return API_PATTERNS.some(pattern => pattern.test(url));
}

async function captureNetworkTraffic(
  page: Page,
  pageUrl: string,
  actions: { type: string; duration?: number; selector?: string }[] = []
): Promise<NetworkCaptureResult> {
  const requests: NetworkRequest[] = [];
  const responses: NetworkResponse[] = [];
  const failedRequests: NetworkRequest[] = [];

  // Listen to requests
  page.on('request', (request: Request) => {
    const reqData: NetworkRequest = {
      url: request.url(),
      method: request.method(),
      resourceType: request.resourceType(),
      timestamp: new Date().toISOString(),
    };

    // Capture headers for API calls
    if (isApiCall(request.url())) {
      reqData.headers = request.headers();
      if (request.postData()) {
        reqData.postData = request.postData()?.substring(0, 1000); // Limit size
      }
    }

    requests.push(reqData);
  });

  // Listen to responses
  page.on('response', async (response: Response) => {
    const resData: NetworkResponse = {
      url: response.url(),
      method: response.request().method(),
      status: response.status(),
      statusText: response.statusText(),
      timestamp: new Date().toISOString(),
    };

    // Capture more details for API calls
    if (isApiCall(response.url())) {
      resData.headers = response.headers();
      try {
        const timing = response.request().timing();
        resData.timing = timing.responseEnd - timing.requestStart;
      } catch {
        // Timing not available
      }

      // Capture error response bodies
      if (response.status() >= 400) {
        try {
          resData.body = (await response.text()).substring(0, 2000);
        } catch {
          // Body not available
        }
      }
    }

    responses.push(resData);
  });

  // Listen to failed requests
  page.on('requestfailed', (request: Request) => {
    failedRequests.push({
      url: request.url(),
      method: request.method(),
      resourceType: request.resourceType(),
      timestamp: new Date().toISOString(),
    });
  });

  // Navigate to page
  try {
    await page.goto(pageUrl, { waitUntil: 'networkidle', timeout: 30000 });
  } catch (error) {
    console.log(`Navigation warning: ${error instanceof Error ? error.message : String(error)}`);
  }

  // Execute additional actions
  for (const action of actions) {
    if (action.type === 'wait' && action.duration) {
      await page.waitForTimeout(action.duration);
    } else if (action.type === 'click' && action.selector) {
      try {
        await page.click(action.selector);
        await page.waitForTimeout(1000);
      } catch {
        console.log(`Could not click: ${action.selector}`);
      }
    }
  }

  // Wait for any pending requests
  await page.waitForTimeout(2000);

  // Calculate summary
  const summary = {
    total: responses.length,
    successful: responses.filter(r => r.status >= 200 && r.status < 400).length,
    clientErrors: responses.filter(r => r.status >= 400 && r.status < 500).length,
    serverErrors: responses.filter(r => r.status >= 500).length,
    failed: failedRequests.length,
    apiCalls: responses.filter(r => isApiCall(r.url)).length,
    apiErrors: responses.filter(r => isApiCall(r.url) && r.status >= 400).length,
  };

  return {
    timestamp: new Date().toISOString(),
    pageUrl,
    requests,
    responses,
    failedRequests,
    summary,
  };
}

function saveReport(results: Record<string, NetworkCaptureResult>) {
  // Ensure output directory exists
  if (!fs.existsSync(OUTPUT_DIR)) {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });
  }

  const reportPath = path.join(OUTPUT_DIR, `network-capture-${Date.now()}.json`);
  fs.writeFileSync(reportPath, JSON.stringify(results, null, 2));
  console.log(`Report saved to: ${reportPath}`);

  // Generate summary
  let totalApiErrors = 0;
  let totalServerErrors = 0;
  const pagesSummary: string[] = [];
  const apiErrorDetails: string[] = [];

  for (const [pageName, result] of Object.entries(results)) {
    totalApiErrors += result.summary.apiErrors;
    totalServerErrors += result.summary.serverErrors;

    const status = result.summary.serverErrors > 0 ? '❌' :
                   result.summary.apiErrors > 0 ? '⚠️' : '✅';
    pagesSummary.push(`${status} ${pageName}: ${result.summary.apiCalls} API calls, ${result.summary.apiErrors} errors`);

    // Collect API error details
    result.responses
      .filter(r => isApiCall(r.url) && r.status >= 400)
      .forEach(r => {
        apiErrorDetails.push(`[${pageName}] ${r.status} ${r.method || 'GET'} ${r.url}`);
        if (r.body) {
          apiErrorDetails.push(`  Response: ${r.body.substring(0, 200)}...`);
        }
      });
  }

  console.log('\n========== NETWORK CAPTURE SUMMARY ==========');
  console.log(`Total API Errors: ${totalApiErrors}`);
  console.log(`Total Server Errors (5xx): ${totalServerErrors}`);
  console.log('\nPer-page summary:');
  pagesSummary.forEach(line => console.log(line));
  console.log('==============================================\n');

  // Save human-readable summary
  const summaryPath = path.join(OUTPUT_DIR, `network-summary-${Date.now()}.txt`);
  fs.writeFileSync(summaryPath, [
    'NETWORK CAPTURE DIAGNOSTIC REPORT',
    `Generated: ${new Date().toISOString()}`,
    '',
    `Total API Errors: ${totalApiErrors}`,
    `Total Server Errors (5xx): ${totalServerErrors}`,
    '',
    'Per-page summary:',
    ...pagesSummary,
    '',
    'API Error Details:',
    ...apiErrorDetails,
    '',
    'HTTP Status Code Reference:',
    '  200 OK - Success',
    '  201 Created - Resource created',
    '  204 No Content - Success (no body)',
    '  400 Bad Request - Invalid request data',
    '  401 Unauthorized - Authentication required',
    '  403 Forbidden - Access denied',
    '  404 Not Found - Resource not found',
    '  422 Unprocessable Entity - Validation error',
    '  500 Internal Server Error - Server error',
    '  502 Bad Gateway - Backend server error',
    '  503 Service Unavailable - Server overloaded',
  ].join('\n'));
  console.log(`Summary saved to: ${summaryPath}`);
}

test.describe('Network Capture Diagnostic', () => {
  const results: Record<string, NetworkCaptureResult> = {};

  test.afterAll(async () => {
    saveReport(results);
  });

  for (const scenario of TEST_SCENARIOS) {
    test(`Capture network for: ${scenario.name}`, async ({ page }) => {
      const baseUrl = process.env.BASE_URL || 'http://localhost:3005';
      const fullUrl = `${baseUrl}${scenario.url}`;

      // If scenario requires auth, login first
      if (scenario.requiresAuth) {
        await page.goto(`${baseUrl}/ro/admin`);
        try {
          await page.fill('input[name="email"], input[type="email"]', 'admin@deschide.md');
          await page.fill('input[name="password"], input[type="password"]', 'admin123');
          await page.click('button[type="submit"]');
          await page.waitForTimeout(2000);
        } catch {
          console.log(`Auth might already be set or login form not found for ${scenario.name}`);
        }
      }

      const result = await captureNetworkTraffic(page, fullUrl, scenario.actions);
      results[scenario.name] = result;

      // Take screenshot if there are API errors
      if (result.summary.apiErrors > 0 || result.summary.serverErrors > 0) {
        const screenshotPath = path.join(
          OUTPUT_DIR,
          `network-error-${scenario.name.replace(/\s+/g, '-')}-${Date.now()}.png`
        );
        await page.screenshot({ path: screenshotPath, fullPage: true });
        console.log(`Error screenshot saved: ${screenshotPath}`);
      }

      // Log immediate results
      console.log(`[${scenario.name}] API calls: ${result.summary.apiCalls}, Errors: ${result.summary.apiErrors}`);

      // Soft assertion - capture all but flag issues
      expect(
        result.summary.serverErrors,
        `Page ${scenario.name} has server errors (5xx)`
      ).toBe(0);
    });
  }
});

// Export for use in other scripts
export { captureNetworkTraffic, NetworkCaptureResult, isApiCall };
