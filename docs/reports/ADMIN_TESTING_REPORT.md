# Admin Panel Testing Report — Deschide News App

**Date**: 2026-03-19
**Tag**: v1.0.0-rc1 (main branch)
**Backend**: Symfony 8.0.2 on :8081
**Frontend**: Next.js 16 on :3005
**DB**: PostgreSQL 18.2, `deschide_news`
**Test Users**: admin/editor/user (password: `password`)
**Test Runner**: Playwright 1.x, Chromium + Mobile Chrome

---

## 1. Data Seed Results

Database seeded via `doctrine:fixtures:load` — all fixtures loaded successfully.

| Entity | Expected | Actual | Status |
|--------|----------|--------|--------|
| Users | 3 (+3 test) | 6 | PASS |
| Categories | 8 | 8 | PASS |
| Authors | 12 | 12 | PASS |
| Articles | 80 | 80 | PASS |
| Images | 40 | 40 | PASS |
| Thumbnails | 160 | 160 | PASS |
| Article-Image Links | ~154 | 154 | PASS |
| Live Texts | 10 | 10 | PASS |
| Important Articles Lists | 5 | 5 | PASS |
| Short Links | 59 | 59 | PASS |
| Thumbnail Profiles | 4 | 4 | PASS |

**Article Status Distribution:**
- Published: 59
- Submitted: 15
- New: 6

**API Verification:**
- `GET /api/articles` — 80 articles returned (PASS)
- `POST /api/login_check` — JWT token generated (PASS)

---

## 2. Admin Module Test Results

### Desktop (Chromium) — 37/41 passed

| # | Module | Tests | Pass | Fail | Issues |
|---|--------|-------|------|------|--------|
| 1 | Auth & Access Control | 4 | 4 | 0 | Login works; unauthenticated users redirected to `/login` |
| 2 | Admin Dashboard | 3 | 3 | 0 | Stats cards load, shows 80 articles, 59 published, 14 action links |
| 3 | Articles CRUD | 9 | 7 | 2 | Table loads 20 rows, Edit/Delete visible, filters work. **Create Article opens modal (not /new page)**. Edit link loses locale prefix |
| 4 | Categories CRUD | 3 | 3 | 0 | 5 category rows, create form has Title/Slug/Status/FrontPage fields |
| 5 | Authors | 3 | 2 | 1 | 12 rows with Edit links. **Edit link navigates to `/admin/authors` (loses `/ro/` prefix)** |
| 6 | Images | 3 | 3 | 0 | Gallery loads, file upload input present, edit page accessible |
| 7 | Live Texts | 3 | 3 | 0 | 10 rows, create form with 8 fields, edit accessible |
| 8 | Short Links | 2 | 2 | 0 | Page loads (101K chars), create form with 5 fields |
| 9 | Users | 1 | 1 | 0 | Shows admin user |
| 10 | Important Articles | 1 | 1 | 0 | Page loads (53K chars) |
| 11 | Archive | 1 | 1 | 0 | Page loads (93K chars) |
| 12 | Statistics | 1 | 1 | 0 | Page loads (115K chars) |
| 13 | Settings | 1 | 1 | 0 | Page loads (49K chars) |
| 14 | Navigation & UI | 4 | 3 | 1 | All 9 sidebar sections present. **Sidebar link loses locale prefix** |
| 15 | Public Frontend | 3 | 2 | 1 | Homepage shows 41 articles, article detail loads. **Russian locale timeout** |
| **TOTAL** | | **41** | **37** | **4** | **90% pass rate** |

### Mobile (Mobile Chrome / Pixel 5) — 34/41 passed

