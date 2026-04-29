# Deschide.md — Release History

Authoritative timeline of release tags. Maintained by hand because tag
order alone (`git tag --sort=-v:refname`) does not encode the difference
between production releases, sprint RCs, and orphaned bugfix-branch tags.

> When in doubt about which tag to deploy, **use the most recent tag listed
> under "Production releases" below**. RCs and orphan tags should never be
> deployed to production.

---

## Production releases

### v1.4.3 — 2026-04-29 (Hotfix)

**Scope:** Backend + frontend hotfix, complete Category slug i18n.

#### Fixed
- All 79 published RO articles returned frontend 404 despite backend 200.
- All EN/RU article URLs rendered with `/uncategorized/` category segment.
- LanguageSwitcher could not navigate from EN/RU pages back to RO.
- Header navigation, sidebar trending widgets, Footer fallback links, and
  cross-locale switcher all emitted RO category slugs under non-RO
  locale prefixes (5 distinct call sites).

#### Added
- `app:category:preview-slug-translations` — generates JSON proposal of
  translated slugs from existing translated titles + RomanianSlugger.
  Output reviewed manually pre-persist.
- `app:category:apply-slug-translations` — persists approved translations
  to `ext_translations`. Idempotent.
- `apps/backend/fixtures/data/category-slug-translations.json` — source
  of truth for translated category slugs. Drives both DevReset and
  CategoryFixtures.
- `CategoryProvider.populateTranslatedSlugs` (mirror of TagProvider pattern).
- `getCategorySlugForLocale` helper as single point of truth for URL building.
- `Footer.buildFallbackCategoryHref` static map for menuItems-empty fallback.
- ADR-029: Category slug i18n strategy.

#### Discovery process
- Found during post-v1.4.2 browser testing by orchestrator.
- 4 hypotheses verified read-only, narrowed to Cluster B as root cause.
- Decomposed into Cluster A (article 404), Cluster B (uncategorized URL),
  Cluster C (switcher asymmetric).
- 5 evaluator-optimizer cycles during implementation surfaced 5 distinct
  defect classes (DB, provider, URL builders, switcher, Footer).

#### Known issues — carried forward
- T60.8: RO article 404 silent null swallow (Cluster A residual) — likely
  auto-resolved, to verify post-deploy.
- T60.9: LanguageSwitcher RO target hardening (Cluster C) — likely
  auto-resolved, to verify post-deploy.
- T60.10: Verify FRA1 deploy script invalidates Next.js fetch cache.
- T60.4 (carried): 2 pre-existing category-page test failures.
- Author locale gate (T60.2)
- PHPStan baseline 644 errors

---

### v1.4.2 — 2026-04-24 (Hotfix)

- **Status**: hotfix off `main` at `v1.4.1` (branch `hotfix/sprint-59-sitemap-hreflang`)
- **Motivation**: Sprint 59 T59.1 (Language Switcher) pre-implementation audit
  surfaced an SEO defect in the public sitemaps. `<loc>` entries and
  `<xhtml:link hreflang>` alternates for `en` and `ru` pointed at the
  Romanian article slug because `lib/api/sitemap-data.ts` fabricated the
  `translations` structure (two mock fabrication sites in that file, one
  for active articles and one for archived) with a
  `// TODO: Fetch actual translation` comment that fell back to
  `article.slug` for non-default locales. Google therefore indexed
  duplicate RO URLs under `hreflang="en"` and `hreflang="ru"`,
  contradicting canonical. Pre-FRA1 deploy priority.
- **Fixes**:
  - **T60.1 — Sitemap hreflang emits RO slug for EN/RU** (`fix(frontend)`).
    `lib/api/sitemap-data.ts` now consumes the backend's real
    `translatedSlugs` and `publishedLocales` fields exposed on
    `/api/articles` (no new endpoint required). `SitemapArticle.translations`
    is now `Partial<Record<Locale, ArticleTranslation>>` — a locale key is
    present only when a translated slug exists. `publishedLocales: Locale[]`
    is surfaced verbatim. `fetchAllArticlesForSitemap` and
    `fetchArchivedArticlesForSitemap` share a `mapArticleToSitemap` helper
    instead of copy-pasted mock blocks. All seven consumer files
    (`app/sitemap.ts`, `app/sitemap-archive.ts`, `app/image-sitemap.ts`,
    `app/news-sitemap.ts`, plus the three `*.xml/route.ts` variants)
    iterate only `article.publishedLocales` (with fallback to all
    configured locales for legacy rows that pre-date the field) and skip
    iterations when `translations[locale]?.slug` is absent. The
    `generateLanguageAlternates` type signature is relaxed from
    `Record<Locale, string>` to `Partial<Record<Locale, string>>` to
    match the existing permissive runtime behavior.
