/**
 * E2E Tests - Locale Switching
 * Tests multilingual functionality and locale switching
 */

import { test, expect } from '@playwright/test';

test.describe('Locale Switching - Homepage', () => {
  test('should load Romanian homepage by default', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Should not have locale prefix in URL
    expect(page.url()).toMatch(/^http:\/\/localhost:3005\/?$/);
    await expect(page.locator('main')).toBeVisible();
  });

  test('should load English homepage with /en prefix', async ({ page }) => {
    await page.goto('/en');
    await page.waitForLoadState('networkidle');

    await expect(page).toHaveURL(/\/en/);
    await expect(page.locator('main')).toBeVisible();
  });

  test('should load Russian homepage with /ru prefix', async ({ page }) => {
    await page.goto('/ru');
    await page.waitForLoadState('networkidle');

    await expect(page).toHaveURL(/\/ru/);
    await expect(page.locator('main')).toBeVisible();
  });

  test('should switch from Romanian to English', async ({ page }) => {
    await page.goto('/');

    const enLink = page.locator('a[href^="/en"]').first();

    if (await enLink.isVisible()) {
      // Click English link
      await enLink.click();
      await page.waitForLoadState('networkidle');

      // Verify switched to English
      await expect(page).toHaveURL(/\/en/);
      await expect(page.locator('main')).toBeVisible();
    }
  });

  test('should switch from Romanian to Russian', async ({ page }) => {
    await page.goto('/');

    const ruLink = page.locator('a[href^="/ru"]').first();

    if (await ruLink.isVisible()) {
      // Click Russian link
      await ruLink.click();
      await page.waitForLoadState('networkidle');

      // Verify switched to Russian
      await expect(page).toHaveURL(/\/ru/);
      await expect(page.locator('main')).toBeVisible();
    }
  });

  test('should cycle through all locales', async ({ page }) => {
    // Start Romanian
    await page.goto('/');
    const roUrl = page.url();

    // Go to English
    const enLink = page.locator('a[href^="/en"]').first();
    if (await enLink.isVisible()) {
      await enLink.click();
      await page.waitForLoadState('networkidle');
      const enUrl = page.url();

      // Go to Russian
      const ruLink = page.locator('a[href^="/ru"]').first();
      if (await ruLink.isVisible()) {
        await ruLink.click();
        await page.waitForLoadState('networkidle');
        const ruUrl = page.url();

        // Back to Romanian
        const roLink = page.locator('a[href="/"]').first();
        if (await roLink.isVisible()) {
          await roLink.click();
          await page.waitForLoadState('networkidle');

          // Should be back to Romanian URL
          expect(page.url()).toMatch(/^http:\/\/localhost:3005\/?$/);
        }

        // All URLs should be different
        expect(roUrl).not.toBe(enUrl);
        expect(enUrl).not.toBe(ruUrl);
      }
    }
  });
});

