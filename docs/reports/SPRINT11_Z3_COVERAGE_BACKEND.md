# SPRINT 11 — Z3: Fix Coverage Backend

**Date:** 2026-04-01
**Agent:** Claude Code
**Execution:** Parallel with Codex (frontend tests)
**Branch:** `feature/sprint-11-security-hardening`

---

## Diagnosis

**Root cause of "0.69%" coverage:** The audit ran PHPUnit without enabling the PCOV coverage driver.

Without `php -d pcov.enabled=1` or `XDEBUG_MODE=coverage`, PHPUnit cannot instrument code and reports near-zero coverage. This was a **measurement error**, not a code quality issue.

- [x] `<source>` present in phpunit.dist.xml (correct PHPUnit 12.x format)
- [ ] ~~Format vechi `<coverage>` (PHPUnit < 12)~~ — `<coverage>` section present but only for report output config (valid)
- [x] PCOV installed but not enabled during audit measurement
- [ ] ~~Testele sunt pur mock-only~~ — mix of mock + integration + functional
- [x] **Root cause: PCOV not enabled** (`php -d pcov.enabled=1` required)

## Results

| Metric | Before (audit) | After (real) |
|--------|---------------|--------------|
| Coverage lines | 0.69% (false) | **69.08%** |
| Coverage methods | N/A | **73.85%** |
| Coverage classes | N/A | **48.64%** |
| Total tests | 3,568 | 3,568 |
| Tests fail | 215 (pre-existing) | 215 (pre-existing, not introduced by us) |
| Tests KernelTestCase | 38 | 38 |
| Tests mock-only | 242 | 242 |
| Smoke tests | 31 | 31 |
| Lines covered | ~132 | **13,204 / 19,114** |

## Pre-Existing Test Failures (Not Introduced by Sprint 11)

| Category | Errors | Failures | Root Cause |
|----------|--------|----------|------------|
| Unit | 132 | 6 | `ClassIsFinalException` (PHPUnit 12 can't mock `final` classes), outdated enum assertions |
| Smoke | 0 | 13 | Endpoints returning 500 (missing test fixtures) |
| Integration | 11 | 2 | DB schema or missing test data |
| Functional | 26 | 13 | Various HTTP assertion failures |
| **Service** | **0** | **0** | **Clean** |
| **Validator** | **0** | **0** | **Clean** |

## Fixes Applied

1. **No config changes needed** — `phpunit.dist.xml` `<source>` section was already correct
2. **Documented proper coverage command** — `php -d pcov.enabled=1 vendor/bin/phpunit --coverage-text`
3. **Created coverage strategy** — `docs/COVERAGE_STRATEGY.md` with plan from 69% to 80%+

## Zero-Coverage Breakdown (184 classes)

| Namespace | Uncovered Classes | Priority |
|-----------|------------------|----------|
| State (Providers/Processors) | 29 | HIGH |
| Command (CLI) | 27 | LOW |
| Entity | 25 | HIGH |
| Service | 19 | HIGH |
| Controller | 16 | HIGH |
| Repository | 15 | MEDIUM |
| EventSubscriber | 8 | MEDIUM |
| Dto | 8 | LOW |
| MessageHandler | 7 | MEDIUM |
| Other | 30 | LOW |

## Strategy Post-Sprint (see docs/COVERAGE_STRATEGY.md)

- **Phase 1**: Fix pre-existing test failures (132 `final` class errors) — est. 1-2 days
- **Phase 2**: 69% → 75% via entity + service + controller tests — est. 2-3 days
- **Phase 3**: 75% → 80%+ via State providers + repositories — est. 3-4 days
- **Blocker**: `final` class mocking needs architectural decision (remove `final` vs use interfaces)

## Summary

The "0.69% coverage" finding was a **false alarm** caused by PCOV not being enabled during measurement. The real coverage is **69.08%**, which significantly exceeds the sprint target of ≥40%. The `phpunit.dist.xml` configuration was correct. No smoke tests needed to be added to boost coverage — the existing 3,568 tests already provide substantial coverage.
