# Staging supervisor configs (local staging topology)

**Scope:** ADR-023 D7 — local staging on same WSL host as dev, isolated via
`APP_ENV=staging` + prefixed indices + dedicated DB + Redis DB 2.

## Invariants

- All programs are `autostart=false` — SV scenarios (T57.09–T57.14) start
  individual workers per scenario; idle staging consumes zero resources.
- `autorestart=true` — once started, workers restart on crash so scenarios
  don't need to babysit health.
- `environment=APP_ENV=staging,...` is **mandatory** — without this, Symfony
  Dotenv loads `.env.local` (dev values) instead of `.env.staging` +
  `.env.staging.local`.
- Log directory: `/var/log/deschide/staging/` (operator must create with
  `mkdir -p` + `chown radu:radu` before first start).

## Install sequence

1. These files live in repo under `apps/backend/config/supervisor/staging/`
2. `scripts/supervisor/sync.sh --apply --reason="..."` copies them to
   `/etc/supervisor/conf.d/` (installed flat, no subdirectory on system side)
3. `sudo supervisorctl reread && sudo supervisorctl update`

## Files

| File | Programs | Notes |
|------|----------|-------|
| `messenger-ai-async-staging.conf` | `messenger-ai-async-staging` | `ai_async` transport |
| `messenger-briefing-staging.conf` | `messenger-briefing-staging` | `briefing` transport |
| `messenger-editorial-flash-staging.conf` | `messenger-editorial-flash-staging` | `editorial_flash`, numprocs=2 |
| `messenger-editorial-signal-staging.conf` | `messenger-editorial-signal-staging` | `editorial_signal_ingest` |
| `messenger-editorial-verification-staging.conf` | `messenger-editorial-verification-extract-staging` (numprocs=4) + `messenger-editorial-verification-decide-staging` (numprocs=2) | 2 programs, 1 file |
| `messenger-topic-detection-staging.conf` | `messenger-topic-detection-staging` | `topic_detection` transport, `--limit=100` |
| `messenger-scheduler-staging.conf` | `messenger-scheduler-default-staging` + 6 typed scheduler consumers | 7 programs, 1 file |
| `messenger-python-scraper-staging.conf` | `messenger-python-scraper-staging` | `python_scraper scheduler_python_scraper`, `--limit=10` |
| `symfony-serve-staging.conf` | `symfony-serve-staging` | `symfony serve -d --port=8001 --no-tls`, APP_ENV=staging |
| `nextjs-staging.conf` | `nextjs-staging` | `next start -p 3001`, sources `.env.staging` + `.env.staging.local` |
| `staging-group.conf` | `[group:staging]` | Group wrapper over all above |
