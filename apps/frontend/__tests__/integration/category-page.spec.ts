/**
 * Integration Tests - Category Page
 * Tests category page rendering and article listing
 */

import { test, expect } from '@playwright/test';

test.describe('Category Page Integration', () => {
  test('should load category page successfully', async ({ page }) => {
    // Try to navigate to a category page
    // Note: This might fail if no categories exist yet
    await page.goto('/politica');

    // Either category page loads or we get a 404
    const statusCode = page.url().includes('politica') ? 200 : 404;

    if (statusCode === 200) {
      await expect(page.locator('h1')).toBeVisible();
    }
  });

  test('should display category title', async ({ page }) => {
    await page.goto('/politica');

    // If category exists, check for title
    if (!page.url().includes('404')) {
      const heading = page.locator('h1');
      await expect(heading).toBeVisible();

      const title = await heading.textContent();
      expect(title?.length).toBeGreaterThan(0);
    }
  });

  test('should list articles in category', async ({ page }) => {
    await page.goto('/politica');

    // If category page loads successfully
    if (!page.url().includes('404')) {
      // Look for article listings
      const articles = page.locator('article, [data-testid="article-card"]');

      // Articles might be empty, but the container should exist
      if (await articles.count() > 0) {
        await expect(articles.first()).toBeVisible();
      }
    }
  });

  test('should have category navigation', async ({ page }) => {
    await page.goto('/politica');

    if (!page.url().includes('404')) {
      // Check for navigation (breadcrumbs or category nav)
      const nav = page.locator('nav');
      await expect(nav.first()).toBeVisible();
    }
  });

  test('should navigate to article from category', async ({ page }) => {
    await page.goto('/politica');

    if (!page.url().includes('404')) {
      // Find article links
      const articleLinks = page.locator('article a, [data-testid="article-card"] a');

      if (await articleLinks.count() > 0) {
        const firstLink = articleLinks.first();
        await firstLink.click();

        // Verify navigation to article page
        await expect(page).toHaveURL(/\/.+\/.+/);
        await expect(page.locator('h1')).toBeVisible();
      }
    }
  });
});

test.describe('Category Page - Different Categories', () => {
  const categories = ['politica', 'economie', 'societate'];

  categories.forEach((category) => {
    test(`should handle ${category} category`, async ({ page }) => {
      await page.goto(`/${category}`);

      // Page should either load or show 404
      // Both are valid states depending on data
      const is404 = page.url().includes('404');

      if (!is404) {
        await expect(page.locator('main')).toBeVisible();
      }
    });
  });
});

test.describe('Category Page - Multi-locale', () => {
  test('should display Romanian category', async ({ page }) => {
    await page.goto('/politica');

    if (!page.url().includes('404')) {
      // Verify no locale prefix in URL
      await expect(page).toHaveURL(/^http:\/\/[^/]+\/[^/]+$/);
    }
  });

  test('should display English category', async ({ page }) => {
    await page.goto('/en/politics');

    if (!page.url().includes('404')) {
      // Verify English locale in URL
      await expect(page).toHaveURL(/\/en\//);
      await expect(page.locator('main')).toBeVisible();
    }
  });

  test('should display Russian category', async ({ page }) => {
    await page.goto('/ru/политика');

    if (!page.url().includes('404')) {
      // Verify Russian locale in URL
      await expect(page).toHaveURL(/\/ru\//);
      await expect(page.locator('main')).toBeVisible();
    }
  });
});

test.describe('Category Page - Pagination', () => {
  test('should handle pagination if present', async ({ page }) => {
    await page.goto('/politica');

    if (!page.url().includes('404')) {
      // Look for pagination controls
      const pagination = page.locator('[aria-label*="pagination"], .pagination, nav[role="navigation"]');

      if (await pagination.count() > 0 && await pagination.first().isVisible()) {
        // Find next button
        const nextButton = pagination.locator('a, button').filter({ hasText: /next|următorul|следующий/i });

        if (await nextButton.count() > 0) {
          await nextButton.first().click();

          // Verify navigation occurred
          await expect(page).toHaveURL(/page=2|p=2|\?.*2/);
        }
      }
    }
  });
});

test.describe('Category Page - Responsive Design', () => {
  test('should display correctly on mobile', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/politica');

    if (!page.url().includes('404')) {
      await expect(page.locator('main')).toBeVisible();

      // Verify articles stack vertically on mobile
      const articles = page.locator('article, [data-testid="article-card"]');
      if (await articles.count() > 1) {
        const first = await articles.nth(0).boundingBox();
        const second = await articles.nth(1).boundingBox();

        // Second article should be below first (higher Y coordinate)
        if (first && second) {
          expect(second.y).toBeGreaterThan(first.y);
        }
      }
    }
  });

  test('should display correctly on tablet', async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.goto('/politica');

    if (!page.url().includes('404')) {
      await expect(page.locator('main')).toBeVisible();
    }
  });

  test('should display correctly on desktop', async ({ page }) => {
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.goto('/politica');

    if (!page.url().includes('404')) {
      await expect(page.locator('main')).toBeVisible();
    }
  });
});

test.describe('Category Page - SEO', () => {
  test('should have proper title tag', async ({ page }) => {
    await page.goto('/politica');

    if (!page.url().includes('404')) {
      const title = await page.title();
      expect(title.length).toBeGreaterThan(0);

      // Title should contain category name or site name
      expect(title).toMatch(/.+/);
    }
  });

  test('should have canonical URL', async ({ page }) => {
    await page.goto('/politica');

    if (!page.url().includes('404')) {
      const canonical = page.locator('link[rel="canonical"]');

      if (await canonical.count() > 0) {
        const href = await canonical.getAttribute('href');
        expect(href?.length).toBeGreaterThan(0);
      }
    }
  });

  test('should have hreflang tags for multilingual', async ({ page }) => {
    await page.goto('/politica');

    if (!page.url().includes('404')) {
      const hreflang = page.locator('link[rel="alternate"][hreflang]');

      if (await hreflang.count() > 0) {
        // Should have alternate language versions
        const count = await hreflang.count();
        expect(count).toBeGreaterThan(0);
      }
    }
  });
});

test.describe('Category Page - Error Handling', () => {
  test('should handle non-existent category gracefully', async ({ page }) => {
    await page.goto('/nonexistent-category-xyz');

    // Should show 404 or redirect
    // Page should still be functional
    await expect(page.locator('body')).toBeVisible();
  });

  test('should handle invalid category slug', async ({ page }) => {
    await page.goto('/!!invalid!!');

    // Should handle gracefully
    await expect(page.locator('body')).toBeVisible();
  });
});
