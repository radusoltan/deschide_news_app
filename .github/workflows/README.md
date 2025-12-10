# GitHub Actions CI/CD Workflows

This directory contains GitHub Actions workflows for automated testing and quality checks in the Deschide News App monorepo.

## Workflows

### 1. Backend CI (`backend-ci.yml`)

**Triggers:**
- Push to: `main`, `develop`, `feature/**`, `bugfix/**`, `release/**`, `hotfix/**`
- Pull requests to: `main`, `develop`
- Path filters: `apps/backend/**`, `.github/workflows/backend-ci.yml`

**Environment:**
- PHP 8.4 with extensions: `ctype`, `iconv`, `pdo_pgsql`, `redis`, `intl`, `opcache`, `apcu`
- PostgreSQL 17 (service container)
- Redis 7 (service container)
- Composer v2 with caching

**Jobs:**
1. **PHPStan** - Static analysis (level 6)
2. **PHP-CS-Fixer** - Code style check (dry-run)
3. **PHPUnit** - Run all tests with coverage
4. **Coverage Report** - Upload coverage artifacts

**Artifacts:**
- `backend-coverage` - Coverage XML report (7 days retention)
- `backend-test-results` - Test results and cache (7 days retention)

**Status:** ✅ Production-ready

---

### 2. Frontend CI (`frontend-ci.yml`)

**Triggers:**
- Push to: `main`, `develop`, `feature/**`, `bugfix/**`, `release/**`, `hotfix/**`
- Pull requests to: `main`, `develop`
- Path filters: `apps/frontend/**`, `.github/workflows/frontend-ci.yml`

**Environment:**
- Node.js 20
- pnpm 9 with caching
- Next.js 16

**Jobs:**
1. **ESLint** - Linting check
2. **Jest** - Unit and integration tests with coverage
3. **Build** - Verify Next.js production build succeeds

**Artifacts:**
- `frontend-jest-coverage` - Jest coverage reports (7 days retention)
- `frontend-build` - Next.js build output (7 days retention)
- `frontend-test-results` - Test results (7 days retention)

**Status:** ✅ Production-ready

---

## Path Filtering Strategy

Both workflows use path filtering to avoid unnecessary runs:

**Backend workflow runs only when:**
- Files in `apps/backend/**` are modified
- The workflow file itself is modified

**Frontend workflow runs only when:**
- Files in `apps/frontend/**` are modified
- The workflow file itself is modified

This saves CI/CD minutes and speeds up feedback loops.

---

## Branch Strategy

Workflows are configured to match the Git-Flow branching model:

| Branch Type | Runs CI? | Purpose |
|-------------|----------|---------|
| `main` | ✅ | Production releases |
| `develop` | ✅ | Integration branch |
| `feature/*` | ✅ | Feature development |
| `bugfix/*` | ✅ | Bug fixes in development |
| `release/*` | ✅ | Release preparation |
| `hotfix/*` | ✅ | Production hotfixes |

---

## Service Containers

### Backend Workflow Services

**PostgreSQL 17:**
```yaml
Database: deschide_test
User: deschide_test
Password: deschide_test
Port: 5432
Health checks: pg_isready
```

**Redis 7:**
```yaml
Port: 6379
Health checks: redis-cli ping
```

---

## Composer Scripts

The following scripts are available in `apps/backend/composer.json`:

```bash
# Run tests without coverage
composer test

# Run tests with HTML coverage report
composer test:coverage

# Run PHPStan static analysis
composer lint

# Fix code style issues
composer lint:fix

# Check code style (dry-run)
composer lint:check

# Run all CI checks (tests + lint)
composer ci
```

**Usage locally:**
```bash
cd /var/www/deschide_news_app/apps/backend
composer ci
```

---

## NPM Scripts

The following scripts are available in `apps/frontend/package.json`:

```bash
# Run tests in CI mode
pnpm test:ci

# Run linting
pnpm lint

# Build production bundle
pnpm build
```

**Usage locally:**
```bash
cd /var/www/deschide_news_app/apps/frontend
pnpm lint
pnpm test:ci
pnpm build
```

---

## Coverage Reports

### Backend Coverage

Coverage reports are generated using Xdebug and PHPUnit:

**Local:**
```bash
cd apps/backend
composer test:coverage
# Open var/coverage/html/index.html in browser
```

**CI:**
- Coverage XML uploaded as artifact
- Summary displayed in GitHub Actions job summary

### Frontend Coverage

Coverage reports are generated using Jest:

**Local:**
```bash
cd apps/frontend
pnpm test:coverage
# Open coverage/lcov-report/index.html in browser
```

**CI:**
- Coverage HTML reports uploaded as artifact
- Summary displayed in GitHub Actions job summary

---

## Workflow Artifacts

All workflows upload artifacts for troubleshooting and analysis:

**Backend:**
- `backend-coverage` (coverage.xml) - 7 days retention
- `backend-test-results` (var/coverage/, .phpunit.cache/) - 7 days retention

**Frontend:**
- `frontend-jest-coverage` (coverage/) - 7 days retention
- `frontend-build` (.next/, out/) - 7 days retention
- `frontend-test-results` (coverage/, test-results/) - 7 days retention

**Download artifacts:**
1. Go to GitHub Actions run
2. Scroll to "Artifacts" section
3. Click artifact name to download

---

## Environment Variables

### Backend CI Environment

