# CI/CD Quick Reference Card

**Quick guide for developers working with GitHub Actions CI/CD**

---

## 🚀 Running CI Checks Locally

### Backend (Before Push)
```bash
cd /var/www/deschide_news_app/apps/backend

# Run ALL CI checks
composer ci

# Or run individually:
composer test           # PHPUnit tests (no coverage)
composer test:coverage  # Tests with HTML coverage report
composer lint          # PHPStan static analysis
composer lint:fix      # Fix code style issues
composer lint:check    # Check code style (dry-run)
```

### Frontend (Before Push)
```bash
cd /var/www/deschide_news_app/apps/frontend

# Run ALL CI checks
pnpm lint && pnpm test:ci && pnpm build

# Or run individually:
pnpm lint          # ESLint linting
pnpm test:ci       # Jest tests (CI mode)
pnpm build         # Next.js production build
```

---

## 📋 CI Workflow Triggers

| Branch Pattern | Triggers CI? | Notes |
|----------------|--------------|-------|
| `main` | ✅ | Production releases |
| `develop` | ✅ | Integration branch |
| `feature/*` | ✅ | Feature development |
| `bugfix/*` | ✅ | Bug fixes |
| `release/*` | ✅ | Release preparation |
| `hotfix/*` | ✅ | Production hotfixes |

---

## 🎯 Path Filtering

**Backend CI runs when:**
- Files in `apps/backend/**` change
- Workflow file `.github/workflows/backend-ci.yml` changes

**Frontend CI runs when:**
- Files in `apps/frontend/**` change
- Workflow file `.github/workflows/frontend-ci.yml` changes

**Tip:** Change only frontend code → Only frontend CI runs (saves ~5 minutes)

---

## ⏱️ Typical Run Times

| Workflow | Cache Hit | Cache Miss | Total |
|----------|-----------|------------|-------|
| Backend CI | ~4-5 min | ~6-7 min | ~5 min avg |
| Frontend CI | ~5-6 min | ~8-9 min | ~6 min avg |
| Both | ~10-11 min | ~14-16 min | ~11 min avg |

---

## 📦 Artifacts Available

### Backend
- `backend-coverage` - Coverage XML (7 days)
- `backend-test-results` - Test results + cache (7 days)

### Frontend
- `frontend-jest-coverage` - Jest coverage (7 days)
- `frontend-build` - Next.js build output (7 days)
- `frontend-test-results` - Test results (7 days)

**Download:** GitHub Actions run → Scroll to "Artifacts" → Click name

---

## ✅ CI Checks

### Backend CI
1. ✅ PHPStan (static analysis, level 6)
2. ⚠️ PHP-CS-Fixer (code style, warning only)
3. ✅ PHPUnit (376 tests with coverage)

### Frontend CI
1. ✅ ESLint (linting)
2. ✅ Jest (163 tests with coverage)
3. ✅ Next.js Build (production build)

---

## 🔧 Troubleshooting

### "Composer install failed"
```bash
# Clear cache and reinstall
rm -rf vendor/ composer.lock
composer install
```

### "PHPStan errors"
```bash
# Regenerate Symfony container
symfony console cache:clear --env=dev

# Update baseline (if needed)
vendor/bin/phpstan analyse --generate-baseline
```

### "pnpm install failed"
```bash
# Clear cache and reinstall
rm -rf node_modules/ pnpm-lock.yaml
pnpm install
```

### "Tests failing in CI but passing locally"
- Check environment variables
- Verify database connection
- Check Node.js/PHP versions match
- Review service container logs

---

## 📚 Documentation

**Detailed docs:**
- Workflow README: `.github/workflows/README.md`
- Implementation Report: `docs/CI_CD_IMPLEMENTATION_REPORT.md`
- Main CLAUDE.md: `/var/www/deschide_news_app/CLAUDE.md`

**GitHub Actions:**
- View runs: GitHub → Actions tab
- View logs: Click workflow run → Click job
- Download artifacts: Scroll to bottom of run

---

## 🎓 Best Practices

**Before creating PR:**
```bash
# 1. Run CI checks locally
composer ci  # Backend
pnpm lint && pnpm test:ci && pnpm build  # Frontend

# 2. Fix any issues

# 3. Commit and push
git add .
git commit -m "feat(scope): description"
git push

# 4. Create PR and wait for green checks ✅
```

**Commit message format:**
```
<type>(<scope>): <description>

Types: feat, fix, docs, style, refactor, test, chore
Scopes: backend, frontend, ci, docs
```

**Examples:**
```bash
git commit -m "feat(backend): add user authentication"
git commit -m "fix(frontend): resolve image loading issue"
git commit -m "test(backend): add article repository tests"
git commit -m "ci: update GitHub Actions workflows"
```

---

## 🚨 Emergency Bypass (Use Sparingly!)

**Skip CI checks (not recommended):**
```bash
# Add [skip ci] to commit message
git commit -m "docs: update README [skip ci]"
```

**When to use:**
- Documentation-only changes
- README updates
- CHANGELOG updates
- Emergency hotfixes (with approval)

**Note:** Branch protection may still require checks before merge

---

## 📞 Getting Help

**CI failing?**
1. Check GitHub Actions logs
2. Run checks locally to reproduce
3. Review troubleshooting section
4. Ask team for help
5. Create issue if persistent

**Need to update workflows?**
- Workflows: `.github/workflows/`
- Test locally with `act` (GitHub Actions local runner)
- Create PR for workflow changes
- Get review before merging

---

**Last Updated:** 2025-12-01
**Maintainer:** Development Team
