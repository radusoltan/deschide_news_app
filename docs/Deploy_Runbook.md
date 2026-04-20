# Deploy Runbook — Deschide News

Operational procedures for deploying and maintaining Deschide News in production.

This file documents **runtime operational flows** that must be available to on-call without a code deploy. For full deploy instructions (server provisioning, migrations, TLS, etc.) see `docs/GHID_DEPLOY_DESCHIDE_WSL.md` and `scripts/DEPLOY_README.md`.

---

## Emergency halt procedure

Use this procedure when the editorial pipeline produces incorrect output in production and must be stopped **immediately**, without waiting for a code deploy.

### Trigger conditions

- LLM producing offensive/defamatory content that L4 guards did not catch.
- Cost runaway (unexpected LLM spend spike).
- Mass-escalation storm (editor queue flooded).
- Any scenario where "better silence than current output" applies.

### Why a dedicated flag (not `editorial.pipeline.enabled`)

`editorial.pipeline.enabled=false` gates **message emission** (schedulers stop ticking, upstream handlers stop dispatching). It does **not** drain messages already sitting in the queue — those would still be consumed, still invoke LLMs, and still emit Articles / escalation rows.

`editorial.emergency_halt=true` short-circuits the 4 consumer handlers at their `__invoke()` entry, **before any LLM call or guard invocation**. In-flight messages are silent-ACKed and dropped from the pipeline's perspective.

### Procedure

```bash
cd /var/www/deschide_news_app/apps/backend

# 1. Flip the circuit breaker (silent-ACK all pipeline messages).
symfony console app:settings:set editorial.emergency_halt true --type=bool

# 2. Verify the flag is live. Expected output: `true`
symfony console app:settings:get editorial.emergency_halt

# 3. Stop editorial message consumers to prevent queue buildup.
#    (Adjust the program pattern to match your supervisor config.)
sudo supervisorctl stop messenger-editorial:*

# 4. Verify queue state (inspect backlog; do NOT purge — preserve for post-mortem).
symfony console messenger:stats

# 5. Investigate via structured logs:
#    - Handler short-circuits emit `emergency_halt.triggered` at INFO level
#    - Each log row carries: handler, message_class, message_id_hint
tail -f var/log/editorial.log | grep emergency_halt.triggered

# 6. Once the fix is deployed (or manual intervention is complete):
symfony console app:settings:set editorial.emergency_halt false --type=bool
sudo supervisorctl start messenger-editorial:*

# 7. Sanity-check all editorial flags after restart.
symfony console app:settings:list --prefix=editorial.
```

### Handlers gated by the flag

| Handler | Message | message_id_hint field |
|---------|---------|-----------------------|
| `WriteFlashMessageHandler` | `WriteFlashMessage` | `primarySignalId` |
| `WriteDevelopingStoryMessageHandler` | `WriteDevelopingStoryMessage` | `articleId` |
| `VerifyClaimMessageHandler` | `VerifyClaimMessage` | `topicHash` |
| `AggregateSignalsMessageHandler` | `AggregateSignalsMessage` | `topicHash` |

### Notes

- `emergency_halt=true` causes handlers to ACK messages **silently**. Messages are NOT requeued — they are dropped from the pipeline's perspective. Upstream message accumulation is bounded by RabbitMQ TTL / queue-length policies.
- `editorial.pipeline.enabled=false` does NOT drain in-flight messages. Use `emergency_halt` for mid-run halts.
- The structured log entry `emergency_halt.triggered` is the audit trail. For post-mortem, query via `LlmAgentCallLog` (once T56.09 lands) or grep logs for the structured field `handler` to count dropped messages per handler.
- Default state: `editorial.emergency_halt=false` (seeded by `AppSettingsFixture`, adjacent to `editorial.pipeline.enabled`).
- Flag semantics: per `App\Repository\AppSettingRepository::getBool()`, any value in `FILTER_VALIDATE_BOOLEAN`'s true-set (`true`, `1`, `on`, `yes`) triggers the halt. Use `'true'` / `'false'` as literal strings for consistency with other seeds.