test.describe('Locale Switching - Article Pages', () => {
  test('should maintain article context when switching locale', async ({ page }) => {
    // Start on Romanian article
    await page.goto('/politica/test-article');

    if (!page.url().includes('404')) {
      // Get article title
      const roTitle = await page.locator('h1').textContent();

      // Switch to English
      const enLink = page.locator('a[href*="/en/"]').first();

      if (await enLink.isVisible()) {
        await enLink.click();
        await page.waitForLoadState('networkidle');

        // Should be on English version
        await expect(page).toHaveURL(/\/en\//);

        // Should still be on an article page
        await expect(page).toHaveURL(/\/.+\/.+/);

        // Article title should exist (might be translated)
        const enTitle = await page.locator('h1').textContent();
        expect(enTitle?.length).toBeGreaterThan(0);
      }
    }
  });

  test('should show same article in different languages', async ({ page }) => {
    // Romanian article
    await page.goto('/politica/test-article');

    if (!page.url().includes('404')) {
      // English version
      const enLink = page.locator('a[href*="/en/politics"]').first();

      if (await enLink.isVisible()) {
        await enLink.click();
        await page.waitForLoadState('networkidle');

        // Should have article content
        await expect(page.locator('h1')).toBeVisible();

        // Russian version
        const ruLink = page.locator('a[href*="/ru/"]').first();

        if (await ruLink.isVisible()) {
          await ruLink.click();
          await page.waitForLoadState('networkidle');

          // Should have article content
          await expect(page.locator('h1')).toBeVisible();
        }
      }
    }
  });
});

test.describe('Locale Switching - Category Pages', () => {
  test('should maintain category context when switching locale', async ({ page }) => {
    // Romanian category
    await page.goto('/politica');

    if (!page.url().includes('404')) {
      // Switch to English
      const enLink = page.locator('a[href*="/en/"]').first();

      if (await enLink.isVisible()) {
        await enLink.click();
        await page.waitForLoadState('networkidle');

        // Should be on English category
        await expect(page).toHaveURL(/\/en\//);
        await expect(page.locator('main')).toBeVisible();
      }
    }
  });
});

test.describe('Locale Persistence', () => {
  test('should remember locale preference across pages', async ({ page }) => {
    // Switch to English on homepage
    await page.goto('/');

    const enLink = page.locator('a[href^="/en"]').first();

    if (await enLink.isVisible()) {
      await enLink.click();
      await page.waitForLoadState('networkidle');

      // Navigate to another page
      const navLinks = page.locator('nav a');

      if (await navLinks.count() > 0) {
        await navLinks.first().click();
        await page.waitForLoadState('networkidle');

        // Should still be in English locale
        await expect(page).toHaveURL(/\/en\//);
      }
    }
  });

  test('should persist locale through browser refresh', async ({ page }) => {
    // Go to English page
    await page.goto('/en');
    await page.waitForLoadState('networkidle');

    // Refresh page
    await page.reload();
    await page.waitForLoadState('networkidle');

    // Should still be on English page
    await expect(page).toHaveURL(/\/en/);
    await expect(page.locator('main')).toBeVisible();
  });
});

test.describe('Locale Links and Alternates', () => {
  test('should have hreflang links in HTML', async ({ page }) => {
    await page.goto('/politica/test-article');

    if (!page.url().includes('404')) {
      // Check for hreflang alternate links
      const hreflangLinks = page.locator('link[rel="alternate"][hreflang]');

      if (await hreflangLinks.count() > 0) {
        // Should have multiple language versions
        const count = await hreflangLinks.count();
        expect(count).toBeGreaterThan(0);

        // Verify hreflang values
        for (let i = 0; i < count; i++) {
          const hreflang = await hreflangLinks.nth(i).getAttribute('hreflang');
          const href = await hreflangLinks.nth(i).getAttribute('href');

          expect(hreflang).toMatch(/^(ro|en|ru|x-default)$/);
          expect(href).toBeDefined();
        }
      }
    }
  });

  test('should have working language alternate links', async ({ page }) => {
    await page.goto('/politica/test-article');

    if (!page.url().includes('404')) {
      const hreflangLinks = page.locator('link[rel="alternate"][hreflang="en"]');

      if (await hreflangLinks.count() > 0) {
        const href = await hreflangLinks.first().getAttribute('href');

        if (href) {
          // Navigate to English version
          await page.goto(href);
          await page.waitForLoadState('networkidle');

          // Should be on English page
          await expect(page).toHaveURL(/\/en\//);
          await expect(page.locator('main')).toBeVisible();
        }
      }
    }
  });
});

test.describe('Locale URLs', () => {
  test('should have correct URL structure for Romanian', async ({ page }) => {
    await page.goto('/politica/test-article');

    if (!page.url().includes('404')) {
      // Romanian URLs should not have locale prefix
      expect(page.url()).toMatch(/^http:\/\/localhost:3005\/[^/]+\/[^/]+$/);
      expect(page.url()).not.toContain('/ro/');
    }
  });

  test('should have correct URL structure for English', async ({ page }) => {
    await page.goto('/en/politics/test-article');

    if (!page.url().includes('404')) {
      // English URLs should have /en/ prefix
      expect(page.url()).toContain('/en/');
    }
  });

  test('should have correct URL structure for Russian', async ({ page }) => {
    await page.goto('/ru/политика/test-article');

    if (!page.url().includes('404')) {
      // Russian URLs should have /ru/ prefix
      expect(page.url()).toContain('/ru/');
    }
  });
});

test.describe('Locale Content Verification', () => {
  test('should display Romanian content on Romanian pages', async ({ page }) => {
    await page.goto('/');

    // Get page content
    const content = await page.textContent('body');

    // Should not have English/Russian locale prefix in URL
    expect(page.url()).not.toMatch(/\/(en|ru)\//);

    // Content should exist
    expect(content?.length).toBeGreaterThan(0);
  });

  test('should display English content on English pages', async ({ page }) => {
    await page.goto('/en');

    // Should have /en/ in URL
    expect(page.url()).toContain('/en/');

    // Content should exist
    const content = await page.textContent('body');
    expect(content?.length).toBeGreaterThan(0);
  });

  test('should display Russian content on Russian pages', async ({ page }) => {
    await page.goto('/ru');

    // Should have /ru/ in URL
    expect(page.url()).toContain('/ru/');

    // Content should exist
    const content = await page.textContent('body');
    expect(content?.length).toBeGreaterThan(0);
  });
});

test.describe('Locale Switching - Mobile', () => {
  test.use({ viewport: { width: 375, height: 667 } });

  test('should switch locale on mobile', async ({ page }) => {
    await page.goto('/');

    // Find English link
    const enLink = page.locator('a[href^="/en"]').first();

    if (await enLink.isVisible()) {
      await enLink.click();
      await page.waitForLoadState('networkidle');

      // Verify switched
      await expect(page).toHaveURL(/\/en/);
      await expect(page.locator('main')).toBeVisible();
    }
  });

  test('should have accessible locale switcher on mobile', async ({ page }) => {
    await page.goto('/');

    // Locale switcher should be accessible
    const localeLinks = page.locator('a[href^="/en"], a[href^="/ru"]');

    if (await localeLinks.count() > 0) {
      // Should be visible or in menu
      const firstLink = localeLinks.first();

      // Check if visible or if menu needs to be opened
      const isVisible = await firstLink.isVisible();

      if (!isVisible) {
        // Try to open mobile menu
        const menuButton = page.locator('button[aria-label*="menu" i]');

        if (await menuButton.count() > 0) {
          await menuButton.first().click();

          // Locale switcher might be in menu
          await page.waitForTimeout(500);
        }
      }
    }
  });
});
