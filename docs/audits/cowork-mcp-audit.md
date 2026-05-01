# Cowork MCP Audit — Deschide News (Symfony 8 backend)

> Read-only technical audit. Audit date: 2026-04-24. Backend root: `apps/backend`.

## 1. Stack confirmation

| Component | Version / value | Source |
|---|---|---|
| PHP | 8.5.4 (cli) | `php -v` (composer requires `>=8.2`) |
| Symfony | 8.0.x (`framework-bundle v8.0.1`) | `composer.lock` |
| Doctrine ORM | 3.6.0 | `composer.lock` |
| Doctrine DBAL | ^4.2 | `composer.json:14` |
| API Platform | `api-platform/symfony v4.2.11` (+ `doctrine-orm v4.2.11`) | `composer.json:9-10` |
| Lexik JWT | `^3.2` | `composer.json:24` |
| Gesdinet Refresh Token | `^2.0` | `composer.json:21` |
| Messenger | `symfony/messenger 8.0.*` | `composer.json:46` |
| Mercure | `symfony/mercure-bundle ^0.4.2` | `composer.json:44` |
| Rate Limiter | `symfony/rate-limiter 8.0.*` | `composer.json:48` |
| Workflow component | NOT installed | grep returned nothing in `composer.json` and no `framework: workflows:` block under `config/packages/` |
| Elasticsearch client | `elasticsearch/elasticsearch ^9.2` (native, NOT FosElastica) | `composer.json:17` |
| FosElastica | NOT installed (confirmed by composer + CLAUDE.md hard rule) | — |
| Sentry | `sentry/sentry-symfony` | `composer.json:30` |
| HTML sanitizer | `symfony/html-sanitizer 8.0.*` | `composer.json:42` |
| Vich uploader | `vich/uploader-bundle ^2.9.2` | `composer.json:55` |
| Stof Doctrine Extensions (Gedmo) | `^1.15.3` | `composer.json:31` |
| Predis | `^3.3` | `composer.json:28` |
| MadelineProto (Telegram) | `danog/madelineproto ^8.6` | `composer.json:11` |

**Database**: PostgreSQL 18 (`server_version: '18'`, `config/packages/doctrine.yaml:9`). Connection string shape: `postgresql://USER:PASSWORD@HOST:5432/DBNAME?serverVersion=18&charset=utf8` (`apps/backend/.env:33`). Secondary read-only connection `newscoop` (MariaDB 10.11) for legacy import.

**Redis**: presence confirmed. DSN `redis://localhost:6379/1` (`apps/backend/.env:60`, `REDIS_URL`). Used for L2 cache, sessions, analytics counters (see `Service/Analytics/AnalyticsService` referenced from `Controller/MetricsController.php`). Policy: `volatile-lru` (CLAUDE.md rule).

**Elasticsearch**: presence confirmed via `ELASTICSEARCH_HOST=https://localhost:9200` (`apps/backend/.env:64`). Listening on `*:9200` (verified by `ss`). Used for article full-text search (`Service/Elasticsearch/ArticleSearchService.php`) and image search.

**RabbitMQ**: confirmed listening on `*:5672`. `MESSENGER_TRANSPORT_DSN` defaults to `doctrine://default?auto_setup=0` in `.env:55`, but RabbitMQ is the production transport (override in `.env.local` per CLAUDE.md). Many queues defined: `async`, `cache_async`, `stats_async`, `translations_critical|urgent|high|*`, `scraping`, `ai_async`, `failed`. (`config/packages/messenger.yaml`)

**Mercure**: hub running on `:3000`, configured at `config/packages/mercure.yaml`. JWT secret in `MERCURE_JWT_SECRET`. Used for SSE breaking-news + LiveText real-time.

## 2. How to run locally

| Item | Value |
|---|---|
| Start backend | `cd apps/backend && symfony serve -d --port=8081` |
| Bind URL | `http://127.0.0.1:8081` (currently listening — `ss` shows `symfony` PID 176272) |
| Stop / status | `symfony server:stop`, `symfony server:status`, `symfony server:log` |
| WSL2 → Windows host URL | Same `http://127.0.0.1:8081` from Windows; Symfony binds to localhost in WSL2 and Windows reaches it through the WSL2 NAT shim. (No manual port forwarding configured: `compose.yaml` only declares Mercure.) |
| Frontend (separate) | `cd apps/frontend && pnpm dev` → `http://localhost:3005` |
| Required env vars before first start | `DATABASE_URL`, `JWT_SECRET_KEY`, `JWT_PUBLIC_KEY`, `JWT_PASSPHRASE`, `MESSENGER_TRANSPORT_DSN`, `REDIS_URL`, `ELASTICSEARCH_HOST` (+ user/pass), `MERCURE_URL`, `MERCURE_JWT_SECRET`, `CORS_ALLOW_ORIGIN`. JWT keypair must exist at `config/jwt/private.pem` + `public.pem` (generate via `symfony console lexik:jwt:generate-keypair`). |
| Docker | `compose.yaml` only spins the Mercure hub. Postgres / Redis / RabbitMQ / Elasticsearch are expected as host services (CLAUDE.md confirms shared system services). |

CLI rule: **always** `symfony console …`, never `php bin/console …` (CLAUDE.md HARD RULE 1).

## 3. Authentication

