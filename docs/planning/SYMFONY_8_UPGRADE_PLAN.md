# Symfony 8 Upgrade Plan - Deschide News App

**Document Version:** 2.0  
**Last Updated:** 2025-12-10  
**Status:** Draft for Review  
**Estimated Timeline:** 10-12 weeks  
**Risk Level:** Medium-High (API Platform 4.x migration + PHP 8.4)

---

## Executive Summary

Acest plan documentează strategia de upgrade a Deschide News App de la **Symfony 7.3** la **Symfony 8.0**, lansată pe 27 noiembrie 2024. Upgrade-ul este **necesar și benefic** pentru:

1. **Securitate:** Symfony 7.3 va primi suport doar până în noiembrie 2026
2. **Performance:** PHP 8.4 + Symfony 8 aduc îmbunătățiri semnificative (lazy objects, optimizări JIT)
3. **Funcționalitate:** ICU MessageFormat îmbunătățit pentru multilingual (ro/en/ru)
4. **Curățenie cod:** Eliminarea a 13,202 linii de cod deprecat

### Current State Analysis

```json
{
  "symfony_version": "7.3.*",
  "php_version": ">=8.2",
  "api_platform": "4.2.6",  // ✅ Deja compatibil Symfony 8!
  "doctrine_orm": "3.5.7",   // ✅ Deja compatibil Symfony 8!
  "doctrine_dbal": "3.10.4", // ⚠️ Necesită upgrade la 4.x
  "agents_count": 25,         // Multi-agent architecture
  "languages": ["ro", "en", "ru"]
}
```

### Critical Dependencies Status

| Dependency | Current | Target | Compatibility | Effort |
|------------|---------|--------|---------------|--------|
| **PHP** | >=8.2 | **8.4** | ⚠️ Mandatory | Medium |
| **Symfony** | 7.3.* | **8.0** | ⚠️ Breaking changes | High |
| **API Platform** | 4.2.6 | 4.2.7+ | ✅ Ready | Low |
| **Doctrine ORM** | 3.5.7 | 3.5+ | ✅ Ready | None |
| **Doctrine DBAL** | 3.10.4 | **4.x** | ⚠️ BC breaks | Medium |
| **Doctrine Migrations** | 3.7 | **4.0** | ⚠️ Requires PHP 8.4 | Low |
| **Lexik JWT** | 3.1.1 | 3.x | ✅ Ready | None |
| **Elasticsearch** | 9.2 | 9.x | ✅ Ready | None |
| **Predis** | 3.3 | 3.x | ✅ Ready | None |

**Status Color Legend:**
- 🟢 **Low Risk:** Drop-in replacement, no code changes
- 🟡 **Medium Risk:** Minor code changes required
- 🔴 **High Risk:** Significant refactoring needed

---

## Upgrade Strategy: Two-Phase Approach

### Why Two Phases?

Symfony 8.0 **removes** toate funcționalitățile deprecate din 7.x. Dacă aplicația folosește cod deprecat, upgrade-ul direct va **eșua**. Strategia în două faze:

1. **Phase 1:** Upgrade la Symfony 7.4 (ultima versiune 7.x) + fix all deprecations
2. **Phase 2:** Upgrade la Symfony 8.0 (primul release major)

Această abordare este **non-negociabilă** și recomandată oficial de Symfony core team.

---

## Phase 1: Symfony 7.4 + Deprecation Elimination

**Duration:** 4 weeks  
**Risk Level:** 🟡 Medium  
**Team:** 2 developers + 1 QA

### Week 1-2: Preparation & Audit

#### 1.1 Environment Setup

```bash
# Create upgrade branch
cd apps/backend
git checkout -b upgrade/symfony-7.4-preparation
git push -u origin upgrade/symfony-7.4-preparation

# Install analysis tools
composer require --dev rector/rector:^1.0
composer require --dev phpstan/phpstan-deprecation-rules:^2.0

# Verify current PHP version
php -v  # Should show 8.2 or 8.3

# Check current Symfony version
php bin/console --version
```

#### 1.2 Deprecation Audit

**A. Run tests with deprecation tracking:**

```bash
# Enable deprecation tracking
export SYMFONY_DEPRECATIONS_HELPER=weak
./vendor/bin/phpunit --testdox

# Alternative: Generate deprecation report
SYMFONY_DEPRECATIONS_HELPER=weak ./vendor/bin/phpunit > deprecations.txt 2>&1
```

**Expected output structure:**
```
Remaining deprecation notices (X)
  X: Path/To/DeprecatedClass.php
    - Description of deprecation
    - Suggested fix
```

**B. Check container deprecations:**

```bash
php bin/console debug:container --deprecations > container_deprecations.txt
```

**C. Scan code with PHPStan:**

```bash
# Run with deprecation rules
./vendor/bin/phpstan analyse src tests \
  --level 8 \
  --configuration phpstan.neon \
  > phpstan_report.txt
```

#### 1.3 Create Deprecation Inventory

Create `docs/planning/deprecations_inventory.md`:

```markdown
# Deprecation Inventory - Symfony 7.3 to 7.4

## Critical (Must Fix Before Upgrade)
- [ ] TaggedIterator → AutowireIterator (XX occurrences)
- [ ] Request::get() usage (XX occurrences)
- [ ] Application::add() → addCommand() (XX occurrences)

## High Priority
- [ ] OIDC token handler config
- [ ] Implicit validator constraints

## Medium Priority
- [ ] ... 

## Low Priority
- [ ] ...
```

#### 1.4 Establish Baseline Metrics

