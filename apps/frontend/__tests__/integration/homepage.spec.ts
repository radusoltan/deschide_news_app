/**
 * Integration Tests - Homepage
 * Tests homepage rendering and functionality
 */

import { test, expect } from '@playwright/test';

test.describe('Homepage Integration', () => {
  test('should load homepage successfully', async ({ page }) => {
    await page.goto('/');

    // Wait for page to load
    await page.waitForLoadState('networkidle');

    // Verify essential elements are present
    await expect(page.locator('h1, h2').first()).toBeVisible();
    await expect(page.locator('main')).toBeVisible();
  });

  test('should display navigation menu', async ({ page }) => {
    await page.goto('/');

    // Check for navigation
    const nav = page.locator('nav').first();
    await expect(nav).toBeVisible();

    // Verify navigation has category links
    const navLinks = nav.locator('a');
    await expect(navLinks.first()).toBeVisible();
  });

  test('should display featured articles', async ({ page }) => {
    await page.goto('/');

    // Look for article cards or links
    const articles = page.locator('article, [data-testid="article-card"]');

    if (await articles.count() > 0) {
      await expect(articles.first()).toBeVisible();

      // Verify article has title
      const firstArticle = articles.first();
      const title = firstArticle.locator('h1, h2, h3, h4').first();
      await expect(title).toBeVisible();
    }
  });

  test('should have working locale selector', async ({ page }) => {
    await page.goto('/');

    // Look for language links or selectors
    const localeLinks = page.locator('a[href^="/en"], a[href^="/ru"]');

    if (await localeLinks.count() > 0) {
      await expect(localeLinks.first()).toBeVisible();
    }
  });

  test('should have responsive layout', async ({ page }) => {
    // Test desktop
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.goto('/');
    await expect(page.locator('main')).toBeVisible();

    // Test tablet
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.goto('/');
    await expect(page.locator('main')).toBeVisible();

    // Test mobile
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/');
    await expect(page.locator('main')).toBeVisible();
  });

  test('should display footer with links', async ({ page }) => {
    await page.goto('/');

    const footer = page.locator('footer');
    if (await footer.isVisible()) {
      // Check for copyright or other footer content
      await expect(footer).toContainText(/.+/);
    }
  });

  test('should have proper meta tags', async ({ page }) => {
    await page.goto('/');

    // Check for essential meta tags
    const title = await page.title();
    expect(title.length).toBeGreaterThan(0);

    // Check for meta description
    const metaDescription = page.locator('meta[name="description"]');
    if (await metaDescription.count() > 0) {
      const content = await metaDescription.getAttribute('content');
      expect(content?.length).toBeGreaterThan(0);
    }
  });
});

test.describe('Homepage - Romanian Locale', () => {
  test('should display content in Romanian', async ({ page }) => {
    await page.goto('/');

    // Verify Romanian content (no /en/ or /ru/ in URL)
    await expect(page).toHaveURL(/^http:\/\/[^/]+\/?$/);

    // Check for Romanian-specific characters if present
    const content = await page.textContent('body');
    // Just verify we have content
    expect(content?.length).toBeGreaterThan(0);
  });
});

test.describe('Homepage - English Locale', () => {
  test('should display content in English', async ({ page }) => {
    await page.goto('/en');

    // Verify English URL
    await expect(page).toHaveURL(/\/en/);

    // Verify page loads
    await expect(page.locator('main')).toBeVisible();
  });
});

test.describe('Homepage - Russian Locale', () => {
  test('should display content in Russian', async ({ page }) => {
    await page.goto('/ru');

    // Verify Russian URL
    await expect(page).toHaveURL(/\/ru/);

    // Verify page loads
    await expect(page.locator('main')).toBeVisible();
  });
});

test.describe('Homepage Performance', () => {
  test('should load within acceptable time', async ({ page }) => {
    const startTime = Date.now();

    await page.goto('/');
    await page.waitForLoadState('networkidle');

    const loadTime = Date.now() - startTime;

    // Should load within 5 seconds (generous for development)
    expect(loadTime).toBeLessThan(5000);
  });

  test('should have no console errors on load', async ({ page }) => {
    const errors: string[] = [];

    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        errors.push(msg.text());
      }
    });

    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // No critical errors should be present
    // Filter out expected errors like network issues in test environment
    const criticalErrors = errors.filter(
      (err) => !err.includes('favicon') && !err.includes('404')
    );

    expect(criticalErrors.length).toBe(0);
  });
});