The workflow sets the following environment variables:

```yaml
# Database connection
DATABASE_URL: postgresql://deschide_test:deschide_test@127.0.0.1:5432/deschide_test?serverVersion=17&charset=utf8

# Redis connection
REDIS_URL: redis://127.0.0.1:6379

# Test environment
APP_ENV: test
SYMFONY_DEPRECATIONS_HELPER: weak
```

### Frontend CI Environment

```yaml
# Next.js environment
NODE_ENV: test

# API endpoints (for tests)
NEXT_PUBLIC_API_URL: http://localhost:8081
NEXT_PUBLIC_CDN_URL: http://localhost:8082
```

---

## Caching Strategy

Both workflows use aggressive caching to speed up builds:

### Backend Caching

**Composer dependencies:**
- Cache key: `${{ runner.os }}-composer-${{ hashFiles('apps/backend/composer.lock') }}`
- Cache directory: Composer cache files directory
- Restore keys: `${{ runner.os }}-composer-`

**Cache hit:** ~2 minutes saved per run
**Cache miss:** Full composer install (~4 minutes)

### Frontend Caching

**pnpm dependencies:**
- Cache key: `${{ runner.os }}-pnpm-store-${{ hashFiles('apps/frontend/pnpm-lock.yaml') }}`
- Cache directory: pnpm store path
- Restore keys: `${{ runner.os }}-pnpm-store-`

**Cache hit:** ~3 minutes saved per run
**Cache miss:** Full pnpm install (~5 minutes)

---

## Troubleshooting

### Backend CI Issues

**Problem:** Database connection fails
```bash
# Solution: Check PostgreSQL service container health
# The workflow includes health checks, but may need retry logic
```

**Problem:** PHPStan fails with memory limit
```bash
# Solution: Increase memory limit in phpstan.neon
# memoryLimit: 1G
```

**Problem:** Tests fail with "database does not exist"
```bash
# Solution: The workflow creates the database automatically
# Check if doctrine:database:create step succeeded
```

### Frontend CI Issues

**Problem:** pnpm install fails
```bash
# Solution: Clear cache and retry
# Check pnpm-lock.yaml is committed
```

**Problem:** Build fails with module not found
```bash
# Solution: Check dependencies are listed in package.json
# Verify pnpm install --frozen-lockfile succeeded
```

**Problem:** Tests timeout
```bash
# Solution: Increase Jest timeout or reduce parallelism
# Current CI uses --maxWorkers=2
```

---

## Future Enhancements

### Planned Improvements

- [ ] **Playwright E2E Tests** - Add E2E testing workflow for frontend
- [ ] **Code Coverage Gates** - Fail build if coverage drops below threshold
- [ ] **Codecov Integration** - Upload coverage to Codecov for tracking
- [ ] **Deploy Previews** - Automatic deployment to preview environments
- [ ] **Docker Builds** - Build and push Docker images on release
- [ ] **Security Scanning** - Add Snyk or Dependabot for vulnerability scanning
- [ ] **Performance Testing** - Lighthouse CI for Core Web Vitals
- [ ] **Database Seeding** - Add test data fixtures to CI environment

### Proposed New Workflows

**E2E Testing (`e2e-tests.yml`):**
```yaml
# Run Playwright tests on staging environment
# Triggered after successful frontend build
# Tests all user flows across locales (ro, en, ru)
```

**Deploy (`deploy.yml`):**
```yaml
# Deploy to staging/production
# Triggered on push to main or release branches
# Includes database migrations and cache warming
```

**Security (`security.yml`):**
```yaml
# Run security scans (SAST, dependency check)
# Scheduled daily
# Alerts on vulnerabilities
```

---

## Performance Metrics

### Typical Run Times

**Backend CI:**
- Setup: ~1 minute
- Composer install (cache hit): ~30 seconds
- PHPStan: ~1 minute
- PHPUnit: ~2 minutes
- **Total: ~4-5 minutes**

**Frontend CI:**
- Setup: ~1 minute
- pnpm install (cache hit): ~20 seconds
- ESLint: ~30 seconds
- Jest: ~1 minute
- Next.js build: ~2 minutes
- **Total: ~5-6 minutes**

**Combined monorepo CI:** ~10-11 minutes per PR

---

## Best Practices

### For Developers

**Before pushing code:**
```bash
# Backend
cd apps/backend
composer ci  # Run all checks locally

# Frontend
cd apps/frontend
pnpm lint && pnpm test:ci && pnpm build
```

**Writing tests:**
- Add tests for new features
- Maintain >70% code coverage (current thresholds)
- Use test doubles for external services
- Keep tests fast (<5 seconds per test)

**Commit conventions:**
```bash
# Conventional commits trigger proper changelog generation
git commit -m "feat(backend): add user authentication"
git commit -m "fix(frontend): resolve image loading issue"
git commit -m "test(backend): add article repository tests"
```

---

## Related Documentation

- **Main Project**: `/var/www/deschide_news_app/CLAUDE.md`
- **Backend**: `/var/www/deschide_news_app/apps/backend/CLAUDE.md`
- **Testing Guide**: `/var/www/deschide_news_app/docs/TESTING_AGENTS_GUIDE.md`
- **Git Workflow**: See "Git Workflow (Git-Flow)" in main CLAUDE.md

---

**Last Updated:** 2025-12-01
**Status:** ✅ Production-ready
**Maintainer:** Development Team