| Item | Value |
|---|---|
| JWT firewall config | `config/packages/security.yaml:18-29` (firewall `api`, `pattern: ^/api`, `stateless: true`, `entry_point: jwt`, `json_login.check_path: /api/login_check`, `refresh_jwt.check_path: /api/token/refresh`) |
| Lexik config | `config/packages/lexik_jwt_authentication.yaml` (only secret/public/passphrase set; **no `token_ttl` override** → defaults to **3600s / 1h** per Lexik default) |
| Refresh token TTL | **2,592,000s = 30 days**, `single_use: false`, `ttl_update: true` (`config/packages/gesdinet_jwt_refresh_token.yaml:3-6`) |
| User entity | `src/Entity/User.php` (table is `` `user` ``; identifier = `username`, length 180) |
| User provider | `app_user_provider` → `App\Entity\User` by `username` (`security.yaml:7-11`) |
| Login endpoint | `POST /api/login_check`, JSON body. **Note**: `json_login` defaults expect `{"username": "...", "password": "..."}` — but CLAUDE.md example sends `{"email":"...","password":"..."}`. There is **no explicit `username_path` override** in `security.yaml`, so the default `username` field is the one Lexik will read. **GAP: discrepancy between CLAUDE.md curl example (`email`) and `json_login` default (`username`) — confirm by inspecting first run, or add `json_login.username_path: email` if email login is desired.** |
| Login response shape | `{ "token": "<jwt>", "refresh_token": "<rt>", "refresh_token_expires_at": <ts> }` (Lexik `AuthenticationSuccessHandler` + `gesdinet_jwt_refresh_token.return_expiration: true`) |
| Refresh endpoint | `POST /api/token/refresh`, body `{ "refresh_token": "..." }` (param name from `gesdinet_jwt_refresh_token.token_parameter_name: refresh_token`) |
| Service / machine user | **No dedicated CLI command** to create users (no `app:user:create`-style command in `src/Command/`). Methods available: (a) load `App\DataFixtures\UserFixtures` via `symfony console doctrine:fixtures:load --group=user` — seeds three users with password `password`: `admin/ROLE_ADMIN`, `editor/ROLE_EDITOR`, `ai_asistent/ROLE_EDITOR`; (b) create via authenticated `POST /api/users` (`security: ROLE_ADMIN`, processor `App\State\UserProcessor`, denormalization group `user:write` accepts `username,email,firstName,lastName,roles,plainPassword,isActive`). **GAP: no make-style CLI; for an MCP service user the cleanest path is a dedicated console command (or a fixture group) that hashes a password and assigns a custom `ROLE_MCP`.** |
| ROLE_* constants found | `ROLE_USER`, `ROLE_EDITOR`, `ROLE_ADMIN`, `ROLE_SUPER_ADMIN`, `ROLE_MAP` (grep across `src/` + `config/`). Hierarchy in `security.yaml:31-33`: `ROLE_EDITOR ⊂ ROLE_ADMIN`. `ROLE_SUPER_ADMIN` and `ROLE_MAP` appear only in single controllers (likely vestigial). `User::getRoles()` always appends `ROLE_USER`. |

## 4. Article entity

**File**: `src/Entity/Article.php` (1085 lines). Table `articles`. Implements `Gedmo\Translatable`. Lifecycle callbacks enabled (`#[ORM\HasLifecycleCallbacks]`).

### Core columns

| Column | Type | Nullable | Length / constraints | Notes |
|---|---|---|---|---|
| `id` | INTEGER (identity) | no | PK | line 152 |
| `title` | string | no | 255, NotBlank | translatable (line 158) |
| `slug` | string | no | 255 unique, ReservedSlug | translatable, Gedmo\Slug from `title` (line 167) |
| `lead` | TEXT | yes | max 3000 | translatable (line 181) |
| `content` | TEXT | yes | — | translatable, only in `article:detail` group (line 187) |
| `meta_title` | string | yes | 60 | translatable, SEO (line 193) |
| `meta_description` | string | yes | 160 | translatable, SEO (line 200) |
| `category_id` | FK Category | yes | onDelete=SET NULL | line 206 |
| `status` | enum string | no | 20, default `NEW` | `App\Enum\ArticleStatus` (line 240) |
| `badge` | enum string | yes | 20 | `App\Enum\ArticleBadge` (line 244) |
| `is_featured` | bool | no | default false | line 248 |
| `view_count` | int | no | default 0 | read-only externally (line 252) |
| `created_at` | datetime_immutable | no | Gedmo Timestampable on=create | line 257 |
| `updated_at` | datetime_immutable | no | Gedmo Timestampable on=update | line 262 |
| `published_at` | datetime_immutable | yes | — | set by lifecycle callback (line 269) |
| `publish_at` | datetime_immutable | yes | scheduled-publish target | writable (line 273) |
| `archived_at` | datetime_immutable | yes | — | line 278 |
| `archive_reason` | enum `ArchiveReason` | yes | 30 | line 282 |
| `webcode` | string | yes | 10 unique | short-link code (line 290) |
| `source_email` | string | yes | 64 unique | set when AI-ingested from email (line 294) |
| `content_hash` | string | yes | 64 (SHA-256) | dedup (line 298) |
| `request_translation` | bool | no | default false | line 302 |
| `translation_status` | string | yes | 20 | line 306 |
| `translated_at` | datetime_immutable | yes | — | line 310 |
| `translated_by` | string | yes | 50 | line 314 |
| `published_locales` | text[] (custom `text_array` Doctrine type) | no | default `{ro}` | per-locale visibility gate (line 319) |
| `internal_summary` | TEXT | yes | — | AI TL;DR (line 324) |
| `ingested_at` | datetime_immutable | yes | — | AI pipeline completion ts (line 328) |
| `ai_generated` | bool | no | default false | line 332 |
| `ai_confidence_score` | float | yes | — | line 336 |
| `ai_source_count` | int | yes | — | line 340 |
| `article_type` | enum `ArticleType` | yes | 30 | Sprint 55: FLASH/DEVELOPING_STORY/LONGFORM/FULL_FLASH (line 346) |
| `revision_history` | JSON | yes | capped at 100 entries | append-only, for DEVELOPING_STORY (line 366) |
| `revision_count` | int | no | default 0 | line 370 |
| `original_source_signal_id` | FK `Editorial\SourceSignal` | yes | onDelete=SET NULL | line 376 |

