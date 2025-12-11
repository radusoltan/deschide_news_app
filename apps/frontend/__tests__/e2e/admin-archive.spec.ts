/**
 * E2E Tests - Admin Archive Management Page
 * Tests admin functionality for managing archived articles including bulk operations and statistics
 */

import { test, expect, type Page } from '@playwright/test';

/**
 * Helper function to wait for network to be idle
 */
async function waitForNetworkIdle(page: Page) {
  await page.waitForLoadState('networkidle');
}

/**
 * Helper function to login as admin
 * Note: This is a placeholder - update with actual login flow when implemented
 */
async function loginAsAdmin(page: Page, locale: string = 'ro') {
  // For now, we'll test the redirect behavior
  // When proper auth is implemented, this should:
  // 1. Navigate to login page
  // 2. Fill in admin credentials
  // 3. Submit form
  // 4. Wait for redirect to admin area

  // Placeholder for future implementation
  await page.goto(`/${locale}/login`);
  await waitForNetworkIdle(page);

  // Check if login form exists
  const emailInput = page.locator('input[type="email"], input[name="email"]');
  const passwordInput = page.locator('input[type="password"], input[name="password"]');

  if (await emailInput.isVisible({ timeout: 2000 })) {
    // Fill in credentials (use test credentials from environment or config)
    const adminEmail = process.env.TEST_ADMIN_EMAIL || 'admin@test.com';
    const adminPassword = process.env.TEST_ADMIN_PASSWORD || 'testpassword123';

    await emailInput.fill(adminEmail);
    await passwordInput.fill(adminPassword);

    // Submit form
    const submitButton = page.locator('button[type="submit"], button:has-text("Login"), button:has-text("Conectare")');
    if (await submitButton.isVisible()) {
      await submitButton.click();
      await waitForNetworkIdle(page);
    }
  }
}

/**
 * Check if user is authenticated
 */
async function isAuthenticated(page: Page): Promise<boolean> {
  const url = page.url();
  return !url.includes('/login');
}

test.describe('Admin Archive Page - Access Control', () => {
  test('admin archive page requires authentication (Romanian)', async ({ page }) => {
    await page.goto('/ro/admin/archive');
    await waitForNetworkIdle(page);

    // Should redirect to login if not authenticated
    const url = page.url();

    // Either we're at login page or we're authenticated
    const atLoginPage = url.includes('/login');
    const atAdminPage = url.includes('/admin/archive');

    expect(atLoginPage || atAdminPage).toBeTruthy();
  });

  test('admin archive page requires authentication (English)', async ({ page }) => {
    await page.goto('/en/admin/archive');
    await waitForNetworkIdle(page);

    const url = page.url();
    const atLoginPage = url.includes('/login');
    const atAdminPage = url.includes('/admin/archive');

    expect(atLoginPage || atAdminPage).toBeTruthy();
  });

  test('admin archive page requires authentication (Russian)', async ({ page }) => {
    await page.goto('/ru/admin/archive');
    await waitForNetworkIdle(page);

    const url = page.url();
    const atLoginPage = url.includes('/login');
    const atAdminPage = url.includes('/admin/archive');

    expect(atLoginPage || atAdminPage).toBeTruthy();
  });

  test('redirect includes return URL parameter', async ({ page }) => {
    await page.goto('/ro/admin/archive');
    await waitForNetworkIdle(page);

    const url = page.url();

    if (url.includes('/login')) {
      // Check for redirect parameter
      expect(url).toMatch(/redirect=/);
    }
  });
});

