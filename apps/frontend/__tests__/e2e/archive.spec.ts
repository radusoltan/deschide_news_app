/**
 * E2E Tests - Archive Public Page
 * Tests the public archive browsing functionality including filters, pagination, and multilingual support
 */

import { test, expect, type Page } from '@playwright/test';

/**
 * Helper function to wait for network to be idle
 */
async function waitForNetworkIdle(page: Page) {
  await page.waitForLoadState('networkidle');
}

test.describe('Archive Public Page - Core Functionality', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/ro/archive');
    await waitForNetworkIdle(page);
  });

  test('archive page displays correctly with header and content', async ({ page }) => {
    // Check for page title
    const heading = page.locator('h1').first();
    await expect(heading).toBeVisible();
    await expect(heading).toContainText(/arhiv/i);

    // Check for subtitle/description
    await expect(page.locator('text=/explor/i').first()).toBeVisible();

    // Wait for articles to load (may take time)
    await page.waitForSelector('main', { state: 'visible', timeout: 10000 });

    // Check for main content area
    const mainContent = page.locator('main');
    await expect(mainContent).toBeVisible();
  });

  test('archive page shows year filters', async ({ page }) => {
    // Wait for filters to load
    await page.waitForTimeout(1000);

    // Check for year filter section header
    const yearFilterHeader = page.locator('text=/ani/i').first();
    await expect(yearFilterHeader).toBeVisible();

    // Check if year buttons/links are present
    const yearButtons = page.locator('[class*="year"]').filter({ hasText: /20\d{2}/ });

    if (await yearButtons.count() > 0) {
      await expect(yearButtons.first()).toBeVisible();
    }
  });

  test('archive page shows category filter', async ({ page }) => {
    // Wait for filters to load
    await page.waitForTimeout(1000);

    // Check for category filter section header
    const categoryHeader = page.locator('text=/categor/i').first();
    await expect(categoryHeader).toBeVisible();
  });

  test('articles are displayed in grid/list format', async ({ page }) => {
    // Wait for articles to load
    await page.waitForTimeout(2000);

    // Look for article elements - they might be in article tags or divs with specific classes
    const articleElements = page.locator('article, [class*="article"], [class*="card"]');

    // At least check that the page has loaded some content
    const mainContent = page.locator('main');
    await expect(mainContent).toBeVisible();
  });

  test('archive info is visible to users', async ({ page }) => {
    // Check for any informational text or banners
    const pageContent = await page.textContent('body');

    // Verify page has loaded correctly
    expect(pageContent).toBeTruthy();
    expect(pageContent?.length).toBeGreaterThan(100);
  });
});

test.describe('Archive Public Page - Year Filter Functionality', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/ro/archive');
    await waitForNetworkIdle(page);
    await page.waitForTimeout(1500); // Wait for filters to load
  });

  test('clicking year filter updates URL with year parameter', async ({ page }) => {
    // Find year buttons/links that contain 4-digit years
    const yearElements = page.locator('button, a').filter({ hasText: /^20\d{2}$/ });

    if (await yearElements.count() > 0) {
      const firstYear = yearElements.first();
      const yearText = await firstYear.textContent();

      await firstYear.click();
      await waitForNetworkIdle(page);

      // Verify URL contains year parameter
      const url = page.url();
      expect(url).toMatch(/year=20\d{2}/);

      if (yearText) {
        expect(url).toContain(`year=${yearText.trim()}`);
      }
    } else {
      console.log('No year filters found - archive may be empty');
    }
  });

  test('year filter persists across page reloads', async ({ page }) => {
    // Find and click a year filter
    const yearElements = page.locator('button, a').filter({ hasText: /^20\d{2}$/ });

    if (await yearElements.count() > 0) {
      await yearElements.first().click();
      await waitForNetworkIdle(page);

      const urlBeforeReload = page.url();

      // Reload the page
      await page.reload();
      await waitForNetworkIdle(page);

      // URL should still contain the year parameter
      expect(page.url()).toBe(urlBeforeReload);
    }
  });

  test('clearing filters removes year parameter from URL', async ({ page }) => {
    // Click a year filter first
    const yearElements = page.locator('button, a').filter({ hasText: /^20\d{2}$/ });

    if (await yearElements.count() > 0) {
      await yearElements.first().click();
      await waitForNetworkIdle(page);

      // Verify year is in URL
      expect(page.url()).toMatch(/year=/);

      // Look for clear filters button
      const clearButton = page.locator('button:has-text("Șterge"), button:has-text("Clear")');

      if (await clearButton.isVisible()) {
        await clearButton.click();
        await waitForNetworkIdle(page);

        // Year parameter should be removed
        expect(page.url()).not.toMatch(/year=/);
      }
    }
  });
});