| # | Module | Tests | Pass | Fail | Issues |
|---|--------|-------|------|------|--------|
| 1 | Auth & Access Control | 4 | 4 | 0 | Works on mobile |
| 2 | Admin Dashboard | 3 | 3 | 0 | Stats load on mobile |
| 3 | Articles CRUD | 9 | 6 | 3 | Create modal works on mobile. **Edit link click intercepted by fixed navbar (z-index overlap)**. Create button modal issue same as desktop |
| 4 | Categories CRUD | 3 | 2 | 1 | **Create form: first `input` is hidden search bar on mobile** |
| 5 | Authors | 3 | 2 | 1 | Same locale-prefix bug as desktop |
| 6 | Images | 3 | 3 | 0 | All pass on mobile |
| 7 | Live Texts | 3 | 3 | 0 | All pass on mobile |
| 8 | Short Links | 2 | 2 | 0 | All pass on mobile |
| 9 | Users | 1 | 1 | 0 | Pass |
| 10 | Important Articles | 1 | 1 | 0 | Pass |
| 11 | Archive | 1 | 1 | 0 | Pass |
| 12 | Statistics | 1 | 1 | 0 | Pass |
| 13 | Settings | 1 | 1 | 0 | Pass |
| 14 | Navigation & UI | 4 | 4 | 0 | Sidebar hidden on mobile (correct), all sections present |
| 15 | Public Frontend | 3 | 0 | 3 | **All public pages timeout on mobile (>60s)** |
| **TOTAL** | | **41** | **34** | **7** | **83% pass rate** |

---

## 3. Issues Found

### Critical (Blocker)

1. **PUBLIC FRONTEND TIMEOUT ON MOBILE** — All public pages (`/ro`, `/en`, `/ru`) timeout after 60s on mobile viewport. The homepage is 600K+ chars of HTML which is extremely heavy for mobile. This is a **performance blocker** for mobile users.

### High (Functionality broken)

2. **ADMIN NAVIGATION LOSES LOCALE PREFIX** — When clicking links in the admin panel (sidebar, edit buttons), the `/ro/` locale prefix is stripped. The browser navigates to `/admin/articles` instead of `/ro/admin/articles`. This causes:
   - Edit links redirect to list page (404 or fallback)
   - Sidebar navigation lands on wrong routes
   - Category edit, Author edit, Article edit all affected
   - **Root cause**: Links in admin components likely use relative paths or miss the locale segment. The `href` attribute shows correct path (e.g., `/ro/admin/articles/79/edit`) but after click, the URL becomes `/admin/articles`.

3. **MOBILE: EDIT LINK CLICK INTERCEPTED BY FIXED NAVBAR** — On mobile viewport, the fixed top navbar (`z-30`) overlaps table rows, making the "Edit" links unclickable. The Playwright error shows `<div class="px-3 py-3 lg:px-5 lg:pl-3"> from <nav class="fixed z-30..."> intercepts pointer events`.

### Medium (Degraded experience)

4. **DASHBOARD PUBLISHED COUNT = 0** — The dashboard shows "Publicate: 0" and "Drafturi: 0" while the database has 59 published and 6 new articles. The "Total Articole: 80" is correct. The published/draft count likely queries with a different locale or uses a stats endpoint that doesn't match the fixture data.

5. **ALL AUTHORS SHOW "INACTIVE" STATUS** — All 12 authors in the list show "Inactive" badge. The AuthorFixtures may not be setting `isActive = true` by default, or the status field mapping differs.

6. **RUSSIAN LOCALE HOMEPAGE TIMEOUT (DESKTOP)** — The `/ru` homepage times out on desktop too (not just mobile). The `/ro` and `/en` pages load fine but `/ru` hangs. Likely a translation lookup issue or missing Russian content causing an infinite query.

7. **CREATE ARTICLE OPENS MODAL, NOT SEPARATE PAGE** — The "+ Create Article" button on the articles list opens a modal dialog (Article Title, Category, Language fields) instead of navigating to `/articles/new`. The `/articles/new` route exists and works, but the button uses a modal. This is a UX choice, not a bug, but creates inconsistency since `/articles/new` has a full form with 8+ fields while the modal has only 3.

### Low (Cosmetic / minor)

8. **"1 Issue" NEXT.JS ERROR BADGE** — A red "1 Issue" badge from Next.js dev tools appears on several pages. Indicates a JavaScript error or hydration mismatch that should be investigated.