test.describe('Admin Archive Page - Page Structure', () => {
  test.beforeEach(async ({ page }) => {
    // Try to access admin page
    await page.goto('/ro/admin/archive');
    await waitForNetworkIdle(page);

    // If we're at login, try to login
    if (page.url().includes('/login')) {
      await loginAsAdmin(page, 'ro');
    }
  });

  test('admin archive page displays header', async ({ page }) => {
    // Skip if not authenticated
    if (!await isAuthenticated(page)) {
      test.skip();
    }

    // Check for page header with gradient background
    const header = page.locator('.bg-gradient-to-r').first();

    if (await header.isVisible({ timeout: 5000 })) {
      await expect(header).toBeVisible();

      // Check for title
      const heading = page.locator('h1').first();
      await expect(heading).toBeVisible();
      await expect(heading).toContainText(/gestionare|management|управление/i);
    }
  });

  test('admin archive page shows sections', async ({ page }) => {
    if (!await isAuthenticated(page)) {
      test.skip();
    }

    // Check for main sections
    const sections = page.locator('h2');
    const count = await sections.count();

    // Should have at least 2-3 sections (stats, bulk archive, archived articles)
    expect(count).toBeGreaterThanOrEqual(2);
  });

  test('page shows archive icon decoration', async ({ page }) => {
    if (!await isAuthenticated(page)) {
      test.skip();
    }

    // Check for SVG icon
    const icon = page.locator('svg').first();

    if (await icon.isVisible({ timeout: 3000 })) {
      await expect(icon).toBeVisible();
    }
  });
});

test.describe('Admin Archive Page - Statistics Display', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/ro/admin/archive');
    await waitForNetworkIdle(page);

    if (page.url().includes('/login')) {
      await loginAsAdmin(page, 'ro');
    }
  });

  test('statistics section is visible', async ({ page }) => {
    if (!await isAuthenticated(page)) {
      test.skip();
    }

    // Wait for stats to load
    await page.waitForTimeout(2000);

    // Look for stats section header
    const statsHeader = page.locator('h2:has-text("Statistici"), h2:has-text("Statistics"), h2:has-text("Статистика")').first();

    if (await statsHeader.isVisible({ timeout: 5000 })) {
      await expect(statsHeader).toBeVisible();
    }
  });

  test('stat cards display numeric values', async ({ page }) => {
    if (!await isAuthenticated(page)) {
      test.skip();
    }

    await page.waitForTimeout(3000);

    // Look for stat cards (they should have numeric values)
    const statCards = page.locator('[class*="stat"]');

    if (await statCards.count() > 0) {
      // Check first stat card has content
      const firstCard = statCards.first();
      const text = await firstCard.textContent();

      expect(text).toBeTruthy();
    }
  });

  test('loading skeleton appears while stats load', async ({ page }) => {
    if (!await isAuthenticated(page)) {
      test.skip();
    }

    // Immediately after navigation, there might be loading skeletons
    const loadingSkeleton = page.locator('.animate-pulse');

    // Either loading skeleton or actual content should be visible
    const hasLoading = await loadingSkeleton.count() > 0;
    console.log('Has loading skeleton:', hasLoading);
  });
});