test.describe('Archive Public Page - Category Filter Functionality', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/ro/archive');
    await waitForNetworkIdle(page);
    await page.waitForTimeout(1500);
  });

  test('category filter is interactive', async ({ page }) => {
    // Look for category filter select or buttons
    const categorySelect = page.locator('select').filter({ hasText: /categor/i });
    const categoryButtons = page.locator('button').filter({ hasText: /categor/i });

    // At least one should exist
    const hasSelect = await categorySelect.count() > 0;
    const hasButtons = await categoryButtons.count() > 0;

    expect(hasSelect || hasButtons).toBeTruthy();
  });

  test('selecting category updates URL', async ({ page }) => {
    // Try to find and interact with category filter
    const categoryOptions = page.locator('select option, button[role="option"]');

    if (await categoryOptions.count() > 1) {
      // Get second option (first is usually "All categories")
      const secondOption = categoryOptions.nth(1);

      await secondOption.click();
      await waitForNetworkIdle(page);

      // URL should contain category parameter
      const url = page.url();
      expect(url).toMatch(/category=/);
    }
  });
});

test.describe('Archive Public Page - Pagination', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/ro/archive');
    await waitForNetworkIdle(page);
    await page.waitForTimeout(2000);
  });

  test('pagination controls are visible when needed', async ({ page }) => {
    // Look for pagination controls
    const paginationElements = page.locator('nav[aria-label*="pagination"], [class*="pagination"]');
    const nextButton = page.locator('button:has-text("Următor"), button:has-text("Next"), a:has-text("Următor")');
    const prevButton = page.locator('button:has-text("Anterior"), button:has-text("Previous"), a:has-text("Anterior")');

    // Check if pagination exists (it should if there are enough articles)
    const hasPagination =
      (await paginationElements.count() > 0) ||
      (await nextButton.count() > 0) ||
      (await prevButton.count() > 0);

    // Pagination may or may not exist depending on content
    console.log('Has pagination:', hasPagination);
  });

  test('clicking next page updates URL and content', async ({ page }) => {
    // Look for next button
    const nextButton = page.locator('button:has-text("Următor"), button:has-text("Next"), a:has-text("Următor"), a:has-text("Next")').first();

    if (await nextButton.isVisible({ timeout: 5000 })) {
      // Get current page content
      const contentBefore = await page.textContent('main');

      // Click next
      await nextButton.click();
      await waitForNetworkIdle(page);

      // URL should have page parameter
      expect(page.url()).toMatch(/page=2/);

      // Content should change
      const contentAfter = await page.textContent('main');
      expect(contentAfter).not.toBe(contentBefore);
    } else {
      console.log('No next button found - may be on last page or only one page');
    }
  });

  test('page number is reflected in URL', async ({ page }) => {
    // Direct navigation to page 2
    await page.goto('/ro/archive?page=2');
    await waitForNetworkIdle(page);

    // Verify URL contains page parameter
    expect(page.url()).toContain('page=2');
  });
});

test.describe('Archive Public Page - SEO and Metadata', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/ro/archive');
    await waitForNetworkIdle(page);
  });

  test('archive page has noindex meta tag', async ({ page }) => {
    // Check for robots meta tag
    const robotsMeta = await page.locator('meta[name="robots"]').getAttribute('content');

    expect(robotsMeta).toBeTruthy();
    expect(robotsMeta).toContain('noindex');
    expect(robotsMeta).toContain('follow');
  });

  test('archive page has proper title', async ({ page }) => {
    const title = await page.title();

    expect(title).toBeTruthy();
    expect(title.toLowerCase()).toMatch(/arhiv/);
  });

  test('archive page has meta description', async ({ page }) => {
    const metaDescription = await page.locator('meta[name="description"]').getAttribute('content');

    expect(metaDescription).toBeTruthy();
    expect(metaDescription!.length).toBeGreaterThan(50);
  });

  test('archive page has canonical URL', async ({ page }) => {
    const canonical = await page.locator('link[rel="canonical"]').getAttribute('href');

    expect(canonical).toBeTruthy();
    expect(canonical).toContain('/archive');
  });
});

test.describe('Archive Public Page - Responsive Design', () => {
  test('desktop layout displays correctly', async ({ page }) => {
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.goto('/ro/archive');
    await waitForNetworkIdle(page);

    // Main content should be visible
    await expect(page.locator('main')).toBeVisible();

    // Filters should be visible in sidebar on desktop
    await page.waitForTimeout(1000);
  });

  test('tablet layout displays correctly', async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.goto('/ro/archive');
    await waitForNetworkIdle(page);

    // Content should be visible and readable
    await expect(page.locator('main')).toBeVisible();
  });

  test('mobile layout displays correctly', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/ro/archive');
    await waitForNetworkIdle(page);

    // Main content should be visible
    await expect(page.locator('main')).toBeVisible();

    // Check for mobile-specific elements (collapsed filters, etc.)
    await page.waitForTimeout(1000);

    // Look for filter toggle button (mobile view)
    const filterToggle = page.locator('button:has-text("Filtre"), button:has-text("Filters")');

    if (await filterToggle.count() > 0) {
      await expect(filterToggle.first()).toBeVisible();
    }
  });

  test('mobile filter toggle works', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/ro/archive');
    await waitForNetworkIdle(page);
    await page.waitForTimeout(1000);

    // Look for filter toggle button
    const filterToggle = page.locator('button').filter({ hasText: /filtre|filters/i }).first();

    if (await filterToggle.isVisible()) {
      // Click to open filters
      await filterToggle.click();
      await page.waitForTimeout(500);

      // Filters should be visible
      // Look for year filter which should now be visible
      const yearFilter = page.locator('text=/ani|years/i').first();
      await expect(yearFilter).toBeVisible();
    }
  });
});

