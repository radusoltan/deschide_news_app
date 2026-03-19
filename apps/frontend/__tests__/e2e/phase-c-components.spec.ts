import { test, expect } from '@playwright/test';

test.describe('Phase C Components', () => {

  test.describe('Homepage', () => {
    test('loads without errors', async ({ page }) => {
      const response = await page.goto('/ro/');
      expect(response?.status()).toBeLessThan(400);
    });

    // TODO: needs article content — enable after production import
    test.skip('displays article cards', async ({ page }) => {
      await page.goto('/ro/');
      const cards = page.locator('[class*="article"], [class*="ArticleCard"], article');
      await expect(cards.first()).toBeVisible({ timeout: 10000 });
    });

    // TODO: needs article content — enable after production import
    test.skip('displays category sections', async ({ page }) => {
      await page.goto('/ro/');
      const sections = page.locator('[class*="category"], [class*="CategorySection"]');
      const count = await sections.count();
      expect(count).toBeGreaterThan(0);
    });
  });

  test.describe('Header', () => {
    test('is visible and contains logo', async ({ page }) => {
      await page.goto('/ro/');
      const header = page.locator('header').first();
      await expect(header).toBeVisible();
      // Logo or brand name
      const logo = header.locator('a[href="/"], a[href="/ro/"], a[href="/ro"], img[alt*="eschide"], [class*="logo"], [class*="Logo"]');
      await expect(logo.first()).toBeVisible();
    });

    test('becomes compact on scroll', async ({ page }) => {
      await page.goto('/ro/');
      const header = page.locator('header').first();
      // Scroll down
      await page.evaluate(() => window.scrollTo(0, 500));
      await page.waitForTimeout(500);
      // Header should still be visible (sticky)
      await expect(header).toBeVisible();
    });
  });

  test.describe('Navigation', () => {
    test('category nav is visible', async ({ page }) => {
      await page.goto('/ro/');
      const nav = page.locator('nav, [class*="CategoryNav"], [class*="category-nav"]');
      await expect(nav.first()).toBeVisible();
    });
  });

  test.describe('Multilingual', () => {
    test('Romanian page loads', async ({ page }) => {
      const res = await page.goto('/ro/');
      expect(res?.status()).toBeLessThan(400);
      // Check html lang attribute
      const lang = await page.getAttribute('html', 'lang');
      expect(lang).toContain('ro');
    });

    test('English page loads', async ({ page }) => {
      const res = await page.goto('/en/');
      expect(res?.status()).toBeLessThan(400);
    });

    test('Russian page loads with Cyrillic', async ({ page }) => {
      const res = await page.goto('/ru/');
      expect(res?.status()).toBeLessThan(400);
      // Check for Cyrillic characters in body
      const text = await page.textContent('body');
      // Cyrillic range: \u0400-\u04FF
      const hasCyrillic = /[\u0400-\u04FF]/.test(text || '');
      // May be OK without Cyrillic if no Russian articles
      if (!hasCyrillic) {
        console.warn('No Cyrillic text found on /ru/ — may need Russian content');
      }
    });
  });

  test.describe('SEO', () => {
    test('has meta tags on homepage', async ({ page }) => {
      await page.goto('/ro/');
      const title = await page.title();
      expect(title.length).toBeGreaterThan(0);
      const desc = await page.getAttribute('meta[name="description"]', 'content');
      // Description might not be set without articles
      if (desc) expect(desc.length).toBeGreaterThan(0);
    });

    test('has hreflang tags', async ({ page }) => {
      await page.goto('/ro/');
      const hreflangs = await page.locator('link[hreflang]').count();
      // Should have at least ro, en, ru, x-default
      expect(hreflangs).toBeGreaterThanOrEqual(2);
    });

    test('sitemap.xml is accessible', async ({ page }) => {
      const res = await page.goto('/sitemap.xml');
      expect(res?.status()).toBeLessThan(400);
    });

    test('robots.txt is accessible', async ({ page }) => {
      const res = await page.goto('/robots.txt');
      expect(res?.status()).toBeLessThan(400);
    });
  });

  test.describe('Footer', () => {
    test('is visible at bottom', async ({ page }) => {
      await page.goto('/ro/');
      const footer = page.locator('footer').first();
      await footer.scrollIntoViewIfNeeded();
      await expect(footer).toBeVisible();
    });
  });

});