```bash
# Performance baseline
cd ../../k6
k6 run --out json=baseline_7.3.json load-test.js

# Test coverage baseline
cd ../apps/backend
XDEBUG_MODE=coverage ./vendor/bin/phpunit --coverage-text > coverage_baseline.txt

# Code quality baseline
./vendor/bin/phpstan analyse --memory-limit=1G > quality_baseline.txt
```

**Store metrics in:** `docs/planning/upgrade_baselines.json`

```json
{
  "symfony_version": "7.3",
  "date": "2025-12-10",
  "metrics": {
    "test_coverage": 78.5,
    "phpstan_errors": 0,
    "deprecations_count": 0,
    "api_response_time_p95": 250,
    "memory_usage_avg": 45
  }
}
```

### Week 3: Symfony 7.4 Upgrade

#### 3.1 Update composer.json

```bash
cd apps/backend

# Backup current state
cp composer.json composer.json.backup
cp composer.lock composer.lock.backup
```

**Edit `composer.json`:**

```json
{
    "require": {
        "php": ">=8.2",
        "symfony/framework-bundle": "7.4.*"
        // ... keep other dependencies
    },
    "extra": {
        "symfony": {
            "require": "7.4.*"
        }
    }
}
```

#### 3.2 Execute Upgrade

```bash
# Clear caches first
rm -rf var/cache/*

# Update Symfony packages
composer update "symfony/*" --with-all-dependencies

# Update Flex recipes (IMPORTANT!)
composer recipes:update

# Clear cache again
php bin/console cache:clear --env=prod
php bin/console cache:clear --env=dev
```

#### 3.3 Verify Installation

```bash
# Check Symfony version
php bin/console --version  # Should show 7.4.x

# Verify all services compile
php bin/console debug:container --env=prod

# Check for new deprecations
SYMFONY_DEPRECATIONS_HELPER=weak ./vendor/bin/phpunit
```

### Week 4: Deprecation Fixes

#### 4.1 Common Deprecation Patterns

**Pattern 1: TaggedIterator → AutowireIterator**

Search pattern: `#\[TaggedIterator\(`

```php
// BEFORE (7.3) - will break in 8.0
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class ArticleProcessor {
    public function __construct(
        #[TaggedIterator('app.article_enricher')] 
        private iterable $enrichers
    ) {}
}

// AFTER (7.4+, 8.0 ready)
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class ArticleProcessor {
    public function __construct(
        #[AutowireIterator('app.article_enricher')] 
        private iterable $enrichers
    ) {}
}
```

**Pattern 2: Request::get() removal**

Search pattern: `\$request->get\(`

```php
// BEFORE - will throw error in 8.0
public function show(Request $request): JsonResponse
{
    $id = $request->get('id');
    $page = $request->get('page', 1);
}

// AFTER - explicit parameter bags
public function show(Request $request): JsonResponse
{
    $id = $request->attributes->get('id');    // Route params
    $page = $request->query->get('page', 1);  // Query string
}
```

**Pattern 3: Application::add() → addCommand()**

Search in: `src/Command/` and any console scripts

```php
// BEFORE
$application = new Application();
$application->add(new ImportNewsCommand());

// AFTER
$application->addCommand(new ImportNewsCommand());
```

**Pattern 4: Validator implicit constraints**

Search in: `config/validator/` YAML files

```yaml
# BEFORE (implicit) - breaks in 8.0
App\Entity\Article:
    constraints:
        - Callback: validatePublishDate

# AFTER (explicit)
App\Entity\Article:
    constraints:
        - Callback:
            callback: validatePublishDate
```

#### 4.2 Automated Refactoring with Rector

**Create `rector.php`:**

```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Symfony\Set\SymfonySetList;
use Rector\Set\ValueObject\LevelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSets([
        LevelSetList::UP_TO_PHP_83,
        SymfonySetList::SYMFONY_74,
        SymfonySetList::SYMFONY_CODE_QUALITY,
        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
    ])
    ->withSkip([
        // Add files to skip if needed
    ])
    ->withImportNames(importShortClasses: false);
```

**Run Rector:**

```bash
# Dry run first (preview changes)
./vendor/bin/rector process --dry-run

# Review changes, then apply
./vendor/bin/rector process

# Run tests after
./vendor/bin/phpunit
```

#### 4.3 Manual Review Checklist

**File-by-file review using agents:**

```bash
# Use backend-api-tester agent
claude --agent backend-api-tester

# Task for agent:
"Review all controller files in src/Controller/ for deprecated patterns:
1. Request::get() usage
2. Response type hints
3. Route attribute syntax
4. Service injection patterns
Report findings in structured format."
```

**Critical files to review:**
- [ ] `src/Controller/` - all controllers
- [ ] `src/Security/` - authentication/authorization
- [ ] `src/Service/` - service layer
- [ ] `src/Repository/` - repository classes
- [ ] `src/Command/` - console commands
- [ ] `src/EventListener/` - event subscribers
- [ ] `config/packages/security.yaml` - OIDC config if used
- [ ] `config/validator/*.yaml` - validation config

#### 4.4 Testing Strategy

**A. Run full test suite:**

```bash
# Unit tests
./vendor/bin/phpunit tests/Unit

# Integration tests
./vendor/bin/phpunit tests/Integration

# API tests
./vendor/bin/phpunit tests/Api
```

**B. Manual testing with agents:**

Use specialized agents for comprehensive coverage:

```bash
# 1. Backend API Testing
claude --agent backend-api-tester
"Test all API endpoints for correct responses after Symfony 7.4 upgrade:
- GET /api/articles (all languages: ro, en, ru)
- POST /api/articles
- PUT /api/articles/{id}
- DELETE /api/articles/{id}
- Authentication endpoints
- File upload endpoints"

# 2. Multilingual Testing
claude --agent multilanguage-tester
"Verify multilingual functionality after upgrade:
- Content negotiation for ro/en/ru
- Translation keys resolution
- Locale-specific routes
- ICU MessageFormat patterns"

# 3. Performance Testing
claude --agent performance-tester
"Run performance benchmarks and compare with baseline:
- API response times
- Database query performance
- Cache hit rates
- Memory consumption"

# 4. Security Audit
claude --agent security-auditor
"Security audit after Symfony 7.4 upgrade:
- JWT token generation/validation
- CSRF protection
- CORS configuration
- Rate limiting
- Input validation"
```

**C. Integration testing:**

```bash
# Use fullstack-integration-tester agent
claude --agent fullstack-integration-tester
"Test complete workflows:
1. User authentication flow
2. Article CRUD operations
3. Image upload and processing
4. Search functionality (Elasticsearch)
5. Cache invalidation patterns
6. Real-time updates (if Mercure is used)"
```

### Phase 1 Completion Criteria

**All criteria must be met before proceeding to Phase 2:**

- [ ] ✅ Symfony version = 7.4.x confirmed
- [ ] ✅ Zero deprecation warnings in tests
- [ ] ✅ Zero deprecation warnings in container
- [ ] ✅ PHPStan Level 8 passes with no errors
- [ ] ✅ All tests passing (100% of previous passing tests)
- [ ] ✅ Test coverage >= baseline (78.5%)
- [ ] ✅ Performance metrics within 5% of baseline
- [ ] ✅ All 25 agents validated their domains
- [ ] ✅ Staging deployment successful
- [ ] ✅ Production smoke tests passed
- [ ] ✅ Rollback procedure documented and tested

**Generate completion report:**

```bash
# Create completion report
cat > docs/planning/phase1_completion_report.md << 'EOF'
# Phase 1 Completion Report

## Metrics Comparison

| Metric | Baseline (7.3) | Current (7.4) | Change |
|--------|----------------|---------------|--------|
| Deprecations | XX | 0 | -XX ✅ |
| Test Coverage | 78.5% | XX% | +/-X% |
| PHPStan Errors | 0 | 0 | 0 ✅ |
| API Response P95 | 250ms | XXms | +/-X% |

## Deprecations Fixed

1. TaggedIterator → AutowireIterator: XX files
2. Request::get() removed: XX occurrences
3. Application::add() → addCommand(): XX files
... (complete list)

## Risk Assessment for Phase 2

Low/Medium/High + justification

## Next Steps

Proceed to Phase 2: Symfony 8.0 upgrade
EOF
```

---

## Phase 2: Symfony 8.0 Upgrade

**Duration:** 6-8 weeks  
**Risk Level:** 🔴 High (major version, breaking changes)  
**Team:** 2 senior developers + 1 QA + 1 DevOps

### Week 5-6: Dependency Updates

#### 5.1 PHP 8.4 Upgrade

**A. Server/Container Setup:**

```bash
# On Ubuntu 24.04 (WSL)
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install php8.4-cli php8.4-fpm php8.4-pgsql php8.4-redis \
  php8.4-curl php8.4-mbstring php8.4-xml php8.4-zip php8.4-intl

# Verify installation
php8.4 -v  # Should show PHP 8.4.x

# Update alternatives
sudo update-alternatives --set php /usr/bin/php8.4
```

**B. Update composer.json:**

```json
{
    "require": {
        "php": ">=8.4",
        // ... rest of dependencies
    }
}
```

**C. Test PHP 8.4 compatibility:**

```bash
# Run with new PHP version
php8.4 bin/console --version
php8.4 vendor/bin/phpunit

# Check for PHP 8.4 specific issues
php8.4 -d error_reporting=E_ALL vendor/bin/phpunit
```

#### 5.2 Doctrine DBAL 4.x Upgrade

**Critical:** Doctrine DBAL 4.x has breaking changes in type system.

**Update composer.json:**

```json
{
    "require": {
        "doctrine/dbal": "^4.2",
        "doctrine/doctrine-bundle": "^2.18.1",
        "doctrine/orm": "^3.5.7",
        "doctrine/doctrine-migrations-bundle": "^4.0"
    }
}
```

**Run upgrade:**

```bash
composer update doctrine/dbal doctrine/doctrine-migrations-bundle \
  --with-all-dependencies

# Check for DBAL-specific issues
php bin/console doctrine:schema:validate
php bin/console doctrine:migrations:status
```

**Common DBAL 4.x breaking changes:**

```php
// BEFORE (DBAL 3.x)
use Doctrine\DBAL\Types\Type;

// Custom type registration
Type::addType('my_type', MyType::class);

// AFTER (DBAL 4.x) - use TypeRegistry
use Doctrine\DBAL\Types\Type;

// Type registration now through DBAL configuration
// In doctrine.yaml:
doctrine:
    dbal:
        types:
            my_type: App\Doctrine\Type\MyType
```

#### 5.3 Doctrine Migrations 4.x Compatibility

**Check existing migrations:**

```bash
# List all migrations
php bin/console doctrine:migrations:list

# Verify migration compatibility
php bin/console doctrine:migrations:migrate --dry-run
```

**If custom migration classes exist, update:**

```php
// BEFORE (Migrations 3.x)
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20241210120000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ...');
    }
}

// AFTER (Migrations 4.x) - same interface, but stricter types
// No changes needed if using standard patterns
```

#### 5.4 Other Dependencies Check

**Review each dependency individually:**

```bash
# Check what can be updated
composer outdated

# Update non-critical packages first
composer update behat/transliterator league/csv --with-all-dependencies

# Test after each significant update
./vendor/bin/phpunit
```

