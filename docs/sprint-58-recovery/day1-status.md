# Sprint 58-Recovery — Day 1 Status (Backend + Infra scope)

**Branch**: `feature/sprint-58-recovery`
**Started**: 2026-04-24
**Owner**: Claude Code (Backend + Infra)
**Day 1 Status**: ✅ Discovery + ADR-026 draft complete; STOP awaiting Radu review.

## What Day 1 produced

All Day 1 deliverables live in the Obsidian vault (separate git repo at
`/mnt/c/Users/Radu/DeschideVault/`):

- `50_Audit/sprint-58-recovery-day1-discovery.md` — empirical disk-state audit
  (supervisor, queues, flags, tags, branches, PHPStan).
- `20_Architecture/Decisions/ADR-026-v1.3.0-recovery-tag-strategy.md` —
  release tag strategy + ADR-025 implementation reconciliation. **Status:
  Proposed.** Pending Radu acceptance.

This monorepo file exists as a marker so the recovery branch has a
meaningful first commit and so future readers of `git log` on this branch
know where the authoritative docs live.

## Headline findings (full detail in vault discovery)

1. **`v1.3.0` tag already exists** on abandoned branch
   `bugfix/story-clusters-frontend-debt-t52` (since 2026-04-07). User's
   Sprint 58-Recovery plan to ship as `v1.3.0` is blocked. ADR-026 §D1
   recommends **`v1.4.0`** instead.
2. **ADR-025 was paper-only** — no implementing branch, no
   `supervisor/disabled/` directory, no commented scheduler attributes.
3. **Master gate `editorial.pipeline.enabled = false`** IS enforced in DB,
   so the runtime pipeline IS suspended even though 15+ supervisor workers
   still RUN.
4. **Translation worker dead 27h** (since 2026-04-23 13:24) with
   `autostart=false`, `autorestart=false`. **951 messages stalled**, 139
   failed (mostly DI signature drift from S57.P2a refactor).
5. **PHPStan baseline drift**: 644 actual vs 22 documented in ADR-022.
6. **9 main-only commits** never back-merged to develop (reverse git-flow
   to reconcile in Day 5).

## Day 2 plan (NOT started — pending Radu OK)

Per user brief, Day 2 will:
- Move 15 editorial/briefing/topic worker configs to
  `/etc/supervisor/conf.d/disabled/`.
- Comment scheduler `#[AsCron]` / `#[AsPeriodicTask]` attributes with
  ADR-025/026 attribution headers (defense-in-depth; master flag already
  gates them).
- Resurrect `messenger-translations`: patch supervisor config
  (`autorestart=true`), triage 139 failed messages, drain 951 stalled
  messages, end-to-end smoke test.
- Reconcile feature flag schema with ADR-025 (per ADR-026 D3 amendments).

## Open Day 1 escalations for Radu

1. Confirm tag: `v1.4.0` (recommended) vs alternative (see ADR-026 §D1.alt).
2. Confirm: amend ADR-025 in-place via this ADR (D3) or rewrite as
   ADR-025-revised?
3. Confirm Day 5 reverse-merge approach: cherry-pick (a) vs `merge --no-ff`
   (b) for the 9 main-only commits.
4. Confirm location for `RELEASES.md` documenting orphaned `v1.3.x` tags
   (`apps/backend/`, repo root, or vault `60_Editorial/`?).

## Files modified Day 1

**Vault** (separate repo):
- `+ 50_Audit/sprint-58-recovery-day1-discovery.md`
- `+ 20_Architecture/Decisions/ADR-026-v1.3.0-recovery-tag-strategy.md`

**Monorepo** (this branch):
- `+ docs/sprint-58-recovery/day1-status.md` (this file)

**Untouched**: backend code, supervisor configs, DB, feature flags. Day 1
is strictly READ-ONLY on infrastructure per the Day 1 invariant.