### Status field

- Type: PHP enum `App\Enum\ArticleStatus` (string-backed), stored as VARCHAR(20).
- Values: `NEW='new'`, `SUBMITTED='submitted'`, `PUBLISHED='published'`, `PUBLISHED_FULL='published_full'`, `ARCHIVED='archived'` (`src/Enum/ArticleStatus.php`).
- Default: `ArticleStatus::NEW`.
- Allowed transitions: **Symfony Workflow component is NOT installed/configured.** Transitions are enforced procedurally inside `App\State\Article\ArticleCreateProcessor` and `ArticleUpdateProcessor` (`src/State/Article/`). Specifically:
  - On create with `status=PUBLISHED` → `ArticlePublishedEvent` dispatched (`ArticleCreateProcessor.php:84`).
  - On update where old != PUBLISHED and new == PUBLISHED → `ArticlePublishedEvent`; otherwise `ArticleUpdatedEvent` (`ArticleUpdateProcessor.php:138-142`).
  - `publishedAt` is auto-set to `now()` by the entity's `#[ORM\PrePersist]`+`#[ORM\PreUpdate]` callback at `Article.php:633-639` when `status=PUBLISHED && publishedAt is null`.
  - `publishAt` (scheduled future publication) is cleared when status moves out of `SUBMITTED` (`ArticleUpdateProcessor.php:85-89`).
  - **GAP: there is no centralised state-machine; all enforcement is implicit in the two processors. Any MCP write tool must replicate or reuse those processors to avoid bypassing the events.**

### Relationships

| Field | Cardinality | Target | File |
|---|---|---|---|
| `category` | ManyToOne | `App\Entity\Category` | `src/Entity/Category.php` |
| `authors` | ManyToMany (`article_author`) | `App\Entity\Author` | `src/Entity/Author.php` |
| `articleImages` | OneToMany | `App\Entity\ArticleImage` (→ `Image`) | `src/Entity/ArticleImage.php`, `src/Entity/Image.php` |
| `relatedArticles` | ManyToMany self (`related_articles`, max 20) | `Article` | same file |
| `tags` | ManyToMany (`article_tag`) | `App\Entity\Tag` | `src/Entity/Tag.php` |
| `topics` | ManyToMany (mappedBy) | `App\Entity\Topic` | `src/Entity/Topic.php` |
| `originalSourceSignal` | ManyToOne | `App\Entity\Editorial\SourceSignal` | `src/Entity/Editorial/SourceSignal.php` |

Translations are stored in the Gedmo `ext_translations` table (Translatable strict mode, `HINT_INNER_JOIN`).

### Timestamps managed by lifecycle

- `created_at`, `updated_at`: Gedmo Timestampable (no manual code).
- `published_at`: lifecycle callback at `Article.php:633-639` (PrePersist + PreUpdate) — sets to `now()` on first transition into `PUBLISHED`.
- `publish_at`: explicit, set by API client for scheduled publishing; cleared in update processor outside SUBMITTED.
- `ingested_at`, `translated_at`, `archived_at`: explicit, set by pipeline services.

### Soft-delete / archive

There is **no Gedmo SoftDeleteable**. Archive is a status transition (`status=ARCHIVED`, `archived_at=now()`, `archive_reason=<enum>`), driven by `App\Service\ArticleArchiveService` and exposed at `POST /api/admin/articles/{id}/archive` (`Controller/Admin/ArticleArchiveController.php`). Restore: `POST /api/admin/articles/{id}/unarchive`. Bulk: `POST /api/admin/articles/archive-bulk`. A second API resource `archived_articles` provides a read-only collection over the archived subset (separate provider `ArchivedArticleProvider`).

## 5. Taxonomy

### Category

