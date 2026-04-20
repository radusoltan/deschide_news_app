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
#    No dedicated CLI command exists yet — the flag is stored in app_settings
#    as a plain TEXT row, so direct SQL is the supported path.
symfony console doctrine:query:sql \
  "UPDATE app_settings SET value = 'true' WHERE key = 'editorial.emergency_halt'"

# 2. Stop editorial message consumers to prevent queue buildup.
#    (Adjust the program pattern to match your supervisor config.)
sudo supervisorctl stop messenger-editorial:*

# 3. Verify queue state (inspect backlog; do NOT purge — preserve for post-mortem).
symfony console messenger:stats

# 4. Investigate via structured logs:
#    - Handler short-circuits emit `emergency_halt.triggered` at INFO level
#    - Each log row carries: handler, message_class, message_id_hint
tail -f var/log/editorial.log | grep emergency_halt.triggered

# 5. Once the fix is deployed (or manual intervention is complete):
symfony console doctrine:query:sql \
  "UPDATE app_settings SET value = 'false' WHERE key = 'editorial.emergency_halt'"
sudo supervisorctl start messenger-editorial:*
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
