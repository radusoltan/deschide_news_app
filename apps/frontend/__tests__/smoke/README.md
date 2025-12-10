# Smoke Tests

Quick, essential tests to verify that critical functionality works after deployments or major changes.

## Purpose

Smoke tests are designed to:
- Run quickly (< 2 minutes total)
- Catch critical regressions early
- Verify basic page loads and navigation
- Test API connectivity and data flow
- Run in CI/CD pipeline before full test suite

## Test Files

### 1. `pages.smoke.spec.ts`
Tests that critical pages load successfully:
- ✅ Homepage loads for all locales (ro, en, ru)
- ✅ Category pages load
- ✅ Archive page loads
- ✅ Login page loads
- ✅ 404 page displays correctly
- ✅ Pages load within 10 seconds
- ✅ Basic SEO elements present (title, meta description)
- ✅ No JavaScript errors on page load

### 2. `navigation.smoke.spec.ts`
Tests that navigation elements work:
- ✅ Header navigation is visible
- ✅ Logo/brand links to homepage
- ✅ Category navigation works
- ✅ Language switcher exists and functions
- ✅ Footer is present
- ✅ Mobile menu toggle exists
- ✅ Navigation accessibility (ARIA attributes)

### 3. `api-integration.smoke.spec.ts`
Tests API connectivity and data flow:
- ✅ Backend API is reachable
- ✅ API returns proper JSON-LD structure
- ✅ Articles API returns data
- ✅ Categories API returns data
- ✅ API supports locale filtering
- ✅ API supports pagination
- ✅ Homepage displays articles from API
- ✅ Article images load correctly
- ✅ CDN images load correctly
- ✅ Frontend handles API errors gracefully

## Running Smoke Tests

### Run all smoke tests (Chromium only - fastest)
```bash
cd /var/www/deschide_news_app/apps/frontend
pnpm test:smoke
```

### Run all smoke tests (all browsers)
```bash
pnpm test:smoke:all
```

### Run specific smoke test file
```bash
pnpm exec playwright test __tests__/smoke/pages.smoke.spec.ts --project=chromium
pnpm exec playwright test __tests__/smoke/navigation.smoke.spec.ts --project=chromium
pnpm exec playwright test __tests__/smoke/api-integration.smoke.spec.ts --project=chromium
```

### Run with UI mode (visual debugging)
```bash
pnpm exec playwright test __tests__/smoke --ui
```

### Run in headed mode (see browser)
```bash
pnpm exec playwright test __tests__/smoke --headed --project=chromium
```

## Prerequisites

### Frontend must be running:
```bash
cd /var/www/deschide_news_app/apps/frontend
pnpm dev
# Or if already configured in playwright.config.ts, it will start automatically
```

### Backend API should be running (optional for full tests):
```bash
cd /var/www/deschide_news_app/apps/backend
symfony serve -d --port=8081
```

**Note:** Some tests will gracefully skip if the backend API is not available. The smoke tests are designed to run even in environments where the backend might not be accessible.

## Environment Variables

The tests use these environment variables (with defaults):

```bash
# Frontend URL (default: http://localhost:3005)
PLAYWRIGHT_BASE_URL=http://localhost:3005

# Backend API URL (default: http://127.0.0.1:8081)
API_URL=http://127.0.0.1:8081
```

Set in `.env.test` or pass directly:
```bash
PLAYWRIGHT_BASE_URL=https://staging.deschide.md pnpm test:smoke
```

## Test Configuration

Tests are configured to:
- **Timeout**: 10 seconds per page load
- **Parallel execution**: All tests run in parallel
- **Retry**: 0 retries in development, 2 in CI
- **Reporter**: List format (concise output)

## Expected Results

### All tests passing
```
✓ 30+ smoke tests passed
Duration: ~60-90 seconds (Chromium only)
```

### Partial failures (acceptable in development)
Some tests may skip or fail gracefully if:
- Backend API is not running
- Database has no test data
- Specific features not yet implemented

## CI/CD Integration

### Add to GitHub Actions workflow:
```yaml
- name: Run Smoke Tests
  run: |
    cd apps/frontend
    pnpm test:smoke
```

### Use as deployment gate:
```bash
# Run smoke tests after deployment
pnpm test:smoke || exit 1
```

## Troubleshooting

### Tests timeout
- Increase timeout in test files (LOAD_TIMEOUT constant)
- Check if services are running (frontend, backend)
- Check network connectivity

### Tests fail with "element not found"
- Page structure might have changed
- Check if selectors in tests match actual DOM
- Run with `--headed` to see what's happening

### API tests fail
- Verify backend is running: `curl http://127.0.0.1:8081/api`
- Check CORS settings
- Verify database has data

### Images don't load
- Check CDN is running (port 8082)
- Verify image paths in API responses
- Check NEXT_PUBLIC_CDN_URL in .env.local

## Test Maintenance

### When to update smoke tests:
- ✅ Major page structure changes
- ✅ New critical pages added
- ✅ Navigation changes
- ✅ API endpoint changes
- ❌ Minor UI tweaks (use integration tests)
- ❌ Detailed functionality (use E2E tests)

### Keep tests:
- **Fast**: < 2 minutes total
- **Focused**: Only critical paths
- **Resilient**: Handle missing data gracefully
- **Simple**: Easy to understand and maintain

## Related Documentation

- Full E2E tests: `__tests__/e2e/`
- Integration tests: `__tests__/integration/`
- Unit tests: `__tests__/unit/`
- Playwright config: `playwright.config.ts`
