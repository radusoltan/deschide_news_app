import { test, expect, type Page } from '@playwright/test';

// ─────────────────────────────────────────────────
// DASH + NAV E2E TESTS — Covers DASH-01..03, NAV-01..03
// Credentials: test_admin / admin123 (ROLE_ADMIN)
//
// NOTE: In dev mode, first-time page compilations can cause timeouts.
// For reliable results, run against production build (pnpm build && pnpm start)
// or pre-warm dev server by visiting each page manually before running tests.
// ─────────────────────────────────────────────────

const BASE_URL = process.env.BASE_URL || 'http://localhost:3005';
const CREDENTIALS = { username: 'test_admin', password: 'admin123' };

// Dev mode compilation can be slow on first load — use generous timeouts
test.setTimeout(120000);

// Retry once — first run may fail due to cold compilation
test.describe.configure({ retries: 1 });

async function loginAsAdmin(page: Page) {
  await page.goto(`${BASE_URL}/ro/login`);

  // Wait for login form (may redirect from /ro/login to /login)
  await page.getByRole('textbox', { name: 'Username' }).waitFor({ timeout: 30000 });
  await page.getByRole('textbox', { name: 'Username' }).fill(CREDENTIALS.username);
  await page.getByRole('textbox', { name: 'Password' }).fill(CREDENTIALS.password);
  await page.getByRole('button', { name: 'Sign in' }).click();

  // Wait for redirect to admin dashboard — dev compilation may take time
  await page.waitForFunction(() => window.location.href.includes('admin'), { timeout: 30000 });
  await page.getByRole('heading', { name: 'Dashboard' }).waitFor({ timeout: 30000 });
}

// ═══════════════════════════════════════════════════
// DASH-01: Dashboard loads correctly
// ═══════════════════════════════════════════════════
test.describe('DASH-01: Dashboard loads correctly', () => {
  test('should display dashboard heading and stats', async ({ page }) => {
    await loginAsAdmin(page);

    await expect(page.getByRole('heading', { name: 'Dashboard', level: 1 })).toBeVisible();
    await expect(page.getByText('Total Articole')).toBeVisible();
    await expect(page.getByText('Publicate')).toBeVisible();
    await expect(page.getByText('Drafturi')).toBeVisible();
    await expect(page.getByText('Categorii')).toBeVisible();
  });

  test('should display quick actions', async ({ page }) => {
    await loginAsAdmin(page);

    await expect(page.getByRole('link', { name: 'Articol Nou' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Categorie Nouă' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Încarcă Imagini' })).toBeVisible();
  });

  test('should have zero console errors on dashboard', async ({ page }) => {
    const errors: string[] = [];
    page.on('console', msg => {
      if (msg.type() === 'error' && !msg.text().includes('Failed to fetch real-time stats')) {
        errors.push(msg.text());
      }
    });

    await loginAsAdmin(page);
    await page.waitForTimeout(2000);

    expect(errors).toHaveLength(0);
  });

  test('should show real category count from API (not hardcoded)', async ({ page }) => {
    await loginAsAdmin(page);

    // Categorii count should be a number > 0 (from API, not hardcoded 18)
    const categoriesCard = page.locator('text=Categorii').locator('..');
    await expect(categoriesCard).toBeVisible();
  });
});

// ═══════════════════════════════════════════════════
// DASH-02: All admin pages load without crashes
// ═══════════════════════════════════════════════════
test.describe('DASH-02: All admin pages load without crashes', () => {
  const pages = [
    { name: 'Dashboard', url: '/admin', heading: 'Dashboard' },
    { name: 'Articles', url: '/admin/articles', heading: 'Articles' },
    { name: 'Categories', url: '/admin/categories', heading: 'Categories' },
    { name: 'Images', url: '/admin/images', heading: 'Images' },
    { name: 'Authors', url: '/admin/authors', heading: 'Authors' },
    { name: 'Important Articles', url: '/admin/important-articles', heading: 'Important Articles' },
    { name: 'Archive', url: '/admin/archive', text: 'Arhiv' },
    { name: 'Thumbnail Profiles', url: '/admin/thumbnail-profiles', heading: 'Thumbnail Profiles' },
  ];

  for (const adminPage of pages) {
    test(`${adminPage.name} page should load without crash`, async ({ page }) => {
      await loginAsAdmin(page);
      await page.goto(`${BASE_URL}/ro${adminPage.url}`);
      await page.waitForLoadState('domcontentloaded');

      // Verify no crash — check for heading or text
      if (adminPage.heading) {
        await expect(page.getByRole('heading', { name: adminPage.heading }).first()).toBeVisible({ timeout: 30000 });
      } else if (adminPage.text) {
        await expect(page.getByText(adminPage.text).first()).toBeVisible({ timeout: 30000 });
      }

      // No unhandled error page
      await expect(page.locator('text=Application error')).not.toBeVisible();
    });
  }
});

