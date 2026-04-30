# ADR-030: Server-Side Locale Context Priming + Hreflang Metadata Fix

**Date:** 2026-04-30
**Status:** Accepted
**Deciders:** Radu (orchestrator), Claude Code (implementation)
**Tags:** #i18n #seo #ssr #language-switcher #hreflang
**Numbering note:** Originally planned as ADR-029 per orchestrator instruction.
At write time, ADR-029 was already taken by the
`category-slug-i18n-backfill` ADR (2026-04-29). Allocated next free number
ADR-030 — D17 deviation.

## Context

### The bug-of-record (T60.15)

The shared `LanguageSwitcher` component renders RO/EN/RU buttons in the
header on every public page. On article and category pages it must emit
the canonical translated URL per locale (e.g. `/en/society/article-en` for
the EN button, not `/en/societate/article-ro`).

Render-tree analysis confirmed the SSR render path:

```
app/layout.tsx
  → app/[locale]/layout.tsx (server, ServerIntlProvider)
    → app/[locale]/(public)/layout.tsx (server, fetches categories+menu)
      → ClientLayoutWrapper.tsx ('use client')
        → <LocaleContextProvider>     ← SEEDED WITH INITIAL_DATA, NO PROP
          → <Header /> → <LanguageSwitcher />  ← reads ctx via useContext
          → {children}
            → page.tsx (article/category/etc.)
              → <LocaleContextSetter ... />     ← useEffect, fires AFTER hydration
```

Provider renders before children. SSR pass of `<LanguageSwitcher>` saw
`INITIAL_DATA.context='generic'` + undefined slugs → fell through to
`buildLocaleUrlGeneric` (naive prefix-swap) → emitted non-canonical hrefs
in raw HTML. `LocaleContextSetter` fires in `useEffect` (post-hydration
only), too late for SSR.

The bug surfaced for:
- SEO crawlers reading server-rendered HTML
- Telegram Instant View, Facebook OG image scraping, RSS readers
- Copy-link sharing of switcher URLs (right-click → copy link)
- Any tool reading the SSR pass directly without executing client JS

### Phase 1.5 expansion: hreflang head metadata

Phase 2 e2e testing revealed `locale-switching.spec.ts:538` started failing:
`category page: <head> hreflang="en" === LanguageSwitcher EN href`. The test
was passing on baseline because BOTH head and switcher were equally wrong
(`/en/politica` for both). Once the switcher fix landed, the test correctly
exposed a pre-existing bug in `generateCategoryMetadata` — it called
`buildHreflangAlternates(SITE_URL, categorySlug)` which uses the same slug
for all locales (naive prefix-swap), producing `/ru/society` instead of
canonical `/ru/obshchestvo`.

Same bug class. Single ADR covers both.

## Decision

**Option A1 + Phase 1.5 expansion:**

1. **Provider initialData**: `LocaleContextProvider` accepts an optional
   `initialData?: LocaleContextData` prop. When provided, `useState`
   initializes with it; otherwise falls back to `INITIAL_DATA`. A
   shallow-equal short-circuit in `setLocaleContext` and `resetLocaleContext`
   prevents no-op state updates when the page-level setter useEffect fires
   with payload identical to the SSR-primed value.

2. **Server resolver** (`lib/server/resolve-locale-context-data.ts`):
   server-only async helper. Parses pathname into segments, dispatches to
   the appropriate API fetch (article via `lookupArticle`, category via
   `fetchCategories`, topic via `fetchTopicBySlug`, tag via `fetchTags`),
   returns `LocaleContextData` shape matching `INITIAL_DATA`. Branches in
   most-specific-first order to handle the greedy
   `/[locale]/<categorySlug>/<articleSlug>` route correctly. Fail-soft on
   any fetch error. Static slug fast-path (D4) skips category lookup for
   known-static one-segment paths.

3. **Pathname propagation**: `proxy.ts` injects `x-pathname` request header
   via `NextResponse.next({ request: { headers: ... } })`. The `(public)`
   layout reads it via `headers()` from `next/headers` and passes it to the
   resolver. React 19 fetch memoization dedupes the resolver fetch with the
   page's own fetch (verified ≤ 1 upstream HTTP call via Symfony access log
   tail in e2e tests).

4. **Layout wiring**: `(public)/layout.tsx` calls the resolver in parallel
   with the existing `fetchCategories` + `fetchPublicMenuItems`, threads the
   resolved `initialLocaleData` through `ClientLayoutWrapper` into
   `LocaleContextProvider`.