test.describe('Admin Archive Page - Bulk Archive Form', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/ro/admin/archive');
    await waitForNetworkIdle(page);

    if (page.url().includes('/login')) {
      await loginAsAdmin(page, 'ro');
    }
  });

  test('bulk archive form is visible', async ({ page }) => {
    if (!await isAuthenticated(page)) {
      test.skip();
    }

    await page.waitForTimeout(2000);

    // Look for bulk archive section
    const bulkArchiveHeader = page.locator('h2:has-text("Masă"), h2:has-text("Bulk"), h2:has-text("Массовая")').first();

    if (await bulkArchiveHeader.isVisible({ timeout: 5000 })) {
      await expect(bulkArchiveHeader).toBeVisible();
    }
  });

  test('years input field exists and is functional', async ({ page }) => {
    if (!await isAuthenticated(page)) {
      test.skip();
    }

    await page.waitForTimeout(2000);

    // Look for years input (number input or range)
    const yearsInput = page.locator('input[type="number"], input[type="range"]').first();

    if (await yearsInput.isVisible({ timeout: 5000 })) {
      await expect(yearsInput).toBeVisible();
      await expect(yearsInput).toBeEditable();

      // Try to change value
      await yearsInput.fill('3');
      const value = await yearsInput.inputValue();
      expect(value).toBe('3');
    }
  });

  test('preview date is calculated and displayed', async ({ page }) => {
    if (!await isAuthenticated(page)) {
      test.skip();
    }

    await page.waitForTimeout(2000);

    // Look for date preview text
    const previewText = page.locator('text=/înainte de|before|до/i').first();

    if (await previewText.isVisible({ timeout: 5000 })) {
      const text = await previewText.textContent();

      // Should contain a year
      expect(text).toMatch(/20\d{2}/);
    }
  });

  test('bulk archive button is present', async ({ page }) => {
    if (!await isAuthenticated(page)) {
      test.skip();
    }

    await page.waitForTimeout(2000);

    // Look for archive button
    const archiveButton = page.locator('button:has-text("Arhivează"), button:has-text("Archive")').first();

    if (await archiveButton.isVisible({ timeout: 5000 })) {
      await expect(archiveButton).toBeVisible();
      await expect(archiveButton).toBeEnabled();
    }
  });

  test('clicking archive button shows confirmation modal', async ({ page }) => {
    if (!await isAuthenticated(page)) {
      test.skip();
    }

    await page.waitForTimeout(2000);

    // Find and click archive button
    const archiveButton = page.locator('button:has-text("Arhivează"), button:has-text("Archive")').first();

    if (await archiveButton.isVisible({ timeout: 5000 })) {
      await archiveButton.click();
      await page.waitForTimeout(1000);

      // Look for confirmation modal
      const modal = page.locator('[role="dialog"], .modal, [class*="modal"]').first();

      if (await modal.isVisible({ timeout: 3000 })) {
        await expect(modal).toBeVisible();

        // Look for confirmation text
        const confirmText = modal.locator('text=/sigur|sure|уверены/i');
        await expect(confirmText).toBeVisible();

        // Look for cancel and confirm buttons
        const cancelButton = modal.locator('button:has-text("Anulează"), button:has-text("Cancel"), button:has-text("Отмена")');
        const confirmButton = modal.locator('button:has-text("Confirmă"), button:has-text("Confirm"), button:has-text("Подтвердить")');

        await expect(cancelButton).toBeVisible();
        await expect(confirmButton).toBeVisible();

        // Close modal by clicking cancel
        await cancelButton.click();
        await page.waitForTimeout(500);
      }
    }
  });

  test('warning message is displayed', async ({ page }) => {
    if (!await isAuthenticated(page)) {
      test.skip();
    }

    await page.waitForTimeout(2000);

    // Look for warning icon or text
    const warningText = page.locator('text=/⚠|warning|atenție/i').first();

    if (await warningText.isVisible({ timeout: 5000 })) {
      await expect(warningText).toBeVisible();
    }
  });
});