**Special attention to:**

- `vich/uploader-bundle` - verify Symfony 8 compatibility
- `stof/doctrine-extensions-bundle` - check for updates
- `intervention/image` - verify PHP 8.4 compatibility
- `elasticsearch/elasticsearch` - already on 9.2, should be fine

### Week 7-8: Symfony 8.0 Core Upgrade

#### 7.1 Pre-Upgrade Verification

```bash
# Verify all Phase 1 criteria are still met
SYMFONY_DEPRECATIONS_HELPER=weak ./vendor/bin/phpunit

# Should output: "Remaining deprecation notices (0)"
```

#### 7.2 Update composer.json to Symfony 8.0

```json
{
    "require": {
        "php": ">=8.4",
        "symfony/framework-bundle": "8.0.*",
        "symfony/console": "8.0.*",
        "symfony/doctrine-messenger": "8.0.*",
        "symfony/dotenv": "8.0.*",
        "symfony/expression-language": "8.0.*",
        "symfony/http-client": "8.0.*",
        "symfony/messenger": "8.0.*",
        "symfony/property-access": "8.0.*",
        "symfony/property-info": "8.0.*",
        "symfony/rate-limiter": "8.0.*",
        "symfony/runtime": "8.0.*",
        "symfony/scheduler": "8.0.*",
        "symfony/security-bundle": "8.0.*",
        "symfony/serializer": "8.0.*",
        "symfony/twig-bundle": "8.0.*",
        "symfony/validator": "8.0.*",
        "symfony/yaml": "8.0.*",
        "symfony/asset": "8.0.*",
        "symfony/object-mapper": "8.0.*"
    },
    "require-dev": {
        "symfony/browser-kit": "8.0.*",
        "symfony/css-selector": "8.0.*",
        "symfony/maker-bundle": "^1.65",
        "symfony/stopwatch": "8.0.*",
        "symfony/web-profiler-bundle": "8.0.*"
    },
    "extra": {
        "symfony": {
            "require": "8.0.*"
        }
    }
}
```

#### 7.3 Execute Upgrade

```bash
# Backup everything
cp composer.json composer.json.pre-sf8
cp composer.lock composer.lock.pre-sf8
git add -A
git commit -m "Backup before Symfony 8.0 upgrade"

# Clear all caches
rm -rf var/cache/*
rm -rf var/log/*

# Perform upgrade
composer update "symfony/*" --with-all-dependencies

# This will take several minutes...
```

#### 7.4 Post-Upgrade Configuration Updates

**A. Update Flex recipes:**

```bash
composer recipes:update

# Review and accept/reject recipe updates
# Pay special attention to:
# - config/packages/security.yaml
# - config/packages/framework.yaml  
# - config/routes.yaml
```

**B. Clear and warm caches:**

```bash
# Production cache
php bin/console cache:clear --env=prod --no-debug
php bin/console cache:warmup --env=prod

# Dev cache  
php bin/console cache:clear --env=dev
php bin/console cache:warmup --env=dev

# Test cache
php bin/console cache:clear --env=test
```

**C. Verify installation:**

```bash
# Check version
php bin/console --version
# Expected: Symfony 8.0.x

# Check container compilation
php bin/console debug:container --env=prod

# Check routes
php bin/console debug:router
```

### Week 9-10: Testing & Validation

#### 9.1 Automated Testing

**A. PHPUnit Test Suite:**

```bash
# Run full suite
./vendor/bin/phpunit

# Run with coverage
XDEBUG_MODE=coverage ./vendor/bin/phpunit \
  --coverage-html var/coverage/symfony8

# Check for test failures
# If failures occur, investigate and fix
```

**B. PHPStan Analysis:**

```bash
# Run static analysis
./vendor/bin/phpstan analyse --memory-limit=2G

# Should show 0 errors
```

**C. Code Style:**

```bash
# Check code style
./vendor/bin/php-cs-fixer fix --dry-run --diff

# Fix issues if any
./vendor/bin/php-cs-fixer fix
```

#### 9.2 Agent-Based Testing Campaign

**Create testing checklist:** `docs/testing/symfony8_validation_checklist.md`

```markdown
# Symfony 8 Validation Checklist

## Backend API Testing
- [ ] Agent: backend-api-tester
- [ ] All CRUD operations for Articles
- [ ] All CRUD operations for Categories  
- [ ] All CRUD operations for Authors
- [ ] Authentication (JWT)
- [ ] Authorization (roles/permissions)
- [ ] File uploads (images)
- [ ] Search (Elasticsearch)
- [ ] Pagination
- [ ] Filtering
- [ ] Sorting

## Multilingual Testing
- [ ] Agent: multilanguage-tester
- [ ] Content in Romanian
- [ ] Content in English
- [ ] Content in Russian
- [ ] Language switching
- [ ] Locale-specific routes
- [ ] Translation fallbacks
- [ ] Date/time formatting per locale

## Performance Testing  
- [ ] Agent: performance-tester
- [ ] API response times (compare with baseline)
- [ ] Database query performance
- [ ] Cache efficiency
- [ ] Memory usage
- [ ] Concurrent users handling
- [ ] Load test with k6

## Security Audit
- [ ] Agent: security-auditor
- [ ] JWT token handling
- [ ] XSS protection
- [ ] CSRF validation
- [ ] SQL injection tests
- [ ] Rate limiting
- [ ] CORS policies
- [ ] File upload security

## Integration Tests
- [ ] Agent: fullstack-integration-tester  
- [ ] Frontend <-> Backend communication
- [ ] Redis caching
- [ ] Elasticsearch indexing
- [ ] Image processing pipeline
- [ ] Scheduled tasks (Symfony Scheduler)
- [ ] Message queue (Symfony Messenger)

## Database Integrity
- [ ] Agent: database-engineer
- [ ] Schema validation
- [ ] Migrations integrity
- [ ] Foreign key constraints
- [ ] Indexes performance
- [ ] Query performance
```

