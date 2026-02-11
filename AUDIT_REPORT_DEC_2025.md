# Deschide News App - Comprehensive Audit Report
**Date:** December 20, 2025
**Auditor:** Gemini Code AI
**Scope:** Frontend (Next.js), Backend (Symfony), Design, & Security

---

## 1. Executive Summary

**Overall Status: 🔴 CRITICAL / PRE-ALPHA**

The application has a solid architectural foundation (Modern Monorepo, Next.js 16, Symfony 8), but the current implementation is **not production-ready**. 

- **Frontend:** Functionally unstable with critical security vulnerabilities (XSS), 75+ linting errors including React anti-patterns, and failing unit tests in core navigation components.
- **Backend:** Test infrastructure is broken (0 tests executed), and static analysis is misconfigured. Code style violations are present in 38 files.
- **Security:** **HIGH RISK.** The frontend sanitization logic fails to strip malicious scripts and event handlers.

---

## 2. Critical Issues (Must Fix Immediately)

### 🚨 Security Vulnerabilities (Frontend)
The `sanitizeHtml` utility failed **13 critical security tests**. It does **not** correctly strip:
- `<script>` tags
- Inline event handlers (`onclick`, `onerror`, `onload`, `onmouseover`)
- `javascript:` protocol links
- SVG embedded scripts

**Impact:** High potential for Cross-Site Scripting (XSS) attacks if user content is rendered.

### 🚨 Broken Development Workflow
- **Backend Tests:** `phpunit` executes **0 tests** due to configuration or discovery issues.
- **Frontend Build:** `pnpm install` fails on `postinstall` (TinyMCE copy script error).
- **E2E Testing:** Playwright fails to start (`TransformStream is not defined`), blocking all smoke and regression tests.

### 🚨 React Anti-Patterns
Linting revealed **75 errors**, mostly critical:
- **JSX in try/catch:** Found extensively in `emisiuni/[slug]/page.tsx`. This bypasses React's error boundaries, potentially causing white-screen crashes instead of graceful error handling.
- **setState in useEffect:** Found in `Navbar.tsx` and others. This causes unnecessary re-renders and performance degradation.

---

## 3. Frontend Audit (Next.js)

| Category | Status | Details |
| :--- | :--- | :--- |
| **Linting** | 🔴 FAILED | 75 Errors. Major issues in `emisiuni` pages and `ArticlesPageClient`. |
| **Unit Tests** | 🟠 MIXED | 225 Passed / **29 Failed**. Failures in `Sanitize` (Security), `CategoryNav` (Routing), `ArticleCard` (Rendering), `Breadcrumb`. |
| **E2E Tests** | 🔴 FAILED | Playwright environment is broken. |
| **Architecture** | 🟢 GOOD | "Data Access Layer" pattern and Server Components usage is evident and good. |

### Design & UX Verification
- **Configuration:** Tailwind config matches "Premium Media" Brandbook (Oxford Blue, Tomato, League Spartan, Poppins).
- **Implementation:** Tests reveal broken active states in `CategoryNav` (Mobile/Desktop mismatch) and localization issues in `Breadcrumb` (generating incorrect URLs).
- **Visuals:** Skeleton loaders are implemented (`ArticleLoading`), but `ArticleCard` tests failed on finding basic content, suggesting layout rendering bugs.

---

## 4. Backend Audit (Symfony)

| Category | Status | Details |
| :--- | :--- | :--- |
| **Static Analysis** | 🔴 FAILED | PHPStan cannot find the Symfony Container (`cache:warmup` didn't fix this for the tool). |
| **Code Style** | 🟠 WARN | 38 files violate PSR-12/Project standards (via PHP-CS-Fixer). |
| **Tests** | 🔴 FAILED | PHPUnit configuration exists but no tests are being discovered/executed. Coverage driver is missing. |
| **Health** | 🟢 GOOD | Cache clearing and warmup commands work (system is bootable). |

---

## 5. Recommendations & Roadmap

### Phase 1: Security & Stability (Immediate)
1.  **Fix Sanitization:** Rewrite `lib/sanitize.ts` to strictly whitelist tags/attributes using a robust library (e.g., `dompurify` properly configured). **Verify with tests.**
2.  **Fix Frontend Linting:** Refactor `emisiuni` pages to remove JSX from `try/catch` blocks. Use React Error Boundaries.
3.  **Enable Backend Tests:** Debug `phpunit.xml` to ensure tests are discovered and run. Fix the "No coverage driver" error (install Xdebug or PCOV, or ignore coverage temporarily).

### Phase 2: Core Functionality (Week 1)
1.  **Fix Navigation Logic:** Repair `CategoryNav` and `Breadcrumb` components to pass all unit tests (especially localization).
2.  **Fix Dev Environment:** Repair `apps/frontend/scripts/copy-tinymce.mjs` to allow smooth `pnpm install`.
3.  **Fix Playwright:** Update Node.js or Playwright config to support `TransformStream`.

### Phase 3: Quality Assurance (Week 2)
1.  **Backend Code Style:** Run `php-cs-fixer fix` to clean up the 38 files.
2.  **Backend Analysis:** Fix `phpstan.neon` to point to the correct container XML path.
3.  **Visual Regression:** Once Playwright is fixed, run visual tests to verify the "Premium Media" design implementation.

---
**Audit Conclusion:** The project requires a "Stop and Fix" sprint. No new features should be added until the security hole is plugged and the test suite is green.
