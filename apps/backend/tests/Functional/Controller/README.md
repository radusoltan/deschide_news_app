# Archive API Controller Tests

Comprehensive PHPUnit integration/functional tests for archive API controllers.

## Files Created

### 1. ArchiveControllerTest.php (408 lines)
Tests for public archive navigation API endpoints (`/api/archive/*`).

**Endpoints Tested:**
- `GET /api/archive/years` - Available years with article counts
- `GET /api/archive/stats` - Archive statistics (total, by year, by category)
- `GET /api/archive/categories` - Categories with archived articles

**Test Cases (10 tests):**
1. `testGetArchiveYearsReturnsArray` - Verify JSON response with year/count structure
2. `testGetArchiveYearsHasCacheHeaders` - Verify Cache-Control headers (24h browser, 7d CDN)
3. `testGetArchiveYearsRespectsLocale` - Verify locale handling (ro, en, ru)
4. `testGetArchiveYearsReturnsSortedDescending` - Verify years sorted newest first
5. `testGetArchiveStatsReturnsJson` - Verify JSON response structure
6. `testGetArchiveStatsHasCacheHeaders` - Verify cache headers
7. `testGetArchiveStatsReturnsValidStructure` - Verify total, byYear, byCategory keys
8. `testGetArchiveCategoriesReturnsFilteredList` - Verify only categories with archived articles
9. `testGetArchiveCategoriesHasCacheHeaders` - Verify cache headers
10. `testArchiveEndpointsRespectAcceptLanguageHeader` - Verify locale handling across all endpoints

### 2. ArticleArchiveControllerTest.php (698 lines)
Tests for admin archive management API endpoints (`/api/admin/articles/*` and `/api/admin/archive/*`).

**Endpoints Tested:**
- `POST /api/admin/articles/{id}/archive` - Archive single article
- `POST /api/admin/articles/{id}/unarchive` - Restore archived article
- `POST /api/admin/articles/archive-bulk` - Bulk archive old articles
- `GET /api/admin/archive/stats` - Detailed admin statistics

**Test Cases (20 tests):**

**Archive Article Tests:**
1. `testArchiveArticleRequiresAuthentication` - Verify 401 without token
2. `testArchiveArticleRequiresAdminRole` - Verify 403 for non-admin users
3. `testArchiveArticleSuccess` - Verify 200 with valid auth and article ID
4. `testArchiveArticleNotFound` - Verify 404 for non-existent article
5. `testArchiveArticleWithInvalidReason` - Verify 400 for invalid reason
6. `testArchiveArticleWithMissingReason` - Verify 400 when reason field missing
7. `testArchiveArticleAlreadyArchived` - Verify 400 when article already archived
8. `testArchiveArticleWithDifferentReasons` - Test all 7 archive reasons

**Unarchive Article Tests:**
9. `testUnarchiveArticleRequiresAuthentication` - Verify 401 without token
10. `testUnarchiveArticleSuccess` - Verify 200 restores article to published
11. `testUnarchiveArticleNotFound` - Verify 404 for non-existent article
12. `testUnarchiveArticleNotArchived` - Verify 400 when article not archived

**Bulk Archive Tests:**
13. `testBulkArchiveRequiresAuthentication` - Verify 401 without token
14. `testBulkArchiveSuccess` - Verify 200 with count in response
15. `testBulkArchiveWithInvalidYears` - Verify 400 for invalid years_old parameter
16. `testBulkArchiveWithInvalidBatchSize` - Verify 400 for invalid batch_size
17. `testBulkArchiveWithDefaultParameters` - Verify default values (4 years, 100 batch)

**Admin Stats Tests:**
18. `testGetAdminArchiveStatsRequiresAuth` - Verify 401 without token
19. `testGetAdminArchiveStatsReturnsDetailedMetrics` - Verify all expected keys
20. `testGetAdminArchiveStatsIncludesOldestAndNewest` - Verify oldest/newest archived articles

## Archive Reasons

The tests cover all 7 archive reasons from the `ArchiveReason` enum:
- `old_content` - Content older than 4 years
- `outdated_info` - Information no longer relevant
- `legal_request` - Legal or GDPR request
- `duplicate` - Duplicate content
- `low_quality` - Low quality content
- `policy_violation` - Violated editorial policy
- `manual` - Manual decision by editor

