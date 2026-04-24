# Deschide.md — Release History

Authoritative timeline of release tags. Maintained by hand because tag
order alone (`git tag --sort=-v:refname`) does not encode the difference
between production releases, sprint RCs, and orphaned bugfix-branch tags.

> When in doubt about which tag to deploy, **use the most recent tag listed
> under "Production releases" below**. RCs and orphan tags should never be
> deployed to production.

---

## Production releases

### v1.4.0 — _upcoming_ (Sprint 58-Recovery)

- **Status**: in progress on `feature/sprint-58-recovery`
- **Posture**: Translation-only — editorial pipeline suspended per
  [ADR-025] / implemented by [ADR-026]
- **Target merge**: `main` after Day 7 re-audit
- **Headline changes**:
  - 11 editorial / briefing / topic / ai_async supervisor configs moved
    to `disabled/` subdirectory (mirrored in `apps/backend/config/supervisor/disabled/`
    and `/etc/supervisor/conf.d/disabled/`).
  - `messenger-translations` worker resurrected (`autostart=true`,
    `autorestart=unexpected`); 951 stalled messages drained, 139 failed
    triaged.
  - Feature flag schema reconciled with ADR-025 D1 (5 ghost flags
    materialized as explicit `false` in `services.yaml`).
  - Editorial scheduler `#[AsCron]` / `#[AsPeriodicTask]` attributes
    commented with ADR-025/026 attribution (defense-in-depth on top of
    master flag).
  - PHPStan baseline acknowledged at 644 (was documented at 22 in
    ADR-022; 5-sprint cleanup roadmap deferred).
  - 9 main-only commits (v1.2.0 release + 8 follow-ups) back-merged to
    develop.
- **Reference**: [ADR-026](20_Architecture/Decisions/ADR-026-v1.3.0-recovery-tag-strategy.md)
  in Obsidian vault.

### Known Issues (deferred to S+1)

- **Author page locale inconsistency**: /en/author/{slug} and
  /ru/author/{slug} render RO article content without fallback notice
  when backend returns unfiltered articles under non-RO locale header.
  Root cause: backend /api/articles endpoint does not apply strict-locale
  filtering on translation presence. Frontend fallback notice (Day 4 /
  Day 7e) only triggers when totalItems === 0, which doesn't fire here.
  Workaround for users: explicit language switcher in header changes URL
  locale consistently. Tracked for S+1 backend fix (add translation-aware
  filter to ArticleProvider).

- **PHPStan baseline drift**: 644 suppressions (vs 22 in ADR-022).
  Triaged in Day 3 into 4 buckets; 5-sprint roadmap deferred. Tracked in
  vault/50_Audit/phpstan-baseline-analysis-2026-04-24.md.

- **Failed messages queue**: 139 messages deferred from DI drift S57.P2a
  transitional state. Triage doc in vault/50_Audit/sprint-58-recovery-
  day2-failed-triage.md. Recommended cleanup: re-translate 19 translation
  failures via `app:translate:articles --force`, evict 120 editorial
  failures (transports suspended).

- **Cost instrumentation gap on LlmAgentCallLog**: cost_usd and token
  counts recorded as 0 across all 76+ rows despite schema support.
  Blocks ADR-025 reactivation cost model. Priority S+1 fix.

- **CI infrastructure stale**: GitHub Actions workflows failing on develop since
  2026-04-21 (pre-v1.4.0). Root causes: backend workflow missing .env copy step
  before composer install; frontend workflow missing `cd apps/frontend` before
  pnpm install. Not a code issue — local verification suffices for v1.4.0.
  Priority S+1 fix (trivial, ~2 one-line patches to workflow files).

### v1.2.0 — 2026-04-06

- **Title**: Complete Aggregator System (Sprints 24-26)
- **Branch**: `main`
- **Highlights**: 82 aggregator sources, 10 Moldova-specific scrapers,
  PressRelease universal gateway, content cleaning pipeline, URL dedup.

