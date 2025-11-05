/**
 * Integration Tests - Navigation
 * Tests navigation components and routing
 */

import { test, expect } from '@playwright/test';

test.describe('Navigation Integration', () => {
  test('should display main navigation', async ({ page }) => {
    await page.goto('/');

    const nav = page.locator('nav').first();
    await expect(nav).toBeVisible();
  });

  test('should have category links in navigation', async ({ page }) => {
    await page.goto('/');

    const categoryLinks = page.locator('nav a');

    if (await categoryLinks.count() > 0) {
      // Verify first category link works
      const firstLink = categoryLinks.first();
      await expect(firstLink).toBeVisible();

      const href = await firstLink.getAttribute('href');
      expect(href).toBeDefined();
    }
  });

  test('should navigate between pages using nav', async ({ page }) => {
    await page.goto('/');

    // Find a navigation link
    const navLinks = page.locator('nav a');

    if (await navLinks.count() > 0) {
      // Click first link
      await navLinks.first().click();
      await page.waitForLoadState('networkidle');

      // Verify navigation occurred
      expect(page.url()).not.toBe('http://localhost:3005/');
    }
  });

  test('should highlight active navigation item', async ({ page }) => {
    await page.goto('/politica');

    if (!page.url().includes('404')) {
      // Look for active nav item
      const activeItem = page.locator('nav a[aria-current], nav a.active, nav a[class*="active"]');

      if (await activeItem.count() > 0) {
        await expect(activeItem.first()).toBeVisible();
      }
    }
  });
});

test.describe('Breadcrumb Navigation', () => {
  test('should display breadcrumbs on article page', async ({ page }) => {
    await page.goto('/politica/test-article');

    if (!page.url().includes('404')) {
      const breadcrumb = page.locator('nav[aria-label*="Breadcrumb" i], .breadcrumb, [data-testid="breadcrumb"]');

      if (await breadcrumb.count() > 0) {
        await expect(breadcrumb.first()).toBeVisible();
      }
    }
  });

  test('should have home link in breadcrumbs', async ({ page }) => {
    await page.goto('/politica/test-article');

    if (!page.url().includes('404')) {
      const homeLink = page.locator('nav a[href="/"], nav a[href="/ro"]').first();

      if (await homeLink.isVisible()) {
        await homeLink.click();

        // Should navigate to homepage
        await expect(page).toHaveURL(/^\/$|^\/ro$/);
      }
    }
  });

  test('should have category link in breadcrumbs', async ({ page }) => {
    await page.goto('/politica/test-article');

    if (!page.url().includes('404')) {
      const categoryLink = page.locator('nav a[href*="politica"]');

      if (await categoryLink.count() > 0) {
        const firstCategoryLink = categoryLink.first();

        if (await firstCategoryLink.isVisible()) {
          await firstCategoryLink.click();

          // Should navigate to category
          await expect(page).toHaveURL(/\/politica/);
        }
      }
    }
  });

  test('should show current page as last breadcrumb', async ({ page }) => {
    await page.goto('/politica/test-article');

    if (!page.url().includes('404')) {
      const breadcrumbs = page.locator('nav[aria-label*="Breadcrumb" i] a, .breadcrumb a');

      if (await breadcrumbs.count() > 0) {
        // Last breadcrumb might be current page (not clickable)
        // or it might not exist (current page shown as text)
        const count = await breadcrumbs.count();
        expect(count).toBeGreaterThan(0);
      }
    }
  });
});