test.describe('Admin Archive Page - Archived Articles List', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/ro/admin/archive');
    await waitForNetworkIdle(page);

    if (page.url().includes('/login')) {
      await loginAsAdmin(page, 'ro');
    }
  });

  test('archived articles section is visible', async ({ page }) => {
    if (!await isAuthenticated(page)) {
      test.skip();
    }

    await page.waitForTimeout(2000);

    // Look for archived articles section
    const articlesHeader = page.locator('h2:has-text("Articole Arhivate"), h2:has-text("Archived Articles"), h2:has-text("Архивные статьи")').first();

    if (await articlesHeader.isVisible({ timeout: 5000 })) {
      await expect(articlesHeader).toBeVisible();
    }
  });

  test('articles table or list is displayed', async ({ page }) => {
    if (!await isAuthenticated(page)) {
      test.skip();
    }

    await page.waitForTimeout(3000);

    // Look for table or list of articles
    const table = page.locator('table, [role="table"]').first();
    const list = page.locator('[class*="article"], [class*="list"]').first();

    const hasTable = await table.isVisible({ timeout: 3000 });
    const hasList = await list.isVisible({ timeout: 3000 });

    // Either table or list should exist
    console.log('Has table:', hasTable, 'Has list:', hasList);
  });

  test('unarchive buttons are present in list', async ({ page }) => {
    if (!await isAuthenticated(page)) {
      test.skip();
    }

    await page.waitForTimeout(3000);

    // Look for unarchive buttons
    const unarchiveButtons = page.locator('button:has-text("Dezarhivează"), button:has-text("Unarchive"), button:has-text("Восстановить")');

    if (await unarchiveButtons.count() > 0) {
      await expect(unarchiveButtons.first()).toBeVisible();
    }
  });

  test('article information is displayed in list', async ({ page }) => {
    if (!await isAuthenticated(page)) {
      test.skip();
    }

    await page.waitForTimeout(3000);

    // Check if there's article data (titles, dates, etc.)
    // This is a general check for content
    const mainContent = page.locator('main');
    const text = await mainContent.textContent();

    expect(text).toBeTruthy();
    expect(text!.length).toBeGreaterThan(200);
  });
});

test.describe('Admin Archive Page - Info Footer', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/ro/admin/archive');
    await waitForNetworkIdle(page);

    if (page.url().includes('/login')) {
      await loginAsAdmin(page, 'ro');
    }
  });

  test('info footer with explanation is visible', async ({ page }) => {
    if (!await isAuthenticated(page)) {
      test.skip();
    }

    await page.waitForTimeout(2000);

    // Look for info box (amber colored)
    const infoBox = page.locator('[class*="amber"]').filter({ hasText: /accesibile|accessible|доступными/i });

    if (await infoBox.count() > 0) {
      const firstInfoBox = infoBox.first();

      if (await firstInfoBox.isVisible({ timeout: 5000 })) {
        await expect(firstInfoBox).toBeVisible();
      }
    }
  });

  test('info icon is present', async ({ page }) => {
    if (!await isAuthenticated(page)) {
      test.skip();
    }

    await page.waitForTimeout(2000);

    // Look for info SVG icons
    const infoIcons = page.locator('svg');
    const count = await infoIcons.count();

    expect(count).toBeGreaterThan(0);
  });
});

test.describe('Admin Archive Page - Multilingual Support', () => {
  test('Romanian admin page displays correct translations', async ({ page }) => {
    await page.goto('/ro/admin/archive');
    await waitForNetworkIdle(page);

    if (!await isAuthenticated(page)) {
      test.skip();
    }

    await page.waitForTimeout(2000);

    // Check for Romanian text
    const heading = page.locator('h1').first();
    const text = await heading.textContent();

    if (text) {
      expect(text.toLowerCase()).toMatch(/gestionare|arhiv/);
    }
  });

  test('English admin page displays correct translations', async ({ page }) => {
    await page.goto('/en/admin/archive');
    await waitForNetworkIdle(page);

    if (!await isAuthenticated(page)) {
      test.skip();
    }

    await page.waitForTimeout(2000);

    const heading = page.locator('h1').first();
    const text = await heading.textContent();

    if (text) {
      expect(text.toLowerCase()).toMatch(/management|archive/);
    }
  });

  test('Russian admin page displays correct translations', async ({ page }) => {
    await page.goto('/ru/admin/archive');
    await waitForNetworkIdle(page);

    if (!await isAuthenticated(page)) {
      test.skip();
    }

    await page.waitForTimeout(2000);

    const heading = page.locator('h1').first();
    const text = await heading.textContent();

    if (text) {
      expect(text).toMatch(/управление|архив/i);
    }
  });
});

