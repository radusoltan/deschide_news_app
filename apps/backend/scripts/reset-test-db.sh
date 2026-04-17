#!/usr/bin/env bash
#
# reset-test-db.sh — rebuild the local test database from migrations
#
# Mirrors the CI pipeline (.github/workflows/backend-ci.yml) so local
# functional tests always see the same schema as CI.
#
# Usage:
#   ./apps/backend/scripts/reset-test-db.sh
#
# Safety:
#   * Refuses to run against any env other than `test` (APP_ENV is hard-coded).
#   * Confirms before dropping unless --yes/-y is passed.
#
# See: 50_Audit/test-db-drift-investigation.md (Sprint 51a follow-up)

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

AUTO_CONFIRM="no"
for arg in "$@"; do
    case "$arg" in
        -y|--yes) AUTO_CONFIRM="yes" ;;
        -h|--help)
            sed -n '2,20p' "${BASH_SOURCE[0]}"
            exit 0
            ;;
        *) echo "Unknown flag: $arg" >&2 ; exit 2 ;;
    esac
done

cd "$BACKEND_DIR"

if [[ "$AUTO_CONFIRM" != "yes" ]]; then
    echo "About to DROP and RECREATE the 'test' Doctrine database."
    echo "All data in that DB will be lost."
    read -r -p "Proceed? [y/N] " reply
    case "$reply" in
        y|Y|yes|YES) ;;
        *) echo "Aborted."; exit 1 ;;
    esac
fi

export APP_ENV=test

echo "→ Dropping test DB (ignores if already absent)"
symfony console doctrine:database:drop --force --if-exists

echo "→ Creating fresh test DB"
symfony console doctrine:database:create

echo "→ Running all migrations"
symfony console doctrine:migrations:migrate --no-interaction

echo "→ Validating schema"
symfony console doctrine:schema:validate

echo "✓ Test DB rebuilt from migrations. Run 'vendor/bin/phpunit' to verify."
