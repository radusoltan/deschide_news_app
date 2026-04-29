# ADR-028 — Unified locale URL builder

- **Status**: Accepted (Sprint 59)
- **Date**: 2026-04-24
- **Decision owner**: Frontend
- **Supersedes**: —
- **Related findings**: Sprint 59 language-switcher audit, Open Question 1 (sitemap `article.translations` structure)
- **Feature branch**: `feature/sprint-59-i18n-switcher`

---

## Context

The public site used two parallel utilities that both answered "what URL
does `locale X` see for this page?":

- `lib/utils/url-builder.ts` — returned **relative paths** for in-app
  navigation (`<Link href=...>`), consumed by 38 files. It stripped the
  `/ro` prefix for the default locale when `i18nConfig.prefixDefault` is
  `false`.
- `lib/seo/locale-url.ts` — returned **absolute URLs with `baseUrl`** for
  SEO (canonical, hreflang alternates), consumed only by the author page.
  It always included the locale segment.

The Sprint 59 audit surfaced a user-facing bug in the `Header`'s
`LanguageSwitcher`: clicking "EN" on a RO article called
`/ro/politica/slug-ro` → `/en/politica/slug-ro`. The English slug was
never requested. The root cause was a third, ad-hoc prefix-swap helper
inline in `LanguageSwitcher.tsx` — a variant of the same logic living
in `url-builder.ts`, without access to the backend-provided
`translatedSlugs` field.

Having three locations for "locale → URL path" mapping invites drift.
Any component that builds a cross-locale link is free to reinvent naive
prefix swaps and reintroduce the same bug.

## Decision

- `lib/seo/locale-url.ts` becomes the **single source of truth** for the
  "locale → URL path" mapping on the public site. It now exports both
  the existing SEO helpers (absolute URLs) and a new family of
  navigation helpers that return relative paths:
  - `applyLocalePrefix(locale, path)` — the primitive that honours
    `i18nConfig.prefixDefault` for the default locale.
  - `buildLocaleUrlForArticle(targetLocale, article, currentLocale)`
    — returns `null` when `translatedSlugs[targetLocale]` is missing.
    The caller is expected to render that locale as disabled.
  - `buildLocaleUrlForCategory(targetLocale, category, currentLocale)`
    — falls back through `currentLocale`, default locale, then
    `category.slug`. Never returns null (categories stay visible
    across locales by design).
  - `buildLocaleUrlGeneric(targetLocale, currentPath, currentLocale)`
    — strips an existing locale segment and re-prefixes. Used for
    pages without translatable slugs (homepage, search, about, …).

- `lib/utils/url-builder.ts` is kept for its object-based signatures
  (`buildArticleUrl(article, locale)`, `buildCategoryUrl(category, locale)`,
  `buildAuthorUrl(authorSlug, locale)`, `buildLocalizedUrl(path, locale)`)
  and refactored to delegate all prefix handling to `applyLocalePrefix`.
  This keeps 38 call sites untouched while making the locale-prefix
  rule impossible to diverge.

- `LanguageSwitcher` (client component) no longer ships its own
  prefix-swap helper. It consumes either:
  - props passed directly (used by tests and callers with inline data),
    or
  - a new `LocaleContext` populated per page by a `LocaleContextSetter`
    client island.

  Article and category pages mount `LocaleContextSetter` with
  `publishedLocales`, `translatedSlugs`, and (for articles)
  `categoryTranslatedSlugs`. The Header itself, which lives in the
  shared `layout.tsx`, does not need per-page props.

## Consequences

- Any future navigation component that needs a cross-locale URL has a
  single, typed, tested entry point. Naive prefix swaps cannot
  reintroduce the original bug without actively avoiding the helpers.
- `publishedLocales` remains the authoritative signal for "is this
  locale available?". The switcher disables a locale when *either*
  `publishedLocales` excludes it *or* `translatedSlugs[locale]` is
  missing. Both cases render an `aria-disabled` `<span>` with a
  localized `title` tooltip (`languageSwitcher.notTranslated`).
- `lib/utils/url-builder.ts` is now a thin wrapper. A future cleanup
  could in-line it at call sites, but that is not in scope for Sprint
  59 — 38 consumers would be touched with no behavioural gain.
- SEO helpers (`buildCanonicalUrl`, `buildHreflangAlternates`,
  `buildLocalizedUrl(baseUrl, locale, path)`) are unchanged.

## Out of scope (explicit)

- **Author locale gate** — authors are considered published in all
  locales. Tracked for Sprint 60 (RELEASES.md v1.4.0 note).
- **Category `publishedLocales`** — categories are visible in every
  locale. This ADR does not introduce a per-locale gate for
  categories.
- **`sitemap.ts` `article.translations` bug** — `app/sitemap.ts`,
  `app/news-sitemap.ts`, and `app/sitemap-archive.ts` read
  `article.translations?.{locale}.{slug,categorySlug}`. That structure
  is **not** populated from the backend `translatedSlugs` field; it is
  a local mock in `lib/api/sitemap-data.ts` (lines 106–125) with
  `TODO: Fetch actual translation` comments that fall back to the RO
  slug for EN/RU. Sitemaps for EN/RU hreflang are currently emitted
  with duplicate RO slugs. Deferred to Sprint 60; out of scope for
  this ADR which is concerned with in-app navigation only.

## Validation

- 10/10 existing `url-builder.test.ts` green after refactor (pure
  delegation, identical behaviour).
- 19/19 new `locale-url.test.ts` cover the acceptance criteria:
  translated-slug lookup, null return for missing translation,
  default-locale no-prefix, category fallback chain, generic swap.
- `locale-switching.spec.ts` gains three E2E tests for the
  translated-slug redirect and disabled state.
