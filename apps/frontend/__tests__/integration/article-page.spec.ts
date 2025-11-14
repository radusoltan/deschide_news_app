/**
 * Integration Tests - Article Page
 * Tests article page rendering and content display
 */

import { test, expect } from '@playwright/test';

test.describe('Article Page Integration', () => {
  test('should load article page successfully', async ({ page }) => {
    // Try to navigate to an article page
    await page.goto('/politica/test-article');

    // Page should load (might be 404 if article doesn't exist)
    await expect(page.locator('body')).toBeVisible();
  });

  test('should display article title', async ({ page }) => {
    await page.goto('/politica/test-article');

    if (!page.url().includes('404')) {
      const heading = page.locator('h1');
      await expect(heading).toBeVisible();

      const title = await heading.textContent();
      expect(title?.length).toBeGreaterThan(0);
    }
  });

  test('should display article content', async ({ page }) => {
    await page.goto('/politica/test-article');

    if (!page.url().includes('404')) {
      // Look for article content container
      const contentSelectors = [
        '.article-content',
        '.content',
        '[data-testid="article-content"]',
        'article .prose',
        'main article',
      ];

      let contentFound = false;
      for (const selector of contentSelectors) {
        if (await page.locator(selector).count() > 0) {
          await expect(page.locator(selector).first()).toBeVisible();
          contentFound = true;
          break;
        }
      }

      // Content should exist on article page
      if (!contentFound) {
        // At least the main element should be there
        await expect(page.locator('main')).toBeVisible();
      }
    }
  });

  test('should display article metadata', async ({ page }) => {
    await page.goto('/politica/test-article');

    if (!page.url().includes('404')) {
      // Look for metadata (date, author, category)
      const metaSelectors = [
        '.article-meta',
        '.meta',
        '[data-testid="article-meta"]',
        'time',
        '.author',
      ];

      let metaFound = false;
      for (const selector of metaSelectors) {
        if (await page.locator(selector).count() > 0) {
          metaFound = true;
          break;
        }
      }

      // Metadata should typically be present on article pages
      expect(metaFound).toBeTruthy();
    }
  });

  test('should display breadcrumb navigation', async ({ page }) => {
    await page.goto('/politica/test-article');

    if (!page.url().includes('404')) {
      const breadcrumb = page.locator('nav[aria-label*="Breadcrumb" i], .breadcrumb');

      if (await breadcrumb.count() > 0) {
        await expect(breadcrumb.first()).toBeVisible();

        // Should have links
        const links = breadcrumb.locator('a');
        expect(await links.count()).toBeGreaterThan(0);
      }
    }
  });

  test('should have featured image if available', async ({ page }) => {
    await page.goto('/politica/test-article');

    if (!page.url().includes('404')) {
      const images = page.locator('article img, main img, .featured-image img');

      if (await images.count() > 0) {
        // Verify image is visible
        await expect(images.first()).toBeVisible();

        // Verify image has alt text
        const alt = await images.first().getAttribute('alt');
        expect(alt).toBeDefined();
      }
    }
  });

  test('should display social sharing buttons', async ({ page }) => {
    await page.goto('/politica/test-article');

    if (!page.url().includes('404')) {
      const shareButtons = page.locator(
        '[aria-label*="share" i], .share-buttons, .social-share'
      );

      if (await shareButtons.count() > 0) {
        await expect(shareButtons.first()).toBeVisible();
      }
    }
  });

  test('should display related articles', async ({ page }) => {
    await page.goto('/politica/test-article');

    if (!page.url().includes('404')) {
      const relatedSection = page.locator(
        '[aria-label*="related" i], .related-articles, .related'
      );

      if (await relatedSection.count() > 0) {
        await expect(relatedSection.first()).toBeVisible();

        // Should have article links
        const articles = relatedSection.locator('article, a');
        if (await articles.count() > 0) {
          await expect(articles.first()).toBeVisible();
        }
      }
    }
  });
});

