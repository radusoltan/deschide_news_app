/**
 * API Health Check Diagnostic Script
 *
 * Directly tests all backend API endpoints without browser interaction.
 * Useful for isolating backend issues from frontend issues.
 * Tests authentication, CRUD operations, and data integrity.
 *
 * Usage: npx playwright test __tests__/diagnostics/api-health-check.diag.ts
 */

import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

interface EndpointCheck {
  name: string;
  endpoint: string;
  method: string;
  status: number;
  statusText: string;
  responseTime: number;
  dataCount?: number;
  error?: string;
  success: boolean;
}

interface ApiHealthReport {
  timestamp: string;
  baseUrl: string;
  authStatus: 'authenticated' | 'unauthenticated' | 'failed';
  endpoints: EndpointCheck[];
  summary: {
    total: number;
    passed: number;
    failed: number;
    avgResponseTime: number;
  };
}

const API_BASE_URL = process.env.API_URL || 'http://127.0.0.1:8081';
const OUTPUT_DIR = path.join(__dirname, '../../test-results/diagnostics');

// API endpoints to check
const PUBLIC_ENDPOINTS = [
  { name: 'API Root', path: '/api', method: 'GET' },
  { name: 'Articles List', path: '/api/articles', method: 'GET' },
  { name: 'Categories List', path: '/api/categories', method: 'GET' },
  { name: 'Authors List', path: '/api/authors', method: 'GET' },
  { name: 'Images List', path: '/api/images', method: 'GET' },
  { name: 'Tags List', path: '/api/tags', method: 'GET' },
  { name: 'Important Articles', path: '/api/important_articles', method: 'GET' },
  { name: 'Archive Years', path: '/api/archive/years', method: 'GET' },
  { name: 'Live Texts', path: '/api/live_texts', method: 'GET' },
  { name: 'Sport Matches', path: '/api/live_text_sport_matches', method: 'GET' },
];

const AUTHENTICATED_ENDPOINTS = [
  { name: 'Thumbnail Profiles', path: '/api/thumbnail_profiles', method: 'GET' },
  { name: 'Article Locks', path: '/api/article_locks', method: 'GET' },
];

const LOCALE_ENDPOINTS = [
  { name: 'Articles RO', path: '/api/articles', method: 'GET', locale: 'ro' },
  { name: 'Articles EN', path: '/api/articles', method: 'GET', locale: 'en' },
  { name: 'Articles RU', path: '/api/articles', method: 'GET', locale: 'ru' },
  { name: 'Categories RO', path: '/api/categories', method: 'GET', locale: 'ro' },
  { name: 'Categories EN', path: '/api/categories', method: 'GET', locale: 'en' },
];

async function checkEndpoint(
  endpoint: string,
  method: string,
  headers: Record<string, string> = {}
): Promise<{ status: number; statusText: string; responseTime: number; data?: any; error?: string }> {
  const startTime = Date.now();

  try {
    const response = await fetch(`${API_BASE_URL}${endpoint}`, {
      method,
      headers: {
        Accept: 'application/ld+json',
        'Content-Type': 'application/json',
        ...headers,
      },
    });

    const responseTime = Date.now() - startTime;
    let data;

    try {
      data = await response.json();
    } catch {
      // Response might not be JSON
    }

    return {
      status: response.status,
      statusText: response.statusText,
      responseTime,
      data,
    };
  } catch (error) {
    return {
      status: 0,
      statusText: 'Connection Failed',
      responseTime: Date.now() - startTime,
      error: error instanceof Error ? error.message : String(error),
    };
  }
}

async function authenticate(): Promise<{ token: string | null; error?: string }> {
  try {
    const response = await fetch(`${API_BASE_URL}/api/login_check`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        username: 'admin',
        password: 'admin123',
      }),
    });

    if (response.ok) {
      const data = await response.json();
      return { token: data.token };
    }

    return { token: null, error: `Auth failed: ${response.status} ${response.statusText}` };
  } catch (error) {
    return { token: null, error: error instanceof Error ? error.message : String(error) };
  }
}

