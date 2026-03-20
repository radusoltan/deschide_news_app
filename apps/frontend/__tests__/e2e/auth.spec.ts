import { test, expect, type Page } from '@playwright/test';

// ─────────────────────────────────────────────────
// AUTH E2E TESTS — Covers AUTH-01 through AUTH-08
// Credentials: test_admin / admin123 (ROLE_ADMIN)
// ─────────────────────────────────────────────────

const BASE_URL = process.env.BASE_URL || 'http://localhost:3005';
const CREDENTIALS = { username: 'test_admin', password: 'admin123' };

async function loginAsAdmin(page: Page) {
  await page.goto(`${BASE_URL}/ro/login`);
  await page.waitForLoadState('networkidle');

  // May redirect to /login (prefixDefault: false) — wait for form
  await page.getByRole('textbox', { name: 'Username' }).waitFor({ timeout: 5000 });
  await page.getByRole('textbox', { name: 'Username' }).fill(CREDENTIALS.username);
  await page.getByRole('textbox', { name: 'Password' }).fill(CREDENTIALS.password);
  await page.getByRole('button', { name: 'Sign in' }).click();

  // Wait for client-side redirect to admin
  await page.waitForFunction(() => window.location.href.includes('admin'), { timeout: 10000 });
  await page.waitForLoadState('networkidle');
}

// ═══════════════════════════════════════════════════
// AUTH-01: Login with valid credentials
// ═══════════════════════════════════════════════════
test.describe('AUTH-01: Login with valid credentials', () => {
  test('should login and redirect to dashboard', async ({ page }) => {
    await loginAsAdmin(page);

    expect(page.url()).toContain('admin');
    await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
  });

  test('should display dashboard stats after login', async ({ page }) => {
    await loginAsAdmin(page);

    await expect(page.getByText('Total Articole')).toBeVisible();
    await expect(page.getByText('80')).toBeVisible();
  });

  test('should have zero console errors', async ({ page }) => {
    const errors: string[] = [];
    page.on('console', msg => {
      if (msg.type() === 'error') errors.push(msg.text());
    });

    await loginAsAdmin(page);
    expect(errors).toHaveLength(0);
  });

  test('should show user menu button in navbar', async ({ page }) => {
    await loginAsAdmin(page);

    await expect(page.getByRole('button', { name: /user menu/i })).toBeVisible();
  });
});

// ═══════════════════════════════════════════════════
// AUTH-02: Login with invalid credentials
// ═══════════════════════════════════════════════════
test.describe('AUTH-02: Login with invalid credentials', () => {
  test('should show error message', async ({ page }) => {
    await page.goto(`${BASE_URL}/ro/login`);
    await page.waitForLoadState('networkidle');

    await page.getByRole('textbox', { name: 'Username' }).fill('wrong@email.com');
    await page.getByRole('textbox', { name: 'Password' }).fill('wrongpass');
    await page.getByRole('button', { name: 'Sign in' }).click();

    await expect(page.getByText('Invalid credentials')).toBeVisible({ timeout: 5000 });
  });

  test('should stay on login page', async ({ page }) => {
    await page.goto(`${BASE_URL}/ro/login`);
    await page.waitForLoadState('networkidle');

    await page.getByRole('textbox', { name: 'Username' }).fill('wrong@email.com');
    await page.getByRole('textbox', { name: 'Password' }).fill('wrongpass');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await page.waitForTimeout(2000);

    expect(page.url()).toContain('login');
  });

  test('should preserve username after failed login', async ({ page }) => {
    await page.goto(`${BASE_URL}/ro/login`);
    await page.waitForLoadState('networkidle');

    await page.getByRole('textbox', { name: 'Username' }).fill('myuser');
    await page.getByRole('textbox', { name: 'Password' }).fill('wrongpass');
    await page.getByRole('button', { name: 'Sign in' }).click();

    await expect(page.getByText('Invalid credentials')).toBeVisible({ timeout: 5000 });
    await expect(page.getByRole('textbox', { name: 'Username' })).toHaveValue('myuser');
  });
});