**Execute agent tests:**

```bash
# Example: Backend API Testing
claude --agent backend-api-tester << 'EOF'
Execute comprehensive API testing for Symfony 8 upgrade validation:

1. Test all endpoints in /api/articles
2. Test authentication endpoints
3. Test file upload endpoints
4. Verify response formats (JSON-LD)
5. Check HTTP status codes
6. Validate error responses
7. Test with different locales (ro, en, ru)

Generate detailed report with:
- Pass/Fail status for each test
- Response times
- Any errors or warnings
- Comparison with expected behavior
EOF

# Continue with other agents...
```

#### 9.3 Performance Benchmarking

**A. Load testing with k6:**

```bash
cd k6

# Run baseline comparison test
k6 run --out json=symfony8_results.json load-test.js

# Compare with baseline
node compare_results.js baseline_7.3.json symfony8_results.json
```

**B. Profiling:**

```bash
# Enable profiler
php bin/console debug:config web_profiler

# Make test requests and review profiles
# Access /_profiler in dev environment
```

**C. Database performance:**

```bash
# Analyze slow queries
psql -U postgres -d deschide_news \
  -c "SELECT * FROM pg_stat_statements ORDER BY total_exec_time DESC LIMIT 20;"

# Check index usage
php bin/console doctrine:schema:validate --verbose
```

#### 9.4 Staging Deployment

**A. Deploy to staging:**

```bash
# Tag release
git tag -a v8.0.0-rc1 -m "Symfony 8.0 Release Candidate 1"
git push origin v8.0.0-rc1

# Deploy to staging (adjust based on your deployment process)
# Example for manual deployment:
cd /var/www/staging/deschide_news_app/apps/backend
git fetch origin
git checkout v8.0.0-rc1
composer install --no-dev --optimize-autoloader
php bin/console cache:clear --env=prod
php bin/console doctrine:migrations:migrate --no-interaction

# Restart PHP-FPM
sudo systemctl restart php8.4-fpm
```

**B. Smoke testing in staging:**

```bash
# Basic connectivity
curl -I https://staging.deschide.md/api/articles

# Health check endpoint (if exists)
curl https://staging.deschide.md/api/health

# Authenticated request
curl -H "Authorization: Bearer YOUR_TEST_TOKEN" \
  https://staging.deschide.md/api/articles
```

**C. Monitor staging:**

```bash
# Check logs
tail -f /var/www/staging/deschide_news_app/apps/backend/var/log/prod.log

# Monitor errors
grep ERROR /var/www/staging/deschide_news_app/apps/backend/var/log/prod.log
```

### Week 11-12: Production Deployment

#### 11.1 Pre-Deployment Checklist

```markdown
# Production Deployment Checklist

## Technical Preparation
- [ ] All tests passing in staging
- [ ] Performance metrics acceptable
- [ ] No critical errors in staging logs (48h monitoring)
- [ ] Database backup completed
- [ ] Code backup/tag created
- [ ] Rollback procedure documented and tested
- [ ] PHP 8.4 installed on production servers
- [ ] Composer dependencies verified

## Team Preparation  
- [ ] Deployment announcement sent (48h notice)
- [ ] Maintenance window scheduled
- [ ] On-call team identified
- [ ] Rollback team identified
- [ ] Communication plan ready

## Monitoring Preparation
- [ ] Alerting thresholds configured
- [ ] Monitoring dashboards updated
- [ ] Log aggregation ready
- [ ] Performance baseline documented

## Documentation
- [ ] CHANGELOG updated
- [ ] API documentation updated (if changes)
- [ ] Deployment runbook reviewed
- [ ] Rollback runbook reviewed
```

#### 11.2 Deployment Strategy: Blue-Green

**Recommended approach:** Blue-Green deployment to minimize downtime and enable instant rollback.

```bash
# Assuming blue-green infrastructure exists

# 1. Deploy to GREEN environment (while BLUE is live)
ssh production-green
cd /var/www/deschide_news_app/apps/backend
git fetch origin
git checkout tags/v8.0.0
composer install --no-dev --optimize-autoloader --classmap-authoritative
php bin/console cache:clear --env=prod --no-debug
php bin/console cache:warmup --env=prod --no-debug
php bin/console doctrine:migrations:migrate --no-interaction

# 2. Restart services on GREEN
sudo systemctl restart php8.4-fpm
sudo systemctl restart redis

# 3. Smoke test GREEN
curl -I https://green.deschide.md/api/articles

# 4. Route 5% traffic to GREEN (load balancer config)
# Monitor for 30 minutes

# 5. If stable, route 50% traffic
# Monitor for 1 hour

# 6. If stable, route 100% traffic to GREEN
# BLUE becomes standby for rollback

# 7. After 24h of stable operation, decommission BLUE
```

#### 11.3 Deployment Execution

**Deployment Day Timeline:**

```
T-2 hours:  Final staging verification
T-1 hour:   Team briefing, go/no-go decision
T-0:        Deployment starts
  +5 min:   Deploy to GREEN
  +10 min:  Smoke tests
  +15 min:  Route 5% traffic
  +45 min:  Check metrics, proceed to 50%
  +1h 45m:  Check metrics, proceed to 100%
T+2 hours:  Deployment complete, monitor
T+24h:      Post-deployment review
```