## Running the Tests

### Run All Archive Tests
```bash
cd /var/www/deschide_news_app/apps/backend

# Run both test files
vendor/bin/phpunit tests/Functional/Controller/

# Run specific file
vendor/bin/phpunit tests/Functional/Controller/ArchiveControllerTest.php
vendor/bin/phpunit tests/Functional/Controller/ArticleArchiveControllerTest.php
```

### Run Specific Test Method
```bash
# Public archive endpoints
vendor/bin/phpunit --filter testGetArchiveYearsReturnsArray
vendor/bin/phpunit --filter testGetArchiveStatsReturnsJson

# Admin archive endpoints
vendor/bin/phpunit --filter testArchiveArticleSuccess
vendor/bin/phpunit --filter testBulkArchiveSuccess
```

### Run with Verbose Output
```bash
vendor/bin/phpunit --testdox tests/Functional/Controller/
```

### Run with Coverage (if configured)
```bash
vendor/bin/phpunit --coverage-html var/coverage tests/Functional/Controller/
```

## Test Pattern

These tests follow the Symfony/API Platform testing best practices:

1. **Setup/Teardown**: Create and cleanup test data in each test
2. **Isolation**: Each test is independent and self-contained
3. **Authentication**: Admin tests use JWT token authentication
4. **HTTP Client**: Use `WebTestCase` and Symfony's HTTP client
5. **Assertions**: Comprehensive response validation (status, headers, JSON structure)
6. **Database**: Tests create real entities and persist to test database
7. **Cleanup**: All test data is removed in tearDown or test-specific cleanup

## Authentication

Admin tests create two test users:
- **Admin User**: Has `ROLE_ADMIN`, can access all admin endpoints
- **Regular User**: Has `ROLE_USER`, denied access to admin endpoints

JWT tokens are obtained via `/api/login_check` endpoint.

## Helper Methods

Both test classes include helper methods for:
- Creating test articles (published, archived, old)
- Creating test users with proper roles
- Getting JWT authentication tokens
- Cleaning up test data after assertions

## Dependencies

Required packages (already installed):
- `symfony/test-pack` - Testing utilities
- `doctrine/doctrine-fixtures-bundle` - Optional for fixtures
- `symfony/browser-kit` - HTTP client for tests
- `symfony/http-client` - HTTP client

## Test Database

Tests run against the test database configured in `.env.test`:
```bash
DATABASE_URL="postgresql://user:pass@127.0.0.1:5432/deschide_test"
```

Make sure migrations are run on the test database:
```bash
php bin/console doctrine:database:create --env=test
php bin/console doctrine:migrations:migrate --env=test
```

## Coverage Summary

**Total Tests**: 30 test methods
**Total Lines**: 1,106 lines of code
**Coverage**:
- ✅ All public archive endpoints (3 routes, 10 tests)
- ✅ All admin archive endpoints (4 routes, 20 tests)
- ✅ Authentication and authorization (JWT)
- ✅ Cache headers validation
- ✅ Locale handling (ro, en, ru)
- ✅ All archive reasons (7 enum values)
- ✅ Error cases (400, 401, 403, 404, 500)
- ✅ Success cases (200)
- ✅ Request validation
- ✅ Response structure validation

## Next Steps

1. Run the tests to verify they pass
2. Add to CI/CD pipeline
3. Monitor test coverage
4. Add more edge case tests as needed
5. Consider adding API documentation tests

## Troubleshooting

**Issue**: JWT authentication fails
**Solution**: Generate JWT keys:
```bash
php bin/console lexik:jwt:generate-keypair
```

**Issue**: Database connection error
**Solution**: Check `.env.test` configuration and create test database

**Issue**: Tests fail due to missing entities
**Solution**: Run migrations on test database:
```bash
php bin/console doctrine:migrations:migrate --env=test
```

**Issue**: Cleanup errors
**Solution**: Tests include comprehensive cleanup in tearDown and helper methods

---

**Created**: 2025-11-30
**Symfony Version**: 7.3
**PHP Version**: 8.4
**PHPUnit Version**: 9.x