- File: `src/Entity/Category.php`. Table `categories`. Translatable.
- Schema: `id` PK, `title` (string 255, translatable), `slug` (string 255 unique, translatable, Gedmo\Slug), `parent` (self ManyToOne, hierarchy), `status` (`CategoryStatus` enum, default `ACTIVE`), `onFrontPage` (bool), `frontPagePosition` (int), `frontPageLayout` (string 20), `inMenu` (bool), `inFooterMenu` (bool), `translationStatus` (string 20), `translatedAt`, `translatedBy`, `createdAt`, `updatedAt`. Indexes on `status`, `on_front_page`. Doctrine L2 cache region `long_lived`.
- API: `GET/POST/PUT/PATCH/DELETE /api/categories[/{id}]`, `paginationItemsPerPage: 50`, filters by `status`, `title` partial, `slug` exact, booleans `onFrontPage|inMenu|inFooterMenu`. Lookup-by-slug: `GET /api/categories/by-slug/{slug}`.
- Management: via Doctrine fixtures (`src/DataFixtures/CategoryFixtures.php`) and via the admin UI (Next.js) hitting the API. No SQL listing performed (HARD rule 2 — read-only audit, no DB query).
- **GAP: live category list not enumerated (no DB SELECT issued by this audit). To list, run `symfony console doctrine:query:sql "SELECT id, slug, status FROM categories ORDER BY id"` — left to the architect.**

### Tag

- File: `src/Entity/Tag.php`. Table `tags`. Translatable. Free-form tags (not controlled vocabulary).
- Fields: `id`, `name` (translatable, 100), `slug` (translatable, unique, 100), `usageCount`, `createdAt`. Indexes on `slug`, `usage_count`.
- API: `GET/POST/PUT/DELETE /api/tags[/{id}]`, plus custom: `GET /api/tags/popular`, `GET /api/tags/search`, `GET /api/tags/{id}/related`, `GET /api/tags/{id}/stats`, `GET /api/tags/unused`, `POST /api/tags/{id}/merge` (`src/Controller/TagController.php`).

### Topic (controlled vocabulary)

- File: `src/Entity/Topic.php`. Hierarchical taxonomy used by editorial pipeline.
- Notable flags: `isActive`, `isSensitive` (bool, line 163), `isStoryLeaf`. Filter exposed via `BooleanFilter` (`Topic.php:84`).
- API: standard CRUD plus custom: `GET /api/topics/tree`, `GET /api/topics/{id}/path`, `GET /api/topics/{id}/articles`, `GET /api/topics/{id}/summary`, `GET /api/topics/{id}/markdown`, `GET /api/topics/search`, `POST /api/topics/{id}/move`, `POST /api/topics/detect`, `GET /api/topics/flat`, `GET /api/topics/proposals`, `POST /api/topics/{id}/approve|reject`, `GET /api/topics/trending` (`src/Controller/TopicController.php` + `Controller/Api/TopicProposalController.php` + `TrendingTopicsController.php`).
- Per memory note: editorial pipeline runs on Topics; story-clusters deprecated.

## 6. API routes — core table

Output of `symfony console debug:router --format=txt`, filtered to articles, auth, search, taxonomy, metrics, archive, press releases, escalation, fact-check, SEO, translations, topics. Admin UI / asset / profiler / debug routes excluded.