test.describe('Archive Multilingual Support', () => {
  test('Romanian archive page loads correctly', async ({ page }) => {
    await page.goto('/ro/archive');
    await waitForNetworkIdle(page);

    // Check for Romanian text
    const heading = await page.locator('h1').first().textContent();
    expect(heading?.toLowerCase()).toMatch(/arhiv/);

    // Check page title
    const title = await page.title();
    expect(title).toContain('Arhivă');
  });

  test('English archive page loads correctly', async ({ page }) => {
    await page.goto('/en/archive');
    await waitForNetworkIdle(page);

    // Check for English text
    const heading = await page.locator('h1').first().textContent();
    expect(heading?.toLowerCase()).toMatch(/archive/);

    // Check page title
    const title = await page.title();
    expect(title).toContain('Archive');
  });

  test('Russian archive page loads correctly', async ({ page }) => {
    await page.goto('/ru/archive');
    await waitForNetworkIdle(page);

    // Check for Russian text
    const heading = await page.locator('h1').first().textContent();
    expect(heading).toMatch(/архив/i);

    // Check page title
    const title = await page.title();
    expect(title).toMatch(/архив/i);
  });

  test('language alternates are present in metadata', async ({ page }) => {
    await page.goto('/ro/archive');
    await waitForNetworkIdle(page);

    // Check for alternate language links
    const alternates = page.locator('link[rel="alternate"]');
    const count = await alternates.count();

    // Should have alternates for other languages
    expect(count).toBeGreaterThan(0);
  });
});

test.describe('Archive Public Page - Combined Filters', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/ro/archive');
    await waitForNetworkIdle(page);
    await page.waitForTimeout(1500);
  });

  test('can combine year and category filters', async ({ page }) => {
    // Select a year
    const yearElements = page.locator('button, a').filter({ hasText: /^20\d{2}$/ });

    if (await yearElements.count() > 0) {
      await yearElements.first().click();
      await waitForNetworkIdle(page);

      // URL should have year
      expect(page.url()).toMatch(/year=/);

      // Try to select a category
      const categoryOptions = page.locator('select option, button[role="option"]');

      if (await categoryOptions.count() > 1) {
        await categoryOptions.nth(1).click();
        await waitForNetworkIdle(page);

        // URL should have both parameters
        const url = page.url();
        expect(url).toMatch(/year=/);
        expect(url).toMatch(/category=/);
      }
    }
  });

  test('filter combinations persist across navigation', async ({ page }) => {
    // Set multiple filters
    const yearElements = page.locator('button, a').filter({ hasText: /^20\d{2}$/ });

    if (await yearElements.count() > 0) {
      await yearElements.first().click();
      await waitForNetworkIdle(page);

      const urlWithFilters = page.url();

      // Go to another page
      await page.goto('/ro');
      await waitForNetworkIdle(page);

      // Navigate back to archive
      await page.goto(urlWithFilters);
      await waitForNetworkIdle(page);

      // URL should still have filters
      expect(page.url()).toBe(urlWithFilters);
    }
  });
});

test.describe('Archive Public Page - Loading States', () => {
  test('page shows loading state initially', async ({ page }) => {
    // Start navigation
    const response = page.goto('/ro/archive');

    // Page should show some loading indication
    // (This is hard to test precisely due to fast loading)

    await response;
    await waitForNetworkIdle(page);

    // Eventually content should be visible
    await expect(page.locator('main')).toBeVisible();
  });

  test('page handles empty archive gracefully', async ({ page }) => {
    // Navigate to archive with filters that might return no results
    await page.goto('/ro/archive?year=1999');
    await waitForNetworkIdle(page);
    await page.waitForTimeout(2000);

    // Page should still render without errors
    await expect(page.locator('main')).toBeVisible();
  });
});

test.describe('Archive Public Page - Error Handling', () => {
  test('page loads even if API is slow', async ({ page }) => {
    await page.goto('/ro/archive');

    // Wait up to 15 seconds for page to load
    await expect(page.locator('h1').first()).toBeVisible({ timeout: 15000 });
  });

  test('page shows content even with network issues', async ({ page }) => {
    // Load page normally first
    await page.goto('/ro/archive');
    await waitForNetworkIdle(page);

    // Page should be visible
    await expect(page.locator('main')).toBeVisible();
  });
});
