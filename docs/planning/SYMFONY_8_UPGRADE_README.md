# Symfony 8 Upgrade - Quick Start Guide

> **TL;DR:** Comprehensive upgrade plan from Symfony 7.3 to 8.0 with automated checks, agent-based testing, and rollback procedures.

---

## 📋 What's Included

This upgrade package contains:

1. **📘 Master Plan:** `SYMFONY_8_UPGRADE_PLAN.md` - Complete 126-page upgrade strategy
2. **🔍 Readiness Check:** `scripts/check-symfony8-readiness.sh` - Automated pre-upgrade verification
3. **📊 Report Template:** `PHASE_COMPLETION_REPORT_TEMPLATE.md` - Structured phase completion reporting
4. **✅ Checklists:** Interactive checklists for each phase
5. **🔧 Scripts:** Automation scripts for common tasks

---

## 🚀 Quick Start

### Step 1: Run Readiness Check

Before starting any work, verify your system is ready:

```bash
cd /var/www/deschide_news_app
chmod +x scripts/check-symfony8-readiness.sh
./scripts/check-symfony8-readiness.sh
```

**Expected Output:**
```
====================================
Symfony 8 Upgrade Readiness Check
====================================

=== PHP Version Checks ===
✓ PASS: PHP installed
✓ PASS: PHP version >= 8.2

[... more checks ...]

====================================
Summary
====================================
✓ Passed:   XX
✗ Failed:   0
⚠ Warnings: X
```

**Action Required:**
- ✅ **All checks passed?** → Proceed to Step 2
- ❌ **Critical failures?** → Fix issues before continuing
- ⚠️ **Warnings only?** → Review and decide if acceptable

### Step 2: Read the Master Plan

Open and review the complete upgrade plan:

```bash
# Open in your favorite editor
code docs/planning/SYMFONY_8_UPGRADE_PLAN.md

# Or view in terminal
less docs/planning/SYMFONY_8_UPGRADE_PLAN.md
```

**Key Sections to Review:**
1. **Executive Summary** - Understand the why and what
2. **Phase 1 Strategy** - Symfony 7.4 + deprecation fixes
3. **Phase 2 Strategy** - Symfony 8.0 upgrade
4. **Risk Assessment** - Know what can go wrong
5. **Rollback Plan** - Have an escape route

### Step 3: Create Upgrade Branch

Start with a clean working directory:

```bash
cd apps/backend

# Ensure clean state
git status

# Create upgrade branch
git checkout -b upgrade/symfony-7.4-preparation
git push -u origin upgrade/symfony-7.4-preparation
```

### Step 4: Begin Phase 1

Follow the detailed instructions in `SYMFONY_8_UPGRADE_PLAN.md` starting with **Week 1-2: Preparation & Audit**.

---

## 📁 File Structure

```
deschide_news_app/
├── docs/
│   ├── planning/
│   │   ├── SYMFONY_8_UPGRADE_PLAN.md         ← Master plan (read this first!)
│   │   ├── PHASE_COMPLETION_REPORT_TEMPLATE.md ← Use for each phase
│   │   ├── deprecations_inventory.md          ← Create during Phase 1
│   │   └── upgrade_baselines.json             ← Create during baseline
│   └── testing/
│       └── symfony8_validation_checklist.md   ← Create during Phase 2
├── scripts/
│   ├── check-symfony8-readiness.sh           ← Run before starting
│   ├── deploy-symfony8.sh                    ← Use for deployment
│   └── monitor-symfony8.sh                   ← Use post-deployment
└── apps/
    └── backend/
        ├── composer.json                      ← Will be updated
        ├── rector.php                         ← Create during Phase 1
        └── phpstan.neon                       ← Already exists
```

---

## 🗓️ Timeline Overview

