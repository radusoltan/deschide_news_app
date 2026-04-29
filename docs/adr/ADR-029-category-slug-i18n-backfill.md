# ADR-029: Category Slug i18n — Backfill Strategy & Frontend Migration

**Date:** 2026-04-29
**Status:** Accepted
**Deciders:** Radu (orchestrator)
**Tags:** #i18n #seo #data-layer #url-builders

## Context

Category slug translations were never populated in `ext_translations`
despite Category entity being correctly wired with Gedmo Translatable +
Sluggable. CategoryProcessor (admin write path) was correctly configured
to persist translations on per-locale edit, but the historical seed
command (commit 18b50ba, Mar 30 2026) was a one-off and never wired into
the dev-reset cycle. After any `app:dev:reset`, slug translations vanished.

Frontend consumers had two compounding gaps:
1. URL builders fell back to `category?.slug || 'uncategorized'` instead
   of consuming `category.translatedSlugs[locale]`.
2. LanguageSwitcher swapped locale prefix but reused source-locale slug,
   producing 404s on cross-locale navigation.

CategoryProvider (backend serialization) was missing the
`populateTranslatedSlugs` method that TagProvider and ArticleProvider had,
so even when DB had translations, payload was incomplete.

## Decision

Three-layer fix:

**Data layer:**
- JSON source-of-truth at `fixtures/data/category-slug-translations.json`,
  reviewed manually before persist (Cyrillic transliteration may need
  human refinement; SEO impact of slug choices justifies review gate).
- Two new commands: preview (generates proposal) + apply (persists,
  idempotent).
- Belt-and-braces wiring: CategoryFixtures loads JSON during fixture
  load + DevResetCommand step 3b runs apply command after fixtures
  (defense-in-depth for production-shape data without full reset).

**Backend serialization:**
- `CategoryProvider.populateTranslatedSlugs` mirrors TagProvider pattern:
  base table query for RO + `ext_translations` query for non-RO locales.

**Frontend:**
- `getCategorySlugForLocale(category, locale)` helper as single
  point of truth.
- 8 call sites refactored: url-builder, metadata-generator, ArticleHeader,
  ArticleMeta, StandardHero, HeroArticle, HomepageSidebar, SpecialArticleBanner.
- LanguageSwitcher consumes the elaborate LocaleContext from T59.1
  (publishedLocales + translatedSlugs + categoryTranslatedSlugs +
  context discriminator), with `buildLocaleUrlForArticle` /
  `buildLocaleUrlForCategory` from `lib/seo/locale-url.ts` (ADR-028)
  computing the per-locale href.
- Footer fallback (menuItems-empty path) uses
  `buildFallbackCategoryHref` with locale-aware slug map.

## Rationale for review-before-persist

Auto-generation via SluggerInterface produces deterministic but not
semantically perfect slugs:
- Russian: soft sign ь transliterates to hyphen (`kul-tura`) → manual
  edit to `kultura`.
- Russian: long compound nouns produce SEO-poor slugs
  (`mezhdunarodnyye` → preferred `mir` for "world news";
  `reklamnyi-material` → preferred `reklama`).
- English: stylistic singular/plural choices (`sport` vs `sports`)
  affect brand voice.

SEO stability of category slugs (canonical URLs persisting across
months) justifies one-time review investment.

## Consequences

### Positive
- Public site article rendering fully restored across 3 locales.
- LanguageSwitcher predictably correct in all 6 locale-switch directions.
- Single helper `getCategorySlugForLocale` prevents future drift across
  consumers.
- DevReset is now idempotent and complete (no manual seed step required).
- JSON source-of-truth is editable, version-controlled, reviewable in PR.

### Negative
- Adding a new category requires running
  `app:category:preview-slug-translations` and editing JSON before slug
  translations appear in non-RO locales.
- ADR-027 numbering ambiguity (repo vs vault) remains unresolved post
  this hotfix — flagged for orchestrator follow-up.

### Neutral
- T60.8 (Cluster A residual) and T60.9 (Cluster C) likely auto-resolved
  by this hotfix. To verify post-deploy.

## References
- Investigation log: `50_Audit/sprint-59-execution-log.md`
- ADR-028: Unified Locale URL Builder (T59.1) — extended by this work
- T60.6 Cluster B decomposition rationale
- 5 evaluator-optimizer rounds during implementation surfaced
  5 distinct defect classes