| Method | Path | Controller::action | Auth | Purpose |
|---|---|---|---|---|
| ANY | `/api/login_check` | Lexik `AuthenticationSuccessHandler` | public | JSON login → JWT |
| POST | `/api/token/refresh` | Gesdinet refresh handler | public | Exchange refresh token |
| GET | `/api/health` (+ `/database`, etc.) | `HealthController` | public | Service health (HTTP 200/503) |
| GET | `/metrics` | `MetricsController::metrics` | ROLE_ADMIN | Prometheus scrape |
| GET | `/api/articles` | API Platform → `ArticleProvider` | public (GET) | List articles (locale-aware) |
| GET | `/api/articles/{id}` | API Platform → `ArticleProvider` | public (GET) | Get article |
| POST | `/api/articles` | API Platform → `ArticleCreateProcessor` | ROLE_ADMIN/EDITOR | Create article (any status incl. NEW = "draft") |
| PUT | `/api/articles/{id}` | API Platform → `ArticleUpdateProcessor` | ROLE_ADMIN/EDITOR | Replace article |
| PATCH | `/api/articles/{id}` | API Platform → `ArticleUpdateProcessor` | ROLE_ADMIN/EDITOR | Partial update (status changes here) |
| DELETE | `/api/articles/{id}` | API Platform → `ArticleDeleteProcessor` | ROLE_ADMIN/EDITOR | Hard delete |
| GET | `/api/articles/by-slug/{slug}` | `SlugLookupController` | public | Resolve slug to article |
| GET | `/api/articles/{id}/background` | `ArticleBackgroundController` | ROLE_EDITOR | AI-generated context blocks |
| POST | `/api/articles/{id}/background/apply` | same | ROLE_EDITOR | Apply background to article |
| GET | `/api/articles/{id}/summary` | `ArticleSummaryController` | ROLE_EDITOR | Internal TL;DR |
| POST | `/api/articles/{id}/summary/regenerate` | same | ROLE_EDITOR | Regenerate summary via AI |
| POST | `/api/articles/{id}/optimize-seo` | `SeoController` | ROLE_ADMIN/EDITOR | Generate metaTitle/metaDescription/tags via Gemini |
| POST | `/api/articles/{id}/translate` | `TranslationController` | ROLE_EDITOR | Trigger translation EN+RU |
| GET | `/api/articles/{id}/translations` | `TranslationController` | ROLE_EDITOR | List translation states |
| GET | `/api/articles/locks/active` | `ArticleLockController` | ROLE_EDITOR | List active edit locks |
| GET | `/api/articles/{id}/lock/check` | same | ROLE_EDITOR | Check lock |
| POST | `/api/articles/{id}/lock` | same | ROLE_EDITOR | Acquire edit lock |
| POST | `/api/articles/{id}/lock/heartbeat` | same | ROLE_EDITOR | Heartbeat |
| DELETE | `/api/articles/{id}/lock` | same | ROLE_EDITOR | Release |
| GET | `/api/archive/years` (+ `/stats`, `/categories`) | `ArchiveController` | public (GET) | Archive navigation |
| GET | `/api/archived_articles[/{id}]` | API Platform → `ArchivedArticleProvider` | public (GET) | Browse archived articles |
| POST | `/api/admin/articles/{id}/archive` | `ArticleArchiveController::archiveArticle` | ROLE_ADMIN | Archive article (sets status + reason) |
| POST | `/api/admin/articles/{id}/unarchive` | same | ROLE_ADMIN | Restore from archive |
| POST | `/api/admin/articles/archive-bulk` | same | ROLE_ADMIN | Bulk archive |
| GET | `/api/admin/archive/stats` | same | ROLE_ADMIN | Archive counts |
| POST | `/api/admin/articles/{id}/factcheck` | `ArticleFactCheckController` | ROLE_EDITOR | NotebookLM fact-check (rate-limited 20/min) |
| GET | `/api/admin/escalations[/stats]` | `AdminEscalationController` | ROLE_EDITOR | Editorial escalation queue |
| POST | `/api/admin/escalations/{id}/approve|reject|extend-sla` | same | ROLE_EDITOR | Resolve escalation |
| GET | `/api/categories[/{id}]` (+ POST/PUT/PATCH/DELETE) | API Platform → `CategoryProvider/Processor` | public GET / ROLE_ADMIN write | Category CRUD |
| GET | `/api/categories/by-slug/{slug}` | `SlugLookupController` | public | Resolve category slug |
| POST | `/api/categories/{id}/translate` | `TranslationController` | ROLE_EDITOR | Translate category |
| GET | `/api/authors[/{id}]` (+ POST/PUT/DELETE) | API Platform | public GET / ROLE_ADMIN write | Author CRUD |
| GET | `/api/authors/by-slug/{slug}` | `SlugLookupController` | public | Resolve author slug |
| POST | `/api/authors/{id}/translate` | `TranslationController` | ROLE_EDITOR | Translate author |
| GET | `/api/tags[/{id}]` (+ POST/PUT/DELETE) | API Platform → `TagProvider/Processor` | public GET / ROLE_ADMIN write | Tag CRUD |
| GET | `/api/tags/popular`, `/search`, `/unused`, `/{id}/related`, `/{id}/stats` | `TagController` | mixed (see file) | Tag analytics |
| POST | `/api/tags/{id}/merge` | `TagController` | ROLE_EDITOR | Merge duplicate tags |
| GET | `/api/topics[/{id}]` (+ POST/PUT/DELETE) | API Platform | public GET / ROLE_ADMIN write | Topic CRUD |
| GET | `/api/topics/tree`, `/flat`, `/search`, `/proposals`, `/trending`, `/{id}/{path,articles,summary,markdown}` | `TopicController` + co. | mixed | Topic taxonomy ops |
| POST | `/api/topics/detect`, `/{id}/move`, `/{id}/approve`, `/{id}/reject` | same | ROLE_EDITOR | Topic curation |
| GET | `/api/press_releases[/{id}]` | API Platform | ROLE_EDITOR | Aggregated incoming content (unified gateway) |
| PATCH | `/api/press_releases/{id}` | API Platform | ROLE_EDITOR | Edit press release |
| POST | `/api/press_releases/{id}/approve` | `PressReleaseApproveProcessor` | ROLE_EDITOR | Approve → spawns Article |
| POST | `/api/press_releases/{id}/reject` | `PressReleaseRejectProcessor` | ROLE_EDITOR | Reject |
| GET | `/api/press_releases/counts` | `PressReleaseCountsController` | ROLE_EDITOR | Status counts |
| POST | `/api/press-emails/fetch` | `PressEmailFetchController` | ROLE_EDITOR | Manual Zoho mailbox fetch |
| POST | `/api/press-releases/{id}/fetch-content` | `PressReleaseFetchContentController` | ROLE_EDITOR | Re-scrape full content |
| GET | `/api/aggregator/stats`, `/dedup-stats` | `AggregatorStatsController`, `DedupStatsController` | ROLE_EDITOR | Aggregator metrics |
| POST | `/api/aggregator/run` | `AggregatorTriggerController` | ROLE_ADMIN | Trigger aggregator run |
| GET | `/api/admin/stats/article/{id}` | `StatsController::articleStats` | ROLE_ADMIN | Per-article daily stats (views, unique, dwell, completion) |
| GET | `/api/admin/stats/site\|categories\|article-counts\|realtime` | same | ROLE_ADMIN | Site-wide stats |
| GET | `/api/admin/stats/trending` | same | public | Frontend trending widget |
| GET | `/search` | `ArticleSearchController::search` | public | Elasticsearch full-text article search |
| GET | `/api/images[/{id}]` (+ POST/PUT/DELETE) | API Platform | public GET / ROLE_ADMIN write | Image CRUD (Vich) |
| POST | `/api/images/{id}/thumbnails/crop`, `/reset-crop`, `/generate-thumbnails` | API Platform sub-ops | ROLE_ADMIN | Thumbnail ops |
| GET | `/api/important_articles[/{id}]` (+ POST/DELETE) | API Platform | mixed | Editor-curated front-page selection |
| GET | `/api/redirects/lookup`, `/statistics`, `/by-entity`, `/health` | `RedirectManagementController` | mixed | URL redirect resolution |
| POST | `/api/slug/lookup\|validate\|suggest\|bulk-validate\|check-redirect\|check-reserved` | `SlugLookupController` | public | Slug utilities |
| POST | `/api/track/pageview\|reading-time\|scroll-depth` | `TrackingController` | public | Frontend analytics ingest |