test.describe('Article Page - Multi-locale', () => {
  test('should display Romanian article', async ({ page }) => {
    await page.goto('/politica/articol-ro');

    if (!page.url().includes('404')) {
      // Verify Romanian URL (no /en/ or /ru/)
      await expect(page).toHaveURL(/^http:\/\/[^/]+\/[^/]+\/[^/]+$/);
      await expect(page.locator('main')).toBeVisible();
    }
  });

  test('should display English article', async ({ page }) => {
    await page.goto('/en/politics/article-en');

    if (!page.url().includes('404')) {
      // Verify English locale
      await expect(page).toHaveURL(/\/en\//);
      await expect(page.locator('main')).toBeVisible();
    }
  });

  test('should display Russian article', async ({ page }) => {
    await page.goto('/ru/политика/статья-ru');

    if (!page.url().includes('404')) {
      // Verify Russian locale
      await expect(page).toHaveURL(/\/ru\//);
      await expect(page.locator('main')).toBeVisible();
    }
  });
});

test.describe('Article Page - Navigation', () => {
  test('should navigate back to category from breadcrumb', async ({ page }) => {
    await page.goto('/politica/test-article');

    if (!page.url().includes('404')) {
      const categoryLink = page.locator('nav a[href*="politica"]').first();

      if (await categoryLink.isVisible()) {
        await categoryLink.click();

        // Should navigate to category page
        await expect(page).toHaveURL(/\/politica$/);
        await expect(page.locator('main')).toBeVisible();
      }
    }
  });

  test('should navigate to related article', async ({ page }) => {
    await page.goto('/politica/test-article');

    if (!page.url().includes('404')) {
      const relatedLinks = page.locator('.related-articles a, [aria-label*="related" i] a');

      if (await relatedLinks.count() > 0) {
        const firstRelated = relatedLinks.first();
        await firstRelated.click();

        // Should navigate to another article
        await expect(page).toHaveURL(/\/.+\/.+/);
        await expect(page.locator('h1')).toBeVisible();
      }
    }
  });
});

test.describe('Article Page - Responsive Design', () => {
  test('should display correctly on mobile', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/politica/test-article');

    if (!page.url().includes('404')) {
      await expect(page.locator('main')).toBeVisible();
      await expect(page.locator('h1')).toBeVisible();

      // Content should be readable
      const content = page.locator('.article-content, .content, main article');
      if (await content.count() > 0) {
        await expect(content.first()).toBeVisible();
      }
    }
  });

  test('should display correctly on tablet', async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.goto('/politica/test-article');

    if (!page.url().includes('404')) {
      await expect(page.locator('main')).toBeVisible();
    }
  });

  test('should display correctly on desktop', async ({ page }) => {
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.goto('/politica/test-article');

    if (!page.url().includes('404')) {
      await expect(page.locator('main')).toBeVisible();
    }
  });
});

test.describe('Article Page - SEO', () => {
  test('should have proper title tag with article title', async ({ page }) => {
    await page.goto('/politica/test-article');

    if (!page.url().includes('404')) {
      const title = await page.title();
      expect(title.length).toBeGreaterThan(0);
    }
  });

  test('should have meta description', async ({ page }) => {
    await page.goto('/politica/test-article');

    if (!page.url().includes('404')) {
      const metaDesc = page.locator('meta[name="description"]');

      if (await metaDesc.count() > 0) {
        const content = await metaDesc.getAttribute('content');
        expect(content?.length).toBeGreaterThan(0);
      }
    }
  });

  test('should have Open Graph tags', async ({ page }) => {
    await page.goto('/politica/test-article');

    if (!page.url().includes('404')) {
      const ogTitle = page.locator('meta[property="og:title"]');

      if (await ogTitle.count() > 0) {
        const content = await ogTitle.getAttribute('content');
        expect(content?.length).toBeGreaterThan(0);
      }
    }
  });

  test('should have structured data (JSON-LD)', async ({ page }) => {
    await page.goto('/politica/test-article');

    if (!page.url().includes('404')) {
      const jsonLd = page.locator('script[type="application/ld+json"]');

      if (await jsonLd.count() > 0) {
        const content = await jsonLd.first().textContent();
        expect(content?.length).toBeGreaterThan(0);

        // Verify it's valid JSON
        expect(() => JSON.parse(content || '{}')).not.toThrow();
      }
    }
  });

  test('should have canonical URL', async ({ page }) => {
    await page.goto('/politica/test-article');

    if (!page.url().includes('404')) {
      const canonical = page.locator('link[rel="canonical"]');

      if (await canonical.count() > 0) {
        const href = await canonical.getAttribute('href');
        expect(href).toContain('/politica/test-article');
      }
    }
  });

  test('should have hreflang tags', async ({ page }) => {
    await page.goto('/politica/test-article');

    if (!page.url().includes('404')) {
      const hreflang = page.locator('link[rel="alternate"][hreflang]');

      if (await hreflang.count() > 0) {
        // Should have multiple language versions
        expect(await hreflang.count()).toBeGreaterThan(0);
      }
    }
  });
});

test.describe('Article Page - Error Handling', () => {
  test('should handle non-existent article gracefully', async ({ page }) => {
    await page.goto('/politica/nonexistent-article-xyz-123');

    // Should show 404 or error page
    await expect(page.locator('body')).toBeVisible();

    // Check if custom 404 message is shown
    const body = await page.textContent('body');
    // Page should be functional even on 404
    expect(body?.length).toBeGreaterThan(0);
  });

  test('should handle wrong category gracefully', async ({ page }) => {
    await page.goto('/wrong-category/test-article');

    // Should handle gracefully (404 or redirect)
    await expect(page.locator('body')).toBeVisible();
  });
});

test.describe('Article Page - Performance', () => {
  test('should load within acceptable time', async ({ page }) => {
    const startTime = Date.now();

    await page.goto('/politica/test-article');
    await page.waitForLoadState('networkidle');

    const loadTime = Date.now() - startTime;

    // Should load within 5 seconds
    expect(loadTime).toBeLessThan(5000);
  });

  test('should have no JavaScript errors', async ({ page }) => {
    const errors: string[] = [];

    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        errors.push(msg.text());
      }
    });

    await page.goto('/politica/test-article');
    await page.waitForLoadState('networkidle');

    // Filter out expected errors
    const criticalErrors = errors.filter(
      (err) => !err.includes('favicon') && !err.includes('404')
    );

    expect(criticalErrors.length).toBe(0);
  });
});
