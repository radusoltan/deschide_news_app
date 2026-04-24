# Test database — setup & recovery

The `test` Doctrine environment has its own Postgres database
(`deschide_news_test` locally, fresh container in CI). This doc covers how
it's initialized, how to recover if it drifts out of sync with migrations,
and why the CI pipeline is immune.

## CI (backend-ci.yml)

CI runs on a fresh `postgres:17` service container per job:

```yaml
- name: Create database schema
  working-directory: apps/backend
  run: |
    php bin/console doctrine:database:create --env=test --if-not-exists
    php bin/console doctrine:migrations:migrate --env=test --no-interaction --allow-no-migration
```

Because the container is wiped between runs, CI always sees a clean DB and
runs every migration in order. There is nothing to maintain.

## Local test DB

Radu's WSL Postgres keeps `deschide_news_test` persistent. Nothing
automatically re-migrates it after a `git pull` brings new migrations, so
it can drift out of sync with `src/Entity/*` mappings.

### Recovering a drifted local test DB

If `vendor/bin/phpunit` fails with `SQLSTATE[42703] Undefined column …` or
`doctrine:migrations:status --env=test` shows any "New" migrations, rebuild
from scratch:

```bash
./apps/backend/scripts/reset-test-db.sh
```

The script mirrors the CI steps:

1. Drops `deschide_news_test` (if present).
2. Recreates it.
3. Runs `doctrine:migrations:migrate --no-interaction` against `--env=test`.
4. Runs `doctrine:schema:validate`.

Pass `--yes` / `-y` to skip the "proceed?" prompt (useful in other scripts).

### When to run it

- After `git pull` that touches `apps/backend/migrations/`.
- After switching to a feature branch with new entity changes.
- Whenever `doctrine:migrations:status --env=test` reports `New > 0`.

### Do NOT

- Do NOT use `doctrine:schema:update --force --env=test` as a shortcut.
  It syncs the schema but leaves `doctrine_migration_versions` stale, which
  causes later `migrations:migrate` runs to fail with "relation already
  exists" errors. If the DB is already in this state, run the reset script
  above to start clean.

## Background

See `50_Audit/test-db-drift-investigation.md` (Obsidian vault) for the
Sprint 51a post-fix root-cause analysis and alternative recovery strategies
that were considered.
