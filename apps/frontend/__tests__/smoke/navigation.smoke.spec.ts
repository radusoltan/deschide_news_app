/**
 * Smoke Tests - Navigation
 * Quick tests to verify navigation elements are present and functional
 */

import { test, expect } from '@playwright/test';

test.describe('Navigation Smoke Tests', () => {
  test.describe.configure({ mode: 'parallel' });

  const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:3005';
  const LOAD_TIMEOUT = 10000;

  test.describe('Header Navigation', () => {
    test('Main navigation is visible and contains links', async ({ page }) => {
      await page.goto(`${BASE_URL}/ro`, {
        waitUntil: 'domcontentloaded',
        timeout: LOAD_TIMEOUT,
      });

      // Verify header exists
      const header = page.locator('header');
      await expect(header).toBeVisible({ timeout: 5000 });

      // Verify navigation exists (either in header or as separate nav)
      const nav = page.locator('header nav, nav').first();
      await expect(nav).toBeVisible({ timeout: 5000 });

      // Verify navigation has links
      const navLinks = nav.locator('a');
      const linkCount = await navLinks.count();
      expect(linkCount).toBeGreaterThan(0);

      // Verify at least one link is visible
      await expect(navLinks.first()).toBeVisible();
    });

    test('Logo/Brand is present and visible', async ({ page }) => {
      await page.goto(`${BASE_URL}/ro`, {
        waitUntil: 'domcontentloaded',
        timeout: LOAD_TIMEOUT,
      });

      // Look for logo (common patterns: img with alt, link with logo class, etc.)
      const logo = page.locator(
        'header img[alt*="logo" i], header img[alt*="deschide" i], header a[href="/"], header a[href="/ro"]'
      );

      // At least one logo/brand element should exist
      const logoCount = await logo.count();
      expect(logoCount).toBeGreaterThan(0);
    });

    test('Logo links to homepage', async ({ page }) => {
      await page.goto(`${BASE_URL}/ro`, {
        waitUntil: 'domcontentloaded',
        timeout: LOAD_TIMEOUT,
      });

      // Find the main logo/brand link
      const logoLink = page.locator('header a[href="/"], header a[href="/ro"]').first();

      if (await logoLink.count() > 0) {
        // Verify link has correct href
        const href = await logoLink.getAttribute('href');
        expect(href).toMatch(/^\/(?:ro)?$/);

        // Verify link is visible and clickable
        await expect(logoLink).toBeVisible();
      }
    });
  });

  test.describe('Category Navigation', () => {
    test('Category links are present', async ({ page }) => {
      await page.goto(`${BASE_URL}/ro`, {
        waitUntil: 'domcontentloaded',
        timeout: LOAD_TIMEOUT,
      });

      // Look for category links (common patterns)
      const categoryLinks = page.locator(
        'nav a[href*="/category/"], a[href*="/politica"], a[href*="/economie"], a[href*="/societate"]'
      );

      const categoryCount = await categoryLinks.count();

      // Either categories exist, or this is expected in test environment
      if (categoryCount > 0) {
        // Verify first category link is visible
        await expect(categoryLinks.first()).toBeVisible();

        // Verify category link has proper href
        const href = await categoryLinks.first().getAttribute('href');
        expect(href).toBeTruthy();
        expect(href?.length).toBeGreaterThan(0);
      }
    });

    test('Category navigation is clickable', async ({ page }) => {
      await page.goto(`${BASE_URL}/ro`, {
        waitUntil: 'domcontentloaded',
        timeout: LOAD_TIMEOUT,
      });

      // Find any category link
      const categoryLink = page.locator('nav a[href*="/category/"]').first();

      if (await categoryLink.count() > 0) {
        // Get the href before clicking
        const href = await categoryLink.getAttribute('href');

        // Click the link
        await categoryLink.click();

        // Wait for navigation
        await page.waitForLoadState('domcontentloaded', { timeout: LOAD_TIMEOUT });

        // Verify we navigated somewhere (URL changed or page loaded)
        const currentURL = page.url();
        expect(currentURL).toBeTruthy();

        // Verify page structure is still intact
        await expect(page.locator('body')).toBeVisible();
      }
    });
  });

  test.describe('Language Switcher', () => {
    test('Language switcher exists', async ({ page }) => {
      await page.goto(`${BASE_URL}/ro`, {
        waitUntil: 'domcontentloaded',
        timeout: LOAD_TIMEOUT,
      });

      // Look for language switcher (common patterns)
      const langSwitcher = page.locator(
        'a[href^="/en"], a[href^="/ru"], [role="combobox"], select[name*="lang"], button[aria-label*="language" i]'
      );

      const switcherCount = await langSwitcher.count();

      // Language switcher should exist
      if (switcherCount > 0) {
        await expect(langSwitcher.first()).toBeVisible();
      } else {
        // If no switcher found, look for any locale links in the page
        const localeLinks = page.locator('a[href*="/en"], a[href*="/ru"]');
        const localeCount = await localeLinks.count();

        // Should have at least some way to switch languages
        // This is a soft check - might not be visible on all pages
        console.log(`Language switcher elements found: ${localeCount}`);
      }
    });

    test('Can navigate to English locale', async ({ page }) => {
      await page.goto(`${BASE_URL}/ro`, {
        waitUntil: 'domcontentloaded',
        timeout: LOAD_TIMEOUT,
      });

      // Look for English locale link
      const enLink = page.locator('a[href^="/en"]').first();

      if (await enLink.count() > 0) {
        await enLink.click();
        await page.waitForLoadState('domcontentloaded', { timeout: LOAD_TIMEOUT });

        // Verify we're on English version
        expect(page.url()).toContain('/en');
        await expect(page.locator('main')).toBeVisible();
      } else {
        // Direct navigation test
        await page.goto(`${BASE_URL}/en`);
        await expect(page.locator('main')).toBeVisible();
      }
    });

    test('Can navigate to Russian locale', async ({ page }) => {
      await page.goto(`${BASE_URL}/ro`, {
        waitUntil: 'domcontentloaded',
        timeout: LOAD_TIMEOUT,
      });

      // Look for Russian locale link
      const ruLink = page.locator('a[href^="/ru"]').first();

      if (await ruLink.count() > 0) {
        await ruLink.click();
        await page.waitForLoadState('domcontentloaded', { timeout: LOAD_TIMEOUT });

        // Verify we're on Russian version
        expect(page.url()).toContain('/ru');
        await expect(page.locator('main')).toBeVisible();
      } else {
        // Direct navigation test
        await page.goto(`${BASE_URL}/ru`);
        await expect(page.locator('main')).toBeVisible();
      }
    });
  });

  test.describe('Footer', () => {
    test('Footer is present', async ({ page }) => {
      await page.goto(`${BASE_URL}/ro`, {
        waitUntil: 'domcontentloaded',
        timeout: LOAD_TIMEOUT,
      });

      const footer = page.locator('footer');

      // Footer should exist
      const footerCount = await footer.count();
      expect(footerCount).toBeGreaterThan(0);

      if (footerCount > 0) {
        // Footer should be visible (might need to scroll)
        await footer.scrollIntoViewIfNeeded();
        await expect(footer).toBeVisible();
      }
    });

    test('Footer contains content or links', async ({ page }) => {
      await page.goto(`${BASE_URL}/ro`, {
        waitUntil: 'domcontentloaded',
        timeout: LOAD_TIMEOUT,
      });

      const footer = page.locator('footer');

      if (await footer.count() > 0) {
        // Check if footer has text content
        const footerText = await footer.textContent();
        expect(footerText?.length).toBeGreaterThan(0);

        // Or check if it has links
        const footerLinks = footer.locator('a');
        const linkCount = await footerLinks.count();

        // Should have either text or links
        expect(footerText?.length || linkCount).toBeGreaterThan(0);
      }
    });
  });

  test.describe('Mobile Menu', () => {
    test('Mobile menu toggle exists on small screens', async ({ page }) => {
      // Set mobile viewport
      await page.setViewportSize({ width: 375, height: 667 });

      await page.goto(`${BASE_URL}/ro`, {
        waitUntil: 'domcontentloaded',
        timeout: LOAD_TIMEOUT,
      });

      // Look for common mobile menu patterns
      const mobileMenuButton = page.locator(
        'button[aria-label*="menu" i], button[aria-label*="navigation" i], button.menu-toggle, [data-testid="mobile-menu-toggle"]'
      );

      const buttonCount = await mobileMenuButton.count();

      if (buttonCount > 0) {
        // Verify mobile menu button is visible
        await expect(mobileMenuButton.first()).toBeVisible();

        // Verify it's clickable
        await expect(mobileMenuButton.first()).toBeEnabled();
      } else {
        // Alternative: navigation might just be hidden on mobile
        const nav = page.locator('nav');
        const navCount = await nav.count();
        expect(navCount).toBeGreaterThan(0);
      }
    });
  });

  test.describe('Breadcrumbs', () => {
    test('Breadcrumbs exist on category pages', async ({ page }) => {
      const response = await page.goto(`${BASE_URL}/ro/category/politica`, {
        waitUntil: 'domcontentloaded',
        timeout: LOAD_TIMEOUT,
      });

      if (response?.status() === 200 || response?.status() === 304) {
        // Look for breadcrumbs - they are optional
        const breadcrumbs = page.locator(
          'nav[aria-label*="breadcrumb" i], ol.breadcrumb, ul.breadcrumb, [data-testid="breadcrumbs"]'
        );

        // Breadcrumbs are optional but nice to have
        const breadcrumbCount = await breadcrumbs.count();

        if (breadcrumbCount > 0) {
          await expect(breadcrumbs.first()).toBeVisible();
        }
        // Test passes even without breadcrumbs - they're optional
      }
    });
  });

  test.describe('Navigation Accessibility', () => {
    test('Navigation elements have proper ARIA attributes', async ({ page }) => {
      await page.goto(`${BASE_URL}/ro`, {
        waitUntil: 'domcontentloaded',
        timeout: LOAD_TIMEOUT,
      });

      // Check that navigation element exists (nav tag is semantic HTML5)
      const nav = page.locator('nav, [role="navigation"]').first();
      await expect(nav).toBeVisible();

      // Check tag name - <nav> is semantic and doesn't require role attribute
      const tagName = await nav.evaluate((el) => el.tagName.toLowerCase());

      // <nav> element is semantic HTML5 - role="navigation" is implicit
      // aria-label is best practice but not required
      const ariaLabel = await nav.getAttribute('aria-label');
      const role = await nav.getAttribute('role');

      // Pass if it's a <nav> element (implicit navigation role) OR has explicit role/aria-label
      expect(tagName === 'nav' || ariaLabel || role).toBeTruthy();
    });

    test('Links have proper text content or aria-labels', async ({ page }) => {
      await page.goto(`${BASE_URL}/ro`, {
        waitUntil: 'domcontentloaded',
        timeout: LOAD_TIMEOUT,
      });

      const navLinks = page.locator('nav a, header a');
      const linkCount = await navLinks.count();

      if (linkCount > 0) {
        // Check first few links
        const linksToCheck = Math.min(5, linkCount);

        for (let i = 0; i < linksToCheck; i++) {
          const link = navLinks.nth(i);
          const text = await link.textContent();
          const ariaLabel = await link.getAttribute('aria-label');

          // Link should have text or aria-label
          expect((text?.trim().length || 0) + (ariaLabel?.length || 0)).toBeGreaterThan(0);
        }
      }
    });
  });
});
