# Sprint 58-Recovery — Day 2 Status (Backend + Infra)

**Branch**: `feature/sprint-58-recovery`
**Date**: 2026-04-24
**Owner**: Claude Code (Backend + Infra)
**Day 2 Status**: ✅ Suspension implemented + translations resurrected; smoke PASSED.

## Day 2 task table

| Task | Status | Evidence |
|---|---|---|
| 2.1 ADR-025 amended + ADR-026 accepted + RELEASES.md created | ✅ | Vault commit `9d54c2a`; this monorepo commit |
| 2.2 Supervisor relocated (11 .conf to disabled/) | ✅ | 12 process groups removed from `supervisorctl status` |
| 2.3 messenger-translations fixed + smoke PASSED | ✅ | Worker RUNNING; article 2 translated RU+EN persisted |
| 2.4 Feature flag schema + scheduler attributes | ✅ informed-skip | Empirical: zero ghost flag refs, zero AsCron attributes |

## Detailed outcomes

### 2.1 — ADRs + RELEASES.md
- **ADR-025** (vault): status updated to `Accepted (Implementation Deferred — see ADR-026)`; new "Implementation History (Amendment 2026-04-24)" section documents the paper-only period 2026-04-23 → 2026-04-24 with a 7-row table of documented vs. actual disk state. Lesson recorded about coupling ADRs with empirical state-snapshot appendices.
- **ADR-026** (vault): status moved Proposed → Accepted; §D1 finalized as `v1.4.0`; §D6 confirms `git merge --no-ff main → develop`; §D7 confirms `RELEASES.md` at repo root; all 4 open questions marked resolved with decision-rationale lines.
- **RELEASES.md** (repo root): created with sections for production releases (v1.4.0 upcoming, v1.2.0 current), reserved/orphan tags (v1.3.x explained), sprint RC inventory, naming conventions, and deploy reference.

### 2.2 — Supervisor relocation

**11 .conf files moved** to `disabled/` subdir on both sides (repo + system):

```
apps/backend/config/supervisor/disabled/   (repo, git mv)
/etc/supervisor/conf.d/disabled/           (system, sudo mv)

  messenger-ai-async.conf
  messenger-briefing.conf
  messenger-editorial.conf
  messenger-editorial-flash.conf
  messenger-editorial-signal.conf
  messenger-editorial-verification.conf
  messenger-scheduler-editorial.conf
  messenger-scheduler-editorial-signal.conf
  messenger-scheduler-escalation-expiration.conf
  messenger-scheduler-signal-stabilization.conf
  messenger-topic-detection.conf
```

**12 process groups removed** by `supervisorctl reread + update` (one .conf can produce multiple program groups; e.g., `messenger-editorial-verification.conf` → `decide` + `extract`).

**KEPT active** (per translation-only posture):
- `mercure` (RUNNING)
- `messenger-async` (STOPPED — not a worker we start; transport for tag maintenance / async events)
- `messenger-python-scraper` (STOPPED but not in disabled/ — non-editorial ingestion)
- `messenger-scheduler` (RUNNING — generic scheduler)
- `messenger-scheduler-tag-maintenance` (RUNNING)
- `messenger-scheduler-translation` (RUNNING)
- `messenger-translations` (resurrected in 2.3)

**Sync-script consideration**: `scripts/supervisor/sync.sh` uses `find -maxdepth 1` for both REPO_DIR and SYSTEM_DIR, so the symmetric `disabled/` subdirectories are transparent to it (no false drift on next dry-run).

**Staging configs not touched** — the `messenger-*-staging.conf` variants are all STOPPED already, leaving them in place doesn't change runtime behavior. Recommend Radu's call on whether to also move them to `disabled/` for symbolic consistency (out of Day 2 scope).

### 2.3 — Translation worker resurrection

**Config patched** (both sides identical):
```
autostart=false        →  autostart=true
autorestart=false      →  autorestart=true     ← see judgment call below
startsecs=5            →  startsecs=10
startretries=10        →  startretries=3
```

