# Topic Classification — Developer Guide

How article → topic classification works in the Deschide News backend,
as of Sprint 51c.

## Current strategy (AI-only)

Classification calls Gemini 2.5 Flash via the existing `GeminiCliService`
wrapper. One CLI prompt carries up to 20 articles and asks for a JSON
array of `{articleId, topicIds}` mappings. The Sprint 51c pilot against
100 articles showed:

- 96% coverage (articles getting at least one topic link)
- 9/10 quality on editorial review
- ~0.25 USD extrapolated cost for the full 14k-article backfill

No keyword/rule-based layer runs anymore — see "Deprecated" below for
why.

## Services

| Component | Responsibility |
|---|---|
| `TopicDetectorService` | Single-article classification (Sprint 22). Sends one article + full topic tree to Gemini. Used by the AI ingestion pipeline for new articles. |
| `BatchTopicClassifier` | 20-at-a-time classification (Sprint 34). Used by the backfill command and any future bulk workloads. Sleep between chunks is 2 s as of Sprint 51c, down from 10 s. |
| `GeminiCliService` | Thin wrapper around the `gemini` CLI binary. Sprint 51c added per-call structured logging (`gemini_call`) with model/duration/tokens/cost plus a `getSessionStats()` aggregation so CLIs can print a cost summary. |
| `ArticleFactoryService::createFromPressRelease()` | When a press release is approved into an article (the other side of the pipeline), the PR's `PressReleaseTopic` rows are propagated to the new Article so it doesn't need a separate classification pass (Sprint 51c T51c.1). |

## CLIs

| Command | Purpose |
|---|---|
| `app:articles:classify-topics` | Backfill published articles missing topic links. NOT EXISTS filter on `article_topics` so the pilot's already-classified rows and any manual classifications are skipped. See `docs/operations/article-topic-backfill.md` for the runbook. |
| `app:topics:batch-classify` | Legacy CLI retained for the ingestion pipeline. Points at the same `BatchTopicClassifier` but targets press releases via a different query. |
| `app:topic:seed-sport` | Idempotent seeder (Sprint 51c T51c.4) that creates the Sport macro-topic root and 5 leaves with RO/EN/RU translations. Amended D2 in ADR-018 — the v2 taxonomy shipped with 10 roots and no sport coverage. |

## Admin UX

The admin articles list (`/{locale}/admin/articles`) exposes:

- **"Topics: Unclassified" chip** — toggles `?unclassified=1`. Pairs
  with the `ArticleUnclassifiedFilter` on the Article resource (OpenAPI
  discoverable) and the inline NOT EXISTS predicate in
  `ArticleProvider` (the filter chain is bypassed by the custom
  provider, same story as `category`/`status`/`isFeatured`).
- **Topics column with `TopicCountBadge`** — 0 → grey "No topics", 1–2
  → blue count, 3+ → indigo "3+". Clicking a row opens the existing
  edit flow where `TopicSelector` handles manual assignment.

## Observability pattern (future AI workloads)

Whenever you add a new Gemini invocation path, go through
`GeminiCliService::execute()` so the call inherits:

- `gemini_call` log line at INFO level with `model`, `duration_ms`,
  `input_tokens`, `output_tokens`, `cost_usd`.
- Session-wide aggregation via `getSessionStats()` which your CLI can
  surface in its end-of-run summary (see `ClassifyArticlesCommand` for
  the pattern).

Cost table (USD per 1M tokens, April 2026):

- `gemini-2.5-flash` — 0.075 in / 0.30 out
- `gemini-2.5-pro` — 1.25 in / 5.00 out

Token counting prefers the Gemini CLI envelope
(`response.usageMetadata.promptTokenCount` /
`candidatesTokenCount`) and falls back to a `chars/4` heuristic when
the envelope is absent.

## Deprecated

- `RuleBasedTopicClassifier` (removed in Sprint 51c T51c.2). Its slug
  map targeted the pre-Sprint 49 taxonomy (politica, economie, alegeri,
  romania, sport, etc.) which no longer exists in the current 164-topic
  v2 structure. The classifier logged a warning per article and
  skipped — effectively dead.
- `--skip-rule-based` and `--skip-ai` flags on
  `app:topics:batch-classify`. Removed. AI-only is the default and
  only mode.

## Files to know

- Services: `src/Service/Topic/BatchTopicClassifier.php`,
  `src/Service/Topic/TopicDetectorService.php`,
  `src/Service/Ai/Provider/GeminiCliService.php`
- CLIs: `src/Command/Topic/ClassifyArticlesCommand.php`,
  `src/Command/Topic/SeedSportTaxonomyCommand.php`,
  `src/Command/BatchClassifyTopicsCommand.php`
- Filter: `src/Filter/ArticleUnclassifiedFilter.php` (+ inline predicate
  in `src/State/ArticleProvider.php`)
- Admin UI:
  `apps/frontend/app/[locale]/admin/articles/ArticlesTableClient.tsx`,
  `apps/frontend/app/[locale]/admin/articles/components/TopicCountBadge.tsx`

## Related decisions

- ADR-018 — Article topic classification (D1 AI-only, D2 Sport
  taxonomy, D4 2 s sleep, D5 cost observability, D6 PR→Article topic
  propagation, D8 admin filter + badge).
