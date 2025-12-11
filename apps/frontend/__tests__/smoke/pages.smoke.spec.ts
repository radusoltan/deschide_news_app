/**
 * Smoke Tests - Pages
 * Quick tests to verify that critical pages load successfully
 * These tests should run fast and catch major regressions
 */

import { test, expect } from '@playwright/test';

test.describe('Pages Load Smoke Tests', () => {
  test.describe.configure({ mode: 'parallel' });

  const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:3005';
  const LOAD_TIMEOUT = 10000; // 10 seconds max per page

  test.describe('Homepage - All Locales', () => {
    test('Homepage (Romanian) loads successfully', async ({ page }) => {
      const response = await page.goto(`${BASE_URL}/ro`, {
        waitUntil: 'domcontentloaded',
        timeout: LOAD_TIMEOUT,
      });

      // Verify successful response
      expect([200, 304]).toContain(response?.status());

      // Verify essential page structure
      await expect(page.locator('header')).toBeVisible({ timeout: 5000 });
      await expect(page.locator('main')).toBeVisible({ timeout: 5000 });

      // Verify page has content
      const mainContent = page.locator('main');
      await expect(mainContent).not.toBeEmpty();
    });

    test('Homepage (English) loads successfully', async ({ page }) => {
      const response = await page.goto(`${BASE_URL}/en`, {
        waitUntil: 'domcontentloaded',
        timeout: LOAD_TIMEOUT,
      });

      expect([200, 304]).toContain(response?.status());
      await expect(page.locator('header')).toBeVisible({ timeout: 5000 });
      await expect(page.locator('main')).toBeVisible({ timeout: 5000 });
    });

    test('Homepage (Russian) loads successfully', async ({ page }) => {
      const response = await page.goto(`${BASE_URL}/ru`, {
        waitUntil: 'domcontentloaded',
        timeout: LOAD_TIMEOUT,
      });

      expect([200, 304]).toContain(response?.status());
      await expect(page.locator('header')).toBeVisible({ timeout: 5000 });
      await expect(page.locator('main')).toBeVisible({ timeout: 5000 });
    });

    test('Default locale (/) redirects or loads Romanian', async ({ page }) => {
      const response = await page.goto(`${BASE_URL}/`, {
        waitUntil: 'domcontentloaded',
        timeout: LOAD_TIMEOUT,
      });

      // Should load successfully regardless of redirect behavior
      expect([200, 301, 302, 304, 307, 308]).toContain(response?.status());
      await expect(page.locator('main')).toBeVisible({ timeout: 5000 });
    });
  });

  test.describe('Category Pages', () => {
    test('Category page loads (politica)', async ({ page }) => {
      const response = await page.goto(`${BASE_URL}/ro/category/politica`, {
        waitUntil: 'domcontentloaded',
        timeout: LOAD_TIMEOUT,
      });

      // Accept 200 (success) or 404 (if no category exists in test DB)
      expect([200, 304, 404]).toContain(response?.status());

      if (response?.status() === 200 || response?.status() === 304) {
        await expect(page.locator('header')).toBeVisible({ timeout: 5000 });
        await expect(page.locator('main')).toBeVisible({ timeout: 5000 });
      }
    });

    test('Category page handles non-existent category gracefully', async ({ page }) => {
      const response = await page.goto(`${BASE_URL}/ro/category/nonexistent-category-xyz`, {
        waitUntil: 'domcontentloaded',
        timeout: LOAD_TIMEOUT,
      });

      // Should return 404 or redirect
      expect([200, 301, 302, 304, 404]).toContain(response?.status());

      // Page structure should still be intact (error page)
      await expect(page.locator('body')).toBeVisible();
    });
  });

  test.describe('Archive Page', () => {
    test('Archive page loads', async ({ page }) => {
      const response = await page.goto(`${BASE_URL}/ro/archive`, {
        waitUntil: 'domcontentloaded',
        timeout: LOAD_TIMEOUT,
      });

      // Accept 200 or 404 (depending on implementation)
      expect([200, 304, 404]).toContain(response?.status());

      if (response?.status() === 200 || response?.status() === 304) {
        await expect(page.locator('header')).toBeVisible({ timeout: 5000 });
        // Use .first() since archive page may have multiple main elements
        await expect(page.locator('main').first()).toBeVisible({ timeout: 5000 });
      }
    });
  });

  test.describe('Authentication Pages', () => {
    test('Login page loads', async ({ page }) => {
      const response = await page.goto(`${BASE_URL}/ro/login`, {
        waitUntil: 'domcontentloaded',
        timeout: LOAD_TIMEOUT,
      });

      expect([200, 304]).toContain(response?.status());
      await expect(page.locator('body')).toBeVisible();

      // Look for login form elements (if present)
      const loginForm = page.locator('form, input[type="email"], input[type="password"]');
      if (await loginForm.count() > 0) {
        await expect(loginForm.first()).toBeVisible({ timeout: 5000 });
      }
    });
  });

  test.describe('Error Pages', () => {
    test('404 page displays correctly', async ({ page }) => {
      const response = await page.goto(`${BASE_URL}/ro/nonexistent-page-xyz-abc-123`, {
        waitUntil: 'domcontentloaded',
        timeout: LOAD_TIMEOUT,
      });

      // Should return 404
      expect(response?.status()).toBe(404);

      // Page should still render (not blank/crashed)
      await expect(page.locator('body')).toBeVisible();

      // Should have some content (error message)
      const bodyText = await page.textContent('body');
      expect(bodyText?.length).toBeGreaterThan(0);
    });
  });

  test.describe('Page Load Performance', () => {
    test('Pages load within acceptable time (<10s)', async ({ page }) => {
      const pages = [
        `${BASE_URL}/ro`,
        `${BASE_URL}/en`,
        `${BASE_URL}/ru`,
      ];

      for (const url of pages) {
        const startTime = Date.now();

        await page.goto(url, {
          waitUntil: 'domcontentloaded',
          timeout: LOAD_TIMEOUT,
        });

        const loadTime = Date.now() - startTime;

        // Should load within 10 seconds
        expect(loadTime).toBeLessThan(LOAD_TIMEOUT);
      }
    });

    test('No JavaScript errors on page load', async ({ page }) => {
      const errors: string[] = [];

      page.on('console', (msg) => {
        if (msg.type() === 'error') {
          errors.push(msg.text());
        }
      });

      page.on('pageerror', (error) => {
        errors.push(error.message);
      });

      await page.goto(`${BASE_URL}/ro`, {
        waitUntil: 'domcontentloaded',
        timeout: LOAD_TIMEOUT,
      });

      // Filter out known non-critical errors
      const criticalErrors = errors.filter(
        (err) =>
          !err.includes('favicon') &&
          !err.includes('404') &&
          !err.includes('Failed to load resource')
      );

      // Should have no critical JavaScript errors
      expect(criticalErrors.length).toBe(0);
    });
  });

  test.describe('Basic SEO Elements', () => {
    test('Homepage has title and meta description', async ({ page }) => {
      await page.goto(`${BASE_URL}/ro`, {
        waitUntil: 'domcontentloaded',
        timeout: LOAD_TIMEOUT,
      });

      // Check title
      const title = await page.title();
      expect(title.length).toBeGreaterThan(0);
      expect(title).not.toBe('');

      // Check meta description
      const metaDescription = page.locator('meta[name="description"]');
      if (await metaDescription.count() > 0) {
        const content = await metaDescription.getAttribute('content');
        expect(content?.length).toBeGreaterThan(0);
      }
    });

    test('Pages have proper HTML structure', async ({ page }) => {
      await page.goto(`${BASE_URL}/ro`, {
        waitUntil: 'domcontentloaded',
        timeout: LOAD_TIMEOUT,
      });

      // Verify basic HTML structure
      await expect(page.locator('html')).toBeVisible();
      await expect(page.locator('head')).toHaveCount(1);
      await expect(page.locator('body')).toHaveCount(1);
    });
  });
});
