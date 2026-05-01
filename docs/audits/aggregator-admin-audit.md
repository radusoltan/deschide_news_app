# Aggregator Admin Page — Exhaustive Audit Report

**Date:** 2026-04-07  
**Auditor:** Claude Opus 4.6 (automated)  
**Scope:** Backend entities, services, commands, API endpoints + Frontend admin pages for the aggregator system  
**Status:** READ-ONLY audit — no files modified

---

## Executive Summary

The Deschide News aggregator system is a well-architected backend pipeline with **11 aggregator implementations**, a **3-layer semantic deduplication engine** (SHA-256 → Elasticsearch MLT → Gemini CLI), **trend scoring** with temporal decay, and **automatic translation** to Romanian. However, the **frontend admin page is a lightweight dashboard** that surfaces only 3 of the system's ~15 backend capabilities. Critical management features — CRUD for aggregators, RelevanceKeyword editing, manual trigger runs, run history/logs, cron configuration, and dedup statistics — have **no frontend UI** and are only accessible via CLI commands. The aggregator dashboard is also **not linked in the admin sidebar**, making it effectively hidden from editors.

---

## 1. Backend Analysis

### 1.1 Entities

| Entity | File | API Resource | Description |
|--------|------|--------------|-------------|
| **RelevanceKeyword** | `src/Entity/RelevanceKeyword.php` | None (no `#[ApiResource]`) | 4-tier keyword system with language, `isActive` toggle, `addedBy` (manual/ai/trend). DB table: `relevance_keywords` |
| **Topic** | `src/Entity/Topic.php` | `#[ApiResource]` — full CRUD at `/api/topics` | Hierarchical (Gedmo NestedTree), translatable, `reviewStatus` field (`approved`/`pending_review`/`rejected`), linked to Articles via `article_topics` M2M |
| **PressRelease** | `src/Entity/PressRelease.php` | `#[ApiResource]` — GetCollection, Get, Patch, Post (approve), Delete | The output entity of the aggregator pipeline. `sourceType` enum: `email|scrape|manual|aggregator`. Has `contentHash`, `relevanceScore`, `suggestedTopics`, `originalLanguage` |

**Notable:** There is **no `Aggregator` entity** and **no `AggregatorRun` entity**. Aggregators are pure service classes tagged with `app.aggregator` — their configuration and run history are not persisted in the database.

### 1.2 API Endpoints

| Endpoint | Controller | Method | Auth | Description |
|----------|-----------|--------|------|-------------|
| `GET /api/aggregator/stats` | `AggregatorStatsController` | GET | `ROLE_EDITOR` | Returns cached stats from Redis key `aggregator_source_stats`. **Currently always returns `[]`** — nothing writes to this cache key |
| `GET /api/topics/trending` | `TrendingTopicsController` | GET | `ROLE_EDITOR` | Trending topics with temporal-decay scoring, params: `days` (1-30), `limit` (1-50) |
| `GET /api/topics/proposals` | `TopicProposalController` | GET | `ROLE_EDITOR` | Topics with `reviewStatus = 'pending_review'` |
| `POST /api/topics/{id}/approve` | `TopicProposalController` | POST | `ROLE_EDITOR` | Sets `reviewStatus = 'approved'`, `isActive = true` |
| `POST /api/topics/{id}/reject` | `TopicProposalController` | POST | `ROLE_EDITOR` | Sets `reviewStatus = 'rejected'`, `isActive = false` |
| `GET /api/topics` | API Platform (Topic entity) | GET | Public (cached) | Full topic listing with search/filter/order |
| `POST/PUT/DELETE /api/topics` | API Platform (Topic entity) | * | `ROLE_EDITOR`/`ROLE_ADMIN` | Full CRUD for topics |
| `GET /api/press_releases` | API Platform (PressRelease) | GET | — | Lists press releases, filterable by `status`, `sourceType`, `originalLanguage` |
| `POST /api/press_releases/{id}/approve` | `PressReleaseApproveProcessor` | POST | — | Approves and creates article |
| `GET /api/press_releases/counts` | `PressReleaseCountsController` | GET | — | Counts by source type |