**Deployment script:** `scripts/deploy-symfony8.sh`

```bash
#!/bin/bash
set -e

DEPLOY_ENV=${1:-production}
DEPLOY_TAG=${2:-v8.0.0}

echo "Starting Symfony 8 deployment to $DEPLOY_ENV..."
echo "Tag: $DEPLOY_TAG"

# 1. Backup current state
echo "Creating backup..."
timestamp=$(date +%Y%m%d_%H%M%S)
tar -czf /var/backups/deschide_$timestamp.tar.gz \
  /var/www/deschide_news_app/apps/backend

# 2. Deploy new version
echo "Deploying $DEPLOY_TAG..."
cd /var/www/deschide_news_app/apps/backend
git fetch origin --tags
git checkout tags/$DEPLOY_TAG

# 3. Install dependencies
echo "Installing dependencies..."
composer install --no-dev --optimize-autoloader --classmap-authoritative

# 4. Run migrations
echo "Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --env=prod

# 5. Clear and warm caches
echo "Preparing caches..."
php bin/console cache:clear --env=prod --no-debug
php bin/console cache:warmup --env=prod --no-debug

# 6. Restart services
echo "Restarting services..."
sudo systemctl restart php8.4-fpm
sudo systemctl reload nginx

echo "Deployment complete!"
echo "Monitor logs: tail -f /var/www/deschide_news_app/apps/backend/var/log/prod.log"
```

#### 11.4 Post-Deployment Monitoring

**A. Immediate checks (first 30 minutes):**

```bash
# 1. Application logs
tail -f var/log/prod.log | grep -E 'ERROR|CRITICAL|WARNING'

# 2. System logs
sudo journalctl -u php8.4-fpm -f

# 3. Nginx logs
sudo tail -f /var/log/nginx/deschide-access.log
sudo tail -f /var/log/nginx/deschide-error.log

# 4. Performance metrics
# Monitor via your monitoring stack (Prometheus, Grafana, etc.)
```

**B. KPIs to monitor:**

| Metric | Baseline | Threshold | Alert If |
|--------|----------|-----------|----------|
| API Response Time (p95) | 250ms | 375ms | > 400ms |
| Error Rate | 0.1% | 0.5% | > 1% |
| Memory Usage | 45MB avg | 65MB | > 80MB |
| Cache Hit Rate | 85% | 75% | < 70% |
| Database Connections | 20 avg | 40 | > 50 |
| 5xx Errors | 0 | 10/hour | > 20/hour |

**C. Extended monitoring (48 hours):**

```bash
# Create monitoring script
cat > scripts/monitor-symfony8.sh << 'EOF'
#!/bin/bash

while true; do
  timestamp=$(date '+%Y-%m-%d %H:%M:%S')
  
  # Check error rate
  errors=$(grep -c ERROR var/log/prod.log)
  
  # Check response time
  response=$(curl -o /dev/null -s -w '%{time_total}' https://deschide.md/api/articles)
  
  # Log metrics
  echo "[$timestamp] Errors: $errors, Response: ${response}s" \
    >> var/log/monitoring.log
  
  sleep 60
done
EOF

chmod +x scripts/monitor-symfony8.sh
nohup ./scripts/monitor-symfony8.sh &
```

---

## Rollback Plan

### Rollback Triggers

Execute rollback if any of the following occur within 24h of deployment:

1. **Critical:** Error rate > 5%
2. **Critical:** API unavailable > 5 minutes
3. **High:** Performance degradation > 50% from baseline
4. **High:** Data corruption detected
5. **High:** Authentication/authorization failures
6. **Medium:** Memory leaks detected
7. **Medium:** Database connection pool exhausted

### Rollback Procedure

**Time to complete:** 15 minutes

```bash
# 1. Immediate action: Route traffic back to BLUE
# (Assuming blue-green deployment)
# Update load balancer to route 100% to BLUE

# 2. OR: Revert to previous version
cd /var/www/deschide_news_app/apps/backend

# Restore from backup
tar -xzf /var/backups/deschide_TIMESTAMP.tar.gz -C /

# OR: Git rollback
git checkout tags/v7.4.x
composer install --no-dev --optimize-autoloader

# 3. Rollback migrations (if needed)
php bin/console doctrine:migrations:migrate prev --no-interaction

# 4. Clear caches
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

# 5. Restart services
sudo systemctl restart php8.2-fpm  # Back to PHP 8.2
sudo systemctl reload nginx

# 6. Verify rollback
curl -I https://deschide.md/api/articles
tail -f var/log/prod.log
```

### Post-Rollback Actions

1. **Immediate:**
   - Notify team
   - Update status page
   - Begin incident investigation

2. **Within 24h:**
   - Complete incident report
   - Identify root cause
   - Create remediation plan
   - Schedule retry deployment

3. **Before retry:**
   - Fix identified issues
   - Add tests for failure scenario
   - Re-validate in staging
   - Update deployment checklist

---

## Risk Assessment & Mitigation

### High-Risk Areas

#### Risk 1: Doctrine DBAL 4.x Type System Changes
**Probability:** Medium | **Impact:** High

**Mitigation:**
- Comprehensive testing of all custom Doctrine types
- Validate all database operations
- Test with production-like data volume
- Have database backup ready

**Rollback:** Git revert + composer install previous versions

#### Risk 2: PHP 8.4 Compatibility Issues
**Probability:** Low | **Impact:** High

**Mitigation:**
- Test all third-party packages individually
- Run full test suite with PHP 8.4 in staging
- Monitor for deprecation warnings
- Keep PHP 8.2 available for quick rollback

