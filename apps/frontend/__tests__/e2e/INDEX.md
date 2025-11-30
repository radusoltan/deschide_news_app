# Archive E2E Tests - Documentation Index

## Overview

This directory contains comprehensive Playwright E2E tests for the archive functionality in the Deschide News App, including both public and admin interfaces.

**Total Coverage:** 66 test cases across 21 test suites

---

## 📚 Documentation Files

### 1. **QUICK_START.md** ⭐ START HERE
**Purpose:** Quick reference guide to get started immediately
**Read when:** You want to run tests right away
**Contains:**
- Prerequisites checklist
- 3 easy ways to run tests
- Quick command reference
- Troubleshooting tips

[→ Open QUICK_START.md](./QUICK_START.md)

---

### 2. **README.archive-tests.md** 📖 FULL DOCUMENTATION
**Purpose:** Comprehensive documentation for all archive tests
**Read when:** You need detailed information about test structure and usage
**Contains:**
- Complete test coverage breakdown
- Running tests (all variants)
- Prerequisites and setup
- Test patterns used
- Known limitations
- Debugging guide
- CI/CD examples
- Contributing guidelines

[→ Open README.archive-tests.md](./README.archive-tests.md)

---

### 3. **ARCHIVE_TESTS_SUMMARY.md** 📊 OVERVIEW & CI/CD
**Purpose:** High-level overview and CI/CD integration guide
**Read when:** You need stats, metrics, or CI/CD setup
**Contains:**
- Test statistics and metrics
- Coverage matrix
- Test execution matrix
- Performance benchmarks
- GitHub Actions examples
- Docker integration
- Maintenance guidelines
- Next steps

[→ Open ARCHIVE_TESTS_SUMMARY.md](./ARCHIVE_TESTS_SUMMARY.md)

---

### 4. **INDEX.md** 📑 THIS FILE
**Purpose:** Navigation hub for all documentation
**Read when:** You don't know where to start

---

## 🧪 Test Files

### 1. **archive.spec.ts** - Public Archive Tests
**31 test cases** covering public archive browsing functionality

**Test Suites:**
- Core Functionality (5 tests)
- Year Filter Functionality (3 tests)
- Category Filter Functionality (2 tests)
- Pagination (3 tests)
- SEO and Metadata (4 tests)
- Responsive Design (5 tests)
- Multilingual Support (4 tests)
- Combined Filters (2 tests)
- Loading States (2 tests)
- Error Handling (2 tests)

**Pages Tested:**
- `/ro/archive`
- `/en/archive`
- `/ru/archive`

[→ View archive.spec.ts](./archive.spec.ts)

---

### 2. **admin-archive.spec.ts** - Admin Archive Tests
**35 test cases** covering admin archive management

**Test Suites:**
- Access Control (4 tests)
- Page Structure (3 tests)
- Statistics Display (3 tests)
- Bulk Archive Form (6 tests)
- Archived Articles List (3 tests)
- Info Footer (2 tests)
- Multilingual Support (3 tests)
- Responsive Design (4 tests)
- Loading States (2 tests)
- Error Handling (2 tests)
- Integration (2 tests)

**Pages Tested:**
- `/ro/admin/archive`
- `/en/admin/archive`
- `/ru/admin/archive`

[→ View admin-archive.spec.ts](./admin-archive.spec.ts)

---

## 🎯 Quick Decision Guide

**I want to...**

### → Run tests immediately
Read: [QUICK_START.md](./QUICK_START.md)
Command: `pnpm test:e2e:archive`

### → Understand test structure
Read: [README.archive-tests.md](./README.archive-tests.md)
Section: "Test Patterns Used"

### → Debug failing tests
Read: [README.archive-tests.md](./README.archive-tests.md)
Section: "Debugging Failed Tests"

### → Set up CI/CD
Read: [ARCHIVE_TESTS_SUMMARY.md](./ARCHIVE_TESTS_SUMMARY.md)
Section: "CI/CD Integration"

### → See test coverage
Read: [ARCHIVE_TESTS_SUMMARY.md](./ARCHIVE_TESTS_SUMMARY.md)
Section: "Test Coverage Summary"

### → Add new tests
Read: [README.archive-tests.md](./README.archive-tests.md)
Section: "Contributing"

### → Check performance
Read: [ARCHIVE_TESTS_SUMMARY.md](./ARCHIVE_TESTS_SUMMARY.md)
Section: "Performance Metrics"

### → Understand authentication
Read: [admin-archive.spec.ts](./admin-archive.spec.ts)
Look for: `loginAsAdmin()` helper function

---

## 📋 Essential Commands

### Run All Archive Tests
```bash
cd /var/www/deschide_news_app/apps/frontend
pnpm test:e2e:archive
```

### Interactive UI Mode (Best for Debugging)
```bash
pnpm test:e2e:ui
```

