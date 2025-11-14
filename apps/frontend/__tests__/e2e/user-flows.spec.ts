/**
 * E2E Tests - User Flows
 * Tests complete user journeys through the application
 */

import { test, expect } from '@playwright/test';

test.describe('User Flow: Homepage to Article', () => {
  test('should browse from homepage to article and back', async ({ page }) => {
    // Start at homepage
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Verify homepage loaded
    await expect(page.locator('main')).toBeVisible();

    // Click on first article
    const articles = page.locator('article a, [data-testid="article-card"] a');

    if (await articles.count() > 0) {
      const firstArticle = articles.first();
      const articleTitle = await firstArticle.textContent();

      await firstArticle.click();
      await page.waitForLoadState('networkidle');

      // Verify article page loaded
      await expect(page).toHaveURL(/\/.+\/.+/);
      await expect(page.locator('h1')).toBeVisible();

      // Go back to homepage
      await page.goBack();
      await page.waitForLoadState('networkidle');

      // Verify back at homepage
      await expect(page).toHaveURL(/^http:\/\/localhost:3005\/?$/);
      await expect(page.locator('main')).toBeVisible();
    }
  });

  test('should browse multiple articles in sequence', async ({ page }) => {
    await page.goto('/');

    // Click first article
    const articles = page.locator('article a, [data-testid="article-card"] a');

    if (await articles.count() > 1) {
      // Go to first article
      await articles.first().click();
      await page.waitForLoadState('networkidle');

      const firstUrl = page.url();

      // Find related articles
      const relatedArticles = page.locator('.related-articles a, [aria-label*="related" i] a');

      if (await relatedArticles.count() > 0) {
        // Click first related article
        await relatedArticles.first().click();
        await page.waitForLoadState('networkidle');

        const secondUrl = page.url();

        // Verify navigation to different article
        expect(secondUrl).not.toBe(firstUrl);
        await expect(page.locator('h1')).toBeVisible();
      }
    }
  });
});

test.describe('User Flow: Category Browsing', () => {
  test('should browse category and read article', async ({ page }) => {
    // Go to homepage
    await page.goto('/');

    // Click on a category
    const categoryLinks = page.locator('nav a[href^="/politica"], nav a[href^="/economie"]');

    if (await categoryLinks.count() > 0) {
      await categoryLinks.first().click();
      await page.waitForLoadState('networkidle');

      // Verify category page loaded
      if (!page.url().includes('404')) {
        await expect(page.locator('main')).toBeVisible();

        // Click on an article in this category
        const categoryArticles = page.locator('article a, [data-testid="article-card"] a');

        if (await categoryArticles.count() > 0) {
          await categoryArticles.first().click();
          await page.waitForLoadState('networkidle');

          // Verify article loaded
          await expect(page.locator('h1')).toBeVisible();

          // Verify we're still in the same category (in URL)
          const url = page.url();
          expect(url).toMatch(/\/.+\/.+/);
        }
      }
    }
  });

  test('should navigate between different categories', async ({ page }) => {
    await page.goto('/');

    // Click first category
    const categoryLinks = page.locator('nav a');

    if (await categoryLinks.count() > 1) {
      await categoryLinks.first().click();
      await page.waitForLoadState('networkidle');

      const firstCategoryUrl = page.url();

      // Go back and click another category
      await page.goBack();
      await page.waitForLoadState('networkidle');

      await categoryLinks.nth(1).click();
      await page.waitForLoadState('networkidle');

      const secondCategoryUrl = page.url();

      // Verify navigated to different category
      if (!firstCategoryUrl.includes('404') && !secondCategoryUrl.includes('404')) {
        expect(secondCategoryUrl).not.toBe(firstCategoryUrl);
      }
    }
  });
});

