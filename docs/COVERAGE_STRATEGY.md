# Backend Coverage Strategy — From 69% to 80%+

## Current State (Post Sprint 11 Z3)

| Metric | Value |
|--------|-------|
| **Line Coverage** | **69.08%** (13,204 / 19,114) |
| Methods Coverage | 73.85% (1,505 / 2,038) |
| Classes Coverage | 48.64% (143 / 294) |
| Total Tests | 3,568 |
| Coverage Driver | PCOV 1.0.12 |
| PHPUnit Version | 12.5.15 |

### Root Cause of "0.69%" Report

The audit that reported 0.69% coverage ran PHPUnit **without enabling PCOV**. Without `-d pcov.enabled=1` or `XDEBUG_MODE=coverage`, PHPUnit cannot measure coverage and reports near-zero. The `phpunit.dist.xml` `<source>` configuration was correct all along.

**Fix**: Always run coverage with:
```bash
php -d pcov.enabled=1 vendor/bin/phpunit --coverage-text
```

## Test Distribution

| Category | Test Count | Base Class | Impact on Coverage |
|----------|-----------|------------|-------------------|
| Unit (mock-only) | ~3,128 | `TestCase` | High (mocks + real entity code) |
| Integration (DB) | ~107 | `KernelTestCase` | Medium (repositories, services) |
| Functional (HTTP) | ~219 | `WebTestCase` | High (full stack traversal) |
| Smoke (endpoints) | ~31 | `WebTestCase` | Medium (controllers + providers) |
| Service | ~23 | `TestCase` | Low (isolated) |
| Validator | ~43 | `TestCase` | Low (isolated) |

## Zero-Coverage Classes by Namespace (184 total)

| Namespace | Count | Priority | Effort |
|-----------|-------|----------|--------|
| `State\` (Providers/Processors) | 29 | HIGH | Medium — functional tests needed |
| `Command\` | 27 | LOW | Low — import/admin commands, test with ApplicationTester |
| `Entity\` | 25 | HIGH | Low — accessor tests or covered via integration tests |
| `Service\` | 19 | HIGH | Medium — integration tests with real DB |
| `Controller\` | 16 | HIGH | Medium — functional tests with WebTestCase |
| `Repository\` | 15 | MEDIUM | Medium — integration tests with KernelTestCase |
| `EventSubscriber\` | 8 | MEDIUM | Low — unit tests with mock events |
| `Dto\` | 8 | LOW | Very low — data classes |
| `MessageHandler\` | 7 | MEDIUM | Medium — integration tests with messenger |
| `Message\` | 7 | LOW | Very low — value objects |
| `EventListener\` | 5 | MEDIUM | Low |
| Other | 33 | LOW | Low |

## Known Test Issues (Pre-existing)

| Issue | Count | Root Cause | Fix |
|-------|-------|-----------|-----|
| `ClassIsFinalException` | ~132 | PHPUnit 12 can't mock `final` classes | Use `#[MockClass]` or remove `final` |
| Enum assertion mismatches | ~6 | Tests not updated after enum expansion | Update expected values |
| Smoke 500 errors | ~13 | Missing test data or service config | Fix test fixtures |
| Integration DB errors | ~11 | Test DB schema or missing data | Run migrations, add fixtures |

## Plan to Reach 80%+

### Phase 1: Fix Existing Test Failures (est. 1-2 days)

**Target: 0 errors/failures, coverage stays ~69%**

1. Fix `ClassIsFinalException` errors (132 tests):
   - Option A: Remove `final` from service classes that need mocking
   - Option B: Use interface-based mocking
   - Option C: Use `#[AllowMockingFinalClasses]` attribute (PHPUnit 12)
2. Fix enum test assertions (6 failures)
3. Fix Smoke test 500 errors (13 failures)
4. Fix Integration DB errors (11 errors)

### Phase 2: 69% → 75% (est. 2-3 days)

**Quick wins — cover the most code with least effort:**

1. **Entity tests** (25 uncovered): Simple accessor/mutator tests cover many lines
2. **Service integration tests** (19 uncovered): Test with `KernelTestCase` and real DB
3. **Controller functional tests** (16 uncovered): `WebTestCase` with HTTP requests

### Phase 3: 75% → 80%+ (est. 3-4 days)

**Deeper coverage:**

1. **State Providers/Processors** (29 uncovered): Functional tests through API Platform
2. **Repository tests** (15 uncovered): Integration tests with `KernelTestCase`
3. **Event subscribers** (8 uncovered): Unit tests with mock events
4. **Message handlers** (7 uncovered): Integration tests with `KernelTestCase`

## CI/CD Integration (Recommended)

```yaml
# GitHub Actions snippet
- name: Run tests with coverage
  run: |
    php -d pcov.enabled=1 vendor/bin/phpunit --coverage-clover var/coverage/clover.xml
    # Fail if coverage drops below threshold
    php vendor/bin/phpunit --coverage-text | grep "Lines:" | awk '{if ($2 < 65) exit 1}'
```

## Coverage Command Reference

```bash
# Quick text coverage
php -d pcov.enabled=1 vendor/bin/phpunit --coverage-text --colors=never

# HTML report (detailed, browsable)
php -d pcov.enabled=1 vendor/bin/phpunit --coverage-html var/coverage/html

# Clover XML (for CI/CD tools)
php -d pcov.enabled=1 vendor/bin/phpunit --coverage-clover var/coverage/clover.xml

# Coverage for specific directory
php -d pcov.enabled=1 vendor/bin/phpunit --coverage-text tests/Unit/
```
