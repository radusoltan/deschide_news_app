#!/usr/bin/env bash
# Staging baseline snapshot capture
# Usage: ./scripts/capture-staging-baseline.sh [--env=staging|dev] [--output=<path>]
# Default: --env=staging, --output=50_Audit/sprint-57-baseline-capture-<date>.md
#
# Read-only. Captures 7 dimensions for diff-against-post-SV comparisons:
# 1. AppSettings (full dump for target env)
# 2. Doctrine migrations status
# 3. Supervisor group state (env-specific programs)
# 4. DB state (table count, article count, user count)
# 5. Redis keyspace (DB index per env)
# 6. Elasticsearch indices + doc counts (prefix per env)
# 7. Environment fingerprint (APP_ENV resolution, git commit, timestamp)

set -euo pipefail

# --- Arg parsing ---
ENV_NAME="staging"
OUTPUT_PATH=""
REDIS_DB=2
SUPERVISOR_GROUP="staging"
ES_PREFIX="staging_deschide"
DB_NAME="deschide_staging"
DB_PORT=6432  # pgbouncer for staging

for arg in "$@"; do
    case $arg in
        --env=*)
            ENV_NAME="${arg#*=}"
            ;;
        --output=*)
            OUTPUT_PATH="${arg#*=}"
            ;;
        *)
            echo "Unknown arg: $arg" >&2
            echo "Usage: $0 [--env=staging|dev] [--output=<path>]" >&2
            exit 1
            ;;
    esac
done

# Adjust per-env params
case "$ENV_NAME" in
    staging)
        REDIS_DB=2
        SUPERVISOR_GROUP="staging"
        ES_PREFIX="staging_deschide"
        DB_NAME="deschide_staging"
        DB_PORT=6432
        ;;
    dev)
        REDIS_DB=1
        SUPERVISOR_GROUP=""  # dev has no group, individual programs
        ES_PREFIX="deschide"
        DB_NAME="deschide_news"
        DB_PORT=5432
        ;;
    *)
        echo "Unsupported env: $ENV_NAME (use staging or dev)" >&2
        exit 1
        ;;
esac

# Default output path
if [ -z "$OUTPUT_PATH" ]; then
    OUTPUT_PATH="50_Audit/sprint-57-baseline-capture-$(date +%Y-%m-%d).md"
fi

# Ensure output dir exists
mkdir -p "$(dirname "$OUTPUT_PATH")"

# --- Environment verification before capture ---
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
if [ ! -f "$REPO_ROOT/apps/backend/symfony.lock" ]; then
    echo "ERROR: repo root not detected at $REPO_ROOT" >&2
    exit 2
fi

cd "$REPO_ROOT"

# --- Begin capture ---
TIMESTAMP=$(date -Iseconds)
GIT_COMMIT=$(git rev-parse HEAD)
GIT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
GIT_DIRTY=$(git diff --quiet && git diff --cached --quiet && echo "clean" || echo "dirty")

