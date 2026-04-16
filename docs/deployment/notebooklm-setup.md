# NotebookLM Setup Guide (Sprint 51a)

## Prerequisites

1. **notebooklm-py** CLI installed (`pip install notebooklm`)
2. Authenticated session: `notebooklm login` (interactive, run manually)
3. Redis running (for fact-check cache)

## Environment Variables

Add to `.env.local`:

```bash
NOTEBOOKLM_ENABLED=false          # Set true when ready
NOTEBOOKLM_CLI_PATH=/home/radu/.local/bin/notebooklm
NOTEBOOKLM_BRIEFING_NOTEBOOK_ID=  # For daily briefing (legacy)
NOTEBOOKLM_WEEKLY_NOTEBOOK_ID=    # For weekly summary (legacy)
```

## AppSettings Keys (Database)

Sprint 51a seeds 6 keys in `app_settings`:

| Key | Default | Description |
|-----|---------|-------------|
| `notebooklm.enabled` | `false` | Master toggle |
| `notebooklm.sync.enabled` | `false` | Auto-sync toggle |
| `notebooklm.sync.max_sources` | `300` | Max sources per notebook |
| `notebooklm.sync.lookback_days` | `30` | Default lookback period |
| `notebooklm.factcheck.enabled` | `false` | Fact-check API toggle |
| `notebooklm.factcheck.cache_ttl` | `3600` | Cache TTL in seconds |

## Topic Notebook Sync

### Manual sync (single topic)

```bash
symfony console app:topic:sync-notebooks --topic=politica --since=30d
```

### Dry run (all topics)

```bash
symfony console app:topic:sync-notebooks --dry-run
```

### Scheduler

The sync runs automatically at **02:00 daily** via `EditorialScheduleProvider`.
Consumer: `symfony console messenger:consume scheduler_editorial -vv`

## Fact-Check API

```bash
# POST /api/admin/articles/{id}/factcheck
curl -s -X POST http://127.0.0.1:8081/api/admin/articles/123/factcheck \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"question": "Optional custom question"}' | jq
```

Returns 503 when disabled, 422 when article has no topics, 429 when rate-limited.

## Activation Checklist

1. Run `notebooklm login` (Radu, manually)
2. Set `NOTEBOOKLM_ENABLED=true` in `.env.local`
3. Update `notebooklm.enabled` to `true` in `app_settings`
4. Sync one topic: `symfony console app:topic:sync-notebooks --topic=<slug>`
5. Test fact-check: POST to `/api/admin/articles/{id}/factcheck`
6. Enable scheduler sync: set `notebooklm.sync.enabled` to `true`