```
╔═══════════════════════════════════════════════════════════════╗
║                     PHASE 1: 4 Weeks                          ║
╠═══════════════════════════════════════════════════════════════╣
║ Week 1-2: Preparation & Audit                                 ║
║   • Run readiness check                                       ║
║   • Audit deprecations                                        ║
║   • Establish baselines                                       ║
║ Week 3: Symfony 7.4 Upgrade                                   ║
║   • Update composer.json                                      ║
║   • Run composer update                                       ║
║   • Update Flex recipes                                       ║
║ Week 4: Deprecation Fixes & Testing                           ║
║   • Fix all deprecations                                      ║
║   • Run agent-based tests                                     ║
║   • Complete Phase 1 report                                   ║
╠═══════════════════════════════════════════════════════════════╣
║              GO/NO-GO DECISION POINT                          ║
╠═══════════════════════════════════════════════════════════════╣
║                     PHASE 2: 6-8 Weeks                        ║
╠═══════════════════════════════════════════════════════════════╣
║ Week 5-6: Dependency Updates                                  ║
║   • PHP 8.4 upgrade                                           ║
║   • Doctrine DBAL 4.x                                         ║
║   • Other dependencies                                        ║
║ Week 7-8: Symfony 8.0 Core                                    ║
║   • Update to Symfony 8.0                                     ║
║   • Configuration updates                                     ║
║   • Initial testing                                           ║
║ Week 9-10: Testing & Validation                               ║
║   • Comprehensive testing                                     ║
║   • Performance benchmarking                                  ║
║   • Staging deployment                                        ║
║ Week 11-12: Production Deployment                             ║
║   • Blue-green deployment                                     ║
║   • Monitoring                                                ║
║   • Post-deployment validation                                ║
╚═══════════════════════════════════════════════════════════════╝

Total: 10-12 weeks
```

---

## ✅ Phase Completion Checklist

### Phase 1 Checklist

Use this before proceeding to Phase 2:

```markdown
- [ ] Symfony 7.4.x installed and verified
- [ ] Zero deprecation warnings in tests
- [ ] Zero deprecation warnings in container
- [ ] PHPStan Level 8 passes with no errors
- [ ] All tests passing (100% of baseline)
- [ ] Test coverage >= baseline
- [ ] Performance within 5% of baseline
- [ ] All 25 agents validated
- [ ] Staging deployment successful
- [ ] Phase completion report generated
- [ ] Go/No-Go decision: GO ✅
```

**To generate completion report:**

```bash
# Copy template
cp docs/planning/PHASE_COMPLETION_REPORT_TEMPLATE.md \
   docs/planning/PHASE_1_COMPLETION_REPORT.md

# Fill in the details
code docs/planning/PHASE_1_COMPLETION_REPORT.md
```

### Phase 2 Checklist

Before production deployment:

```markdown
- [ ] PHP 8.4 installed and tested
- [ ] Doctrine DBAL 4.x upgraded
- [ ] Symfony 8.0 installed
- [ ] All tests passing
- [ ] Performance acceptable
- [ ] Staging validated (48h)
- [ ] Rollback procedure tested
- [ ] Deployment runbook reviewed
- [ ] Team briefed
- [ ] Monitoring configured
- [ ] Go/No-Go decision: GO ✅
```

---

## 🎯 Key Success Criteria

Upgrade is successful when:

### Technical Criteria
- ✅ **Symfony Version:** 8.0.x confirmed
- ✅ **PHP Version:** 8.4 confirmed
- ✅ **Deprecations:** Zero
- ✅ **Tests:** 100% passing
- ✅ **Performance:** Within 20% of baseline
- ✅ **Errors:** < 0.5% error rate
- ✅ **Stability:** Zero critical issues in 24h

### Business Criteria
- ✅ **Uptime:** No unplanned downtime
- ✅ **User Impact:** No user-reported issues
- ✅ **Content:** Publishing workflow unaffected
- ✅ **SEO:** No negative impact

---

## 🛠️ Common Commands Reference

### Readiness & Preparation

```bash
# Check system readiness
./scripts/check-symfony8-readiness.sh

# Check current Symfony version
php bin/console --version

# Check deprecations
SYMFONY_DEPRECATIONS_HELPER=weak ./vendor/bin/phpunit

# Check container deprecations
php bin/console debug:container --deprecations
```

### Testing

```bash
# Run all tests
./vendor/bin/phpunit

# Run tests with coverage
XDEBUG_MODE=coverage ./vendor/bin/phpunit --coverage-html var/coverage

# Run PHPStan
./vendor/bin/phpstan analyse

# Run code style check
./vendor/bin/php-cs-fixer fix --dry-run
```

### Agent-Based Testing

