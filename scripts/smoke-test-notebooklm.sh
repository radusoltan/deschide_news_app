#!/bin/bash
# Smoke test for NotebookLM integration (Sprint 51a)
# Run from apps/backend/ directory

set -e

echo "=== NotebookLM Smoke Test ==="
echo ""

# 1. Schema validation
echo "[1/6] Schema validation..."
symfony console doctrine:schema:validate --quiet && echo "  OK" || echo "  FAIL"

# 2. AppSettings count
echo "[2/6] AppSettings count..."
COUNT=$(symfony console dbal:run-sql "SELECT COUNT(*) as c FROM app_settings WHERE key LIKE 'notebooklm.%'" 2>/dev/null | grep -oP '\d+')
if [ "$COUNT" = "6" ]; then
    echo "  OK — 6 notebooklm.* keys found"
else
    echo "  FAIL — expected 6, found $COUNT"
fi

# 3. Scheduler task count
echo "[3/6] Scheduler tasks..."
TASK_COUNT=$(symfony console debug:scheduler editorial 2>/dev/null | grep -c "App\\\\Message")
if [ "$TASK_COUNT" = "16" ]; then
    echo "  OK — 16 scheduled tasks"
else
    echo "  WARN — expected 16, found $TASK_COUNT"
fi

# 4. Command registration
echo "[4/6] Command registration..."
symfony console app:topic:sync-notebooks --help > /dev/null 2>&1 && echo "  OK — app:topic:sync-notebooks registered" || echo "  FAIL"

# 5. Dry run sync
echo "[5/6] Dry-run sync..."
symfony console app:topic:sync-notebooks --dry-run --no-interaction 2>/dev/null && echo "  OK" || echo "  FAIL"

# 6. API route check
echo "[6/6] Fact-check route..."
ROUTE=$(symfony console debug:router admin_article_factcheck 2>/dev/null | grep "Path" || echo "")
if echo "$ROUTE" | grep -q "factcheck"; then
    echo "  OK — POST /api/admin/articles/{id}/factcheck"
else
    echo "  FAIL — route not found"
fi

echo ""
echo "=== Smoke test complete ==="