{
    echo "# Staging Baseline Snapshot"
    echo ""
    echo "**Environment:** \`$ENV_NAME\`"
    echo "**Captured:** $TIMESTAMP"
    echo "**Git commit:** \`$GIT_COMMIT\` (branch: \`$GIT_BRANCH\`, $GIT_DIRTY)"
    echo ""
    echo "Pure read-only capture. No mutations performed."
    echo ""

    echo "---"
    echo ""
    echo "## 1. AppSettings (full dump)"
    echo ""
    echo '```'
    cd apps/backend
    APP_ENV="$ENV_NAME" symfony console app:settings:list --no-interaction 2>&1 | \
        head -500 || echo "(AppSettings read failed — continuing)"
    cd - > /dev/null
    echo '```'
    echo ""

    echo "---"
    echo ""
    echo "## 2. Doctrine migrations status"
    echo ""
    echo '```'
    cd apps/backend
    APP_ENV="$ENV_NAME" symfony console doctrine:migrations:status --no-interaction 2>&1 | \
        head -30 || echo "(migrations status failed)"
    cd - > /dev/null
    echo '```'
    echo ""

    echo "---"
    echo ""
    echo "## 3. Supervisor state"
    echo ""
    echo '```'
    # Note: supervisorctl returns non-zero when any process is STOPPED, so we
    # swallow the exit code — the text output is what we care about.
    if [ -n "$SUPERVISOR_GROUP" ]; then
        { sudo supervisorctl status "$SUPERVISOR_GROUP:*" 2>&1 || true; } | sort
    else
        # Dev env: no group, filter by name pattern
        { sudo supervisorctl status 2>&1 || true; } | grep -v -- "-staging" | sort
    fi
    echo '```'
    echo ""

    echo "---"
    echo ""
    echo "## 4. Database state (table + row counts)"
    echo ""

    # Use dbal:run-sql for portability (doctrine:query:sql removed in 3.x)
    # The output is a multi-row ascii table; the numeric count is the only integer
    # on a non-dash line — take the last integer in the last 5 lines.
    run_count() {
        local sql="$1"
        ( cd apps/backend && APP_ENV="$ENV_NAME" symfony console dbal:run-sql "$sql" \
            --no-interaction 2>&1 ) | tail -5 | grep -oE '[0-9]+' | tail -1 || echo "?"
    }

    TABLE_COUNT=$(run_count "SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema='public'")
    ARTICLE_COUNT=$(run_count "SELECT COUNT(*) AS c FROM articles")
    USER_COUNT=$(run_count 'SELECT COUNT(*) AS c FROM "user"')
    LLM_ROW_COUNT=$(run_count "SELECT COUNT(*) AS c FROM llm_agent_call_log")
    TABLE_COUNT="${TABLE_COUNT:-?}"
    ARTICLE_COUNT="${ARTICLE_COUNT:-?}"
    USER_COUNT="${USER_COUNT:-?}"
    LLM_ROW_COUNT="${LLM_ROW_COUNT:-?}"

    echo "| Metric | Count |"
    echo "|---|---|"
    echo "| DB name | \`$DB_NAME\` |"
    echo "| Tables (public schema) | $TABLE_COUNT |"
    echo "| Articles | $ARTICLE_COUNT |"
    echo "| Users | $USER_COUNT |"
    echo "| LlmAgentCallLog rows | $LLM_ROW_COUNT |"
    echo ""

    echo "---"
    echo ""
    echo "## 5. Redis keyspace (DB $REDIS_DB)"
    echo ""
    echo '```'
    redis-cli -n "$REDIS_DB" DBSIZE 2>&1 || echo "(Redis read failed)"
    redis-cli -n "$REDIS_DB" INFO keyspace 2>&1 | head -10 || true
    echo '```'
    echo ""

    echo "---"
    echo ""
    echo "## 6. Elasticsearch indices (prefix: \`${ES_PREFIX}_*\`)"
    echo ""
    echo '```'
    # Read ES credentials from env (NEVER echo values)
    ES_USER=$(grep -E "^ELASTICSEARCH_USER=" "apps/backend/.env.local" 2>/dev/null | cut -d= -f2- || echo "elastic")
    ES_PASS=$(grep -E "^ELASTICSEARCH_PASSWORD=" "apps/backend/.env.local" 2>/dev/null | cut -d= -f2- || echo "")

    if [ -n "$ES_PASS" ]; then
        curl -sk -u "$ES_USER:$ES_PASS" \
            "https://localhost:9200/_cat/indices/${ES_PREFIX}_*?v&s=index" 2>&1 | head -20
    else
        echo "(ES credentials not readable — skipping index list)"
    fi
    echo '```'
    echo ""

    echo "---"
    echo ""
    echo "## 7. Environment fingerprint"
    echo ""
    echo '```'
    echo "APP_ENV: $ENV_NAME"
    echo ""

    # Safe env-var fingerprint via debug:dotenv.
    # Filter to non-credential keys; redact DSN/URL passwords defensively.
    cd apps/backend
    SAFE_KEYS='^ *(APP_ENV|APP_URL|FRONTEND_URL|ELASTICSEARCH_INDEX_PREFIX|CORS_ALLOW_ORIGIN|MAILER_DSN|MESSENGER_TRANSPORT_DSN|REDIS_URL|DATABASE_URL|MERCURE_URL|MERCURE_PUBLIC_URL)  '
    APP_ENV="$ENV_NAME" symfony console debug:dotenv --no-interaction 2>&1 | \
        grep -E "$SAFE_KEYS" | \
        awk '{
            # Col1 = key, Col2 = resolved value. Redact DSN passwords defensively.
            key=$1; val=$2;
            gsub(/:\/\/[^:@[:space:]]+:[^@[:space:]]+@/, "://***:***@", val);
            printf "%-30s %s\n", key, val;
        }' | head -20 || echo "(dotenv read returned no safe keys)"
    cd - > /dev/null

    # Service versions
    echo ""
    echo "Service versions:"
    php --version | head -1
    node --version 2>&1
    redis-cli --version 2>&1
    psql --version 2>&1
    echo '```'
    echo ""

    echo "---"
    echo ""
    echo "**End of baseline snapshot.**"
    echo ""
    echo "Diff against this baseline post-SV scenarios via:"
    echo "\`\`\`bash"
    echo "./scripts/capture-staging-baseline.sh --env=$ENV_NAME --output=/tmp/post-sv.md"
    echo "diff $OUTPUT_PATH /tmp/post-sv.md"
    echo "\`\`\`"

} > "$OUTPUT_PATH"

echo "Baseline captured: $OUTPUT_PATH"
echo "Size: $(wc -l "$OUTPUT_PATH" | awk '{print $1}') lines"
