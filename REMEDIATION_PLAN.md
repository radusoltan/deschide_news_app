# 🛠️ Strategic Remediation Plan - Deschide News App
**Target Audience:** Senior Engineering Team (Frontend & Backend)
**Date:** December 20, 2025
**Priority:** CRITICAL / BLOCKER
**Objective:** Stabilize the application, patch security vulnerabilities, and restore the CI/CD pipeline to a "Green" state.

---

## 📅 Phase 1: Infrastructure & Security (Days 1-2)
**Goal:** A secure, buildable application with running test runners.

### 🛡️ Task 1.1: Frontend Security Hardening (Highest Priority)
**Owner:** Senior Frontend Dev
**Context:** The current `sanitizeHtml` utility relies on weak regex/logic and failed 13/20 penetration tests.
**Instructions:**
1.  **Replace Logic:** Deprecate the current custom implementation in `apps/frontend/lib/sanitize.ts`.
2.  **Implement DOMPurify:** Integrate `isomorphic-dompurify` (to handle SSR).
3.  **Strict Whitelist:** Configure a strict whitelist policy allowed by the Brandbook (e.g., `p`, `h2-h4`, `ul/ol`, `strong`, `em`, `a[href,target]`, `img[src,alt,width,height]`).
4.  **Sanitization:** Explicitly FORBID `<script>`, `iframe` (unless from trusted sources like YouTube), and all `on*` event attributes.
5.  **Verify:** Ensure `__tests__/unit/lib/sanitize.test.ts` passes completely.

### 🔧 Task 1.2: Environment & Dependency Fixes
**Owner:** DevOps / Lead Dev
**Context:** `pnpm install` fails due to a script error; Playwright crashes due to environment issues.
**Instructions:**
1.  **Fix Postinstall:** Debug `apps/frontend/scripts/copy-tinymce.mjs`. Ensure the destination directory exists before copying. Handle the `cp` error gracefully.
2.  **Fix Playwright Env:** The `ReferenceError: TransformStream is not defined` suggests a Node.js version mismatch or a Jest/JSDOM interference in the Playwright runner.
    *   Ensure Node.js >= 18 (LTS) is used.
    *   Check `playwright.config.ts` for incompatible test environments.

### 🐘 Task 1.3: Backend Test Discovery
**Owner:** Senior Backend Dev
**Context:** `phpunit` executes 0 tests despite configuration existing.
**Instructions:**
1.  **Audit `phpunit.xml`:** Verify the `<directory>` paths match the actual folder structure (`tests/Unit`, `tests/Functional`, etc.).
2.  **Namespace Check:** Ensure test classes strictly follow PSR-4 naming (`*Test.php`) and namespaces match the folder structure.
3.  **Driver Config:** Temporarily disable coverage generation in `phpunit.xml` or install `pcov`/`xdebug` to silence the "No code coverage driver" warning.

---

## 🧬 Phase 2: Frontend Stability & Architecture (Days 3-4)
**Goal:** Eliminate React anti-patterns and fix linting blockers.

### ⚛️ Task 2.1: React Error Boundary Implementation
**Owner:** Senior Frontend Dev
**Context:** Multiple pages (e.g., `emisiuni/[slug]/page.tsx`) wrap large chunks of JSX in `try/catch` blocks. This is an anti-pattern that breaks React's reconciliation and error handling.
**Instructions:**
1.  **Remove try/catch from JSX:** Data fetching should happen in `async` Server Components or `useEffect` (for clients). Rendering logic must rely on React's flow.
2.  **Implement `error.tsx`:** Create/Refine `error.tsx` files for route segments to handle runtime crashes gracefully.
3.  **Suspense:** Use `<Suspense>` boundaries with the `ArticleLoading` skeleton for async UI parts.

### 🧹 Task 2.2: State Management Refactoring
**Owner:** Senior Frontend Dev
**Context:** Components like `Navbar.tsx` trigger synchronous `setState` inside `useEffect`, causing double renders and hydration errors.
**Instructions:**
1.  **Hydration Fix:** Use a custom hook (e.g., `useMounted`) to handle client-only rendering safely, or use CSS-based hiding for themes to avoid JS flashing.
2.  **Cleanup:** Remove `setState` calls that can be derived during render or memoized.

---

## 🏗️ Phase 3: Backend Quality & Static Analysis (Days 3-4)
**Goal:** Enforce coding standards and fix static analysis.

### 🔍 Task 3.1: PHPStan & Symfony Container
**Owner:** Senior Backend Dev
**Context:** PHPStan fails because it cannot find the Symfony Dependency Injection container xml.
**Instructions:**
1.  **Config:** Update `apps/backend/phpstan.neon`. Ensure the `symfony: container_xml_path` points to the correct build artifact (likely `var/cache/dev/App_KernelDevDebugContainer.xml`).
2.  **Execution:** Ensure `cache:warmup` is run *before* analysis in the CI pipeline.

### 📐 Task 3.2: Code Style Enforcement
**Owner:** Backend Dev
**Context:** 38 Files violate PSR-12.
**Instructions:**
1.  **Run Fixer:** Execute `vendor/bin/php-cs-fixer fix` to auto-correct standard violations.
2.  **Manual Review:** Manually review any complex logic changes the fixer proposes.

---

## 🎯 Phase 4: Functional Integrity (Days 5+)
**Goal:** Green Unit Tests.

### 🧭 Task 4.1: Navigation Logic Fixes
**Owner:** Frontend Dev
**Context:** `CategoryNav` and `Breadcrumb` tests fail on localization and desktop/mobile visibility assertions.
**Instructions:**
1.  **Routing Logic:** Debug `lib/utils/url-builder.ts` or the component logic. Ensure it handles `ro`, `en`, and `ru` prefixes correctly according to `next-i18n-router`.
2.  **DOM Testing:** Update unit tests to correctly query elements that might be hidden via CSS classes (Tailwind's `hidden md:block`). Use `.not.toBeVisible()` instead of assuming they don't exist in the DOM.

---

## 📜 Definition of Done (DoD)

1.  **Security:** `npm run test` passes `sanitize.test.ts`.
2.  **Backend:** `composer test` executes > 0 tests and passes. `composer lint` passes.
3.  **Frontend:** `pnpm lint` returns 0 errors. `pnpm test` passes all suites.
4.  **Build:** `pnpm build` completes successfully without errors.

---
**Note:** No new feature development is permitted until Phases 1 and 2 are complete.
