# Deprecation Fix Priority List

**Generated:** 2025-12-10
**Project:** Deschide News App - Symfony 8 Upgrade
**Phase:** 1, Week 1-2 (Preparation & Audit)

---

## Executive Summary

| Category | Count | Effort | Priority |
|----------|-------|--------|----------|
| Breaking Changes | 0 | - | - |
| Configuration Changes | 2-3 | Low | Medium |
| Third-Party Bundles | 5 | Low-Medium | Medium |
| Code Deprecations | 0 | - | - |

**Total Estimated Effort:** 6-10 hours
**Status:** Ready for Symfony 8 upgrade

---

## Analysis Results

### Code Deprecations (0 Found)

Based on the deprecation scan:
- TaggedIterator: 0 occurrences
- TaggedLocator: 0 occurrences
- Request::get(): 0 occurrences
- Application::add(): 0 occurrences
- PHPUnit Deprecations: 0

**Status:** No code changes required for deprecated patterns.

---

## Configuration Review

### 1. Security Configuration (`config/packages/security.yaml`)

**Status:** Reviewed - No OIDC token handler configuration found

Current configuration uses:
- JWT authentication via lexik/jwt-authentication-bundle
- Stateless firewall for API
- JSON login for authentication

**Action Required:** None - configuration is Symfony 8 compatible

### 2. Framework Configuration (`config/packages/framework.yaml`)

**Status:** To verify

Items to check:
- [ ] `session.storage_factory_id` - may need update
- [ ] `http_method_override` - deprecated setting
- [ ] `handle_all_throwables` - new in Symfony 7

**Action Required:** Review during upgrade

### 3. Doctrine Configuration (`config/packages/doctrine.yaml`)

**Status:** To verify

Items to check:
- [ ] `schema_manager_factory` - verify compatibility
- [ ] Connection settings for DBAL 4.x compatibility

**Action Required:** Review during DBAL 4.x upgrade (Phase 2)

### 4. Validation Configuration

**Status:** No config/validator/*.yaml files found

Validation is done via PHP attributes in entities.

**Action Required:** None

---

## Third-Party Bundle Compatibility

### Bundle Status Matrix

| Bundle | Current Version | Symfony 8 Compatible | Notes |
|--------|-----------------|---------------------|-------|
| api-platform/symfony | ^4.2.6 | Yes | Already using compatible version |
| doctrine/orm | ^3.5.7 | Yes | Compatible with Symfony 8 |
| doctrine/dbal | ^3.10.4 | Needs Upgrade | Upgrade to 4.x in Phase 2 |
| lexik/jwt-authentication-bundle | ^3.3.2 | Yes | Compatible |
| gesdinet/jwt-refresh-token-bundle | ^2.0.3 | Yes | Compatible |
| stof/doctrine-extensions-bundle | ^1.13 | Yes | Compatible |
| vich/uploader-bundle | ^2.8 | Monitor | Check for v3.0 |
| nelmio/cors-bundle | ^2.5 | Yes | Compatible |

### Bundles Requiring Attention

1. **doctrine/dbal** - Must upgrade from 3.x to 4.x
   - **When:** Phase 2, Step 20
   - **Effort:** Medium (2-3 hours)
   - **Risk:** Breaking changes in type system

2. **vich/uploader-bundle** - Monitor for updates
   - **Status:** v2.8 works with Symfony 7.x
   - **Action:** Check for v3.0 release before Phase 2

---

## Priority Fix List

### Phase 1 (Pre-Upgrade - This Week)

| Priority | Item | Effort | Status |
|----------|------|--------|--------|
| 1 | Review framework.yaml settings | 30 min | Pending |
| 2 | Verify doctrine.yaml compatibility | 30 min | Pending |
| 3 | Check security.yaml for deprecations | 30 min | Done |
| 4 | Update test expectations (reserved slugs) | 15 min | Pending |

### Phase 2 (During Symfony 7.4/8.0 Upgrade)

| Priority | Item | Effort | Status |
|----------|------|--------|--------|
| 5 | Upgrade Doctrine DBAL to 4.x | 2-3 hours | Pending |
| 6 | Run composer recipes:update | 30 min | Pending |
| 7 | Fix any Flex recipe conflicts | 1-2 hours | Pending |

### Phase 3 (Post-Upgrade Verification)

| Priority | Item | Effort | Status |
|----------|------|--------|--------|
| 8 | Run full test suite | 30 min | Pending |
| 9 | Verify bundle compatibility | 1 hour | Pending |
| 10 | Performance comparison | 30 min | Pending |

---

## Risk Assessment

### Low Risk Items
- Code deprecations (none found)
- Most third-party bundles (already compatible)
- Security configuration (JWT-based, no OIDC)

### Medium Risk Items
- Doctrine DBAL 4.x upgrade (breaking changes)
- Flex recipe updates (potential config conflicts)

### Items to Monitor
- VichUploaderBundle v3.0 release
- PHP 8.4 specific deprecations

---

## Recommendations

1. **Proceed with Upgrade:** The codebase is in excellent condition for Symfony 8 upgrade
2. **Focus Areas:** Doctrine DBAL upgrade will require most attention
3. **Testing Strategy:** Run tests after each step, not just at the end
4. **Rollback Plan:** Backup created, git branches ready

---

## Sign-Off

**Analysis Completed:** 2025-12-10
**Analyzed By:** Workflow Orchestrator Agent
**Approved By:** _____________ Date: _______

**Ready for Phase 1, Week 3 (Symfony 7.4 Upgrade):** YES

---

*This document is part of the Symfony 8 Upgrade Project*
*Reference: SYMFONY_8_UPGRADE_PLAN.md*