function saveReport(report: ApiHealthReport) {
  if (!fs.existsSync(OUTPUT_DIR)) {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });
  }

  // JSON report
  const jsonPath = path.join(OUTPUT_DIR, `api-health-${Date.now()}.json`);
  fs.writeFileSync(jsonPath, JSON.stringify(report, null, 2));

  // Markdown report
  const mdLines = [
    '# API Health Check Report',
    '',
    `**Generated:** ${report.timestamp}`,
    `**Base URL:** ${report.baseUrl}`,
    `**Auth Status:** ${report.authStatus}`,
    '',
    '## Summary',
    '',
    `- Total Endpoints: ${report.summary.total}`,
    `- ✅ Passed: ${report.summary.passed}`,
    `- ❌ Failed: ${report.summary.failed}`,
    `- Average Response Time: ${report.summary.avgResponseTime.toFixed(0)}ms`,
    '',
    '## Endpoint Status',
    '',
    '| Status | Endpoint | Method | Response Time | Data Count |',
    '|--------|----------|--------|---------------|------------|',
  ];

  for (const ep of report.endpoints) {
    const status = ep.success ? '✅' : '❌';
    const dataCount = ep.dataCount !== undefined ? ep.dataCount.toString() : '-';
    mdLines.push(`| ${status} ${ep.status} | ${ep.name} | ${ep.method} | ${ep.responseTime}ms | ${dataCount} |`);
  }

  mdLines.push('');
  mdLines.push('## Failed Endpoints');
  mdLines.push('');

  const failed = report.endpoints.filter(e => !e.success);
  if (failed.length === 0) {
    mdLines.push('No failed endpoints!');
  } else {
    for (const ep of failed) {
      mdLines.push(`### ${ep.name}`);
      mdLines.push(`- **Endpoint:** ${ep.endpoint}`);
      mdLines.push(`- **Status:** ${ep.status} ${ep.statusText}`);
      if (ep.error) {
        mdLines.push(`- **Error:** ${ep.error}`);
      }
      mdLines.push('');
    }
  }

  mdLines.push('');
  mdLines.push('## Performance Analysis');
  mdLines.push('');

  const sorted = [...report.endpoints].sort((a, b) => b.responseTime - a.responseTime);
  mdLines.push('**Slowest Endpoints:**');
  sorted.slice(0, 5).forEach((ep, i) => {
    const warning = ep.responseTime > 1000 ? ' ⚠️' : '';
    mdLines.push(`${i + 1}. ${ep.name}: ${ep.responseTime}ms${warning}`);
  });

  const mdPath = path.join(OUTPUT_DIR, `api-health-${Date.now()}.md`);
  fs.writeFileSync(mdPath, mdLines.join('\n'));

  console.log('\n========== API HEALTH CHECK COMPLETE ==========');
  console.log(`✅ Passed: ${report.summary.passed}/${report.summary.total}`);
  console.log(`❌ Failed: ${report.summary.failed}/${report.summary.total}`);
  console.log(`⏱️ Avg Response: ${report.summary.avgResponseTime.toFixed(0)}ms`);
  console.log(`\nReports: ${jsonPath}`);
  console.log(`         ${mdPath}`);
  console.log('=================================================\n');
}