## 7. Draft & publishing flow

- **Draft representation**: an article with `status=NEW` (default on insert) — there is **no separate Draft entity**. `SUBMITTED` is the moderation-queue state; `PUBLISHED` is live; `PUBLISHED_FULL` distinguishes flash-promoted-to-full editorial articles; `ARCHIVED` is the terminal state.
- **Endpoint that creates a draft**: `POST /api/articles` (API Platform standard). Required fields driven by Validator on `Article` (group `article:write`): `title` (NotBlank, ≤255). All other fields optional. Status defaults to `NEW` if omitted. Auth: `ROLE_ADMIN` or `ROLE_EDITOR`.
- **Endpoint that publishes**: `PATCH /api/articles/{id}` (or `PUT`) with `{"status": "published"}`. Side-effects:
  - Lifecycle callback at `Article.php:633-639` sets `published_at = now()` on first transition.
  - `ArticleUpdateProcessor` (`src/State/Article/ArticleUpdateProcessor.php:138-142`) dispatches `ArticlePublishedEvent` (or `ArticleUpdatedEvent` for re-publish edits).
  - Article cache is invalidated (`ArticleCacheInvalidator`).
  - Mercure SSE update is published.
- **Scheduled publishing**: set `publishAt` to a future timestamp + `status=SUBMITTED`. A scheduler (`scheduler_default` per CLAUDE.md, `app:publish-scheduled-articles` command) flips status to `PUBLISHED` at the target time. (Confirmed in CLAUDE.md but not re-grepped here.)
- **Symfony Workflow**: not used. State transitions are enforced procedurally inside the two API Platform processors (see §4).
- **Human-approval gate**: yes — for AI-generated content. `App\Service\Editorial\AutoPublishGateService` decides whether AI-generated articles bypass moderation (criteria per CLAUDE.md: confidence ≥ 0.85, sources ≥ 3, words ≥ 300, non-sensitive). Sensitive cases route to `App\Service\Editorial\Escalation\*` → editorial escalation queue exposed at `/api/admin/escalations`. Press releases also go through an explicit moderation step (`POST /api/press_releases/{id}/approve` is the only path that materialises an `Article` from a press release).

## 8. Metrics

- **Internal analytics**: `App\Entity\ArticleStatsDaily` (`src/Entity/ArticleStatsDaily.php`) — daily aggregation rows per article: `views`, `uniqueVisitors`, `avgReadingTime`, `completionRate (decimal 5,2)`. Unique on `(article_id, date)`. Sister entity `App\Entity\SiteStatsDaily` for site-wide. Plus `App\Entity\PageView` (raw pageviews — present in Entity dir).
- **Live counters**: Redis (analytics), surfaced via `App\Service\Analytics\AnalyticsService` (referenced from `MetricsController` and `Controller/Api/StatsController`). `Article.viewCount` is the lifetime counter on the entity.
- **Ingest endpoints**: `POST /api/track/pageview`, `/track/reading-time`, `/track/scroll-depth` (`TrackingController`, public).
- **Per-article query API**: `GET /api/admin/stats/article/{id}?range=7days|30days|...` (`StatsController::articleStats`, `ROLE_ADMIN`) returns `{article_id, title, current_views, stats: [{date, views, unique_visitors, avg_reading_time, completion_rate}]}`. Cached 60s in Redis.
- **Site-wide**: `/api/admin/stats/site`, `/categories`, `/article-counts`, `/realtime`, `/trending` (last is public).
- **Prometheus**: `GET /metrics` (`ROLE_ADMIN`), Prometheus text format, includes cache hit rate, active sessions, unique visitors today, top-10 trending articles. `App\Service\MetricsService` + `prometheus_client_php`.
- **External analytics**: not present in code (no Matomo / GA4 / Plausible bundle in `composer.json`). All analytics are first-party.

## 9. Search