- **Tests**:
  - `apps/frontend/__tests__/unit/app/sitemap.test.ts` — 4 new tests with
    `fetchAllArticlesForSitemap` mocked (green): distinct translated slugs
    per locale, hreflang skipped for unpublished locale, graceful fallback
    on missing translations (no `/en/` or `/ru/` sitemap URLs for RO-only
    articles), per-locale category slug cascade.
  - Full suite: 2 failed / 845 passed / 847 total on `develop` post
    back-merge. The 2 failures are the pre-existing T60.4 category-page
    error-fallback tests, unchanged from v1.4.1.
  - Placed under `__tests__/unit/app/` not `__tests__/integration/`
    because `jest.config.mjs:43` lists the integration directory in
    `testPathIgnorePatterns` (reserved for Playwright).
- **Deferred to S+1** (out of scope for this hotfix, confirmed by test 4):
  - `fetchAllCategoriesForSitemap` uses a `SitemapCategory.translations`
    type that the backend payload never populates (backend returns
    `translatedSlugs`). The `|| category.slug` fallback masks this so
    category sitemap entries are correct-but-duplicated across locales.
    Tracked for optional S+1 cleanup; not SEO-critical because category
    canonical URLs are unaffected by the article-hreflang bug.
- **Back-merge**: `develop` received `v1.4.2` via `--no-ff` merge with
  zero conflicts (T59.1 and T60.1 touch disjoint file sets).
- **References**:
  - Discovery context: Sprint 59 T59.1 (Language Switcher)
    pre-implementation audit on the `develop` branch. See
    `docs/adr/ADR-028-unified-locale-url-builder.md` on develop
    for the full audit trail that surfaced this bug in its
    "Out of scope" section.

### v1.4.1 — 2026-04-24 (Hotfix)

- **Status**: hotfix off `main` at `v1.4.0` (branch `hotfix/image-attach-locale-gate`)
- **Motivation**: REPORT.md (2026-04-24) identified a P0 blocker in the admin
  editorial flow — attaching images to articles failed with 400/500 for every
  browser locale other than `ro` — plus a high-severity duplicate-article
  creation bug on the new-article form.
- **Fixes**:
  - **Finding #1 — ArticleProvider locale gate bleeds into IriConverter**
    (`fix(backend)`). The per-locale publishing gate at
    `src/State/ArticleProvider.php` was applied to every single-item lookup,
    including API Platform's IriConverter calls that resolve `/api/articles/{id}`
    references inside write payloads (e.g. `POST /api/article_images`).
    Admin requests with `Accept-Language: en` therefore received
    `Symfony\Serializer\UnexpectedValueException: Item not found for ...`
    and the write returned 400/500. The gate is now applied only to public
    single-item GETs (operation is `Get`, operation class is `Article`, and
    `$context['fetch_data']` is not set — the latter is always present when
    `AbstractItemNormalizer` invokes IriConverter during denormalization).
    See ADR-027 for the full rationale and options considered.
  - **Finding #6 — admin Save duplicates articles on rapid clicks**
    (`fix(frontend)`). `useArticleForm.handleSubmit` relied on React state
    (`isSubmitting`) to debounce, but state updates are async and multiple
    clicks could dispatch concurrent `createArticleAction` calls before the
    Save button re-rendered as disabled (scenario A in REPORT.md created 7
    duplicate articles from 7 clicks). A synchronous `submittingRef` now
    gates `handleSubmit`, and a successful create redirects to the edit
    page via `router.replace` so browser back cannot re-enter the form.
  - **Finding #4 — nginx production template lacks `client_max_body_size`**
    (`infra`). `apps/backend/nginx-cloudflare.conf` now sets
    `client_max_body_size 15M;` (aligned with the Symfony `Image` validator's
    10M limit plus headroom, and with the other nginx templates in
    `scripts/nginx/` that already carry the directive).