**Rollback:** Switch PHP version via update-alternatives

#### Risk 3: API Platform State Provider Migration
**Probability:** Low (already on 4.x) | **Impact:** Medium

**Mitigation:**
- Currently using API Platform 4.2.6 (already compatible)
- Verify all custom providers work
- Test pagination, filtering, sorting
- Validate JSON-LD output format

**Rollback:** No code changes needed if already on 4.x

#### Risk 4: Breaking Changes in Symfony 8.0
**Probability:** Low (if Phase 1 complete) | **Impact:** Medium

**Mitigation:**
- Phase 1 eliminates all deprecations (mandatory)
- Comprehensive testing before Phase 2
- Agent-based validation
- Staged rollout (blue-green)

**Rollback:** Git tag rollback + composer install

#### Risk 5: Performance Degradation
**Probability:** Low | **Impact:** High

**Mitigation:**
- Extensive load testing before production
- Compare benchmarks with baseline
- Monitor key metrics closely
- Optimize if needed

**Rollback:** Full rollback if degradation > 50%

### Medium-Risk Areas

#### Risk 6: Cache Invalidation Issues
**Probability:** Medium | **Impact:** Medium

**Mitigation:**
- Clear all caches during deployment
- Verify Redis connection
- Test cache warming
- Monitor cache hit rates

**Recovery:** Clear and rebuild caches

#### Risk 7: Multilingual Edge Cases
**Probability:** Medium | **Impact:** Low

**Mitigation:**
- Extensive multilingual testing (ro, en, ru)
- Test ICU MessageFormat patterns
- Verify locale-specific routes
- Test translation fallbacks

**Recovery:** Fix and deploy patch

### Low-Risk Areas

- Elasticsearch integration (already on 9.x)
- JWT authentication (already on 3.x)
- Redis integration (no breaking changes)
- File uploads (no breaking changes)

---

## Success Criteria

### Technical Metrics

✅ **Deployment is successful if ALL criteria are met:**

1. **Functionality:**
   - [ ] All API endpoints responding
   - [ ] Authentication working
   - [ ] File uploads working
   - [ ] Search working
   - [ ] All locales (ro, en, ru) working

2. **Performance:**
   - [ ] API response time p95 < 300ms (baseline: 250ms)
   - [ ] Error rate < 0.5% (baseline: 0.1%)
   - [ ] Memory usage < 60MB avg (baseline: 45MB)
   - [ ] Cache hit rate > 80% (baseline: 85%)

3. **Stability:**
   - [ ] Zero critical errors in 24h
   - [ ] Zero data corruption incidents
   - [ ] Zero unplanned downtime

4. **Testing:**
   - [ ] 100% of test suite passing
   - [ ] PHPStan Level 8 with zero errors
   - [ ] All 25 agents validated their domains
   - [ ] Load test passed (k6)

5. **Code Quality:**
   - [ ] Zero deprecation warnings
   - [ ] Code coverage >= baseline
   - [ ] No security vulnerabilities

### Business Metrics

Monitor for 7 days post-deployment:

- [ ] No increase in user-reported issues
- [ ] No impact on content publication workflow
- [ ] No impact on SEO performance
- [ ] No impact on mobile experience

---

## Timeline Overview

```
Week 1-2:   Preparation & Deprecation Audit
Week 3:     Symfony 7.4 Upgrade
Week 4:     Deprecation Fixes + Testing
--------------------------------------------
            Phase 1 Completion Review
            Go/No-Go Decision Point
--------------------------------------------
Week 5-6:   Dependency Updates (PHP 8.4, Doctrine)
Week 7-8:   Symfony 8.0 Core Upgrade
Week 9-10:  Comprehensive Testing & Staging
Week 11-12: Production Deployment + Monitoring
```

**Total Duration:** 10-12 weeks  
**Can be accelerated if:** Low deprecation count, high test coverage, simple codebase  
**May need extension if:** High deprecation count, complex custom code, external dependencies issues

---

## Team Assignments

### Core Team

**Phase 1 Lead:** Senior Backend Developer  
**Responsibilities:**
- Deprecation audit
- Symfony 7.4 upgrade
- Deprecation fixes
- Phase 1 testing

**Phase 2 Lead:** Senior Backend Developer + DevOps Engineer  
**Responsibilities:**
- Dependency updates
- PHP 8.4 upgrade
- Symfony 8.0 upgrade
- Production deployment

**QA Lead:** QA Engineer + Agent Orchestrator  
**Responsibilities:**
- Test plan execution
- Agent-based testing coordination
- Performance validation
- Regression testing

**DevOps Lead:** DevOps Engineer  
**Responsibilities:**
- Infrastructure preparation
- PHP 8.4 installation
- Deployment automation
- Monitoring setup
- Rollback procedures

### Agent Coordination

| Agent | Primary Responsibility | Owner |
|-------|----------------------|-------|
| backend-api-tester | API endpoint validation | QA Lead |
| multilanguage-tester | Multilingual functionality | QA Lead |
| performance-tester | Load testing, benchmarks | DevOps |
| security-auditor | Security validation | Phase 2 Lead |
| database-engineer | DB migrations, performance | Phase 2 Lead |
| fullstack-integration-tester | End-to-end workflows | QA Lead |

---

## Communication Plan

### Internal Updates

**Daily Standups (during active phases):**
- Progress updates
- Blockers identification
- Risk assessment
- Next steps

**Weekly Status Reports:**
- Milestone progress
- Metrics tracking
- Risk register updates
- Timeline adjustments

**Phase Completion Reviews:**
- Comprehensive metrics review
- Go/No-Go decision
- Lessons learned
- Adjust plan if needed