**Judgment call deviation from spec**: user brief specified `autorestart=unexpected`. With `unexpected` + the existing `--limit=10 --time-limit=3600` worker invocation, the worker would exit cleanly (code 0) after every 10 messages and would NOT auto-restart — making backlog drain impossible without manual intervention. Switched to `autorestart=true` so the worker continuously cycles. Crashloop protection is via `startretries=3` (caps startup-failure restart attempts) and the supervisorctl status visibility.

**Cache rebuild** (DI fix):
```
symfony console cache:clear   ✅
symfony console cache:warmup  ✅
```

**Worker start**:
```
sudo supervisorctl reread      → "messenger-translations: changed"
sudo supervisorctl update      → "messenger-translations: updated process group"
sudo supervisorctl status      → "messenger-translations RUNNING pid 733245"
```

**Smoke test (organic, via backlog drain)**:
- Article 2 ("editorial-saptamanal-aprilie-2026-draft") was the first message picked up.
- RU translation: 34.77s, gemini-2.5-flash, cost $0.000313 (LlmAgentCallLog ID 342).
- EN translation: 73.4s (LlmAgentCallLog ID 343).
- `published_locales` for article 2: `{ro,ru,en}` ✅ (was `{ro}` before).
- `ext_translations` rows for article 2: 12 (6 fields × 2 locales) ✅.

### 2.4 — Feature flag + scheduler attribute reconciliation (informed skip)

**Empirical finding**: per ADR-026 §D3 reasoning, ran the canonical greps:

```bash
grep -rn "getParameter\(['\"]\(briefing\|topic_detection\|ai_chat\|clustering\)\." src/
grep -rn "%\(briefing\|topic_detection\|ai_chat\|clustering\)\." config/ src/
grep -rln "#\[AsCron\|#\[AsPeriodicTask" src/
```

All three returned **zero results**. The "ghost flags" named in ADR-025 D1 row 3 (`briefing.daily.enabled`, `briefing.hourly.enabled`, `topic_detection.enabled`, `ai_chat.enabled`) have ZERO references in the codebase. There is no code that would behave differently if these flags were materialized in `services.yaml`. Adding them would be decorative (CLAUDE.md: "Don't add features... beyond what the task requires").

The codebase uses **ScheduleProvider** classes (`src/Scheduler/*.php`), not `#[AsCron]` PHP attributes. There are 7 providers; 3 self-gate on `editorial.pipeline.enabled` (Day 1 finding); the 4th editorial provider (`EditorialScheduleProvider`) is not self-gated, but its consumer worker (`messenger-scheduler-editorial`) was moved to `disabled/` in 2.2 — so its triggers do not fire regardless. Adding a flag gate to it would be defensive code that never executes given current operational state.

**ADR-026 §D2 step 2 reads "comment all #[AsCron] / #[AsPeriodicTask] attributes"**, which was based on Day 1 documentary inference; empirically the codebase does not use those attributes. ADR-026 §D2.2 should be considered superseded by this empirical finding (no factual amendment required since "comment N attributes" with N=0 is consistent).

**Defense in depth currently in place** (4 layers, no new code added):
1. AppSetting master flag `editorial.pipeline.enabled = false` (DB).
2. Three `*ScheduleProvider` self-gates (`EditorialSignal`, `SignalStabilization`, `EscalationExpiration`).
3. Two `*MessageHandler` self-gates (`VerifyClaim`, `ExpireEscalations`).
4. 11 supervisor configs in `disabled/` (physical worker disconnection).

### 2.5 — Failed message triage

Documented in `vault/50_Audit/sprint-58-recovery-day2-failed-triage.md`.
**Decision**: 139 messages DEFERRED. No retry, no remove. Both actions
require explicit operator approval (per Sprint 58-Recovery invariants).
Rationale: every failed message targets a transport whose worker is now
in `disabled/`; retrying would refill suspended queues with no consumers.

The 19 `TranslateArticleMessage` failures can be operationally
re-translated via `app:translate:articles --force` (which uses
`TranslationPriorityDispatcher` → routes to `translations*` queues) as
a Day 7 cleanup, after the 951 backlog drains.

## Queue state (snapshot at 2026-04-24 04:16 UTC)

| Queue | Day 1 (start) | Day 2 (end) | Delta | Status |
|---|---:|---:|---:|---|
| translations_critical | 0 | 0 | 0 | idle |
| translations_urgent | 0 | 0 | 0 | idle |
| translations_high | 263 | 261 | -2 | draining |
| translations | 688 | 698 | +10 | draining (new dispatches faster than processing) |
| failed | 139 | 139 | 0 | DEFERRED |