### 1.3 Aggregator Service Implementations (11 total)

All implement `AggregatorInterface` (methods: `fetch(): AggregatorResult[]`, `getSourceType()`, `getName()`) and are tagged `app.aggregator`:

| # | Class | Source Type | Notes |
|---|-------|-------------|-------|
| 1 | `GoogleNewsRssAggregator` | `google_news_rss` | Rate-limited (1 req/5min), 6 locales, configurable keywords in `aggregator.yaml` |
| 2 | `GoogleAlertsAggregator` | `google_alerts` | Parses Google Alert emails/feeds |
| 3 | `NewsApiAggregator` | `news_api` | newsapi.org integration |
| 4 | `BingNewsAggregator` | `bing_news` | Bing News search API |
| 5 | `TelegramAggregator` | `telegram` | Channel monitoring (requires `TelegramSessionManager`) |
| 6 | `FacebookRssBridgeAggregator` | `facebook_rss` | Via RSS Bridge proxy |
| 7 | `GuardianApiAggregator` | `direct_portal` | The Guardian API |
| 8 | `AnsaAggregator` | `direct_portal` | ANSA (Italian news agency) |
| 9 | `CorriereAggregator` | `direct_portal` | Corriere della Sera |
| 10 | `BildAlternativeAggregator` | `direct_portal` | Bild (German) |
| 11 | `LeMondeScraper` | `direct_portal` | Le Monde (French) |

### 1.4 Supporting Services

| Service | File | Purpose |
|---------|------|---------|
| `SemanticDeduplicatorService` | `src/Service/Aggregator/SemanticDeduplicatorService.php` | 3-layer dedup: L1 SHA-256 hash → L2 ES more_like_this (thresholds: 0.55 review, 0.82 duplicate) → L3 Gemini CLI semantic comparison |
| `TrendScoringService` | `src/Service/Aggregator/TrendScoringService.php` | Newton's Law of Cooling temporal decay scoring with source weights (Reuters/AP: 3.0, local agencies: 2.0, aggregator: 1.0) |
| `TrendQueryGeneratorService` | `src/Service/Aggregator/TrendQueryGeneratorService.php` | Generates aggregator queries from trending topics, caches in Redis (6h TTL) |
| `PressReleaseAggregatorFactory` | `src/Service/Aggregator/PressReleaseAggregatorFactory.php` | Creates `PressRelease` entities from `AggregatorResult`, auto-detects category |
| `AggregatorRateLimiter` | `src/Service/Aggregator/AggregatorRateLimiter.php` | Token bucket rate limiting for Google News (1 req/5 min) |
| `ElasticsearchSimilarityService` | `src/Service/Aggregator/ElasticsearchSimilarityService.php` | MLT (more_like_this) similarity search on `deschide_articles_trilingual` index |
| `AggregatorTranslationService` | `src/Service/Translation/AggregatorTranslationService.php` | Auto-translates non-Romanian content to Romanian via Gemini CLI |

### 1.5 Commands

| Command | File | Purpose |
|---------|------|---------|
| `app:aggregator:run` | `AggregatorSchedulerCommand.php` | Runs all or specific aggregator sources. Options: `--source`, `--dry-run`, `--limit` |
| `app:trend:generate-queries` | `GenerateTrendQueriesCommand.php` | Generates and caches trend queries from top trending topics |
| `app:relevance:seed` | `RelevanceKeywordSeedCommand.php` | Seeds 69 relevance keywords (4 tiers) from hardcoded data. Options: `--force` |

### 1.6 Message Handlers

| Handler | Message | Purpose |
|---------|---------|---------|
| `ProcessAggregatorResultHandler` | `ProcessAggregatorResultMessage` | Dedup → create PressRelease → translate to RO → persist. Routed to `editorial` transport |
| `ScrapeSourceHandler` | `ScrapeSourceMessage` | Scrapes RSS feeds by priority group, applies relevance filtering with dynamic trend keywords |

### 1.7 Scheduler/Cron Configuration

