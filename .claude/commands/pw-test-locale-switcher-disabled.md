# Playwright Manual Testing: Language Switcher Disabled-State (ADR-028)

> **Release:** v1.5.0  (regression guard for v1.5.0 sign-off; original implementation shipped in Sprint 59 / v1.4.1)
> **ADR:** [ADR-028 — Unified Locale URL Builder](../../apps/frontend/docs/adr/ADR-028-unified-locale-url-builder.md)  (D4 Disabled-state policy)
> **Source task:** T59.1 — `feature/sprint-59-i18n-switcher`
> **Notion tracking task:** [TSK-692](https://app.notion.com/p/3534b6d1296e81579bcacfc76ff61173) (E2E: Verify language switcher disables when target locale not published)

## Scenario: Verify language switcher disables when target locale not published

The `LanguageSwitcher` (`apps/frontend/app/components/LanguageSwitcher.tsx`) must:

1. Render the active locale as a styled (current) link.
2. Render any locale that is published on the resource as an enabled `<a>` whose href is built from `translatedSlugs` (NOT a naive prefix swap).
3. Render any locale that is **not** in `publishedLocales` (or for which `translatedSlugs[locale]` is missing) as a disabled `<span role="link" aria-disabled="true">` carrying a tooltip from `intl.formatMessage({ id: 'languageSwitcher.notTranslated' })`.

The test exercises (3) end-to-end on a real article that has only the RO translation published, then cross-checks against an article published in all three locales to prove the switcher is not globally broken.

### Priority

**P1 - High**  (blocks v1.5.0 sign-off; SEO + UX correctness; ADR-028 D3+D4 enforcement)

### Prerequisites

- **Servers running:**
  - Backend on `http://127.0.0.1:8081` (verify: `curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8081/api`).
  - Frontend on `http://localhost:3005` (started via `pnpm dev` in `apps/frontend`, NEVER PM2).
  - CDN on `http://127.0.0.1:8082` (only needed if asserting hero images render).
- **Dev DB fixtures (stable IDs from `__tests__/e2e/sprint-59-i18n.spec.ts`):**
  - **3-locale article** — id `100`, category `politica`:
    - `ro`: `criza-politica-de-la-bucuresti-fara-solutii-dupa-consultarile-convocate-de-presedinte`
    - `en`: `political-crisis-in-bucharest-no-solutions-after-consultations-convened-by-the-president`
    - `ru`: `politicheskij-krizis-v-buhareste-bez-reshenij-posle-consultacij-prezidenta`
  - **1-locale article** — id `103`, category `politica`:
    - `ro`: `premierul-alexandru-munteanu-in-dialog-cu-presedintele-comitetului-economic-si-social-european-1`
- **If fixtures are missing** (running against a freshly-reset DB):
  ```bash
  cd /var/www/deschide_news_app/apps/backend
  symfony console app:dev:reset                       # reseed dev DB
  # If a previously trilingual article is needed in 1-locale state:
  symfony console app:dev:demote-translation --article=103 --locale=en
  symfony console app:dev:demote-translation --article=103 --locale=ru
  # Sanity check the resulting publishedLocales for article 103:
  curl -s "http://127.0.0.1:8081/api/articles/103" -H "Accept-Language: ro" \
    | jq '{ id, publishedLocales, translatedSlugs }'
  # Expected: publishedLocales == ["ro"], translatedSlugs has only "ro"
  ```
- **Cookie hygiene** — before Step 1, clear any existing `NEXT_LOCALE` cookie so Step 5's cookie-state assertion is unambiguous:
  ```js
  // mcp__playwright__browser_evaluate
  () => { document.cookie = 'NEXT_LOCALE=; path=/; max-age=0'; return document.cookie; }
  ```
  (Per Memory note: `NEXT_LOCALE` is owned jointly by `proxy.ts:withLocaleCookie()` and `LanguageSwitcher:setLocaleCookie()` — there is NO library auto-sync. The disabled `<span>` has no `onClick`, so it must not write the cookie.)
- **Login NOT required** — switcher is on public pages.

### Test Environment

- **Frontend Base**: `http://localhost:3005`
- **Article URLs under test**:
  - `/ro/politica/{slug-ro}` for both fixtures
  - Direct deep-link probes: `/en/politica/{slug-ro-of-1-locale-article}`
- **Locales**: All three (ro / en / ru) — switching from each starting locale
- **Viewport**: 1280×800 (Desktop). Re-run at 375×667 (Mobile) for the keyboard-a11y step.
- **Selectors (verified against `LanguageSwitcher.tsx` lines 125–155):**
  - Enabled link: `[data-testid="locale-switch-{ro|en|ru}"]` — rendered as `<a>` with `href`, `aria-label`, optional `aria-current="true"`.
  - Disabled span: `[data-testid="locale-switch-{ro|en|ru}-disabled"]` — rendered as `<span role="link" aria-disabled="true" title="…">`, classes include `cursor-not-allowed opacity-40`.
  - The `<a>` and the `-disabled` `<span>` are **mutually exclusive** for a given locale.

---

### Test Steps

#### Step 1: Open the 1-locale article (RO-only) on its RO URL

**Action**: Navigate the browser to the RO-only article in the RO locale.
**Tool**: `mcp__playwright__browser_navigate`
**Parameters**:
```json
{
  "url": "http://localhost:3005/ro/politica/premierul-alexandru-munteanu-in-dialog-cu-presedintele-comitetului-economic-si-social-european-1"
}
```
**Expected Result**: HTTP 200, article body rendered (h1 visible, NOT the 404 placeholder).
**Verification**:
- [ ] Use `mcp__playwright__browser_snapshot` and confirm an `<h1>` containing "Premierul Alexandru Munteanu…" is present.
- [ ] If the snapshot returns the 404 fallback, **STOP** — record this as a blocker (article SSR is broken in dev env, see "Known Issues" #1) and move to Step 8 (Cross-check) directly.

#### Step 2: Confirm RO is the current/active locale

**Action**: Read the snapshot for the RO switcher entry.
**Tool**: `mcp__playwright__browser_snapshot`
**Parameters**: `{}`
**Expected Result**:
- An element with `data-testid="locale-switch-ro"` exists.
- It carries `aria-current="true"` and the underline class (`underline-offset-4`).
- `[data-testid="locale-switch-ro-disabled"]` is **absent**.
**Verification**:
- [ ] RO renders as enabled link with `aria-current="true"`.
- [ ] RO `href` matches `/ro/politica/{ro-slug}` (current page) — `mcp__playwright__browser_evaluate` with `() => document.querySelector('[data-testid="locale-switch-ro"]')?.getAttribute('href')`.

#### Step 3: Confirm EN renders as disabled  *(ASSERTION GROUP A — visual / aria)*

**Action**: Probe the EN switcher entry.
**Tool**: `mcp__playwright__browser_evaluate`
**Parameters**:
```json
{
  "function": "() => { const en = document.querySelector('[data-testid=\"locale-switch-en-disabled\"]'); return en ? { ariaDisabled: en.getAttribute('aria-disabled'), title: en.getAttribute('title'), text: en.textContent, role: en.getAttribute('role'), classes: en.className } : null; }"
}
```
**Expected Result**: The returned object is non-null with:
```json
{
  "ariaDisabled": "true",
  "title": "Articolul nu este tradus în această limbă",
  "text": "English",
  "role": "link",
  "classes": "<contains 'cursor-not-allowed' and 'opacity-40'>"
}
```
**Verification**:
- [ ] `aria-disabled="true"` present.
- [ ] `title` attribute equals the RO i18n message (`messages/ro.json:12 → "languageSwitcher.notTranslated"`).
- [ ] No `[data-testid="locale-switch-en"]` (active variant) exists in the DOM — assert via a second `browser_evaluate` returning `document.querySelectorAll('[data-testid="locale-switch-en"]').length === 0`.
- [ ] The visible label is "English" (the `Locale.label` in `LOCALE_OPTIONS`).
- [ ] No `href` attribute (disabled `<span>` is non-navigable by construction).

#### Step 4: Confirm RU renders as disabled  *(ASSERTION GROUP A continued)*

**Action**: Same probe as Step 3 but for `ru`.
**Tool**: `mcp__playwright__browser_evaluate`
**Parameters**:
```json
{
  "function": "() => { const ru = document.querySelector('[data-testid=\"locale-switch-ru-disabled\"]'); return ru ? { ariaDisabled: ru.getAttribute('aria-disabled'), title: ru.getAttribute('title'), text: ru.textContent, role: ru.getAttribute('role') } : null; }"
}
```
**Expected Result**:
- `ariaDisabled === "true"`, `title === "Articolul nu este tradus în această limbă"`, `text === "Русский"`, `role === "link"`.
**Verification**:
- [ ] RU disabled span present and labelled correctly.
- [ ] Active `[data-testid="locale-switch-ru"]` is absent.

#### Step 5: Verify clicking the disabled locale does NOT navigate AND does NOT write the cookie  *(ASSERTION GROUPS B — behavior / no nav AND C — state / cookie)*

**Action**: Click the EN disabled span and assert (a) URL is unchanged, (b) no `pageerror`, (c) `NEXT_LOCALE` cookie is unchanged from its pre-click value.

**Sub-step 5a — capture pre-click cookie state:**
**Tool**: `mcp__playwright__browser_evaluate`
**Parameters**:
```json
{
  "function": "() => document.cookie.split('; ').find(c => c.startsWith('NEXT_LOCALE=')) ?? null"
}
```
**Expected Result**: Either `null` (cookie cleared in Prerequisites) or `"NEXT_LOCALE=ro"` (set by `proxy.ts:withLocaleCookie()` on the SSR pass for `/ro/...`). Record the value.

**Sub-step 5b — click the disabled span:**
**Tool**: `mcp__playwright__browser_click`
**Parameters**:
```json
{
  "element": "EN locale switch (disabled span)",
  "ref": "[data-testid=\"locale-switch-en-disabled\"]"
}
```
**Expected Result**: The click resolves (the `<span>` is interactive only via tooltip), URL stays at `/ro/politica/{ro-slug}`. No navigation.

**Sub-step 5c — assertions:**
**Tool**: `mcp__playwright__browser_evaluate`
**Parameters**:
```json
{
  "function": "() => ({ pathname: location.pathname, cookie: document.cookie.split('; ').find(c => c.startsWith('NEXT_LOCALE=')) ?? null })"
}
```
**Verification (Group B — behavior):**
- [ ] `pathname` still ends with the RO slug (no navigation).
- [ ] `mcp__playwright__browser_console_messages` returns NO `pageerror` introduced by the click. (ADR-028 D4 invariant: "Zero click-uri care ajung 404".)

**Verification (Group C — state):**
- [ ] `cookie` value returned from sub-step 5c equals the value captured in 5a (NOT `"NEXT_LOCALE=en"`). The disabled `<span>` has no `onClick` handler — only the active `<Link>` calls `setLocaleCookie()` (`LanguageSwitcher.tsx:141`). Any cookie write here is a regression of ADR-028 D4.
- [ ] If pre-click cookie was `null`, post-click cookie must also be `null` (or absent of `NEXT_LOCALE=...`).

#### Step 6: Verify hover tooltip appears

**Action**: Hover the disabled EN span; the native `title` attribute is the tooltip mechanism (no JS tooltip lib, per ADR-028 D4 last paragraph).
**Tool**: `mcp__playwright__browser_hover`
**Parameters**:
```json
{
  "element": "EN locale switch (disabled span)",
  "ref": "[data-testid=\"locale-switch-en-disabled\"]"
}
```
**Expected Result**: Browser native title-tooltip behaviour. The `title` attribute is asserted in Step 3; this step confirms no JS error on hover and (optionally) takes a screenshot for visual evidence.
**Verification**:
- [ ] `mcp__playwright__browser_take_screenshot` with `{ "filename": "locale-switcher-disabled-en-hover.png" }` — captures DOM state (native title-tooltip rendering is browser-driven and not always in the screenshot, but the visual styling — `opacity-40`, `cursor-not-allowed` — must be visible).

#### Step 7: Direct-URL probe for the unpublished EN translation (locale-gate, ADR-027)

**Action**: Navigate directly to the EN-prefixed URL of the RO-only article. Per ADR-027 the backend must NOT silently render RO content under `/en/`.
**Tool**: `mcp__playwright__browser_navigate`
**Parameters**:
```json
{
  "url": "http://localhost:3005/en/politica/premierul-alexandru-munteanu-in-dialog-cu-presedintele-comitetului-economic-si-social-european-1"
}
```
**Expected Result**: HTTP 404 (or a redirect to a 404 page — both are acceptable per ADR-027). The page must NOT show the RO article body.
**Verification**:
- [ ] Use `mcp__playwright__browser_network_request` (or capture the response from `browser_navigate`) and assert `status === 404`.
- [ ] Snapshot does not contain the Romanian title text "Premierul Alexandru Munteanu…".
- [ ] If a 200 with RO body is observed, log this as a regression of ADR-027 — separate bug, NOT a switcher issue.

#### Step 8: Cross-check on the 3-locale article (full-enabled switcher)  *(NEGATIVE-PATH SANITY)*

**Action**: Navigate to the trilingual article's RO URL and confirm all three switcher entries are enabled. This is the **negative-path coverage** required by the v1.5.0 task — the same component must not over-disable; when the resource IS published in EN/RU, the toggle works in both directions.
**Tool**: `mcp__playwright__browser_navigate`
**Parameters**:
```json
{
  "url": "http://localhost:3005/ro/politica/criza-politica-de-la-bucuresti-fara-solutii-dupa-consultarile-convocate-de-presedinte"
}
```
**Expected Result**: 200 OK, article body visible. (Skip the cross-check if Step 1 hit the article-route blocker — Jest unit test `__tests__/unit/components/LanguageSwitcher.test.tsx` covers the happy path.)
**Verification**:
- [ ] `mcp__playwright__browser_evaluate` with:
      `() => ({ enHref: document.querySelector('[data-testid="locale-switch-en"]')?.getAttribute('href'), ruHref: document.querySelector('[data-testid="locale-switch-ru"]')?.getAttribute('href'), enDisabled: !!document.querySelector('[data-testid="locale-switch-en-disabled"]'), ruDisabled: !!document.querySelector('[data-testid="locale-switch-ru-disabled"]') })`
- [ ] `enDisabled === false`, `ruDisabled === false`.
- [ ] `enHref` ends with `/en/politica/political-crisis-in-bucharest-no-solutions-after-consultations-convened-by-the-president`.
- [ ] `ruHref` ends with `/ru/politica/politicheskij-krizis-v-buhareste-bez-reshenij-posle-consultacij-prezidenta`.
- [ ] Distinct slugs (no naive prefix swap) — the three href paths are pairwise different.
- [ ] **Bidirectional toggle check** — click the EN link, confirm navigation to `/en/...`, and now `NEXT_LOCALE=en` cookie IS set (proves the active link path correctly invokes `setLocaleCookie`, contrast with Step 5c). Then click RO to navigate back; cookie becomes `NEXT_LOCALE=ro`.

#### Step 9: SSR raw-HTML probe for crawlers (no JS hydration)

**Action**: Verify the disabled state is rendered server-side, not client-only — Google sees the same DOM as the user.
**Tool**: `mcp__playwright__browser_navigate` (treat the response body as HTML; alternatively use `curl` outside Playwright).
**Parameters**:
```json
{
  "url": "http://localhost:3005/ro/politica/premierul-alexandru-munteanu-in-dialog-cu-presedintele-comitetului-economic-si-social-european-1"
}
```
**Expected Result**: The raw HTML response body (before client hydration) contains `data-testid="locale-switch-en-disabled"` and `data-testid="locale-switch-ru-disabled"` — both as `<span>`, both with `aria-disabled="true"`. This confirms `LocaleContextProvider` was primed by `apps/frontend/lib/server/resolve-locale-context-data.ts:108` on the SSR pass (ADR-029 invariant).
**Verification**:
- [ ] Inspect the page HTML via `mcp__playwright__browser_evaluate` with `() => document.documentElement.outerHTML.includes('locale-switch-en-disabled')` BEFORE any user interaction — expect `true`.

#### Step 10: Keyboard / a11y — disabled options are skipped by Tab

**Action**: Tab through the header on the RO-only article and verify focus order skips disabled spans.
**Tool**: `mcp__playwright__browser_press_key`
**Parameters**:
```json
{
  "key": "Tab"
}
```
**Expected Result**: After repeated Tabs, focus reaches the active RO link (or a sibling nav element) but never lands on the EN/RU disabled spans (they are non-focusable: no `href` and no `tabindex`).
**Verification**:
- [ ] Use `mcp__playwright__browser_evaluate` with `() => document.activeElement?.getAttribute('data-testid')` after each Tab and confirm the disabled testids never appear.
- [ ] Screen-reader announcement: in NVDA/VoiceOver these `<span role="link" aria-disabled="true">` nodes are read as "English, dimmed link" / "Русский, dimmed link" — verify in a manual a11y pass (out of scope for automated MCP run, but called out as P3 manual test).

---

### Assertion Summary (mapped to test groups)

| Group | What it proves | Steps |
|-------|----------------|-------|
| **A — Visual / aria** | Disabled span has correct `role`, `aria-disabled`, `title`, label, classes; active variant absent | 3, 4, 9 (SSR variant) |
| **B — Behavior / no navigation** | Click on disabled span does not navigate and does not raise errors | 5b, 5c (pathname check), 7 (404 gate) |
| **C — State / cookie not written** | `NEXT_LOCALE` cookie is unchanged after click on disabled span | 5a, 5c (cookie check) |
| **Negative path / sanity** | When resource IS published in EN/RU, switcher is enabled and bidirectional toggle works (cookie IS written) | 8 |
| **Crawler parity** | Disabled state visible to crawlers in raw SSR HTML | 9 |
| **A11y** | Disabled spans not in tab order | 10 |

### Edge Cases

1. **Article published in all three locales** — covered by Step 8 cross-check; switcher must be fully enabled.
2. **Article published in 2 locales (ro+en, no ru)** — no fixture exists in dev DB (`__tests__/e2e/sprint-59-i18n.spec.ts` lines 156-163 explicitly skips this). Recreate by running `app:dev:demote-translation --article=100 --locale=ru` for the cross-check; expect EN enabled, RU disabled.
3. **Stale state — article unpublished after page load**: load `/ro/{slug}` while all three locales are published, then run `app:dev:demote-translation --article=100 --locale=en` in a separate shell. The current switcher snapshot is now stale; ADR-028 does **not** mandate live-invalidation — a hard reload is acceptable. Verify a fresh navigation re-renders EN as disabled.
4. **Partial `translatedSlugs` (broken backend payload)** — `LanguageSwitcher.tsx:117–118`: when `publishedLocales` claims a locale but `translatedSlugs[locale]` is missing, `computeHref()` returns `null` → `missingHref === true` → `isDisabled === true`. Covered by `__tests__/e2e/sprint-59-i18n.spec.ts:425–490`. Reproduce manually with `mcp__playwright__browser_evaluate` route-mocking only when the dev env article route is healthy.
5. **Locale cookie consistency** — `setLocaleCookie()` (line 38 of `LanguageSwitcher.tsx`) is only called from the active link's `onClick` (line 141). Disabled span has no click handler, so cookie remains untouched. Promoted to first-class assertion in Step 5c.
6. **Direct deep-link to `/en/{slug-of-1-locale-article}`** — covered in Step 7. Acceptance: 404, no silent RO content.
7. **Mobile viewport (375×667)** — re-run Steps 1–4. Touch targets on the disabled span are still ≥ 44px (the underlying span has padding `px-1.5 py-1` plus the surrounding flex-item; verify via the snapshot's bounding box).
8. **Locale switching while on EN page of trilingual article** — start at `/en/{slug-en}`, click RO switch → lands on `/ro/{slug-ro}`. Validates ADR-028 D1 (locale-aware, NOT prefix swap).
9. **Tooltip language matches `currentLocale`, NOT `targetLocale`** — fallback map at `LanguageSwitcher.tsx:26–30` is keyed by `currentLocale`. So on `/ro/...` BOTH disabled buttons (EN and RU) show the Romanian tooltip. Re-run the test on `/ru/...` of a RU-only article (if you can stage one) and assert the Russian tooltip from `messages/ru.json:12`. Intentional per ADR-028 D4.

### Cleanup / Teardown

- **No DB writes** are performed by this scenario (read-only HTTP traffic). No teardown needed for backend state.
- **Browser cookies** — if Step 8's bidirectional toggle ran, `NEXT_LOCALE` is now set to whatever locale the test left the user on. Reset with:
  ```js
  () => { document.cookie = 'NEXT_LOCALE=; path=/; max-age=0'; }
  ```
- **If `app:dev:demote-translation` was run in Prerequisites** to create the 1-locale article, restore via `app:dev:reset` OR re-publish manually:
  ```bash
  symfony console app:translate:articles 103 --force   # regenerates EN+RU translations
  ```
- **Screenshot artifact** from Step 6 (`locale-switcher-disabled-en-hover.png`) — attach to the test report; safe to delete after.

### Known Issues

1. **Article SSR 404 in some dev environments** — `__tests__/e2e/sprint-59-i18n.spec.ts:60-73` (`articleRouteWorks` probe) was added precisely because the article route can return `/_not-found` in dev despite the URL being valid. If Step 1 hits this, the scenario falls back to the SSR raw-HTML probe (Step 9) and the cross-check on the 3-locale article (Step 8). Out-of-scope to debug here; track against the article-route owner.
2. **Sitemap hreflang bug** — `apps/frontend/lib/api/sitemap-data.ts:106-125` emits the RO slug for EN/RU `<xhtml:link hreflang>` (T60.1, deferred from Sprint 59). If a v1.5.0 reviewer also asserts sitemap correctness, cross-link to T60.1 — the switcher itself is unaffected because it consumes `translatedSlugs` directly (ADR-028 D1).
3. **Tooltip behaviour relies on the browser's native `title` rendering** — there is no Tippy/Floating-UI dependency (ADR-028 D4). On some Linux desktop browsers the tooltip shows after a 500–1500 ms hover delay; do not flake-fail on its visibility, only on the `title` attribute value.
4. **`react-intl` wiring** — fallback message tree (`UNAVAILABLE_TOOLTIP_FALLBACK` in `LanguageSwitcher.tsx:26-30`) is keyed by `currentLocale`, NOT `targetLocale`. So on `/ro/...` the tooltip is in Romanian for both EN and RU disabled buttons. This is intentional (ADR-028 D4: tooltip language matches page language), but a regression check on a `/ru/...` page should expect the Russian tooltip text from `messages/ru.json:12`. (Per Memory note: frontend uses `react-intl`, NOT `next-intl`; cookie sync is manual via `proxy.ts:withLocaleCookie()` + `LanguageSwitcher:setLocaleCookie()`, no library auto-sync.)
5. **Forward-looking note for v1.5.0** — the disabled-state behaviour is **already implemented** as of T59.1 (Sprint 59 / v1.4.1 / `feature/sprint-59-i18n-switcher`). v1.5.0 carries this forward; this scenario doubles as a regression guard. If v1.5.0 introduces editorial flow that toggles `publishedLocales` at runtime (e.g., scheduled un-publish), revisit Edge Case #3 for live-invalidation requirements and amend ADR-028 accordingly.

### Success Criteria

- [ ] On a RO-only article, EN and RU each render as `<span data-testid="locale-switch-{en|ru}-disabled" role="link" aria-disabled="true" title="…">` (Group A).
- [ ] No active `<a data-testid="locale-switch-en">` or `<a data-testid="locale-switch-ru">` exists for the RO-only article (Group A).
- [ ] Clicking the disabled span does not navigate, does not raise console errors (Group B).
- [ ] Clicking the disabled span does **not** set or change `NEXT_LOCALE` cookie (Group C).
- [ ] Tooltip text is the locale-correct rendering of `languageSwitcher.notTranslated` (RO/EN/RU per `currentLocale`).
- [ ] On the trilingual article, all three switcher entries are enabled, hrefs are pairwise distinct, EN/RU hrefs match the translated slugs from `translatedSlugs`, and clicking the active link DOES write the `NEXT_LOCALE` cookie (negative-path sanity).
- [ ] Direct deep-link to an unpublished EN translation returns HTTP 404 (ADR-027 locale gate).
- [ ] Disabled state is rendered server-side (visible in raw HTML, before hydration) — confirms `resolve-locale-context-data.ts` SSR priming.
- [ ] Keyboard Tab focus order skips disabled spans (no `tabindex`, no `href`).
- [ ] Zero uncaught JS errors throughout the run (`mcp__playwright__browser_console_messages` returns nothing of severity `error` introduced by the switcher).

### Related Scenarios

- `__tests__/e2e/sprint-59-i18n.spec.ts` — automated suite covering the same disabled-state assertions (Test 2: "1-locale article: EN and RU rendered as disabled with tooltip"), happy path (Test 1: trilingual switch), ADR-027 locale gate (Test 5), and graceful degradation (Test 10).
- `__tests__/e2e/locale-switcher-ssr.spec.ts` — SSR raw-HTML invariants (T60.15 / ADR-029).
- `__tests__/unit/components/LanguageSwitcher.test.tsx` — disabled-branch unit coverage (independent of dev-env article-route health).
- `__tests__/unit/lib/seo/locale-url.test.ts` — 19 unit tests on `buildLocaleUrlForArticle` returning `null` for missing translations (the data path that triggers the switcher's disabled fallback).

---

## Handoff

When this scenario is executed (by `@frontend-e2e-tester`, `@manual-frontend-tester`, or `@multilanguage-tester`):

1. Record actual `aria-disabled`, `title`, and `data-testid` values observed in the snapshot.
2. Capture the screenshot from Step 6.
3. Record the cookie comparison from Step 5a vs 5c verbatim.
4. If Step 1 hits the article-route 404 blocker, jump to Steps 8 + 9 and note the blocker in the test report.
5. Cross-link the report to ADR-028 and the parent v1.5.0 sprint page (to be created) and to Notion task TSK-692.