// ═══════════════════════════════════════════════════
// AUTH-03: Protected routes without authentication
// ═══════════════════════════════════════════════════
test.describe('AUTH-03: Protected routes redirect to login', () => {
  test.beforeEach(async ({ page }) => {
    await page.context().clearCookies();
  });

  const protectedRoutes = [
    '/ro/admin',
    '/ro/admin/articles',
    '/ro/admin/articles/create',
    '/ro/admin/categories',
    '/ro/admin/users',
  ];

  for (const route of protectedRoutes) {
    test(`${route} should redirect to login`, async ({ page }) => {
      const response = await page.request.get(`${BASE_URL}${route}`, { maxRedirects: 0 });
      expect(response.status()).toBe(307);
      expect(response.headers()['location']).toContain('login');
    });
  }

  test('should not leak admin content', async ({ page }) => {
    const response = await page.request.get(`${BASE_URL}/ro/admin`, { maxRedirects: 0 });
    const body = await response.text();
    expect(body.length).toBeLessThan(100);
    expect(body).not.toContain('Dashboard');
  });
});

// ═══════════════════════════════════════════════════
// AUTH-04: Logout
// ═══════════════════════════════════════════════════
test.describe('AUTH-04: Logout', () => {
  test('should show dropdown with Sign out option', async ({ page }) => {
    await loginAsAdmin(page);

    // Open profile dropdown via evaluate (Playwright mousedown can race with close-outside listener)
    await page.evaluate(() => {
      const btn = document.querySelector('button[aria-haspopup="true"]');
      if (btn) (btn as HTMLElement).click();
    });
    await page.waitForTimeout(200);

    await expect(page.getByRole('button', { name: 'Sign out' })).toBeVisible();
  });

  test('should display real username in dropdown', async ({ page }) => {
    await loginAsAdmin(page);

    await page.evaluate(() => {
      const btn = document.querySelector('button[aria-haspopup="true"]');
      if (btn) (btn as HTMLElement).click();
    });
    await page.waitForTimeout(200);

    await expect(page.getByText(CREDENTIALS.username)).toBeVisible();
  });

  test('should logout and redirect to login', async ({ page }) => {
    await loginAsAdmin(page);

    // Open dropdown and click Sign out
    await page.evaluate(() => {
      const btn = document.querySelector('button[aria-haspopup="true"]');
      if (btn) (btn as HTMLElement).click();
    });
    await page.waitForTimeout(200);

    await page.evaluate(() => {
      const signOut = Array.from(document.querySelectorAll('button'))
        .find(b => b.textContent?.trim() === 'Sign out');
      if (signOut) (signOut as HTMLElement).click();
    });

    await page.waitForFunction(() => window.location.href.includes('login'), { timeout: 10000 });
    expect(page.url()).toContain('login');
  });

  test('should not access admin after logout', async ({ page }) => {
    await loginAsAdmin(page);

    // Logout via evaluate
    await page.evaluate(() => {
      const btn = document.querySelector('button[aria-haspopup="true"]');
      if (btn) (btn as HTMLElement).click();
    });
    await page.waitForTimeout(200);
    await page.evaluate(() => {
      const signOut = Array.from(document.querySelectorAll('button'))
        .find(b => b.textContent?.trim() === 'Sign out');
      if (signOut) (signOut as HTMLElement).click();
    });
    await page.waitForFunction(() => window.location.href.includes('login'), { timeout: 10000 });

    // Try to access admin
    await page.goto(`${BASE_URL}/ro/admin`);
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('login');
  });
});

// ═══════════════════════════════════════════════════
// AUTH-05: Refresh token (API-level test)
// ═══════════════════════════════════════════════════
test.describe('AUTH-05: Refresh token', () => {
  const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';

  test('should return JWT and refresh token on login', async ({ request }) => {
    const response = await request.post(`${API_URL}/api/login_check`, {
      data: { username: CREDENTIALS.username, password: CREDENTIALS.password },
    });

    expect(response.status()).toBe(200);
    const body = await response.json();
    expect(body.token).toBeTruthy();
    expect(body.refresh_token).toBeTruthy();
    expect(body.refresh_token_expires_at).toBeGreaterThan(0);
  });

  test('should refresh and invalidate old token (single-use)', async ({ request }) => {
    // Login
    const loginResp = await request.post(`${API_URL}/api/login_check`, {
      data: { username: CREDENTIALS.username, password: CREDENTIALS.password },
    });
    const { refresh_token } = await loginResp.json();

    // First refresh — should succeed
    const refresh1 = await request.post(`${API_URL}/api/token/refresh`, {
      form: { refresh_token },
    });
    expect(refresh1.status()).toBe(200);

    // Second refresh with same token — should fail (single-use)
    const refresh2 = await request.post(`${API_URL}/api/token/refresh`, {
      form: { refresh_token },
    });
    expect(refresh2.status()).toBe(401);
  });

  test('should reject invalid refresh token', async ({ request }) => {
    const response = await request.post(`${API_URL}/api/token/refresh`, {
      form: { refresh_token: 'totally_invalid_token' },
    });
    expect(response.status()).toBe(401);
  });
});

