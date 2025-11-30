# Playwright E2E Tests - Quick Start Guide

## TL;DR - Run Tests Now

```bash
cd /var/www/deschide_news_app/apps/frontend

# List all tests
pnpm playwright test --list

# Run all tests (UI mode - recommended)
pnpm test:e2e:ui

# Run all tests (headless)
pnpm test:e2e
```

## Prerequisites Check

Before running tests, ensure:

1. **Backend API is running** (port 8081):
   ```bash
   # Check if running
   curl http://127.0.0.1:8081/api

   # If not, start it
   cd /var/www/deschide_news_app/apps/backend
   symfony serve -d --port=8081
   ```

2. **Frontend dev server** (optional - Playwright will auto-start if needed):
   ```bash
   # Check if running
   curl http://localhost:3005

   # If not, start it manually
   cd /var/www/deschide_news_app/apps/frontend
   pnpm dev
   ```

## Common Commands

### List Tests

```bash
# List all tests
pnpm playwright test --list

# List tests for specific file
pnpm playwright test e2e/archive.spec.ts --list

# List tests for single browser
pnpm playwright test --list --project=chromium
```

### Run Tests

```bash
# UI Mode (Interactive - BEST for development)
pnpm test:e2e:ui

# Run all tests (headless, all browsers)
pnpm test:e2e

# Run specific test file
pnpm playwright test e2e/archive.spec.ts

# Run archive tests only
pnpm test:e2e:archive

# Run integration tests only
pnpm test:integration

# Debug a single test
pnpm test:e2e:debug

# Run in headed mode (see browser)
pnpm test:e2e:headed
```

### Run Specific Browsers

```bash
# Chrome only
pnpm test:e2e:chromium

# Firefox only
pnpm test:e2e:firefox

# Safari (WebKit) only
pnpm test:e2e:webkit

# Mobile browsers only
pnpm test:e2e:mobile
```

### View Results

```bash
# Generate and open HTML report
pnpm playwright show-report

# The report is saved in: playwright-report/
```

## Test Files Overview

**E2E Tests** (`__tests__/e2e/`):
- `archive.spec.ts` - Public archive page functionality (31 tests)
- `admin-archive.spec.ts` - Admin archive management (35 tests)
- `article-navigation.spec.ts` - Article browsing flows (14 tests)
- `locale-switching.spec.ts` - Language switching (27 tests)
- `user-flows.spec.ts` - Complete user journeys (14 tests)

**Integration Tests** (`__tests__/integration/`):
- `article-page.spec.ts` - Article page integration (31 tests)
- `category-page.spec.ts` - Category listing (18 tests)
- `homepage.spec.ts` - Homepage functionality (11 tests)
- `navigation.spec.ts` - Navigation components (22 tests)

**Total: 196 unique tests × 6 browsers = 1176 test executions**

## Browsers Tested

- Desktop Chrome (Chromium)
- Desktop Firefox
- Desktop Safari (WebKit)
- Mobile Chrome (Pixel 5)
- Mobile Safari (iPhone 12)
- iPad Pro

## Troubleshooting

### "No tests found"

This was the original issue! If you see this:
1. Check `playwright.config.ts` has: `testMatch: '**/*.spec.ts'`
2. Not: `testMatch: ['**/(e2e|integration)/**/*.spec.ts']`

### "Browser not installed"

```bash
npx playwright install chromium
# Or install all browsers
npx playwright install
```

### "Error: page.goto: net::ERR_CONNECTION_REFUSED"

The dev server isn't running. Either:
1. Start it manually: `pnpm dev`
2. Or Playwright will auto-start it (wait 120 seconds)

### Tests fail with timeout

Some tests may timeout if:
- Backend API is slow/not responding
- Database has no test data
- Network is slow

Increase timeout in `playwright.config.ts`:
```typescript
timeout: 120 * 1000, // 2 minutes
```

## Tips for Development

1. **Use UI Mode** for development:
   ```bash
   pnpm test:e2e:ui
   ```
   - Interactive test explorer
   - Visual debugging
   - Time-travel debugging
   - Watch mode

2. **Use Debug Mode** to step through tests:
   ```bash
   pnpm test:e2e:debug
   ```

3. **Run single browser** during development:
   ```bash
   pnpm test:e2e:chromium
   ```
   Much faster than all 6 browsers!

4. **Filter tests by name**:
   ```bash
   pnpm playwright test -g "archive page"
   ```

5. **Run failed tests only**:
   ```bash
   pnpm playwright test --last-failed
   ```

## CI/CD Integration

For continuous integration, tests run automatically on all browsers:

```yaml
# .github/workflows/e2e-tests.yml
- name: Run Playwright tests
  run: pnpm test:e2e
  env:
    CI: true
```

## Next Steps

1. Run tests and review results
2. Fix any failing tests
3. Add new tests for new features
4. Set up CI/CD pipeline
5. Generate coverage reports

## Documentation

- Full setup details: `PLAYWRIGHT_SETUP_SUMMARY.md`
- Playwright docs: https://playwright.dev/
- Test writing guide: https://playwright.dev/docs/writing-tests

---

**Updated:** 2025-11-30
**Playwright Version:** 1.57.0
**Total Tests:** 196 tests (1176 executions across 6 browsers)