| Schedule | Provider | Frequency | Message |
|----------|----------|-----------|---------|
| Morning briefing | `EditorialScheduleProvider` | `0 6 * * *` | `GenerateDailyBriefingMessage(type: 'morning')` |
| Evening briefing | `EditorialScheduleProvider` | `0 20 * * *` | `GenerateDailyBriefingMessage(type: 'evening')` |
| Weekly summary | `EditorialScheduleProvider` | `0 21 * * 0` | `GenerateWeeklySummaryMessage` |
| Dossier generation | `EditorialScheduleProvider` | `0 22 1,15 * *` | `GenerateDossiersMessage(threshold: 5, days: 30)` |
| Local sources scraping | `EditorialScheduleProvider` | Every 30 min | `ScrapeSourceMessage(priorityGroup: 'local')` |
| High-priority intl | `EditorialScheduleProvider` | Every 2 hours | `ScrapeSourceMessage(priorityGroup: 'international_high')` |
| Medium-priority intl | `EditorialScheduleProvider` | Every 4 hours | `ScrapeSourceMessage(priorityGroup: 'international_medium')` |

**Note:** There is **no scheduled task for `app:aggregator:run`**. The aggregator command must be triggered manually or via an external cron. This is separate from the scraping scheduler.

### 1.8 Configuration

File: `config/packages/aggregator.yaml`

- Google News RSS enabled: `true`
- Rate limit: 1 request per 5 minutes (token bucket)
- Keywords configured per locale: ro, en, ru, it, de, fr
- Locales with Google News parameters (hl, gl, ceid)
- 5 rotating user agents

---

## 2. Frontend Analysis

### 2.1 Aggregator Dashboard Page

**File:** `apps/frontend/app/[locale]/admin/aggregator/page.tsx` (306 lines)

**Navigation:** **NOT linked in the admin sidebar** (`Sidebar.tsx` contains links to Press Queue and Topics but NOT to Aggregator Dashboard). The page exists but is only accessible by manually navigating to `/{locale}/admin/aggregator`.

**Data fetched (3 endpoints):**

| Endpoint | Status | Purpose |
|----------|--------|---------|
| `GET /api/topics/trending?days=7&limit=20` | Working | Trending topics with scores |
| `GET /api/aggregator/stats` | **Always returns `[]`** | Source statistics — cache key never populated |
| `GET /api/topics/proposals` | Working | Pending review topic proposals |

**UI Components:**

| Component | What it renders | CRUD Support |
|-----------|----------------|--------------|
| `TrendingTopicsChart` | Horizontal bar chart of trending topics (score, article count, velocity) | Read-only |
| `AggregatorSourcesStatus` | Grid cards per source (articles found, duplicates skipped, pending, last run, health status) | Read-only (**always empty** — no data written to cache) |
| `TopicProposalsList` | Table of pending topics with Approve/Reject buttons | Approve/Reject only |

**Actions supported:**
- `POST /api/topics/{id}/approve` — Approve topic proposal
- `POST /api/topics/{id}/reject` — Reject topic proposal
- Refresh button (re-fetches all 3 endpoints)

**Authentication:** Reads JWT token from cookie (`auth_token`). This is a **different auth pattern** from the press-queue page which uses server actions with `getAccessToken()` from DAL.

### 2.2 Press Queue Page (Aggregator-related)

**File:** `apps/frontend/app/[locale]/admin/press-queue/page.tsx` (517 lines)

The press queue is the **primary working interface** for aggregator output. It:
- Displays all PressRelease items (email, scrape, manual, **aggregator**)
- Has source type filter with "Agregator" option
- Shows `aggregator:` prefix in source labels
- Supports approve/reject actions
- Linked in sidebar as "Press Queue"

### 2.3 Frontend API Layer

| File | Purpose |
|------|---------|
| `app/actions/press-releases.ts` | Server actions: `fetchPressReleases`, `approvePressRelease`, `rejectPressRelease`, `fetchPressEmails`, `fetchPressReleaseCounts` |
| `lib/api/press-releases.ts` | Client-side API functions (older pattern, possibly unused in favor of server actions) |