```bash
# Backend API testing
claude --agent backend-api-tester

# Multilingual testing
claude --agent multilanguage-tester

# Performance testing
claude --agent performance-tester

# Security audit
claude --agent security-auditor

# Full integration testing
claude --agent fullstack-integration-tester
```

### Upgrade Commands

```bash
# Phase 1: Update to Symfony 7.4
composer require "symfony/framework-bundle:7.4.*"
composer recipes:update

# Phase 2: Update to Symfony 8.0
composer require "symfony/framework-bundle:8.0.*"
composer recipes:update

# Update all Symfony packages
composer update "symfony/*" --with-all-dependencies
```

### Cache Management

```bash
# Clear all caches
rm -rf var/cache/*

# Clear specific environment
php bin/console cache:clear --env=prod

# Warm cache
php bin/console cache:warmup --env=prod
```

### Database

```bash
# Check migrations status
php bin/console doctrine:migrations:status

# Run migrations
php bin/console doctrine:migrations:migrate --no-interaction

# Validate schema
php bin/console doctrine:schema:validate
```

---

## 🚨 Troubleshooting

### Problem: Composer Memory Exhausted

```bash
# Solution: Increase memory limit
php -d memory_limit=-1 $(which composer) update
```

### Problem: Deprecations Still Showing After Fixes

```bash
# Solution: Clear cache thoroughly
rm -rf var/cache/*
php bin/console cache:clear --no-warmup
php bin/console cache:warmup

# Then rerun tests
SYMFONY_DEPRECATIONS_HELPER=weak ./vendor/bin/phpunit
```

### Problem: Tests Failing After Upgrade

```bash
# Solution: Update test dependencies
composer update --dev

# Clear test cache
php bin/console cache:clear --env=test

# Run with verbose output
./vendor/bin/phpunit --testdox --verbose
```

### Problem: Container Compilation Fails

```bash
# Solution: Debug container
php bin/console debug:container X --env=prod

# Clear and rebuild
rm -rf var/cache/*
php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:warmup --env=prod
```

**For more issues, see:** `SYMFONY_8_UPGRADE_PLAN.md` → Appendix B: Common Issues & Solutions

---

## 🔄 Rollback Procedures

### Quick Rollback (15 minutes)

If critical issues occur in production:

```bash
# Option 1: Blue-Green (if available)
# Switch load balancer back to BLUE environment

# Option 2: Git Rollback
cd apps/backend
git checkout tags/v7.4.x
composer install --no-dev --optimize-autoloader
php bin/console cache:clear --env=prod
sudo systemctl restart php8.2-fpm

# Option 3: Backup Restore
tar -xzf /var/backups/deschide_TIMESTAMP.tar.gz -C /
sudo systemctl restart php8.2-fpm
```

**Rollback Triggers:**
- Error rate > 5%
- API unavailable > 5 minutes
- Performance degradation > 50%
- Data corruption detected
- Authentication failures

**For complete rollback plan, see:** `SYMFONY_8_UPGRADE_PLAN.md` → Rollback Plan

---

## 👥 Team Roles & Responsibilities

### Phase 1
- **Lead:** Senior Backend Developer
- **Support:** 1 Developer
- **QA:** QA Engineer + Agent Orchestration
- **Duration:** 4 weeks

### Phase 2
- **Lead:** Senior Backend Developer
- **DevOps:** DevOps Engineer
- **Support:** 1 Developer
- **QA:** QA Engineer + Agent Orchestration
- **Duration:** 6-8 weeks

---

## 📞 Support & Resources

### Internal Resources
- **Master Plan:** `docs/planning/SYMFONY_8_UPGRADE_PLAN.md`
- **Agents:** `.claude/agents/` directory
- **Scripts:** `scripts/` directory
- **Documentation:** `docs/` directory