5. **Page setters**: `LocaleContextSetter` retained in article + category +
   topic + tag pages — needed for client-side navigation between
   same-route-type pages (where the layout doesn't re-mount and the
   resolver doesn't re-run). Author + static + homepage skip the setter
   because their slugs are shared across locales (naive prefix-swap is
   already canonical — D1 deviation).

6. **LanguageSwitcher** branches extended to handle `'topic'` and `'tag'`
   contexts, calling new `buildLocaleUrlForTopic` and `buildLocaleUrlForTag`
   helpers in `lib/seo/locale-url.ts`. Both follow the same pattern as
   `buildLocaleUrlForArticle` (return `null` when target locale lacks a
   translated slug → switcher renders disabled).

7. **Hreflang metadata fix (Phase 1.5)**: new
   `buildHreflangAlternatesForResource(baseUrl, translatedPaths)` helper in
   `lib/seo/locale-url.ts` emits per-locale URLs and SKIPS locales without a
   translated slug (matches Google hreflang spec — no alternate URLs
   pointing at 404s). `generateCategoryMetadata` accepts an optional
   `translatedSlugs?` param and uses the new helper when provided;
   backward-compat fallback to old behavior retained for callers without
   translation data. Category page caller updated to pass
   `category.translatedSlugs`. Article + author + static + topic + tag
   metadata generators were already correct (article uses
   `buildAlternateUrls` fan-out; the others use shared slugs).

## Considered and rejected

- **A2 — route-group split:** require article/category/topic/tag pages
  under a separate route group with its own layout that wraps Provider.
  Rejected: explosion of route-group nesting, worse mental model than a
  single resolver seam.

- **A3 — server LanguageSwitcher:** make `LanguageSwitcher` a server
  component that fetches its own data per request. Rejected: switcher is
  click-interactive (uses `useRouter` + cookie writes), can't be server-only
  without adding a client wrapper that re-introduces the same problem.

- **B — per-page switcher prop drilling:** pass switcher props from each
  page through the wrapper. Rejected: 6+ pages × per-route prop shapes,
  Header would need a discriminated-union prop type.

- **C — relax the failing test:** comparing canonical paths (Phase 1.5
  trigger) instead of exact strings. Rejected: would mask the underlying
  hreflang bug; both the head AND the switcher should point at canonical
  per-locale URLs.

## Consequences

### Positive

- SSR raw HTML for article + category pages now emits canonical translated
  hreflang URLs in switcher buttons (verified by e2e + curl smoke tests).
- `<head>` hreflang for category pages now uses translated per-locale slugs.
- Architecture extends cleanly — adding a new public route type means
  adding one resolver branch + one builder + one switcher case.
- Single seam for SSR locale priming reduces chance of future drift.
- Backward-compat retained: every modified function has a no-arg path.

### Neutral / explicit non-issues

- **D13 RO △ between switcher and head hreflang is intentional, not a bug.**
  - Switcher applies `i18nConfig.prefixDefault: false` → RO has no `/ro`
    prefix in the URL (e.g. `/societate`).
  - Head hreflang follows Google spec, emits absolute canonical URLs with
    `/ro` prefix (e.g. `/ro/societate`).
  - `proxy.ts` normalizes `/X` → `/ro/X` for default-locale requests.
  - Both URLs resolve to the same canonical resource.
  - Pre-existing convention. NOT a follow-up task. Documented in
    `lib/seo/locale-url.ts` JSDoc and e2e spec comments.

- **D15 Topic + tag e2e SSR consistency tests deferred.** Current dev
  fixture has 0/164 topics and 0/2000 tags with `translatedSlugs.en|ru`.
  Functionality fully covered by 31+ unit tests across resolver, URL
  builders, hreflang helper, and LanguageSwitcher contexts. E2E case
  becomes trivial once fixtures grow.

- `LocaleContextSetter` is now somewhat redundant on the article and
  category pages for the SSR-only first load (resolver primes already), but
  retained because client-side navigation between same-route-type pages
  doesn't re-run the layout-level resolver. Could be evaluated for cleanup
  in a future iteration but no urgency.

### Negative

- One additional async call in the layout (resolver) — but parallel with
  existing fetches and dedupes via React 19 fetch memo, so wall-clock
  impact is ~0.

## References

- ADR-027 (Obsidian: `multi-agent-orchestration-discipline`; repo:
  `article-provider-locale-gate-scope`) — pre-existing numbering conflict,
  not resolved here.
- ADR-028 (`unified-locale-url-builder`) — `applyLocalePrefix` and the
  `buildLocaleUrlFor*` family this work extends.
- ADR-029 (`category-slug-i18n-backfill`, 2026-04-29) — backend translation
  data prerequisite that made this work feasible.

## Implementation

- Branch: `feature/T60.15-server-locale-context-priming`
- Base SHA: `6b6f68d` (develop, 2026-04-30)
- Commits (in order):
  - `82763dc` feat(frontend): LocaleContextProvider initialData + shallow-equal short-circuit
  - `f7a6858` feat(frontend): topic + tag URL builders + hreflang resource helper
  - `7d172f9` feat(frontend): extend LanguageSwitcher with topic and tag context branches
  - `a476420` feat(frontend): extract fetchTopicBySlug to shared lib/api/topics module
  - `a649786` feat(frontend): add resolveLocaleContextData server helper
  - `403f229` feat(frontend): inject x-pathname request header in proxy
  - `96c6362` feat(frontend): prime LocaleContext server-side in (public) layout
  - `57d3322` feat(frontend): add LocaleContextSetter to topic and tag pages
  - `3b3a6aa` fix(frontend): pipe translatedSlugs through generateCategoryMetadata
  - `2fb5d00` test(frontend): SSR canonical hreflang + dedup + head/switcher consistency e2e
  - `36a863c` chore(frontend): cap Playwright local workers at 2 for WSL2 stability
- Merge commit: `cdb6708` (develop, pushed to origin 2026-04-30)
- Tests added: 71 (58 unit + 13 e2e)
- Regression posture: 16-baseline-failure invariant in
  `locale-switching.spec.ts` preserved; line 538 (the Phase 1.5 trigger)
  moved out of failures.