---

## 3. Data Flow Mapping

### 3.1 Complete Lifecycle

```
[Aggregator Sources (11)]
   │
   ├── Manual trigger: `app:aggregator:run`
   │
   ▼
[AggregatorInterface.fetch()] → AggregatorResult[]
   │
   ▼
[Symfony Messenger] → ProcessAggregatorResultMessage
   │                    (routed to 'editorial' transport)
   ▼
[ProcessAggregatorResultHandler]
   │
   ├── L1: SHA-256 hash check (ContentHasher + ContentDeduplicator)
   │   └── DUPLICATE → skip
   │
   ├── L2: Elasticsearch more_like_this (score > 0.82 → DUPLICATE)
   │   └── score < 0.55 → UNIQUE
   │
   ├── L3: Gemini CLI semantic comparison (gray zone 0.55-0.82)
   │   └── isDuplicate + confidence > 0.7 → DUPLICATE
   │
   ├── UNIQUE: Create PressRelease via PressReleaseAggregatorFactory
   │   ├── Auto-detect category (CategoryDetectorService)
   │   ├── Set sourceType = 'aggregator'
   │   ├── Set sourceName = 'aggregator:{sourceName}'
   │   └── Compute contentHash
   │
   ├── Translate to Romanian if sourceLanguage ≠ 'ro'
   │   └── AggregatorTranslationService (Gemini CLI)
   │
   └── Persist PressRelease (status = 'pending')
       │
       ▼
[Admin: Press Queue] → Editor reviews
   │
   ├── Approve → PressReleaseApproveProcessor → Article created
   └── Reject → status = 'rejected'
```

### 3.2 Separate Scraping Pipeline

```
[EditorialScheduleProvider] → ScrapeSourceMessage (every 30min/2h/4h)
   │
   ▼
[ScrapeSourceHandler]
   ├── Parse RSS feeds
   ├── Scrape full content
   ├── Content dedup (hash-based)
   ├── Relevance filter (with dynamic trending keywords from TrendQueryGeneratorService)
   └── Dispatch ProcessScrapedArticleMessage → PressRelease (sourceType = 'scrape')
```

### 3.3 Configurable vs Hardcoded

| Field | Configurable from Admin UI | Configurable from Config | Hardcoded |
|-------|---------------------------|--------------------------|-----------|
| Google News keywords | No | Yes (`aggregator.yaml`) | — |
| Google News locales | No | Yes (`aggregator.yaml`) | — |
| Rate limit (5 min) | No | Yes (`aggregator.yaml`) | — |
| Aggregator enabled/disabled | No | Yes (`aggregator.yaml`, only Google News) | Other aggregators: hardcoded in constructors |
| Relevance keywords (69) | No | No | `RelevanceKeywordSeedCommand.php` |
| Dedup thresholds (0.55, 0.82) | No | No | `SemanticDeduplicatorService.php` |
| Trend scoring weights | No | No | `TrendScoringService.php` |
| Scraping schedule intervals | No | No | `EditorialScheduleProvider.php` |
| Source priority groups | No | No | `ScrapeSourceHandler.php` |
| Topic review status | **Yes** (approve/reject) | — | — |
| PressRelease approval | **Yes** (press queue) | — | — |

### 3.4 Real-time (Mercure) Integration

**None.** There are no Mercure subscriptions or publications related to the aggregator system. The dashboard relies on manual refresh via a button click.

---

## 4. Feature Inventory Table