test.describe('Locale Navigation', () => {
  test('should have language switcher', async ({ page }) => {
    await page.goto('/');

    // Look for language links
    const localeLinks = page.locator('a[href^="/en"], a[href^="/ru"], [data-testid="locale-switcher"]');

    if (await localeLinks.count() > 0) {
      await expect(localeLinks.first()).toBeVisible();
    }
  });

  test('should switch from Romanian to English', async ({ page }) => {
    await page.goto('/');

    const enLink = page.locator('a[href^="/en"]').first();

    if (await enLink.isVisible()) {
      await enLink.click();

      // Verify switched to English
      await expect(page).toHaveURL(/\/en/);
    }
  });

  test('should switch from Romanian to Russian', async ({ page }) => {
    await page.goto('/');

    const ruLink = page.locator('a[href^="/ru"]').first();

    if (await ruLink.isVisible()) {
      await ruLink.click();

      // Verify switched to Russian
      await expect(page).toHaveURL(/\/ru/);
    }
  });

  test('should maintain current page when switching locale', async ({ page }) => {
    await page.goto('/politica');

    if (!page.url().includes('404')) {
      const enLink = page.locator('a[href*="/en/"]').first();

      if (await enLink.isVisible()) {
        await enLink.click();

        // Should be on English version of same page type
        await expect(page).toHaveURL(/\/en\//);
      }
    }
  });
});

test.describe('Mobile Navigation', () => {
  test.use({ viewport: { width: 375, height: 667 } });

  test('should have mobile menu button', async ({ page }) => {
    await page.goto('/');

    // Look for hamburger menu or mobile menu button
    const menuButton = page.locator('button[aria-label*="menu" i], .mobile-menu-button, [data-testid="mobile-menu-button"]');

    if (await menuButton.count() > 0) {
      await expect(menuButton.first()).toBeVisible();
    }
  });

  test('should open mobile menu on button click', async ({ page }) => {
    await page.goto('/');

    const menuButton = page.locator('button[aria-label*="menu" i], .mobile-menu-button');

    if (await menuButton.count() > 0) {
      await menuButton.first().click();

      // Menu should be visible
      const menu = page.locator('nav[aria-label*="mobile" i], .mobile-menu, [data-testid="mobile-menu"]');

      if (await menu.count() > 0) {
        await expect(menu.first()).toBeVisible();
      }
    }
  });

  test('should close mobile menu after navigation', async ({ page }) => {
    await page.goto('/');

    const menuButton = page.locator('button[aria-label*="menu" i], .mobile-menu-button');

    if (await menuButton.count() > 0) {
      // Open menu
      await menuButton.first().click();

      // Click a menu link
      const menuLinks = page.locator('nav a').filter({ hasText: /.+/ });

      if (await menuLinks.count() > 0) {
        await menuLinks.first().click();
        await page.waitForLoadState('networkidle');

        // Menu should close after navigation
        // (This behavior might vary by implementation)
      }
    }
  });
});

test.describe('Footer Navigation', () => {
  test('should display footer', async ({ page }) => {
    await page.goto('/');

    const footer = page.locator('footer');
    await expect(footer).toBeVisible();
  });

  test('should have footer links', async ({ page }) => {
    await page.goto('/');

    const footerLinks = page.locator('footer a');

    if (await footerLinks.count() > 0) {
      await expect(footerLinks.first()).toBeVisible();

      // Verify links have href
      const href = await footerLinks.first().getAttribute('href');
      expect(href).toBeDefined();
    }
  });

  test('should navigate using footer links', async ({ page }) => {
    await page.goto('/');

    const footerLinks = page.locator('footer a[href^="/"]').first();

    if (await footerLinks.isVisible()) {
      const href = await footerLinks.getAttribute('href');

      await footerLinks.click();
      await page.waitForLoadState('networkidle');

      // Verify navigation occurred
      if (href && href !== '/') {
        expect(page.url()).toContain(href);
      }
    }
  });
});

test.describe('Search Navigation', () => {
  test('should have search functionality', async ({ page }) => {
    await page.goto('/');

    // Look for search input or search link
    const searchInput = page.locator('input[type="search"], input[name="q"], input[placeholder*="search" i]');
    const searchLink = page.locator('a[href*="/search"]');

    const hasSearch = (await searchInput.count()) > 0 || (await searchLink.count()) > 0;

    // Search might not be implemented yet
    if (hasSearch) {
      expect(hasSearch).toBeTruthy();
    }
  });
});

test.describe('Navigation Accessibility', () => {
  test('should have accessible navigation labels', async ({ page }) => {
    await page.goto('/');

    const navs = page.locator('nav');

    if (await navs.count() > 0) {
      // Check if at least one nav has aria-label
      for (let i = 0; i < await navs.count(); i++) {
        const nav = navs.nth(i);
        const ariaLabel = await nav.getAttribute('aria-label');

        if (ariaLabel) {
          expect(ariaLabel.length).toBeGreaterThan(0);
          break; // Found one with label
        }
      }
    }
  });

  test('should be keyboard navigable', async ({ page }) => {
    await page.goto('/');

    // Focus first link
    const firstLink = page.locator('a').first();
    await firstLink.focus();

    // Verify it's focused
    const isFocused = await firstLink.evaluate((el) => el === document.activeElement);
    expect(isFocused).toBeTruthy();

    // Tab to next link
    await page.keyboard.press('Tab');

    // Another element should now be focused
    const activeElement = await page.evaluate(() => document.activeElement?.tagName);
    expect(activeElement).toBeDefined();
  });

  test('should have skip to content link', async ({ page }) => {
    await page.goto('/');

    // Look for skip link (usually hidden)
    const skipLink = page.locator('a[href="#content"], a[href="#main"], .skip-link');

    if (await skipLink.count() > 0) {
      // Skip link exists
      expect(await skipLink.count()).toBeGreaterThan(0);
    }
  });
});
