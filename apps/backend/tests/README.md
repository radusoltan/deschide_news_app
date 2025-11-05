# Tests Directory

This directory contains all automated tests for the Deschide News Backend application.

## Directory Structure

```
tests/
├── Unit/                   # Unit tests (isolated, no external dependencies)
│   ├── Entity/            # Entity tests (Article, Category, Author, etc.)
│   ├── Service/           # Service tests (business logic)
│   └── Validator/         # Validator tests (custom validation rules)
├── Integration/            # Integration tests (with database, external services)
│   ├── Database/          # Database integration tests
│   └── Repository/        # Repository tests (Doctrine queries)
├── Functional/             # Functional/API tests (full HTTP stack)
│   ├── Api/               # API endpoint tests (JSON-LD, Hydra)
│   └── Controller/        # Controller tests
├── Service/                # Legacy service tests (to be migrated to Unit/)
├── Validator/              # Legacy validator tests (to be migrated to Unit/)
└── bootstrap.php           # Test bootstrap file
```

## Test Categories

### Unit Tests (`tests/Unit/`)
- **Purpose**: Test individual classes in isolation
- **Dependencies**: None (no database, no services)
- **Speed**: Very fast
- **Examples**:
  - Entity getters/setters
  - Business logic methods
  - Transformers
  - Validators (logic only)

**Run unit tests only:**
```bash
vendor/bin/phpunit --testsuite=Unit
```

### Integration Tests (`tests/Integration/`)
- **Purpose**: Test integration between components
- **Dependencies**: Database, Doctrine, Redis, etc.
- **Speed**: Moderate
- **Examples**:
  - Repository queries
  - Database transactions
  - Service integrations

**Run integration tests only:**
```bash
vendor/bin/phpunit --testsuite=Integration
```

### Functional Tests (`tests/Functional/`)
- **Purpose**: Test full HTTP request/response cycle
- **Dependencies**: Full Symfony stack, database
- **Speed**: Slower
- **Examples**:
  - API endpoints
  - Authentication flows
  - Complete user scenarios

**Run functional tests only:**
```bash
vendor/bin/phpunit --testsuite=Functional
```

## Running Tests

### All Tests
```bash
# Run all test suites
vendor/bin/phpunit

# With colors (recommended)
vendor/bin/phpunit --colors=always
```

### Specific Test Suites
```bash
# Unit tests only
vendor/bin/phpunit --testsuite=Unit

# Integration tests only
vendor/bin/phpunit --testsuite=Integration

# Functional tests only
vendor/bin/phpunit --testsuite=Functional

# Legacy tests (Service + Validator)
vendor/bin/phpunit --testsuite=Service
vendor/bin/phpunit --testsuite=Validator
```

### Specific Test Files
```bash
# Single test file
vendor/bin/phpunit tests/Unit/Entity/ArticleTest.php

# Specific test method
vendor/bin/phpunit --filter testArticleCreation tests/Unit/Entity/ArticleTest.php
```

### Code Coverage
```bash
# Generate HTML coverage report
vendor/bin/phpunit --coverage-html var/coverage

# View coverage in browser
open var/coverage/index.html  # macOS
xdg-open var/coverage/index.html  # Linux
```

## Writing Tests

### Unit Test Example

```php
<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Article;
use PHPUnit\Framework\TestCase;

class ArticleTest extends TestCase
{
    public function testArticleCreation(): void
    {
        $article = new Article();
        $article->setTitle('Test Article');

        $this->assertEquals('Test Article', $article->getTitle());
    }
}
```

### Integration Test Example

```php
<?php

namespace App\Tests\Integration\Repository;

use App\Entity\Article;
use App\Repository\ArticleRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ArticleRepositoryTest extends KernelTestCase
{
    private ArticleRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = self::getContainer()->get(ArticleRepository::class);
    }

    public function testFindPublishedArticles(): void
    {
        $articles = $this->repository->findPublished();

        $this->assertIsArray($articles);
    }
}
```

### Functional Test Example

```php
<?php

namespace App\Tests\Functional\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ArticleApiTest extends WebTestCase
{
    public function testGetArticles(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/articles');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
    }
}
```

## Test Database

Tests use a separate test database configured in `.env.test`:

```bash
DATABASE_URL="postgresql://user:password@127.0.0.1:5432/deschide_test?serverVersion=16&charset=utf8"
```

**Setup test database:**
```bash
# Create test database
APP_ENV=test symfony console doctrine:database:create

# Run migrations
APP_ENV=test symfony console doctrine:migrations:migrate --no-interaction

# Load fixtures (optional)
APP_ENV=test symfony console doctrine:fixtures:load --no-interaction
```

## Best Practices

### 1. Test Naming
- Use descriptive test method names: `testArticleCreation`, `testInvalidEmailThrowsException`
- Prefix with `test` (PHPUnit requirement)

### 2. Arrange-Act-Assert Pattern
```php
public function testExample(): void
{
    // Arrange: Setup test data
    $article = new Article();

    // Act: Execute the action
    $article->setTitle('Test');

    // Assert: Verify the result
    $this->assertEquals('Test', $article->getTitle());
}
```

### 3. Test Independence
- Each test should be independent
- Don't rely on test execution order
- Clean up resources in `tearDown()`

### 4. Use Data Providers
```php
/**
 * @dataProvider slugProvider
 */
public function testSlugGeneration(string $input, string $expected): void
{
    $article = new Article();
    $article->setTitle($input);

    $this->assertEquals($expected, $article->getSlug());
}

public function slugProvider(): array
{
    return [
        ['Hello World', 'hello-world'],
        ['Test Article', 'test-article'],
    ];
}
```

### 5. Coverage Goals
- **Unit Tests**: 80%+ coverage
- **Integration Tests**: Critical paths
- **Functional Tests**: Main user workflows

## CI/CD Integration

Tests run automatically on:
- Every pull request
- Every push to main branch
- Scheduled nightly builds

**GitHub Actions example:**
```yaml
- name: Run tests
  run: vendor/bin/phpunit --coverage-clover coverage.xml
```

## Troubleshooting

### "No tests executed"
- Check test class extends `TestCase` or `KernelTestCase`
- Verify test methods start with `test` prefix
- Ensure test files end with `Test.php`

### "Database connection failed"
- Check `.env.test` has correct database credentials
- Verify test database exists: `APP_ENV=test symfony console doctrine:database:create`

### "Class not found"
- Run `composer dump-autoload`
- Verify namespace matches directory structure

## Legacy Tests Migration

Tests in `tests/Service/` and `tests/Validator/` are legacy tests that should be migrated to the new structure:

- `tests/Service/SlugLookupServiceTest.php` → `tests/Unit/Service/SlugLookupServiceTest.php`
- `tests/Validator/ReservedSlugValidatorTest.php` → `tests/Unit/Validator/ReservedSlugValidatorTest.php`

**Migration is tracked in**: Sprint 2, Week 2 of OPTIMIZATION_PLAN.md

## Additional Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Symfony Testing](https://symfony.com/doc/current/testing.html)
- [API Platform Testing](https://api-platform.com/docs/core/testing/)

---

**Last Updated**: 4 Noiembrie 2025
**PHPUnit Version**: 12.4
**Symfony Version**: 7.3
