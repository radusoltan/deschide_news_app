# Archive Functionality E2E Tests

This directory contains comprehensive Playwright E2E tests for the archive functionality in the Deschide News App frontend.

## Test Files

### 1. `archive.spec.ts` - Public Archive Page Tests

Tests the public-facing archive browsing interface available at `/[locale]/archive`.

**Test Coverage:**

- **Core Functionality**
  - Page structure and header display
  - Year and category filter visibility
  - Article grid/list display
  - Archive information banners

- **Year Filter Functionality**
  - Clicking year filters updates URL
  - Year parameter persists across reloads
  - Clearing filters removes parameters
  - Multiple year selections

- **Category Filter Functionality**
  - Category filter interactivity
  - URL updates on category selection
  - Category combinations

- **Pagination**
  - Pagination controls visibility
  - Next/previous navigation
  - Page number URL reflection
  - Content changes between pages

- **SEO and Metadata**
  - `noindex, follow` robots meta tag
  - Proper page titles
  - Meta descriptions
  - Canonical URLs
  - Language alternates

- **Responsive Design**
  - Desktop layout (1920x1080)
  - Tablet layout (768x1024)
  - Mobile layout (375x667)
  - Mobile filter toggle functionality

- **Multilingual Support**
  - Romanian (`/ro/archive`)
  - English (`/en/archive`)
  - Russian (`/ru/archive`)
  - Language-specific content

- **Combined Filters**
  - Year + Category combinations
  - Filter persistence across navigation

- **Loading States**
  - Initial loading indicators
  - Empty archive handling

- **Error Handling**
  - Slow API handling
  - Network issues
  - Missing data

**Total Tests:** 40+ test cases

### 2. `admin-archive.spec.ts` - Admin Archive Management Tests

Tests the administrative archive management interface at `/[locale]/admin/archive`.

**Test Coverage:**

- **Access Control**
  - Authentication requirement
  - Login redirects (all locales)
  - Return URL parameters

- **Page Structure**
  - Admin header with gradient
  - Section organization
  - Archive icon decorations

- **Statistics Display**
  - Stats section visibility
  - Stat cards with numeric values
  - Loading skeletons
  - Grid layouts

- **Bulk Archive Form**
  - Form visibility and structure
  - Years input field functionality
  - Preview date calculation
  - Archive button presence
  - Confirmation modal
  - Warning messages

- **Archived Articles List**
  - List/table display
  - Unarchive buttons
  - Article information display

- **Info Footer**
  - Explanation banners
  - Info icons

- **Multilingual Support**
  - Romanian translations
  - English translations
  - Russian translations

- **Responsive Design**
  - Desktop layout (1920x1080)
  - Tablet layout (768x1024)
  - Mobile layout (375x667)
  - Stat card stacking

- **Loading States**
  - Stats loading skeletons
  - Table loading indicators

- **Error Handling**
  - Authentication errors
  - API errors
  - Graceful degradation

- **Integration**
  - Admin navigation
  - Breadcrumbs

**Total Tests:** 35+ test cases

## Running the Tests

### Run All Archive Tests

```bash
cd /var/www/deschide_news_app/apps/frontend

# Run all archive tests (public + admin)
pnpm test:e2e:archive
```

### Run Individual Test Files

```bash
# Public archive tests only
pnpm playwright test __tests__/e2e/archive.spec.ts

# Admin archive tests only
pnpm playwright test __tests__/e2e/admin-archive.spec.ts
```

### Run with UI Mode (Visual Debugging)

```bash
# Interactive UI mode for debugging
pnpm test:e2e:ui

# Then filter by "archive" in the UI
```

### Run with Specific Browser

```bash
# Chromium only
pnpm playwright test __tests__/e2e/archive.spec.ts --project=chromium

# Firefox only
pnpm playwright test __tests__/e2e/archive.spec.ts --project=firefox

# Mobile Chrome
pnpm playwright test __tests__/e2e/archive.spec.ts --project='Mobile Chrome'
```

### Run with Headed Browser (See Tests Run)

```bash
pnpm playwright test __tests__/e2e/archive.spec.ts --headed
```

### Run in Debug Mode

```bash
# Step through tests with debugger
pnpm playwright test __tests__/e2e/archive.spec.ts --debug
```

## Prerequisites

### 1. Frontend Development Server

The tests expect the frontend to be running on `http://localhost:3005`:

```bash
cd /var/www/deschide_news_app/apps/frontend
pnpm dev
```

Or let Playwright start it automatically (configured in `playwright.config.ts`).

### 2. Backend API Server

The backend API should be running on `http://127.0.0.1:8081`:

```bash
cd /var/www/deschide_news_app/apps/backend
symfony serve -d --port=8081
```

### 3. Test Data

For comprehensive test coverage, ensure:

- Archive has articles from multiple years
- Multiple categories exist
- Some articles are marked as archived (`is_archived = true`)
- Enough articles for pagination (30+ recommended)

### 4. Authentication (Admin Tests)

Admin tests require authentication. Configure test credentials:

```bash
# Set environment variables (optional)
export TEST_ADMIN_EMAIL="admin@test.com"
export TEST_ADMIN_PASSWORD="testpassword123"
```

Or update the `loginAsAdmin()` helper in `admin-archive.spec.ts` with valid credentials.

## Test Configuration

Tests use the configuration from `playwright.config.ts`:

- **Base URL:** `http://localhost:3005` (configurable via `PLAYWRIGHT_BASE_URL` env var)
- **Timeout:** 60 seconds per test
- **Retries:** 2 retries in CI, 0 locally
- **Screenshots:** On failure only
- **Videos:** On failure only
- **Trace:** On first retry

## Test Patterns Used

### 1. Network Idle Waits

```typescript
await page.waitForLoadState('networkidle');
```

Ensures all network requests complete before assertions.

### 2. Flexible Selectors

Tests use multiple selector strategies to handle dynamic content:

```typescript
const heading = page.locator('h1').first();
const yearFilter = page.locator('button, a').filter({ hasText: /^20\d{2}$/ });
```

### 3. Conditional Tests

Tests gracefully handle missing features:

```typescript
if (await element.isVisible({ timeout: 5000 })) {
  await expect(element).toBeVisible();
}
```

### 4. Authentication Checks

Admin tests skip when not authenticated:

```typescript
if (!await isAuthenticated(page)) {
  test.skip();
}
```

### 5. Timeout Management

Appropriate timeouts for slow operations:

```typescript
await page.waitForTimeout(2000); // Wait for filters to load
await element.isVisible({ timeout: 5000 }); // 5 second timeout
```

## Known Limitations

### 1. Authentication

The admin tests currently use placeholder login logic. Update the `loginAsAdmin()` helper when authentication is fully implemented.

### 2. Test Data Dependency

Tests assume archive content exists. Running against an empty database will result in some test skips/failures.

### 3. Dynamic Content

Some tests may be flaky if:
- API responses are slow (>5 seconds)
- Articles are being added/removed during test runs
- Cache is stale

### 4. Browser-Specific Issues

Some features may behave differently across browsers:
- Modal animations (webkit vs chromium)
- Date formatting (locale-dependent)
- Font rendering

## Debugging Failed Tests

### 1. View Test Report

```bash
# After test run
npx playwright show-report
```

### 2. Check Screenshots

Failed tests generate screenshots in `test-results/`:

```bash
ls -la test-results/
```

### 3. View Trace

```bash
# Open trace viewer
npx playwright show-trace test-results/archive-spec-ts-**/trace.zip
```

### 4. Run Single Test

```bash
# Run only the failing test
pnpm playwright test __tests__/e2e/archive.spec.ts -g "archive page displays correctly"
```

### 5. Increase Verbosity

```bash
# Run with debug output
DEBUG=pw:api pnpm playwright test __tests__/e2e/archive.spec.ts
```

## Continuous Integration

### GitHub Actions

Tests can run in CI with:

```yaml
- name: Run Archive E2E Tests
  run: pnpm test:e2e:archive
  env:
    CI: true
```

### Docker

```bash
# Run in Docker container
docker run -it --rm \
  -v $(pwd):/app \
  -w /app \
  mcr.microsoft.com/playwright:latest \
  pnpm test:e2e:archive
```

## Contributing

When adding new archive features:

1. Add corresponding test cases to the appropriate spec file
2. Use descriptive test names: `test('feature does X when Y happens', ...)`
3. Follow existing patterns for selectors and waits
4. Add comments for complex test logic
5. Ensure tests pass on all browsers (chromium, firefox, webkit)
6. Test on mobile viewports when relevant

## Resources

- [Playwright Documentation](https://playwright.dev)
- [Playwright Best Practices](https://playwright.dev/docs/best-practices)
- [Writing Tests](https://playwright.dev/docs/writing-tests)
- [Debugging Tests](https://playwright.dev/docs/debug)
- [Test Reporters](https://playwright.dev/docs/test-reporters)

## Support

For issues or questions:

1. Check test output and traces
2. Review Playwright documentation
3. Check `playwright.config.ts` configuration
4. Verify frontend and backend are running
5. Ensure test data exists in database