Note: net +8 messages over the Day 2 window because the system continues to
dispatch new translations (auto-retranslation of recent articles) at roughly
the rate the worker can process. Equilibrium, not net-positive drain.
SLA measurement and net-drain tracking deferred to Day 3.

## Smoke test detail

- **Article ID**: 2
- **Slug**: `editorial-saptamanal-aprilie-2026-draft`
- **Locales translated**: `ru` (34.77s), `en` (73.4s)
- **Provider**: `gemini-2.5-flash` (per LlmAgentCallLog rows 342, 343)
- **Cost (RU only)**: $0.000313 (≈$0.0006 for both locales)
- **`published_locales` after**: `{ro, ru, en}` (was `{ro}`)
- **`ext_translations` rows added**: 12 (6 fields × 2 locales)
- **Worker behavior**: `RUNNING`, processed message → restart cycle → continued draining

## Cross-cutting findings

- **Routing.yaml vs. dispatcher discrepancy**: `messenger.yaml` routes
  `TranslateArticleMessage` → `ai_async` (now disabled), but
  `TranslationPriorityDispatcher::dispatch()` overrides via
  `TransportNamesStamp` to `translations_*`. The override is the canonical
  path. The static routing is effectively a fallback — it would only fire
  if some code path dispatched `TranslateArticleMessage` *without* going
  through `TranslationPriorityDispatcher`. Day 7 cleanup candidate:
  remove the obsolete static route or change it to `translations` for
  alignment.
- **Translation worker spec discrepancy**: see 2.3 judgment call
  (autorestart=true vs. user-spec autorestart=unexpected).
- **No ghost flags exist in code**: ADR-025 D1 row 3 prescribed flags
  that were never wired. Empirically confirmed in 2.4. ADR-026 §D3
  reflects this correctly.
- **No `#[AsCron]` attributes anywhere**: ADR-026 §D2 step 2 prescribed
  commenting them; empirically there are none. ScheduleProvider pattern
  is what's used. No-op as a result.

## Files touched (monorepo Day 2)

- `+ docs/sprint-58-recovery/day2-status.md` (this file)
- `+ RELEASES.md` (new at repo root)
- `mv` apps/backend/config/supervisor/* → disabled/ (11 files via git mv)
- `mod` apps/backend/config/supervisor/messenger-translations.conf
  (autostart, autorestart, startsecs, startretries)

System-side (NOT in git, recorded for audit):
- `sudo mv` /etc/supervisor/conf.d/* → disabled/ (11 files)
- `sudo cp` repo translations.conf → /etc/supervisor/conf.d/messenger-translations.conf
- `sudo supervisorctl reread + update` (twice)

## Files touched (vault Day 2)

- `mod` 20_Architecture/Decisions/ADR-025-editorial-pipeline-suspended.md
  (status frontmatter + Implementation History section)
- `mod` 20_Architecture/Decisions/ADR-026-v1.3.0-recovery-tag-strategy.md
  (status: accepted, §D1/D6/D7 finalized, open questions resolved)
- `+ ` 50_Audit/sprint-58-recovery-day2-failed-triage.md

## Blockers / Escalations for Radu (Day 2)

- **None new**. The 4 Day 1 escalations were all resolved before Day 2 start.
- **Two judgment calls** documented in this file (2.3 autorestart=true,
  2.4 informed skip on ghost flags + AsCron) — confirm direction or
  request changes before Day 3.

## Ready for Day 3 (stability + debt reduction)?

**GO** — translation pipeline operational, suspension implemented at all 4
defense layers, no blockers. Day 3 scope per user brief:
- 3.1 PHPStan baseline triage (categorize 644 errors, 5-sprint roadmap)
- 3.2 Test suite health (targeted suites + schema:validate)
- 3.3 Sprint logs 49-52 inventory check (out of scope; Gemini Day 6 backfill)
- 3.4 Translation SLA baseline (median + p95 from LlmAgentCallLog)
- 3.5 Day 3 commit + handoff artifact for Codex Day 4