- **Client**: `elasticsearch/elasticsearch ^9.2` (native PHP client). FosElastica is **not** installed (CLAUDE.md hard rule + composer confirmation).
- **Service split**: `App\Service\Elasticsearch\ArticleSearchService` (queries), `ElasticIndexManager` (lifecycle), `ElasticDocumentService` (index/update/delete docs). Constructor argument `elasticsearchIndexPrefix` defaults to `"deschide"` (env `ELASTICSEARCH_INDEX_PREFIX`).
- **Index naming**: `{prefix}_articles_{locale}` → `deschide_articles_ro`, `deschide_articles_en`, `deschide_articles_ru` (one index per locale; see `ElasticDocumentService::getIndexName`). Image indices are managed similarly via `app:elasticsearch:create-image-index` per CLAUDE.md.
- **Indexed Article fields** (per `ArticleSearchService::search`, multi_match query): `title^3`, `tag_names^2.5`, `lead^2`, `content`. Filters supported: `status` (default `published`), `category_id`. Fuzziness `AUTO`. (See `src/Service/Elasticsearch/ArticleSearchService.php:65-82`.) **GAP: full doc shape (which fields are mapped) requires reading `ElasticIndexManager::createIndex` mapping body — only the queryable subset is documented above.**
- **Search endpoint**: `GET /search` (note: **not** under `/api`, so CORS rules at `^/api` do not apply — it has its own controller-level prefix at `Route('', name: 'api_')`). Public access. Query params: `q` (≥2 chars, required), `page` (default 1), `itemsPerPage` (default 12, max 100), `locale` (defaults to `Accept-Language`), `categoryId` (optional). Response: `{results: [...], total, page, itemsPerPage, totalPages, query}`. Each result includes: `id, title, slug, lead, image, category, publishedAt, viewCount` (extracted from `_source`).
- **Test endpoint**: `GET /search-test` (returns `{status: ok, message: ...}`).
- **GAP: `/search` is not gated by access_control rules (it is outside `^/api`), confirm it is intentionally public for the frontend (likely yes — frontend public site search).**

## 10. Sensitive / moderation flagging

- **No dedicated `article_flags` / `moderation_queue` table for arbitrary admin flagging exists.** The only flagging primitives are:
  - `App\Entity\Topic.isSensitive` (boolean per topic). When an article is tagged with a sensitive topic, `App\Service\Editorial\AutoPublishGateService` blocks auto-publishing and routes to escalation. Detection logic: `App\Service\Editorial\SensitiveTopicDetector` (slug-based fallback marked `@deprecated` since Sprint 49 in favour of `Topic.isSensitive`).
  - `App\Entity\Editorial\EditorialEscalationLog` (`src/Entity/Editorial/EditorialEscalationLog.php`) — immutable log of AI/legal-flagged escalations. Fields: `articleSnapshot (JSON)`, `category` (enum `EscalationCategory`), `originGraphSnapshot (JSON)`, `decision` (enum `EscalationDecision`, nullable while pending), `decidedBy` (User), `createdAt`, `decidedAt`, `expiresAt` (SLA). Indexes for pending-by-expiry, by-decision, by-category.
  - `App\Service\Editorial\Guard\LegalGuard` flags Category-6 (legal) content automatically.
- **No editor-facing "flag this article as sensitive" button mapped to a generic `flagged` boolean on `Article`**.
- **GAP: no flagging mechanism for free-form sensitive flags by a third-party tool (e.g., MCP). Proposed minimal schema** (5 lines):

  ```
  table article_flags
  ┣ id PK, article_id FK→articles ON DELETE CASCADE
  ┣ severity smallint (1..5), reason varchar(40), notes text NULL
  ┣ flagged_by_user_id FK→user NULL, flagged_by_source varchar(40)  -- e.g. 'mcp:cowork'
  ┣ created_at, resolved_at NULL, resolved_by FK→user NULL, resolution varchar(40) NULL
  ┗ index (article_id), index (resolved_at) WHERE resolved_at IS NULL
  ```

## 11. MCP coverage matrix

| MCP tool | Status | Covering endpoint / note |
|---|---|---|
| `list_drafts(limit, author)` | PARTIAL | `GET /api/articles?status=new&authors.id={id}&itemsPerPage={limit}` works (SearchFilter on `tags`/`tags.id` exists; **`author.id` filter is NOT in the SearchFilter list at `Article.php:139-148`**, only `authors.type` is exposed). Need to either add filter `'authors' => 'exact'` / `'authors.id' => 'exact'` to the entity or rely on iteration. `status` filter for `new` works through API Platform's enum coercion. |
| `get_article(id)` | EXISTS | `GET /api/articles/{id}` (or `/api/articles/by-slug/{slug}`) |
| `create_draft_article(title, lead, body, category, tags, source_urls[])` | PARTIAL | `POST /api/articles` covers `title, lead, content, category, tags`. **`source_urls[]` is NOT a first-class field on Article** — there is `source_email` (string, nullable) for a single source ref, but no array of source URLs. Provenance in the editorial pipeline lives on `Editorial\SourceSignal` and is linked via `originalSourceSignal` (single FK). |
| `update_article(id, fields)` | EXISTS | `PATCH /api/articles/{id}` (with `application/merge-patch+json`) |
| `get_published_today()` | PARTIAL | `GET /api/articles?status=published&order[publishedAt]=DESC&publishedAt[after]={today}`. **Date range filter is NOT in the entity's `ApiFilter` list** (only `OrderFilter` on `publishedAt`); a `DateFilter` would have to be added, or the call has to rely on `order` + client-side cutoff. |
| `get_published_this_week()` | PARTIAL | Same as above — needs a `DateFilter` on `publishedAt`. |
| `get_article_metrics(id)` | EXISTS | `GET /api/admin/stats/article/{id}?range=7days` (ROLE_ADMIN) |
| `search_archive(query, date_from, date_to)` | PARTIAL | `GET /search?q=…` covers full-text. Date-range filter is **not** exposed on `/search` (only `categoryId` and `status`). Workaround: either extend `ArticleSearchController` (small change) or call `GET /api/archived_articles?…` which goes through `ArchivedArticleProvider` (relational, not Elasticsearch). |
| `flag_sensitive(id, reason, severity)` | MISSING | No matching endpoint. Closest are: (a) escalation flow at `POST /api/admin/escalations/...` (but those operate on existing escalation log rows, not on arbitrary articles), (b) topic curation. **Requires the new `article_flags` table proposed in §10 + a 1-method controller.** |
| `suggest_seo(id)` | EXISTS | `POST /api/articles/{id}/optimize-seo` (writes `metaTitle`, `metaDescription`, suggested tags via Gemini). Note it **persists** results — there is no read-only "suggest without applying" mode. |