- **Tests**:
  - `apps/backend/tests/Unit/State/ArticleProviderTest.php` — 4 new
    gate-scope unit tests (green).
  - `apps/backend/tests/Functional/Api/ArticleProviderLocaleTest.php` — 4 HTTP
    scenarios mirroring the hotfix matrix (green).
- **Deferred to S+1** (findings tracked but not fixed in this hotfix):
  #2 (Image.path lifecycle), #3 (thumbnail_profiles empty), #5 (new-article
  upload widget missing), #7 (TinyMCE images_upload_handler), #8 (inline-image
  origin validation), #9 (refresh-token fallback UX), #10 (slug diacritics
  transliteration), #11 (featured-image unsaved-changes prompt), #12 (file
  data-URI in JSON response), #13 (Vich deprecated annotation).
- **Production deployment prerequisite**: if the production nginx config is
  not generated from `apps/backend/nginx-cloudflare.conf` (e.g. managed by
  ansible or a parallel server-side config), the equivalent
  `client_max_body_size 15M;` directive must be applied to the api vhost
  before deploy.
- **References**:
  - ADR-027 in `docs/adr/ADR-027-article-provider-locale-gate-scope.md`
  - Investigation artefacts at `/tmp/cc-img-investigation/`
  - Hotfix artefacts at `/tmp/cc-img-investigation-hotfix/`

### v1.4.0 — _upcoming_ (Sprint 58-Recovery)

- **Status**: in progress on `feature/sprint-58-recovery`
- **Posture**: Translation-only — editorial pipeline suspended per
  [ADR-025] / implemented by [ADR-026]
- **Target merge**: `main` after Day 7 re-audit
- **Headline changes**:
  - 11 editorial / briefing / topic / ai_async supervisor configs moved
    to `disabled/` subdirectory (mirrored in `apps/backend/config/supervisor/disabled/`
    and `/etc/supervisor/conf.d/disabled/`).
  - `messenger-translations` worker resurrected (`autostart=true`,
    `autorestart=unexpected`); 951 stalled messages drained, 139 failed
    triaged.
  - Feature flag schema reconciled with ADR-025 D1 (5 ghost flags
    materialized as explicit `false` in `services.yaml`).
  - Editorial scheduler `#[AsCron]` / `#[AsPeriodicTask]` attributes
    commented with ADR-025/026 attribution (defense-in-depth on top of
    master flag).
  - PHPStan baseline acknowledged at 644 (was documented at 22 in
    ADR-022; 5-sprint cleanup roadmap deferred).
  - 9 main-only commits (v1.2.0 release + 8 follow-ups) back-merged to
    develop.
- **Reference**: [ADR-026](20_Architecture/Decisions/ADR-026-v1.3.0-recovery-tag-strategy.md)
  in Obsidian vault.

### Known Issues (deferred to S+1)

- **Author page locale inconsistency**: /en/author/{slug} and
  /ru/author/{slug} render RO article content without fallback notice
  when backend returns unfiltered articles under non-RO locale header.
  Root cause: backend /api/articles endpoint does not apply strict-locale
  filtering on translation presence. Frontend fallback notice (Day 4 /
  Day 7e) only triggers when totalItems === 0, which doesn't fire here.
  Workaround for users: explicit language switcher in header changes URL
  locale consistently. Tracked for S+1 backend fix (add translation-aware
  filter to ArticleProvider).

- **PHPStan baseline drift**: 644 suppressions (vs 22 in ADR-022).
  Triaged in Day 3 into 4 buckets; 5-sprint roadmap deferred. Tracked in
  vault/50_Audit/phpstan-baseline-analysis-2026-04-24.md.

- **Failed messages queue**: 139 messages deferred from DI drift S57.P2a
  transitional state. Triage doc in vault/50_Audit/sprint-58-recovery-
  day2-failed-triage.md. Recommended cleanup: re-translate 19 translation
  failures via `app:translate:articles --force`, evict 120 editorial
  failures (transports suspended).

- **Cost instrumentation gap on LlmAgentCallLog**: cost_usd and token
  counts recorded as 0 across all 76+ rows despite schema support.
  Blocks ADR-025 reactivation cost model. Priority S+1 fix.

- **CI infrastructure stale**: GitHub Actions workflows failing on develop since
  2026-04-21 (pre-v1.4.0). Root causes: backend workflow missing .env copy step
  before composer install; frontend workflow missing `cd apps/frontend` before
  pnpm install. Not a code issue — local verification suffices for v1.4.0.
  Priority S+1 fix (trivial, ~2 one-line patches to workflow files).

