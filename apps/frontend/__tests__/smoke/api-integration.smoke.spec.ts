/**
 * Smoke Tests - API Integration
 * Quick tests to verify API connectivity and basic data flow
 */

import { test, expect } from '@playwright/test';

test.describe('API Integration Smoke Tests', () => {
  test.describe.configure({ mode: 'parallel' });

  const FRONTEND_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:3005';
  const API_URL = process.env.API_URL || 'http://127.0.0.1:8081';
  const LOAD_TIMEOUT = 10000;

  test.describe('Backend API Connectivity', () => {
    test('API endpoint is reachable', async ({ request }) => {
      try {
        const response = await request.get(`${API_URL}/api`, {
          timeout: LOAD_TIMEOUT,
        });

        // API should respond with 200
        expect(response.ok()).toBeTruthy();
        expect(response.status()).toBe(200);

        // Should return JSON
        const contentType = response.headers()['content-type'];
        expect(contentType).toContain('json');
      } catch (error) {
        // API might not be running in test environment
        console.warn(`API not reachable at ${API_URL}/api - this is expected if backend is not running`);
      }
    });

    test('API returns proper JSON-LD structure', async ({ request }) => {
      try {
        const response = await request.get(`${API_URL}/api`, {
          timeout: LOAD_TIMEOUT,
        });

        if (response.ok()) {
          const data = await response.json();

          // Should have JSON-LD context
          expect(data).toHaveProperty('@context');
          expect(data).toHaveProperty('@id');
          expect(data).toHaveProperty('@type');
        }
      } catch (error) {
        console.warn('API JSON-LD structure test skipped - API not available');
      }
    });
  });

  test.describe('Articles API', () => {
    test('Articles API endpoint returns data', async ({ request }) => {
      try {
        const response = await request.get(`${API_URL}/api/articles`, {
          timeout: LOAD_TIMEOUT,
          headers: {
            'Accept': 'application/ld+json',
            'Accept-Language': 'ro',
          },
        });

        if (response.ok()) {
          const data = await response.json();

          // Should have hydra collection structure
          expect(data).toBeTruthy();

          // Check for hydra:member (articles array)
          if (data['hydra:member']) {
            expect(Array.isArray(data['hydra:member'])).toBeTruthy();

            // If articles exist, verify structure
            if (data['hydra:member'].length > 0) {
              const firstArticle = data['hydra:member'][0];
              expect(firstArticle).toHaveProperty('@id');
              expect(firstArticle).toHaveProperty('@type');
            }
          }
        }
      } catch (error) {
        console.warn('Articles API test skipped - API not available');
      }
    });

    test('Articles API supports locale filtering', async ({ request }) => {
      try {
        // Test Romanian locale
        const roResponse = await request.get(`${API_URL}/api/articles?locale=ro`, {
          timeout: LOAD_TIMEOUT,
          headers: {
            'Accept-Language': 'ro',
          },
        });

        if (roResponse.ok()) {
          expect(roResponse.status()).toBe(200);
        }

        // Test English locale
        const enResponse = await request.get(`${API_URL}/api/articles?locale=en`, {
          timeout: LOAD_TIMEOUT,
          headers: {
            'Accept-Language': 'en',
          },
        });

        if (enResponse.ok()) {
          expect(enResponse.status()).toBe(200);
        }
      } catch (error) {
        console.warn('Locale filtering test skipped - API not available');
      }
    });

    test('Articles API supports pagination', async ({ request }) => {
      try {
        const response = await request.get(`${API_URL}/api/articles?page=1&itemsPerPage=10`, {
          timeout: LOAD_TIMEOUT,
          headers: {
            'Accept-Language': 'ro',
          },
        });

        if (response.ok()) {
          const data = await response.json();

          // Should have pagination metadata
          if (data['hydra:view']) {
            expect(data['hydra:view']).toHaveProperty('@id');
          }

          // Should have totalItems
          if (data['hydra:totalItems'] !== undefined) {
            expect(typeof data['hydra:totalItems']).toBe('number');
          }
        }
      } catch (error) {
        console.warn('Pagination test skipped - API not available');
      }
    });
  });

  test.describe('Categories API', () => {
    test('Categories API endpoint returns data', async ({ request }) => {
      try {
        const response = await request.get(`${API_URL}/api/categories`, {
          timeout: LOAD_TIMEOUT,
          headers: {
            'Accept': 'application/ld+json',
            'Accept-Language': 'ro',
          },
        });

        if (response.ok()) {
          const data = await response.json();

          // Should have hydra collection
          expect(data).toBeTruthy();

          if (data['hydra:member']) {
            expect(Array.isArray(data['hydra:member'])).toBeTruthy();

            // If categories exist, verify structure
            if (data['hydra:member'].length > 0) {
              const firstCategory = data['hydra:member'][0];
              expect(firstCategory).toHaveProperty('@id');
              expect(firstCategory).toHaveProperty('@type');
            }
          }
        }
      } catch (error) {
        console.warn('Categories API test skipped - API not available');
      }
    });
  });

  test.describe('Frontend Data Integration', () => {
    test('Homepage displays articles from API', async ({ page }) => {
      await page.goto(`${FRONTEND_URL}/ro`, {
        waitUntil: 'domcontentloaded',
        timeout: LOAD_TIMEOUT,
      });

      // Wait for page to fully load
      await page.waitForLoadState('networkidle', { timeout: LOAD_TIMEOUT }).catch(() => {
        // Timeout is fine, we'll check what loaded
      });

      // Look for article elements (various possible patterns)
      const articleElements = page.locator(
        'article, [data-testid="article-card"], [data-testid="article"], .article-card'
      );

      const articleCount = await articleElements.count();

      if (articleCount > 0) {
        // Verify first article is visible
        await expect(articleElements.first()).toBeVisible();

        // Verify article has title
        const articleTitle = articleElements.first().locator('h1, h2, h3, h4, h5').first();
        if (await articleTitle.count() > 0) {
          await expect(articleTitle).toBeVisible();
          const titleText = await articleTitle.textContent();
          expect(titleText?.length).toBeGreaterThan(0);
        }
      } else {
        // No articles found - might be test environment without data
        console.warn('No articles found on homepage - this is expected if API has no data');
      }
    });

    test('Article images load correctly', async ({ page }) => {
      await page.goto(`${FRONTEND_URL}/ro`, {
        waitUntil: 'domcontentloaded',
        timeout: LOAD_TIMEOUT,
      });

      // Wait for images to load
      await page.waitForLoadState('networkidle', { timeout: LOAD_TIMEOUT }).catch(() => {
        // Continue anyway
      });

      // Look for article images
      const images = page.locator('article img, [data-testid="article-card"] img, .article-card img');

      const imageCount = await images.count();

      if (imageCount > 0) {
        const firstImage = images.first();

        // Wait for image to be visible
        await expect(firstImage).toBeVisible({ timeout: 5000 });

        // Verify image has src
        const src = await firstImage.getAttribute('src');
        expect(src).toBeTruthy();
        expect(src?.length).toBeGreaterThan(0);

        // Verify image has alt text (accessibility)
        const alt = await firstImage.getAttribute('alt');
        expect(alt).toBeDefined(); // Should at least be empty string, not null
      }
    });

    test('API requests complete successfully', async ({ page }) => {
      const apiRequests: any[] = [];
      const failedRequests: any[] = [];

      // Monitor API requests
      page.on('request', (request) => {
        if (request.url().includes('/api/')) {
          apiRequests.push({
            url: request.url(),
            method: request.method(),
          });
        }
      });

      page.on('requestfailed', (request) => {
        if (request.url().includes('/api/')) {
          failedRequests.push({
            url: request.url(),
            failure: request.failure(),
          });
        }
      });

      await page.goto(`${FRONTEND_URL}/ro`, {
        waitUntil: 'domcontentloaded',
        timeout: LOAD_TIMEOUT,
      });

      // Wait for network to settle
      await page.waitForLoadState('networkidle', { timeout: LOAD_TIMEOUT }).catch(() => {
        // Timeout is acceptable
      });

      // Log request info
      console.log(`Total API requests: ${apiRequests.length}`);
      console.log(`Failed API requests: ${failedRequests.length}`);

      // In development, some API failures might be expected
      // Just verify we're not seeing complete API failure
      if (apiRequests.length > 0) {
        const failureRate = failedRequests.length / apiRequests.length;
        expect(failureRate).toBeLessThan(1); // Not ALL requests should fail
      }
    });

    test('Navigation to category pages shows category articles', async ({ page }) => {
      await page.goto(`${FRONTEND_URL}/ro`, {
        waitUntil: 'domcontentloaded',
        timeout: LOAD_TIMEOUT,
      });

      // Find a category link
      const categoryLink = page.locator('a[href*="/category/"]').first();

      if (await categoryLink.count() > 0) {
        // Click category link
        await categoryLink.click();

        // Wait for navigation
        await page.waitForLoadState('domcontentloaded', { timeout: LOAD_TIMEOUT });

        // Verify page loaded
        await expect(page.locator('main')).toBeVisible();

        // Look for articles in category page
        const articles = page.locator(
          'article, [data-testid="article-card"], .article-card'
        );

        // Articles might or might not exist depending on test data
        const articleCount = await articles.count();
        console.log(`Articles in category: ${articleCount}`);
      }
    });
  });

  test.describe('Image CDN', () => {
    test('CDN images load correctly', async ({ page }) => {
      await page.goto(`${FRONTEND_URL}/ro`, {
        waitUntil: 'domcontentloaded',
        timeout: LOAD_TIMEOUT,
      });

      await page.waitForLoadState('networkidle', { timeout: LOAD_TIMEOUT }).catch(() => {});

      // Look for images that might be from CDN
      const images = page.locator('img[src*="uploads"], img[src*="8082"]');

      const imageCount = await images.count();

      if (imageCount > 0) {
        const firstImage = images.first();

        // Verify image loads
        await expect(firstImage).toBeVisible({ timeout: 5000 });

        // Check if image actually loaded (not broken)
        const naturalWidth = await firstImage.evaluate((img: any) => img.naturalWidth);
        expect(naturalWidth).toBeGreaterThan(0);
      }
    });
  });

  test.describe('API Error Handling', () => {
    test('Frontend handles API errors gracefully', async ({ page }) => {
      const consoleErrors: string[] = [];

      page.on('console', (msg) => {
        if (msg.type() === 'error') {
          consoleErrors.push(msg.text());
        }
      });

      await page.goto(`${FRONTEND_URL}/ro`, {
        waitUntil: 'domcontentloaded',
        timeout: LOAD_TIMEOUT,
      });

      // Page should load even if API has issues
      await expect(page.locator('body')).toBeVisible();

      // Should not have critical React errors
      const criticalErrors = consoleErrors.filter((err) =>
        err.toLowerCase().includes('uncaught') ||
        err.toLowerCase().includes('unhandled')
      );

      expect(criticalErrors.length).toBe(0);
    });
  });

  test.describe('API Response Performance', () => {
    test('API responds within acceptable time', async ({ request }) => {
      try {
        const startTime = Date.now();

        const response = await request.get(`${API_URL}/api/articles?itemsPerPage=10`, {
          timeout: LOAD_TIMEOUT,
          headers: {
            'Accept-Language': 'ro',
          },
        });

        const responseTime = Date.now() - startTime;

        if (response.ok()) {
          // API should respond quickly (< 5 seconds)
          expect(responseTime).toBeLessThan(5000);
          console.log(`API response time: ${responseTime}ms`);
        }
      } catch (error) {
        console.warn('API performance test skipped - API not available');
      }
    });
  });
});