---

## Supervised message consumers

Canonical inventory of supervised Symfony Messenger consumers as of Sprint 56 T56.03. Repo-versioned conf files live in `apps/backend/config/supervisor/`; the production supervisor reads them from `/etc/supervisor/conf.d/` (symlinks or copies maintained by the deploy step — see the reload procedure below).

### Editorial pipeline consumers (`messenger-editorial-*`)

| Consumer | Transport(s) | Role |
|---|---|---|
| `messenger-editorial-signal` | `editorial_signal_ingest` | L1 — signal intake fan-in |
| `messenger-editorial-verification-extract` | `editorial_verification_extract` | L2 — extract signal features |
| `messenger-editorial-verification-decide` | `editorial_verification_decide` | L2 — decide claim verdict |
| `messenger-editorial-flash` | `editorial_flash` | L3 — WriteFlash + WriteDevelopingStory writers |
| `messenger-editorial` | `editorial` | legacy queue (kept autostart=false until drained) |

### Scheduler tick consumers (`messenger-scheduler-*`)

All scheduler providers must have a matching consumer so `supervisorctl status` reflects a complete view of scheduled work.

| Consumer | Transport | Schedule provider | Sprint |
|---|---|---|---|
| `messenger-scheduler` | `scheduler_default` | (PublishScheduledArticles etc.) | pre-S53 |
| `messenger-scheduler-editorial-signal` | `scheduler_editorial_signal` | `EditorialSignalScheduleProvider` | S53 T53.9 |
| `messenger-scheduler-escalation-expiration` | `scheduler_escalation_expiration` | `EscalationExpirationScheduleProvider` | S55 T55.11 |
| `messenger-scheduler-signal-stabilization` | `scheduler_signal_stabilization` | `SignalStabilizationScheduleProvider` | S54 T54.6 |
| `messenger-scheduler-tag-maintenance` | `scheduler_tag_maintenance` | `TagMaintenanceScheduleProvider` | — |
| `messenger-scheduler-editorial` | `scheduler_editorial` | `EditorialScheduleProvider` | S22+ |
| `messenger-scheduler-translation` | `scheduler_translation` | `TranslationScheduleProvider` | S51 |

### Other consumers

| Consumer | Transport(s) | Role |
|---|---|---|
| `messenger-async` | `async scraping python_scraper cache_async stats_async` | generic async pool |
| `messenger-ai-async` | `ai_async` | LLM async (topic detection, translations, background) |
| `messenger-translations` | `translations_critical translations_urgent translations_high translations` | priority translation queue |
| `messenger-python-scraper` | `python_scraper scheduler_python_scraper` | Python scraper bridge |
| `messenger-briefing` | `briefing` | daily press briefing generation |
| `messenger-topic-detection` | `topic_detection` | topic classification batch |

### Reload procedure

After adding or editing supervisor conf files, sync them to the system supervisor directory and reload:

```bash
# 1. Sync repo confs → system (adjust to your deploy's symlink/copy strategy)
sudo cp /var/www/deschide_news_app/apps/backend/config/supervisor/*.conf /etc/supervisor/conf.d/

# 2. Clear Symfony cache BEFORE reload so new commands (e.g. app:settings:*) are discoverable
symfony console cache:clear --env=prod

# 3. Pick up new/edited confs and apply
sudo supervisorctl reread
sudo supervisorctl update

# 4. Verify all expected consumers are RUNNING
sudo supervisorctl status
```

**Note:** `supervisorctl reread` detects changes; `supervisorctl update` starts new programs and stops removed ones. Neither restarts already-running programs with unchanged configs. Use `supervisorctl restart <program>` explicitly when a conf change requires a worker restart (e.g. new `--memory-limit` flag).