## 12. Rate limiting & security

- **Rate limiter**: `symfony/rate-limiter` enabled. Config: `config/packages/rate_limiter.yaml`. Limiters defined:
  - `api_general` (100/min fixed_window), `api_login` (5/min token_bucket), `api_write` (60/min), `api_image_operations` (120/min), `ai_chat` (30/h per user), `topic_detection` (1/sec), `factcheck` (20/min), `escalation` (100/h token_bucket per editor), `escalation_extend` (5/h per editor), `editorial_writer` (10/h sliding_window).
  - **GAP: `api_general` and `api_write` are defined but no global event-listener wiring grepped to apply them automatically — these appear to be opt-in per controller via the `RateLimiterFactoryInterface` constructor injection. MCP traffic would not be rate-limited unless the tool route uses the factory.**
- **CORS**: `config/packages/nelmio_cors.yaml`. `CORS_ALLOW_ORIGIN` regex from env (default in `.env:46`: `^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$`). Methods on `^/api`: GET, OPTIONS, POST, PUT, PATCH, DELETE. Headers: `Content-Type`, `Authorization`. `^/api/embed` is **wildcard `*`** for cross-origin embedding.
- **CSRF**: `config/packages/csrf.yaml` enables stateless CSRF protection for forms (`token_id: submit, authenticate, logout`). The `^/api` firewall is `stateless: true`, so **CSRF is not enforced on `/api/*` (relies on JWT bearer)**. Suitable for MCP server-to-server.
- **IP allowlist**: not configured (no `access_control` rule references `ips:` in `security.yaml`).
- **HMAC validation**: not configured. The only shared-secret-style header in the codebase is the frontend `REVALIDATE_SECRET` mentioned in CLAUDE.md (used by Symfony→Next.js cache invalidation), not by API consumers.
- **Other security headers**: CSP/HSTS/etc. handled by Nginx in production (`apps/backend/nginx-cloudflare.conf`); not enforced by Symfony itself.

## 13. Open questions for the MCP design

1. **Service-account identity model.** Should the MCP server authenticate as a dedicated `User` row with a custom role (`ROLE_MCP`?) and use a long-lived refresh token, or should we issue a separate non-rotating API key bypassing JWT? Current refresh token TTL is 30 days — acceptable for an automated cron-style consumer?
2. **Login field discrepancy.** `security.yaml` `json_login` has no `username_path` override, so Lexik defaults to `{username, password}`, while CLAUDE.md examples send `{email, password}`. Which is the actual contract today, and should the MCP send `username` or `email`?
3. **Draft semantics.** Is `status=NEW` truly the "draft" state for human authors, or does the editorial team treat `SUBMITTED` as the draft-ready-for-review state? Should an MCP-created article default to `NEW` (silent buffer) or `SUBMITTED` (visible in moderation queue)?
4. **Source provenance.** `Article` has only `source_email` (single string) and `originalSourceSignal` (single FK). Where should an MCP store an array of `source_urls[]` it ingested from external research? New JSON column on `Article`, or a thin sidecar entity (`ArticleSource`)?
5. **Date filtering on `/api/articles`.** The entity exposes only `OrderFilter` on `publishedAt`; there is no `DateFilter`. Are we OK adding `#[ApiFilter(DateFilter::class, properties: ['publishedAt'])]` to support `get_published_today()` / `_this_week()` cleanly, or should the MCP call a custom controller?
6. **Search date range.** `/search` (Elasticsearch) currently filters only by `categoryId` and `status`. Add `dateFrom`/`dateTo` query params to `ArticleSearchController`, or push the MCP onto the relational `archived_articles` collection for archive queries?
7. **Flagging schema.** Approve the `article_flags` table sketched in §10? Should "flag" be a write-only audit (immutable like `EditorialEscalationLog`) or a mutable state on the article itself?
8. **`suggest_seo` mode.** Currently `POST /optimize-seo` writes results. Do we want a `?dryRun=1` mode for the MCP to surface suggestions without persisting, or is auto-apply the desired contract?
9. **Rate-limit policy for MCP.** None of the existing limiters (`api_general`, `api_write`, `ai_chat`) are auto-applied via a global event subscriber. Should MCP traffic share the per-user limiters, or get a dedicated `mcp_*` limiter family identified by the service-account user?
10. **Workflow component adoption.** Status transitions are enforced ad-hoc in two processors. Should we formalise the article state machine with `symfony/workflow` before MCP starts mutating state, so transitions like `NEW → SUBMITTED → PUBLISHED → ARCHIVED` are validated centrally?

DONE — cowork-mcp-audit.md created (332 lines)
