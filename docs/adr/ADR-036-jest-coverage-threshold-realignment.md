---
adr: 36
title: Jest coverage threshold realignment
status: Accepted
date_proposed: 2026-05-08
date_decided: 2026-05-08
related: "T60.23, T60.26, T60.27, T60.28, ADR-034 (gate-honesty precedent), ADR-035 (Nuke Editorial Pipelines, concurrent)"
author: "Radu (decided) + Claude Opus 4.7 (proposed during Phase D Layer 5)"
---
# ADR-036 — Jest coverage threshold realignment

## Status

Accepted (decided 2026-05-08).

## Context

Frontend Jest coverage threshold (global 70/70/70/70 across statements,
branches, functions, lines) was empirically unreachable for this codebase
architecture. Empirical floor measurements 2026-05-08 (Sprint 60, Phase D
Layer 5 investigation):

| Scope | Statements | Branches | Lines | Functions |
|---|---|---|---|---|
| Whole codebase, no exclusions | 9.6% | 10.39% | 9.78% | 8.9% |
| + zero-test path exclusions (12 paths) | 10.5% | 11.2% | 10.75% | 9.57% |
| + `components/admin/**` exclusion | 11.17% | 11.94% | 11.41% | 10.46% |
| `lib/**` + `components/**` only (drop all `app/**`) | 12.03% | 13.3% | 12.31% | 10.42% |

Reaching 70% requires 7-10× current test count (months of work). Even
excluding the entire `app/**` route tree caps at ~12% — bulk of zero-coverage
code is `components/**` UI categories (admin, hero, banners, social, ui kit,
etc.) plus `lib/**` infrastructure paths (auth, data, errors, hooks,
middleware) that haven't grown test scaffolding alongside the codebase.

The threshold was never enforced because frontend CI was broken from at
least 2026-04-30 onward. Phase D Sprint 60 investigation chained discovery:

1. **Layer 1** (T60.23) — `pnpm-workspace.yaml` shape rejected by pnpm 9.x
2. **Layer 2** (T60.23) — floating `version: 9` resolved to broken pnpm 9.x
3. **Layer 3** (T60.26) — React 19 strict-mode lint errors in `Header.tsx`
   and `locale-context-provider.test.tsx` (7 errors)
4. **Layer 4** (T60.27) — Unicode `…` vs ASCII `...` ellipsis test/source
   drift (2 failing tests)
5. **Layer 5 (this ADR)** — coverage threshold misconfigured at codebase
   scale

Each layer was masked by the layer above. Layer 5 surfaced only after
Layers 1-4 closed. Same temporal coupling pattern as ADR-034 PHPStan
baseline (which surfaced after T60.19 closed CI bootstrap layers).

This is NOT a deferred-debt situation. It is a gate **misconfigured at
inception** for a Next.js Server Components-heavy architecture. The
`app/[locale]/**` route tree is legitimately tested via Playwright E2E,
not Jest unit — including it in the Jest coverage denominator was a
category mismatch from day one.

## Decision

Realign the Jest coverage gate using two orthogonal mechanisms.

### 1. Surgical `collectCoverageFrom` exclusions

Exclude paths with empirically verified zero unit tests AND no realistic
unit-test target. These paths are removed from the coverage denominator
because including them produces noise, not signal:

- `app/api/**` — Next.js API route handlers, integration-tested via E2E
- `lib/services/**` — server-only services (cache-manager, slug-resolver)
- `lib/react-query/**` — query hooks, tested via integration scenarios
- `lib/types/**` — type-only files (no runtime logic)
- `lib/utils/{analyticsTracker,soundNotifications,redirect-utils,url-parser,socialMetadata}.ts` — third-party integration utilities
- `lib/seo/{og-image-generator,structured-data,schema-org-global,seo-config}.ts` — JSON-LD generators (P1 ratchet candidates: pure functions, easy to test, currently untested debt)
- `components/admin/**` — legacy admin zone, deprecated path

Exclusions are **explicit in `jest.config.mjs`** (not via `/* istanbul
ignore */` per-file). Adding to this list requires an ADR amendment AND
empirical justification (zero tests + no realistic test target).

### 2. Threshold realigned to empirical floor + ratchet ramp

**Initial threshold (Sprint 60, this ADR):** `10 / 10 / 9 / 10` (statements
/ branches / functions / lines), matching post-exclusion actuals
`11.17 / 11.94 / 10.46 / 11.41` with ~1-2 pp margin.

**Ratchet schedule (T60.28 partner task):**

