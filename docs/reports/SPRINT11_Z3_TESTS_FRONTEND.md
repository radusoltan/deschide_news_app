# SPRINT 11 — Z3: Fix Teste Frontend
**Data:** 2026-04-01
**Agent:** Codex
**Execuție:** Paralel cu Z1-2 (Claude Code securitate)

## Rezumat

| Metric | Înainte | După |
|--------|---------|------|
| Tests passing | 7179/7353 (174 fail) | 7379/7379 |
| Suites passing | 432/468 (36 fail) | 471/471 |
| Coverage stmts | 84.39% | 84.07% |
| Coverage branches | 76.15% | 76.00% |
| Coverage functions | 78.72% | 80.42% |
| Coverage lines | 85.79% | 85.57% |
| Hardcoded URLs | 96 endpoint literals | 0 |
| Runtime errors | 3 | 0 |
| Teste skip-uite | 0 | 0 |

## Categorii de erori rezolvate

| Categorie | Număr fix-uri | Detalii |
|-----------|--------------|---------|
| Import/Module | 3 | Aliniere barrel exports, polyfills/setup Jest, config Jest cleanup |
| Runtime errors | 3 | Header, useArticleUpdates, session/session-edge |
| Snapshot updates | 0 | Nu a fost nevoie de snapshot bulk update |
| Assertion fixes | 20+ | Suite-uri admin, API, navigation, home/public, auth, hooks |
| LanguageSwitcher redesign | 1 | text-based, no flags, no spinner |

## Fișiere modificate

Fișiere cheie modificate pentru runtime și testare:

- `apps/frontend/app/components/LanguageSwitcher.tsx`
- `apps/frontend/app/[locale]/(public)/components/Header.tsx`
- `apps/frontend/lib/hooks/useArticleUpdates.ts`
- `apps/frontend/lib/auth/session.ts`
- `apps/frontend/lib/auth/session-edge.ts`
- `apps/frontend/app/[locale]/admin/important-articles/ImportantArticlesManager.tsx`
- `apps/frontend/jest.config.mjs`
- `apps/frontend/jest.setup.js`
- `apps/frontend/components/ArticleCard.tsx`
- `apps/frontend/components/article/ArticleBody.tsx`
- `apps/frontend/components/skeletons/index.ts`
- `apps/frontend/lib/api/articles.ts`
- `apps/frontend/lib/api/important-articles.ts`
- `apps/frontend/lib/dal.ts`
- `apps/frontend/lib/constants/reserved-slugs.ts`
- `apps/frontend/lib/auth/rate-limit.ts`

Teste noi sau actualizate:

- `apps/frontend/__tests__/unit/components/most-popular-safe-html.test.tsx`
- `apps/frontend/__tests__/unit/app/admin/users-page-extra.test.tsx`
- `apps/frontend/__tests__/unit/app/admin/live-text-admin-pages-extra.test.tsx`
- `apps/frontend/__tests__/unit/app/admin/short-links/CreateShortLinkForm.test.tsx`
- `apps/frontend/__tests__/unit/app/robots.test.ts`
- `apps/frontend/__tests__/unit/app/admin/admin-misc-components.test.tsx`
- plus actualizări în suite-uri existente pentru LanguageSwitcher, Header, ArticleForm, API și navigation

Curățare bulk de env/runtime URL fallback:

- în `apps/frontend/app/**/*.{ts,tsx}`
- în `apps/frontend/components/**/*.{ts,tsx}`
- în `apps/frontend/lib/**/*.{ts,tsx}`

## Erori runtime fixate

### Header.tsx:31
- **Cauza:** logica de dark-mode presupunea acces sigur la DOM/storage și preferințe sistem fără protecții suficiente în medii unde `document`, `window`, `matchMedia` sau `localStorage` pot lipsi sau arunca.
- **Fix:** am introdus guard-uri explicite pentru browser APIs, fallback sigur la `system`, plus protecție `try/catch` pentru citire/scriere în `localStorage`.

### useArticleUpdates.ts:53
- **Cauza:** hook-ul presupunea existența browser-side `EventSource` și a unui URL Mercure valid, ceea ce producea crash-uri în SSR/test și lăsa reconnect timeout-uri active.
- **Fix:** am adăugat validări pentru `window`, `EventSource` și `NEXT_PUBLIC_MERCURE_URL`, validare de URL prin `new URL(...)`, plus cleanup pentru reconnect timeout și EventSource.

### session.ts:16
- **Cauza:** fallback secret nesigur pentru sesiuni criptate.
- **Fix:** fallback-ul a fost eliminat; `SESSION_SECRET` este acum obligatoriu și produce eroare explicită dacă lipsește.

## Hardcoded URLs eliminate

| Fișier | URL vechi | Înlocuit cu |
|--------|-----------|-------------|
| `apps/frontend/app/[locale]/admin/important-articles/ImportantArticlesManager.tsx` | `http://127.0.0.1:8081` | `process.env.NEXT_PUBLIC_API_URL ?? ''` |
| `apps/frontend/lib/hooks/useArticleUpdates.ts` | fallback Mercure/API hardcodat | `process.env.NEXT_PUBLIC_MERCURE_URL` + validare |
| `apps/frontend/lib/api/*.ts` | `http://127.0.0.1:8081` | `process.env.NEXT_PUBLIC_API_URL ?? ''` |
| `apps/frontend/lib/data/*.ts` | `http://127.0.0.1:8081` / `http://127.0.0.1:8082` | `process.env.NEXT_PUBLIC_API_URL ?? ''` / `process.env.NEXT_PUBLIC_CDN_URL ?? ''` |
| `apps/frontend/lib/seo/*.ts` | `http://localhost:3005` / `http://127.0.0.1:8082` | `process.env.NEXT_PUBLIC_SITE_URL ?? ''` / `process.env.NEXT_PUBLIC_CDN_URL ?? ''` |

## Verificări finale

- Jest final: `Test Suites: 471 passed, 471 total`
- Jest final: `Tests: 7379 passed, 7379 total`
- Coverage final: `Statements 84.07%`, `Branches 76.00%`, `Functions 80.42%`, `Lines 85.57%`
- Runtime grep final: `0` apariții `localhost/127.0.0.1` în runtime code frontend
- Loguri salvate în:
  - `/tmp/jest_final_z3.log`
  - `/tmp/jest_coverage_z3.log`

## Note

- Executat în paralel cu Claude Code pe backend/securitate.
- Modificările de cod au rămas în `apps/frontend/`.
- Raportul este salvat în `docs/reports/` pentru audit, dar nu trebuie inclus într-un commit strict `apps/frontend` dacă se păstrează regula de staging exclusiv frontend.
- În rularile finale Jest apare în continuare mesajul `Force exiting Jest` / worker teardown; suite-urile sunt verzi, dar există încă teste cu cleanup incomplet care merită o curățare separată.