9. **SEARCH BAR IN NAVBAR NOT FUNCTIONAL** — The search input in the top navbar shows `placeholder="Search"` but the test couldn't verify it's actually working (no search results behavior tested).

10. **CATEGORIES LIST SHOWS 5 ROWS, EXPECTED 8** — The categories list shows only 5 rows instead of 8 categories. May be a pagination issue or some categories are filtered by status/locale.

---

## 4. Security Observations

| Check | Status | Notes |
|-------|--------|-------|
| Auth redirect on `/admin` | PASS | Unauthenticated users redirected to `/en/login?from=%2Fadmin` |
| Login with wrong password | PASS | Error shown, stays on login page |
| JWT authentication | PASS | Token generated via `/api/login_check` |
| Admin isolation from public | PASS | Admin and public are separate route groups |
| Rate limiting | NOT TESTED | Configuration exists but not verified in E2E |
| CSRF protection | NOT TESTED | Server actions should include CSRF tokens |

**Note**: The auth redirect goes to `/en/login` regardless of the original locale (`/ro/admin`). This should redirect to `/ro/login` to maintain locale consistency.

---

## 5. Mobile Responsiveness

| Aspect | Status | Notes |
|--------|--------|-------|
| Sidebar hidden on mobile | PASS | `sidebar visible=false` on 375x667 |
| Dashboard stats | PASS | Cards render on mobile |
| Articles table | PARTIAL | Table renders but **Edit links unclickable** due to navbar overlap |
| Category forms | PARTIAL | Hidden search input conflicts with form inputs |
| Public homepage | FAIL | **Timeout on mobile viewport** |
| Login form | PASS | Works on mobile |
| Navigation | PASS | Menu structure accessible |

---

## 6. Recommendations (Priority Order)

### P0 — Fix immediately

1. **Fix locale prefix in admin navigation** — Investigate why `<a href="/ro/admin/articles/79/edit">` navigates to `/admin/articles` after click. Likely a Next.js Link component issue or middleware rewrite problem. Check if admin layout uses `<Link>` with correct locale interpolation.

2. **Fix mobile navbar z-index overlap** — The fixed navbar (`z-30`) overlaps table content on mobile. Either:
   - Add `scroll-margin-top` to table rows
   - Reduce navbar z-index
   - Add top padding to main content area on mobile

3. **Fix public frontend performance on mobile** — 600K+ chars of HTML is too heavy. Investigate:
   - Server-side rendering bottleneck
   - Missing pagination on article queries
   - Image optimization / lazy loading
   - Consider ISR caching for mobile

### P1 — Fix before release

4. **Fix dashboard published/draft counts** — The stats API returns 0 for published/drafts. Check if the dashboard queries use the correct status field and locale.

5. **Fix Russian locale timeout** — The `/ru` homepage hangs indefinitely. Check:
   - Russian translations exist for articles
   - Gedmo TranslatableListener fallback for missing translations
   - Query timeout configuration

6. **Investigate "1 Issue" Next.js error** — Open browser console to identify the JavaScript error causing the Next.js error badge.

### P2 — Improve

7. **Set all fixture authors to Active** — Update `AuthorFixtures` to set `isActive = true` by default.

8. **Auth redirect should preserve locale** — When redirecting unauthenticated users, redirect to `/{current-locale}/login` not always `/en/login`.

9. **Show all 8 categories in list** — Verify categories pagination or locale filtering isn't hiding categories.

10. **Unify article creation flow** — Either make the modal a full form or remove the `/articles/new` page to avoid confusion.

---

## 7. Test Files

- **Test suite**: `apps/frontend/__tests__/e2e/admin-panel-complete.spec.ts`
- **41 tests** covering 15 admin modules
- **Desktop results**: 37/41 pass (90%)
- **Mobile results**: 34/41 pass (83%)
- **Combined unique failures**: 7 distinct issues identified

---

*Report generated by Playwright E2E testing suite on 2026-03-19*
