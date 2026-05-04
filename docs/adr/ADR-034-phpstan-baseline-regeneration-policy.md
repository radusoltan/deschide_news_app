---
adr: 34
title: PHPStan baseline regeneration policy
status: Accepted
date_proposed: 2026-05-03
date_decided: 2026-05-03
related: "T60.19, T60.20, ADR-033 (concurrent ADR), comprehensive-audit-2026-05-03.md"
author: "Radu (decided) + workflow-orchestrator (proposed)"
---

# ADR-034 — PHPStan baseline regeneration policy

## Status

Accepted (decided 2026-05-03).

## Context

PHPStan baseline file `apps/backend/phpstan-baseline.neon` went stale between 2026-04-24 (last regeneration) and 2026-05-03 (T60.19 discovery). During this nine-day window, the following work landed on `develop` without keeping the baseline in sync:

- PR #18 (agent-roster-v2 + chore/repo-cleanup-pre-FRA1)
- ADR-029 (Category slug i18n backfill — `ApplySlugTranslationsCommand`)
- ADR-030 (Server-side locale context priming)
- Sprint 57 (S57) contributions

Combined with a separate CI gap surfaced in T60.19 layers 1-2 (composer post-install needs `.env`, PHPStan Symfony plugin needs dev-debug container XML), PHPStan effectively stopped running as a CI gate from 2026-04-30 onward. By 2026-05-03 the gate had been silently bypassed for four merges to `develop`.

The T60.19 triage (`50_Audit/phpstan-baseline-regen-2026-05-03.md`) classified the 34 file-level errors that the baseline did not cover:

- **8 real bugs** — runtime issues, control flow, wrong signatures, missing match cases, undefined variables, broken DI attribute references.
- **23 quality regressions** — over-defensive checks, deprecated usages, dead code, tautological instanceof, redundant null-coalesce on always-existing offsets.
- **3 framework PHPDoc gaps** — API Platform 4.x added template parameters to `ProcessorInterface` that the project's `@implements` annotations had not picked up.

The T60.19 fix path required regenerating the baseline so v1.5.1 (FRA1 readiness release) could ship. Without this ADR codifying the conditions under which regeneration is acceptable, future contributors would have a precedent ("we regenerated last time when it was inconvenient") that erodes the gate further.

## Decision

**Baseline regeneration is acceptable ONLY when ALL five conditions hold.**

1. **Pre-existing scope.** The errors absorbed are not introduced by the regenerating PR. Confirmed by git blame on the offending lines or by chronology — every absorbed error must trace to a commit that landed BEFORE the regenerating PR's first commit.
2. **No real bugs absorbed.** Errors classified as runtime risks MUST be fixed in commits that land BEFORE the regen commit. The following PHPStan identifiers are categorically real bugs and ineligible for baseline absorption:
    - `arguments.count` (wrong number of arguments to a method)
    - `argument.type` (type mismatch on an argument the callee will use)
    - `match.unhandled` (a match expression that throws `UnhandledMatchError` at runtime)
    - `variable.undefined` (control flow can read an unassigned variable)
    - `attribute.notFound` (PHP attribute references a class that does not exist)
    - any other identifier whose runtime semantics is "the program will crash on this code path"
   The triage step that produces the categorisation IS itself a deliverable and must be linked from the regen commit.
3. **Ratchet partner exists.** A follow-up task is created in the same sprint, priority P1 minimum, with an explicit goal of reducing the baseline back to or below the pre-regeneration size. T60.20 sets the precedent.
4. **Audit doc.** A markdown audit at `50_Audit/phpstan-baseline-regen-{YYYY-MM-DD}.md` lists categorised entries, rationale per category, and the ratchet plan. The doc must include raw counts (`wc -l` on the baseline file) — not summarised numbers — so future audits cannot misread the gate's strictness.
5. **Commit body.** The regen commit's body references the audit doc, the ratchet task ID, and this ADR. Without those three references the commit fails review.

## Consequences

### Allowed
- T60.19's regen commit is the first to satisfy all five conditions and serves as the canonical example.
- Future PRs that ratchet the baseline DOWN do not need to satisfy these conditions — only ratcheting UP triggers the gate.
- One-off events (PHPStan major upgrade, framework major version bump) can regenerate as a single bookkeeping commit, bypassing condition 3 (no ratchet partner needed) IF a separate ADR amendment justifies the bypass.

### Forbidden
- Adding new entries to the baseline as part of an unrelated feature PR. Even one entry ratchets the gate up and must be justified under this ADR.
- Regenerating the baseline to mask a real bug ("just absorb it now, fix later") — condition 2 is non-negotiable. The 5 real-bug commits in T60.19 are the precedent: real bugs always land before regen.
- "Lazy" regeneration that doesn't include a triage doc. The categorisation is the safety mechanism; without it, real bugs leak into the absorbed set.

### Detection
- Stale-baseline drift is detectable: monthly check that `phpstan-baseline.neon` mtime is within 30 days of HEAD on `develop`. Surfaced in sprint planning.
- A PR that grows baseline without satisfying conditions 1-5 fails review (this is reviewer-enforced, not a CI gate, since CI cannot reason about ratchet partner tasks).

## Reverse-decision triggers

This ADR must be revisited (and likely superseded) if any of the following becomes true:

- **PHPStan major upgrade** (e.g., level 7 → level 8). A bulk regen as part of the upgrade is acceptable as a one-time event, with a separate ADR amendment.
- **Framework major version bump** (Symfony 8 → 9, Doctrine ORM 3 → 4, API Platform 4 → 5). Framework-knowledge category may regenerate as platform-knowledge update, also with explicit amendment.
- **Adopting strict mode** (`treatPhpDocTypesAsCertain: false`, level 9, etc.). May invalidate the current baseline structure entirely.
- **Discovery that a real-bug identifier was previously absorbed.** Triggers a focused fix + amendment to clarify the categorisation rules.

## References

- `50_Audit/phpstan-baseline-regen-2026-05-03.md` — full triage (34 errors classified)
- `50_Audit/comprehensive-audit-2026-05-03.md` Section 8 (Post-audit revisions, including the "21 entries" misread correction)
- T60.19 commit chain on `bugfix/T-CI-ENV-bootstrap-env-on-ci`:
  - `3516db3` — fix ArticleContextService PUBLISHED_FULL match
  - `ddec288` — fix AiMercureService signatures (UUID conversation IDs)
  - `10ade33` — fix TaggedIterator → AutowireIterator (Symfony 8)
  - `1f7881c` — fix $sensitiveTopicSlugs initialisation
  - `96f97f2` — fix EntityManager::clear() arg (Doctrine ORM 3.x)
  - regen commit (TBD) — absorbs 26 quality + framework entries
- T60.20 — Ratchet PHPStan baseline post-Sprint 60 (created 2026-05-03 in Notion)
- ADR-019 — StoryCluster hard-drop (one of the upstream sources of velocity that outpaced the gate)
