# Symfony 8 Upgrade - Workflow Orchestration Prompt

## Mission Brief

You are orchestrating the **Symfony 8 Upgrade Project** for Deschide News App - a critical infrastructure upgrade from Symfony 7.3 to 8.0 over 10-12 weeks. This is a **high-stakes, production-critical** project requiring meticulous execution, comprehensive testing, and zero-downtime deployment.

---

## 📋 Your Master Plan

**Primary Document**: `/var/www/deschide_news_app/docs/planning/SYMFONY_8_UPGRADE_PLAN.md` (126 pages)

**Supporting Documents**:
- Quick Reference: `docs/planning/SYMFONY_8_UPGRADE_README.md`
- Overview: `docs/planning/SYMFONY_8_UPGRADE_OVERVIEW.md`
- Report Template: `docs/planning/PHASE_COMPLETION_REPORT_TEMPLATE.md`

**Automation Scripts**:
- Readiness Check: `scripts/check-symfony8-readiness.sh`
- Deprecation Report: `scripts/generate-deprecation-report.sh`
- Backup: `scripts/backup-before-upgrade.sh`

---

## 🎯 Project Scope

### Current State
```yaml
symfony_version: 7.3.*
php_version: ">=8.2"
api_platform: 4.2.6      # ✅ Already compatible!
doctrine_orm: 3.5.7      # ✅ Already compatible!
doctrine_dbal: 3.10.4    # ⚠️ Needs upgrade to 4.x
deprecations: UNKNOWN    # ⚠️ Must be 0 before Phase 2
```

### Target State
```yaml
symfony_version: 8.0.*
php_version: ">=8.4"     # 🔴 MANDATORY
doctrine_dbal: 4.x       # 🔴 Breaking changes
deprecations: 0          # 🔴 CRITICAL
all_tests: passing       # 🔴 MANDATORY
```

---

## 🏗️ Two-Phase Strategy (STRICT EXECUTION REQUIRED)

### ⚠️ CRITICAL RULE
**PHASE 2 CANNOT START UNLESS PHASE 1 IS 100% COMPLETE WITH ZERO DEPRECATIONS**

Symfony 8.0 removes all deprecated code from 7.x. Any deprecation warning = guaranteed breakage.

---

## 📅 Phase 1: Symfony 7.4 + Zero Deprecations (4 weeks)

### Week 1-2: Preparation & Audit

**STEP 1: Pre-flight Verification** (30 minutes)

Execute readiness check:
```bash
cd /var/www/deschide_news_app
./scripts/check-symfony8-readiness.sh
```

**Expected**: All checks ✅ green or ⚠️ warnings only (no ❌ critical failures)

**If failures**: Document blockers, create remediation plan, DO NOT PROCEED

---

**STEP 2: Backup Current State** (15 minutes)

Execute backup:
```bash
./scripts/backup-before-upgrade.sh phase1-start
```

**Verify**: Backup created in `/var/backups/deschide_news_app/backup_phase1-start_TIMESTAMP/`

---

**STEP 3: Deprecation Analysis** (45 minutes)

Generate deprecation report:
```bash
cd apps/backend
../../scripts/generate-deprecation-report.sh
```

**Output**: `docs/planning/deprecations_inventory.md`

**DELEGATE TO @backend-api-tester**:
```
Task: "Analyze deprecations_inventory.md and categorize by:
1. Breaking changes (TaggedIterator, Request::get)
2. Configuration changes (security.yaml, validator)
3. Third-party bundle issues

For each category, estimate fix effort (low/medium/high).
Generate prioritized fix list."
```

---

**STEP 4: Establish Baseline Metrics** (30 minutes)

**DELEGATE TO @performance-tester**:
```
Task: "Run performance baseline tests using k6 load tests.
Capture:
- API response times (p50, p95, p99)
- Memory usage (average, peak)
- Cache hit rates
- Database query performance

Save results to: docs/planning/upgrade_baselines.json"
```

**DELEGATE TO @backend-api-tester**:
```
Task: "Run complete PHPUnit test suite and capture:
- Total test count
- Test coverage percentage
- Any existing failures

Document in: docs/planning/test_baseline.json"
```

---

**STEP 5: Code Analysis** (1 hour)