### External Resources
- [Symfony 8.0 Release Notes](https://symfony.com/8)
- [Symfony Upgrade Guide](https://symfony.com/doc/current/setup/upgrade_major.html)
- [API Platform Documentation](https://api-platform.com/docs/)
- [Doctrine ORM 3.x](https://www.doctrine-project.org/projects/orm.html)
- [PHP 8.4 Release Notes](https://www.php.net/releases/8.4/en.php)

### Community
- [Symfony Slack](https://symfony.com/slack)
- [Stack Overflow - Symfony](https://stackoverflow.com/questions/tagged/symfony)

---

## 📝 Documentation Requirements

### During Upgrade

Document continuously:

1. **Deprecations Inventory:**
   - Create: `docs/planning/deprecations_inventory.md`
   - Update as you fix each deprecation

2. **Baseline Metrics:**
   - Create: `docs/planning/upgrade_baselines.json`
   - Capture before any changes

3. **Phase Reports:**
   - Use template: `PHASE_COMPLETION_REPORT_TEMPLATE.md`
   - Complete after each phase

4. **Issues Log:**
   - Document all issues encountered
   - Include resolution steps

### After Upgrade

Required documentation:

1. **Post-Deployment Report**
2. **Performance Analysis**
3. **Lessons Learned**
4. **Updated Architecture Docs**
5. **Migration Guide for Future Symfony Upgrades**

---

## 🎯 Success Metrics Dashboard

Track these KPIs throughout the upgrade:

| Metric | Baseline | Target | Current | Status |
|--------|----------|--------|---------|--------|
| Symfony Version | 7.3 | 8.0 | X.X | 🔄 |
| PHP Version | 8.2 | 8.4 | 8.X | 🔄 |
| Deprecations | XX | 0 | XX | 🔄 |
| Test Pass Rate | 100% | 100% | XX% | 🔄 |
| Coverage | 78.5% | >=78.5% | XX% | 🔄 |
| API P95 | 250ms | <375ms | XXXms | 🔄 |
| Error Rate | 0.1% | <0.5% | X.X% | 🔄 |

**Legend:**
- ✅ On track
- 🔄 In progress
- ⚠️ Needs attention
- ❌ Blocked

---

## 🏁 Final Checklist Before Production

**48 hours before deployment:**

```markdown
Technical Preparation
- [ ] All tests passing in staging (48h stable)
- [ ] Performance benchmarks acceptable
- [ ] Zero critical errors in logs
- [ ] Database backup completed and verified
- [ ] Code tagged for rollback
- [ ] PHP 8.4 installed on production
- [ ] Rollback procedure documented and tested

Team Preparation
- [ ] Deployment announcement sent
- [ ] Maintenance window confirmed
- [ ] On-call team identified
- [ ] Communication plan ready
- [ ] Post-deployment runbook reviewed

Monitoring Preparation
- [ ] Alert thresholds configured
- [ ] Dashboards updated
- [ ] Log aggregation verified
- [ ] Performance baseline documented

Go/No-Go Meeting
- [ ] Technical Lead: GO ✅
- [ ] DevOps Lead: GO ✅
- [ ] QA Lead: GO ✅
- [ ] Project Manager: GO ✅
```

---

## 🎓 Training & Knowledge Transfer

### Required Reading
1. ✅ Symfony 8.0 Release Notes
2. ✅ PHP 8.4 Migration Guide
3. ✅ Doctrine DBAL 4.x Upgrade Guide
4. ✅ This upgrade plan (complete)

### Hands-On Practice
1. ✅ Run readiness check
2. ✅ Test deprecation scanning
3. ✅ Practice rollback procedure
4. ✅ Execute agent-based tests

### Team Sync
- **Pre-Upgrade Meeting:** Review plan, assign roles
- **Daily Standups:** During active phases
- **Phase Reviews:** Go/No-Go decisions
- **Post-Mortem:** After completion

---

## 💡 Pro Tips

1. **Start Small:** Test everything in local environment first
2. **Use Agents:** Leverage the 25 specialized agents for comprehensive testing
3. **Document Everything:** Future-you will thank current-you
4. **Monitor Closely:** Watch metrics like a hawk post-deployment
5. **Have Coffee Ready:** Some deprecation fixes can be tedious 😅
6. **Trust the Process:** The two-phase approach exists for a reason
7. **Backup Everything:** You can never have too many backups
8. **Test Rollback:** Practice makes perfect

---

## 📧 Questions or Issues?

- **Technical Questions:** Check `SYMFONY_8_UPGRADE_PLAN.md` first
- **Blockers:** Escalate to Phase Lead immediately
- **Suggestions:** Document and discuss in phase reviews

---

**Ready to start?**

```bash
./scripts/check-symfony8-readiness.sh
```

**Good luck! 🚀**

---

*Last Updated: 2025-12-10*  
*Version: 2.0*  
*Maintained by: Tech Team*
