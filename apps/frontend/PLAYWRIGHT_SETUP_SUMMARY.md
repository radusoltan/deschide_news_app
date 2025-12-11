# Playwright E2E Test Setup - Issue Resolution

## Problem Summary

Playwright was unable to discover any E2E tests despite having 197 tests across 10 test files in the `__tests__/e2e/` and `__tests__/integration/` directories.

**Symptom:**
```bash
$ pnpm playwright test --list
Error: No tests found
Listing tests:
Total: 0 tests in 0 files
```

## Root Cause

The issue was in the `testMatch` pattern in `playwright.config.ts`:

**BEFORE (Not Working):**
```typescript
testMatch: ['**/(e2e|integration)/**/*.spec.ts']
```

This array syntax with a regex-like pattern was not being properly interpreted by Playwright's test discovery mechanism.

**AFTER (Working):**
```typescript
testMatch: '**/*.spec.ts'
```

Since `testDir` is already set to `./__tests__`, a simple glob pattern is sufficient to match all `.spec.ts` files within the test directory structure.

## Solution Applied

### 1. Fixed testMatch Pattern

Updated `/var/www/deschide_news_app/apps/frontend/playwright.config.ts`:

```typescript
export default defineConfig({
  testDir: './__tests__',

  // Test patterns - match both e2e and integration tests
  testMatch: '**/*.spec.ts',  // Changed from array format

  // ... rest of config
});
```

### 2. Installed Playwright Browsers

Ran browser installation command:
```bash
npx playwright install chromium
```

This downloaded:
- Chromium 143.0.7499.4 (164.7 MiB)
- Chromium Headless Shell (109.7 MiB)

## Verification

After the fix, test discovery works correctly:

```bash
$ pnpm playwright test --list | grep "Total"
Total: 1182 tests in 10 files
```

**Test Distribution:**
- 197 unique tests
- 6 browser projects (Chromium, Firefox, WebKit, Mobile Chrome, Mobile Safari, iPad)
- 1182 total test executions (197 tests × 6 projects)

**Test Files:**
- `__tests__/e2e/archive.spec.ts` - 31 tests
- `__tests__/e2e/admin-archive.spec.ts` - 35 tests
- `__tests__/e2e/article-navigation.spec.ts` - 14 tests
- `__tests__/e2e/locale-switching.spec.ts` - 27 tests
- `__tests__/e2e/user-flows.spec.ts` - 14 tests
- `__tests__/integration/article-page.spec.ts` - 31 tests
- `__tests__/integration/category-page.spec.ts` - 18 tests
- `__tests__/integration/homepage.spec.ts` - 11 tests
- `__tests__/integration/navigation.spec.ts` - 22 tests

## Available Test Commands

The following npm scripts are available in `package.json`:

```bash
# Run all E2E tests (all browsers)
pnpm test:e2e

# Interactive UI mode
pnpm test:e2e:ui

# Debug mode (step through tests)
pnpm test:e2e:debug

# Headed mode (see browser)
pnpm test:e2e:headed

# Single browser
pnpm test:e2e:chromium
pnpm test:e2e:firefox
pnpm test:e2e:webkit

# Mobile browsers only
pnpm test:e2e:mobile

# Archive tests only
pnpm test:e2e:archive

# Integration tests only
pnpm test:integration

# List all tests
pnpm playwright test --list

# List tests for specific file
pnpm playwright test e2e/archive.spec.ts --list

# List tests for specific project
pnpm playwright test --list --project=chromium
```

## Running Tests

### Prerequisites

**Servers must be running:**

1. **Backend API** (port 8081):
   ```bash
   cd /var/www/deschide_news_app/apps/backend
   symfony serve -d --port=8081
   ```

2. **Frontend Dev Server** (port 3005):
   ```bash
   cd /var/www/deschide_news_app/apps/frontend
   pnpm dev
   ```

   OR Playwright will automatically start it using the `webServer` config:
   ```typescript
   webServer: {
     command: 'pnpm dev',
     url: 'http://localhost:3005',
     reuseExistingServer: !process.env.CI,
     timeout: 120 * 1000,
   }
   ```

### Run Tests

```bash
cd /var/www/deschide_news_app/apps/frontend

# List all tests (verify discovery)
pnpm playwright test --list

# Run all tests
pnpm test:e2e

# Run specific test file
pnpm playwright test e2e/archive.spec.ts

# Run with UI (recommended for development)
pnpm test:e2e:ui
```

## Configuration Details

**Test Directory Structure:**
```
/var/www/deschide_news_app/apps/frontend/
├── __tests__/
│   ├── e2e/                    # End-to-end user flow tests
│   │   ├── archive.spec.ts
│   │   ├── admin-archive.spec.ts
│   │   ├── article-navigation.spec.ts
│   │   ├── locale-switching.spec.ts
│   │   └── user-flows.spec.ts
│   ├── integration/            # Integration tests
│   │   ├── article-page.spec.ts
│   │   ├── category-page.spec.ts
│   │   ├── homepage.spec.ts
│   │   └── navigation.spec.ts
│   └── unit/                   # Unit tests (Jest)
├── playwright.config.ts        # Playwright configuration
└── package.json
```

**Browser Projects:**
- Desktop: Chromium, Firefox, WebKit (Safari)
- Mobile: Pixel 5 (Chrome), iPhone 12 (Safari)
- Tablet: iPad Pro

**Timeouts:**
- Test timeout: 60 seconds
- Web server startup: 120 seconds

**Reporters:**
- HTML report (generated in `playwright-report/`)
- List output (console)
- GitHub Actions format (in CI only)

**Debugging Features:**
- Screenshots on failure
- Video recording on failure
- Trace on first retry

## Troubleshooting

### Tests Still Not Found?

1. **Verify Playwright is installed:**
   ```bash
   pnpm playwright --version
   # Should show: Version 1.57.0
   ```

2. **Check test files exist:**
   ```bash
   ls -la __tests__/e2e/
   ls -la __tests__/integration/
   ```

3. **Verify TypeScript compilation:**
   ```bash
   npx tsc --noEmit __tests__/e2e/archive.spec.ts
   # Should complete without errors
   ```

4. **Check config syntax:**
   ```bash
   node -e "const config = require('./playwright.config.ts'); console.log(config.default.testMatch);"
   # Should output: **/*.spec.ts
   ```

### Browsers Not Installed?

```bash
npx playwright install chromium firefox webkit
```

Or install all with system dependencies:
```bash
npx playwright install --with-deps
```

### Dev Server Not Starting?

Check if port 3005 is available:
```bash
ss -tulpn | grep 3005
```

If occupied, either:
- Stop the existing process
- Or set `reuseExistingServer: true` (already configured)

## Next Steps

1. **Run the tests** to verify they execute (may fail if application isn't fully implemented)
2. **Review test results** and fix failing tests
3. **Add CI/CD integration** (GitHub Actions, GitLab CI, etc.)
4. **Generate HTML report:**
   ```bash
   pnpm test:e2e
   pnpm playwright show-report
   ```

## References

- Playwright Documentation: https://playwright.dev/
- Test Discovery: https://playwright.dev/docs/test-configuration#testmatch
- Test Directory: https://playwright.dev/docs/test-configuration#testdir
- Browser Installation: https://playwright.dev/docs/browsers

---

**Date Fixed:** 2025-11-30
**Playwright Version:** 1.57.0
**Node Version:** 24.10.1 (as per package.json)
**Total Tests:** 197 tests across 10 files