### v1.2.0 — 2026-04-06

- **Title**: Complete Aggregator System (Sprints 24-26)
- **Branch**: `main`
- **Highlights**: 82 aggregator sources, 10 Moldova-specific scrapers,
  PressRelease universal gateway, content cleaning pipeline, URL dedup.

---

## Reserved / orphan tags — DO NOT DEPLOY

The following tags exist in `git tag` but are not production releases.
They were cut on a side branch that did not return to mainline. The
underlying work landed via different paths and is part of v1.2.0+. Do
not deploy these tags; they are kept only for historical reference.

| Tag | Date | Source branch | Underlying work |
|---|---|---|---|
| `v1.3.0`     | 2026-04-07 | `bugfix/story-clusters-frontend-debt-t52` | Sprint 27 Per-Locale Publishing (commit `c8ac7a45`) |
| `v1.3.0-rc2` | 2026-04-08 | _same_ | Sprint 31 Clustering Fix |
| `v1.3.0-rc3` | 2026-04-08 | _same_ | Sprint 32 Score Calibration |

Versioning resumes monotonically at **`v1.4.0`** (Sprint 58-Recovery).
The `v1.3.x` namespace is permanently retired.

Reference: [ADR-026 §D1.alt](20_Architecture/Decisions/ADR-026-v1.3.0-recovery-tag-strategy.md)
documents why the `v1.4.0` skip-strategy was chosen over force-retag,
`v1.3.1` (patch-on-orphan), `v1.2.1` (patch-on-prod), and `v2.0.0`.

---

## Sprint release candidates

These are intermediate tags cut at sprint boundaries during ongoing
development. Useful for rollback to a known-working sprint state, but
typically superseded by the next production release.

### v1.2.0-rc series
- `v1.2.0-rc.sprint57-pivot` — current `develop` head reference
- `v1.2.0-rc1`

### v1.1.0-rc series — Editorial pipeline development (S53–S56)
- `v1.1.0-rc.sprint56`
- `v1.1.0-rc.sprint55`
- `v1.1.0-rc.sprint54`
- `v1.1.0-rc.sprint53`
- `v1.1.0-rc1`

### v1.0.0-rc series — Pre-monorepo / early platform (S49–S52)
- `v1.0.0-rc.sprint52`
- `v1.0.0-rc.sprint51c`
- `v1.0.0-rc.sprint51a`
- `v1.0.0-rc.sprint49`
- `v1.0.0-rc1` / `v1.0.0-rc2` / `v1.0.0-rc3`
- `v1.0.0-monorepo`

---

## Tag naming conventions (for future releases)

- **Production**: `vMAJOR.MINOR.PATCH` (semver, no suffix). Cut from
  `main` after a release branch finishes.
- **Sprint RC**: `vMAJOR.MINOR.PATCH-rc.sprintNN[suffix]`. Cut from
  `develop` (or a release branch) at sprint boundary. NOT for production.
- **Sprint RC unsuffixed**: `vMAJOR.MINOR.PATCH-rcN` (older convention,
  used for the v1.0.0 → v1.1.0 series). Avoid for new tags; use the
  sprint-suffixed form.
- **Bumping rules**:
  - PATCH: bug fixes only, no schema/API changes, no posture changes.
  - MINOR: feature additions, posture changes, schema additions.
  - MAJOR: breaking API/contract changes, multi-sprint platform inflections.
- **Skip-version protocol**: when a version namespace is contaminated
  (e.g. orphaned tags on side branches), document the skip in this file
  and resume at the next clean integer (as done with v1.3.x → v1.4.0).

---

## Deploy reference

| Environment | Source | Tag selection |
|---|---|---|
| Production (FRA1) | `main` HEAD | Most recent "Production release" entry above |
| Staging | `develop` HEAD | Most recent `vX.Y.Z-rc.sprintNN` |
| Local dev | `feature/*` | N/A — branch HEAD |

[ADR-025]: /mnt/c/Users/Radu/DeschideVault/20_Architecture/Decisions/ADR-025-editorial-pipeline-suspended.md
[ADR-026]: /mnt/c/Users/Radu/DeschideVault/20_Architecture/Decisions/ADR-026-v1.3.0-recovery-tag-strategy.md