---

## Reserved / orphan tags — DO NOT DEPLOY

The following tags exist in `git tag` but are not production releases.
They were cut on a side branch that did not return to mainline. The
underlying work landed via different paths and is part of v1.2.0+. Do
not deploy these tags; they are kept only for historical reference.

| Tag | Date | Source branch | Underlying work |
|---|---|---|---|
| `v1.3.0`     | 2026-04-07 | `bugfix/story-clusters-frontend-debt-t52` | Sprint 27 Per-Locale Publishing (commit `c8ac7a45`) |
| `v1.3.0-rc2` | 2026-04-08 | _same_ | Sprint 31 Clustering Fix |
| `v1.3.0-rc3` | 2026-04-08 | _same_ | Sprint 32 Score Calibration |

Versioning resumes monotonically at **`v1.4.0`** (Sprint 58-Recovery).
The `v1.3.x` namespace is permanently retired.

Reference: [ADR-026 §D1.alt](20_Architecture/Decisions/ADR-026-v1.3.0-recovery-tag-strategy.md)
documents why the `v1.4.0` skip-strategy was chosen over force-retag,
`v1.3.1` (patch-on-orphan), `v1.2.1` (patch-on-prod), and `v2.0.0`.

---

## Sprint release candidates

These are intermediate tags cut at sprint boundaries during ongoing
development. Useful for rollback to a known-working sprint state, but
typically superseded by the next production release.

### v1.2.0-rc series
- `v1.2.0-rc.sprint57-pivot` — current `develop` head reference
- `v1.2.0-rc1`

### v1.1.0-rc series — Editorial pipeline development (S53–S56)
- `v1.1.0-rc.sprint56`
- `v1.1.0-rc.sprint55`
- `v1.1.0-rc.sprint54`
- `v1.1.0-rc.sprint53`
- `v1.1.0-rc1`

### v1.0.0-rc series — Pre-monorepo / early platform (S49–S52)
- `v1.0.0-rc.sprint52`
- `v1.0.0-rc.sprint51c`
- `v1.0.0-rc.sprint51a`
- `v1.0.0-rc.sprint49`
- `v1.0.0-rc1` / `v1.0.0-rc2` / `v1.0.0-rc3`
- `v1.0.0-monorepo`

---

## Tag naming conventions (for future releases)

- **Production**: `vMAJOR.MINOR.PATCH` (semver, no suffix). Cut from
  `main` after a release branch finishes.
- **Sprint RC**: `vMAJOR.MINOR.PATCH-rc.sprintNN[suffix]`. Cut from
  `develop` (or a release branch) at sprint boundary. NOT for production.
- **Sprint RC unsuffixed**: `vMAJOR.MINOR.PATCH-rcN` (older convention,
  used for the v1.0.0 → v1.1.0 series). Avoid for new tags; use the
  sprint-suffixed form.
- **Bumping rules**:
  - PATCH: bug fixes only, no schema/API changes, no posture changes.
  - MINOR: feature additions, posture changes, schema additions.
  - MAJOR: breaking API/contract changes, multi-sprint platform inflections.
- **Skip-version protocol**: when a version namespace is contaminated
  (e.g. orphaned tags on side branches), document the skip in this file
  and resume at the next clean integer (as done with v1.3.x → v1.4.0).

---

## Deploy reference

| Environment | Source | Tag selection |
|---|---|---|
| Production (FRA1) | `main` HEAD | Most recent "Production release" entry above |
| Staging | `develop` HEAD | Most recent `vX.Y.Z-rc.sprintNN` |
| Local dev | `feature/*` | N/A — branch HEAD |

[ADR-025]: /mnt/c/Users/Radu/DeschideVault/20_Architecture/Decisions/ADR-025-editorial-pipeline-suspended.md
[ADR-026]: /mnt/c/Users/Radu/DeschideVault/20_Architecture/Decisions/ADR-026-v1.3.0-recovery-tag-strategy.md