### Stakeholder Communication

**T-4 weeks:** Initial notification
- Timeline
- Expected impact
- Maintenance windows

**T-1 week:** Deployment reminder
- Exact date/time
- Expected downtime (if any)
- Contact information

**T-Day:** Real-time updates
- Deployment start
- Progress checkpoints
- Completion notification

**T+1 week:** Post-deployment report
- Success metrics
- Issues encountered
- Next steps

---

## Post-Upgrade Optimization

### PHP 8.4 Optimizations

```ini
; config/php.ini additions for PHP 8.4
zend.assertions = 0
opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0  ; in production
opcache.jit = tracing
opcache.jit_buffer_size = 128M
```

### Symfony 8.0 Features to Adopt

1. **Lazy Objects:**
   ```php
   use Symfony\Component\DependencyInjection\Attribute\Lazy;
   
   #[Lazy]
   class ExpensiveService {
       // Only instantiated when actually used
   }
   ```

2. **Improved Attributes:**
   ```php
   use Symfony\Component\Console\Attribute\Argument;
   use Symfony\Component\Console\Attribute\Option;
   
   #[AsCommand(name: 'app:import-news')]
   class ImportNewsCommand {
       public function __invoke(
           #[Argument] string $source,
           #[Option] int $limit = 100
       ): int {
           // Cleaner command definition
       }
   }
   ```

3. **Security Voter Explanations:**
   ```php
   protected function voteOnAttribute(
       string $attribute,
       mixed $subject,
       TokenInterface $token,
       ?Vote $vote = null
   ): bool {
       if (!$token->getUser() instanceof User) {
           $vote?->reasons[] = 'User not authenticated';
           return false;
       }
       // Better debugging of authorization
   }
   ```

---

## Appendix A: Quick Reference Commands

```bash
# Check current Symfony version
php bin/console --version

# Check deprecations
SYMFONY_DEPRECATIONS_HELPER=weak ./vendor/bin/phpunit

# Update to Symfony 7.4
composer require "symfony/framework-bundle:7.4.*"

# Update to Symfony 8.0
composer require "symfony/framework-bundle:8.0.*"

# Update Flex recipes
composer recipes:update

# Clear caches
php bin/console cache:clear --env=prod
rm -rf var/cache/*

# Database migrations
php bin/console doctrine:migrations:migrate

# Run tests
./vendor/bin/phpunit

# Static analysis
./vendor/bin/phpstan analyse

# Code style
./vendor/bin/php-cs-fixer fix

# Load test
cd k6 && k6 run load-test.js

# Monitor logs
tail -f var/log/prod.log
```

---

## Appendix B: Common Issues & Solutions

### Issue 1: Composer Memory Exhausted

**Symptom:** `Fatal error: Allowed memory size exhausted`

**Solution:**
```bash
php -d memory_limit=-1 $(which composer) update
```

### Issue 2: OpCache Not Clearing

**Symptom:** Old code still executing after deploy

**Solution:**
```bash
# Restart PHP-FPM
sudo systemctl restart php8.4-fpm

# Clear OpCache via script
php -r "opcache_reset();"
```

### Issue 3: Database Migration Fails

**Symptom:** Migration command hangs or errors

**Solution:**
```bash
# Check migration status
php bin/console doctrine:migrations:status

# Skip problematic migration
php bin/console doctrine:migrations:version YYYYMMDDHHMMSS --add

# Manual SQL execution if needed
psql -U postgres -d deschide_news < migration.sql
```

### Issue 4: Container Compilation Fails

**Symptom:** `Cannot autowire service X`

**Solution:**
```bash
# Verify service definition
php bin/console debug:container X

# Clear cache aggressively
rm -rf var/cache/* var/log/*
php bin/console cache:clear --no-warmup
php bin/console cache:warmup
```

### Issue 5: Tests Failing After Upgrade

**Symptom:** Previously passing tests now fail

**Solution:**
```bash
# Update test dependencies
composer update --dev

# Clear test cache
php bin/console cache:clear --env=test

# Run specific failing test with verbose output
./vendor/bin/phpunit --testdox tests/Path/To/FailingTest.php
```

---

## Appendix C: Useful Resources

### Official Documentation
- [Symfony 8.0 Release Notes](https://symfony.com/8)
- [Symfony Upgrade Guide](https://symfony.com/doc/current/setup/upgrade_major.html)
- [API Platform 4.x Documentation](https://api-platform.com/docs/)
- [Doctrine ORM 3.x Guide](https://www.doctrine-project.org/projects/orm.html)
- [PHP 8.4 Release Notes](https://www.php.net/releases/8.4/en.php)

### Community Resources
- [JoliCode Symfony 8 Upgrade Experience](https://jolicode.com/blog/our-experience-upgrading-a-project-to-symfony-8)
- [Symfony Slack](https://symfony.com/slack)
- [Symfony Reddit](https://reddit.com/r/symfony)

### Tools
- [Rector - Automated Refactoring](https://github.com/rectorphp/rector)
- [PHPStan - Static Analysis](https://phpstan.org/)
- [PHP CS Fixer - Code Style](https://github.com/FriendsOfPHP/PHP-CS-Fixer)

---

## Document Change Log

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2025-12-01 | Team Lead | Initial draft |
| 2.0 | 2025-12-10 | AI Architect | Complete rewrite with research-based recommendations |

---

## Approvals

| Role | Name | Signature | Date |
|------|------|-----------|------|
| Team Lead | Radu | _________ | _____ |
| Senior Developer | | _________ | _____ |
| QA Lead | | _________ | _____ |
| DevOps Lead | | _________ | _____ |

---

**END OF DOCUMENT**
