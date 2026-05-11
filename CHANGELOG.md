# Changelog

All notable changes to Deschide.md (`deschide_news_app`) are documented
here from v2.0.0 onward. Pre-v2.0.0 release notes live in annotated git
tag messages — see `git tag --list 'v[0-9]*' --sort=-creatordate` and
`git show <tag>`.

Format: based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Versioning: [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

— nothing pending —

## [v2.0.3] — 2026-05-11

### Fixed
- `scripts/deploy.sh` — 7 latent defects discovered during Phase 3
  v2.0.2 staging dry-run (TSK-782), all surfaced once `backup-database.sh`
  was unblocked (v2.0.2 hotfix). Three affect both dev and FRA1
  environments; four are dev-environment artifacts but harmless on
  prod (guarded by APP_ENV checks).

  **Universal fixes (affect FRA1):**
  - `PHP_FPM_SERVICE` default was `php8.4-fpm`; project runs PHP 8.5.3.
    Changed default to `php8.5-fpm`. `systemctl reload php-fpm` would
    have failed silently on FRA1 with the wrong service name.
  - `composer install --no-dev --classmap-authoritative` now includes
    `--no-scripts`. Prevents post-install hook crashes (cache:clear
    invocation needing dev deps that aren't installed).
  - `((SMOKE_PASS++))` and `((SMOKE_FAIL++))` (11 sites) refactored to
    `VAR=$((VAR + 1))`. **Real bash bug**: post-increment on var
    starting at 0 returns 0 (old value), arithmetic exit code 1,
    `set -e` aborts the script. Would have aborted FRA1 deploy at the
    first smoke check increment.

  **Dev-only patches (guarded; harmless on FRA1):**
  - Explicit `--env=prod` on `doctrine:migrations:migrate` and
    `messenger:stats`. On FRA1 (`APP_ENV=prod`) this is redundant but
    safe. On dev (`APP_ENV=dev`) it forces prod-context execution.
  - Pre-`pnpm install` removal of stale `apps/frontend/public/tinymce`
    symlink that causes EINVAL on dev machines.
  - Post-backend-deploy conditional `composer install` (restore dev
    deps) — only fires when `grep -q 'APP_ENV=dev' .env.local` matches.
    On FRA1 the grep returns no match → block skipped.

### Notes
- Discovered via Phase 3 v2.0.2 staging dry-run (GO verdict after
  patches applied). All 7 fixes validated end-to-end on WSL: backup
  created (430K), Redis FLUSHDB ran, B1–B6 all PASS, T60.8 24-probe
  matrix 72/72 PASS, 30-min HTTP-poll monitor 30 checks / 0 anomalies.
- v2.0.3 patch bump on v2.0.2. No app code changes, no DB migrations.
- Workflow note: agent applied these patches inline during testing
  rather than stopping at first failure. Outcome positive but contract
  was softened — documented as Sprint 60 retrospective lesson.

## [v2.0.2] — 2026-05-11

### Fixed
- `scripts/backup-database.sh` — script was unrunnable as deploy.sh
  child-process: (1) `log_error` called at lines 22–24 before being
  defined at line 52 → `command not found`; (2) hardcoded credentials
  (`deschide` DB / `deschide_admin` user / port `6432`) did not match
  actual topology (`deschide_news` / `deschide_user` / `5432`);
  (3) required `PGPASSWORD` exported externally but `deploy.sh` did
  not export it. Resolved by reordering `log_*()` defs to top of file,
  parsing `DATABASE_URL` from environment or `apps/backend/.env.local`,
  and supporting `BACKUP_DB_*` overrides. Verified standalone on WSL:
  430K compressed backup, 97.7% compression, real stats (67 articles).

### Notes
- Phase 1 review (PR #25 A1) missed this because `deploy.sh --dry-run`
  emits `[DRY-RUN]` lines instead of invoking `backup-database.sh`.
  Future deploy.sh hardening reviews should include a real-mode
  child-process invocation test.
- v2.0.2 patch bump on v2.0.1. No app-code changes; no DB migrations.
  Tag lives on `develop`. Main advancement remains deferred until
  FRA1 production deploy successful.

## [v2.0.1] — 2026-05-11

### Deployment requirements

**`--confirm-flush` is auto-detected.** If migrations newer than
`v2.0.0` contain `DROP TABLE` or `DROP COLUMN`, `scripts/deploy.sh`
aborts unless `--confirm-flush` is passed. For v2.0.1 specifically, no
new destructive migrations exist — the flag is informational only.

`scripts/deploy.sh` now reloads supervisor configs and probes queue
health automatically. If `supervisorctl` is not on PATH, the reload
step is skipped with a warning.

### Fixed
- `apps/backend/config/supervisor/messenger-async.conf` — removed
  references to deleted `scraping` and `python_scraper` transports
  (PR #21 collateral). Latent on dev WSL via `autostart=false`; would
  manifest on FRA1 with `autostart=true`.

### Added
- `scripts/deploy.sh` Step 4: `supervisorctl reread + update` so
  supervisor config edits land in the running daemon at deploy time.
- `scripts/deploy.sh` Step 4: explicit `cache:pool:clear cache.app` and
  `cache:pool:clear cache.system_clearer` between migrations and cache
  warmup.
- `scripts/deploy.sh` Step 6: `messenger:stats` queue-health probe
  asserting `translations` transport is present.
- `scripts/deploy.sh` pre-Step-1: auto-detection of entity-removal
  migrations since last release tag; requires `--confirm-flush` flag.
- This `CHANGELOG.md` itself.

### Notes
- `main` branch advancement deferred until FRA1 production deploy
  success. `v2.0.1` tag lives on `develop` until then.
- `T60.X-NUKE-EDITORIAL-FRONTEND` (PR #24) shipped as part of this
  release line — see PR #24 for details.

## [v2.0.0] — 2026-05-08

### Removed (BREAKING CHANGE)

Editorial pipelines retired; system reverts to manual article
publishing via admin UI / RSS importer with automatic translation.

- 492+ PHP files across Service/, Message/, MessageHandler/, Command/,
  Controller/, Entity/, Repository/, Enum/, Dto/, EventListener/,
  EventSubscriber/, Scheduler/, State/, DataFixtures/, ValueObject/,
  tests/
- 15 DB tables: `press_releases`, `press_release_topics`,
  `source_signals`, `editorial_escalation_log`, `topic_briefings`,
  `aggregator_runs`, `source_claim_history`, `verified_sources`,
  `sources`, `curation_suggestions`, `relevance_keywords`,
  `generated_content`, `ai_conversations`, `ai_messages`,
  `ai_prompt_templates`
- 13 columns dropped across `articles` and `topics`
- 9 messenger transports, 5 schedule providers, ~25 supervisor configs
- 61 AppSettings keys (4 retained: `agent.emergency_halt` and 3
  `agent.journalistic_translator.*`)

### Retained

- Translation pipeline (`TranslateArticleMessage` + handler, 5 priority
  transports)
- Topics taxonomy (164 rows, slim `Topic` entity, public pages)
- AI infrastructure (`AgentDispatcher`, `EmergencyHaltException`,
  `LlmRetryExecutor`, `LlmInvocationLogger`)

### Migration

`Version20260508063850` + `Version20260508075043`. Irreversible at data
level. Manual FK ordering correction applied (see PR #21).

### References

- PR: [#21](https://github.com/radusoltan/deschide_news_app/pull/21)
- ADR: ADR-035 (retroactive author task in flight)
- Supersedes: ADR-008, ADR-009, ADR-010, ADR-011, ADR-016, ADR-020

[Unreleased]: https://github.com/radusoltan/deschide_news_app/compare/v2.0.3...HEAD
[v2.0.3]: https://github.com/radusoltan/deschide_news_app/compare/v2.0.2...v2.0.3
[v2.0.2]: https://github.com/radusoltan/deschide_news_app/compare/v2.0.1...v2.0.2
[v2.0.1]: https://github.com/radusoltan/deschide_news_app/compare/v2.0.0...v2.0.1
[v2.0.0]: https://github.com/radusoltan/deschide_news_app/releases/tag/v2.0.0