| Sprint | Threshold target | Required uplift mechanism |
|---|---|---|
| 60 (init) | 10 / 10 / 9 / 10 | Empirical floor — this commit |
| 61 | 15 / 15 / 14 / 15 | New unit tests on `lib/auth` or `lib/data` |
| 62 | 20 / 20 / 19 / 20 | `components/cards` expansion or `lib/hooks` |
| 63 | 25 / 25 / 24 / 25 | `components/navigation` or `lib/middleware` |
| 64 | 30 / 30 / 29 / 30 | `app/[locale]` route components (server/client split) |
| 65 | 35 / 35 / 34 / 35 | Final init horizon — re-ADR for next phase |

**Hard rule:** Threshold increase requires NEW unit tests landing in the
same sprint. Cannot bump artificially. If a sprint adds no tests, the
threshold STAYS — no silent ratchet.

## Consequences

### Allowed
- Gate runs honestly: 11% actuals vs 10% threshold = ~1pp regression-detection
  buffer. A drop below 10% triggers CI fail.
- Future commits that ADD test coverage above the current threshold are
  free to land without ADR amendment.
- Sprint 61-65 ratchet bumps (per T60.28) are documented increments, each
  paired with new tests.

### Forbidden
- Adding paths to the exclusion list to "keep the gate green" without ADR
  amendment + empirical zero-test justification.
- Lowering the threshold mid-sprint without updating T60.28 ratchet
  schedule.
- Per-file `/* istanbul ignore */` annotations (semantic disable, hides
  state — same anti-pattern as `// eslint-disable-next-line` per ADR-034
  spirit).
- Removing `--coverage` from the CI Jest invocation (drops the gate
  entirely).
- Bumping threshold without simultaneously merging tests that justify the
  bump.

### Detection
- CI runs `jest --ci --coverage --maxWorkers=2` on every PR; threshold
  failure blocks merge to `develop` / `main`.
- Sprint planning includes a coverage check: confirm last sprint's ratchet
  target was met (T60.28 sprint cadence).
- Quarterly audit: empirical actuals vs threshold delta. If actuals drift
  ≥3pp above threshold without a ratchet bump, flag in sprint review.

## Reverse-decision triggers

This ADR must be revisited (and likely superseded) if any of the following
becomes true:

- **Three consecutive sprints without ratchet progress** — escalate via
  re-ADR. Either the ramp slope is unrealistic, or product/sprint priority
  is structurally deprioritising tests. Either way, re-decide.
- **Migration to a different test runner** (Vitest, Playwright Component
  Testing, etc.) — invalidates current threshold semantics, full re-ADR.
- **Product decision to deprecate Jest unit testing in favor of E2E only**
  — drop the gate entirely with a successor ADR documenting the
  replacement E2E coverage targets.
- **Achievable threshold reaches 50%+** — revisit ramp slope (likely
  flatten to +2-3% per sprint as diminishing-returns sets in).
- **Discovery that an excluded path was wrongly classified** (e.g.,
  someone writes tests for `lib/services/**` but exclusion masks them) —
  fix exclusion + amend ADR.

## Comparison to alternatives considered

- **(D-pure) Lower threshold to 10/10/10/9 without exclusions.** Rejected:
  keeps zero-test paths in denominator, producing noise instead of signal.
  Forces ratchet to fight code categories that legitimately won't be
  unit-tested (types, generators).
- **(E-massive) Exclude ~25 components/** sub-folders + ~10 lib/** sub-folders + most app/[locale]/** routes to keep 70%.** Rejected: gate
  scope collapses to a small island ("paper tiger inverted") — looks strict
  but tests almost nothing of the visible product.
- **(F) Drop `--coverage` from CI.** Rejected: loses regression-detection
  on coverage entirely. Per ADR-034 spirit (preserve gates over remove).
- **(G) Custom soft-fail reporter (warnings instead of errors).** Rejected:
  engineering overhead for marginal value over (D-revisited).

## References

- T60.23 — pnpm-workspace + version pin (Layers 1, 2)
- T60.26 — React 19 strict-mode lint refactor (Layer 3)
- T60.27 — Unicode ellipsis test alignment (Layer 4)
- T60.28 — Jest coverage ratchet partner (Sprint 61-65)
- ADR-034 — PHPStan baseline regeneration policy (gate-honesty precedent)
- Phase D Sprint 60 execution log: `50_Audit/sprint-60-execution-log.md`
- Phase D layered failure pattern finding: 5 layers, all temporal-coupled,
  all visible only post-upstream-clear. Documented as pattern for future
  audits ("CI green on a gate one step ahead masks N layers of pre-existing
  debt").
