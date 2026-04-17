# Article Topic Backfill — Operator Runbook

Sprint 51c ships the machinery; this runbook is how you actually run the
backfill against a populated database. Amended D2 in ADR-018 (2026-04-17)
adds the Sport taxonomy prerequisite — run that once per environment
before the first classification pass.

## When to run

- After Sprint 51c lands on `develop` — the initial overnight backfill.
- Periodically on dev when new imports accumulate unclassified articles.
- Immediately after a taxonomy change (new topics added, slugs renamed).
- Not on prod until the team signs off on dev results (see "Production
  strategy" below).

## Prerequisites

- Valid Gemini CLI session (`gemini auth status` — "Signed in as …").
- Backend app boots (`symfony server:status` is fine either way; the
  command doesn't need the HTTP server).
- Dev database is in a known state. If unsure, run
  `scripts/reset-test-db.sh` or `app:dev:reset` per CLAUDE.md.
- At least 1 GB free disk for the log file.
- Sport taxonomy seeded:

  ```bash
  cd apps/backend
  symfony console app:topic:seed-sport
  # Idempotent. Safe to rerun; only writes if the slugs don't already exist.
  ```

- Estimated runtime for the full 14k article set: ~6.3 h on Gemini 2.5
  Flash at the 2 s inter-batch sleep (Sprint 51c tuning, down from 10 s).
- Estimated cost at pilot rates: ~0.25 USD per full run. The command
  prints the actual cost in its end-of-run summary.

## Invocation

Always dry-run against a small sample first so the target set looks right:

```bash
cd apps/backend
symfony console app:articles:classify-topics --dry-run --limit=100
```

The output lists every candidate article (ID + trimmed title). Scan it
for surprises — unpublished rows shouldn't appear; already-classified
articles should be skipped by the NOT EXISTS filter.

For the overnight run (nohup so it survives SSH drops):

```bash
cd apps/backend
nohup symfony console app:articles:classify-topics \
  --batch-size=20 \
  > /tmp/sprint-51c-backfill.log 2>&1 &

echo $! > /tmp/sprint-51c-backfill.pid
```

### Useful flags

| Flag | Purpose |
|---|---|
| `--limit=N` | Cap total articles processed (0 = all). Use a small value for a smoke test. |
| `--offset=N` | Skip the first N unclassified rows. Use to resume after a crash. |
| `--batch-size=N` | Articles per Gemini prompt (default 20). Lower if you're hitting 429s. |
| `--category=ID` | Restrict to one category (e.g. `--category=7` for sport). |
| `--dry-run` | List what would be processed; no DB writes, no Gemini calls. |
| `--confidence-threshold=F` | Reserved for future per-mapping threshold — currently a no-op with a warning. |

## Monitoring

Tail the log from another shell:

```bash
tail -f /tmp/sprint-51c-backfill.log
```

You'll see:

- A ProgressBar with `chunk N/M | classified: X | skipped: Y | failed: Z`.
- A `checkpoint:` line every 100 classified articles.
- A second warning (`consider rerunning later`) if two chunks fail in a
  row — the internal retry in BatchTopicClassifier already handles single
  transient failures.

At the end, the command prints two tables:

- **Backfill Summary** — targeted count, classified, skipped, total
  assignments, failed chunks, wall time. If the GeminiCliService is
  wired (it is in the backend's default container), the summary also
  includes total calls, input tokens, output tokens and cost in USD.
- **Top topics hit** — top 10 topics that got the most assignments. Use
  it as a sanity check: if 95% of hits land on `republic-of-moldova`,
  the classifier is being lazy and you should review the prompt.

## Failure recovery

If the process dies mid-run:

1. Note the highest completed offset from the log (search for the last
   `checkpoint:` line or the Progress line).
2. Kill anything stale: `kill $(cat /tmp/sprint-51c-backfill.pid)`.
3. Resume: `symfony console app:articles:classify-topics --offset=<N>`.

The NOT EXISTS query filters out already-classified articles each run,
so re-running with a stale offset is safe — you just waste a little
extra SQL on the filter side.

If Gemini 429s persist (every chunk fails), stop the run and wait for
the quota window to reset. Don't try to burn through — the cost doesn't
go down and the risk of partial writes goes up.

## Post-run validation

Coverage:

```bash
symfony console dbal:run-sql "
SELECT
  (SELECT COUNT(DISTINCT article_id) FROM article_topics) AS classified,
  (SELECT COUNT(*) FROM articles WHERE status = 'published') AS total
"
```

Spot-check 20 random assignments:

```bash
symfony console dbal:run-sql "
SELECT a.id, LEFT(a.title, 60) AS title, STRING_AGG(t.slug, ', ') AS topics
FROM articles a
JOIN article_topics at ON at.article_id = a.id
JOIN topics t ON t.id = at.topic_id
GROUP BY a.id, a.title
ORDER BY RANDOM()
LIMIT 20
"
```

Acceptance bar: at least 17 of 20 pairings look editorially sensible.
Flag the odd ones for a manual triage pass using the new Unclassified
chip in the admin articles list (T51c.6).

## Production strategy (Sprint 52+)

Three options, no default yet:

1. **Re-run against prod DB** after production deploy. Same ~0.25 USD,
   same ~6.3 h window. Pros: deterministic, straightforward. Cons:
   spends tokens twice; prod content may drift from dev after the run.
2. **Dump & load dev `article_topics`** into prod. Pros: zero token
   cost. Cons: cross-environment writes, risky if prod content isn't a
   strict subset of dev.
3. **Schedule a periodic catch-up** (cron + nohup invocation) after
   production deploy. Covers imports on rolling basis. Cons: coverage
   lag between import and classification.

Record the chosen strategy in ADR-018 Sprint 52 amendment before acting.