| Feature | Status | Backend Support | Frontend Support | Notes |
|---------|--------|----------------|-----------------|-------|
| **View trending topics** | Active | `GET /api/topics/trending` | TrendingTopicsChart | Working — bar chart with score, count, velocity |
| **View aggregator source stats** | **Broken** | `GET /api/aggregator/stats` returns `[]` | AggregatorSourcesStatus | Cache key `aggregator_source_stats` is **never written to** by any service |
| **Topic proposals review** | Active | `GET/POST /api/topics/proposals|approve|reject` | TopicProposalsList | Approve/reject pending topics |
| **CRUD aggregators** | **Missing** | No entity, no API endpoints | No UI | Aggregators are hardcoded service classes |
| **Enable/disable aggregator toggle** | **Missing** | Only Google News has YAML config `enabled` | No UI | Other aggregators have no enable/disable |
| **Cron interval configuration** | **Missing** | Hardcoded in `EditorialScheduleProvider` | No UI | No way to change scraping intervals from admin |
| **Manual trigger aggregator run** | **Missing** | `app:aggregator:run` CLI command exists | No UI button | No "Run Now" button in dashboard |
| **Run history/logs** | **Missing** | No `AggregatorRun` entity, no persistence | No UI | Runs are logged to Monolog only |
| **RelevanceKeyword CRUD** | **Missing** | Entity exists but **no API resource** | No UI | Only seeded via CLI (`app:relevance:seed`) |
| **Dedup statistics** | **Missing** | Logged to Monolog only | No UI | No aggregate stats (total dupes, unique, review) |
| **Trend scoring configuration** | **Missing** | Hardcoded weights in `TrendScoringService` | No UI | Source weights and decay parameters not configurable |
| **Error monitoring** | **Missing** | Logged to Monolog only | No UI | No error count, no alerting |
| **Bulk operations** | **Missing** | No batch endpoints | No UI | Cannot approve/reject multiple topics at once |
| **PressRelease queue (aggregator)** | **Active** | Full API via API Platform | Press Queue page with "Agregator" filter | Working — main editorial workflow |
| **Dashboard in admin nav** | **Missing** | N/A | **Not in sidebar** | Page exists but unreachable without manual URL |
| **Trend query generation** | CLI-only | `app:trend:generate-queries` | No UI | Cannot generate/view trend queries from admin |
| **Real-time status updates** | **Missing** | No Mercure integration | No SSE subscription | Dashboard requires manual refresh |

---

## 5. Gaps & Recommendations

### 5.1 Backend Functionality with No Frontend UI

1. **`app:aggregator:run` command** — No "Run Now" button in dashboard
2. **`app:trend:generate-queries` command** — No UI to view/generate trend queries
3. **`app:relevance:seed` command** — No UI to manage RelevanceKeyword CRUD
4. **RelevanceKeyword entity** — Has no `#[ApiResource]` annotation, completely invisible to frontend
5. **Aggregator enable/disable** — Only Google News has YAML config, no API endpoint to toggle
6. **Dedup service thresholds** — Hardcoded, not exposed via API
7. **Scraping schedule** — Hardcoded intervals, no API to view or modify
8. **Source weights for trend scoring** — Hardcoded, no API

### 5.2 Frontend UI Calling Non-existent or Broken Endpoints

1. **`GET /api/aggregator/stats`** — Endpoint exists and returns valid JSON, but **always returns `[]`** because no service ever writes to the `aggregator_source_stats` cache key. The `AggregatorSourcesStatus` component will **always show "Nicio statistică disponibilă"**.

2. **Authentication mismatch** — The aggregator dashboard uses cookie-based JWT extraction (`document.cookie.split(...)`) while the rest of the admin (press-queue) uses server actions with `getAccessToken()` from DAL. This inconsistency may cause auth failures.

### 5.3 Completely Missing Features

1. **Aggregator configuration UI** — No way to add/remove aggregator sources, change keywords, or adjust parameters from the admin panel
2. **Run history persistence** — No database table to store aggregator run metadata (start time, duration, results count, errors)
3. **Dedup dashboard** — No visibility into how many articles are being deduplicated, what the hit rate is, or which articles are in "needs review" status
4. **Alerting/notifications** — No mechanism to alert editors when an aggregator fails or when there's a spike in new content
5. **RelevanceKeyword admin UI** — The 69 keywords are only seedable via CLI and cannot be managed through the admin panel
6. **Per-aggregator health monitoring** — No heartbeat or last-success tracking per source

### 5.4 Security Concerns

