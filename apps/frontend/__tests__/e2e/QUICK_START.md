# Archive E2E Tests - Quick Start Guide

## Files Created

✅ **archive.spec.ts** - 31 tests for public archive page
✅ **admin-archive.spec.ts** - 35 tests for admin archive management
✅ **README.archive-tests.md** - Detailed documentation
✅ **ARCHIVE_TESTS_SUMMARY.md** - Comprehensive overview
✅ **package.json** - Added `test:e2e:archive` script

**Total: 66 test cases across 21 test suites**

---

## Prerequisites (Required)

### 1. Start Backend API
```bash
cd /var/www/deschide_news_app/apps/backend
symfony serve -d --port=8081
```
✓ Verify: http://127.0.0.1:8081

### 2. Start Frontend Dev Server
```bash
cd /var/www/deschide_news_app/apps/frontend
pnpm dev
```
✓ Verify: http://localhost:3005

### 3. Ensure Test Data Exists
- Articles from multiple years (2019-2024)
- Multiple categories (5+)
- Some archived articles (`is_archived = true`)
- At least 30 articles for pagination

---

## Running Tests (3 Easy Ways)

### Option 1: Run All Archive Tests ⭐ RECOMMENDED
```bash
cd /var/www/deschide_news_app/apps/frontend
pnpm test:e2e:archive
```

### Option 2: Interactive UI Mode (Best for Debugging)
```bash
pnpm test:e2e:ui
# Then filter by "archive" in the UI
```

### Option 3: Run Individual Files
```bash
# Public archive only
pnpm playwright test __tests__/e2e/archive.spec.ts

# Admin archive only
pnpm playwright test __tests__/e2e/admin-archive.spec.ts
```

---

## Quick Commands Reference

```bash
# See tests in headed browser (watch them run)
pnpm playwright test __tests__/e2e/archive.spec.ts --headed

# Debug mode (step through tests)
pnpm playwright test __tests__/e2e/archive.spec.ts --debug

# Run specific test suite
pnpm playwright test -g "SEO and Metadata"

# Run on specific browser
pnpm playwright test --project=chromium

# View HTML report after run
npx playwright show-report

# Run with verbose output
DEBUG=pw:api pnpm test:e2e:archive
```

---

## Test Coverage Highlights

### Public Archive (`archive.spec.ts`)
- ✅ Page structure and layout
- ✅ Year filter functionality
- ✅ Category filter functionality
- ✅ Pagination controls
- ✅ SEO metadata (noindex, canonical, etc.)
- ✅ Responsive design (desktop/tablet/mobile)
- ✅ Multilingual support (ro/en/ru)
- ✅ Combined filters
- ✅ Loading states
- ✅ Error handling

### Admin Archive (`admin-archive.spec.ts`)
- ✅ Authentication & access control
- ✅ Statistics dashboard
- ✅ Bulk archive form
- ✅ Confirmation modals
- ✅ Archived articles list
- ✅ Unarchive functionality
- ✅ Multilingual admin interface
- ✅ Responsive admin layout
- ✅ Loading skeletons
- ✅ Error handling

---

## Expected Output

```
Running 66 tests using 6 workers

  ✓ archive.spec.ts:21:3 › Archive Public Page - Core Functionality › archive page displays correctly
  ✓ archive.spec.ts:35:3 › Archive Public Page - Core Functionality › archive page shows year filters
  ✓ archive.spec.ts:45:3 › Archive Public Page - Core Functionality › archive page shows category filter
  ...
  ✓ admin-archive.spec.ts:450:3 › Admin Archive Page - Integration › breadcrumb navigation

  66 passed (4m 30s)

To view the HTML report, run: npx playwright show-report
```

---

## Troubleshooting

### ❌ Error: "No tests found"
**Fix:** Check test files are in `__tests__/e2e/` and have `.spec.ts` extension

### ❌ Error: "Timeout waiting for element"
**Fix:** Ensure frontend and backend are running, check network

### ❌ Error: "Connection refused"
**Fix:** Start backend on port 8081 and frontend on port 3005

### ❌ Error: "Authentication required"
**Fix:** Update credentials in `admin-archive.spec.ts` loginAsAdmin() helper

### ❌ Tests are flaky
**Fix:** Increase timeouts, ensure test data exists, check API responses

---

## Next Steps

1. ✅ **Run the tests** using `pnpm test:e2e:archive`
2. 📊 **View the report** with `npx playwright show-report`
3. 🐛 **Debug failures** using `pnpm test:e2e:ui`
4. 📝 **Read full docs** in `README.archive-tests.md`
5. 🔧 **Update auth** in `admin-archive.spec.ts` when implemented

---

## File Locations

```
/var/www/deschide_news_app/apps/frontend/
├── __tests__/
│   └── e2e/
│       ├── archive.spec.ts           ← Public archive tests (31 tests)
│       ├── admin-archive.spec.ts     ← Admin archive tests (35 tests)
│       ├── README.archive-tests.md   ← Full documentation
│       ├── ARCHIVE_TESTS_SUMMARY.md  ← Detailed overview
│       └── QUICK_START.md            ← This file
├── playwright.config.ts              ← Test configuration
└── package.json                      ← Scripts (test:e2e:archive)
```

---

## Environment Variables (Optional)

```bash
# Set test admin credentials
export TEST_ADMIN_EMAIL="admin@test.com"
export TEST_ADMIN_PASSWORD="testpassword123"

# Override base URL
export PLAYWRIGHT_BASE_URL="http://localhost:3005"
```

---

## Tips for Success

💡 **Tip 1:** Use UI mode (`pnpm test:e2e:ui`) for best debugging experience
💡 **Tip 2:** Run in headed mode (`--headed`) to watch tests execute
💡 **Tip 3:** Check traces for failures: `npx playwright show-trace test-results/*/trace.zip`
💡 **Tip 4:** Filter tests with `-g`: `pnpm playwright test -g "pagination"`
💡 **Tip 5:** Ensure backend API is healthy before running tests

---

## CI/CD Ready

Tests are configured for GitHub Actions:
- ✅ Retry on failure (2 retries in CI)
- ✅ Screenshot on failure
- ✅ Video on failure
- ✅ Trace on first retry
- ✅ HTML report generation

---

## Questions?

1. Check `README.archive-tests.md` for detailed docs
2. Check `ARCHIVE_TESTS_SUMMARY.md` for overview
3. Review Playwright docs: https://playwright.dev
4. Check existing test patterns in other `.spec.ts` files

---

**Happy Testing! 🎭**

Last Updated: 2025-11-30