### Run Specific File
```bash
pnpm playwright test __tests__/e2e/archive.spec.ts
```

### View Report
```bash
npx playwright show-report
```

### Debug Mode
```bash
pnpm playwright test __tests__/e2e/archive.spec.ts --debug
```

---

## 🎨 Test Architecture

```
__tests__/e2e/
├── archive.spec.ts              ← Public archive tests (31 tests)
├── admin-archive.spec.ts        ← Admin archive tests (35 tests)
├── INDEX.md                     ← This file (navigation hub)
├── QUICK_START.md               ← Quick reference (start here)
├── README.archive-tests.md      ← Full documentation
└── ARCHIVE_TESTS_SUMMARY.md     ← Overview & CI/CD
```

---

## 📊 Test Coverage at a Glance

| Category | Coverage |
|----------|----------|
| **Functionality** | Year filters, Category filters, Pagination, Bulk operations |
| **SEO** | Meta tags, noindex, canonical URLs, language alternates |
| **Responsive** | Desktop (1920x1080), Tablet (768x1024), Mobile (375x667) |
| **Browsers** | Chromium, Firefox, WebKit |
| **Languages** | Romanian, English, Russian |
| **Auth** | Login redirects, Access control, Token handling |
| **UX** | Loading states, Error handling, Empty states |

---

## 🚀 Prerequisites Checklist

Before running tests, ensure:

- [ ] Backend API running on http://127.0.0.1:8081
- [ ] Frontend dev server running on http://localhost:3005
- [ ] Test data exists (articles from multiple years)
- [ ] Multiple categories configured
- [ ] Some archived articles in database
- [ ] At least 30 articles for pagination tests

---

## 🔗 Related Resources

- **Project Root:** `/var/www/deschide_news_app/`
- **Frontend:** `/var/www/deschide_news_app/apps/frontend/`
- **Backend:** `/var/www/deschide_news_app/apps/backend/`
- **Playwright Config:** `../../playwright.config.ts`
- **Package.json:** `../../package.json`
- **CLAUDE.md:** `/var/www/deschide_news_app/CLAUDE.md`

---

## 📞 Getting Help

1. **Quick issues?** → Check [QUICK_START.md](./QUICK_START.md) troubleshooting
2. **Test failures?** → See [README.archive-tests.md](./README.archive-tests.md) debugging guide
3. **CI/CD setup?** → Check [ARCHIVE_TESTS_SUMMARY.md](./ARCHIVE_TESTS_SUMMARY.md) examples
4. **Playwright docs:** https://playwright.dev
5. **Project docs:** `/var/www/deschide_news_app/CLAUDE.md`

---

## 🎯 Common Tasks

### First Time Running Tests
1. Read [QUICK_START.md](./QUICK_START.md)
2. Start backend and frontend servers
3. Run `pnpm test:e2e:archive`
4. View report with `npx playwright show-report`

### Debugging a Failing Test
1. Run in UI mode: `pnpm test:e2e:ui`
2. Or headed mode: `pnpm playwright test ... --headed`
3. Check screenshots in `test-results/`
4. View trace: `npx playwright show-trace test-results/*/trace.zip`

### Adding New Tests
1. Read "Contributing" in [README.archive-tests.md](./README.archive-tests.md)
2. Choose appropriate test file
3. Follow existing patterns
4. Use descriptive test names
5. Add comments for complex logic

### Setting Up CI/CD
1. Read "CI/CD Integration" in [ARCHIVE_TESTS_SUMMARY.md](./ARCHIVE_TESTS_SUMMARY.md)
2. Copy GitHub Actions example
3. Adjust for your CI platform
4. Configure environment variables
5. Test on feature branches first

---

## 📈 Statistics

- **Total Test Cases:** 66
- **Total Test Suites:** 21
- **Code Lines:** 1,300+
- **Documentation Lines:** 1,000+
- **Browsers Tested:** 3 (Chromium, Firefox, WebKit)
- **Viewports Tested:** 6 (Desktop, Tablet, Mobile x2)
- **Languages Tested:** 3 (Romanian, English, Russian)

---

## ✅ Next Steps

1. ✅ **Tests created** - You are here
2. → **Run tests** - Use `pnpm test:e2e:archive`
3. → **Review results** - Check HTML report
4. → **Debug failures** - Use UI mode
5. → **Update auth** - When implemented
6. → **Add to CI** - GitHub Actions
7. → **Expand coverage** - Add more tests as needed

---

**Last Updated:** 2025-11-30
**Version:** 1.0.0
**Status:** ✅ Ready to Use

---

## 🏁 Start Here

**New to these tests?** → [QUICK_START.md](./QUICK_START.md)

**Ready to run?**
```bash
cd /var/www/deschide_news_app/apps/frontend
pnpm test:e2e:archive
```

**Happy Testing! 🎭**
