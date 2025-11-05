/**
 * E2E Tests - Article Navigation
 * Tests end-to-end article browsing and navigation flows
 */

import { test, expect } from '@playwright/test';

test.describe('Article Navigation', () => {
  test.beforeEach(async ({ page }) => {
    // Start from homepage
    await page.goto('/');
  });

  test('should navigate to article from homepage', async ({ page }) => {
    // Wait for page to load
    await page.waitForSelector('h1');

    // Find and click on first article link
    const articleLink = page.locator('article a').first();
    await expect(articleLink).toBeVisible();

    const articleTitle = await articleLink.textContent();
    await articleLink.click();

    // Verify navigation to article page
    await expect(page).toHaveURL(/\/.+\/.+/); // Should match /{category}/{article}
    await expect(page.locator('h1')).toContainText(articleTitle || '');
  });

  test('should display article content', async ({ page }) => {
    // Navigate to a specific article
    await page.goto('/politica/declaratii-premierului');

    // Verify article elements are present
    await expect(page.locator('h1')).toBeVisible();
    await expect(page.locator('.article-lead, .lead')).toBeVisible();
    await expect(page.locator('.article-content, .content')).toBeVisible();
    await expect(page.locator('.article-meta, .meta')).toBeVisible();
  });

  test('should switch locale and navigate', async ({ page }) => {
    // Start on Romanian article
    await page.goto('/politica/articol-test');

    // Click English language switcher
    const enLink = page.locator('a[href*="/en/"]').first();
    if (await enLink.isVisible()) {
      await enLink.click();

      // Verify URL changed to English
      await expect(page).toHaveURL(/\/en\//);
    }
  });

  test('should navigate via breadcrumbs', async ({ page }) => {
    await page.goto('/politica/articol-test');

    // Click on category breadcrumb
    const categoryLink = page.locator('nav[aria-label="Breadcrumb"] a').first();
    if (await categoryLink.isVisible()) {
      await categoryLink.click();

      // Should navigate to category page
      await expect(page).toHaveURL(/\/politica$/);
    }
  });

  test('should show related articles', async ({ page }) => {
    await page.goto('/politica/articol-test');

    // Check for related articles section
    const relatedSection = page.locator('[aria-label*="Related"], .related-articles');
    if (await relatedSection.isVisible()) {
      const relatedLinks = relatedSection.locator('a');
      await expect(relatedLinks.first()).toBeVisible();

      // Click first related article
      await relatedLinks.first().click();

      // Verify navigation
      await expect(page).toHaveURL(/\/.+\/.+/);
    }
  });

  test('should handle back navigation', async ({ page }) => {
    // Navigate to article
    await page.goto('/politica/articol-test');
    const firstUrl = page.url();

    // Navigate to another article
    const relatedLink = page.locator('article a, .related-articles a').first();
    if (await relatedLink.isVisible()) {
      await relatedLink.click();
      await page.waitForLoadState('networkidle');

      // Go back
      await page.goBack();
      await page.waitForLoadState('networkidle');

      // Verify we're back at first article
      expect(page.url()).toBe(firstUrl);
    }
  });
});

test.describe('Article Navigation - Mobile', () => {
  test.use({ viewport: { width: 375, height: 667 } }); // iPhone SE

  test('should navigate on mobile', async ({ page }) => {
    await page.goto('/');

    // Find article link
    const articleLink = page.locator('article a').first();
    await articleLink.click();

    // Verify navigation
    await expect(page).toHaveURL(/\/.+\/.+/);
    await expect(page.locator('h1')).toBeVisible();
  });

  test('should show mobile menu', async ({ page }) => {
    await page.goto('/politica/articol-test');

    // Look for mobile menu button
    const menuButton = page.locator('button[aria-label*="menu"], .mobile-menu-button');
    if (await menuButton.isVisible()) {
      await menuButton.click();

      // Verify menu opened
      const menu = page.locator('nav[aria-label*="Mobile"], .mobile-menu');
      await expect(menu).toBeVisible();
    }
  });
});

test.describe('Article Navigation - Multi-locale', () => {
  test('should navigate Romanian articles', async ({ page }) => {
    await page.goto('/politica/articol-ro');
    await expect(page.locator('h1')).toBeVisible();
    await expect(page).toHaveURL(/^\/[^/]+\/[^/]+$/); // No /en/ or /ru/
  });

  test('should navigate English articles', async ({ page }) => {
    await page.goto('/en/politics/article-en');
    await expect(page.locator('h1')).toBeVisible();
    await expect(page).toHaveURL(/\/en\//);
  });

  test('should navigate Russian articles', async ({ page }) => {
    await page.goto('/ru/политика/статья-ru');
    await expect(page.locator('h1')).toBeVisible();
    await expect(page).toHaveURL(/\/ru\//);
  });
});

test.describe('Article Search and Discovery', () => {
  test('should search for articles', async ({ page }) => {
    await page.goto('/search');

    // Enter search query
    const searchInput = page.locator('input[type="search"], input[name="q"]');
    await searchInput.fill('politică');
    await searchInput.press('Enter');

    // Wait for results
    await page.waitForLoadState('networkidle');

    // Verify search results
    const results = page.locator('article, .search-result');
    await expect(results.first()).toBeVisible();
  });

  test('should browse by category', async ({ page }) => {
    await page.goto('/politica');

    // Verify category page loaded
    await expect(page.locator('h1')).toContainText(/politic/i);

    // Verify articles listed
    const articles = page.locator('article');
    await expect(articles.first()).toBeVisible();
  });

  test('should view trending articles', async ({ page }) => {
    await page.goto('/trending');

    // Verify trending page
    await expect(page.locator('h1')).toContainText(/trending|popular/i);
    await expect(page.locator('article').first()).toBeVisible();
  });
});