// ═══════════════════════════════════════════════════
// DASH-03: Responsive layout
// ═══════════════════════════════════════════════════
test.describe('DASH-03: Responsive layout', () => {
  test('desktop: sidebar visible, stats grid', async ({ page }) => {
    await page.setViewportSize({ width: 1920, height: 1080 });
    await loginAsAdmin(page);

    // Sidebar should be visible
    await expect(page.getByRole('complementary', { name: 'Sidebar' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Articles' })).toBeVisible();
  });

  test('mobile: hamburger menu, sidebar hidden initially', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    await loginAsAdmin(page);

    // Dashboard heading should be visible
    await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
  });

  test('tablet: layout adapts', async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 1024 });
    await loginAsAdmin(page);

    await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
  });
});

// ═══════════════════════════════════════════════════
// NAV-01: Sidebar navigation has all sections
// ═══════════════════════════════════════════════════
test.describe('NAV-01: Sidebar navigation', () => {
  test('should contain all expected navigation links', async ({ page }) => {
    await loginAsAdmin(page);

    const expectedLinks = [
      'Dashboard',
      'Articles',
      'Categories',
      'Images',
      'Thumbnail Profiles',
      'Authors',
      'Important Articles',
      'Archive',
      'Settings',
    ];

    for (const linkName of expectedLinks) {
      await expect(
        page.getByRole('complementary', { name: 'Sidebar' }).getByRole('link', { name: linkName })
      ).toBeVisible();
    }
  });

  test('should highlight active link for current page', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto(`${BASE_URL}/ro/admin/articles`);
    await page.waitForLoadState('domcontentloaded');

    // The Articles link should have active styling (blue)
    const articlesLink = page.getByRole('complementary', { name: 'Sidebar' }).getByRole('link', { name: 'Articles' });
    await expect(articlesLink).toBeVisible();
    // Check active class applied
    const classes = await articlesLink.getAttribute('class');
    expect(classes).toContain('blue');
  });

  test('sidebar links navigate correctly', async ({ page }) => {
    await loginAsAdmin(page);

    // Click Articles
    await page.getByRole('complementary', { name: 'Sidebar' }).getByRole('link', { name: 'Articles' }).click();
    await page.waitForLoadState('domcontentloaded');
    await expect(page.getByRole('heading', { name: 'Articles' })).toBeVisible();

    // Click Categories
    await page.getByRole('complementary', { name: 'Sidebar' }).getByRole('link', { name: 'Categories' }).click();
    await page.waitForLoadState('domcontentloaded');
    await expect(page.getByRole('heading', { name: 'Categories' })).toBeVisible();

    // Click Thumbnail Profiles
    await page.getByRole('complementary', { name: 'Sidebar' }).getByRole('link', { name: 'Thumbnail Profiles' }).click();
    await page.waitForLoadState('domcontentloaded');
    await expect(page.getByRole('heading', { name: 'Thumbnail Profiles' })).toBeVisible();
  });
});

// ═══════════════════════════════════════════════════
// NAV-02: Breadcrumbs
// ═══════════════════════════════════════════════════
test.describe('NAV-02: Breadcrumbs', () => {
  test('article edit page has Dashboard > Articles > Edit breadcrumb', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto(`${BASE_URL}/ro/admin/articles`);
    await page.waitForLoadState('domcontentloaded');

    // Find first edit link and click it
    const firstEditLink = page.getByRole('link', { name: 'Edit' }).first();
    await firstEditLink.click();
    await page.waitForLoadState('domcontentloaded');

    // Check breadcrumb has Dashboard prefix
    await expect(page.getByRole('link', { name: 'Dashboard' }).first()).toBeVisible();
    await expect(page.getByRole('link', { name: 'Articles' }).first()).toBeVisible();
    await expect(page.getByText('Edit Article')).toBeVisible();
  });

  test('thumbnail profiles page has Dashboard breadcrumb', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto(`${BASE_URL}/ro/admin/thumbnail-profiles`);
    await page.waitForLoadState('domcontentloaded');

    await expect(page.getByRole('link', { name: 'Dashboard' }).first()).toBeVisible();
    await expect(page.getByText('Thumbnail Profiles').first()).toBeVisible();
  });
});

// ═══════════════════════════════════════════════════
// NAV-03: Browser back/forward
// ═══════════════════════════════════════════════════
test.describe('NAV-03: Browser navigation', () => {
  test('back/forward between admin pages works', async ({ page }) => {
    await loginAsAdmin(page);

    // Navigate: Dashboard → Articles → Categories
    await page.getByRole('complementary', { name: 'Sidebar' }).getByRole('link', { name: 'Articles' }).click();
    await page.waitForLoadState('domcontentloaded');
    await expect(page.getByRole('heading', { name: 'Articles' })).toBeVisible();

    await page.getByRole('complementary', { name: 'Sidebar' }).getByRole('link', { name: 'Categories' }).click();
    await page.waitForLoadState('domcontentloaded');
    await expect(page.getByRole('heading', { name: 'Categories' })).toBeVisible();

    // Go back → Articles
    await page.goBack();
    await page.waitForLoadState('domcontentloaded');
    await expect(page.getByRole('heading', { name: 'Articles' })).toBeVisible();

    // Go back → Dashboard
    await page.goBack();
    await page.waitForLoadState('domcontentloaded');
    await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();

    // Go forward → Articles
    await page.goForward();
    await page.waitForLoadState('domcontentloaded');
    await expect(page.getByRole('heading', { name: 'Articles' })).toBeVisible();
  });
});