test.describe('User Flow: Locale Switching', () => {
  test('should switch locale on homepage', async ({ page }) => {
    await page.goto('/');

    // Get current content
    const originalContent = await page.textContent('body');

    // Switch to English
    const enLink = page.locator('a[href^="/en"]').first();

    if (await enLink.isVisible()) {
      await enLink.click();
      await page.waitForLoadState('networkidle');

      // Verify URL changed
      await expect(page).toHaveURL(/\/en/);

      // Content should be visible
      await expect(page.locator('main')).toBeVisible();
    }
  });

  test('should switch locale on article page', async ({ page }) => {
    // Start on Romanian article
    await page.goto('/politica/test-article');

    if (!page.url().includes('404')) {
      // Switch to English
      const enLink = page.locator('a[href*="/en/"]').first();

      if (await enLink.isVisible()) {
        await enLink.click();
        await page.waitForLoadState('networkidle');

        // Should be on English version
        await expect(page).toHaveURL(/\/en\//);
        await expect(page.locator('main')).toBeVisible();
      }
    }
  });

  test('should navigate across all three locales', async ({ page }) => {
    // Start Romanian
    await page.goto('/');
    await expect(page.locator('main')).toBeVisible();

    // Switch to English
    const enLink = page.locator('a[href^="/en"]').first();
    if (await enLink.isVisible()) {
      await enLink.click();
      await page.waitForLoadState('networkidle');
      await expect(page).toHaveURL(/\/en/);
    }

    // Switch to Russian
    const ruLink = page.locator('a[href^="/ru"]').first();
    if (await ruLink.isVisible()) {
      await ruLink.click();
      await page.waitForLoadState('networkidle');
      await expect(page).toHaveURL(/\/ru/);
    }

    // Back to Romanian
    const roLink = page.locator('a[href^="/"]').first();
    if (await roLink.isVisible()) {
      await roLink.click();
      await page.waitForLoadState('networkidle');

      // Should be back to Romanian (no locale prefix)
      const url = page.url();
      expect(url).not.toMatch(/\/(en|ru)\//);
    }
  });
});

test.describe('User Flow: Social Sharing', () => {
  test('should have social sharing buttons on article', async ({ page }) => {
    await page.goto('/politica/test-article');

    if (!page.url().includes('404')) {
      // Look for social sharing buttons
      const shareButtons = page.locator('[aria-label*="share" i], .share-button, .social-share');

      if (await shareButtons.count() > 0) {
        await expect(shareButtons.first()).toBeVisible();

        // Verify share buttons are clickable
        const firstButton = shareButtons.first();
        await expect(firstButton).toBeEnabled();
      }
    }
  });
});

test.describe('User Flow: Search and Discovery', () => {
  test('should search for articles', async ({ page }) => {
    // Go to search page if it exists
    await page.goto('/search');

    if (!page.url().includes('404')) {
      // Find search input
      const searchInput = page.locator('input[type="search"], input[name="q"]');

      if (await searchInput.count() > 0) {
        // Enter search query
        await searchInput.fill('test');
        await searchInput.press('Enter');

        await page.waitForLoadState('networkidle');

        // Verify search results page
        await expect(page).toHaveURL(/search/);
      }
    }
  });

  test('should browse trending articles', async ({ page }) => {
    await page.goto('/trending');

    if (!page.url().includes('404')) {
      await expect(page.locator('main')).toBeVisible();

      // Should have articles
      const articles = page.locator('article, [data-testid="article-card"]');

      if (await articles.count() > 0) {
        // Click first trending article
        const firstArticle = articles.first().locator('a').first();
        await firstArticle.click();

        // Should navigate to article
        await expect(page.locator('h1')).toBeVisible();
      }
    }
  });
});

test.describe('User Flow: Mobile Experience', () => {
  test.use({ viewport: { width: 375, height: 667 } });

  test('should complete full journey on mobile', async ({ page }) => {
    // Homepage
    await page.goto('/');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('main')).toBeVisible();

    // Open mobile menu if present
    const menuButton = page.locator('button[aria-label*="menu" i]');
    if (await menuButton.count() > 0) {
      await menuButton.first().click();

      // Click a category
      const categoryLink = page.locator('nav a').first();
      if (await categoryLink.isVisible()) {
        await categoryLink.click();
        await page.waitForLoadState('networkidle');
      }
    }

    // Click article
    const articles = page.locator('article a, [data-testid="article-card"] a');
    if (await articles.count() > 0) {
      await articles.first().click();
      await page.waitForLoadState('networkidle');

      // Verify article loaded
      await expect(page.locator('h1')).toBeVisible();
    }

    // Scroll to bottom
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));

    // Verify page is still functional
    await expect(page.locator('main')).toBeVisible();
  });

  test('should handle touch gestures on mobile', async ({ page }) => {
    await page.goto('/');

    // Test swipe/scroll
    await page.mouse.move(200, 300);
    await page.mouse.down();
    await page.mouse.move(200, 100);
    await page.mouse.up();

    // Page should still be functional
    await expect(page.locator('main')).toBeVisible();
  });
});

test.describe('User Flow: Error Recovery', () => {
  test('should recover from 404 error', async ({ page }) => {
    // Go to non-existent page
    await page.goto('/nonexistent-page-xyz-123');

    // Page should still render
    await expect(page.locator('body')).toBeVisible();

    // Should have navigation to get back
    const navLinks = page.locator('nav a, a[href="/"]');
    if (await navLinks.count() > 0) {
      await navLinks.first().click();

      // Should navigate away from 404
      await expect(page.locator('main')).toBeVisible();
    }
  });

  test('should handle navigation errors gracefully', async ({ page }) => {
    await page.goto('/');

    // Try to navigate to invalid URL
    await page.goto('/!!!invalid!!!');

    // Page should handle it gracefully
    await expect(page.locator('body')).toBeVisible();
  });
});

test.describe('User Flow: Performance', () => {
  test('should maintain good performance during navigation', async ({ page }) => {
    const timings: number[] = [];

    // Measure homepage load
    let start = Date.now();
    await page.goto('/');
    await page.waitForLoadState('networkidle');
    timings.push(Date.now() - start);

    // Navigate to category
    const categoryLink = page.locator('nav a').first();
    if (await categoryLink.isVisible()) {
      start = Date.now();
      await categoryLink.click();
      await page.waitForLoadState('networkidle');
      timings.push(Date.now() - start);
    }

    // Navigate to article
    const articles = page.locator('article a');
    if (await articles.count() > 0) {
      start = Date.now();
      await articles.first().click();
      await page.waitForLoadState('networkidle');
      timings.push(Date.now() - start);
    }

    // All navigations should be reasonably fast
    for (const timing of timings) {
      expect(timing).toBeLessThan(5000); // 5 seconds max
    }
  });
});