| Concern | Severity | Details |
|---------|----------|---------|
| **Auth pattern inconsistency** | Medium | Aggregator dashboard reads JWT from cookie via `document.cookie.split(';')` while press-queue uses server-side `getAccessToken()`. Cookie parsing is less secure than server-side DAL. |
| **No CSRF on topic approve/reject** | Low | POST endpoints `/api/topics/{id}/approve|reject` don't require CSRF tokens, but JWT auth mitigates this. |
| **Gemini CLI shell execution** | Medium | Both `SemanticDeduplicatorService` and `AggregatorTranslationService` execute external Gemini CLI via `Symfony\Component\Process\Process`. Input is passed via `-p` flag. While not directly user-controlled, aggregated content from external sources flows into these prompts — potential for prompt injection. |
| **No rate limiting on admin endpoints** | Low | `/api/aggregator/stats`, `/api/topics/trending`, `/api/topics/proposals` have `ROLE_EDITOR` security but no rate limiting. |
| **User agents in config** | Info | Rotating user agents in `aggregator.yaml` are used for scraping, which is expected behavior but should be documented. |

---

## Final Recommendations

### P0 — Critical (blocks effective use of the system)

1. **Fix aggregator stats cache population** — The `GET /api/aggregator/stats` endpoint always returns `[]`. Either implement a service that writes stats after each `app:aggregator:run` execution, or make the endpoint query actual data (PressRelease counts by sourceType, latest timestamps, etc.) instead of relying on a cache key that nothing populates.

2. **Add aggregator dashboard to admin sidebar** — The page at `/{locale}/admin/aggregator` is unreachable from the admin navigation. Add it to `Sidebar.tsx` alongside Press Queue.

3. **Add "Run Now" button for aggregator** — Create a backend endpoint (e.g., `POST /api/aggregator/run`) that triggers `app:aggregator:run` asynchronously via Messenger, and add a button in the dashboard to call it. This is the most requested feature for editorial teams.

### P1 — Important (significantly improves admin experience)

4. **Create `#[ApiResource]` for RelevanceKeyword** — Expose CRUD endpoints for RelevanceKeyword to enable admin management of the 4-tier keyword system. Add a dedicated admin page.

5. **Persist aggregator run history** — Create an `AggregatorRun` entity with fields: `source`, `startedAt`, `finishedAt`, `articlesFound`, `duplicatesSkipped`, `errorsCount`, `triggeredBy`. Write to it in the command/handler. Display in dashboard.

6. **Fix auth pattern** — Migrate the aggregator dashboard from cookie-based JWT extraction to server actions with `getAccessToken()` from DAL, matching the press-queue page pattern.

7. **Schedule `app:aggregator:run`** — Add the aggregator run command to `EditorialScheduleProvider` on a configurable interval (e.g., every 2 hours), or document that it should be added to system cron.

8. **Add dedup statistics endpoint** — Create `GET /api/aggregator/dedup-stats` that queries PressRelease table for counts by dedup outcome (unique, duplicate, needs_review) and exposes in dashboard.

### P2 — Nice-to-have (enhances monitoring and configuration)

9. **Real-time status via Mercure** — Publish aggregator run progress to a Mercure topic (`deschide_news/aggregator/status`) and subscribe in the dashboard for live updates.

10. **Bulk topic operations** — Add "Select All" + "Bulk Approve/Reject" for topic proposals.

11. **Trend scoring configuration UI** — Allow editors to adjust source weights and decay parameters via an admin settings page.

12. **Error alerting** — Add a notification badge in the sidebar when aggregator errors exceed a threshold, or when no new content has been ingested for > 6 hours.

13. **Per-aggregator enable/disable UI** — Extend `aggregator.yaml` pattern to all aggregators and create an admin toggle page.

14. **Add Gemini input sanitization** — Sanitize or truncate content before passing to Gemini CLI in `SemanticDeduplicatorService` and `AggregatorTranslationService` to mitigate prompt injection from external article content.

---

*Report generated by automated audit. All findings are based on static code analysis of the repository at commit `c8ac7a4` on branch `develop`.*