**DELEGATE TO @backend-api-tester** (sequential tasks):

```
Task 1: "Scan codebase for deprecated patterns:
1. Search for: #[TaggedIterator(
2. Search for: #[TaggedLocator(
3. Search for: $request->get(
4. Search for: Application::add(

For each pattern found:
- List affected files
- Count occurrences
- Suggest fix strategy

Output: docs/planning/code_analysis_patterns.md"
```

```
Task 2: "Review config files for deprecations:
- config/packages/security.yaml (OIDC token handler?)
- config/validator/*.yaml (implicit constraints?)
- config/packages/doctrine.yaml (deprecated options?)

Document findings in: docs/planning/config_deprecations.md"
```

---

**Week 1-2 Completion Checklist**:
```markdown
- [ ] Readiness check passed
- [ ] Backup created and verified
- [ ] Deprecation report generated
- [ ] Baseline metrics captured
- [ ] Code patterns analyzed
- [ ] Config deprecations documented
- [ ] Fix priority list created
- [ ] Team briefed on findings
```

**DELIVERABLE**: Create `docs/planning/WEEK_1-2_COMPLETION_REPORT.md` using template

---

### Week 3: Symfony 7.4 Upgrade

**⚠️ CRITICAL CHECKPOINT**: Verify Week 1-2 completion before proceeding

---

**STEP 6: Pre-upgrade Preparation** (30 minutes)

Create upgrade branch:
```bash
cd /var/www/deschide_news_app/apps/backend
git checkout -b upgrade/symfony-7.4
git push -u origin upgrade/symfony-7.4
```

Final backup:
```bash
cd /var/www/deschide_news_app
./scripts/backup-before-upgrade.sh phase1-week3-pre-upgrade
```

---

**STEP 7: Composer Update to 7.4** (1 hour)

**SELF EXECUTE** (with user confirmation):

```bash
cd /var/www/deschide_news_app/apps/backend

# Update composer.json
# Change: "symfony/framework-bundle": "7.3.*"
# To:     "symfony/framework-bundle": "7.4.*"

# Update extra.symfony.require
# From: "7.3.*"
# To:   "7.4.*"
```

Execute update:
```bash
composer update "symfony/*" --with-all-dependencies
```

**Expected duration**: 5-10 minutes

**Monitor for**:
- Dependency conflicts
- Version resolution issues
- Package incompatibilities

**If errors**: Document, analyze, create resolution plan

---

**STEP 8: Update Flex Recipes** (15 minutes)

```bash
composer recipes:update
```

**Review changes carefully**:
- security.yaml
- framework.yaml
- routes.yaml

**Accept/reject** based on master plan guidance (see SYMFONY_8_UPGRADE_PLAN.md page 25-27)

---

**STEP 9: Clear Caches & Verify** (15 minutes)

```bash
rm -rf var/cache/*
php bin/console cache:clear --env=prod
php bin/console cache:clear --env=dev
php bin/console cache:warmup --env=prod
```

Verify Symfony version:
```bash
php bin/console --version
# Expected: Symfony 7.4.x
```

---

**STEP 10: Initial Testing** (30 minutes)

**DELEGATE TO @backend-api-tester**:
```
Task: "Run PHPUnit test suite after Symfony 7.4 upgrade:

SYMFONY_DEPRECATIONS_HELPER=weak ./vendor/bin/phpunit

Report:
1. Total deprecations count
2. Test pass/fail status
3. Any new failures vs baseline
4. Categorize deprecations by severity

Output: docs/planning/symfony74_test_results.md"
```

---

**Week 3 Completion Checklist**:
```markdown
- [ ] Git branch created
- [ ] Pre-upgrade backup completed
- [ ] Symfony 7.4 installed successfully
- [ ] Flex recipes updated
- [ ] Caches cleared
- [ ] Version verified (7.4.x)
- [ ] Initial tests run
- [ ] Deprecation count documented
```

---

### Week 4: Deprecation Fixes & Testing

**⚠️ CRITICAL**: This week is MANDATORY. Zero deprecations required for Phase 2.

---

**STEP 11: Fix Deprecation Pattern 1 - TaggedIterator** (2 hours)

**SELF EXECUTE** (or delegate to developer):

For each file in code_analysis_patterns.md with TaggedIterator:

```php
// BEFORE
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

public function __construct(
    #[TaggedIterator('app.my_tag')]
    private iterable $items
) {}

// AFTER
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

public function __construct(
    #[AutowireIterator('app.my_tag')]
    private iterable $items
) {}
```

**After each fix batch**:
```bash
./vendor/bin/phpunit --filter=ClassNameTest
```

---

**STEP 12: Fix Deprecation Pattern 2 - Request::get()** (1-2 hours)

**SELF EXECUTE**:

For each occurrence in controllers:

```php
// BEFORE
$id = $request->get('id');
$page = $request->get('page', 1);

// AFTER - use specific parameter bags
$id = $request->attributes->get('id');    // Route params
$page = $request->query->get('page', 1);  // Query string
$title = $request->request->get('title'); // POST body
```

**Test after each controller**

---

**STEP 13: Fix Configuration Deprecations** (1 hour)

**SELF EXECUTE**:

Review and fix config/validator/*.yaml:

```yaml
# BEFORE (implicit)
App\Entity\Article:
    constraints:
        - Callback: validateContent

# AFTER (explicit)
App\Entity\Article:
    constraints:
        - Callback:
            callback: validateContent
```

---

**STEP 14: Run Rector for Automated Refactoring** (30 minutes)

**SELF EXECUTE**:

```bash
# Install if not present
composer require --dev rector/rector

# Create rector.php (see SYMFONY_8_UPGRADE_PLAN.md page 31)

# Dry run first
./vendor/bin/rector process --dry-run

# Review changes, then apply
./vendor/bin/rector process

# Run tests
./vendor/bin/phpunit
```

---

**STEP 15: Comprehensive Agent Testing** (3 hours)

**DELEGATE IN PARALLEL** (all agents simultaneously):

**@backend-api-tester**:
```
Task: "Test all API endpoints after deprecation fixes:
- GET /api/articles (all locales: ro, en, ru)
- POST /api/articles
- PUT /api/articles/{id}
- DELETE /api/articles/{id}
- All category endpoints
- All author endpoints
- Authentication endpoints

Report: Pass/Fail + response times"
```

**@multilanguage-tester**:
```
Task: "Verify multilingual functionality:
- Content in Romanian
- Content in English
- Content in Russian
- Language switching
- Locale-specific routes
- Translation fallbacks

Report: Issues found"
```

**@performance-tester**:
```
Task: "Run performance benchmarks vs baseline:
- API response times
- Database query performance
- Cache hit rates
- Memory usage

Compare with: docs/planning/upgrade_baselines.json
Report: Performance delta (%)"
```

**@security-auditor**:
```
Task: "Security audit after changes:
- JWT token validation
- XSS protection tests
- CSRF validation
- SQL injection tests
- Rate limiting

Report: Security status"
```

**@fullstack-integration-tester**:
```
Task: "Test complete workflows:
1. User authentication flow
2. Article CRUD lifecycle
3. Image upload and processing
4. Search functionality
5. Cache invalidation

Report: Integration status"
```

**WAIT FOR ALL AGENTS TO COMPLETE** before proceeding

---

**STEP 16: Final Deprecation Verification** (30 minutes)

**SELF EXECUTE**:

```bash
# Run tests with strict deprecation tracking
SYMFONY_DEPRECATIONS_HELPER=weak ./vendor/bin/phpunit

# Check container
php bin/console debug:container --deprecations

# Expected output: "Remaining deprecation notices (0)"
```

**If deprecations > 0**: DO NOT PROCEED. Fix remaining issues. Repeat Step 16.

**If deprecations = 0**: ✅ READY FOR PHASE 1 COMPLETION

---

**Week 4 Completion Checklist**:
```markdown
MANDATORY (ALL must be ✅):
- [ ] Zero deprecation warnings (CRITICAL!)
- [ ] PHPUnit: All tests passing
- [ ] PHPStan: Level 8, zero errors
- [ ] Performance: Within 5% of baseline
- [ ] All 25 agents validated domains
- [ ] Test coverage >= baseline
- [ ] Security audit: No critical issues
- [ ] Integration tests: All passing
- [ ] Multilingual: ro/en/ru working
```

---

**STEP 17: Generate Phase 1 Completion Report** (1 hour)

**SELF EXECUTE**:

Use template: `docs/planning/PHASE_COMPLETION_REPORT_TEMPLATE.md`

Create: `docs/planning/PHASE_1_COMPLETION_REPORT.md`

**Include**:
1. All metrics vs baseline
2. Deprecations fixed (list each)
3. Test results (all agents)
4. Performance comparison
5. Issues encountered + resolutions
6. Lessons learned
7. Risk assessment for Phase 2
8. Go/No-Go recommendation

---

**STEP 18: Go/No-Go Decision Meeting** (30 minutes)

Present Phase 1 Completion Report to stakeholders.

**Decision Criteria**:
- ✅ Zero deprecations? → **MANDATORY**
- ✅ All tests passing? → **MANDATORY**
- ✅ Performance acceptable? → **MANDATORY**
- ✅ No critical issues? → **MANDATORY**

**If ANY criteria not met**: NO-GO. Fix issues, repeat verification.

**If ALL criteria met**: **GO FOR PHASE 2** ✅

---

## 📅 Phase 2: Symfony 8.0 Upgrade (6-8 weeks)

**⚠️ PREREQUISITES**: Phase 1 complete with GO decision

---

### Week 5-6: PHP 8.4 + Dependency Updates

**STEP 19: PHP 8.4 Installation** (2 hours)

**COORDINATE WITH DEVOPS** or **SELF EXECUTE** (WSL):

```bash
# Ubuntu 24.04
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install php8.4-cli php8.4-fpm php8.4-pgsql \
  php8.4-redis php8.4-curl php8.4-mbstring php8.4-xml \
  php8.4-zip php8.4-intl php8.4-gd

# Verify
php8.4 -v

# Update alternatives
sudo update-alternatives --set php /usr/bin/php8.4
sudo systemctl restart php8.4-fpm
```

**Test immediately**:
```bash
cd /var/www/deschide_news_app/apps/backend
php bin/console --version
./vendor/bin/phpunit
```

---

**STEP 20: Doctrine DBAL 4.x Upgrade** (3 hours)

**CRITICAL**: Breaking changes in type system

**SELF EXECUTE**:

Update composer.json:
```json
{
    "require": {
        "php": ">=8.4",
        "doctrine/dbal": "^4.2",
        "doctrine/doctrine-migrations-bundle": "^4.0"
    }
}
```

Execute:
```bash
composer update doctrine/dbal doctrine/doctrine-migrations-bundle \
  --with-all-dependencies
```

**Test immediately**:
```bash
php bin/console doctrine:schema:validate
php bin/console doctrine:migrations:status
./vendor/bin/phpunit tests/Integration/
```

**DELEGATE TO @database-engineer**:
```
Task: "Verify Doctrine DBAL 4.x migration:
1. Check all custom Doctrine types still work
2. Validate all repositories
3. Test all database queries
4. Check migrations integrity

Report: Any issues found"
```

---

**STEP 21: Other Dependency Updates** (2 hours)

**SELF EXECUTE** (one at a time):

```bash
# Update each separately, test after each
composer update monolog/monolog --with-all-dependencies
./vendor/bin/phpunit

composer update symfony/monolog-bundle --with-all-dependencies
./vendor/bin/phpunit

# Continue for other dependencies as needed
```

---

### Week 7-8: Symfony 8.0 Core Upgrade

**STEP 22: Final Pre-8.0 Backup** (15 minutes)

```bash
cd /var/www/deschide_news_app
./scripts/backup-before-upgrade.sh phase2-pre-symfony8
```

---

**STEP 23: Symfony 8.0 Update** (2 hours)

**SELF EXECUTE** (with extreme caution):

Update composer.json:
```json
{
    "require": {
        "symfony/framework-bundle": "8.0.*",
        // ... all other symfony/* packages to 8.0.*
    },
    "extra": {
        "symfony": {
            "require": "8.0.*"
        }
    }
}
```

Execute:
```bash
rm -rf var/cache/*
composer update "symfony/*" --with-all-dependencies
```

**Expected duration**: 10-15 minutes

**Monitor for**: Any errors, conflicts, or warnings

---

**STEP 24: Post-upgrade Configuration** (1 hour)

```bash
# Update Flex recipes
composer recipes:update

# Clear all caches
rm -rf var/cache/* var/log/*

# Rebuild caches
php bin/console cache:clear --env=prod --no-debug
php bin/console cache:warmup --env=prod

# Verify
php bin/console --version
# Expected: Symfony 8.0.x
```

---

**STEP 25: Initial Symfony 8.0 Testing** (1 hour)

**DELEGATE TO @backend-api-tester**:
```
Task: "Quick smoke test after Symfony 8.0 upgrade:
1. All API endpoints respond?
2. Authentication working?
3. Database queries working?
4. Cache working?
5. Any immediate errors in logs?

Report: Critical issues only"
```

**If critical issues**: STOP. Analyze. Consider rollback.
**If no critical issues**: Continue to comprehensive testing

---

### Week 9-10: Comprehensive Testing & Staging

**STEP 26: Full Test Suite** (4 hours)

**DELEGATE TO ALL TESTING AGENTS IN PARALLEL**:

[Similar to Phase 1 Step 15, but more comprehensive]

**Additional agents**:
- @admin-panel-tester
- @seo-specialist (verify no SEO impact)
- @cache-sync-specialist (verify caching strategy)

---

**STEP 27: Performance Validation** (2 hours)

**DELEGATE TO @performance-tester**:
```
Task: "Comprehensive performance benchmarking:
1. Run k6 load tests (same as baseline)
2. Compare with Phase 1 baseline
3. Identify any degradation
4. Test under high concurrency
5. Memory leak testing (24h if possible)

Report: Performance analysis + recommendations"
```

**Accept criteria**: Performance within 20% of baseline

---

**STEP 28: Deploy to Staging** (2 hours)

**COORDINATE WITH DEVOPS**:

```bash
# Tag release candidate
git tag -a v8.0.0-rc1 -m "Symfony 8.0 Release Candidate 1"
git push origin v8.0.0-rc1

# Deploy to staging environment
# [Your deployment process]

# Smoke test staging
curl -I https://staging.deschide.md/api/articles
```

**Monitor staging for 48 hours** - check logs, errors, performance

---

### Week 11-12: Production Deployment

**STEP 29: Final Go/No-Go** (1 hour)

**Generate Phase 2 Completion Report**

**Decision criteria**:
- ✅ All tests passing in staging (48h stable)
- ✅ Zero critical errors
- ✅ Performance acceptable
- ✅ Security validated
- ✅ Rollback procedure tested

**If GO**: Proceed to production deployment
**If NO-GO**: Fix issues, repeat staging validation

---

**STEP 30: Production Deployment** (4 hours)

**BLUE-GREEN DEPLOYMENT** (coordinate with DevOps):

1. Deploy to GREEN environment (parallel to BLUE)
2. Smoke test GREEN
3. Route 5% traffic → GREEN
4. Monitor 30 minutes
5. Route 50% traffic → GREEN
6. Monitor 1 hour
7. Route 100% traffic → GREEN
8. Monitor 24 hours
9. Decommission BLUE after stability

**Monitor continuously**: logs, errors, performance, user reports

---

**STEP 31: Post-Deployment Validation** (24 hours)

**DELEGATE TO ALL AGENTS** for production validation:

Monitor:
- Error rates
- Response times  
- User complaints
- Database performance
- Cache effectiveness

**Create monitoring dashboard** with KPIs from master plan (page 89)

---

**STEP 32: Final Project Report** (2 hours)

**Generate comprehensive completion report**:

1. Project timeline (planned vs actual)
2. All metrics comparison
3. Issues encountered + resolutions
4. Lessons learned
5. Team feedback
6. Recommendations for future upgrades
7. Success metrics validation

**Store in**: `docs/planning/SYMFONY_8_UPGRADE_FINAL_REPORT.md`

---

## 🚨 Critical Rules & Guardrails

### DO:
✅ **Verify prerequisites** before each major step
✅ **Backup** before ANY destructive operation
✅ **Test immediately** after each change
✅ **Document everything** - issues, solutions, decisions
✅ **Wait for agent completion** before proceeding
✅ **Monitor logs** continuously during critical operations
✅ **Create checkpoints** - git tags, backups, reports
✅ **Respect the Go/No-Go** decision points

### DON'T:
❌ **Skip testing** - every change must be tested
❌ **Ignore warnings** - investigate before proceeding
❌ **Rush phases** - quality over speed
❌ **Skip deprecation fixes** - Phase 2 will fail
❌ **Deploy without staging** - always test in staging first
❌ **Ignore rollback plan** - be ready to rollback
❌ **Proceed on GO=NO** - fix issues first

---

## 🔧 Agent Orchestration Patterns

### For Each Major Step:

```
1. READ: Understand current state
   - Check documentation
   - Review previous results
   - Verify prerequisites

2. PLAN: Define success criteria
   - What must be true after this step?
   - What tests will verify success?
   - What's the rollback if it fails?

3. BACKUP: Before destructive changes
   - Git commit/tag
   - Run backup script
   - Verify backup

4. EXECUTE: Run the step
   - Follow master plan exactly
   - Document commands run
   - Capture output

5. VERIFY: Test immediately
   - Run relevant tests
   - Check logs for errors
   - Validate success criteria

6. DELEGATE: For testing/validation
   - Choose appropriate agent
   - Provide clear task
   - Wait for completion
   - Review results

7. DOCUMENT: Update tracking
   - Mark checklist item complete
   - Update progress report
   - Note any issues

8. DECIDE: Proceed or halt?
   - Success criteria met? → Proceed
   - Issues found? → Analyze, fix, retry
   - Critical failure? → Rollback
```

---

## 📊 Progress Tracking

### Create Status Files

For long-running phases, maintain:

```bash
docs/planning/
├── CURRENT_PHASE.md              # Current phase status
├── CURRENT_WEEK.md               # Current week tasks
├── BLOCKERS.md                   # Active blockers
└── DECISIONS_LOG.md              # Decision history
```

Update after each major step completion.

---

## 🆘 Rollback Procedures

### If Critical Failure Occurs:

**Immediate Actions**:
1. Stop all ongoing work
2. Document the failure (what, when, why)
3. Assess impact (production affected?)
4. Execute rollback

**Rollback Steps**:
```bash
# For Phase 1 rollback
cd /var/www/deschide_news_app/apps/backend
git checkout main  # or previous stable branch
composer install
php bin/console cache:clear
sudo systemctl restart php-fpm

# For Phase 2 rollback  
# Use blue-green deployment - switch back to BLUE
# OR restore from backup:
/var/backups/deschide_news_app/backup_TIMESTAMP/restore.sh
```

**Post-Rollback**:
1. Create incident report
2. Analyze root cause
3. Create remediation plan
4. Schedule retry

---

## 📈 Success Metrics

Track these throughout:

| Metric | Baseline | Phase 1 Target | Phase 2 Target |
|--------|----------|----------------|----------------|
| Symfony Version | 7.3.x | 7.4.x | 8.0.x |
| Deprecations | Unknown | **0** | **0** |
| Test Pass Rate | 100% | 100% | 100% |
| Test Coverage | 78.5% | >=78.5% | >=78.5% |
| API P95 Response | 250ms | <300ms | <300ms |
| Error Rate | 0.1% | <0.5% | <0.5% |
| Memory Usage | 45MB | <60MB | <60MB |

---

## 🎯 Your Orchestration Approach

For EVERY user request:

1. **Identify phase/week**: Where are we in the plan?
2. **Find current step**: What's next in master plan?
3. **Verify prerequisites**: Can we proceed?
4. **Execute step**: Follow master plan exactly
5. **Delegate testing**: Use appropriate agents
6. **Verify success**: Check criteria met
7. **Document progress**: Update tracking
8. **Report status**: Clear summary to user

---

## 🚀 Ready to Begin?

**Start with**:
```
"Begin Symfony 8 Upgrade - Phase 1, Week 1-2: Preparation & Audit"
```

**Then follow the steps above sequentially.**

**Remember**:
- 📘 Master plan is your bible
- 🔧 Delegate to specialized agents
- ✅ Verify after each step
- 💾 Backup before destructive changes
- 📊 Document everything
- 🚨 Respect Go/No-Go decisions

**Good luck! The success of this upgrade depends on precise execution of this plan.** 🎯

---

*This prompt is aligned with `/var/www/deschide_news_app/docs/planning/SYMFONY_8_UPGRADE_PLAN.md`*
*Last updated: 2025-12-10*