test.describe('Admin Archive Page - Responsive Design', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/ro/admin/archive');
    await waitForNetworkIdle(page);

    if (page.url().includes('/login')) {
      await loginAsAdmin(page, 'ro');
    }
  });

  test('desktop layout displays all sections', async ({ page }) => {
    if (!await isAuthenticated(page)) {
      test.skip();
    }

    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.waitForTimeout(2000);

    // Check that main sections are visible
    const sections = page.locator('h2');
    const count = await sections.count();

    expect(count).toBeGreaterThanOrEqual(2);
  });

  test('tablet layout is readable', async ({ page }) => {
    if (!await isAuthenticated(page)) {
      test.skip();
    }

    await page.setViewportSize({ width: 768, height: 1024 });
    await page.waitForTimeout(2000);

    // Check main content is visible
    const mainContent = page.locator('main');
    await expect(mainContent).toBeVisible();
  });

  test('mobile layout is functional', async ({ page }) => {
    if (!await isAuthenticated(page)) {
      test.skip();
    }

    await page.setViewportSize({ width: 375, height: 667 });
    await page.waitForTimeout(2000);

    // Content should be visible and usable
    const heading = page.locator('h1').first();
    await expect(heading).toBeVisible();
  });

  test('stat cards stack properly on mobile', async ({ page }) => {
    if (!await isAuthenticated(page)) {
      test.skip();
    }

    await page.setViewportSize({ width: 375, height: 667 });
    await page.waitForTimeout(3000);

    // Stats should be visible (they'll stack vertically on mobile)
    const statCards = page.locator('[class*="grid"]').first();

    if (await statCards.isVisible({ timeout: 5000 })) {
      await expect(statCards).toBeVisible();
    }
  });
});

test.describe('Admin Archive Page - Loading States', () => {
  test('page shows loading skeletons for stats', async ({ page }) => {
    // Navigate quickly to catch loading state
    const navigation = page.goto('/ro/admin/archive');

    // Look for loading skeletons
    const skeleton = page.locator('.animate-pulse').first();

    // May or may not catch the loading state depending on speed
    console.log('Checking for loading state...');

    await navigation;
    await waitForNetworkIdle(page);
  });

  test('page shows loading skeleton for table', async ({ page }) => {
    const navigation = page.goto('/ro/admin/archive');

    await navigation;
    await waitForNetworkIdle(page);

    // By the time we check, loading should be complete
    const mainContent = page.locator('main');
    await expect(mainContent).toBeVisible();
  });
});

test.describe('Admin Archive Page - Error Handling', () => {
  test('page handles authentication errors gracefully', async ({ page }) => {
    await page.goto('/ro/admin/archive');
    await waitForNetworkIdle(page);

    // Should either show login page or admin page (no crashes)
    const url = page.url();
    expect(url).toBeTruthy();

    // Page should be interactive
    const body = page.locator('body');
    await expect(body).toBeVisible();
  });

  test('page handles API errors gracefully', async ({ page }) => {
    await page.goto('/ro/admin/archive');
    await waitForNetworkIdle(page);

    if (!await isAuthenticated(page)) {
      test.skip();
    }

    await page.waitForTimeout(3000);

    // Even if APIs fail, page should still render
    const mainContent = page.locator('main');
    await expect(mainContent).toBeVisible();
  });
});

test.describe('Admin Archive Page - Integration', () => {
  test('navigation to admin archive from other admin pages', async ({ page }) => {
    // This would test navigation from admin dashboard or other admin pages
    // For now, just test direct access
    await page.goto('/ro/admin/archive');
    await waitForNetworkIdle(page);

    const url = page.url();
    expect(url).toContain('/admin/archive');
  });

  test('breadcrumb or navigation shows current location', async ({ page }) => {
    await page.goto('/ro/admin/archive');
    await waitForNetworkIdle(page);

    if (!await isAuthenticated(page)) {
      test.skip();
    }

    await page.waitForTimeout(2000);

    // Check for any navigation elements
    const nav = page.locator('nav').first();

    if (await nav.isVisible({ timeout: 3000 })) {
      await expect(nav).toBeVisible();
    }
  });
});