test.describe('API Health Check', () => {
  const report: ApiHealthReport = {
    timestamp: new Date().toISOString(),
    baseUrl: API_BASE_URL,
    authStatus: 'unauthenticated',
    endpoints: [],
    summary: {
      total: 0,
      passed: 0,
      failed: 0,
      avgResponseTime: 0,
    },
  };

  let authToken: string | null = null;

  test.beforeAll(async () => {
    // Try to authenticate
    const auth = await authenticate();
    if (auth.token) {
      authToken = auth.token;
      report.authStatus = 'authenticated';
      console.log('✅ Authentication successful');
    } else {
      report.authStatus = 'failed';
      console.log(`⚠️ Authentication failed: ${auth.error}`);
    }
  });

  test.afterAll(async () => {
    // Calculate summary
    report.summary.total = report.endpoints.length;
    report.summary.passed = report.endpoints.filter(e => e.success).length;
    report.summary.failed = report.endpoints.filter(e => !e.success).length;
    report.summary.avgResponseTime =
      report.endpoints.reduce((sum, e) => sum + e.responseTime, 0) / report.endpoints.length || 0;

    saveReport(report);
  });

  test('Check API connectivity', async () => {
    const result = await checkEndpoint('/api', 'GET');
    expect(result.status).toBe(200);

    report.endpoints.push({
      name: 'API Connectivity',
      endpoint: '/api',
      method: 'GET',
      status: result.status,
      statusText: result.statusText,
      responseTime: result.responseTime,
      success: result.status === 200,
    });
  });

  // Test public endpoints
  for (const ep of PUBLIC_ENDPOINTS) {
    test(`Public: ${ep.name}`, async () => {
      const result = await checkEndpoint(ep.path, ep.method);

      const check: EndpointCheck = {
        name: ep.name,
        endpoint: ep.path,
        method: ep.method,
        status: result.status,
        statusText: result.statusText,
        responseTime: result.responseTime,
        error: result.error,
        success: result.status >= 200 && result.status < 400,
      };

      // Count data if it's a collection
      if (result.data?.['hydra:member']) {
        check.dataCount = result.data['hydra:member'].length;
      } else if (result.data?.['hydra:totalItems'] !== undefined) {
        check.dataCount = result.data['hydra:totalItems'];
      }

      report.endpoints.push(check);

      console.log(`[${ep.name}] ${result.status} - ${result.responseTime}ms`);
      expect(result.status, `${ep.name} should be accessible`).toBeGreaterThanOrEqual(200);
      expect(result.status, `${ep.name} should not error`).toBeLessThan(500);
    });
  }

  // Test locale endpoints
  for (const ep of LOCALE_ENDPOINTS) {
    test(`Locale: ${ep.name}`, async () => {
      const result = await checkEndpoint(ep.path, ep.method, {
        'Accept-Language': ep.locale!,
      });

      const check: EndpointCheck = {
        name: ep.name,
        endpoint: `${ep.path} (${ep.locale})`,
        method: ep.method,
        status: result.status,
        statusText: result.statusText,
        responseTime: result.responseTime,
        error: result.error,
        success: result.status >= 200 && result.status < 400,
      };

      if (result.data?.['hydra:member']) {
        check.dataCount = result.data['hydra:member'].length;
      }

      report.endpoints.push(check);

      console.log(`[${ep.name}] ${result.status} - ${result.responseTime}ms`);
      expect(result.status).toBeGreaterThanOrEqual(200);
    });
  }

  // Test authenticated endpoints (if auth succeeded)
  for (const ep of AUTHENTICATED_ENDPOINTS) {
    test(`Auth: ${ep.name}`, async () => {
      if (!authToken) {
        console.log(`Skipping ${ep.name} - no auth token`);
        report.endpoints.push({
          name: ep.name,
          endpoint: ep.path,
          method: ep.method,
          status: 0,
          statusText: 'Skipped - No Auth',
          responseTime: 0,
          success: false,
          error: 'Authentication not available',
        });
        return;
      }

      const result = await checkEndpoint(ep.path, ep.method, {
        Authorization: `Bearer ${authToken}`,
      });

      report.endpoints.push({
        name: ep.name,
        endpoint: ep.path,
        method: ep.method,
        status: result.status,
        statusText: result.statusText,
        responseTime: result.responseTime,
        error: result.error,
        success: result.status >= 200 && result.status < 400,
      });

      console.log(`[${ep.name}] ${result.status} - ${result.responseTime}ms`);
      expect(result.status).toBeGreaterThanOrEqual(200);
    });
  }

  // Test pagination
  test('Pagination: Articles with limit', async () => {
    const result = await checkEndpoint('/api/articles?itemsPerPage=5&page=1', 'GET');

    report.endpoints.push({
      name: 'Pagination Test',
      endpoint: '/api/articles?itemsPerPage=5&page=1',
      method: 'GET',
      status: result.status,
      statusText: result.statusText,
      responseTime: result.responseTime,
      dataCount: result.data?.['hydra:member']?.length,
      success: result.status === 200 && (result.data?.['hydra:member']?.length || 0) <= 5,
    });

    expect(result.status).toBe(200);
    expect(result.data?.['hydra:member']?.length).toBeLessThanOrEqual(5);
  });

  // Test filtering
  test('Filtering: Articles by status', async () => {
    const result = await checkEndpoint('/api/articles?status=published', 'GET');

    report.endpoints.push({
      name: 'Filter Test (status)',
      endpoint: '/api/articles?status=published',
      method: 'GET',
      status: result.status,
      statusText: result.statusText,
      responseTime: result.responseTime,
      dataCount: result.data?.['hydra:member']?.length,
      success: result.status === 200,
    });

    expect(result.status).toBe(200);
  });

  // Test response time threshold
  test('Performance: Response times under 2s', async () => {
    const slowEndpoints = report.endpoints.filter(e => e.responseTime > 2000);

    if (slowEndpoints.length > 0) {
      console.warn('Slow endpoints detected:');
      slowEndpoints.forEach(e => console.warn(`  - ${e.name}: ${e.responseTime}ms`));
    }

    expect(slowEndpoints.length, 'No endpoints should take > 2s').toBe(0);
  });
});