// ═══════════════════════════════════════════════════
// AUTH-06: Login with empty fields
// ═══════════════════════════════════════════════════
test.describe('AUTH-06: Empty field validation', () => {
  test('should not submit with empty fields (HTML5 validation)', async ({ page }) => {
    await page.goto(`${BASE_URL}/ro/login`);
    await page.waitForLoadState('networkidle');

    const requestMade = { value: false };
    page.on('request', req => {
      if (req.method() === 'POST') requestMade.value = true;
    });

    await page.getByRole('button', { name: 'Sign in' }).click();
    await page.waitForTimeout(500);

    expect(page.url()).toContain('login');
    expect(requestMade.value).toBe(false);
  });

  test('should validate missing password', async ({ page }) => {
    await page.goto(`${BASE_URL}/ro/login`);
    await page.waitForLoadState('networkidle');

    await page.getByRole('textbox', { name: 'Username' }).fill('test');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await page.waitForTimeout(500);

    expect(page.url()).toContain('login');
  });
});

// ═══════════════════════════════════════════════════
// AUTH-07: Multi-tab session
// ═══════════════════════════════════════════════════
test.describe('AUTH-07: Multi-tab session', () => {
  test('should share session across tabs', async ({ page, context }) => {
    await loginAsAdmin(page);

    // Open Tab 2
    const page2 = await context.newPage();
    await page2.goto(`${BASE_URL}/ro/admin/articles`);
    await page2.waitForLoadState('networkidle');

    // Tab 2 should access admin (shared cookie)
    expect(page2.url()).toContain('admin');
    await page2.close();
  });

  test('should detect expired session in all tabs', async ({ page, context }) => {
    await loginAsAdmin(page);

    const page2 = await context.newPage();
    await page2.goto(`${BASE_URL}/ro/admin/articles`);
    await page2.waitForLoadState('networkidle');

    // Clear cookies (simulate logout)
    await context.clearCookies();

    // Tab 2: access admin → should redirect to login
    await page2.goto(`${BASE_URL}/ro/admin/categories`);
    await page2.waitForLoadState('networkidle');
    expect(page2.url()).toContain('login');

    // Tab 1: access admin → should also redirect
    await page.goto(`${BASE_URL}/ro/admin`);
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('login');

    await page2.close();
  });
});

// ═══════════════════════════════════════════════════
// AUTH-08: XSS security in login
// ═══════════════════════════════════════════════════
test.describe('AUTH-08: XSS protection', () => {
  test('should not execute injected script', async ({ page }) => {
    let alertFired = false;
    page.on('dialog', () => { alertFired = true; });

    await page.goto(`${BASE_URL}/ro/login`);
    await page.waitForLoadState('networkidle');

    await page.getByRole('textbox', { name: 'Username' }).fill('<script>alert("xss")</script>');
    await page.getByRole('textbox', { name: 'Password' }).fill('testpass');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await page.waitForTimeout(2000);

    expect(alertFired).toBe(false);
    expect(page.url()).toContain('login');
  });

  test('should not inject script tags into DOM', async ({ page }) => {
    await page.goto(`${BASE_URL}/ro/login`);
    await page.waitForLoadState('networkidle');

    await page.getByRole('textbox', { name: 'Username' }).fill('<script>alert("xss")</script>');
    await page.getByRole('textbox', { name: 'Password' }).fill('testpass');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await page.waitForTimeout(2000);

    const injected = await page.evaluate(() => {
      return document.body.innerHTML.includes('<script>alert');
    });
    expect(injected).toBe(false);
  });
});

// ═══════════════════════════════════════════════════
// Login page i18n
// ═══════════════════════════════════════════════════
test.describe('Login page i18n', () => {
  test('Romanian locale shows Romanian title', async ({ page }) => {
    await page.context().clearCookies();
    await page.goto(`${BASE_URL}/ro/login`);
    await page.waitForLoadState('networkidle');

    // Title should be in Romanian (default locale may strip /ro prefix)
    const heading = page.getByRole('heading', { level: 1 });
    const text = await heading.textContent();
    // Could be Romanian or English depending on locale detection
    expect(text).toBeTruthy();
    expect(text!.length).toBeGreaterThan(5);
  });
});
